<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\CanonicalSiteTopologyContract;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

/**
 * Returns the schema that is shared by the UI and GM authoring paths.
 */
final class DescribeStorylineAuthoringContractTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'describe_storyline_authoring_contract',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Return the complete storyline draft, operation, branching, and canonical site-topology contracts.',
      FALSE,
      'config/schemas/storyline_authoring_draft.schema.json',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $path = dirname(__DIR__, 5) . '/config/schemas/storyline_authoring_draft.schema.json';
    $schema = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    return [
      'schema' => $schema,
      'operations' => StorylineAuthoringDraftService::OPERATION_CONTRACT,
      'operation_contract_description' => StorylineAuthoringDraftService::operationContractDescription(),
      'collection_contracts' => StorylineAuthoringDraftService::collectionAuthoringContract(),
      'branching_contract' => StorylineAuthoringDraftService::branchAuthoringContract(),
      'site_topology_contract' => CanonicalSiteTopologyContract::describe(),
      'path_format' => 'RFC 6901 JSON pointer rooted at the draft payload.',
      'immutable_paths' => ['/canonical_template'],
      'collection_identity_fields' => [
        '/process_flow/journal_entries' => 'scene_id',
        '/process_flow/tasks' => 'task_id',
        '/process_flow/branch_choices' => 'choice_id',
        '/process_flow/objective_completion_criteria' => 'objective_id',
        '/process_flow/conflicts' => 'conflict_id',
        '/objects/actors/generation_specs' => 'contact_id',
        '/objects/dungeons/generation_specs' => 'dungeon_id',
        '/objects/rooms/generation_specs' => [
          'room_id',
          'chapter_id',
          'scene_id',
          'narrative',
        ],
        '/objects/items/generation_specs' => 'item_id',
      ],
    ];
  }

}
