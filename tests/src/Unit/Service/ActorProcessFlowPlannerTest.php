<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorProcessFlowArchetypeResolver;
use Drupal\dungeoncrawler_content\Service\ActorProcessFlowIntentHelper;
use Drupal\dungeoncrawler_content\Service\ActorProcessFlowPlanner;
use Drupal\dungeoncrawler_content\Service\CommonerEncounterActorProcessFlow;
use Drupal\dungeoncrawler_content\Service\DefaultFighterEncounterActorProcessFlow;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorProcessFlowPlanner
 * @group dungeoncrawler_content
 * @group ai
 */
class ActorProcessFlowPlannerTest extends UnitTestCase {

  /**
   * @covers ::planDecision
   */
  public function testPlannerSelectsDeterministicFighterStrikeForObviousCombatTurn(): void {
    $planner = $this->buildPlanner();

    $decision = $planner->planDecision(
      [
        'actor_id' => 'pc-1',
        'combat_loadout' => [
          'weapon' => [
            'attack_bonus' => 8,
            'damage_dice' => '1d8+4',
            'damage_type' => 'slashing',
          ],
        ],
      ],
      [
        'campaign_id' => 44,
        'phase' => 'encounter',
        'game_state' => ['turn' => ['entity' => 'pc-1'], 'encounter_id' => 91],
        'available_actions' => ['strike', 'stride', 'end_turn'],
        'hostile_targets' => [
          ['entity_id' => 'npc-2', 'team' => 'hostile'],
        ],
        'actor_entity' => [
          'state' => ['metadata' => ['class' => 'fighter']],
        ],
      ],
      [],
      ['planner_mode' => 'harness']
    );

    $this->assertIsArray($decision);
    $this->assertSame('strike', $decision['intent']['type']);
    $this->assertSame('npc-2', $decision['intent']['target']);
    $this->assertSame('default_fighter_encounter', $decision['decision_meta']['flow_id']);
    $this->assertSame('fighter', $decision['decision_meta']['archetype']);
  }

  /**
   * @covers ::planDecision
   */
  public function testPlannerAdvancesTowardHostileWhenStrikeIsUnavailable(): void {
    $planner = $this->buildPlanner();

    $decision = $planner->planDecision(
      [
        'actor_id' => 'pc-1',
        'combat_loadout' => [
          'weapon' => [
            'attack_bonus' => 8,
            'damage_dice' => '1d8+4',
            'damage_type' => 'slashing',
          ],
        ],
      ],
      [
        'campaign_id' => 44,
        'phase' => 'encounter',
        'game_state' => ['turn' => ['entity' => 'pc-1'], 'encounter_id' => 91],
        'available_actions' => ['stride', 'end_turn'],
        'hostile_targets' => [
          ['entity_id' => 'npc-2', 'team' => 'hostile'],
        ],
        'actor_entity' => [
          'state' => ['metadata' => ['class' => 'fighter']],
          'placement' => ['hex' => ['q' => 0, 'r' => 0]],
        ],
        'visible_entities' => [
          [
            'entity_instance_id' => 'pc-1',
            'placement' => ['hex' => ['q' => 0, 'r' => 0]],
          ],
          [
            'entity_instance_id' => 'npc-2',
            'placement' => ['hex' => ['q' => 2, 'r' => 0]],
          ],
        ],
      ],
      [],
      ['planner_mode' => 'langgraph']
    );

    $this->assertIsArray($decision);
    $this->assertSame('stride', $decision['intent']['type']);
    $this->assertSame(['q' => 1, 'r' => 0], $decision['intent']['params']['target_hex']);
    $this->assertSame(5, $decision['intent']['params']['distance_ft']);
    $this->assertSame('default_fighter_encounter', $decision['decision_meta']['flow_id']);
  }

  /**
   * @covers ::resolveArchetypes
   */
  public function testPlannerResolvesCommonerArchetypeFromActorRole(): void {
    $planner = $this->buildPlanner();

    $archetypes = $planner->resolveArchetypes(
      ['actor_id' => 'npc-1'],
      [
        'actor_entity' => [
          'state' => [
            'metadata' => ['role' => 'Commoner'],
          ],
        ],
      ]
    );

    $this->assertContains('commoner', $archetypes);
    $this->assertContains('default', $archetypes);
  }

  protected function buildPlanner(): ActorProcessFlowPlanner {
    $helper = new ActorProcessFlowIntentHelper();
    return new ActorProcessFlowPlanner(
      new ActorProcessFlowArchetypeResolver(),
      [
        new DefaultFighterEncounterActorProcessFlow($helper),
        new CommonerEncounterActorProcessFlow($helper),
      ]
    );
  }

}
