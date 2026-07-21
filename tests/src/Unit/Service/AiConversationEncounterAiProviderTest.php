<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\dungeoncrawler_content\Service\AiSessionManager;
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

  protected AiSessionManager $sessionManager;

  protected AiConversationEncounterAiProvider $provider;

  protected function setUp(): void {
    parent::setUp();

    $this->aiApiService = $this->createMock(AIApiService::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->fallbackProvider = new StubEncounterAiProvider();
    $this->sessionManager = $this->createMock(AiSessionManager::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['encounter_ai_retry_attempts', 2],
      ['encounter_ai_recommendation_max_tokens', 800],
      ['encounter_ai_narration_max_tokens', 500],
    ]);
    $this->configFactory->method('get')->with('dungeoncrawler_content.settings')->willReturn($config);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);
    $this->sessionManager->method('npcSessionKey')->willReturn('campaign.22.npc.npc-1');
    $this->sessionManager->method('buildSessionContext')->willReturn('');

    $this->provider = new AiConversationEncounterAiProvider(
      $this->aiApiService,
      $this->loggerFactory,
      $this->configFactory,
      $this->fallbackProvider,
      $this->sessionManager
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
        'decision_reason' => 'Close threat in reach.',
        'decision_basis' => [
          'used_profile' => TRUE,
          'used_psychology' => TRUE,
          'used_availability' => TRUE,
        ],
        'contract_version' => 'hash-001',
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
    $this->assertSame('Close threat in reach.', $recommendation['decision_reason']);
    $this->assertTrue(is_array($recommendation['decision_basis']));
    $this->assertSame(1, $recommendation['request_attempts']);
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionThrowsWhenAiCallFails(): void {
    $context = $this->buildEncounterContext();

    $this->aiApiService->method('invokeModelDirect')->willReturn([
      'success' => FALSE,
      'error' => 'Transport failure',
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Transport failure');
    $this->provider->recommendNpcAction($context);
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
            'decision_reason' => 'Recovered on retry.',
            'decision_basis' => [
              'used_profile' => TRUE,
              'used_psychology' => TRUE,
              'used_availability' => TRUE,
            ],
            'contract_version' => 'hash-001',
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
      'response' => "```json\n{\n  \"version\": \"v1\",\n  \"actor_instance_id\": \"npc-1\",\n  \"recommended_action\": {\n    \"type\": \"strike\",\n    \"target_instance_id\": \"pc-1\",\n    \"action_cost\": 1,\n    \"parameters\": {}\n  },\n  \"alternatives\": [],\n  \"rationale\": \"Maintain pressure.\",\n  \"decision_reason\": \"Maintain pressure.\",\n  \"decision_basis\": {\n    \"used_profile\": true,\n    \"used_psychology\": true,\n    \"used_availability\": true\n  },\n  \"contract_version\": \"hash-001\",\n  \"confidence\": 0.73\n}\n```",
    ]);

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertFalse($recommendation['fallback_used']);
    $this->assertSame('strike', $recommendation['recommended_action']['type']);
    $this->assertSame('pc-1', $recommendation['recommended_action']['target_instance_id']);
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionInjectsPsychologyContextIntoPrompt(): void {
    $context = $this->buildEncounterContext();
    $context['current_actor_profile'] = [
      'attitude' => 'hostile',
      'motivations' => 'Protect the relic',
      'personality_axes' => ['cunning' => 8, 'discipline' => 7],
      'goals' => ['Gain XP', 'Gain Treasure', 'Protect the relic'],
    ];
    $context['current_actor_tactical_intent'] = [
      'intent' => 'finish_weakest',
      'target_strategy' => 'weakest_adjacent',
      'decision_reason' => 'High cunning/discipline profile prioritizes focused pressure on weak targets.',
    ];
    $context['npc_psychology'] = "=== NPC COMBAT PERSONALITY ===\nFighting motivation: Protect the relic";

    $this->aiApiService->expects($this->once())
      ->method('invokeModelDirect')
      ->with(
        $this->callback(static function ($prompt): bool {
          if (!is_string($prompt)) {
            return FALSE;
          }
          $payload = json_decode($prompt, TRUE);
          if (!is_array($payload)) {
            return FALSE;
          }
          $encounter = is_array($payload['encounter'] ?? NULL) ? $payload['encounter'] : [];
          return ($encounter['current_actor_profile']['motivations'] ?? '') === 'Protect the relic'
            && ($encounter['current_actor_profile']['goals'][0] ?? '') === 'Gain XP'
            && ($encounter['current_actor_tactical_intent']['intent'] ?? '') === 'finish_weakest'
            && ($encounter['npc_psychology'] ?? '') === "=== NPC COMBAT PERSONALITY ===\nFighting motivation: Protect the relic";
        }),
        'dungeoncrawler_content',
        'encounter_npc_recommendation',
        $this->anything(),
        $this->anything()
      )
      ->willReturn([
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
          'rationale' => 'Use motivation-aligned pressure.',
          'decision_reason' => 'Use motivation-aligned pressure.',
          'decision_basis' => [
            'used_profile' => TRUE,
            'used_psychology' => TRUE,
            'used_availability' => TRUE,
          ],
          'contract_version' => 'hash-001',
          'confidence' => 0.74,
        ]),
      ]);

    $recommendation = $this->provider->recommendNpcAction($context);
    $this->assertFalse($recommendation['fallback_used']);
    $this->assertSame('Use motivation-aligned pressure.', $recommendation['rationale']);
    $this->assertSame('Use motivation-aligned pressure.', $recommendation['decision_reason']);
    $this->assertTrue(is_array($recommendation['decision_basis']));
  }

  /**
   * @covers ::recommendNpcAction
   */
  public function testRecommendNpcActionUsesCanonicalActionAvailabilityEnvelopeInPrompt(): void {
    $context = $this->buildEncounterContext();
    $context['allowed_actions'] = ['stride'];
    $context['actions_available_to_me_this_turn'] = [
      'actor_instance_id' => 'npc-1',
      'actions_remaining' => 1,
      'reaction_available' => FALSE,
      'available_actions' => ['strike', 'end_turn'],
      'action_option_families' => [
        'cast_spell' => [
          'family' => 'spells',
          'option_count' => 1,
          'options' => [
            ['id' => 'magic-missile', 'label' => 'Magic Missile', 'action_cost' => 2, 'targeting' => 'contextual', 'metadata' => []],
          ],
        ],
      ],
      'action_contract' => [
        'phase' => 'encounter',
        'actor_id' => 'npc-1',
        'available_actions' => ['strike', 'end_turn'],
      ],
    ];

    $this->aiApiService->expects($this->once())
      ->method('invokeModelDirect')
      ->with(
        $this->callback(static function ($prompt): bool {
          if (!is_string($prompt)) {
            return FALSE;
          }
          $payload = json_decode($prompt, TRUE);
          if (!is_array($payload)) {
            return FALSE;
          }
          $constraints = is_array($payload['constraints'] ?? NULL) ? $payload['constraints'] : [];
          $encounter = is_array($payload['encounter'] ?? NULL) ? $payload['encounter'] : [];
          $availability = is_array($encounter['actions_available_to_me_this_turn'] ?? NULL)
            ? $encounter['actions_available_to_me_this_turn']
            : [];
          $action_contract = is_array($encounter['action_contract'] ?? NULL)
            ? $encounter['action_contract']
            : [];
          $families = is_array($action_contract['action_option_families'] ?? NULL)
            ? $action_contract['action_option_families']
            : [];

          return ($constraints['allowed_actions'] ?? NULL) === ['strike', 'end_turn']
            && ($constraints['action_cost_max'] ?? NULL) === 1
            && ($availability['available_actions'] ?? NULL) === ['strike', 'end_turn']
            && (($families['cast_spell']['option_count'] ?? NULL) === 1);
        }),
        'dungeoncrawler_content',
        'encounter_npc_recommendation',
        $this->anything(),
        $this->anything()
      )
      ->willReturn([
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
          'rationale' => 'Uses canonical turn envelope constraints.',
          'decision_reason' => 'Uses canonical turn envelope constraints.',
          'decision_basis' => [
            'used_profile' => TRUE,
            'used_psychology' => TRUE,
            'used_availability' => TRUE,
          ],
          'contract_version' => 'hash-001',
          'confidence' => 0.76,
        ]),
      ]);

    $recommendation = $this->provider->recommendNpcAction($context);

    $this->assertFalse($recommendation['fallback_used']);
    $this->assertSame('strike', $recommendation['recommended_action']['type']);
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
      'action_contract' => [
        'phase' => 'encounter',
        'actor_id' => 'npc-1',
        'available_actions' => ['strike', 'end_turn'],
      ],
      'actions_available_to_me_this_turn' => [
        'actor_instance_id' => 'npc-1',
        'actions_remaining' => 3,
        'reaction_available' => FALSE,
        'available_actions' => ['strike', 'end_turn'],
        'action_contract' => [
          'phase' => 'encounter',
          'actor_id' => 'npc-1',
          'available_actions' => ['strike', 'end_turn'],
        ],
      ],
      'allowed_actions' => ['strike', 'end_turn'],
      'action_contract_hash' => 'hash-001',
    ];
  }

}
