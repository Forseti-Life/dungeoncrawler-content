<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ActionProcessor;
use Drupal\dungeoncrawler_content\Service\AiGmService;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\CombatEngine;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\RulesEngine;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests Chameleon Gnome passive Stealth bonus behavior.
 *
 * AC items covered:
 * - +2 circumstance bonus applied when terrain_color_tag === coloration_tag
 * - Bonus NOT applied when terrain does not match coloration
 * - Circumstance bonuses do not stack; only the highest applies
 * - Bonus absence when heritage is not 'chameleon'
 *
 * @group dungeoncrawler_content
 * @group encounter
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler
 */
class EncounterPhaseHandlerChameleonStealthTest extends UnitTestCase {

  /**
   * +2 bonus applied when terrain color matches character coloration.
   *
   * @covers ::processIntent
   */
  public function testChameleonBonusAppliedWhenTerrainMatches(): void {
    // Die 10 + 0 base = 10; with +2 chameleon = 12 vs DC 11 → success.
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalHideState();

    $dungeon_data = [];
    $result = $handler->processIntent(
      [
        'type'   => 'hide',
        'actor'  => 'char-1',
        'params' => [
          'has_cover' => TRUE,
          'heritage'             => 'chameleon',
          'terrain_color_tag'    => 'forest_green',
          'coloration_tag'       => 'forest_green',
          'stealth_bonus'        => 0,
          'observer_ids'         => ['obs-1'],
          'perception_dcs'       => ['obs-1' => 11],
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertNotNull($result['result']['chameleon_bonus_applied'] ?? NULL,
      'Expected chameleon_bonus_applied to be set when terrain matches'
    );
    $this->assertGreaterThan(0, $result['result']['chameleon_bonus_applied'] ?? 0);
    // Verify visibility was set to hidden (total 12 ≥ DC 11).
    $this->assertSame('hidden', $game_state['visibility']['obs-1']['char-1'] ?? '');
  }

  /**
   * Bonus NOT applied when terrain color does not match coloration.
   *
   * @covers ::processIntent
   */
  public function testChameleonBonusAbsentWhenTerrainMismatches(): void {
    // Die 10 + 0 = 10 vs DC 11 → failure (no bonus because terrain mismatch).
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalHideState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'hide',
        'actor'  => 'char-1',
        'params' => [
          'has_cover' => TRUE,
          'heritage'             => 'chameleon',
          'terrain_color_tag'    => 'stone_grey',
          'coloration_tag'       => 'forest_green',  // mismatch
          'stealth_bonus'        => 0,
          'observer_ids'         => ['obs-1'],
          'perception_dcs'       => ['obs-1' => 11],
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertNull($result['result']['chameleon_bonus_applied'] ?? NULL,
      'Expected no chameleon bonus when terrain does not match coloration'
    );
    $this->assertSame('observed', $game_state['visibility']['obs-1']['char-1'] ?? '');
  }

  /**
   * Non-chameleon heritage does not get the bonus even in matching terrain.
   *
   * @covers ::processIntent
   */
  public function testNonChameleonHeritageGetNoBonusInMatchingTerrain(): void {
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalHideState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'hide',
        'actor'  => 'char-1',
        'params' => [
          'has_cover' => TRUE,
          'heritage'             => 'fey_touched',  // different heritage
          'terrain_color_tag'    => 'forest_green',
          'coloration_tag'       => 'forest_green',
          'stealth_bonus'        => 0,
          'observer_ids'         => ['obs-1'],
          'perception_dcs'       => ['obs-1' => 11],
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertNull($result['result']['chameleon_bonus_applied'] ?? NULL);
  }

  /**
   * Circumstance bonuses don't stack: existing +2 means chameleon adds 0.
   *
   * @covers ::processIntent
   */
  public function testChameleonBonusDoesNotStackWithExistingCircumstanceBonus(): void {
    // Already have circumstance_bonus = 2 (e.g., from a spell).
    // Chameleon adds max(0, 2 − 2) = 0.
    $handler = $this->buildHandler(10);
    $game_state = $this->minimalHideState();
    $dungeon_data = [];

    $result = $handler->processIntent(
      [
        'type'   => 'hide',
        'actor'  => 'char-1',
        'params' => [
          'has_cover' => TRUE,
          'heritage'             => 'chameleon',
          'terrain_color_tag'    => 'forest_green',
          'coloration_tag'       => 'forest_green',
          'stealth_bonus'        => 0,
          'circumstance_bonus'   => 2,
          'observer_ids'         => ['obs-1'],
          'perception_dcs'       => ['obs-1' => 11],
        ],
      ],
      $game_state,
      $dungeon_data,
      1
    );

    $this->assertNull($result['result']['chameleon_bonus_applied'] ?? NULL,
      'Expected no additional chameleon bonus when existing circumstance bonus is already at cap'
    );
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Builds EncounterPhaseHandler with deterministic die roller.
   */
  private function buildHandler(int $die_result = 10): EncounterPhaseHandler {
    $roller = $this->createMock(NumberGenerationService::class);
    $roller->method('rollPathfinderDie')->willReturn($die_result);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $calc = $this->createMock(CombatCalculator::class);
    // Wire real degree-of-success logic: success if total >= dc, crit if >= dc+10.
    $calc->method('calculateDegreeOfSuccess')
      ->willReturnCallback(function (int $total, int $dc, int $d20): string {
        if ($d20 === 1) {
          return $total >= $dc ? 'failure' : 'critical_failure';
        }
        if ($d20 === 20) {
          return $total >= $dc ? 'critical_success' : 'success';
        }
        if ($total >= $dc + 10) {
          return 'critical_success';
        }
        if ($total >= $dc) {
          return 'success';
        }
        if ($total <= $dc - 10) {
          return 'critical_failure';
        }
        return 'failure';
      });

    return new EncounterPhaseHandler(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(CombatEngine::class),
      $this->createMock(ActionProcessor::class),
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(HPManager::class),
      $this->createMock(ConditionManager::class),
      $calc,
      $roller,
      $this->createMock(EncounterAiIntegrationService::class),
      $this->createMock(RulesEngine::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(AiGmService::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(NpcPsychologyService::class)
    );
  }

  /**
   * Minimal game state for a hide action.
   */
  private function minimalHideState(): array {
    return [
      'round'      => 1,
      'visibility' => [],
      'turn'       => ['actions_remaining' => 3],
    ];
  }

}
