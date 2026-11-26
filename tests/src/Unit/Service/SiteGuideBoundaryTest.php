<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\dungeoncrawler_content\Service\SiteGuide\DungeonCrawlerSiteGuideProvider;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideHarnessService;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideMcpGatewayService;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuidePersonaResolver;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideProviderInterface;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideProviderRegistry;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers the Site Guide permission and handoff boundaries.
 *
 * @group dungeoncrawler_content
 * @group site_guide
 */
final class SiteGuideBoundaryTest extends UnitTestCase {

  public function testAnonymousProviderExposesNoSpecialistHandoffs(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(TRUE);
    $account->method('hasPermission')->willReturn(FALSE);

    $provider = new DungeonCrawlerSiteGuideProvider();

    $this->assertSame([], $provider->personas($account, []));
    $this->assertNotContains(TRUE, array_column($provider->handoffs($account, []), 'available'));
  }

  public function testCreatorPersonaGetsEditorButNoGmTools(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => $permission === 'edit canonical dungeoncrawler rooms',
    );

    $provider = new DungeonCrawlerSiteGuideProvider();
    $personas = $provider->personas($account, []);
    $handoffs = array_column($provider->handoffs($account, []), 'available', 'agent_id');

    $this->assertContains('creator', $personas);
    $this->assertTrue($handoffs['editor_gm']);
    $this->assertFalse($handoffs['runtime_gm_actor']);

    $resolver = $this->resolver($account, $this->createMock(CampaignAuthorizationService::class), []);
    $management = $this->createMock(AgentHarnessManagementService::class);
    $management->method('getBacklogSuggestionToolDescriptor')->willReturn(['tool' => 'submit_backlog_suggestion']);
    $harness = new SiteGuideHarnessService(
      new SiteGuideService($resolver, $management),
      $management,
    );
    $tools = array_keys($harness->listAgentTools());

    $this->assertNotContains('modify_room_state', $tools);
    $this->assertNotContains('publish_room_draft', $tools);
    $this->assertContains('agents.request_handoff', $tools);
  }

  public function testResolverAddsCampaignMembershipFromRoute(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $authorization = $this->createMock(CampaignAuthorizationService::class);
    $authorization->expects($this->once())
      ->method('buildCampaignAccessContext')
      ->with(42, 7)
      ->willReturn([
        'campaign_id' => 42,
        'is_member' => TRUE,
        'can_use_gm_mode' => TRUE,
      ]);

    $resolver = $this->resolver($account, $authorization, [
      'is_member' => TRUE,
      'can_use_gm_mode' => TRUE,
    ]);
    $context = $resolver->resolve(['route' => '/campaigns/42/characters']);

    $this->assertSame(['campaign_gm'], $context['personas']);
  }

  public function testMcpHandoffRequiresAuthorizedScope(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => $permission === 'edit canonical dungeoncrawler rooms',
    );
    $resolver = $this->resolver($account, $this->createMock(CampaignAuthorizationService::class), []);
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('id')->willReturn(7);
    $gateway = new SiteGuideMcpGatewayService($current_user, $resolver, $logger_factory);

    $missing_scope = $gateway->requestHandoff([], 'editor_gm', 'Review a draft', NULL);
    $denied = $gateway->requestHandoff([], 'runtime_gm_actor', 'Request help', '42');
    $accepted = $gateway->requestHandoff([], 'editor_gm', 'Review a draft', 'draft-42');

    $this->assertSame('agent_handoff_scope_required', $missing_scope['reason']);
    $this->assertSame('agent_handoff_not_available_for_current_principal', $denied['reason']);
    $this->assertTrue($accepted['accepted']);
    $this->assertFalse($accepted['mutation_authorized']);
    $this->assertSame('draft-42', $accepted['scope_id']);
  }

  private function resolver(
    AccountInterface $account,
    CampaignAuthorizationService $authorization,
    array $campaign_access,
  ): SiteGuidePersonaResolver {
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('getAccount')->willReturn($account);
    $current_user->method('id')->willReturn(7);
    $current_user->method('isAnonymous')->willReturn($account->isAnonymous());
    $current_user->method('isAuthenticated')->willReturn(!$account->isAnonymous());

    $provider = new class((bool) $account->hasPermission('edit canonical dungeoncrawler rooms'), $campaign_access) implements SiteGuideProviderInterface {
      public function __construct(
        private readonly bool $creator,
        private readonly array $campaignAccess,
      ) {}

      public function id(): string {
        return 'test';
      }

      public function label(): string {
        return 'Test';
      }

      public function personas(AccountInterface $account, array $context): array {
        return !empty($context['campaign_access']['is_member'])
          ? ['campaign_gm']
          : ($this->creator ? ['creator'] : []);
      }

      public function navigation(AccountInterface $account, array $context): array {
        return [];
      }

      public function resources(AccountInterface $account, array $context): array {
        return [];
      }

      public function handoffs(AccountInterface $account, array $context): array {
        return [
          ['agent_id' => 'editor_gm', 'scope' => 'draft', 'available' => $this->creator],
          [
            'agent_id' => 'runtime_gm_actor',
            'scope' => 'campaign_room',
            'available' => !empty($context['campaign_access']['is_member']),
          ],
        ];
      }
    };

    return new SiteGuidePersonaResolver(
      $current_user,
      new SiteGuideProviderRegistry([$provider]),
      $authorization,
    );
  }

}
