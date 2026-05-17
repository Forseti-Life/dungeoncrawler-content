<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\PlayerAgentExplorationPolicy;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\PlayerAgentExplorationPolicy
 * @group dungeoncrawler_content
 * @group ai
 */
class PlayerAgentExplorationPolicyTest extends UnitTestCase {

  protected PlayerAgentExplorationPolicy $policy;

  protected function setUp(): void {
    parent::setUp();
    $this->policy = new PlayerAgentExplorationPolicy();
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionSearchesUnsearchedRoomFirst(): void {
    $decision = $this->policy->chooseAction(
      ['actor_id' => 'pc-1'],
      [
        'available_actions' => ['search', 'transition'],
        'active_room_id' => 'room-a',
      ],
      ['memory' => ['searched_rooms' => []]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('search', $decision['intent']['type']);
    $this->assertSame('pc-1', $decision['intent']['actor']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionTalksToUnvisitedNpcBeforeMoving(): void {
    $decision = $this->policy->chooseAction(
      [
        'actor_id' => 'pc-1',
        'character_id' => 77,
        'character_name' => 'Torgar',
        'persona' => ['tone' => 'curious'],
      ],
      [
        'available_actions' => ['talk', 'transition'],
        'active_room_id' => 'room-a',
        'visible_npcs' => [
          ['entity_instance_id' => 'npc-1', 'state' => ['metadata' => ['display_name' => 'Marta']]],
        ],
      ],
      ['memory' => ['searched_rooms' => ['room-a'], 'talked_entities' => []]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('talk', $decision['intent']['type']);
    $this->assertSame('npc-1', $decision['intent']['target']);
    $this->assertStringContainsString('Marta', $decision['intent']['params']['message']);
    $this->assertStringContainsString('quest', strtolower($decision['intent']['params']['message']));
    $this->assertTrue(
      str_contains(strtolower($decision['intent']['params']['message']), 'gold')
      || str_contains(strtolower($decision['intent']['params']['message']), 'work')
    );
    $this->assertSame(77, $decision['intent']['params']['character_id']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionTransitionsToUnvisitedRoom(): void {
    $decision = $this->policy->chooseAction(
      ['actor_id' => 'pc-1'],
      [
        'available_actions' => ['transition'],
        'active_room_id' => 'room-a',
        'connected_rooms' => [
          ['room_id' => 'room-b', 'name' => 'North Hall'],
          ['room_id' => 'room-c', 'name' => 'South Hall'],
        ],
      ],
      ['memory' => ['searched_rooms' => ['room-a'], 'talked_entities' => [], 'visited_rooms' => ['room-a']]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('transition', $decision['intent']['type']);
    $this->assertSame('room-b', $decision['intent']['params']['target_room_id']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionPrefersTransitionBeforeRepeatedPaidWorkTalk(): void {
    $decision = $this->policy->chooseAction(
      [
        'actor_id' => 'pc-1',
        'character_id' => 77,
      ],
      [
        'available_actions' => ['talk', 'transition', 'rest'],
        'active_room_id' => 'room-a',
        'visible_npcs' => [
          ['entity_instance_id' => 'npc-1', 'state' => ['metadata' => ['display_name' => 'Marta']]],
        ],
        'connected_rooms' => [
          ['room_id' => 'room-b', 'name' => 'North Hall'],
        ],
      ],
      ['memory' => ['searched_rooms' => ['room-a'], 'talked_entities' => ['npc-1'], 'visited_rooms' => ['room-a']]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('transition', $decision['intent']['type']);
    $this->assertSame('room-b', $decision['intent']['params']['target_room_id']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionUsesRoomChatHarnessBeforeResting(): void {
    $decision = $this->policy->chooseAction(
      [
        'actor_id' => 'pc-1',
        'character_id' => 77,
      ],
      [
        'available_actions' => ['talk', 'rest'],
        'active_room_id' => 'room-a',
        'visible_npcs' => [
          ['entity_instance_id' => 'npc-1', 'state' => ['metadata' => ['display_name' => 'Marta']]],
        ],
      ],
      ['memory' => ['searched_rooms' => ['room-a'], 'talked_entities' => ['npc-1'], 'rested_rooms' => []]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('talk', $decision['intent']['type']);
    $this->assertSame('npc-1', $decision['intent']['target']);
    $this->assertStringContainsString('Marta', $decision['intent']['params']['message']);
    $this->assertSame('paid_work_fallback', $decision['intent']['params']['automation_goal']);
    $this->assertTrue(
      str_contains(strtolower($decision['intent']['params']['message']), 'gold')
      || str_contains(strtolower($decision['intent']['params']['message']), 'work')
      || str_contains(strtolower($decision['intent']['params']['message']), 'bounty')
    );
    $this->assertSame(77, $decision['intent']['params']['character_id']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionRestsOnlyOncePerRoom(): void {
    $firstDecision = $this->policy->chooseAction(
      ['actor_id' => 'pc-1'],
      [
        'available_actions' => ['rest'],
        'active_room_id' => 'room-a',
      ],
      ['memory' => ['rested_rooms' => []]]
    );

    $secondDecision = $this->policy->chooseAction(
      ['actor_id' => 'pc-1'],
      [
        'available_actions' => ['rest'],
        'active_room_id' => 'room-a',
      ],
      ['memory' => ['rested_rooms' => ['room-a']]]
    );

    $this->assertSame('intent', $firstDecision['type']);
    $this->assertSame('rest', $firstDecision['intent']['type']);
    $this->assertSame('wait', $secondDecision['type']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionDoesNotRepeatPaidWorkFallbackInSameRoom(): void {
    $decision = $this->policy->chooseAction(
      ['actor_id' => 'pc-1'],
      [
        'available_actions' => ['talk', 'rest'],
        'active_room_id' => 'room-a',
        'visible_npcs' => [
          ['entity_instance_id' => 'npc-1', 'state' => ['metadata' => ['display_name' => 'Marta']]],
        ],
      ],
      ['memory' => ['talked_entities' => ['npc-1'], 'consulted_rooms' => ['room-a'], 'rested_rooms' => []]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('rest', $decision['intent']['type']);
  }

}
