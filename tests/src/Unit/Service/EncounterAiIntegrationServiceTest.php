<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\EncounterAiProviderInterface;

/**
 * Tests for EncounterAiIntegrationService.
 *
 * @group dungeoncrawler_content
 * @group combat
 * @group ai
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService
 */
class EncounterAiIntegrationServiceTest extends UnitTestCase {

  protected EncounterAiProviderInterface $provider;

  protected TimeInterface $time;

  protected LoggerChannelFactoryInterface $loggerFactory;

  protected EncounterAiIntegrationService $service;

  protected function setUp(): void {
    parent::setUp();

    $this->provider = $this->createMock(EncounterAiProviderInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);
    $this->time->method('getCurrentTime')->willReturn(1700000000);

    $this->service = new EncounterAiIntegrationService(
      $this->provider,
      $this->time,
      $this->loggerFactory
    );
  }

  /**
   * @covers ::buildEncounterContext
   */
  public function testBuildEncounterContextThrowsWhenEncounterMissing(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Encounter context requires encounter snapshot.');

    $this->service->buildEncounterContext(0, 10, NULL);
  }

  /**
   * @covers ::buildEncounterContext
   */
  public function testBuildEncounterContextReturnsNormalizedEnvelope(): void {
    $encounter = [
      'status' => 'active',
      'current_round' => 3,
      'turn_index' => 1,
      'participants' => [
        ['entity_ref' => 'pc-1', 'team' => 'player'],
        ['entity_ref' => json_encode(['content_id' => 'npc-2', 'heritage' => 'chameleon']), 'team' => 'npc'],
      ],
    ];

    $context = $this->service->buildEncounterContext(77, 501, $encounter);

    $this->assertSame(77, $context['campaign_id']);
    $this->assertSame(501, $context['encounter_id']);
    $this->assertSame('active', $context['status']);
    $this->assertSame(3, $context['current_round']);
    $this->assertSame(json_encode(['content_id' => 'npc-2', 'heritage' => 'chameleon']), $context['current_actor']['entity_ref']);
    $this->assertContains('strike', $context['allowed_actions']);
    $this->assertContains('raise_shield', $context['allowed_actions']);
    $this->assertSame('encounter', $context['action_contract']['phase']);
    $this->assertSame('npc-2', $context['action_contract']['actor_id']);
    $this->assertTrue(is_array($context['action_contract']['actions']));
    $this->assertTrue(is_array($context['action_contract']['action_option_families'] ?? NULL));
    $this->assertArrayHasKey('cast_spell', $context['action_contract']['action_option_families']);
    $this->assertTrue(isset($context['actions_available_to_me_this_turn']));
    $this->assertSame('npc-2', $context['actions_available_to_me_this_turn']['actor_instance_id']);
    $this->assertContains('end_turn', $context['actions_available_to_me_this_turn']['available_actions']);
    $this->assertSame('encounter', $context['actions_available_to_me_this_turn']['action_contract']['phase']);
    $this->assertNotSame('', (string) ($context['action_contract_hash'] ?? ''));
    $this->assertSame([], $context['resolved_actor_context']);
    $this->assertNull($context['disposition_summary']);
    $this->assertNull($context['aggression_summary']);
    $this->assertNull($context['stance_summary']);
    $this->assertSame([], $context['relationship_attitudes']);
  }

  /**
   * @covers ::buildEncounterContext
   */
  public function testBuildEncounterContextUsesStableTopLevelEnvelopeShape(): void {
    $encounter = [
      'status' => 'active',
      'current_round' => 1,
      'turn_index' => 0,
      'participants' => [
        ['entity_ref' => 'npc-1', 'team' => 'npc'],
      ],
    ];

    $context = $this->service->buildEncounterContext(77, 700, $encounter);

    $this->assertSame([
      'campaign_id',
      'encounter_id',
      'status',
      'current_round',
      'turn_index',
      'current_actor',
      'participants',
      'allowed_actions',
      'action_contract',
      'action_contract_hash',
      'action_option_families',
      'actions_available_to_me_this_turn',
      'resolved_actor_context',
      'disposition_summary',
      'aggression_summary',
      'stance_summary',
      'relationship_attitudes',
      'context_built_at',
    ], array_keys($context));
    $this->assertSame(
      $context['action_contract']['action_option_families'] ?? [],
      $context['action_option_families']
    );
  }

  /**
   * @covers ::buildEncounterContext
   */
  public function testBuildEncounterContextProjectsParticipantStateIntoActionOptionFamilies(): void {
    $encounter = [
      'status' => 'active',
      'current_round' => 1,
      'turn_index' => 0,
      'participants' => [
        [
          'entity_ref' => 'npc-7',
          'team' => 'npc',
          'state' => [
            'spells' => [
              'known' => [
                ['id' => 'magic-missile', 'name' => 'Magic Missile', 'action_cost' => 2],
              ],
            ],
          ],
        ],
      ],
    ];

    $context = $this->service->buildEncounterContext(77, 502, $encounter);
    $families = $context['action_contract']['action_option_families'] ?? [];

    $this->assertSame(1, $families['cast_spell']['option_count'] ?? NULL);
    $this->assertSame('magic-missile', $families['cast_spell']['options'][0]['id'] ?? NULL);
    $this->assertContains('cast_spell', $context['allowed_actions']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationReturnsValidForNpcStrike(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-1',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['strike', 'end_turn'],
      'action_contract' => [
        'actions' => [
          ['id' => 'strike', 'cost' => 1],
          ['id' => 'end_turn', 'cost' => 0],
        ],
      ],
    ];

    $recommendation = $this->buildRecommendation('npc-1', 'strike', 1, 'contract-hash-1', [], NULL);

    $validation = $this->service->validateRecommendation($recommendation, $context);

    $this->assertTrue($validation['valid']);
    $this->assertSame([], $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationReturnsErrorsForInvalidActorAndCost(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-2',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'player',
        'actions_remaining' => 1,
      ],
      'allowed_actions' => ['strike'],
      'action_contract' => [
        'actions' => [
          ['id' => 'strike', 'cost' => 1],
        ],
      ],
    ];

    $recommendation = $this->buildRecommendation('npc-2', 'unsupported_action', 3, 'contract-hash-2', [], NULL);

    $validation = $this->service->validateRecommendation($recommendation, $context);

    $this->assertFalse($validation['valid']);
    $this->assertNotEmpty($validation['errors']);
    $this->assertContains('actor_instance_id must match active turn actor.', $validation['errors']);
    $this->assertContains('active turn actor is a player; NPC recommendation is not applicable.', $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationPrefersCanonicalActionAvailabilityEnvelope(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-3',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['strike', 'end_turn'],
      'actions_available_to_me_this_turn' => [
        'actor_instance_id' => 'npc-1',
        'actions_remaining' => 0,
        'available_actions' => ['end_turn'],
        'action_contract' => [
          'phase' => 'encounter',
          'available_actions' => ['end_turn'],
          'actions' => [
            ['id' => 'end_turn', 'cost' => 0],
          ],
        ],
      ],
    ];

    $recommendation = $this->buildRecommendation('npc-1', 'strike', 1, 'contract-hash-3', [], NULL);

    $validation = $this->service->validateRecommendation($recommendation, $context);

    $this->assertFalse($validation['valid']);
    $this->assertContains('recommended_action.type is not supported by server action handlers.', $validation['errors']);
    $this->assertContains('recommended_action.type is missing from the canonical action contract.', $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationAllowsCanonicalZeroCostEndTurn(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-4',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 0,
      ],
      'actions_available_to_me_this_turn' => [
        'actor_instance_id' => 'npc-1',
        'actions_remaining' => 0,
        'available_actions' => ['end_turn'],
        'action_contract' => [
          'phase' => 'encounter',
          'available_actions' => ['end_turn'],
          'actions' => [
            ['id' => 'end_turn', 'cost' => 0],
          ],
        ],
      ],
    ];

    $recommendation = $this->buildRecommendation('npc-1', 'end_turn', 0, 'contract-hash-4', [], NULL);

    $validation = $this->service->validateRecommendation($recommendation, $context);

    $this->assertTrue($validation['valid']);
    $this->assertSame([], $validation['errors']);
  }

  /**
   * @covers ::requestNpcActionRecommendation
   */
  public function testRequestNpcActionRecommendationWrapsProviderResponse(): void {
    $context = [
      'encounter_id' => 901,
      'campaign_id' => 0,
      'action_contract_hash' => 'contract-hash-5',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['strike'],
      'action_contract' => [
        'actions' => [
          ['id' => 'strike', 'cost' => 1],
        ],
      ],
    ];

    $this->provider->method('getProviderName')->willReturn('stub');
    $this->provider->method('recommendNpcAction')->willReturn(
      $this->buildRecommendation('npc-1', 'strike', 1, 'contract-hash-5', [], NULL)
    );

    $response = $this->service->requestNpcActionRecommendation($context);

    $this->assertTrue($response['success']);
    $this->assertSame('stub', $response['provider']);
    $this->assertSame('action', $response['actor_decision']['tool'] ?? NULL);
    $this->assertSame('contract-hash-5', $response['actor_decision']['contract_version'] ?? NULL);
    $this->assertSame('strike', $response['actor_decision']['payload']['action']['type'] ?? NULL);
    $this->assertTrue($response['validation']['valid']);
    $this->assertSame(1700000000, $response['requested_at']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationRejectsMissingTalkMessageParameter(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-6',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['talk'],
      'action_contract' => [
        'actions' => [
          ['id' => 'talk', 'cost' => 1, 'targeting' => 'entity_or_room'],
        ],
      ],
    ];
    $recommendation = $this->buildRecommendation('npc-1', 'talk', 1, 'contract-hash-6', [], 'target-1');

    $validation = $this->service->validateRecommendation($recommendation, $context);
    $this->assertFalse($validation['valid']);
    $this->assertContains('recommended_action.parameters.message is required for talk.', $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationRejectsMissingStrideTargetHexParameters(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-6b',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['stride'],
      'action_contract' => [
        'actions' => [
          ['id' => 'stride', 'cost' => 1, 'targeting' => 'connected_room'],
        ],
      ],
    ];
    $recommendation = $this->buildRecommendation('npc-1', 'stride', 1, 'contract-hash-6b', [], 'target-room');

    $validation = $this->service->validateRecommendation($recommendation, $context);
    $this->assertFalse($validation['valid']);
    $this->assertContains('recommended_action.parameters.target_hex.{q,r} is required for stride.', $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationRejectsMissingTransitionTargetRoomParameter(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-6c',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['transition'],
      'action_contract' => [
        'actions' => [
          ['id' => 'transition', 'cost' => 0, 'targeting' => 'connected_room'],
        ],
      ],
    ];
    $recommendation = $this->buildRecommendation('npc-1', 'transition', 0, 'contract-hash-6c', [], 'target-room');

    $validation = $this->service->validateRecommendation($recommendation, $context);
    $this->assertFalse($validation['valid']);
    $this->assertContains('recommended_action.parameters.target_room_id is required for transition.', $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationRejectsMissingOptionIdForOptionActions(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-6d',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['cast_spell'],
      'action_contract' => [
        'actions' => [
          ['id' => 'cast_spell', 'cost' => 2, 'targeting' => 'entity_or_object'],
        ],
      ],
    ];
    $recommendation = $this->buildRecommendation('npc-1', 'cast_spell', 2, 'contract-hash-6d', [], 'target-1');

    $validation = $this->service->validateRecommendation($recommendation, $context);
    $this->assertFalse($validation['valid']);
    $this->assertContains('recommended_action.parameters.option_id is required for cast_spell.', $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationRejectsContractVersionMismatch(): void {
    $context = [
      'action_contract_hash' => 'contract-hash-7',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['end_turn'],
      'action_contract' => [
        'actions' => [
          ['id' => 'end_turn', 'cost' => 0],
        ],
      ],
    ];
    $recommendation = $this->buildRecommendation('npc-1', 'end_turn', 0, 'wrong-hash', [], NULL);

    $validation = $this->service->validateRecommendation($recommendation, $context);
    $this->assertFalse($validation['valid']);
    $this->assertContains('contract_version does not match current action contract hash.', $validation['errors']);
  }

  /**
   * @covers ::requestNpcActionRecommendation
   */
  public function testRequestNpcActionRecommendationThrowsOnInvalidRecommendationContract(): void {
    $context = [
      'encounter_id' => 901,
      'campaign_id' => 0,
      'action_contract_hash' => 'contract-hash-8',
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['talk'],
      'action_contract' => [
        'actions' => [
          ['id' => 'talk', 'cost' => 1, 'targeting' => 'entity_or_room'],
        ],
      ],
    ];

    $this->provider->method('getProviderName')->willReturn('stub');
    $this->provider->method('recommendNpcAction')->willReturn(
      $this->buildRecommendation('npc-1', 'talk', 1, 'contract-hash-8', [], 'target-1')
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Encounter AI recommendation contract violation:');
    $this->service->requestNpcActionRecommendation($context);
  }

  /**
   * @covers ::requestEncounterNarration
   */
  public function testRequestEncounterNarrationWrapsProviderNarration(): void {
    $this->provider->method('getProviderName')->willReturn('stub');
    $this->provider->method('generateEncounterNarration')->willReturn([
      'narration' => 'A measured tactical beat.',
    ]);

    $response = $this->service->requestEncounterNarration(['encounter_id' => 1001]);

    $this->assertTrue($response['success']);
    $this->assertSame('stub', $response['provider']);
    $this->assertSame('A measured tactical beat.', $response['narration']['narration']);
    $this->assertSame(1700000000, $response['requested_at']);
  }

  protected function buildRecommendation(
    string $actor_instance_id,
    string $action_type,
    int $action_cost,
    string $contract_version,
    array $parameters = [],
    ?string $target_instance_id = NULL
  ): array {
    return [
      'version' => 'v1',
      'contract_version' => $contract_version,
      'actor_instance_id' => $actor_instance_id,
      'recommended_action' => [
        'type' => $action_type,
        'target_instance_id' => $target_instance_id,
        'action_cost' => $action_cost,
        'parameters' => $parameters,
      ],
      'alternatives' => [],
      'rationale' => 'test rationale',
      'decision_reason' => 'test decision reason',
      'decision_basis' => [
        'used_profile' => TRUE,
        'used_psychology' => TRUE,
        'used_availability' => TRUE,
      ],
      'confidence' => 0.8,
    ];
  }

}
