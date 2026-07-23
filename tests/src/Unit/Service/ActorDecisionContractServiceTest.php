<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorDecisionContractService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorDecisionContractService
 */
class ActorDecisionContractServiceTest extends UnitTestCase {

  /**
   * @covers ::normalizeActionIds
   */
  public function testNormalizeActionIdsCanonicalizesAndDeduplicates(): void {
    $service = new ActorDecisionContractService();
    $normalized = $service->normalizeActionIds([' Strike ', 'strike', 'TALK', '', ' step ']);
    $this->assertSame(['strike', 'talk', 'step'], $normalized);
  }

  /**
   * @covers ::buildActionContractHash
   */
  public function testBuildActionContractHashIsDeterministicAcrossEquivalentInputs(): void {
    $service = new ActorDecisionContractService();

    $action_contract = [
      'phase' => 'encounter',
      'actions' => [
        ['id' => 'strike', 'cost' => 1],
        ['id' => 'talk', 'cost' => 1],
      ],
    ];

    $hash_a = $service->buildActionContractHash($action_contract, [' strike ', 'talk', 'TALK']);
    $hash_b = $service->buildActionContractHash($action_contract, ['talk', 'strike']);

    $this->assertSame($hash_a, $hash_b);
    $this->assertNotSame('', $hash_a);
  }

  /**
   * @covers ::buildActorDecisionEnvelopeFromRecommendation
   */
  public function testBuildActorDecisionEnvelopeFromRecommendationBuildsActionToolEnvelope(): void {
    $service = new ActorDecisionContractService();
    $envelope = $service->buildActorDecisionEnvelopeFromRecommendation([
      'actor_instance_id' => 'npc-1',
      'decision_reason' => 'Push advantage.',
      'decision_basis' => [
        'used_profile' => TRUE,
        'used_psychology' => TRUE,
        'used_availability' => TRUE,
      ],
      'confidence' => 0.73,
      'contract_version' => 'contract-hash-1',
      'recommended_action' => [
        'type' => 'strike',
        'target_instance_id' => 'pc-1',
        'action_cost' => 1,
        'parameters' => [],
      ],
    ], ['action_contract_hash' => 'contract-hash-1'], 'stub');

    $this->assertSame(ActorDecisionContractService::ACTOR_DECISION_CONTRACT_VERSION, $envelope['decision_contract_version']);
    $this->assertSame('action', $envelope['tool']);
    $this->assertSame('stub', $envelope['provider']);
    $this->assertSame('npc-1', $envelope['actor_instance_id']);
    $this->assertSame('contract-hash-1', $envelope['contract_version']);
    $this->assertSame('strike', $envelope['payload']['action']['type']);
  }

  /**
   * @covers ::buildActorDecisionEnvelopeFromChatDialogue
   */
  public function testBuildActorDecisionEnvelopeFromChatDialogueBuildsChatToolEnvelope(): void {
    $service = new ActorDecisionContractService();
    $envelope = $service->buildActorDecisionEnvelopeFromChatDialogue([
      'entity_ref' => 'npc-1',
      'channel' => 'room',
      'delivery_type' => 'room_interjection',
      'text' => 'Keep your voices down.',
      'context' => ['generation_source' => 'model'],
    ], 'model-provider', ['used_profile' => TRUE, 'used_psychology' => TRUE, 'used_availability' => TRUE], 'hash-123');

    $this->assertSame('chat', $envelope['tool']);
    $this->assertSame('npc-1', $envelope['actor_instance_id']);
    $this->assertSame('hash-123', $envelope['contract_version']);
    $this->assertSame('room', $envelope['payload']['chat']['channel']);
    $this->assertSame('Keep your voices down.', $envelope['payload']['chat']['message']);
  }

}
