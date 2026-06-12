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

}

