<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\dungeoncrawler_content\Controller\CharacterCreationStepController;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
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

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn(string $string, array $args = [], array $options = []): string => strtr($string, $args));
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    Drupal::setContainer($container);

    $controller = new class(
      $character_manager,
      $this->createMock(SchemaLoader::class),
      $csrf,
      $this->createMock(Connection::class),
      $this->createMock(CharacterPortraitGenerationService::class),
      $this->createMock(FeatLibraryService::class),
      $account
    ) extends CharacterCreationStepController {
      public function __construct(
        CharacterManager $character_manager,
        SchemaLoader $schema_loader,
        CsrfTokenGenerator $csrf_token,
        Connection $database,
        CharacterPortraitGenerationService $portrait_generator,
        FeatLibraryService $feat_library,
        private AccountInterface $account,
      ) {
        parent::__construct($character_manager, $schema_loader, $csrf_token, $database, $portrait_generator, $feat_library);
      }

      public function currentUser() {
        return $this->account;
      }
    };

    $request = new Request(['campaign_id' => 97]);
    $request->headers->set('X-CSRF-Token', 'valid-token');

    $response = $controller->saveStep(6, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertFalse($payload['success'] ?? TRUE);
  }

}
