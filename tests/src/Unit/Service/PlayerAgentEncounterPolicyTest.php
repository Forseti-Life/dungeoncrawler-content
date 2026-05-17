<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\PlayerAgentEncounterPolicy;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\PlayerAgentEncounterPolicy
 * @group dungeoncrawler_content
 * @group ai
 * @group combat
 */
class PlayerAgentEncounterPolicyTest extends UnitTestCase {

  protected PlayerAgentEncounterPolicy $policy;

  protected function setUp(): void {
    parent::setUp();
    $this->policy = new PlayerAgentEncounterPolicy();
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionWaitsWhenItIsNotActorsTurn(): void {
    $decision = $this->policy->chooseAction(
      ['actor_id' => 'pc-1'],
      [
        'game_state' => ['turn' => ['entity' => 'npc-1']],
        'available_actions' => ['strike', 'end_turn'],
      ],
      []
    );

    $this->assertSame('wait', $decision['type']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionUsesConfiguredWeaponAgainstHostileTarget(): void {
    $decision = $this->policy->chooseAction(
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
        'game_state' => ['turn' => ['entity' => 'pc-1'], 'encounter_id' => 44],
        'available_actions' => ['strike', 'end_turn'],
        'hostile_targets' => [
          ['entity_id' => 'npc-2', 'team' => 'hostile'],
        ],
      ],
      []
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('strike', $decision['intent']['type']);
    $this->assertSame('npc-2', $decision['intent']['target']);
    $this->assertSame('1d8+4', $decision['intent']['params']['weapon']['damage_dice']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionFallsBackToEndTurnWithoutWeapon(): void {
    $decision = $this->policy->chooseAction(
      ['actor_id' => 'pc-1'],
      [
        'game_state' => ['turn' => ['entity' => 'pc-1'], 'encounter_id' => 44],
        'available_actions' => ['strike', 'end_turn'],
        'hostile_targets' => [
          ['entity_id' => 'npc-2', 'team' => 'hostile'],
        ],
      ],
      []
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('end_turn', $decision['intent']['type']);
  }

}
