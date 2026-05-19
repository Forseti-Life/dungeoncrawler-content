<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\CombatEncounterApiController;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests encounter participant normalization for room-wide encounter startup.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CombatEncounterApiController
 */
class CombatEncounterApiControllerTeamRulesTest extends UnitTestCase {

  protected function buildController(): CombatEncounterApiController {
    return new CombatEncounterApiController(
      $this->createMock(CombatEncounterStore::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(EncounterAiIntegrationService::class),
      $this->createMock(Connection::class),
      $this->createMock(CharacterStateService::class),
      $this->createMock(NumberGenerationService::class),
      $this->createMock(EventDispatcherInterface::class),
    );
  }

  /**
   * @covers ::normalizeParticipantTeam
   * @covers ::normalizeParticipants
   * @covers ::hasPlayerParticipant
   */
  public function testNormalizeParticipantsKeepsNeutralAndAllowsPlayerOnlyStart(): void {
    $controller = $this->buildController();

    $normalize_team = new \ReflectionMethod(CombatEncounterApiController::class, 'normalizeParticipantTeam');
    $normalize_team->setAccessible(TRUE);
    $normalize_participants = new \ReflectionMethod(CombatEncounterApiController::class, 'normalizeParticipants');
    $normalize_participants->setAccessible(TRUE);
    $has_player = new \ReflectionMethod(CombatEncounterApiController::class, 'hasPlayerParticipant');
    $has_player->setAccessible(TRUE);

    $this->assertSame('neutral', $normalize_team->invoke($controller, 'neutral'));

    $participants = $normalize_participants->invoke($controller, [
      [
        'entityId' => 3001,
        'entityRef' => 'pc-hero-1',
        'name' => 'Valeros',
        'team' => 'player',
        'initiative' => 12,
        'hp' => 20,
        'max_hp' => 20,
      ],
      [
        'entityId' => 3002,
        'entityRef' => 'npc-innkeeper-1',
        'name' => 'Innkeeper',
        'team' => 'neutral',
        'initiative' => 10,
        'hp' => 12,
        'max_hp' => 12,
      ],
    ]);

    $this->assertCount(2, $participants);
    $this->assertSame('player', $participants[0]['team']);
    $this->assertSame('neutral', $participants[1]['team']);
    $this->assertTrue($has_player->invoke($controller, $participants));
  }

  /**
   * @covers ::evaluateEncounterOutcome
   * @covers ::normalizeEncounterOutcomeSide
   */
  public function testEvaluateEncounterOutcomeKeepsPlayerAndNeutralEncounterActive(): void {
    $controller = $this->buildController();

    $evaluate_outcome = new \ReflectionMethod(CombatEncounterApiController::class, 'evaluateEncounterOutcome');
    $evaluate_outcome->setAccessible(TRUE);

    $resolution = $evaluate_outcome->invoke($controller, [
      [
        'team' => 'player',
        'is_defeated' => 0,
      ],
      [
        'team' => 'neutral',
        'is_defeated' => 0,
      ],
    ]);

    $this->assertFalse($resolution['ended']);
  }

  /**
   * @covers ::normalizeEncounterForResponse
   */
  public function testNormalizeEncounterForResponseDoesNotAutoEndActiveEncounter(): void {
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

    $this->assertSame('active', $normalized['status']);
    $this->assertSame(2, count($normalized['participants']));
  }

}
