<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\dungeoncrawler_content\Service\AiConversationEncounterAiProvider;
use Drupal\dungeoncrawler_content\Service\StubEncounterAiProvider;

/**
 * Tests for AiConversationEncounterAiProvider.
 *
 * @group dungeoncrawler_content
 * @group combat
 * @group ai
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\AiConversationEncounterAiProvider
 */
class AiConversationEncounterAiProviderTest extends UnitTestCase {

  protected AIApiService $aiApiService;

  protected LoggerChannelFactoryInterface $loggerFactory;

  protected ConfigFactoryInterface $configFactory;

  protected StubEncounterAiProvider $fallbackProvider;

  protected AiConversationEncounterAiProvider $provider;

  protected function setUp(): void {
    parent::setUp();

    $this->aiApiService = $this->createMock(AIApiService::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->fallbackProvider = new StubEncounterAiProvider();

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['encounter_ai_retry_attempts', 2],
      ['encounter_ai_recommendation_max_tokens', 800],
      ['encounter_ai_narration_max_tokens', 500],
    ]);
    $this->configFactory->method('get')->with('dungeoncrawler_content.settings')->willReturn($config);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->provider = new AiConversationEncounterAiProvider(
      $this->aiApiService,
      $this->loggerFactory,
      $this->configFactory,
      $this->fallbackProvider
    );
  }

  /**
   * @covers ::getProviderName
   */
  public function testGetProviderName(): void {
    $this->assertSame('ai_conversation', $this->provider->getProviderName());
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionUsesAiConversationResponse(): void {
    $context = $this->buildEncounterContext();

    $this->aiApiService->method('invokeModelDirect')->willReturn([
      'success' => TRUE,
      'response' => json_encode([
        'version' => 'v1',
        'actor_instance_id' => 'npc-1',
        'recommended_action' => [
          'type' => 'strike',
          'target_instance_id' => 'pc-1',
          'action_cost' => 1,
          'parameters' => ['weapon' => 'spear'],
        ],
        'alternatives' => [],
        'rationale' => 'Close threat in reach.',
        'confidence' => 0.81,
      ]),
    ]);

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertSame('ai_conversation', $recommendation['provider']);
    $this->assertFalse($recommendation['fallback_used']);
    $this->assertSame('npc-1', $recommendation['actor_instance_id']);
    $this->assertSame('strike', $recommendation['recommended_action']['type']);
    $this->assertSame('pc-1', $recommendation['recommended_action']['target_instance_id']);
    $this->assertSame('Close threat in reach.', $recommendation['rationale']);
    $this->assertSame(1, $recommendation['request_attempts']);
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionFallsBackWhenAiCallFails(): void {
    $context = $this->buildEncounterContext();

    $this->aiApiService->method('invokeModelDirect')->willReturn([
      'success' => FALSE,
      'error' => 'Transport failure',
    ]);

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertSame('ai_conversation', $recommendation['provider']);
    $this->assertTrue($recommendation['fallback_used']);
    $this->assertSame('strike', $recommendation['recommended_action']['type']);
    $this->assertSame('pc-1', $recommendation['recommended_action']['target_instance_id']);
    $this->assertStringContainsString('Transport failure', (string) $recommendation['fallback_reason']);
    $this->assertSame(2, $recommendation['request_attempts']);
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionRetriesAndSucceedsOnSecondAttempt(): void {
    $context = $this->buildEncounterContext();

    $this->aiApiService->expects($this->exactly(2))
      ->method('invokeModelDirect')
      ->willReturnOnConsecutiveCalls(
        [
          'success' => FALSE,
          'error' => 'Transient timeout',
        ],
        [
          'success' => TRUE,
          'response' => json_encode([
            'version' => 'v1',
            'actor_instance_id' => 'npc-1',
            'recommended_action' => [
              'type' => 'strike',
              'target_instance_id' => 'pc-1',
              'action_cost' => 1,
              'parameters' => [],
            ],
            'alternatives' => [],
            'rationale' => 'Recovered on retry.',
            'confidence' => 0.7,
          ]),
        ]
      );

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertFalse($recommendation['fallback_used']);
    $this->assertSame('Recovered on retry.', $recommendation['rationale']);
    $this->assertSame(2, $recommendation['request_attempts']);
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionParsesMarkdownCodeFenceJson(): void {
    $context = $this->buildEncounterContext();

    $this->aiApiService->method('invokeModelDirect')->willReturn([
      'success' => TRUE,
      'response' => "```json\n{\n  \"version\": \"v1\",\n  \"actor_instance_id\": \"npc-1\",\n  \"recommended_action\": {\n    \"type\": \"strike\",\n    \"target_instance_id\": \"pc-1\",\n    \"action_cost\": 1,\n    \"parameters\": {}\n  },\n  \"alternatives\": [],\n  \"rationale\": \"Maintain pressure.\",\n  \"confidence\": 0.73\n}\n```",
    ]);

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertFalse($recommendation['fallback_used']);
    $this->assertSame('strike', $recommendation['recommended_action']['type']);
    $this->assertSame('pc-1', $recommendation['recommended_action']['target_instance_id']);
  }

  /**
   * @covers ::generateEncounterNarration
   */
  public function testGenerateEncounterNarrationUsesAiConversationResponse(): void {
    $context = $this->buildEncounterContext();

    $this->aiApiService->method('invokeModelDirect')->willReturn([
      'success' => TRUE,
      'response' => json_encode([
        'narration' => 'The goblin lunges forward with practiced aggression.',
        'style' => 'neutral-tactical',
      ]),
    ]);

    $narration = $this->provider->generateEncounterNarration($context);

    $this->assertSame('ai_conversation', $narration['provider']);
    $this->assertFalse($narration['fallback_used']);
    $this->assertSame('The goblin lunges forward with practiced aggression.', $narration['narration']);
    $this->assertSame(1, $narration['request_attempts']);
  }

  /**
   * @covers ::generateEncounterNarration
   */
  public function testGenerateEncounterNarrationFallsBackOnMalformedPayload(): void {
    $context = $this->buildEncounterContext();

    $this->aiApiService->method('invokeModelDirect')->willReturn([
      'success' => TRUE,
      'response' => '{"style":"neutral-tactical"}',
    ]);

    $narration = $this->provider->generateEncounterNarration($context);

    $this->assertSame('ai_conversation', $narration['provider']);
    $this->assertTrue($narration['fallback_used']);
    $this->assertStringContainsString('Round', $narration['narration']);
    $this->assertSame(1, $narration['request_attempts']);
  }

  /**
   * Build baseline encounter context fixture.
   *
   * @return array<string, mixed>
   *   Encounter context payload.
   */
  private function buildEncounterContext(): array {
    return [
      'campaign_id' => 22,
      'encounter_id' => 501,
      'status' => 'active',
      'current_round' => 2,
      'turn_index' => 0,
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'name' => 'Goblin Raider',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'participants' => [
        [
          'entity_ref' => 'pc-1',
          'team' => 'player',
          'is_defeated' => FALSE,
        ],
        [
          'entity_ref' => 'npc-1',
          'team' => 'npc',
          'is_defeated' => FALSE,
        ],
      ],
      'allowed_actions' => ['strike', 'end_turn'],
    ];
  }

}
