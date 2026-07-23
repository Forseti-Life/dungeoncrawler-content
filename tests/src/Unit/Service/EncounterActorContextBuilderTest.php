<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ActorActionAvailabilityService;
use Drupal\dungeoncrawler_content\Service\ActorDecisionContractService;
use Drupal\dungeoncrawler_content\Service\EncounterActorContextBuilder;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EncounterActorContextBuilder
 */
class EncounterActorContextBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildActorContext
   */
  public function testBuildActorContextBuildsContractHashFromSharedService(): void {
    $action_contract = [
      'phase' => 'encounter',
      'actions' => [
        ['id' => 'strike', 'cost' => 1],
        ['id' => 'talk', 'cost' => 1],
      ],
    ];
    $allowed_actions = [' Strike ', 'talk'];

    $availability = $this->createMock(ActorActionAvailabilityService::class);
    $availability->expects($this->once())
      ->method('resolveEncounterAvailability')
      ->willReturn([
        'available_actions' => $allowed_actions,
        'action_contract' => $action_contract,
        'availability_envelope' => ['available_actions' => ['strike'], 'actions_remaining' => 2],
      ]);

    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->expects($this->once())
      ->method('buildUnifiedActorContext')
      ->with(55, 'npc_profiled', $this->isType('array'))
      ->willReturn([
        'entity_ref' => 'npc_profiled',
        'decision_profile' => ['motivations' => 'Protect the relic'],
        'combat_psychology_context' => 'Fighting motivation: Protect the relic',
      ]);

    $builder = new EncounterActorContextBuilder($psychology, $availability, new ActorDecisionContractService());
    $context = $builder->buildActorContext('npc-1', [
      'campaign_id' => 55,
      'encounter_id' => 901,
      'round' => 2,
      'turn' => ['entity' => 'npc-1', 'actions_remaining' => 3],
      'initiative_order' => [
        [
          'entity_id' => 'npc-1',
          'entity_ref' => 'npc_profiled',
          'name' => 'Profiled NPC',
          'team' => 'enemy',
          'hp' => 16,
          'max_hp' => 18,
          'ac' => 18,
          'position_q' => 0,
          'position_r' => 0,
        ],
      ],
    ], [], []);

    $expected_hash = (new ActorDecisionContractService())->buildActionContractHash($action_contract, $allowed_actions);
    $this->assertSame($expected_hash, $context['action_contract_hash']);
    $this->assertSame('npc_profiled', $context['current_actor']['entity_ref']);
    $this->assertSame('Protect the relic', $context['current_actor_profile']['motivations']);
  }

  /**
   * @covers ::buildActorContext
   */
  public function testBuildActorContextAppliesTurnDefaultsBeforeAvailabilityResolution(): void {
    $availability = $this->createMock(ActorActionAvailabilityService::class);
    $availability->expects($this->once())
      ->method('resolveEncounterAvailability')
      ->with(
        $this->callback(function (array $state): bool {
          return ($state['turn']['entity'] ?? '') === 'npc-1'
            && (int) ($state['turn']['actions_remaining'] ?? 0) === 3
            && array_key_exists('reaction_available', $state['turn'])
            && $state['turn']['reaction_available'] === FALSE;
        }),
        $this->isType('array'),
        'npc-1'
      )
      ->willReturn([
        'available_actions' => ['end_turn'],
        'action_contract' => ['phase' => 'encounter', 'actions' => [['id' => 'end_turn', 'cost' => 0]]],
        'availability_envelope' => ['available_actions' => ['end_turn'], 'actions_remaining' => 0],
      ]);

    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('buildUnifiedActorContext')
      ->willReturn([
        'entity_ref' => 'npc_profiled',
        'decision_profile' => [],
        'combat_psychology_context' => '',
      ]);

    $builder = new EncounterActorContextBuilder($psychology, $availability, new ActorDecisionContractService());
    $context = $builder->buildActorContext('npc-1', [
      'campaign_id' => 55,
      'initiative_order' => [
        ['entity_id' => 'npc-1', 'entity_ref' => 'npc_profiled', 'team' => 'enemy'],
      ],
    ], [], []);

    $this->assertSame('npc-1', $context['entity_id']);
    $this->assertSame(['end_turn'], $context['allowed_actions']);
  }

  /**
   * @covers ::buildActorContext
   */
  public function testBuildActorContextNormalizesArrayEntityRefToContentId(): void {
    $availability = $this->createMock(ActorActionAvailabilityService::class);
    $availability->method('resolveEncounterAvailability')
      ->willReturn([
        'available_actions' => ['end_turn'],
        'action_contract' => ['phase' => 'encounter', 'actions' => [['id' => 'end_turn', 'cost' => 0]]],
        'availability_envelope' => ['available_actions' => ['end_turn'], 'actions_remaining' => 0],
      ]);

    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->expects($this->once())
      ->method('buildUnifiedActorContext')
      ->with(55, 'npc_profiled', $this->isType('array'))
      ->willReturn([
        'entity_ref' => 'npc_profiled',
        'decision_profile' => ['display_name' => 'Profiled NPC'],
        'combat_psychology_context' => '',
      ]);

    $builder = new EncounterActorContextBuilder($psychology, $availability, new ActorDecisionContractService());
    $builder->buildActorContext('npc-1', [
      'campaign_id' => 55,
      'initiative_order' => [
        ['entity_id' => 'npc-1', 'entity_ref' => ['content_id' => 'npc_profiled'], 'team' => 'enemy'],
      ],
    ], [], []);
  }

  /**
   * @covers ::buildActorContext
   */
  public function testBuildActorContextKeepsFallbackDecisionEnvelopeWhenProfileMissing(): void {
    $availability = $this->createMock(ActorActionAvailabilityService::class);
    $availability->method('resolveEncounterAvailability')
      ->willReturn([
        'available_actions' => ['end_turn'],
        'action_contract' => ['phase' => 'encounter', 'actions' => [['id' => 'end_turn', 'cost' => 0]]],
        'availability_envelope' => ['available_actions' => ['end_turn'], 'actions_remaining' => 0],
      ]);

    $psychology = $this->createMock(NpcPsychologyService::class);
    $psychology->method('buildUnifiedActorContext')
      ->willReturn([
        'entity_ref' => 'npc_profiled',
        'profile' => NULL,
        'decision_profile' => [
          'display_name' => 'Profiled NPC',
          'attitude' => 'indifferent',
          'goals' => ['Gain XP', 'Gain Treasure'],
        ],
        'combat_psychology_context' => '',
        'prompt_context' => 'You are Profiled NPC. No detailed background is available.',
      ]);

    $builder = new EncounterActorContextBuilder($psychology, $availability, new ActorDecisionContractService());
    $context = $builder->buildActorContext('npc-1', [
      'campaign_id' => 55,
      'initiative_order' => [
        ['entity_id' => 'npc-1', 'entity_ref' => '{"content_id":"npc_profiled"}', 'team' => 'enemy'],
      ],
    ], [], []);

    $this->assertSame('indifferent', $context['current_actor_profile']['attitude']);
    $this->assertSame(['Gain XP', 'Gain Treasure'], $context['current_actor_profile']['goals']);
    $this->assertSame('', $context['npc_psychology']);
  }

}
