<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Controller\CharacterApiController;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for GM chat controller validation.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group unit
 */
class CharacterApiControllerGmChatTest extends TestCase {

  /**
   * Tests blank GM chat messages are rejected before service execution.
   */
  public function testGmChatRejectsBlankMessage(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $gm_service = $this->createMock(CharacterCreationGmService::class);
    $gm_service->expects($this->never())->method('handleMessage');

    $csrf = $this->createMock(CsrfTokenGenerator::class);
    $csrf->expects($this->once())
      ->method('validate')
      ->with('valid-token', 'rest')
      ->willReturn(TRUE);

    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);

    $controller = new class($character_manager, $gm_service, $csrf, $account) extends CharacterApiController {
      public function __construct(CharacterManager $character_manager, CharacterCreationGmService $character_creation_gm, CsrfTokenGenerator $csrf_token, private AccountInterface $account) {
        parent::__construct($character_manager, $character_creation_gm, $csrf_token);
      }

      public function currentUser() {
        return $this->account;
      }
    };

    $request = new Request([], [], [], [], [], [], json_encode([
      'step' => 1,
      'message' => '   ',
    ]));
    $request->headers->set('X-CSRF-Token', 'valid-token');

    $response = $controller->gmChat($request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertFalse($payload['success'] ?? TRUE);
    $this->assertSame('Message is required', $payload['error'] ?? '');
  }

  /**
   * Tests successful GM chat responses expose reload intent to the client.
   */
  public function testGmChatReturnsReloadRequiredFlag(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $gm_service = $this->createMock(CharacterCreationGmService::class);
    $gm_service->expects($this->once())
      ->method('handleMessage')
      ->with(NULL, NULL, 1, 'Recommend a class.')
      ->willReturn([
        'reply' => 'Try fighter.',
        'character_id' => 42,
        'step' => 1,
        'history' => [['role' => 'assistant', 'content' => 'Try fighter.']],
        'applied_updates' => [],
        'reload_required' => FALSE,
        'reload_url' => '/characters/create/step/1?character_id=42',
        'summary' => ['step' => 1],
      ]);

    $csrf = $this->createMock(CsrfTokenGenerator::class);
    $csrf->expects($this->once())
      ->method('validate')
      ->with('valid-token', 'rest')
      ->willReturn(TRUE);

    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);

    $controller = new class($character_manager, $gm_service, $csrf, $account) extends CharacterApiController {
      public function __construct(CharacterManager $character_manager, CharacterCreationGmService $character_creation_gm, CsrfTokenGenerator $csrf_token, private AccountInterface $account) {
        parent::__construct($character_manager, $character_creation_gm, $csrf_token);
      }

      public function currentUser() {
        return $this->account;
      }
    };

    $request = new Request([], [], [], [], [], [], json_encode([
      'step' => 1,
      'message' => 'Recommend a class.',
    ]));
    $request->headers->set('X-CSRF-Token', 'valid-token');

    $response = $controller->gmChat($request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success'] ?? FALSE);
    $this->assertArrayHasKey('reload_required', $payload);
    $this->assertFalse($payload['reload_required']);
    $this->assertSame('/characters/create/step/1?character_id=42', $payload['reload_url'] ?? '');
  }

}
