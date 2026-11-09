<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dungeoncrawler_content\Controller\CharacterCreationStepController;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\CharacterWizardHardeningService;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for character creation step controller campaign handling.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group unit
 */
class CharacterCreationStepControllerTest extends TestCase {

  /**
   * Install a translation container for controller t() responses.
   */
  private function installTranslationContainer(): void {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn(string $string, array $args = [], array $options = []): string => strtr($string, $args));
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    Drupal::setContainer($container);
  }

  /**
   * Build a controller test double with injected current user.
   */
  private function buildController(
    CharacterManager $character_manager,
    CsrfTokenGenerator $csrf,
    AccountInterface $account,
    ?CharacterWizardHardeningService $wizard_hardening = NULL,
  ): CharacterCreationStepController {
    return new class(
      $character_manager,
      $this->createMock(SchemaLoader::class),
      $csrf,
      $this->createMock(Connection::class),
      $this->createMock(CharacterPortraitGenerationService::class),
      $this->createMock(FeatLibraryService::class),
      $wizard_hardening ?? $this->createMock(CharacterWizardHardeningService::class),
      $account
    ) extends CharacterCreationStepController {
      public function __construct(
        CharacterManager $character_manager,
        SchemaLoader $schema_loader,
        CsrfTokenGenerator $csrf_token,
        Connection $database,
        CharacterPortraitGenerationService $portrait_generator,
        FeatLibraryService $feat_library,
        CharacterWizardHardeningService $wizard_hardening,
        private AccountInterface $account,
      ) {
        parent::__construct($character_manager, $schema_loader, $csrf_token, $database, $portrait_generator, $feat_library, $wizard_hardening);
      }

      public function currentUser() {
        return $this->account;
      }
    };
  }

  /**
   * Tests campaign-scoped AJAX step saves are rejected.
   */
  public function testSaveStepRejectsCampaignScopedAjaxFlow(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->never())->method('updateCharacter');

    $csrf = $this->createMock(CsrfTokenGenerator::class);
    $csrf->expects($this->exactly(2))
      ->method('validate')
      ->willReturnMap([
        ['valid-token', 'X-CSRF-Token', TRUE],
        ['valid-token', 'rest', TRUE],
      ]);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);

    $this->installTranslationContainer();
    $controller = $this->buildController($character_manager, $csrf, $account);

    $request = new Request(['campaign_id' => 97]);
    $request->headers->set('X-CSRF-Token', 'valid-token');

    $response = $controller->saveStep(6, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertFalse($payload['success'] ?? TRUE);
  }

  /**
   * Tests requests missing CSRF token are rejected before any mutation path.
   */
  public function testSaveStepRejectsMissingCsrfToken(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->never())->method('updateCharacter');

    $csrf = $this->createMock(CsrfTokenGenerator::class);
    $csrf->expects($this->never())->method('validate');

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->willReturn(FALSE);

    $this->installTranslationContainer();
    $controller = $this->buildController($character_manager, $csrf, $account);

    $response = $controller->saveStep(1, new Request());
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertFalse($payload['success'] ?? TRUE);
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('savedWizardStates')]
  public function testSaveStepPreservesProgressWhenRevisitingEarlierStep(int $unlocked_step, bool $complete): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('loadCharacter')->with(77)->willReturn((object) [
      'id' => 77,
      'uid' => 7,
      'campaign_id' => 0,
      'character_data' => json_encode([
        'name' => 'Original Hero',
        'step' => $unlocked_step,
        'wizard_complete' => $complete,
      ]),
    ]);
    $character_manager->expects($this->once())->method('updateCharacter')
      ->with(77, $this->callback(function (array $fields) use ($unlocked_step, $complete): bool {
        $data = json_decode($fields['character_data'], TRUE);
        $this->assertSame('Renamed Hero', $data['name']);
        $this->assertSame($unlocked_step, $data['step']);
        $this->assertSame($unlocked_step, $data['wizard']['step']);
        $this->assertSame($complete, $data['wizard_complete']);
        $this->assertSame($complete, $data['wizard']['wizard_complete']);
        $this->assertSame($complete ? 1 : 0, $fields['status']);
        $this->assertSame($complete ? 'ready_library' : 'draft_library', $fields['lifecycle_state']);
        return TRUE;
      }));
    $csrf = $this->createMock(CsrfTokenGenerator::class);
    $csrf->method('validate')->willReturn(TRUE);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $hardening = new CharacterWizardHardeningService(
      $character_manager,
      $this->createMock(Connection::class),
      $this->createMock(CampaignCharacterRuntimeResolverService::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $this->installTranslationContainer();
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')->willReturn('/characters/create/step/2?character_id=77');
    Drupal::getContainer()->set('url_generator', $url_generator);
    $request = new Request([], ['character_id' => 77, 'name' => 'Renamed Hero']);
    $request->headers->set('X-CSRF-Token', 'valid-token');
    $response = $this->buildController($character_manager, $csrf, $account, $hardening)->saveStep(1, $request);
    $this->assertSame(200, $response->getStatusCode());
  }

  public static function savedWizardStates(): array {
    return [
      'earlier edit retains unlocked step' => [7, FALSE],
      'review stays incomplete' => [8, FALSE],
      'completed edit stays complete' => [8, TRUE],
    ];
  }

}
