<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\CombatApiController;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies round/turn authority protections on legacy combat admin endpoints.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CombatApiController
 */
class CombatApiControllerAuthorityTest extends UnitTestCase {

  /**
   * @covers ::rerollInitiative
   */
  public function testRerollInitiativeIsDisabledForCanonicalAuthority(): void {
    $controller = new CombatApiController(
      $this->createMock(\stdClass::class),
      $this->createMock(\stdClass::class),
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(Connection::class)
    );

    $request = new Request([], [], [], [], [], [], json_encode(['participant_ids' => [1, 2]]));
    $response = $controller->rerollInitiative(42, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('round_turn_authority_disabled', $payload['error_code'] ?? NULL);
    $this->assertSame('/api/game/{campaign_id}/action', $payload['canonical_endpoint'] ?? NULL);
    $this->assertSame(['initiative'], $payload['blocked_fields'] ?? []);
  }

  /**
   * @covers ::updateParticipant
   */
  public function testUpdateParticipantBlocksCanonicalTurnFields(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->once())
      ->method('loadEncounter')
      ->with(55)
      ->willReturn([
        'participants' => [
          ['id' => 9],
        ],
      ]);
    $store->expects($this->never())
      ->method('updateParticipant');

    $controller = new CombatApiController(
      $this->createMock(\stdClass::class),
      $this->createMock(\stdClass::class),
      $store,
      $this->createMock(Connection::class)
    );

    $request = new Request([], [], [], [], [], [], json_encode([
      'actions_remaining' => 1,
      'reaction_available' => 0,
    ]));
    $response = $controller->updateParticipant(55, 9, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('round_turn_authority_disabled', $payload['error_code'] ?? NULL);
    $this->assertSame(
      ['actions_remaining', 'reaction_available'],
      $payload['blocked_fields'] ?? []
    );
  }

  /**
   * @covers ::updateParticipant
   */
  public function testUpdateParticipantAllowsNonTurnFields(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->once())
      ->method('loadEncounter')
      ->with(77)
      ->willReturn([
        'participants' => [
          ['id' => 3],
        ],
      ]);
    $store->expects($this->once())
      ->method('updateParticipant')
      ->with(3, ['name' => 'Updated Name', 'hp' => 12]);

    $controller = new CombatApiController(
      $this->createMock(\stdClass::class),
      $this->createMock(\stdClass::class),
      $store,
      $this->createMock(Connection::class)
    );

    $request = new Request([], [], [], [], [], [], json_encode([
      'hp' => 12,
      'name' => 'Updated Name',
    ]));
    $response = $controller->updateParticipant(77, 3, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['name', 'hp'], $payload['updated_fields'] ?? []);
  }

  /**
   * @covers ::updateParticipant
   */
  public function testUpdateParticipantBlocksTeamMutation(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->once())
      ->method('loadEncounter')
      ->with(88)
      ->willReturn([
        'participants' => [
          ['id' => 4],
        ],
      ]);
    $store->expects($this->never())
      ->method('updateParticipant');

    $controller = new CombatApiController(
      $this->createMock(\stdClass::class),
      $this->createMock(\stdClass::class),
      $store,
      $this->createMock(Connection::class)
    );

    $request = new Request([], [], [], [], [], [], json_encode([
      'team' => 'player',
    ]));
    $response = $controller->updateParticipant(88, 4, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('round_turn_authority_disabled', $payload['error_code'] ?? NULL);
    $this->assertSame(['team'], $payload['blocked_fields'] ?? []);
  }

  /**
   * @covers ::addParticipant
   */
  public function testAddParticipantIsDisabledForCanonicalAuthority(): void {
    $controller = new CombatApiController(
      $this->createMock(\stdClass::class),
      $this->createMock(\stdClass::class),
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(Connection::class)
    );

    $request = new Request([], [], [], [], [], [], json_encode([
      'name' => 'New NPC',
      'team' => 'enemy',
    ]));
    $response = $controller->addParticipant(12, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('round_turn_authority_disabled', $payload['error_code'] ?? NULL);
    $this->assertSame(['participant_roster'], $payload['blocked_fields'] ?? []);
  }

  /**
   * @covers ::removeParticipant
   */
  public function testRemoveParticipantIsDisabledForCanonicalAuthority(): void {
    $controller = new CombatApiController(
      $this->createMock(\stdClass::class),
      $this->createMock(\stdClass::class),
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(Connection::class)
    );

    $request = new Request([], [], [], [], [], [], json_encode([
      'reason' => 'cleanup',
    ]));
    $response = $controller->removeParticipant(12, 7, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('round_turn_authority_disabled', $payload['error_code'] ?? NULL);
    $this->assertSame(['participant_roster'], $payload['blocked_fields'] ?? []);
  }

}
