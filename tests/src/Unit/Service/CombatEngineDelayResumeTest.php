<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\ActionProcessor;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\CombatEngine;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\StateManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests CombatEngine delay/resume turn behavior.
 *
 * @group dungeoncrawler_content
 * @group combat
 */
class CombatEngineDelayResumeTest extends UnitTestCase {

  /**
   * Delay turn updates initiative and advances to next turn.
   */
  public function testDelayTurnUpdatesInitiativeAndAdvancesTurn(): void {
    $store = $this->createMock(CombatEncounterStore::class);

    $initial = [
      'id' => 99,
      'current_round' => 1,
      'turn_index' => 0,
      'participants' => [
        ['id' => 11, 'initiative' => 18],
        ['id' => 22, 'initiative' => 12],
      ],
    ];
    $updated = [
      'id' => 99,
      'current_round' => 1,
      'turn_index' => 0,
      'participants' => [
        ['id' => 22, 'initiative' => 12],
        ['id' => 11, 'initiative' => 11],
      ],
    ];

    $store->expects($this->exactly(3))
      ->method('loadEncounter')
      ->with(99)
      ->willReturnOnConsecutiveCalls($initial, $updated, $updated);

    $store->expects($this->once())
      ->method('updateParticipant')
      ->with(11, $this->callback(static function (array $fields): bool {
        return isset($fields['initiative']) && $fields['initiative'] < 12;
      }))
      ->willReturn(TRUE);

    $store->expects($this->once())
      ->method('updateEncounter')
      ->with(99, ['turn_index' => 0, 'current_round' => 1])
      ->willReturn(TRUE);

    $engine = $this->buildEngine($store);
    $result = $engine->delayTurn(99, 11);

    $this->assertSame('ok', $result['status'] ?? NULL);
    $this->assertTrue((bool) ($result['delayed'] ?? FALSE));
    $this->assertSame(11, $result['participant_id'] ?? NULL);
    $this->assertSame(22, $result['next_turn']['participant_id'] ?? NULL);
  }

  /**
   * Resume from delay restores initiative and sets turn index to participant.
   */
  public function testResumeFromDelayRestoresInitiativeAndTurnIndex(): void {
    $store = $this->createMock(CombatEncounterStore::class);

    $initial = [
      'id' => 77,
      'turn_index' => 0,
      'participants' => [
        ['id' => 22, 'initiative' => 15],
        ['id' => 11, 'initiative' => 10],
      ],
    ];
    $updated = [
      'id' => 77,
      'turn_index' => 0,
      'participants' => [
        ['id' => 11, 'initiative' => 19],
        ['id' => 22, 'initiative' => 15],
      ],
    ];

    $store->expects($this->exactly(3))
      ->method('loadEncounter')
      ->with(77)
      ->willReturnOnConsecutiveCalls($initial, $updated, $updated);

    $store->expects($this->once())
      ->method('updateParticipant')
      ->with(11, ['initiative' => 19])
      ->willReturn(TRUE);

    $store->expects($this->once())
      ->method('updateEncounter')
      ->with(77, ['turn_index' => 0])
      ->willReturn(TRUE);

    $engine = $this->buildEngine($store);
    $result = $engine->resumeFromDelay(77, 11, 19);

    $this->assertSame('ok', $result['status'] ?? NULL);
    $this->assertTrue((bool) ($result['resumed'] ?? FALSE));
    $this->assertSame(11, $result['participant_id'] ?? NULL);
    $this->assertSame(19, $result['initiative'] ?? NULL);
    $this->assertSame(0, $result['turn_index'] ?? NULL);
  }

  /**
   * Builds a CombatEngine with mocked dependencies.
   */
  protected function buildEngine(CombatEncounterStore $store): CombatEngine {
    return new CombatEngine(
      $this->createMock(Connection::class),
      $this->createMock(StateManager::class),
      $this->createMock(ActionProcessor::class),
      $store,
      $this->createMock(HPManager::class),
      $this->createMock(NumberGenerationService::class)
    );
  }

}

