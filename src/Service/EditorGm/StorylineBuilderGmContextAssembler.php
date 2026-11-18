<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineRemediationTool;

/**
 * Projects storyline draft context for GM assistant turns.
 */
final class StorylineBuilderGmContextAssembler implements EditorGmContextAssemblerInterface {

  public const AUTHORITY_BOUNDARY = [
    'draft_source_of_truth' => 'dungeoncrawler_content_storyline_authoring_drafts',
    'publish_authority' => 'StorylineManagerService::saveTemplate() through StorylineAuthoringDraftService',
    'campaign_runtime_mutation' => 'forbidden',
  ];

  public function __construct(
    private readonly EditorGmToolRegistry $registry,
    private readonly EditorGmIntentParser $intentParser,
    private readonly PlanStorylineRemediationTool $remediationPlanner,
    private readonly StorylineGmWorkService $work,
  ) {}

  public function assemble(EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $draft = $context->draft();
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $validation = $context->validation();

    return [
      'tool_id' => StorylineBuilderGmSurface::ID,
      'draft' => [
        'draft_id' => (string) ($draft['draft_id'] ?? ''),
        'revision' => (int) ($draft['revision'] ?? 0),
        'status' => (string) ($draft['status'] ?? ''),
        'updated_at' => (int) ($draft['updated_at'] ?? 0),
      ],
      'storyline' => $payload,
      'current_state' => $context->status(),
      'actor_sheet_workflow' => [
        'inspect_tool' => 'inspect_storyline_actor_sheets',
        'build_tool' => 'build_storyline_actor_sheets',
        'source_of_truth' => 'Native library actor rows through CharacterCreationGmService and CharacterManager',
        'existing_character_context_required' => FALSE,
        'whole_roster_is_supported' => TRUE,
        'role_driven_native_choices_are_supported' => TRUE,
        'retries_reuse_linked_sheets' => TRUE,
        'campaign_runtime_mutation' => 'forbidden',
        'instructions' => 'For an explicit request or selected option to build roster character sheets, execute build_storyline_actor_sheets immediately. Omit actor_ids for the whole roster. Do not ask for classes, draft IDs or confirmation; the tool derives native choices, creates missing drafts and links each actor atomically.',
      ],
      'authoring_workflow' => [
        'bounded_work' => [
          'plan_tool' => 'plan_storyline_work',
          'apply_tool' => 'apply_storyline_work',
          'max_operations_per_batch' => StorylineGmWorkService::MAX_OPERATIONS,
          'max_patch_bytes' => StorylineGmWorkService::MAX_PATCH_BYTES,
          // Work the draft has moved past is closed first; what remains is current.
          'closed_work' => $this->work->closeSupersededWork($context->draftId),
          'active_work' => $this->work->activeWork($context->draftId),
          'instructions' => 'Large or multi-scene edits start with short work-unit instructions, not a complete operation payload. The client automatically continues checkpointed batches. Resume active_work before planning the same edits again. If its revision conflicts, explicitly stop the saved work before planning against the newer draft; completed edits remain saved. Plan work units only for findings whose route is draft_operation; the route on a finding overrides the route on its stage. dungeon_editor findings need a room or site built: use route_to_editor to send the author to the Dungeon Editor on that site, never work units, remediation or new draft rows. Never ask the author to divide the technical workload.',
        ],
        'next_step_contract' => [
          'maximum_items' => 1,
          'one_creative_decision_or_bounded_action' => TRUE,
          'technical_batching_is_agent_owned' => TRUE,
          'completed_work_must_not_be_reproposed' => TRUE,
        ],
        'updates_apply_immediately' => TRUE,
        'revision_conflicts_are_rejected' => TRUE,
        'vague_creation_option_count' => 3,
        'draft_local_ids_may_be_derived' => TRUE,
        'optional_bindings_never_block_requested_work' => TRUE,
        'mutation_requests_must_use_plan_apply' => TRUE,
        'collection_contracts' => StorylineAuthoringDraftService::collectionAuthoringContract(),
        'identity_contract' => StorylineAuthoringDraftService::identityAuthoringContract(),
        'branching_contract' => StorylineAuthoringDraftService::branchAuthoringContract(),
        'site_topology_contract' => CanonicalSiteTopologyContract::describe(),
        'room_site_binding_plan' => $context->drafts->planRoomDungeonBindings($context->draftId),
        'linked_quest_reference_repairs' => $this->remediationPlanner->planLinkedQuestReferenceRepairs($context),
        'finalization' => [
          'review_tool' => 'check_storyline_creation_readiness',
          'signoff_tool' => 'publish_storyline_draft',
          'review_is_current_canonical_validation' => TRUE,
          'manual_review_flag_required' => FALSE,
          'final_signoff_is_publication_receipt' => TRUE,
          'publication_receipt' => $draft['publication_receipt'] ?? NULL,
          'instructions' => 'Review runs canonical validation. There is no separate review-completed checkbox or signoff document to invent. An explicit "Review and create" or final-signoff command publishes the current revision through publish_storyline_draft and stores its native publication receipt. Never substitute another review or change story content for that command.',
        ],
      ],
      'validation_summary' => [
        'valid' => (bool) ($validation['valid'] ?? FALSE),
        'error_count' => count((array) ($validation['errors'] ?? [])),
        'warning_count' => count((array) ($validation['warnings'] ?? [])),
        'errors' => array_values((array) ($validation['errors'] ?? [])),
        'warnings' => array_values((array) ($validation['warnings'] ?? [])),
        'stages' => array_values(array_filter((array) ($validation['stages'] ?? []), 'is_array')),
      ],
      'narrative_analysis' => is_array($validation['analysis'] ?? NULL) ? $validation['analysis'] : [],
      'authority_boundary' => self::AUTHORITY_BOUNDARY,
      'assistant' => [
        'natural_language_available' => $this->intentParser->isAvailable(),
        'natural_language_may_mutate' => TRUE,
        'must_complete_requested_work' => TRUE,
        'must_return_next_steps' => TRUE,
        'vague_object_creation_requires_options' => 3,
        'detailed_object_requests_execute_immediately' => TRUE,
      ],
      'tools' => $this->registry->manifest(),
    ];
  }

}
