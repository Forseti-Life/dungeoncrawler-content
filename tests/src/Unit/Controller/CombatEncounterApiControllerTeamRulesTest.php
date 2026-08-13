<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\CombatEncounterApiController;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\Tests\UnitTestCase;

/**
 * Tests read-path encounter normalization stays side-effect free.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CombatEncounterApiController
 */
class CombatEncounterApiControllerTeamRulesTest extends UnitTestCase {

  protected function buildController(): CombatEncounterApiController {
    return new CombatEncounterApiController(
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(Connection::class),
    );
  }

  /**
   * @covers ::normalizeEncounterForResponse
   */
  public function testNormalizeEncounterForResponseKeepsParticipantTeamsUnchanged(): void {
    $controller = $this->buildController();

    $normalize_encounter = new \ReflectionMethod(CombatEncounterApiController::class, 'normalizeEncounterForResponse');
    $normalize_encounter->setAccessible(TRUE);

    $encounter = [
      'id' => 99,
      'status' => 'active',
      'turn_index' => 1,
      'participants' => [
        [
          'id' => 10,
          'team' => 'neutral',
          'is_defeated' => 0,
        ],
        [
          'id' => 11,
          'team' => 'player',
          'is_defeated' => 0,
        ],
      ],
    ];

    $normalized = $normalize_encounter->invoke($controller, $encounter);
    $this->assertSame($encounter, $normalized);
  }

  /**
   * @covers ::normalizeEncounterForResponse
   */
  public function testNormalizeEncounterForResponseReturnsNullWhenMissing(): void {
    $controller = $this->buildController();

    $normalize_encounter = new \ReflectionMethod(CombatEncounterApiController::class, 'normalizeEncounterForResponse');
    $normalize_encounter->setAccessible(TRUE);

    $normalized = $normalize_encounter->invoke($controller, NULL);
    $this->assertNull($normalized);
  }

  /**
   * @covers ::buildEncounterResponse
   */
  public function testBuildEncounterResponseIncludesEncounterPresentationContract(): void {
    $controller = $this->buildController();

    $build_response = new \ReflectionMethod(CombatEncounterApiController::class, 'buildEncounterResponse');
    $build_response->setAccessible(TRUE);

    $encounter = [
      'id' => 101,
      'campaign_id' => 77,
      'room_id' => 'crypt_chamber_a',
      'status' => 'active',
      'current_round' => 2,
      'turn_index' => 1,
      'participants' => [
        [
          'id' => 1,
          'entity_ref' => 'pc-hero-1',
          'name' => 'Lyra',
          'team' => 'player',
          'initiative' => 22,
          'hp' => 28,
          'max_hp' => 36,
          'actions_remaining' => 2,
          'reaction_available' => TRUE,
        ],
        [
          'id' => 2,
          'entity_ref' => 'npc-skeleton-1',
          'name' => 'Skeleton',
          'team' => 'enemy',
          'initiative' => 18,
          'hp' => 14,
          'max_hp' => 20,
          'actions_remaining' => 3,
          'reaction_available' => FALSE,
        ],
      ],
    ];

    $response = $build_response->invoke($controller, $encounter);
    $presentation = $response['encounter_presentation'] ?? NULL;
    $this->assertIsArray($presentation);
    $this->assertSame('encounter-map-v1', $presentation['schema_version'] ?? NULL);
    $this->assertSame(101, $presentation['encounter_id'] ?? NULL);
    $this->assertSame('active', $presentation['status'] ?? NULL);
    $this->assertSame(2, $presentation['current_round'] ?? NULL);
    $this->assertSame('npc-skeleton-1', $presentation['current_entity_id'] ?? NULL);
    $this->assertIsArray($presentation['initiative_order'] ?? NULL);
    $this->assertCount(2, $presentation['initiative_order']);

    $player_card = $presentation['initiative_order'][0];
    $enemy_card = $presentation['initiative_order'][1];
    $this->assertSame('full', $player_card['hp']['visibility'] ?? NULL);
    $this->assertSame('status_only', $enemy_card['hp']['visibility'] ?? NULL);
    $this->assertTrue((bool) ($enemy_card['is_current'] ?? FALSE));
  }

}
