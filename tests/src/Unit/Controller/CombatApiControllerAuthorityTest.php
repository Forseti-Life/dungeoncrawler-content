<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\CombatApiController;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\EncounterStateService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies round/turn authority protections on legacy combat admin endpoints.
 *
 * Phase 2 (object-state authority): the participant-membership read routes
 * through the canonical owner EncounterStateService::tryGetState(); the raw
 * store is retained only for the participant persistence write.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CombatApiController
 */
class CombatApiControllerAuthorityTest extends UnitTestCase {

  /**
   * Build a controller with the given store + encounter-state owner mocks.
   */
  protected function buildController(?MockObject $store = NULL, ?MockObject $encounter_state = NULL): CombatApiController {
    return new CombatApiController(
      $this->createMock(\stdClass::class),
      $this->createMock(\stdClass::class),
      $store ?? $this->createMock(CombatEncounterStore::class),
      $this->createMock(Connection::class),
      $encounter_state ?? $this->createMock(EncounterStateService::class)
    );
  }

  /**
   * Owner mock whose tryGetState() returns an encounter with one participant.
   */
  protected function ownerWithParticipant(int $encounter_id, int $participant_id): MockObject {
    $encounter_state = $this->createMock(EncounterStateService::class);
    $encounter_state->expects($this->once())
      ->method('tryGetState')
      ->with($encounter_id)
      ->willReturn([
        'participants' => [
          ['id' => $participant_id],
        ],
      ]);
    return $encounter_state;
  }

  /**
   * @covers ::rerollInitiative
   */
  public function testRerollInitiativeIsDisabledForCanonicalAuthority(): void {
    $controller = $this->buildController();

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
    $store->expects($this->never())->method('updateParticipant');

    $controller = $this->buildController($store, $this->ownerWithParticipant(55, 9));

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
  public function testUpdateParticipantAllowsNonCanonicalMetadataFields(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->once())
      ->method('updateParticipant')
      ->with(3, ['name' => 'Updated Name', 'ac' => 17]);

    $controller = $this->buildController($store, $this->ownerWithParticipant(77, 3));

    $request = new Request([], [], [], [], [], [], json_encode([
      'ac' => 17,
      'name' => 'Updated Name',
    ]));
    $response = $controller->updateParticipant(77, 3, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['name', 'ac'], $payload['updated_fields'] ?? []);
  }

  /**
   * @covers ::updateParticipant
   */
  public function testUpdateParticipantBlocksTeamMutation(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->never())->method('updateParticipant');

    $controller = $this->buildController($store, $this->ownerWithParticipant(88, 4));

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
   * @covers ::updateParticipant
   */
  public function testUpdateParticipantBlocksHpMutation(): void {
    $store = $this->createMock(CombatEncounterStore::class);
    $store->expects($this->never())->method('updateParticipant');

    $controller = $this->buildController($store, $this->ownerWithParticipant(89, 5));

    $request = new Request([], [], [], [], [], [], json_encode([
      'hp' => 12,
      'max_hp' => 20,
    ]));
    $response = $controller->updateParticipant(89, 5, $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('round_turn_authority_disabled', $payload['error_code'] ?? NULL);
    $this->assertSame(['hp', 'max_hp'], $payload['blocked_fields'] ?? []);
  }

  /**
   * @covers ::addParticipant
   */
  public function testAddParticipantIsDisabledForCanonicalAuthority(): void {
    $controller = $this->buildController();

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
    $controller = $this->buildController();

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
