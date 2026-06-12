<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\CombatActionController;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies authority behavior for legacy combat action controller endpoints.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CombatActionController
 */
class CombatActionControllerAuthorityTest extends UnitTestCase {

  /**
   * @covers ::getCurrentTurn
   */
  public function testGetCurrentTurnReturnsStoreBackedParticipantState(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->once())
      ->method('loadEncounter')
      ->with(77)
      ->willReturn([
        'turn_index' => 1,
        'current_round' => 3,
        'participants' => [
          [
            'id' => 1,
            'name' => 'First',
            'actions_remaining' => 3,
            'attacks_this_turn' => 0,
          ],
          [
            'id' => 2,
            'name' => 'Second',
            'actions_remaining' => 2,
            'attacks_this_turn' => 1,
          ],
        ],
      ]);

    $controller = new CombatActionController($store);
    $response = $controller->getCurrentTurn(77);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(2, $payload['participant_id'] ?? NULL);
    $this->assertSame('Second', $payload['name'] ?? NULL);
    $this->assertSame(2, $payload['actions_remaining'] ?? NULL);
    $this->assertSame(1, $payload['turn_index'] ?? NULL);
    $this->assertSame(3, $payload['current_round'] ?? NULL);
  }

  /**
   * @covers ::startTurn
   */
  public function testStartTurnIsDisabledForCanonicalAuthority(): void {
    $controller = new CombatActionController($this->createMock(CombatEncounterStore::class));
    $response = $controller->startTurn(42, 9);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('legacy_combat_mutation_disabled', $payload['error_code'] ?? NULL);
    $this->assertSame('/api/game/{campaign_id}/action', $payload['canonical_endpoint'] ?? NULL);
  }

}
