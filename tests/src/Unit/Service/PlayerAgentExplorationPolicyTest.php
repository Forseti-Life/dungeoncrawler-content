<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\PlayerAgentExplorationPolicy;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Drupal\Tests\UnitTestCase;

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
  public function testChooseActionPrioritizesActiveQuestConversation(): void {
    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->method('getActiveQuests')->with(65, 77)->willReturn([
      [
        'quest_id' => 'missing-courier',
        'quest_name' => 'Missing Courier',
        'current_phase' => 1,
        'last_updated' => 50,
        'objective_states' => json_encode([
          [
            'phase' => 1,
            'objectives' => [
              ['objective_id' => 'ask-innkeeper', 'description' => 'Ask the innkeeper where the courier was last seen.', 'completed' => FALSE],
            ],
          ],
        ]),
      ],
    ]);
    $policy = new PlayerAgentExplorationPolicy($quest_tracker);

    $decision = $policy->chooseAction(
      [
        'actor_id' => 'pc-1',
        'character_id' => 77,
        'character_name' => 'Torgar',
      ],
      [
        'campaign_id' => 65,
        'available_actions' => ['talk', 'search', 'transition'],
        'active_room_id' => 'room-a',
        'visible_npcs' => [
          ['entity_instance_id' => 'npc-1', 'state' => ['metadata' => ['display_name' => 'Marta']]],
        ],
      ],
      ['memory' => ['searched_rooms' => [], 'talked_entities' => []]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('talk', $decision['intent']['type']);
    $this->assertSame('active_quest_progress', $decision['intent']['params']['automation_goal']);
    $this->assertSame('missing-courier', $decision['intent']['params']['quest_id']);
    $this->assertStringContainsString('Missing Courier', $decision['intent']['params']['message']);
    $this->assertStringContainsString('next objective', strtolower($decision['intent']['params']['message']));
    $this->assertStringContainsString('active quest', strtolower($decision['reason']));
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionTargetsQuestLeadNpcBeforeOtherVisibleNpcs(): void {
    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->method('getActiveQuests')->with(65, 77)->willReturn([
      [
        'quest_id' => 'tavern-storyline-leads',
        'quest_name' => 'Gather Storyline Leads in the Tavern',
        'current_phase' => 1,
        'last_updated' => 200,
        'objective_states' => json_encode([
          [
            'phase' => 1,
            'objectives' => [
              ['objective_id' => 'speak_to_marta', 'type' => 'interact', 'target' => 'scholar_npc', 'description' => 'Speak to Marta the Scholar and gather her storyline lead.', 'completed' => FALSE],
            ],
          ],
        ]),
      ],
    ]);
    $policy = new PlayerAgentExplorationPolicy($quest_tracker);

    $decision = $policy->chooseAction(
      [
        'actor_id' => 'pc-1',
        'character_id' => 77,
        'character_name' => 'Torgar',
      ],
      [
        'campaign_id' => 65,
        'available_actions' => ['talk', 'transition'],
        'active_room_id' => 'tavern_entrance',
        'visible_npcs' => [
          ['entity_instance_id' => 'npc-eldric', 'content_id' => 'tavern_keeper', 'state' => ['metadata' => ['display_name' => 'Eldric']]],
          ['entity_instance_id' => 'npc-marta', 'content_id' => 'scholar_npc', 'state' => ['metadata' => ['display_name' => 'Marta the Scholar']]],
        ],
      ],
      ['memory' => ['talked_entities' => []]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('talk', $decision['intent']['type']);
    $this->assertSame('npc-marta', $decision['intent']['target']);
    $this->assertSame('speak_to_marta', $decision['intent']['params']['objective_id']);
    $this->assertStringContainsString('scholar_npc', $decision['reason']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionSearchesForQuestObjectiveBeforeGenericLoop(): void {
    $quest_tracker = $this->createMock(QuestTrackerService::class);
    $quest_tracker->method('getActiveQuests')->with(65, 77)->willReturn([
      [
        'quest_id' => 'lost-ledger',
        'quest_name' => 'Lost Ledger',
        'current_phase' => 1,
        'last_updated' => 100,
        'objective_states' => json_encode([
          [
            'phase' => 1,
            'objectives' => [
              ['objective_id' => 'find-ledger', 'description' => 'Search the room and find the missing ledger.', 'completed' => FALSE],
            ],
          ],
        ]),
      ],
    ]);
    $policy = new PlayerAgentExplorationPolicy($quest_tracker);

    $decision = $policy->chooseAction(
      [
        'actor_id' => 'pc-1',
        'character_id' => 77,
      ],
      [
        'campaign_id' => 65,
        'available_actions' => ['search', 'transition'],
        'active_room_id' => 'room-a',
        'visible_npcs' => [],
      ],
      ['memory' => ['searched_rooms' => []]]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('search', $decision['intent']['type']);
    $this->assertSame('lost-ledger', $decision['intent']['params']['quest_id']);
    $this->assertStringContainsString('active quest', strtolower($decision['reason']));
    $this->assertStringContainsString('Lost Ledger', $decision['reason']);
  }

  /**
   * @covers ::chooseAction
   */
  public function testChooseActionFollowsUpOnPendingNpcLead(): void {
    $decision = $this->policy->chooseAction(
      [
        'actor_id' => 'pc-1',
        'character_id' => 77,
        'character_name' => 'Chikoet',
      ],
      [
        'available_actions' => ['talk', 'transition'],
        'active_room_id' => 'room-a',
        'visible_npcs' => [
          ['entity_instance_id' => 'npc-gribbles', 'state' => ['metadata' => ['display_name' => 'Gribbles Rindsworth']]],
        ],
      ],
      [
        'memory' => [
          'pending_conversation_lead' => [
            'target' => 'npc-gribbles',
            'room_id' => 'room-a',
            'excerpt' => 'Town guard has been jumpy about missing livestock and strange lights near the cemetery.',
          ],
        ],
      ]
    );

    $this->assertSame('intent', $decision['type']);
    $this->assertSame('talk', $decision['intent']['type']);
    $this->assertSame('npc-gribbles', $decision['intent']['target']);
    $this->assertSame('conversation_follow_up', $decision['intent']['params']['automation_goal']);
    $this->assertStringContainsString('Gribbles', $decision['intent']['params']['message']);
    $this->assertStringContainsString('What should I check first', $decision['intent']['params']['message']);
    $this->assertStringContainsString('Follow up', $decision['reason']);
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
