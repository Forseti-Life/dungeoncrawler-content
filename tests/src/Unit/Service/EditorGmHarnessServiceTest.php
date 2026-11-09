<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService
 */
class EditorGmHarnessServiceTest extends UnitTestCase {

  /**
   * @covers ::authorizesStorylineRemediation
   *
   * @dataProvider remediationAuthorizationProvider
   */
  public function testAuthorizesStorylineRemediation(string $utterance, bool $expected): void {
    $service = (new \ReflectionClass(EditorGmHarnessService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'authorizesStorylineRemediation');
    $method->setAccessible(TRUE);

    $this->assertSame($expected, $method->invoke($service, $utterance));
  }

  /**
   * Approval phrasing that should or should not execute remediation.
   *
   * @return array<string, array{0:string,1:bool}>
   */
  public static function remediationAuthorizationProvider(): array {
    return [
      'explicit execute' => ['execute it', TRUE],
      'numbered do it' => ['1. do it.', TRUE],
      'run it now' => ['run it now', TRUE],
      'fix warnings phrase' => ['fix the warnings', TRUE],
      'requesting execution with conditional review' => [
        "I'm requesting execution. if you can't do it this time and this still isn't remediated, lets review what tool you think you are missing.",
        TRUE,
      ],
      'try again to get canonical item published' => [
        'Lets try again to get that canonical item published',
        TRUE,
      ],
      'persist staged journal definition' => [
        'Persist the staged field journal definition at revision 4 with its existing bindings and no unrelated writes, then revalidate',
        TRUE,
      ],
      'save to library confirmation' => [
        'Definately want it saved into the library. Save it to the library.',
        TRUE,
      ],
      'create canonical items request' => [
        'create canonical items for the storyline and save them to the canonical library',
        TRUE,
      ],
      'negated command' => ['do not execute it', FALSE],
      'review request only' => ['lets review what tool is missing', FALSE],
    ];
  }

  /**
   * Verifies PlanStorylineRemediationTool derives missing items from operations and explicit specs.
   */
  public function testPlanStorylineRemediationDerivesCanonicalItems(): void {
    $drafts = $this->createMock(\Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService::class);
    $definitions = $this->createMock(\Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService::class);

    $base_draft = [
      'draft_id' => 'draft-1',
      'revision' => 5,
      'status' => 'editing',
      'payload' => [
        'premise' => 'A relic is stolen from the Grand Lodge.',
        'player_hook' => 'Recover the relic before the ritual.',
        'objects' => [
          'items' => [
            'generation_specs' => [],
          ],
        ],
      ],
    ];
    $planned_payload = $base_draft['payload'];
    $planned_payload['objects']['items']['generation_specs'][] = [
      'item_id' => 'wayfinder_resonance_key',
      'purpose' => 'Unlocks the sealed vault beneath the lodge.',
      'acquisition' => 'Recovered from the courier.',
      'reward' => 'Grants access to the vault.',
    ];

    $drafts->method('getDraft')->willReturn($base_draft);
    $drafts->method('validateDraft')->willReturn([
      'valid' => TRUE,
      'errors' => [],
      'warnings' => [],
    ]);
    $drafts->expects($this->once())
      ->method('planDraftOperations')
      ->willReturn([
        'has_changes' => TRUE,
        'payload' => $planned_payload,
      ]);

    $definitions->method('catalogEntry')
      ->willReturnCallback(static fn(string $family, string $id): ?array => NULL);

    $context = new \Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext(
      'draft-1',
      'editing',
      $drafts,
      $definitions,
    );

    $tool = new \Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineRemediationTool();
    $result = $tool->execute([
      'expected_revision' => 5,
      'operations' => [
        [
          'op' => 'append',
          'path' => '/objects/items/generation_specs',
          'value' => $planned_payload['objects']['items']['generation_specs'][0],
        ],
      ],
      'definitions' => [
        [
          'family' => 'item',
          'definition_id' => 'ritual_ledger',
          'prompt' => 'Encrypted ledger detailing the smuggling routes.',
        ],
      ],
    ], $context);

    $this->assertTrue($result['has_changes']);
    $this->assertSame(2, $result['definition_count']);
    $this->assertSame('apply_storyline_remediation', $result['proposed_execution']['tool_name']);
    $planned_defs = $result['proposed_execution']['arguments']['definitions'];
    $this->assertSame('item', $planned_defs[0]['family']);
    $this->assertSame('wayfinder_resonance_key', $planned_defs[0]['definition_id']);
    $this->assertSame('item', $planned_defs[1]['family']);
    $this->assertSame('ritual_ledger', $planned_defs[1]['definition_id']);
  }

  /**
   * Verifies describeToolCall emits human-readable tool call messages across surfaces.
   */
  public function testDescribeToolCallEmitsReadableActionDescriptions(): void {
    $service = (new \ReflectionClass(EditorGmHarnessService::class))
      ->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'describeToolCallMessage');
    $method->setAccessible(TRUE);

    $this->assertSame(
      'Now looking up canonical catalog (item)…',
      $method->invoke($service, 'list_canonical_catalog', ['family' => 'item']),
    );
    $this->assertSame(
      "Now generating canonical item definition 'ritual_ledger'…",
      $method->invoke($service, 'generate_canonical_definition', ['family' => 'item', 'definition_id' => 'ritual_ledger']),
    );
    $this->assertSame(
      'Now validating storyline draft…',
      $method->invoke($service, 'validate_storyline_draft', []),
    );
    $this->assertSame(
      'Now publishing storyline draft…',
      $method->invoke($service, 'publish_storyline_draft', []),
    );
  }

  /**
   * Verifies AgentHarnessManagementService execution profiles, compaction, and task queues.
   */
  public function testAgentHarnessManagementProfilesCompactionAndOrdering(): void {
    $manager = new \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService();

    // 1. Execution profiles bound or disable thinking appropriately.
    $routing = $manager->resolveExecutionOptions(\Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_INTENT_ROUTING);
    $this->assertSame('disabled', $routing['thinking']);
    $this->assertTrue($routing['json_object']);

    $creative = $manager->resolveExecutionOptions(\Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_CREATIVE_REASONING);
    $this->assertSame('enabled', $creative['thinking']);
    $this->assertSame(1024, $creative['thinking_budget_tokens']);

    // 2. Context compaction strips duplicate canonical_template and heavy narrative_analysis graphs.
    $bloated = [
      'draft' => ['draft_id' => 'draft-1', 'revision' => 4],
      'storyline' => [
        'title' => 'Test',
        'canonical_template' => ['huge_duplicate' => str_repeat('x', 5000)],
      ],
      'narrative_analysis' => [
        'metrics' => ['scene_count' => 3],
        'timeline_svg' => str_repeat('svg', 1000),
      ],
      'validation_summary' => ['valid' => TRUE, 'error_count' => 0, 'warning_count' => 0],
    ];
    $compacted = $manager->compactContextForIntent($bloated, 'Storyline Builder');
    $this->assertArrayNotHasKey('canonical_template', $compacted['storyline']);
    $this->assertArrayNotHasKey('narrative_analysis', $compacted);
    $this->assertSame(['scene_count' => 3], $compacted['narrative_metrics']);

    // 3. Storyline draft operations are ordered by dependency tier (chapters/scenes before conflicts/branches).
    $unordered_ops = [
      ['op' => 'append', 'path' => '/process_flow/branch_choices', 'value' => ['branch_id' => 'b1']],
      ['op' => 'replace', 'path' => '/process_flow/conflicts/0/resolution_scene_id', 'value' => 'scene-2'],
      ['op' => 'append', 'path' => '/process_flow/chapters/0/scenes', 'value' => ['scene_id' => 'scene-2']],
    ];
    $ordered_ops = $manager->orderStorylineDraftOperations($unordered_ops);
    $this->assertSame('/process_flow/chapters/0/scenes', $ordered_ops[0]['path']);
    $this->assertSame('/process_flow/conflicts/0/resolution_scene_id', $ordered_ops[1]['path']);
    $this->assertSame('/process_flow/branch_choices', $ordered_ops[2]['path']);

    // 4. Multi-step task queue orders draft patches before canonical generation and publication.
    $unordered_tasks = [
      ['tool_name' => 'publish_storyline_draft', 'arguments' => ['expected_revision' => 4]],
      ['tool_name' => 'generate_canonical_definition', 'arguments' => ['family' => 'item', 'definition_id' => 'ledger']],
      ['tool_name' => 'apply_storyline_draft_operations', 'arguments' => ['expected_revision' => 4, 'operations' => $unordered_ops]],
    ];
    $ordered_tasks = $manager->orderTaskQueue($unordered_tasks);
    $this->assertSame('apply_storyline_draft_operations', $ordered_tasks[0]['tool_name']);
    $this->assertSame('generate_canonical_definition', $ordered_tasks[1]['tool_name']);
    $this->assertSame('publish_storyline_draft', $ordered_tasks[2]['tool_name']);

    // 5. Deterministic mutation synthesis produces an immediate summary without a second LLM call.
    $summary = $manager->synthesizeDeterministicMutationResponse(
      'apply_storyline_draft_operations',
      [
        'result_revision' => 5,
        'reasoning' => 'Added scene-2 and resolved conflict ordering.',
        'changes' => [
          ['type' => 'added', 'path' => '/process_flow/chapters/0/scenes/1', 'after' => ['scene_id' => 'scene-2', 'title' => 'Aftermath']],
        ],
      ],
      [
        'draft' => ['revision' => 5],
        'validation_summary' => [
          'valid' => TRUE,
          'error_count' => 0,
          'warning_count' => 0,
        ],
      ],
      'Storyline Builder',
    );
    $this->assertNotNull($summary);
    $this->assertStringContainsString('Applied Draft Updates', $summary['answer']);
    $this->assertStringContainsString("Updated process_flow → chapters → 0 → scenes → 1 'Aftermath'", $summary['answer']);
    $this->assertStringContainsString('Revision 5 is at 0% completion with 0 validation error(s) and 0 warning(s).', $summary['answer']);

    // 6. Operation-to-profile mapping covers runtime, psychology, validation, and generation operations.
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_CLASSIFICATION_GATE,
      $manager->resolveProfileForOperation('npc_interjection_eval_single'),
    );
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_NPC_PSYCHOLOGY,
      $manager->resolveProfileForOperation('npc_inner_monologue'),
    );
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_SCHEMA_VALIDATION,
      $manager->resolveProfileForOperation('room_description_metadata_alignment'),
    );
    $this->assertSame(
      \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService::PROFILE_NARRATION_BEAT,
      $manager->resolveProfileForOperation('ai_gm_room_entry'),
    );

    // 7. Unified describeToolCall formats runtime GM and Character Creation GM tools alongside Editor GM tools.
    $this->assertSame(
      'Now updating room state…',
      $manager->describeToolCallMessage('modify_room_state'),
    );
    $this->assertSame(
      'Now looking up campaign database records…',
      $manager->describeToolCallMessage('query_campaign_database'),
    );
    $this->assertSame(
      'Now resolving storyline branch decision…',
      $manager->describeToolCallMessage('resolve_storyline_branch'),
    );
  }

  /**
   * Verifies AgentHarnessRegistry registers and describes AgentHarnessInterface implementations.
   */
  public function testAgentHarnessRegistryDescribesRegisteredHarnesses(): void {
    $runtime = $this->createMock(\Drupal\dungeoncrawler_content\Service\GmActorRuntimeService::class);
    $tool_exec = $this->createMock(\Drupal\dungeoncrawler_content\Service\GmToolExecutionService::class);
    $tool_exec->method('listTools')->willReturn(['modify_room_state' => 'Modify room state.']);

    $gm_actor_harness = new \Drupal\dungeoncrawler_content\Service\GmActorHarnessService($runtime, $tool_exec);
    $registry = new \Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessRegistry([$gm_actor_harness]);

    $this->assertSame(['runtime_gm_actor'], $registry->agentIds());
    $this->assertSame($gm_actor_harness, $registry->get('runtime_gm_actor'));

    $all = $registry->describeAll();
    $this->assertSame('Runtime GM Actor Harness', $all['runtime_gm_actor']['label']);
    $this->assertSame('campaign_room', $all['runtime_gm_actor']['scope']);
    $this->assertSame('runtime_dialogue', $all['runtime_gm_actor']['default_execution_profile']);
  }

}


