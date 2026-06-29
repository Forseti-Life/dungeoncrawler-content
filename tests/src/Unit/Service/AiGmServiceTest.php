<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\AiSessionManager;
use Drupal\dungeoncrawler_content\Service\EncounterBalancer;
use Drupal\dungeoncrawler_content\Service\GameEventLogger;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\NpcService;
use Drupal\dungeoncrawler_content\Service\SessionService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\AiGmService
 * @group dungeoncrawler_content
 * @group service
 */
final class AiGmServiceTest extends UnitTestCase {

  /**
   * @covers ::buildPromptWithSessionContext
   */
  public function testBuildPromptWithSessionContextPrefixesCurrentRequestWhenSessionExists(): void {
    $session_manager = $this->createMock(AiSessionManager::class);
    $session_manager->expects($this->once())
      ->method('buildSessionContext')
      ->with('gm_44', 44, 8)
      ->willReturn('Session context');

    $service = $this->createService($session_manager);
    $result = $service->buildPromptWithSessionContextForTest('Prompt payload', 'gm_44', 44, 8);

    $this->assertSame("Session context\n\n---\nCURRENT REQUEST:\nPrompt payload", $result);
  }

  /**
   * @covers ::buildPromptWithSessionContext
   */
  public function testBuildPromptWithSessionContextReturnsOriginalPromptForNonCampaignScope(): void {
    $session_manager = $this->createMock(AiSessionManager::class);
    $session_manager->expects($this->never())
      ->method('buildSessionContext');

    $service = $this->createService($session_manager);
    $result = $service->buildPromptWithSessionContextForTest('Prompt payload', 'gm_0', 0, 8);

    $this->assertSame('Prompt payload', $result);
  }

  private function createService(AiSessionManager $session_manager): object {
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('dungeoncrawler')->willReturn($logger);

    $rate_limit_store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $key_value_factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $key_value_factory->method('get')
      ->with('dungeoncrawler_content.ai_gm_rate_limit')
      ->willReturn($rate_limit_store);

    return new class(
      $this->createMock(ConfigFactoryInterface::class),
      $logger_factory,
      $this->createMock(GameEventLogger::class),
      $session_manager,
      $this->createMock(NpcPsychologyService::class),
      $this->createMock(EncounterBalancer::class),
      $this->createMock(SessionService::class),
      $this->createMock(NpcService::class),
      $key_value_factory
    ) extends AiGmService {
      public function __construct(
        ConfigFactoryInterface $config_factory,
        LoggerChannelFactoryInterface $logger_factory,
        GameEventLogger $event_logger,
        AiSessionManager $session_manager,
        NpcPsychologyService $npc_psychology_service,
        EncounterBalancer $encounter_balancer,
        SessionService $session_service,
        NpcService $npc_service,
        KeyValueExpirableFactoryInterface $key_value_factory
      ) {
        parent::__construct(
          NULL,
          $config_factory,
          $logger_factory,
          $event_logger,
          $session_manager,
          $npc_psychology_service,
          $encounter_balancer,
          $session_service,
          $npc_service,
          $key_value_factory
        );
      }

      public function buildPromptWithSessionContextForTest(string $prompt, string $session_key, int $campaign_id, int $message_limit): string {
        return $this->buildPromptWithSessionContext($prompt, $session_key, $campaign_id, $message_limit);
      }
    };
  }

}
