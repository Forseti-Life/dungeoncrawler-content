<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\StubEncounterAiProvider;

/**
 * Tests for StubEncounterAiProvider.
 *
 * @group dungeoncrawler_content
 * @group combat
 * @group ai
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\StubEncounterAiProvider
 */
class StubEncounterAiProviderTest extends UnitTestCase {

  protected StubEncounterAiProvider $provider;

  protected function setUp(): void {
    parent::setUp();
    $this->provider = new StubEncounterAiProvider();
  }

  /**
   * @covers ::getProviderName
   */
  public function testGetProviderName(): void {
    $this->assertSame('stub', $this->provider->getProviderName());
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionTargetsFirstAlivePlayer(): void {
    $context = [
      'current_actor' => [
        'entity_ref' => 'npc-goblin-1',
      ],
      'participants' => [
        [
          'entity_ref' => 'pc-defeated',
          'team' => 'player',
          'is_defeated' => TRUE,
        ],
        [
          'entity_ref' => 'pc-active',
          'team' => 'player',
          'is_defeated' => FALSE,
        ],
      ],
    ];

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertSame('v1', $recommendation['version']);
    $this->assertSame('npc-goblin-1', $recommendation['actor_instance_id']);
    $this->assertSame('strike', $recommendation['recommended_action']['type']);
    $this->assertSame('pc-active', $recommendation['recommended_action']['target_instance_id']);
    $this->assertSame(1, $recommendation['recommended_action']['action_cost']);
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionFallsBackToEndTurnWhenNoPlayerAlive(): void {
    $context = [
      'current_actor' => [
        'entity_ref' => 'npc-goblin-1',
      ],
      'participants' => [
        [
          'entity_ref' => 'pc-defeated',
          'team' => 'player',
          'is_defeated' => TRUE,
        ],
      ],
    ];

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertSame('end_turn', $recommendation['recommended_action']['type']);
    $this->assertNull($recommendation['recommended_action']['target_instance_id']);
    $this->assertSame(0.4, $recommendation['confidence']);
  }

  /**
   * @covers ::generateEncounterNarration
   */
  public function testGenerateEncounterNarrationIncludesRoundAndActorName(): void {
    $context = [
      'current_round' => 4,
      'current_actor' => [
        'name' => 'Goblin Raider',
      ],
    ];

    $narration = $this->provider->generateEncounterNarration($context);

    $this->assertSame(4, $narration['round']);
    $this->assertSame('stub', $narration['provider']);
    $this->assertStringContainsString('Round 4', $narration['narration']);
    $this->assertStringContainsString('Goblin Raider', $narration['narration']);
  }

}
