<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorDecisionValidatorService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\ActorDecisionValidatorService
 */
class ActorDecisionValidatorServiceTest extends UnitTestCase {

  /**
   * @covers ::validateDecision
   */
  public function testValidateDecisionAcceptsActionEnvelope(): void {
    $validator = new ActorDecisionValidatorService();
    $result = $validator->validateDecision([
      'decision_contract_version' => 'actor_decision_v1',
      'actor_instance_id' => 'npc-1',
      'tool' => 'action',
      'decision_reason' => 'Advance.',
      'decision_basis' => [
        'used_profile' => TRUE,
        'used_psychology' => TRUE,
        'used_availability' => TRUE,
      ],
      'confidence' => 0.8,
      'contract_version' => 'hash-1',
      'payload' => [
        'action' => [
          'type' => 'strike',
          'target_instance_id' => 'pc-1',
          'action_cost' => 1,
          'parameters' => [],
        ],
      ],
    ], 'npc-1');

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  /**
   * @covers ::validateDecision
   */
  public function testValidateDecisionRejectsInvalidChatEnvelope(): void {
    $validator = new ActorDecisionValidatorService();
    $result = $validator->validateDecision([
      'decision_contract_version' => 'actor_decision_v1',
      'actor_instance_id' => 'npc-1',
      'tool' => 'chat',
      'decision_reason' => 'Speak.',
      'decision_basis' => [
        'used_profile' => TRUE,
        'used_psychology' => TRUE,
        'used_availability' => TRUE,
      ],
      'confidence' => 0.7,
      'contract_version' => 'hash-2',
      'payload' => [
        'chat' => [
          'channel' => '',
          'message' => '',
        ],
      ],
    ], 'npc-1');

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
  }

}

