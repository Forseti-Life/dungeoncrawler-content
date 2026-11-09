<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

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
      'authoring_workflow' => [
        'updates_apply_immediately' => TRUE,
        'revision_conflicts_are_rejected' => TRUE,
        'vague_creation_option_count' => 3,
        'draft_local_ids_may_be_derived' => TRUE,
        'optional_bindings_never_block_requested_work' => TRUE,
        'mutation_requests_must_use_plan_apply' => TRUE,
        'collection_contracts' => StorylineAuthoringDraftService::collectionAuthoringContract(),
        'branching_contract' => StorylineAuthoringDraftService::branchAuthoringContract(),
        'site_topology_contract' => CanonicalSiteTopologyContract::describe(),
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
