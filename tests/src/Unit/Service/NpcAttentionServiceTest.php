<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\NpcAttentionService;
use PHPUnit\Framework\TestCase;

/**
 * Tests NpcAttentionService.
 *
 * @covers \Drupal\dungeoncrawler_content\Service\NpcAttentionService
 */
class NpcAttentionServiceTest extends TestCase {

  protected NpcAttentionService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->service = new NpcAttentionService();
  }

  /**
   * Tests initializeAttentionState creates proper structure.
   */
  public function testInitializeAttentionState(): void {
    $state = $this->service->initializeAttentionState();

    $this->assertIsArray($state);
    $this->assertNull($state['primary_focus_npc']);
    $this->assertNull($state['last_speaker']);
    $this->assertIsArray($state['speaker_chain']);
    $this->assertIsArray($state['engagement_scores']);
    $this->assertEmpty($state['speaker_chain']);
  }

  /**
   * Tests recordSpeaker updates last_speaker and speaker_chain.
   */
  public function testRecordSpeaker(): void {
    $state = $this->service->initializeAttentionState();

    $this->service->recordSpeaker($state, 'npc:eldric', 'Eldric', 1);

    $this->assertSame('npc:eldric', $state['last_speaker']);
    $this->assertSame('Eldric', $state['last_speaker_display_name']);
    $this->assertContains('npc:eldric', $state['speaker_chain']);
  }

  /**
   * Tests recordSpeaker maintains speaker_chain order.
   */
  public function testRecordSpeakerChainOrder(): void {
    $state = $this->service->initializeAttentionState();

    $this->service->recordSpeaker($state, 'npc:eldric', 'Eldric', 1);
    $this->service->recordSpeaker($state, 'pc:1050', 'Player', 2);
    $this->service->recordSpeaker($state, 'npc:gribbles', 'Gribbles', 3);

    $this->assertSame(
      ['npc:eldric', 'pc:1050', 'npc:gribbles'],
      $state['speaker_chain']
    );
  }

  /**
   * Tests recent_speakers limited to last 5.
   */
  public function testRecentSpeakersLimitedToFive(): void {
    $state = $this->service->initializeAttentionState();

    for ($i = 1; $i <= 7; $i++) {
      $this->service->recordSpeaker($state, "speaker_$i", "Speaker $i", $i);
    }

    $this->assertCount(5, $state['recent_speakers']);
    $this->assertSame('speaker_3', $state['recent_speakers'][0]['speaker']);
    $this->assertSame('speaker_7', end($state['recent_speakers'])['speaker']);
  }

  /**
   * Tests detectTopic identifies quest keywords.
   */
  public function testDetectTopicQuest(): void {
    $result = $this->service->detectTopic('I have a quest for you');

    $this->assertSame('quest', $result['topic']);
    $this->assertContains('quest', $result['keywords']);
    $this->assertGreaterThanOrEqual(70, $result['confidence']);
  }

  /**
   * Tests detectTopic identifies commerce keywords.
   */
  public function testDetectTopicCommerce(): void {
    $result = $this->service->detectTopic('How much does that sword cost?');

    $this->assertSame('commerce', $result['topic']);
    $this->assertContains('commerce', $result['keywords']);
  }

  /**
   * Tests detectTopic identifies navigation keywords.
   */
  public function testDetectTopicNavigation(): void {
    $result = $this->service->detectTopic('Where is the vault located?');

    $this->assertSame('navigation', $result['topic']);
    $this->assertContains('navigation', $result['keywords']);
  }

  /**
   * Tests detectTopic returns null for unrecognized messages.
   */
  public function testDetectTopicUnrecognized(): void {
    $result = $this->service->detectTopic('The weather is nice today');

    $this->assertNull($result['topic']);
    $this->assertEmpty($result['keywords']);
  }

  /**
   * Tests updateTopic returns false when topic unchanged.
   */
  public function testUpdateTopicUnchanged(): void {
    $state = $this->service->initializeAttentionState();
    $state['current_topic'] = 'quest';

    $changed = $this->service->updateTopic($state, 'quest', 10);

    $this->assertFalse($changed);
  }

  /**
   * Tests updateTopic returns true and applies penalty when changed.
   */
  public function testUpdateTopicChanged(): void {
    $state = $this->service->initializeAttentionState();
    $state['current_topic'] = 'quest';
    $state['topic_drift_penalty'] = 0;

    $changed = $this->service->updateTopic($state, 'commerce', 10);

    $this->assertTrue($changed);
    $this->assertSame('commerce', $state['current_topic']);
    $this->assertGreaterThan(0, $state['topic_drift_penalty']);
  }

  /**
   * Tests calculateAttentionScore for quest-relevant NPC.
   */
  public function testCalculateAttentionScoreQuestRelevant(): void {
    $npc = [
      'entity_ref' => 'npc:eldric',
      'quest_leads' => ['quest-1'],
      'attitude' => 'friendly',
      'ability_scores' => ['charisma' => 16],
      'initiative_total' => 5,
    ];
    $state = $this->service->initializeAttentionState();
    $game_state = [];

    $score = $this->service->calculateAttentionScore(
      $npc,
      $state,
      'I need help with a quest',
      $game_state
    );

    $this->assertIsArray($score);
    $this->assertGreaterThan(40, $score['total_score']);
    $this->assertTrue($score['qualified']);
  }

  /**
   * Tests calculateAttentionScore for unrelated topic.
   */
  public function testCalculateAttentionScoreUnrelated(): void {
    $npc = [
      'entity_ref' => 'npc:eldric',
      'quest_leads' => ['quest-1'],
      'ability_scores' => ['charisma' => 10],
      'initiative_total' => 0,
    ];
    $state = $this->service->initializeAttentionState();

    $score = $this->service->calculateAttentionScore(
      $npc,
      $state,
      'The weather is nice',
      []
    );

    $this->assertLessThan(40, $score['total_score']);
    $this->assertFalse($score['qualified']);
  }

  /**
   * Tests scoreTopicRelevance for quest NPC and quest message.
   */
  public function testScoreTopicRelevanceQuest(): void {
    $npc = [
      'quest_leads' => ['quest-1', 'quest-2'],
    ];

    $score = $this->getPrivateMethod('scoreTopicRelevance')->invoke(
      $this->service,
      $npc,
      'Can you help me with a quest?'
    );

    $this->assertGreaterThanOrEqual(85, $score);
  }

  /**
   * Tests scoreTopicRelevance for merchant NPC and commerce message.
   */
  public function testScoreTopicRelevanceMerchant(): void {
    $npc = [
      'is_merchant' => TRUE,
    ];

    $score = $this->getPrivateMethod('scoreTopicRelevance')->invoke(
      $this->service,
      $npc,
      'How much does this cost?'
    );

    $this->assertGreaterThanOrEqual(85, $score);
  }

  /**
   * Tests scorePersonalityAlignment for friendly NPC.
   */
  public function testScorePersonalityAlignmentFriendly(): void {
    $npc = [
      'attitude' => 'friendly',
      'personality_type' => 'talkative',
    ];
    $state = $this->service->initializeAttentionState();

    $score = $this->getPrivateMethod('scorePersonalityAlignment')->invoke(
      $this->service,
      $npc,
      $state
    );

    $this->assertGreaterThan(0, $score);
    $this->assertLessThanOrEqual(50, $score);
  }

  /**
   * Tests scorePersonalityAlignment for hostile NPC.
   */
  public function testScorePersonalityAlignmentHostile(): void {
    $npc = [
      'attitude' => 'hostile',
      'personality_type' => 'quiet',
    ];
    $state = $this->service->initializeAttentionState();

    $score = $this->getPrivateMethod('scorePersonalityAlignment')->invoke(
      $this->service,
      $npc,
      $state
    );

    $this->assertLessThan(0, $score);
    $this->assertGreaterThanOrEqual(-50, $score);
  }

  /**
   * Tests scoreRecentInteraction bonuses last speaker.
   */
  public function testScoreRecentInteractionLastSpeaker(): void {
    $npc = [
      'entity_ref' => 'npc:eldric',
    ];
    $state = $this->service->initializeAttentionState();
    $this->service->recordSpeaker($state, 'npc:eldric', 'Eldric', 5);

    $score = $this->getPrivateMethod('scoreRecentInteraction')->invoke(
      $this->service,
      $npc,
      $state
    );

    $this->assertGreaterThan(0, $score);
    $this->assertLessThanOrEqual(20, $score);
  }

  /**
   * Tests scoreRecentInteraction no bonus for non-recent speaker.
   */
  public function testScoreRecentInteractionNotRecent(): void {
    $npc = [
      'entity_ref' => 'npc:eldric',
    ];
    $state = $this->service->initializeAttentionState();
    $this->service->recordSpeaker($state, 'npc:other', 'Other', 5);

    $score = $this->getPrivateMethod('scoreRecentInteraction')->invoke(
      $this->service,
      $npc,
      $state
    );

    $this->assertSame(0, $score);
  }

  /**
   * Tests incrementFatiguePenalty adds to existing penalty.
   */
  public function testIncrementFatiguePenalty(): void {
    $state = $this->service->initializeAttentionState();
    $state['engagement_scores'] = [
      'npc:eldric' => ['fatigue_penalty' => 5],
    ];

    $this->service->incrementFatiguePenalty($state, 'npc:eldric');

    $this->assertSame(10, $state['engagement_scores']['npc:eldric']['fatigue_penalty']);
  }

  /**
   * Tests incrementFatiguePenalty capped at 30.
   */
  public function testIncrementFatiguePenaltyCapped(): void {
    $state = $this->service->initializeAttentionState();
    $state['engagement_scores'] = [
      'npc:eldric' => ['fatigue_penalty' => 28],
    ];

    $this->service->incrementFatiguePenalty($state, 'npc:eldric');

    $this->assertSame(30, $state['engagement_scores']['npc:eldric']['fatigue_penalty']);
  }

  /**
   * Tests decayFatiguePenalties reduces penalties gradually.
   */
  public function testDecayFatiguePenalties(): void {
    $state = $this->service->initializeAttentionState();
    $state['engagement_scores'] = [
      'npc:eldric' => ['fatigue_penalty' => 10],
      'npc:gribbles' => ['fatigue_penalty' => 5],
    ];

    $this->service->decayFatiguePenalties($state);

    $this->assertSame(9, $state['engagement_scores']['npc:eldric']['fatigue_penalty']);
    $this->assertSame(4, $state['engagement_scores']['npc:gribbles']['fatigue_penalty']);
  }

  /**
   * Tests decayFatiguePenalties doesn't go below 0.
   */
  public function testDecayFatiguePenaltiesFloor(): void {
    $state = $this->service->initializeAttentionState();
    $state['engagement_scores'] = [
      'npc:eldric' => ['fatigue_penalty' => 0],
    ];

    $this->service->decayFatiguePenalties($state);

    $this->assertSame(0, $state['engagement_scores']['npc:eldric']['fatigue_penalty']);
  }

  /**
   * Tests resetAttentionState clears all state.
   */
  public function testResetAttentionState(): void {
    $state = [
      'last_speaker' => 'npc:eldric',
      'engagement_duration' => 5,
      'participants' => ['npc:eldric', 'npc:gribbles'],
    ];

    $this->service->resetAttentionState($state);

    $this->assertNull($state['last_speaker']);
    $this->assertSame(0, $state['engagement_duration']);
    $this->assertEmpty($state['participants']);
  }

  /**
   * Helper to get private method via reflection.
   */
  private function getPrivateMethod(string $method_name) {
    $reflection = new \ReflectionClass($this->service);
    $method = $reflection->getMethod($method_name);
    $method->setAccessible(TRUE);
    return $method;
  }

}
