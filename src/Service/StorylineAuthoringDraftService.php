<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Exception\StorylineDraftValidationException;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;

/**
 * Lightweight draft authority for GM storyline builder authoring.
 */
class StorylineAuthoringDraftService {

  /**
   * Only malformed contracts block Storyline publication.
   */
  private const BLOCKING_VALIDATION_STAGES = [
    'schema',
    'questline_progression',
    'objective_control_chain',
    'task_contracts',
    'authored_room_dungeon_bindings',
    'actor_character_sheets',
  ];
  public const ACTOR_SHEET_LINK_FIELDS = ['character_id', 'character_sheet_revision', 'character_sheet_spec_hash'];
  private const STAGE_FIX_ROUTES = [
    'schema' => 'draft_operation',
    'questline_progression' => 'draft_operation',
    'objective_control_chain' => 'draft_operation',
    'task_contracts' => 'draft_operation',
    'authored_room_dungeon_bindings' => 'dungeon_editor',
    'authored_questline_edges' => 'draft_operation',
    'actor_character_sheets' => 'actor_sheet_build',
  ];
  /**
   * Findings whose fix differs from the rest of their stage.
   */
  private const FINDING_FIX_ROUTES = [
    'actor_duplicates_library_actor' => 'draft_operation',
    'actor_placement_missing' => 'draft_operation',
    'actor_placement_unknown' => 'draft_operation',
    'room_scene_unknown' => 'draft_operation',
    'institution_leader_unknown' => 'draft_operation',
    'institution_leader_not_member' => 'draft_operation',
    'institution_seat_unknown' => 'draft_operation',
  ];
  /**
   * Library roles whose characters hand out work to the party.
   */
  public const QUEST_GIVER_ROLES = ['landmark_contact', 'contact', 'quest_giver', 'mentor_guide', 'merchant'];

  private const TABLE = 'dungeoncrawler_content_storyline_authoring_drafts';
  private const REVISION_TABLE = 'dungeoncrawler_content_storyline_draft_revisions';
  private const REDIRECT_TABLE = 'dungeoncrawler_content_storyline_draft_redirects';
  private const OPERATION_TABLE = 'dungeoncrawler_content_storyline_draft_operations';
  public const OPERATION_CONTRACT = [
    'set' => ['required' => ['type', 'path', 'value']],
    'append' => ['required' => ['type', 'path', 'value'], 'optional' => ['key']],
    'replace' => ['required' => ['type', 'path', 'match', 'value'], 'match' => ['field + value or fields']],
    'patch' => ['required' => ['type', 'path', 'match', 'value'], 'match' => ['field + value or fields']],
    'remove' => ['required' => ['type', 'path', 'match'], 'match' => ['field + value or fields']],
    'move' => ['required' => ['type', 'path', 'match', 'to_index'], 'match' => ['field + value or fields']],
  ];
  private const GENERATED_ID_FIELDS = [
    '/process_flow/journal_entries' => ['scene_id' => 'scene'],
    '/process_flow/tasks' => ['task_id' => 'task'],
    '/process_flow/branch_choices' => ['choice_id' => 'choice', 'decision_id' => 'decision'],
    '/process_flow/objective_completion_criteria' => ['objective_id' => 'objective'],
    '/process_flow/conflicts' => ['conflict_id' => 'conflict'],
    '/objects/actors/generation_specs' => ['actor_id' => 'actor', 'contact_id' => 'contact'],
    '/objects/dungeons/generation_specs' => ['dungeon_id' => 'dungeon'],
    '/objects/rooms/generation_specs' => ['room_id' => 'room'],
    '/objects/items/generation_specs' => ['item_id' => 'item'],
    '/objects/institutions' => ['institution_id' => 'institution'],
  ];
  private const NARRATIVE_BEATS = [
    'inciting_incident' => ['label' => 'Inciting incident', 'fields' => ['disruption', 'stakes']],
    'threshold_of_no_return' => ['label' => 'Threshold of no return', 'fields' => ['crossing', 'point_of_no_return']],
    'progressive_complications' => ['label' => 'Progressive complications', 'fields' => ['road_of_trials', 'midpoint_shift']],
    'crisis' => ['label' => 'All hope is lost crisis', 'fields' => ['dark_night', 'psychological_pivot']],
    'climax' => ['label' => 'Climax', 'fields' => ['central_confrontation', 'premise_resolution']],
    'denouement' => ['label' => 'Denouement', 'fields' => ['new_equilibrium', 'internal_shift']],
  ];
  private const ADVENTURE_REQUIREMENTS = [
    'physical_quest' => 'Defined physical quest',
    'antagonistic_force' => 'Antagonistic force',
    'mentor_guide' => 'Mentor or guide',
    'boon_macguffin' => 'Boon or MacGuffin',
  ];

  public function __construct(
    protected Connection $database,
    protected StorylineManagerService $storylineManager,
    protected AccountProxyInterface $currentUser,
    protected UuidInterface $uuid,
    protected TimeInterface $time,
    protected ?DefinitionSchemaValidator $draftValidator = NULL,
    protected ?CanonicalQuestTemplateService $questTemplates = NULL,
    protected ?ObjectiveTypeService $objectiveTypes = NULL,
    protected ?CanonicalDefinitionService $definitions = NULL,
  ) {}

  /**
   * Library characters whose definition is retired: not offered, not bound.
   *
   * @return array<string, true>
   */
  private function retiredLibraryCharacters(): array {
    if ($this->definitions === NULL) {
      throw new \LogicException('storyline_library_definitions_unavailable');
    }
    return $this->definitions->retiredDefinitionIds('actor');
  }

  /**
   * Describe the exact operation objects accepted by the draft authority.
   */
  public static function operationContractDescription(): string {
    return 'Each item must use one exact shape: '
      . '{"type":"set","path":"/json/pointer","value":...}; '
      . '{"type":"append","path":"/collection","value":...}; '
      . '{"type":"replace","path":"/collection","match":{"field":"id_field","value":"id"},"value":...}; '
      . '{"type":"patch","path":"/collection","match":{"field":"id_field","value":"id"},"value":{"changed_field":"new value"}}; '
      . '{"type":"remove","path":"/collection","match":{"fields":{"id_field":"id","second_field":"value"}}}; '
      . '{"type":"move","path":"/collection","match":{"field":"id_field","value":"id"},"to_index":0}. '
      . 'The key is type, never operation or op. Paths are RFC 6901 JSON pointers. '
      . 'Use only paths present in the grounded storyline payload. A set path points to a property whose parent already exists. '
      . 'Append paths may point to a schema-declared collection omitted from the payload because it has no rows yet. '
      . 'New append rows may omit their generated identity fields: the authority allocates them before validation. '
      . 'An append may include an internal key, for example {"type":"append","path":"/objects/actors/generation_specs","key":"guide","value":{"role":"guide","display_name":"Ena"}}. '
      . 'Link new rows in the same batch with {"$draft_ref":"guide.actor_id"} as a field value; forward references are supported. Keys and references are internal, never author decisions. '
      . 'Replace, patch, remove, and move paths point to an existing collection. '
      . 'Patch merges a nonempty object of changed fields into the matched row, preserving every other field. Prefer patch for renaming or editing a few row fields; never resend whole rows for those edits. '
      . 'A match must identify exactly one row; when one field is not unique, use match.fields with enough exact field/value pairs to make it unique. '
      . 'Collection operation paths never add /- or an array index; set may address an existing row field by its grounded index.';
  }

  /**
   * Warn when an authored unlocks_after is not what the engine derives.
   *
   * @return array<int, string>
   *   Non-blocking warnings.
   */
  private function validateAuthoredQuestlineEdgeProjection(array $payload, array $definition): array {
    $warnings = [];
    $projected_nodes = [];
    foreach (array_values(array_filter((array) ($definition['questline']['quest_nodes'] ?? []), 'is_array')) as $node) {
      $quest_id = trim((string) ($node['quest_id'] ?? ''));
      if ($quest_id !== '') {
        $projected_nodes[$quest_id] = $node;
      }
    }
    foreach (array_values(array_filter((array) ($payload['process_flow']['tasks'] ?? []), 'is_array')) as $task) {
      $task_id = trim((string) ($task['task_id'] ?? ''));
      if ($task_id === '' || !isset($projected_nodes[$task_id])) {
        continue;
      }
      $authored = $this->normalizeCsvReferences($task['unlocks_after'] ?? []);
      $projected = $this->normalizeCsvReferences($projected_nodes[$task_id]['unlocks_after'] ?? []);
      sort($authored);
      sort($projected);
      if ($authored !== [] && $authored !== $projected) {
        $warnings[] = "Task '{$task_id}' unlocks_after is not used; prerequisites are derived from the tasks and choices that lead to it. Clear it, or point an upstream task or choice at '{$task_id}'.";
      }
    }

    return array_values(array_unique($warnings));
  }

  /**
   * Findings about choices that the questline projection cannot express.
   *
   * These are causes: a choice that cannot be placed, and a continuation a
   * task's choices replaced. Both leave later tasks unreachable, so they are
   * reported ahead of the unreachable tasks they cause.
   *
   * @return array<int, array<string, mixed>>
   */
  private function authoredChoiceFindings(array $payload): array {
    $tasks = array_values(array_filter((array) ($payload['process_flow']['tasks'] ?? []), 'is_array'));
    $task_ids = array_fill_keys(array_map(static fn(array $task): string => trim((string) ($task['task_id'] ?? '')), $tasks), TRUE);
    $findings = [];
    $choice_targets = [];
    foreach (array_values(array_filter((array) ($payload['process_flow']['branch_choices'] ?? []), 'is_array')) as $choice) {
      $source = trim((string) ($choice['source_task_id'] ?? ''));
      if ($source === '') {
        continue;
      }
      if (!isset($task_ids[$source])) {
        $label = trim((string) ($choice['label'] ?? ''));
        $findings[] = [
          'severity' => 'error',
          'code' => 'choice_source_task_unknown',
          'choice_id' => trim((string) ($choice['choice_id'] ?? '')),
          'label' => $label,
          'message' => "The choice '{$label}' names a source task that does not exist ('{$source}'). Point it at an existing task, or leave its source empty to make it a storyline-wide choice.",
        ];
        continue;
      }
      $choice_targets[$source] ??= [];
      if (!$this->normalizeBranchTerminal($choice['terminal'] ?? FALSE)) {
        $choice_targets[$source][trim((string) ($choice['target_task_id'] ?? ''))] = TRUE;
      }
    }
    foreach ($tasks as $task) {
      $task_id = trim((string) ($task['task_id'] ?? ''));
      if (!isset($choice_targets[$task_id])) {
        continue;
      }
      $dropped = array_values(array_filter(
        $this->normalizeCsvReferences($task['unlocks_to'] ?? []),
        static fn(string $continuation): bool => !isset($choice_targets[$task_id][$continuation]),
      ));
      if ($dropped === []) {
        continue;
      }
      $findings[] = [
        'severity' => 'error',
        'code' => 'continuation_replaced_by_choices',
        'task_id' => $task_id,
        'dropped_task_ids' => $dropped,
        'message' => sprintf(
          "Task '%s' lists '%s' as what comes next, but a task with choices continues only where its choices lead, and none leads there. Add a choice that continues there, or make the conditional choice storyline-wide so the task keeps its normal continuation.",
          $task_id,
          implode("', '", $dropped),
        ),
      ];
    }
    return $findings;
  }

  /**
   * Shared identity workflow for planning, grounding, and response synthesis.
   */
  public static function identityAuthoringContract(): array {
    return [
      'owner' => 'StorylineAuthoringDraftService::planDraftOperations',
      'user_supplies_internal_ids' => FALSE,
      'generated_fields_by_collection' => self::GENERATED_ID_FIELDS,
      'storyline_id' => 'Allocated once from the native draft UUID; changing the title never changes this identity.',
      'process' => [
        'Read the current draft and revision; distinguish inspection from an authorized edit.',
        'Reuse grounded IDs for existing objects; look up named canonical objects before rebinding.',
        'To add a character the author names, use bind_library_characters; it looks the character up and saves the cast row. Before inventing a character, search the character library with find_library_characters. When a library character fits, bind it by its actor_id instead of inventing one, and offer the matches to the author when the choice is theirs. A quest giver or hook is a library character whenever one fits.',
        'A character new to the story is placed: its location_id names a room or site of this storyline.',
        'Every cast member is tied to the cast when it is added or bound, in the same operations: an affiliation to each institution of this storyline it is tied to, by institution_id, with standing leader, exile or ally where it is not a plain member (define the institution in /objects/institutions first when the story has none that fits: clan, family or house, settlement, faction, guard, culture), and one /objects/actors/relationships row for each cast member it has a personal tie to, with the attitude and a bond such as spouse, parent, child, sibling, rival, mentor or serves. Ancestry and class are not affiliations; they come from the sheet. Infer the obvious ties from the roles and the story; do not ask the author for them.',
        'For new append rows, omit generated identity fields and let the authority allocate collision-free, draft-scoped IDs.',
        'Give related new rows internal batch keys and link them with $draft_ref values; never ask the author to supply keys or IDs.',
        'Keep existing IDs when renaming or editing content; only rebind references when the author requests that change.',
        'Use the grounded room-site binding plan; never infer physical sites from narrative chapter order.',
        'Validate the fully resolved batch and save through revision-checked, idempotent native operations.',
        'Ask only genuine creative questions using named people, places, or story choices. Reject technical ID-assignment prompts.',
      ],
      'reference_format' => ['$draft_ref' => 'batch-key.identity_field'],
      'allocation_is_not_canonical_publication' => TRUE,
      'placement_and_instance_ids_remain_owned_by_their_native_editors' => TRUE,
      'invalid_references_fail_explicitly' => TRUE,
    ];
  }

  /**
   * Rejects requests that improperly hand identifier mechanics to the author.
   */
  public static function assertNoIdAssignmentRequest(string $text): void {
    $verbs = '(?:assign|provide|supply|choose|select|pick|enter|specify|invent|create|generate|give|set|confirm)';
    $ids = '(?:ids?|uuids?|identifiers?|slugs?|(?:[a-z][a-z0-9]*_)+ids?|(?:batch|internal|technical)\\s+keys?)';
    $request = '(?:^|[.!?:\\n]\\s*)(?:\\d+[.)]\\s*)?(?:please\\s+)?' . $verbs
      . '|\\b(?:can|could|would|will)\\s+you\\s+' . $verbs
      . '|\\byou\\s+(?:must|need\\s+to|should)\\s+' . $verbs;
    if (preg_match('/(?:' . $request . ')\\b[^.!?\\n]{0,100}\\b' . $ids . '\\b/iu', trim($text))
      || preg_match('/\\b(?:which|what)\\s+(?:[\\w-]+\\s+){0,3}' . $ids . '\\b[^.!?\\n]{0,80}\\b(?:use|assign|want|prefer|choose)\\b/iu', $text)
      || preg_match('/\\bwhat\\s+should\\s+(?:[\\w-]+\\s+){0,4}' . $ids . '\\s+be\\b/iu', $text)) {
      throw new \DomainException('storyline_gm_user_id_assignment_forbidden');
    }
  }

  /**
   * Project exact collection fields from the authoring schema for GM grounding.
   */
  public static function collectionAuthoringContract(): array {
    $schema = self::loadAuthoringSchema();
    $definitions = is_array($schema['$defs'] ?? NULL) ? $schema['$defs'] : [];
    $collections = [
      '/process_flow/journal_entries' => 'journal_entry',
      '/process_flow/tasks' => 'task',
      '/process_flow/branch_choices' => 'branch_choice',
      '/process_flow/objective_completion_criteria' => 'objective',
      '/process_flow/conflicts' => 'conflict',
      '/objects/actors/generation_specs' => 'actor',
      '/objects/actors/relationships' => 'actor_relationship',
      '/objects/dungeons/generation_specs' => 'dungeon',
      '/objects/rooms/generation_specs' => 'room',
      '/objects/items/generation_specs' => 'item',
      '/objects/institutions' => 'institution',
    ];
    $contract = [];
    foreach ($collections as $path => $definition_name) {
      $definition = is_array($definitions[$definition_name] ?? NULL) ? $definitions[$definition_name] : [];
      if ($definition === [] || !is_array($definition['properties'] ?? NULL)) {
        throw new \RuntimeException('storyline_authoring_collection_schema_missing:' . $definition_name);
      }
      $contract[$path] = [
        'required_fields' => array_values(array_filter((array) ($definition['required'] ?? []), 'is_string')),
        'allowed_fields' => array_keys($definition['properties']),
        'server_generated_fields' => array_keys(self::GENERATED_ID_FIELDS[$path] ?? []),
        'field_constraints' => self::projectFieldConstraints($definition['properties']),
      ];
    }
    return $contract;
  }

  /**
   * Project model-relevant scalar constraints without duplicating the schema.
   */
  private static function projectFieldConstraints(array $properties): array {
    $constraints = [];
    foreach ($properties as $field => $property) {
      if (!is_array($property)) {
        continue;
      }
      $constraint = [];
      if (is_string($property['type'] ?? NULL)) {
        $constraint['type'] = $property['type'];
      }
      if (is_array($property['enum'] ?? NULL)) {
        $constraint['enum'] = array_values($property['enum']);
      }
      if (is_string($property['description'] ?? NULL)) {
        $constraint['description'] = $property['description'];
      }
      if (is_string($property['$ref'] ?? NULL)) {
        $constraint['schema_ref'] = (string) basename($property['$ref']);
      }
      $one_of_types = [];
      foreach ((array) ($property['oneOf'] ?? []) as $option) {
        if (is_array($option) && is_string($option['type'] ?? NULL)) {
          $one_of_types[] = $option['type'];
        }
      }
      if ($one_of_types !== []) {
        $constraint['allowed_types'] = array_values(array_unique($one_of_types));
      }
      if ($constraint !== []) {
        $constraints[(string) $field] = $constraint;
      }
    }
    return $constraints;
  }

  /**
   * Describe the canonical choice trigger/effect structure for GM tools.
   */
  public static function branchAuthoringContract(): array {
    return [
      'draft_path' => '/process_flow/branch_choices',
      'collection_identity_field' => 'choice_id',
      'canonical_projection' => 'questline.quest_nodes[].choices',
      'required_fields' => [
        'decision_id',
        'source_task_id',
        'choice_id',
        'label',
        'trigger',
        'effects',
        'target_task_id',
        'target_scene_id',
        'terminal',
      ],
      'trigger' => [
        'required_fields' => ['type', 'condition', 'source_id'],
        'field_types' => [
          'type' => 'enum',
          'condition' => 'string',
          'interaction' => 'enum',
          'source_id' => 'string',
        ],
        'action_interaction_field' => 'interaction',
        'action_interactions' => StorylineManagerService::STORYLINE_BRANCH_INTERACTIONS,
        'types' => StorylineManagerService::STORYLINE_BRANCH_TRIGGER_TYPES,
      ],
      'effects' => [
        'required_fields' => ['summary', 'actors', 'environment', 'storyline'],
        'actor_fields' => ['actor_id', 'interaction', 'reaction', 'effect', 'state_changes'],
        'actor_field_types' => [
          'actor_id' => 'string',
          'interaction' => 'enum',
          'reaction' => 'string',
          'effect' => 'string',
          'state_changes' => 'array',
        ],
        'actor_interactions' => StorylineManagerService::STORYLINE_BRANCH_INTERACTIONS,
        'environment_fields' => ['target_type', 'target_id', 'effect', 'state_changes'],
        'environment_field_types' => [
          'target_type' => 'enum',
          'target_id' => 'string',
          'effect' => 'string',
          'state_changes' => 'array',
        ],
        'environment_target_types' => StorylineManagerService::STORYLINE_BRANCH_ENVIRONMENT_TARGET_TYPES,
        'storyline_fields' => [
          'target_type',
          'target_id',
          'source_actor_id',
          'operation',
          'effect',
          'follow_up_required',
          'state_changes',
        ],
        'storyline_field_types' => [
          'target_type' => 'enum',
          'target_id' => 'string',
          'source_actor_id' => 'string',
          'operation' => 'enum',
          'effect' => 'string',
          'follow_up_required' => 'boolean',
          'state_changes' => 'array',
        ],
        'storyline_target_types' => StorylineManagerService::STORYLINE_BRANCH_TARGET_TYPES,
        'storyline_operations' => StorylineManagerService::STORYLINE_BRANCH_OPERATIONS,
        'state_change_fields' => ['key', 'operation', 'value'],
        'state_change_field_types' => [
          'key' => 'string',
          'operation' => 'enum',
          'value' => 'scalar_or_structured_value',
        ],
        'state_change_operations' => StorylineManagerService::STORYLINE_BRANCH_STATE_CHANGE_OPERATIONS,
      ],
      'invariants' => [
        'A decision has one or more choices on one source task. One choice is a fixed outcome with its trigger and effects; never invent a second choice to pad it.',
        'A choice with an empty source_task_id is storyline-wide: a condition that can fire from any task, authored once. The same choice repeated on several tasks is rejected.',
        'A task with choices continues only where its choices lead; its unlocks_to is not used. A condition that should not replace the normal continuation is a storyline-wide choice.',
        'A task that is done only when an actor is persuaded (agrees to help, agrees to join the party) has an interact objective on that actor with requires_check set to request. It completes when a player\'s Request, a Diplomacy check made harder or easier by the actor\'s attitude, succeeds; talking alone does not complete it. Do not model persuasion as a choice.',
        'Actors who travel with the party are added by the players in the camp and marching-order setup once they agree; a storyline does not add them.',
        'choice_id is unique across the storyline draft.',
        'A non-terminal choice names a target task and that task\'s scene.',
        'A terminal choice sets terminal to true and sets target_task_id and target_scene_id to empty strings.',
        'Actor or environment effects require at least one called-out storyline implication.',
        'Action triggers identify the affected actor in source_id and classify the interaction.',
        'Attacked or killed actors have an explicit reaction.',
        'Killing an actor has a storyline implication linked by source_actor_id.',
        'Choice destinations are authoritative. A task without choices continues to its unlocks_to, or to the next task row when unlocks_to is empty. unlocks_after is derived and never authored.',
      ],
      'runtime_selection' => 'variables.branch_choices[decision_id] = choice_id',
    ];
  }

  /**
   * Create a new authoring draft.
   */
  public function createDraft(?string $template_id = NULL): array {
    $this->assertCanEdit();
    $uid = (int) $this->currentUser->id();
    if ($uid <= 0) {
      throw new \UnexpectedValueException('storyline_draft_auth_required');
    }
    $template_id = trim((string) $template_id);
    if ($template_id !== '') {
      $query = $this->database->select(self::TABLE, 'd');
      $template_match = $query->orConditionGroup()
        ->condition('source_template_id', $template_id)
        ->condition('published_template_id', $template_id);
      $existing_draft_id = $query
        ->fields('d', ['draft_id'])
        ->condition('status', 'editing')
        ->condition($template_match)
        ->orderBy('updated_at', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if (is_string($existing_draft_id) && $existing_draft_id !== '') {
        return $this->getDraft($existing_draft_id);
      }
    }

    $payload = [
      'schema_version' => 'storyline-authoring-draft-v1',
      'storyline_id' => '',
      'title' => '',
      'description' => '',
      'premise' => '',
      'level_range' => '',
      'tags' => [],
      'player_hook' => '',
      'entry_actor_id' => '',
      'entry_location_id' => '',
      'ending' => '',
      'process_flow' => [
        'journal_entries' => [],
        'tasks' => [],
        'branch_choices' => [],
        'objective_completion_criteria' => [],
        'conflicts' => [],
        'narrative_architecture' => $this->emptyNarrativeArchitecture(),
      ],
      'objects' => [
        'actors' => ['generation_specs' => [], 'validation_criteria' => []],
        'dungeons' => ['generation_specs' => []],
        'rooms' => ['generation_specs' => [], 'validation_criteria' => []],
        'items' => ['generation_specs' => [], 'validation_criteria' => []],
      ],
      'orchestration' => [
        'generation' => [],
        'validation' => [],
      ],
    ];

    if ($template_id !== '') {
      $template = $this->storylineManager->getTemplate($template_id);
      if ($template === NULL) {
        throw new \OutOfBoundsException('storyline_template_not_found');
      }
      $resolved_template_id = $template_id;
      $seen = [$template_id => TRUE];
      while (TRUE) {
        $consolidation = $template['template_data']['metadata']['storyline_consolidation'] ?? [];
        if (!is_array($consolidation)) {
          throw new \DomainException('storyline_template_consolidation_redirect_invalid');
        }
        if (!array_key_exists('merged_into_template_id', $consolidation)) {
          break;
        }
        $replacement = $consolidation['merged_into_template_id'];
        if (!is_string($replacement) || trim($replacement) === '' || $replacement !== trim($replacement) || isset($seen[$replacement])) {
          throw new \DomainException('storyline_template_consolidation_redirect_invalid');
        }
        $seen[$replacement] = TRUE;
        $resolved_template_id = $replacement;
        $template = $this->storylineManager->getTemplate($resolved_template_id);
        if ($template === NULL) {
          throw new \OutOfBoundsException('storyline_template_consolidation_target_not_found');
        }
      }
      if ($resolved_template_id !== $template_id) {
        return $this->createDraft($resolved_template_id);
      }
      $template_data = is_array($template['template_data'] ?? NULL) ? $template['template_data'] : [];
      $payload['storyline_id'] = (string) ($template_data['template_id'] ?? $template_id);
      $payload['title'] = (string) ($template_data['name'] ?? $template['name'] ?? $template_id);
      $payload['description'] = (string) ($template_data['synopsis'] ?? $template['synopsis'] ?? '');
      $payload['premise'] = (string) ($template_data['metadata']['goal'] ?? '');
      $identity = is_array($template_data['metadata']['storyline_builder']['identity'] ?? NULL)
        ? $template_data['metadata']['storyline_builder']['identity']
        : [];
      $entry_point = is_array($template_data['metadata']['generated_outline']['entry_point'] ?? NULL)
        ? $template_data['metadata']['generated_outline']['entry_point']
        : [];
      $payload['level_range'] = (string) ($template_data['level_range'] ?? '');
      $payload['tags'] = array_values(array_filter((array) ($template_data['tags'] ?? []), 'is_string'));
      $payload['player_hook'] = (string) ($identity['player_hook'] ?? '');
      $payload['entry_actor_id'] = (string) ($entry_point['primary_quest_giver_id'] ?? '');
      $payload['entry_location_id'] = (string) ($entry_point['primary_location_id'] ?? '');
      $payload['ending'] = (string) ($identity['ending'] ?? '');
      $payload['canonical_template'] = $template_data;
      $payload = $this->hydrateDraftRowsFromCanonicalTemplate($payload, $template_data);
    }

    return $this->insertDraft($uid, $payload, $template_id, NULL);
  }

  /**
   * Lists drafts every storyline editor may load, with ownership metadata.
   */
  public function listDrafts(bool $include_archived = FALSE): array {
    $this->assertCanEdit();
    $uid = (int) $this->currentUser->id();
    $query = $this->database->select(self::TABLE, 'd');
    $query->leftJoin('users_field_data', 'u', 'u.uid = d.owner_uid AND u.default_langcode = 1');
    $query->leftJoin(self::REVISION_TABLE, 'r', 'r.draft_id = d.draft_id AND r.revision = d.revision');
    $query->leftJoin('users_field_data', 'editor', 'editor.uid = r.owner_uid AND editor.default_langcode = 1');
    $query->fields('d', ['draft_id', 'owner_uid', 'source_draft_id', 'source_revision', 'source_template_id', 'published_template_id', 'revision', 'status', 'draft_payload', 'created_at', 'updated_at']);
    $query->addField('u', 'name', 'owner_name');
    $query->addField('r', 'owner_uid', 'updated_by_uid');
    $query->addField('editor', 'name', 'updated_by_name');
    if (!$include_archived) {
      $query->condition('d.status', 'archived', '<>');
    }
    $rows = $query
      ->orderBy('d.updated_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $revision_counts = [];
    $count_query = $this->database->select(self::REVISION_TABLE, 'r');
    $count_query->addField('r', 'draft_id');
    $count_query->addExpression('COUNT(*)', 'revision_count');
    $count_query->groupBy('r.draft_id');
    foreach ($count_query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $count_row) {
      $revision_counts[(string) $count_row['draft_id']] = (int) $count_row['revision_count'];
    }
    return array_map(function (array $row) use ($uid, $revision_counts): array {
      $payload = $this->decodePayload((string) $row['draft_payload']);
      $archive_meta = is_array($payload['_archive_meta'] ?? NULL) ? $payload['_archive_meta'] : [];
      return [
        'draft_id' => (string) $row['draft_id'],
        'owner_uid' => (int) $row['owner_uid'],
        'owner_name' => (string) ($row['owner_name'] ?? ('User ' . $row['owner_uid'])),
        'is_owner' => (int) $row['owner_uid'] === $uid,
        'can_edit' => (string) $row['status'] === 'editing',
        'can_copy' => (string) $row['status'] !== 'editing',
        'updated_by_uid' => (int) $row['updated_by_uid'],
        'updated_by_name' => (string) $row['updated_by_name'],
        'source_draft_id' => (string) ($row['source_draft_id'] ?? ''),
        'source_revision' => isset($row['source_revision']) ? (int) $row['source_revision'] : NULL,
        'source_template_id' => (string) ($row['source_template_id'] ?? ''),
        'published_template_id' => (string) ($row['published_template_id'] ?? ''),
        'revision' => (int) $row['revision'],
        'revision_count' => $revision_counts[(string) $row['draft_id']] ?? 0,
        'status' => (string) $row['status'],
        'title' => (string) ($payload['title'] ?? $payload['storyline_id'] ?? 'Untitled storyline'),
        'storyline_id' => (string) ($payload['storyline_id'] ?? ''),
        'created_at' => (int) $row['created_at'],
        'updated_at' => (int) $row['updated_at'],
        'archived_at' => isset($archive_meta['archived_at']) ? (int) $archive_meta['archived_at'] : NULL,
      ];
    }, $rows);
  }

  /**
   * Lists only archived drafts owned by the current user.
   */
  public function listArchivedDrafts(): array {
    return array_values(array_filter(
      $this->listDrafts(TRUE),
      static fn(array $draft): bool => !empty($draft['is_owner']) && (string) ($draft['status'] ?? '') === 'archived',
    ));
  }

  /**
   * Archives an owner-bound storyline draft.
   */
  public function archiveDraft(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
    $this->assertCanManageDraft($draft);
    if ((string) ($draft['status'] ?? '') === 'archived') {
      return ['status' => 'already_archived', 'draft' => $draft];
    }
    $now = $this->time->getRequestTime();
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $payload['_archive_meta'] = [
      'previous_status' => (string) ($draft['status'] ?? 'editing'),
      'archived_at' => $now,
    ];
    $payload_json = $this->encodePayload($payload);
    $this->database->update(self::TABLE)
      ->fields([
        'status' => 'archived',
        'draft_payload' => $payload_json,
        'updated_at' => $now,
      ])
      ->condition('draft_id', $draft_id)
      ->condition('owner_uid', (int) $draft['owner_uid'])
      ->execute();

    return [
      'status' => 'archived',
      'draft' => $this->getDraft($draft_id),
    ];
  }

  /**
   * Unarchives an owner-bound storyline draft.
   */
  public function unarchiveDraft(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
    $this->assertCanManageDraft($draft);
    if ((string) ($draft['status'] ?? '') !== 'archived') {
      return ['status' => 'not_archived', 'draft' => $draft];
    }
    $now = $this->time->getRequestTime();
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $previous_status = (string) ($payload['_archive_meta']['previous_status'] ?? 'editing');
    if (!in_array($previous_status, ['editing', 'published'], TRUE)) {
      $previous_status = 'editing';
    }
    unset($payload['_archive_meta']);
    $payload_json = $this->encodePayload($payload);
    $this->database->update(self::TABLE)
      ->fields([
        'status' => $previous_status,
        'draft_payload' => $payload_json,
        'updated_at' => $now,
      ])
      ->condition('draft_id', $draft_id)
      ->condition('owner_uid', (int) $draft['owner_uid'])
      ->execute();

    return [
      'status' => 'unarchived',
      'draft' => $this->getDraft($draft_id),
    ];
  }

  /**
   * Lists one selector entry per canonical storyline plus unbound drafts.
   */
  public function listStorylineOptions(): array {
    $options = [];
    foreach ($this->storylineManager->listTemplates() as $template) {
      $template_id = trim((string) ($template['template_id'] ?? ''));
      if ($template_id === '') {
        continue;
      }
      $options[] = [
        'selector_type' => 'template',
        'template_id' => $template_id,
        'draft_id' => '',
        'title' => (string) ($template['name'] ?? $template_id),
        'owner_name' => 'Canonical',
        'is_owner' => FALSE,
        'can_edit' => FALSE,
        'can_copy' => FALSE,
        'revision' => 0,
        'revision_count' => 0,
        'status' => 'canonical',
        'updated_at' => (int) ($template['updated_at'] ?? 0),
      ];
    }
    foreach ($this->listDrafts() as $draft) {
      if ($draft['status'] !== 'editing') {
        continue;
      }
      $draft['selector_type'] = 'draft';
      $draft['template_id'] = '';
      $options[] = $draft;
    }
    return $options;
  }

  /**
   * Loads any shared draft for viewing without granting mutation authority.
   */
  public function getDraftForViewing(string $draft_id): array {
    $this->assertCanEdit();
    $requested_draft_id = $draft_id;
    $draft = $this->loadDraftRow($draft_id);
    if ($draft === NULL) {
      $draft_id = (string) ($this->database->select(self::REDIRECT_TABLE, 'r')
        ->fields('r', ['replacement_draft_id'])
        ->condition('old_draft_id', $requested_draft_id)
        ->range(0, 1)
        ->execute()
        ->fetchField() ?: '');
      $draft = $draft_id !== '' ? $this->loadDraftRow($draft_id) : NULL;
    }
    if ($draft === NULL) {
      throw new \OutOfBoundsException('storyline_draft_not_found');
    }
    if ($requested_draft_id !== $draft_id) {
      $draft['redirected_from_draft_id'] = $requested_draft_id;
    }
    return $this->projectOwnership($draft);
  }

  /**
   * Finds the most recently edited draft derived from one canonical template.
   */
  public function findLatestDraftForTemplate(string $template_id, bool $editable_only = FALSE): ?array {
    $this->assertCanEdit();
    $template_id = trim($template_id);
    if ($template_id === '') {
      throw new \InvalidArgumentException('storyline_template_id_required');
    }
    $query = $this->database->select(self::TABLE, 'd');
    $template_match = $query->orConditionGroup()
      ->condition('source_template_id', $template_id)
      ->condition('published_template_id', $template_id);
    if ($editable_only) {
      $query->condition('status', 'editing');
    }
    $row = $query
      ->fields('d', ['draft_id'])
      ->condition($template_match)
      ->orderBy('updated_at', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }
    return $this->getDraftForViewing((string) $row['draft_id']);
  }

  /**
   * Lists immutable snapshots available for one shared draft.
   */
  public function listDraftRevisions(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
    $query = $this->database->select(self::REVISION_TABLE, 'r');
    $query->leftJoin('users_field_data', 'editor', 'editor.uid = r.owner_uid AND editor.default_langcode = 1');
    $query->addField('editor', 'name', 'updated_by_name');
    $rows = $query
      ->fields('r', ['revision', 'payload_hash', 'created_at', 'owner_uid'])
      ->condition('draft_id', $draft_id)
      ->orderBy('revision', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => [
      'revision' => (int) $row['revision'],
      'payload_hash' => (string) $row['payload_hash'],
      'created_at' => (int) $row['created_at'],
      'is_current' => (int) $row['revision'] === (int) $draft['revision'],
      'updated_by_uid' => (int) $row['owner_uid'],
      'updated_by_name' => (string) $row['updated_by_name'],
    ], $rows);
  }

  /**
   * Loads one immutable shared draft snapshot without mutation authority.
   */
  public function getDraftRevisionForViewing(string $draft_id, int $revision): array {
    $draft = $this->getDraft($draft_id);
    $query = $this->database->select(self::REVISION_TABLE, 'r');
    $query->leftJoin('users_field_data', 'editor', 'editor.uid = r.owner_uid AND editor.default_langcode = 1');
    $query->addField('editor', 'name', 'updated_by_name');
    $row = $query
      ->fields('r', ['revision', 'draft_payload', 'payload_hash', 'created_at', 'owner_uid'])
      ->condition('draft_id', $draft_id)
      ->condition('revision', $revision)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \OutOfBoundsException('storyline_draft_revision_not_found');
    }
    $draft['current_revision'] = (int) $draft['revision'];
    $draft['revision'] = (int) $row['revision'];
    $draft['payload'] = $this->decodePayload((string) $row['draft_payload']);
    $draft['payload_hash'] = (string) $row['payload_hash'];
    $draft['updated_at'] = (int) $row['created_at'];
    $draft['updated_by_uid'] = (int) $row['owner_uid'];
    $draft['updated_by_name'] = (string) $row['updated_by_name'];
    $draft['is_historical'] = (int) $row['revision'] !== (int) $draft['current_revision'];
    $draft['can_edit'] = FALSE;
    $draft['can_copy'] = TRUE;
    return $draft;
  }

  /**
   * Returns caller-relative draft metrics without exposing shared history.
   */
  public function summarizeDraftLibrary(): array {
    $uid = (int) $this->currentUser->id();
    if ($uid <= 0) {
      throw new \UnexpectedValueException('storyline_draft_auth_required');
    }
    $total = (int) $this->database->select(self::TABLE, 'd')
      ->condition('status', 'archived', '<>')
      ->countQuery()
      ->execute()
      ->fetchField();
    $mine = (int) $this->database->select(self::TABLE, 'd')
      ->condition('owner_uid', $uid)
      ->condition('status', 'archived', '<>')
      ->countQuery()
      ->execute()
      ->fetchField();
    $editing = (int) $this->database->select(self::TABLE, 'd')
      ->condition('owner_uid', $uid)
      ->condition('status', 'editing')
      ->countQuery()
      ->execute()
      ->fetchField();
    $archived = (int) $this->database->select(self::TABLE, 'd')
      ->condition('owner_uid', $uid)
      ->condition('status', 'archived')
      ->countQuery()
      ->execute()
      ->fetchField();
    $versions = (int) $this->database->select(self::REVISION_TABLE, 'r')
      ->condition('owner_uid', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();
    return [
      'total_drafts' => $total,
      'my_drafts' => $mine,
      'shared_drafts' => max(0, $total - $mine),
      'my_editing_drafts' => $editing,
      'my_archived_drafts' => $archived,
      'my_saved_versions' => $versions,
    ];
  }

  /**
   * Permanently deletes one creator-managed draft and its shared history.
   */
  public function deleteDraft(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
    $this->assertCanManageDraft($draft);
    $transaction = $this->database->startTransaction();
    $this->database->delete(self::OPERATION_TABLE)
      ->condition('draft_id', $draft_id)
      ->execute();
    $this->database->delete(self::REDIRECT_TABLE)
      ->condition('replacement_draft_id', $draft_id)
      ->execute();
    $revision_count = $this->database->delete(self::REVISION_TABLE)
      ->condition('draft_id', $draft_id)
      ->execute();
    $deleted = $this->database->delete(self::TABLE)
      ->condition('draft_id', $draft_id)
      ->condition('owner_uid', (int) $draft['owner_uid'])
      ->execute();
    if ($deleted !== 1) {
      throw new \DomainException('storyline_draft_delete_conflict');
    }
    return [
      'draft_id' => $draft_id,
      'deleted' => TRUE,
      'deleted_revisions' => (int) $revision_count,
    ];
  }

  /**
   * Creates an owner-isolated derivative from a shared draft.
   */
  public function copyDraft(string $source_draft_id, ?int $source_revision = NULL): array {
    $source = $source_revision === NULL
      ? $this->getDraftForViewing($source_draft_id)
      : $this->getDraftRevisionForViewing($source_draft_id, $source_revision);
    $uid = (int) $this->currentUser->id();
    $payload = $this->deriveOwnedDraftIdentity((array) $source['payload'], $uid);
    return $this->insertDraft(
      $uid,
      $payload,
      (string) ($source['published_template_id'] ?: $source['source_template_id']),
      $source_draft_id,
      (int) $source['revision'],
    );
  }

  /**
   * Inserts one new owner-bound draft.
   */
  private function insertDraft(
    int $uid,
    array $payload,
    string $template_id,
    ?string $source_draft_id,
    ?int $source_revision = NULL,
  ): array {
    $draft_id = $this->uuid->generate();
    if (($payload['storyline_id'] ?? '') === '') {
      $payload['storyline_id'] = 'storyline-' . $draft_id;
    }
    $now = $this->time->getRequestTime();
    $payload_json = $this->encodePayload($payload);
    $payload_hash = hash('sha256', $payload_json);
    $transaction = $this->database->startTransaction();
    $this->database->insert(self::TABLE)
      ->fields([
        'draft_id' => $draft_id,
        'owner_uid' => $uid,
        'source_draft_id' => $source_draft_id,
        'source_revision' => $source_revision,
        'source_template_id' => $template_id !== '' ? $template_id : NULL,
        'revision' => 1,
        'status' => 'editing',
        'draft_payload' => $payload_json,
        'payload_hash' => $payload_hash,
        'created_at' => $now,
        'updated_at' => $now,
        'published_template_id' => NULL,
        'published_at' => NULL,
        'publication_receipt' => NULL,
      ])
      ->execute();
    $this->insertRevisionSnapshot($draft_id, 1, $uid, $payload_json, $payload_hash, $now);
    return $this->getDraft($draft_id);
  }

  /**
   * Loads a shared draft for an authorized storyline editor.
   */
  public function getDraft(string $draft_id): array {
    $this->assertCanEdit();
    $draft = $this->loadDraftRow($draft_id);
    if ($draft === NULL) {
      throw new \OutOfBoundsException('storyline_draft_not_found');
    }
    $uid = (int) $this->currentUser->id();
    if ($uid <= 0) {
      throw new \UnexpectedValueException('storyline_draft_auth_required');
    }
    return $this->projectOwnership($draft);
  }

  /**
   * Build a publishable canonical storyline definition from one draft.
   */
  public function previewTemplateDefinition(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    return $this->storylineManager->prepareStorylineDefinition(
      $this->buildTemplateDefinitionFromDraftPayload($payload)
    );
  }

  /**
   * Validate one draft against canonical storyline contracts.
   */
  public function validateDraft(string $draft_id, string $profile = 'publication'): array {
    if (!in_array($profile, ['editing', 'publication'], TRUE)) {
      throw new \InvalidArgumentException('storyline_draft_validation_profile_invalid');
    }
    try {
      $definition = $this->previewTemplateDefinition($draft_id);
    }
    catch (\InvalidArgumentException | \DomainException $exception) {
      $code = trim($exception->getMessage());
      if (!str_starts_with($code, 'storyline_draft_')) {
        throw $exception;
      }
      return [
        'valid' => FALSE,
        'profile' => $profile,
        'payload_type' => 'definition',
        'errors' => [$code],
        'warnings' => [],
        'error_count' => 1,
        'warning_count' => 0,
        'stage_count' => 1,
        'stages' => [[
          'stage' => 'draft_materialization',
          'valid' => FALSE,
          'error_count' => 1,
          'warning_count' => 0,
          'errors' => [$code],
          'warnings' => [],
          'findings' => [[
            'severity' => 'error',
            'code' => $code,
            'message' => str_replace('_', ' ', ucfirst($code)),
          ]],
        ]],
        'analysis' => [],
        'definition' => [],
      ];
    }
    $validation = $this->storylineManager->validateStorylineEndToEndContract($definition, 'definition');
    $draft = $this->getDraft($draft_id);
    $room_bindings = (array) ($draft['payload']['objects']['rooms']['generation_specs'] ?? []);
    $binding_errors = $this->storylineManager->validateAuthoredRoomDungeonBindings($room_bindings);
    $scene_findings = self::roomSceneFindings($draft['payload'] ?? []);
    $validation['stages']['authored_room_dungeon_bindings'] = [
      'valid' => $binding_errors === [] && $scene_findings === [],
      'errors' => array_merge(array_column($scene_findings, 'message'), $binding_errors),
      'findings' => $scene_findings,
    ];
    $stages = is_array($validation['stages'] ?? NULL) ? $validation['stages'] : [];
    $choice_findings = $this->authoredChoiceFindings($draft['payload'] ?? []);
    if ($choice_findings !== []) {
      $progression = is_array($stages['questline_progression'] ?? NULL) ? $stages['questline_progression'] : [];
      $stages['questline_progression'] = [
        'valid' => FALSE,
        'errors' => array_merge(array_column($choice_findings, 'message'), (array) ($progression['errors'] ?? [])),
        'findings' => array_merge($choice_findings, (array) ($progression['findings'] ?? [])),
      ] + $progression;
    }
    $sheet_findings = array_merge(
      $this->libraryActorDuplicateFindings($draft['payload'] ?? []),
      $this->actorPlacementFindings($draft),
      self::institutionFindings($draft['payload'] ?? []),
      self::actorSheetFindings($draft['payload'] ?? []),
    );
    $stages['actor_character_sheets'] = [
      'valid' => $sheet_findings === [],
      'errors' => array_column($sheet_findings, 'message'),
      'warnings' => array_merge($this->questGiverWarnings($draft), self::actorTieWarnings($draft['payload'] ?? [])),
      'findings' => $sheet_findings,
    ];
    $stages['authored_questline_edges'] = [
      'valid' => TRUE,
      'errors' => [],
      'warnings' => $this->validateAuthoredQuestlineEdgeProjection($draft['payload'] ?? [], $definition),
    ];
    $stage_summaries = [];
    $blocking_errors = [];
    $warnings = array_values(array_filter((array) ($validation['warnings'] ?? []), static fn($warning): bool => is_string($warning) && trim($warning) !== ''));
    foreach ($stages as $stage_key => $stage_result) {
      $errors = array_values(array_filter((array) ($stage_result['errors'] ?? []), static fn($error): bool => is_string($error) && trim($error) !== ''));
      $stage_warnings = array_values(array_filter((array) ($stage_result['warnings'] ?? []), static fn($warning): bool => is_string($warning) && trim($warning) !== ''));
      if (!in_array((string) $stage_key, self::BLOCKING_VALIDATION_STAGES, TRUE)) {
        $stage_warnings = array_values(array_unique(array_merge($stage_warnings, $errors)));
        $errors = [];
      }
      else {
        $blocking_errors = array_merge($blocking_errors, $errors);
      }
      $warnings = array_merge($warnings, $stage_warnings);
      $stage_summaries[] = [
        'stage' => (string) $stage_key,
        'route' => self::STAGE_FIX_ROUTES[(string) $stage_key] ?? 'unclassified',
        'valid' => $errors === [],
        'error_count' => count($errors),
        'warning_count' => count($stage_warnings),
        'errors' => $errors,
        'warnings' => $stage_warnings,
        'findings' => array_map(
          static fn(array $finding): array => $finding + [
            'route' => self::FINDING_FIX_ROUTES[(string) ($finding['code'] ?? '')]
              ?? self::STAGE_FIX_ROUTES[(string) $stage_key]
              ?? 'unclassified',
          ],
          array_values(array_filter((array) ($stage_result['findings'] ?? []), 'is_array')),
        ),
      ];
    }
    $blocking_errors = array_values(array_unique($blocking_errors));
    $warnings = array_values(array_unique($warnings));
    return [
      'valid' => $blocking_errors === [],
      'profile' => $profile,
      'payload_type' => 'definition',
      'errors' => $blocking_errors,
      'warnings' => $warnings,
      'error_count' => count($blocking_errors),
      'warning_count' => count($warnings),
      'stage_count' => count($stage_summaries),
      'stages' => $stage_summaries,
      'analysis' => is_array($validation['analysis'] ?? NULL) ? $validation['analysis'] : [],
      'definition' => $definition,
    ];
  }

  /**
   * Returns the human-authoring completion state for one draft.
   */
  public function summarizeDraftStatus(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
    return $this->summarizeDraftState($draft, $this->validateDraft($draft_id));
  }

  /**
   * Returns a brief synopsis and whether the draft has complete story shape.
   */
  public function summarizeDraftSynopsis(string $draft_id): array {
    $definition = $this->previewTemplateDefinition($draft_id);
    $status = $this->summarizeDraftStatus($draft_id);
    $metadata = is_array($definition['metadata'] ?? NULL) ? $definition['metadata'] : [];
    $outline = is_array($metadata['generated_outline'] ?? NULL) ? $metadata['generated_outline'] : [];
    $chapters = array_values(array_filter((array) ($definition['chapters'] ?? []), 'is_array'));
    $scene_count = 0;
    foreach ($chapters as $chapter) {
      $scene_count += count(array_filter((array) ($chapter['scenes'] ?? []), 'is_array'));
    }

    $synopsis = trim((string) ($definition['synopsis'] ?? ''));
    $objective = trim((string) ($metadata['goal'] ?? $outline['goal'] ?? ''));
    $gaps = array_values(array_filter((array) ($status['missing_content'] ?? []), 'is_string'));
    if ($synopsis === '') {
      $gaps[] = 'Story synopsis';
    }
    if ($objective === '') {
      $gaps[] = 'Overall objective';
    }
    if ($scene_count === 0) {
      $gaps[] = 'At least one scene';
    }
    $gaps = array_values(array_unique($gaps));
    $narrative = $this->narrativeArchitectureStatus($definition['metadata']['storyline_builder']['narrative_architecture'] ?? []);

    return [
      'schema_version' => 'storyline-synopsis-v1',
      'qualifies_as_story' => $gaps === [],
      'title' => (string) ($definition['name'] ?? ''),
      'synopsis' => $synopsis,
      'objective' => $objective,
      'act_structure_defined' => $narrative['beats_complete'] === $narrative['beats_total'],
      'chapter_count' => count($chapters),
      'scene_count' => $scene_count,
      'conflict_count' => (int) ($status['counts']['conflicts'] ?? 0),
      'narrative_architecture' => $narrative,
      'gaps' => $gaps,
    ];
  }

  /**
   * Return authored decisions, choices, triggers, and effect implications.
   */
  public function summarizeDraftBranching(string $draft_id): array {
    $definition = $this->previewTemplateDefinition($draft_id);
    $decisions = [];
    $choice_count = 0;
    $terminal_count = 0;
    $follow_up_count = 0;
    foreach (array_values(array_filter((array) ($definition['questline']['quest_nodes'] ?? []), 'is_array')) as $node) {
      $source_task_id = trim((string) ($node['quest_id'] ?? ''));
      foreach (array_values(array_filter((array) ($node['choices'] ?? []), 'is_array')) as $choice) {
        $decision_id = trim((string) ($choice['decision_id'] ?? ''));
        if ($decision_id === '') {
          continue;
        }
        $choice_count++;
        if (($choice['terminal'] ?? FALSE) === TRUE) {
          $terminal_count++;
        }
        foreach (array_values(array_filter((array) ($choice['effects']['storyline'] ?? []), 'is_array')) as $effect) {
          if (($effect['follow_up_required'] ?? FALSE) === TRUE) {
            $follow_up_count++;
          }
        }
        $decisions[$decision_id] ??= [
          'decision_id' => $decision_id,
          'source_task_id' => $source_task_id,
          'choices' => [],
        ];
        $decisions[$decision_id]['choices'][] = $choice;
      }
    }
    foreach (array_values(array_filter((array) ($definition['questline']['storyline_choices'] ?? []), 'is_array')) as $choice) {
      $decision_id = trim((string) ($choice['decision_id'] ?? ''));
      if ($decision_id === '') {
        continue;
      }
      $choice_count++;
      if (($choice['terminal'] ?? FALSE) === TRUE) {
        $terminal_count++;
      }
      foreach (array_values(array_filter((array) ($choice['effects']['storyline'] ?? []), 'is_array')) as $effect) {
        if (($effect['follow_up_required'] ?? FALSE) === TRUE) {
          $follow_up_count++;
        }
      }
      $decisions[$decision_id] ??= [
        'decision_id' => $decision_id,
        'source_task_id' => '',
        'storyline_wide' => TRUE,
        'choices' => [],
      ];
      $decisions[$decision_id]['choices'][] = $choice;
    }
    return [
      'schema_version' => 'storyline-branching-summary-v1',
      'decision_count' => count($decisions),
      'choice_count' => $choice_count,
      'terminal_choice_count' => $terminal_count,
      'follow_up_implication_count' => $follow_up_count,
      'has_branching' => $decisions !== [],
      'decisions' => array_values($decisions),
      'contract' => self::branchAuthoringContract(),
    ];
  }

  /**
   * The authored part of an actor row, without its character sheet link.
   *
   * A sheet records this hash when it is built; a different hash later means
   * the actor was edited after its sheet.
   */
  public static function actorSpecHash(array $spec): string {
    $source = array_diff_key($spec, array_flip(self::ACTOR_SHEET_LINK_FIELDS));
    ksort($source);
    return hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));
  }

  /**
   * One blocking finding per roster actor without a current character sheet.
   *
   * @return array<int, array<string, mixed>>
   */
  public static function actorSheetFindings(array $payload): array {
    $findings = [];
    foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $spec) {
      if (!is_array($spec) || trim((string) ($spec['actor_id'] ?? '')) === '') {
        continue;
      }
      $name = trim((string) ($spec['display_name'] ?? '')) ?: (string) $spec['actor_id'];
      if (empty($spec['character_id'])) {
        $findings[] = [
          'severity' => 'error',
          'code' => 'actor_sheet_missing',
          'actor_id' => (string) $spec['actor_id'],
          'display_name' => $name,
          'message' => sprintf("Actor '%s' has no character sheet. Build the roster's character sheets.", $name),
        ];
      }
      elseif (($spec['character_sheet_spec_hash'] ?? NULL) !== self::actorSpecHash($spec)) {
        $findings[] = [
          'severity' => 'error',
          'code' => 'actor_sheet_outdated',
          'actor_id' => (string) $spec['actor_id'],
          'display_name' => $name,
          'message' => sprintf("Actor '%s' was changed after its character sheet was built. Rebuild its character sheet.", $name),
        ];
      }
    }
    return $findings;
  }

  /**
   * Roster actors that would duplicate a character already in the library.
   *
   * An actor named like a library character that has a different ID is the
   * same character under a second identity. Building its sheet would
   * create the duplicate, and one built earlier is one.
   *
   * @return array<int, array<string, mixed>>
   */
  public function libraryActorDuplicateFindings(array $payload): array {
    $named = [];
    foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $spec) {
      $name = is_array($spec) ? trim((string) ($spec['display_name'] ?? '')) : '';
      if ($name !== '' && trim((string) ($spec['actor_id'] ?? '')) !== '') {
        $named[mb_strtolower($name)] = $spec;
      }
    }
    if ($named === []) {
      return [];
    }
    $rows = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['instance_id', 'name'])
      ->condition('campaign_id', 0)
      ->condition('name', array_values(array_map(static fn(array $spec): string => trim((string) $spec['display_name']), $named)), 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    // A cast member bound to a library record is not a duplicate of it,
    // nor of other library records that share its name.
    $library_ids = array_fill_keys(array_column($rows, 'instance_id'), TRUE);
    $retired = $this->retiredLibraryCharacters();
    $findings = [];
    foreach ($rows as $row) {
      $spec = $named[mb_strtolower(trim((string) $row['name']))] ?? NULL;
      if ($spec === NULL || isset($library_ids[(string) $spec['actor_id']]) || isset($retired[(string) $row['instance_id']])) {
        continue;
      }
      $findings[] = [
        'severity' => 'error',
        'code' => 'actor_duplicates_library_actor',
        'actor_id' => (string) $spec['actor_id'],
        'library_actor_id' => (string) $row['instance_id'],
        'display_name' => (string) $row['name'],
        'message' => sprintf(
          "Actor '%s' already exists in the library as '%s', but this storyline lists it under the new ID '%s'. Use the existing actor's ID everywhere this storyline names it, or give this actor a different name if it is a different character.",
          (string) $row['name'],
          (string) $row['instance_id'],
          (string) $spec['actor_id'],
        ),
      ];
    }
    return $findings;
  }

  /**
   * What each storyline needs from one dungeon or room.
   *
   * Read by the Dungeon and Room editors so a site is built from the story
   * that uses it. Nothing is copied: every call reads the storyline drafts.
   * One entry per draft that names the site; a site may serve several.
   *
   * @return array{kind: string, id: string, storylines: array<int, array<string, mixed>>}
   */
  public function storyBriefs(string $kind, string $id): array {
    $this->assertCanEdit();
    return $this->readStoryBriefs($kind, $id);
  }

  /**
   * Which storylines name each dungeon and each room, in one pass.
   *
   * @return array{dungeons: array<string, array<int, array{draft_id: string, title: string, status: string}>>, rooms: array<string, array<int, array{draft_id: string, title: string, status: string}>>}
   */
  public function placeUsageIndex(): array {
    $rows = $this->database->select(self::TABLE, 'd')
      ->fields('d', ['draft_id', 'status', 'draft_payload'])
      ->orderBy('updated_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $index = ['dungeons' => [], 'rooms' => []];
    foreach ($rows as $row) {
      $payload = json_decode((string) $row['draft_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      $story = [
        'draft_id' => (string) $row['draft_id'],
        'title' => trim((string) ($payload['title'] ?? '')),
        'status' => (string) $row['status'],
      ];
      $rooms = array_filter((array) ($payload['objects']['rooms']['generation_specs'] ?? []), 'is_array');
      $dungeons = array_filter((array) ($payload['objects']['dungeons']['generation_specs'] ?? []), 'is_array');
      $named = [
        'dungeons' => array_merge(array_column($dungeons, 'dungeon_id'), array_column($rooms, 'dungeon_id')),
        'rooms' => array_column($rooms, 'room_id'),
      ];
      foreach ($named as $kind => $ids) {
        foreach (array_unique(array_filter(array_map(static fn(mixed $id): string => trim((string) $id), $ids))) as $id) {
          $index[$kind][$id][] = $story;
        }
      }
    }
    return $index;
  }

  /**
   * The story brief without a permission check.
   *
   * For the Dungeon and Room editors, which derive status and findings from
   * it. Callers show a storyline's title and room IDs only.
   */
  public function readStoryBriefs(string $kind, string $id): array {
    if (!in_array($kind, ['dungeon', 'room'], TRUE) || trim($id) === '') {
      throw new \InvalidArgumentException('storyline_story_brief_subject_invalid:' . $kind . ':' . $id);
    }
    $rows = $this->database->select(self::TABLE, 'd')
      ->fields('d', ['draft_id', 'status', 'revision', 'draft_payload'])
      ->orderBy('updated_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $storylines = [];
    foreach ($rows as $row) {
      $payload = json_decode((string) $row['draft_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
      $list = static fn(mixed $value): array => array_values(array_filter((array) $value, 'is_array'));
      $rooms = $list($payload['objects']['rooms']['generation_specs'] ?? []);
      $subject_rooms = array_values(array_filter(
        $rooms,
        static fn(array $room): bool => trim((string) ($room[$kind === 'dungeon' ? 'dungeon_id' : 'room_id'] ?? '')) === $id,
      ));
      $site = NULL;
      foreach ($list($payload['objects']['dungeons']['generation_specs'] ?? []) as $dungeon) {
        if ($kind === 'dungeon' && trim((string) ($dungeon['dungeon_id'] ?? '')) === $id) {
          $site = $dungeon;
        }
      }
      if ($subject_rooms === [] && $site === NULL) {
        continue;
      }
      $flow = is_array($payload['process_flow'] ?? NULL) ? $payload['process_flow'] : [];
      $scenes = array_column($list($flow['journal_entries'] ?? []), NULL, 'scene_id');
      $tasks_by_scene = [];
      foreach ($list($flow['tasks'] ?? []) as $task) {
        $goal = trim((string) ($task['goal_text'] ?? ''));
        if ($goal !== '') {
          $tasks_by_scene[(string) ($task['scene_id'] ?? '')][] = $goal;
        }
      }
      $cast_by_place = [];
      foreach ($list($payload['objects']['actors']['generation_specs'] ?? []) as $actor) {
        $place = trim((string) ($actor['location_id'] ?? ''));
        if ($place !== '') {
          $cast_by_place[$place][] = [
            'name' => trim((string) ($actor['display_name'] ?? '')) ?: (string) ($actor['actor_id'] ?? ''),
            'role' => str_replace('_', ' ', (string) ($actor['role'] ?? '')),
            'attitude' => (string) ($actor['attitude'] ?? ''),
          ];
        }
      }
      $objectives_by_place = [];
      foreach ($list($flow['objective_completion_criteria'] ?? []) as $objective) {
        foreach (['location', 'destination', 'target'] as $field) {
          $place = trim((string) ($objective[$field] ?? ''));
          if ($place !== '' && trim((string) ($objective['description'] ?? '')) !== '') {
            $objectives_by_place[$place][] = trim((string) $objective['description']);
          }
        }
      }
      $room_briefs = [];
      foreach ($subject_rooms as $room) {
        $room_id = trim((string) ($room['room_id'] ?? ''));
        $scene = $scenes[(string) ($room['scene_id'] ?? '')] ?? [];
        $room_briefs[] = [
          'room_id' => $room_id,
          'dungeon_id' => trim((string) ($room['dungeon_id'] ?? '')),
          'narrative' => trim((string) ($room['narrative'] ?? '')),
          'scene' => trim((string) ($scene['title'] ?? '')),
          'scene_summary' => trim((string) ($scene['summary'] ?? '')),
          'tasks' => $tasks_by_scene[(string) ($room['scene_id'] ?? '')] ?? [],
          'objectives' => $objectives_by_place[$room_id] ?? [],
          'starting_cast' => $cast_by_place[$room_id] ?? [],
        ];
      }
      $storylines[] = [
        'draft_id' => (string) $row['draft_id'],
        'title' => trim((string) ($payload['title'] ?? '')),
        'status' => (string) $row['status'],
        'revision' => (int) $row['revision'],
        'premise' => trim((string) ($payload['premise'] ?? '')),
      ] + ($kind === 'dungeon' ? [
        'purpose' => trim((string) ($site['purpose'] ?? '')),
        'entrance_room' => trim((string) ($site['entrance_room'] ?? '')),
        'starting_cast_at_site' => $cast_by_place[$id] ?? [],
      ] : []) + ['rooms' => $room_briefs];
    }
    return ['kind' => $kind, 'id' => $id, 'storylines' => $storylines];
  }

  /**
   * Searches the character library: full characters and placed contacts.
   *
   * @return array{total: int, characters: array<int, array<string, mixed>>}
   */
  public function findLibraryCharacters(string $search = '', bool $quest_givers_only = FALSE, int $limit = 25): array {
    if ($limit < 1 || $limit > 50) {
      throw new \InvalidArgumentException('storyline_library_character_limit_invalid');
    }
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['instance_id', 'name', 'role', 'level', 'location_ref', 'character_data'])
      ->condition('campaign_id', 0)
      ->condition('type', 'npc')
      ->condition('status', 2, '<>')
      ->condition('lifecycle_state', ['canonical_template', 'canonical_seed'], 'IN')
      ->orderBy('name');
    if ($quest_givers_only) {
      $query->condition('role', self::QUEST_GIVER_ROLES, 'IN');
    }
    $terms = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($search))) ?: []));
    $matches = [];
    $retired = $this->retiredLibraryCharacters();
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      if (isset($retired[(string) $row['instance_id']])) {
        continue;
      }
      $data = json_decode((string) $row['character_data'], TRUE);
      $description = is_array($data) ? trim((string) ($data['description'] ?? '')) : '';
      $haystack = mb_strtolower(implode(' ', [$row['name'], $row['instance_id'], $row['role'], $row['location_ref'], $description]));
      foreach ($terms as $term) {
        if (!str_contains($haystack, $term)) {
          continue 2;
        }
      }
      $matches[] = [
        'actor_id' => (string) $row['instance_id'],
        'name' => (string) $row['name'],
        'role' => (string) $row['role'],
        'level' => (int) $row['level'],
        'home_location' => (string) $row['location_ref'],
        'description' => mb_substr($description, 0, 200),
        'gives_quests' => in_array((string) $row['role'], self::QUEST_GIVER_ROLES, TRUE),
      ];
    }
    return ['total' => count($matches), 'characters' => array_slice($matches, 0, $limit)];
  }

  /**
   * Roster actors that were in the library before this draft was started.
   *
   * A character whose library record is older than the draft was not made
   * for it; one created later was built by this storyline's sheet build.
   *
   * @return array<string, true>
   */
  private function libraryCharactersOlderThanDraft(array $draft): array {
    $actor_ids = array_values(array_filter(array_map(
      static fn(mixed $spec): string => is_array($spec) ? trim((string) ($spec['actor_id'] ?? '')) : '',
      (array) ($draft['payload']['objects']['actors']['generation_specs'] ?? []),
    )));
    if ($actor_ids === []) {
      return [];
    }
    $existing = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['instance_id'])
      ->condition('campaign_id', 0)
      ->condition('instance_id', $actor_ids, 'IN')
      ->condition('created', (int) ($draft['created_at'] ?? 0), '<')
      ->execute()
      ->fetchCol();
    return array_fill_keys(array_map('strval', $existing), TRUE);
  }

  /**
   * A character new to this story must be placed in one of its rooms or sites.
   *
   * @return array<int, array<string, mixed>>
   */
  private function actorPlacementFindings(array $draft): array {
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $places = [];
    foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $room) {
      $places[trim((string) ($room['room_id'] ?? ''))] = TRUE;
    }
    foreach ((array) ($payload['objects']['dungeons']['generation_specs'] ?? []) as $dungeon) {
      $places[trim((string) ($dungeon['dungeon_id'] ?? ''))] = TRUE;
    }
    $from_library = $this->libraryCharactersOlderThanDraft($draft);
    $findings = [];
    foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $spec) {
      $actor_id = is_array($spec) ? trim((string) ($spec['actor_id'] ?? '')) : '';
      if ($actor_id === '' || isset($from_library[$actor_id])) {
        continue;
      }
      $name = trim((string) ($spec['display_name'] ?? '')) ?: $actor_id;
      $location = trim((string) ($spec['location_id'] ?? ''));
      if ($location === '') {
        $findings[] = [
          'severity' => 'error',
          'code' => 'actor_placement_missing',
          'actor_id' => $actor_id,
          'display_name' => $name,
          'message' => sprintf("Actor '%s' is new to this story and is not placed anywhere. Set its location to one of this storyline's rooms or sites.", $name),
        ];
      }
      elseif (!isset($places[$location])) {
        $findings[] = [
          'severity' => 'error',
          'code' => 'actor_placement_unknown',
          'actor_id' => $actor_id,
          'display_name' => $name,
          'location_id' => $location,
          'message' => sprintf("Actor '%s' is placed at '%s', which is not a room or site of this storyline. Set its location to one that is.", $name, $location),
        ];
      }
    }
    return $findings;
  }

  /**
   * What ties one cast member to the rest of the cast in a storyline draft.
   *
   * Groups come from the actor's sheet (ancestry, class) and its authored
   * affiliations; each lists the other cast members who share it. Personal
   * ties are the authored actor relationships, in both directions.
   *
   * @return array{storyline: string, groups: array<int, array{kind: string, name: string, shared_with: string[]}>, ties: array<int, array<string, string>>}|null
   *   NULL when the draft has no such cast member.
   */
  public function actorStoryTies(string $draft_id, string $actor_id): ?array {
    $payload = $this->getDraftForViewing($draft_id)['payload'];
    $cast = array_values(array_filter((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'is_array'));
    $names = [];
    foreach ($cast as $member) {
      $names[(string) ($member['actor_id'] ?? '')] = trim((string) ($member['display_name'] ?? '')) ?: (string) ($member['actor_id'] ?? '');
    }
    if (!isset($names[$actor_id])) {
      return NULL;
    }
    $institutions = array_column(array_filter((array) ($payload['objects']['institutions'] ?? []), 'is_array'), NULL, 'institution_id');
    $groups_of = static function (array $member) use ($institutions): array {
      $groups = [];
      foreach (['ancestry' => 'Ancestry', 'class' => 'Class'] as $field => $kind) {
        $value = trim((string) ($member[$field] ?? ''));
        if ($value !== '') {
          $groups[$kind . ':' . mb_strtolower($value)] = ['kind' => $kind, 'name' => ucwords(str_replace(['_', '-'], ' ', $value))];
        }
      }
      foreach ((array) ($member['affiliations'] ?? []) as $affiliation) {
        $institution = $institutions[(string) ($affiliation['institution_id'] ?? '')] ?? NULL;
        if ($institution !== NULL) {
          $groups['institution:' . $institution['institution_id']] = [
            'kind' => InstitutionNormalizationService::AUTHORABLE_DOMAINS[(string) $institution['domain']] ?? ucfirst((string) $institution['domain']),
            'name' => trim((string) $institution['name']),
          ];
        }
      }
      return $groups;
    };
    $groups = [];
    foreach ($cast as $member) {
      $member_id = (string) ($member['actor_id'] ?? '');
      foreach ($groups_of($member) as $key => $group) {
        $groups[$key] ??= $group + ['members' => []];
        $groups[$key]['members'][$member_id] = $names[$member_id];
      }
    }
    $mine = [];
    foreach ($groups as $group) {
      if (isset($group['members'][$actor_id])) {
        $mine[] = [
          'kind' => $group['kind'],
          'name' => $group['name'],
          'shared_with' => array_values(array_diff_key($group['members'], [$actor_id => TRUE])),
        ];
      }
    }
    $ties = [];
    foreach ((array) ($payload['objects']['actors']['relationships'] ?? []) as $relationship) {
      $source = (string) ($relationship['source_actor_id'] ?? '');
      $target = (string) ($relationship['target_actor_id'] ?? '');
      if ($source !== $actor_id && $target !== $actor_id) {
        continue;
      }
      $outgoing = $source === $actor_id;
      $other = $outgoing ? $target : $source;
      $ties[] = [
        'direction' => $outgoing ? 'toward' : 'from',
        'other_actor_id' => $other,
        'other_name' => $names[$other] ?? $other,
        'attitude' => (string) ($relationship['attitude'] ?? ''),
        'bond' => trim((string) ($relationship['bond'] ?? '')),
        'notes' => trim((string) ($relationship['notes'] ?? '')),
      ];
    }
    // The actor's own standing in each institution it is tied to.
    foreach ($cast as $member) {
      if ((string) ($member['actor_id'] ?? '') !== $actor_id) {
        continue;
      }
      foreach (array_filter((array) ($member['affiliations'] ?? []), 'is_array') as $affiliation) {
        $institution = $institutions[(string) ($affiliation['institution_id'] ?? '')] ?? NULL;
        $standing = (string) ($affiliation['standing'] ?? 'member');
        foreach ($mine as $position => $group) {
          if ($institution !== NULL && $group['name'] === trim((string) $institution['name']) && $standing !== 'member') {
            $mine[$position]['name'] .= ' (' . $standing . ')';
          }
        }
      }
    }
    return ['storyline' => trim((string) ($payload['title'] ?? '')), 'groups' => $mine, 'ties' => $ties];
  }

  /**
   * Institutions whose leader or seat does not hold in this storyline.
   */
  private static function institutionFindings(array $payload): array {
    $cast = array_column(array_filter((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'is_array'), NULL, 'actor_id');
    $places = array_fill_keys(array_merge(
      array_column(array_filter((array) ($payload['objects']['rooms']['generation_specs'] ?? []), 'is_array'), 'room_id'),
      array_column(array_filter((array) ($payload['objects']['dungeons']['generation_specs'] ?? []), 'is_array'), 'dungeon_id'),
    ), TRUE);
    $findings = [];
    foreach (array_filter((array) ($payload['objects']['institutions'] ?? []), 'is_array') as $institution) {
      $id = (string) $institution['institution_id'];
      $name = trim((string) $institution['name']);
      $finding = static fn(string $code, string $message): array => ['severity' => 'error', 'code' => $code, 'institution_id' => $id, 'display_name' => $name, 'message' => $message];
      $leader = trim((string) ($institution['leader_actor_id'] ?? ''));
      if ($leader !== '' && !isset($cast[$leader])) {
        $findings[] = $finding('institution_leader_unknown', sprintf("Institution '%s' is led by '%s', who is not in this storyline's cast. Name a cast member or clear the leader.", $name, $leader));
      }
      elseif ($leader !== '') {
        $ties = array_column(array_filter((array) ($cast[$leader]['affiliations'] ?? []), 'is_array'), NULL, 'institution_id');
        if (!isset($ties[$id]) || !self::belongs($ties[$id])) {
          $findings[] = $finding('institution_leader_not_member', sprintf("Institution '%s' is led by '%s', who does not belong to it. Give that cast member an affiliation to it with standing leader.", $name, trim((string) ($cast[$leader]['display_name'] ?? '')) ?: $leader));
        }
      }
      $seat = trim((string) ($institution['seat_id'] ?? ''));
      if ($seat !== '' && !isset($places[$seat])) {
        $findings[] = $finding('institution_seat_unknown', sprintf("Institution '%s' is based at '%s', which is not a room or site of this storyline. Set its seat to one that is, or clear it.", $name, $seat));
      }
    }
    return $findings;
  }

  /**
   * Warns when cast members have nothing that ties them to anyone.
   *
   * Ancestry and class come from the sheet. Clan, family, settlement and
   * the like are authored as affiliations, and how one cast member regards
   * another as a relationship; without them nothing is recorded when the
   * story is played.
   *
   * @return string[]
   */
  private static function actorTieWarnings(array $payload): array {
    $cast = array_values(array_filter((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'is_array'));
    if (count($cast) < 2) {
      return [];
    }
    $related = [];
    foreach ((array) ($payload['objects']['actors']['relationships'] ?? []) as $relationship) {
      $related[(string) ($relationship['source_actor_id'] ?? '')] = TRUE;
      $related[(string) ($relationship['target_actor_id'] ?? '')] = TRUE;
    }
    $no_affiliation = [];
    $no_relationship = [];
    foreach ($cast as $member) {
      $name = trim((string) ($member['display_name'] ?? '')) ?: (string) ($member['actor_id'] ?? '');
      if (array_filter((array) ($member['affiliations'] ?? []), 'is_array') === []) {
        $no_affiliation[] = $name;
      }
      if (!isset($related[(string) ($member['actor_id'] ?? '')])) {
        $no_relationship[] = $name;
      }
    }
    $list = static fn(array $names): string => implode(', ', array_slice($names, 0, 8)) . (count($names) > 8 ? sprintf(' and %d more', count($names) - 8) : '');
    $warnings = [];
    if ($no_affiliation !== []) {
      $warnings[] = sprintf(
        '%d of %d cast members are tied to no institution: %s. Define the story\'s institutions (clan, family, settlement, faction and the like) and give each cast member its affiliations.',
        count($no_affiliation), count($cast), $list($no_affiliation),
      );
    }
    $with_members = [];
    foreach ($cast as $member) {
      foreach (array_filter((array) ($member['affiliations'] ?? []), 'is_array') as $affiliation) {
        $with_members[(string) ($affiliation['institution_id'] ?? '')] = TRUE;
      }
    }
    $empty = [];
    foreach (array_filter((array) ($payload['objects']['institutions'] ?? []), 'is_array') as $institution) {
      if (!isset($with_members[(string) $institution['institution_id']])) {
        $empty[] = trim((string) $institution['name']);
      }
    }
    if ($empty !== []) {
      $warnings[] = sprintf('%d institution(s) have no cast member tied to them: %s. An institution without members is not created in play.', count($empty), $list($empty));
    }
    if ($no_relationship !== []) {
      $warnings[] = sprintf(
        '%d of %d cast members have no relationship with any other cast member: %s. Record how each regards the cast members it is tied to (spouse, parent, rival, serves).',
        count($no_relationship), count($cast), $list($no_relationship),
      );
    }
    return $warnings;
  }

  /**
   * Warns when the story's quest giver was invented for it.
   *
   * @return string[]
   */
  private function questGiverWarnings(array $draft): array {
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $entry = trim((string) ($payload['entry_actor_id'] ?? ''));
    if ($entry === '' || isset($this->libraryCharactersOlderThanDraft($draft)[$entry])) {
      return [];
    }
    foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $spec) {
      if (is_array($spec) && trim((string) ($spec['actor_id'] ?? '')) === $entry) {
        return [sprintf(
          "The story's quest giver, '%s', is a character new to this story. The library has %d characters who give quests; search it before keeping a new one.",
          trim((string) ($spec['display_name'] ?? '')) ?: $entry,
          $this->findLibraryCharacters('', TRUE, 1)['total'],
        )];
      }
    }
    return [];
  }

  /**
   * Builds one status projection shared by the GM and Storyline Builder UI.
   */
  public function summarizeDraftState(array $draft, array $validation): array {
      $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
      $process_flow = is_array($payload['process_flow'] ?? NULL) ? $payload['process_flow'] : [];
      $objects = is_array($payload['objects'] ?? NULL) ? $payload['objects'] : [];
      $narrative = $this->narrativeArchitectureStatus($process_flow['narrative_architecture'] ?? []);
      $rows = static fn(mixed $value): array => array_values(array_filter((array) $value, 'is_array'));
      $decision_choice_counts = [];
      foreach ($rows($process_flow['branch_choices'] ?? []) as $choice) {
        $decision_id = trim((string) ($choice['decision_id'] ?? ''));
        if ($decision_id !== '') {
          $decision_choice_counts[$decision_id] = ($decision_choice_counts[$decision_id] ?? 0) + 1;
        }
      }
      $counts = [
        'scenes' => count($rows($process_flow['journal_entries'] ?? [])),
        'tasks' => count($rows($process_flow['tasks'] ?? [])),
        'choices' => count($rows($process_flow['branch_choices'] ?? [])),
        'decisions' => count($decision_choice_counts),
        'objectives' => count($rows($process_flow['objective_completion_criteria'] ?? [])),
        'conflicts' => count($rows($process_flow['conflicts'] ?? [])),
        'actors' => count($rows($objects['actors']['generation_specs'] ?? [])),
        'dungeons' => count($rows($objects['dungeons']['generation_specs'] ?? [])),
        'rooms' => count($rows($objects['rooms']['generation_specs'] ?? [])),
        'items' => count($rows($objects['items']['generation_specs'] ?? [])),
        'narrative_beats_complete' => $narrative['beats_complete'],
        'adventure_requirements_complete' => $narrative['requirements_complete'],
      ];
      $all_have = static function (array $records, array $fields): bool {
        if ($records === []) {
          return FALSE;
        }
        foreach ($records as $record) {
          foreach ($fields as $field) {
            if (trim((string) ($record[$field] ?? '')) === '') {
              return FALSE;
            }
          }
        }
        return TRUE;
      };
      $checklist = [];
      $add = static function (string $id, string $label, bool $complete, string $group, string $detail = '') use (&$checklist): void {
        $checklist[] = compact('id', 'label', 'complete', 'group', 'detail');
      };
      foreach ([
        'storyline_id' => 'Storyline ID',
        'title' => 'Title',
        'description' => 'Description',
        'premise' => 'Premise',
        'player_hook' => 'Player hook',
        'ending' => 'Intended ending',
      ] as $field => $label) {
        $add("identity_{$field}", $label, trim((string) ($payload[$field] ?? '')) !== '', 'minimum_content');
      }
      foreach ([
        'scenes' => 'At least one scene',
        'tasks' => 'At least one task',
        'objectives' => 'At least one objective',
        'conflicts' => 'At least one conflict',
        'decisions' => 'At least one branching decision',
        'actors' => 'At least one actor binding',
        'dungeons' => 'At least one dungeon binding',
        'rooms' => 'At least one room binding',
      ] as $key => $label) {
        $add("content_{$key}", $label, $counts[$key] > 0, 'minimum_content', (string) $counts[$key]);
      }
      foreach ($narrative['beat_checks'] as $key => $complete) {
        $add("narrative_beat_{$key}", self::NARRATIVE_BEATS[$key]['label'], $complete, 'minimum_content');
      }
      foreach ($narrative['requirement_checks'] as $key => $complete) {
        $add("adventure_requirement_{$key}", self::ADVENTURE_REQUIREMENTS[$key], $complete, 'minimum_content');
      }
      $add(
        'content_room_bindings',
        'Every room has dungeon and scene bindings',
        $all_have($rows($objects['rooms']['generation_specs'] ?? []), ['room_id', 'dungeon_id', 'scene_id']),
        'minimum_content',
      );
      $actor_rows = $rows($objects['actors']['generation_specs'] ?? []);
      $actors_without_sheet = count(self::actorSheetFindings($payload));
      $sheets_complete = $actor_rows !== [] && $actors_without_sheet === 0;
      $add(
        'content_actor_sheets',
        'Every actor has a current character sheet',
        $sheets_complete,
        'minimum_content',
        sprintf('%d/%d', count($actor_rows) - $actors_without_sheet, count($actor_rows)),
      );
      $add(
        'canonical_validation',
        'Canonical validation passes',
        ($validation['valid'] ?? FALSE) === TRUE,
        'validation',
        sprintf('%d errors, %d warnings', (int) ($validation['error_count'] ?? 0), (int) ($validation['warning_count'] ?? 0)),
      );
      $stage_rows = [
        1 => [
          trim((string) ($payload['storyline_id'] ?? '')) !== '',
          trim((string) ($payload['title'] ?? '')) !== '',
          trim((string) ($payload['description'] ?? '')) !== '',
          trim((string) ($payload['premise'] ?? '')) !== '',
          trim((string) ($payload['player_hook'] ?? '')) !== '',
          trim((string) ($payload['ending'] ?? '')) !== '',
        ],
        2 => array_merge([
          $counts['scenes'] > 0,
          $counts['tasks'] > 0,
          $counts['objectives'] > 0,
          $counts['conflicts'] > 0,
          $counts['decisions'] > 0,
        ], array_values($narrative['beat_checks']), array_values($narrative['requirement_checks'])),
        3 => [
          $counts['actors'] > 0,
          $all_have($rows($objects['actors']['generation_specs'] ?? []), ['contact_id', 'actor_id', 'role', 'display_name']),
          $sheets_complete,
        ],
        4 => [
          $counts['dungeons'] > 0 && $counts['rooms'] > 0,
          $all_have($rows($objects['rooms']['generation_specs'] ?? []), ['room_id', 'dungeon_id', 'scene_id']),
        ],
        5 => [
          $counts['items'] > 0,
          $all_have($rows($objects['items']['generation_specs'] ?? []), ['item_id', 'purpose']),
        ],
        6 => [
          ($validation['valid'] ?? FALSE) === TRUE,
        ],
        7 => [
          ($validation['valid'] ?? FALSE) === TRUE,
          (string) ($draft['status'] ?? '') === 'published',
        ],
      ];
      $stage_labels = [
        1 => 'Identity and premise',
        2 => 'Progression and conflict',
        3 => 'Actors and relationships',
        4 => 'Dungeons and rooms',
        5 => 'Items and prerequisites',
        6 => 'Composition and validation',
        7 => 'Canonical review and creation',
      ];
      $stage_metrics = [
        1 => ['fields_complete' => count(array_filter(array_slice($stage_rows[1], 0, 6))), 'fields_total' => 6],
        2 => array_merge(
          array_intersect_key($counts, array_flip(['scenes', 'tasks', 'objectives', 'conflicts', 'decisions', 'choices'])),
          [
            'narrative_beats' => sprintf('%d/%d', $narrative['beats_complete'], $narrative['beats_total']),
            'adventure_requirements' => sprintf('%d/%d', $narrative['requirements_complete'], $narrative['requirements_total']),
          ],
        ),
        3 => [
          'actors' => $counts['actors'],
          'character_sheets' => sprintf('%d/%d', count($actor_rows) - $actors_without_sheet, count($actor_rows)),
        ],
        4 => ['dungeons' => $counts['dungeons'], 'rooms' => $counts['rooms']],
        5 => ['items' => $counts['items']],
        6 => ['errors' => (int) ($validation['error_count'] ?? 0), 'warnings' => (int) ($validation['warning_count'] ?? 0)],
        7 => ['canonical_valid' => ($validation['valid'] ?? FALSE) === TRUE],
      ];
      $stages = [];
      foreach ($stage_rows as $stage => $checks) {
        $done = count(array_filter($checks));
        $stages[] = [
          'stage' => $stage,
          'label' => $stage_labels[$stage],
          'completion_percent' => (int) round(($done / count($checks)) * 100),
          'complete' => $done === count($checks),
          'complete_checks' => $done,
          'total_checks' => count($checks),
          'metrics' => $stage_metrics[$stage],
        ];
      }
      $completed = count(array_filter($checklist, static fn(array $item): bool => $item['complete']));
      $completion_percent = (int) round(array_sum(array_column($stages, 'completion_percent')) / count($stages));
      $missing_content = array_values(array_map(
        static fn(array $item): string => $item['label'],
        array_filter($checklist, static fn(array $item): bool => $item['group'] === 'minimum_content' && !$item['complete']),
      ));
      $current_stage = 7;
      foreach ($stage_rows as $stage => $checks) {
        if ($stage <= 6 && count(array_filter($checks)) !== count($checks)) {
          $current_stage = $stage;
          break;
        }
      }
      $warning_count = (int) ($validation['warning_count'] ?? 0);

    $result = [
        'schema_version' => 'storyline-draft-status-v1',
        'draft_id' => (string) ($draft['draft_id'] ?? ''),
        'revision' => (int) ($draft['revision'] ?? 0),
        'status' => (string) ($draft['status'] ?? ''),
        'completion_percent' => $completion_percent,
        'completed_checks' => $completed,
        'total_checks' => count($checklist),
        'canonical_valid' => ($validation['valid'] ?? FALSE) === TRUE,
        'error_count' => (int) ($validation['error_count'] ?? 0),
        'warning_count' => (int) ($validation['warning_count'] ?? 0),
        'current_stage' => $current_stage,
        'counts' => $counts,
        'stages' => $stages,
        'missing_content' => $missing_content,
        'checklist' => $checklist,
    ];
    $result['next_steps'] = self::recommendNextAuthoringAction($result);
    return $result;
  }

  /**
   * Owns the single next author action shared by status and GM summaries.
   */
  public static function recommendNextAuthoringAction(array $state): array {
    if (($state['status'] ?? '') === 'published') {
      return ['Open the published storyline.'];
    }
    $missing = array_values(array_filter((array) ($state['missing_content'] ?? []), 'is_string'));
    if ($missing !== []) {
      return [match ($missing[0]) {
        'Storyline ID' => 'Have the GM prepare this draft for review.',
        'Title' => 'Choose a title for this storyline.',
        'Description' => 'Describe the adventure in one short paragraph.',
        'Premise' => 'Choose the central problem the heroes must solve.',
        'Player hook' => 'Choose how the heroes first become involved.',
        'Intended ending' => 'Choose one possible ending.',
        'At least one scene' => 'Have the GM develop the next scene.',
        'At least one task' => 'Have the GM develop one quest for the next scene.',
        'At least one objective' => 'Have the GM add one goal to the current quest.',
        'At least one conflict' => 'Choose the central opposition.',
        'At least one branching decision' => 'Have the GM add a meaningful choice to the next scene.',
        'At least one actor binding' => 'Have the GM develop the first story character.',
        'Every actor has a current character sheet' => 'Have the GM build the character sheets for the roster.',
        'At least one dungeon binding' => 'Have the GM develop the first adventure location.',
        'At least one room binding' => 'Have the GM develop one room for that location.',
        'Every room has dungeon and scene bindings' => 'Have the GM connect the next unbound room to its location.',
        default => 'Develop the next missing story element: ' . trim($missing[0]) . '.',
      }];
    }
    if (($state['error_count'] ?? 0) > 0) {
      return ['Have the GM address the next validation finding.'];
    }
    if (($state['warning_count'] ?? 0) > 0) {
      return ['Have the GM address the next validation warning.'];
    }
    return ['Review the updated storyline.'];
  }

  /**
   * Persist one draft revision atomically by expected revision.
   */
  public function saveDraft(string $draft_id, int $expected_revision, array $payload): array {
    $draft = $this->getDraft($draft_id);
    if (($draft['status'] ?? '') !== 'editing') {
      throw new \DomainException('storyline_draft_not_editable');
    }
    $current_revision = (int) ($draft['revision'] ?? 0);
    if ($expected_revision !== $current_revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    if (array_key_exists('canonical_template', $payload) && $payload['canonical_template'] !== ($draft['payload']['canonical_template'] ?? NULL)) {
      throw new \InvalidArgumentException('storyline_draft_canonical_source_immutable');
    }
    if (is_array($draft['payload']['canonical_template'] ?? NULL)) {
      $payload['canonical_template'] = $draft['payload']['canonical_template'];
    }
    // Every save goes through here, the builder form included.
    $payload = self::rebindChangedIdentities($draft['payload'] ?? [], self::withInstitutionSubjectIds($payload));
    self::assertRemovedPlacesAreUnreferenced($draft['payload'] ?? [], $payload);
    $this->assertDraftPayloadValid($payload);
    $updated_at = $this->time->getRequestTime();
    $next_revision = $current_revision + 1;
    $payload_json = $this->encodePayload($payload);
    $payload_hash = hash('sha256', $payload_json);
    $transaction = $this->database->startTransaction();
    $affected = $this->database->update(self::TABLE)
      ->fields([
        'revision' => $next_revision,
        'updated_at' => $updated_at,
        'draft_payload' => $payload_json,
        'payload_hash' => $payload_hash,
      ])
      ->condition('draft_id', $draft_id)
      ->condition('owner_uid', (int) $draft['owner_uid'])
      ->condition('revision', $current_revision)
      ->execute();
    if ($affected !== 1) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    $this->insertRevisionSnapshot(
      $draft_id,
      $next_revision,
      (int) $this->currentUser->id(),
      $payload_json,
      $payload_hash,
      $updated_at,
    );
    return $this->getDraft($draft_id);
  }

  /**
   * Plans evidence-backed site links without changing the saved draft.
   */
  public function planRoomDungeonBindings(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
    $resolution = $this->storylineManager->resolveAuthoredRoomDungeonBindings(
      $draft['payload']['objects']['rooms']['generation_specs'] ?? [],
      $draft['payload']['objects']['dungeons']['generation_specs'] ?? [],
    );
    $operations = [];
    foreach ($resolution['resolved'] as $binding) {
      $operations[] = [
        'type' => 'set',
        'path' => '/objects/rooms/generation_specs/' . $binding['index'] . '/dungeon_id',
        'value' => $binding['dungeon_id'],
      ];
    }
    return [
      'expected_revision' => (int) $draft['revision'],
      'resolved' => $resolution['resolved'],
      'unresolved' => $resolution['unresolved'],
      'operations' => $operations,
    ];
  }

  /**
   * Plans a revision-bound operation batch without changing stored state.
   */
  public function planDraftOperations(string $draft_id, int $expected_revision, array $operations): array {
    $draft = $this->getDraft($draft_id);
    if (($draft['status'] ?? '') !== 'editing') {
      throw new \DomainException('storyline_draft_not_editable');
    }
    if ($expected_revision !== (int) ($draft['revision'] ?? 0)) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    if ($operations === [] || count($operations) > 100) {
      throw new \InvalidArgumentException('storyline_draft_operations_count_invalid');
    }

    $before = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    [$operations, $allocated_ids] = $this->prepareOperationIdentities($draft_id, $before, array_values($operations));
    $after = $before;
    $changes = [];
    foreach (array_values($operations) as $index => $operation) {
      if (!is_array($operation)) {
        throw new \InvalidArgumentException(sprintf('storyline_draft_operation_invalid:%d', $index));
      }
      $change = $this->applyDraftOperation($after, $operation, $index);
      if ($change !== NULL) {
        $changes[] = $change;
      }
    }
    if (($after['storyline_id'] ?? '') === '') {
      $change = $this->applyDraftOperation($after, [
        'type' => 'set',
        'path' => '/storyline_id',
        'value' => 'storyline-' . $draft_id,
      ], count($operations));
      $changes[] = $change;
      $allocated_ids[] = ['operation_index' => count($operations), 'field' => 'storyline_id', 'id' => $after['storyline_id']];
    }
    if (($after['canonical_template'] ?? NULL) !== ($before['canonical_template'] ?? NULL)) {
      throw new \InvalidArgumentException('storyline_draft_canonical_source_immutable');
    }
    $after = self::rebindChangedIdentities($before, self::withInstitutionSubjectIds($after));
    self::assertRemovedPlacesAreUnreferenced($before, $after);
    $this->assertDraftPayloadValid($after);

    return [
      'draft_id' => $draft_id,
      'expected_revision' => $expected_revision,
      'result_revision' => $expected_revision + ($changes === [] ? 0 : 1),
      'has_changes' => $changes !== [],
      'changes' => $changes,
      'allocated_ids' => $allocated_ids,
      'payload' => $after,
    ];
  }

  /**
   * A room appears in a scene of this storyline.
   *
   * @return array<int, array<string, mixed>>
   */
  private static function roomSceneFindings(array $payload): array {
    $scenes = array_column(array_filter((array) ($payload['process_flow']['journal_entries'] ?? []), 'is_array'), 'title', 'scene_id');
    $findings = [];
    foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $room) {
      $room_id = is_array($room) ? trim((string) ($room['room_id'] ?? '')) : '';
      $scene_id = is_array($room) ? trim((string) ($room['scene_id'] ?? '')) : '';
      if ($room_id !== '' && $scene_id !== '' && !isset($scenes[$scene_id])) {
        $findings[] = [
          'severity' => 'error',
          'code' => 'room_scene_unknown',
          'room_id' => $room_id,
          'scene_id' => $scene_id,
          'message' => sprintf("Room '%s' is bound to a scene that is not in this storyline. Bind it to the scene it appears in.", $room_id),
        ];
      }
    }
    return $findings;
  }

  /**
   * Row identities whose change is carried to every reference.
   *
   * A room row is one appearance of a room in a scene, so several rows may
   * share a room_id; the room is renamed only when no row keeps the old ID.
   */
  private const CASCADING_IDENTITIES = [
    '/objects/actors/generation_specs' => ['actor_id', 'contact_id'],
    '/objects/rooms/generation_specs' => ['room_id'],
    '/objects/dungeons/generation_specs' => ['dungeon_id'],
    '/objects/institutions' => ['institution_id'],
  ];

  /**
   * Carries a changed actor, room, dungeon or institution identity to every
   * reference.
   *
   * When a save changes the ID of a row, the row is the same thing under a
   * new identity: every scene, choice, objective, placement and binding that
   * named the old ID must name the new one in the same save. An identity is
   * never left half changed.
   */
  /**
   * The institution engine's subject ID for a draft institution row.
   *
   * The same domain and name is the same institution in every storyline and
   * every campaign, so a draft uses the ID play will use.
   */
  private static function institutionSubjectId(array $institution): string {
    $normalizer = new InstitutionNormalizationService();
    $normalized = $normalizer->normalizeInstitutionInput([
      'domain' => (string) ($institution['domain'] ?? ''),
      'display_name' => (string) ($institution['name'] ?? ''),
    ]);
    return $normalizer->buildInstitutionSubjectId($normalized['domain'], $normalized['normalized_label']);
  }

  /**
   * Keeps every institution's ID equal to its domain and name.
   *
   * Renaming an institution therefore changes its ID; the identity cascade
   * carries the new ID to every affiliation, stance and parent.
   */
  private static function withInstitutionSubjectIds(array $payload): array {
    if (!is_array($payload['objects']['institutions'] ?? NULL)) {
      return $payload;
    }
    foreach ($payload['objects']['institutions'] as $index => $institution) {
      if (is_array($institution) && trim((string) ($institution['domain'] ?? '')) !== '' && trim((string) ($institution['name'] ?? '')) !== '') {
        $payload['objects']['institutions'][$index]['institution_id'] = self::institutionSubjectId($institution);
      }
    }
    return $payload;
  }

  private static function rebindChangedIdentities(array $before, array $after): array {
    $renamed = [];
    foreach (self::CASCADING_IDENTITIES as $path => $fields) {
      $rows_at = static function (array $payload) use ($path): array {
        $value = $payload;
        foreach (array_slice(explode('/', $path), 1) as $segment) {
          $value = is_array($value) ? ($value[$segment] ?? []) : [];
        }
        return array_values(array_filter((array) $value, 'is_array'));
      };
      $old_rows = $rows_at($before);
      $new_rows = $rows_at($after);
      if (count($old_rows) !== count($new_rows)) {
        // Rows were added or removed; positions no longer pair up.
        continue;
      }
      $still_used = [];
      foreach ($new_rows as $row) {
        foreach ($fields as $field) {
          $still_used[trim((string) ($row[$field] ?? ''))] = TRUE;
        }
      }
      foreach ($old_rows as $index => $old_row) {
        foreach ($fields as $field) {
          $old = trim((string) ($old_row[$field] ?? ''));
          $new = trim((string) ($new_rows[$index][$field] ?? ''));
          if ($old !== '' && $new !== '' && $old !== $new && !isset($still_used[$old])) {
            if (isset($renamed[$old]) && $renamed[$old] !== $new) {
              throw new \DomainException('storyline_draft_identity_change_ambiguous:' . $old);
            }
            $renamed[$old] = $new;
          }
        }
      }
    }
    if ($renamed === []) {
      return $after;
    }
    $rebind = static function (mixed $value) use (&$rebind, $renamed): mixed {
      if (is_array($value)) {
        return array_map($rebind, $value);
      }
      if (!is_string($value)) {
        return $value;
      }
      if (isset($renamed[$value])) {
        return $renamed[$value];
      }
      // Comma-separated ID lists, such as a scene's characters.
      if (str_contains($value, ',') && preg_match('/^[A-Za-z0-9:_,\s-]+$/', $value) === 1) {
        $parts = explode(',', $value);
        foreach ($parts as $position => $part) {
          $parts[$position] = isset($renamed[trim($part)]) ? str_replace(trim($part), $renamed[trim($part)], $part) : $part;
        }
        return implode(',', $parts);
      }
      return $value;
    };
    foreach ($after as $key => $value) {
      if ($key !== 'canonical_template') {
        $after[$key] = $rebind($value);
      }
    }
    return $after;
  }

  /**
   * A save may not remove a room or site that something still stands on.
   *
   * Only what this save removes is checked: a reference that was already
   * stale before it is reported by validation, not here.
   */
  private static function assertRemovedPlacesAreUnreferenced(array $before, array $after): void {
    $places = static function (array $payload): array {
      $ids = [];
      foreach ((array) ($payload['objects']['rooms']['generation_specs'] ?? []) as $room) {
        $ids[trim((string) ($room['room_id'] ?? ''))] = 'room';
      }
      foreach ((array) ($payload['objects']['dungeons']['generation_specs'] ?? []) as $dungeon) {
        $ids[trim((string) ($dungeon['dungeon_id'] ?? ''))] = 'site';
      }
      unset($ids['']);
      return $ids;
    };
    $removed = array_diff_key($places($before), $places($after));
    if ($removed === []) {
      return;
    }
    $users = [];
    foreach ((array) ($after['objects']['actors']['generation_specs'] ?? []) as $actor) {
      $place = trim((string) ($actor['location_id'] ?? ''));
      if (isset($removed[$place])) {
        $users[$place][] = 'actor ' . (trim((string) ($actor['display_name'] ?? '')) ?: (string) ($actor['actor_id'] ?? ''));
      }
    }
    foreach ((array) ($after['objects']['rooms']['generation_specs'] ?? []) as $room) {
      $place = trim((string) ($room['dungeon_id'] ?? ''));
      if (isset($removed[$place])) {
        $users[$place][] = 'room ' . trim((string) ($room['room_id'] ?? ''));
      }
    }
    foreach ((array) ($after['objects']['dungeons']['generation_specs'] ?? []) as $dungeon) {
      $place = trim((string) ($dungeon['entrance_room'] ?? ''));
      if (isset($removed[$place])) {
        $users[$place][] = 'entrance of site ' . trim((string) ($dungeon['dungeon_id'] ?? ''));
      }
    }
    foreach ((array) ($after['process_flow']['objective_completion_criteria'] ?? []) as $objective) {
      foreach (['location', 'destination'] as $field) {
        $place = trim((string) ($objective[$field] ?? ''));
        if (isset($removed[$place])) {
          $users[$place][] = 'objective ' . trim((string) ($objective['objective_id'] ?? ''));
        }
      }
    }
    if ($users !== []) {
      $place = (string) array_key_first($users);
      throw new \DomainException(sprintf(
        "storyline_draft_place_still_in_use: %s '%s' is removed by this change but is still used by %s. Move or remove those in the same change.",
        $removed[$place],
        $place,
        implode(', ', array_slice(array_unique($users[$place]), 0, 6)),
      ));
    }
  }

  /**
   * Allocates new row identities and resolves batch links before validation.
   */
  private function prepareOperationIdentities(string $draft_id, array $payload, array $operations): array {
    $used = [];
    $collect = static function (array $value) use (&$collect, &$used): void {
      foreach ($value as $field => $item) {
        if (is_array($item)) {
          $collect($item);
        }
        elseif (is_string($field) && str_ends_with($field, '_id') && is_string($item) && $item !== '') {
          $used[$item] = TRUE;
        }
      }
    };
    $collect($payload);
    $collect($operations);
    $allocate = function (string $kind, string $seed) use ($draft_id, &$used): string {
      $base = substr($this->sanitizeIdentifier('draft-' . $draft_id . '-' . $kind . '-' . $seed), 0, 110);
      $id = $base;
      for ($suffix = 2; isset($used[$id]); $suffix++) {
        $id = $base . '-' . $suffix;
      }
      $used[$id] = TRUE;
      return $id;
    };
    $decisions = [];
    $batch_decisions = [];
    foreach ($payload['process_flow']['branch_choices'] ?? [] as $choice) {
      if (is_string($choice['source_task_id'] ?? NULL) && is_string($choice['decision_id'] ?? NULL) && $choice['decision_id'] !== '') {
        $decisions[json_encode($choice['source_task_id'], JSON_THROW_ON_ERROR)][$choice['decision_id']] = TRUE;
      }
    }
    foreach ($operations as $operation) {
      if (is_array($operation) && ($operation['type'] ?? '') === 'append'
        && ($operation['path'] ?? '') === '/process_flow/branch_choices'
        && is_string($operation['value']['decision_id'] ?? NULL) && $operation['value']['decision_id'] !== '') {
        $source = json_encode($operation['value']['source_task_id'] ?? '', JSON_THROW_ON_ERROR);
        $decisions[$source][$operation['value']['decision_id']] = TRUE;
      }
    }
    $symbols = [];
    $allocated = [];
    foreach ($operations as $index => &$operation) {
      if (!is_array($operation) || ($operation['type'] ?? '') !== 'append') {
        continue;
      }
      $path = $operation['path'] ?? '';
      if (!is_string($path) || !isset(self::GENERATED_ID_FIELDS[$path])
        || !is_array($operation['value'] ?? NULL) || array_is_list($operation['value'])) {
        continue;
      }
      $key = $operation['key'] ?? NULL;
      if ($key !== NULL) {
        if (!is_string($key) || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $key) !== 1 || isset($symbols[$key])) {
          throw new \InvalidArgumentException('storyline_draft_batch_key_invalid_or_duplicate:' . $index);
        }
        $symbols[$key] = $index;
      }
      $row =& $operation['value'];
      foreach (self::GENERATED_ID_FIELDS[$path] as $field => $kind) {
        if ($path === '/objects/institutions') {
          // An institution's identity is its domain and name, as in play.
          $row[$field] = self::institutionSubjectId($row);
          continue;
        }
        if (array_key_exists($field, $row) && $row[$field] !== '') {
          continue;
        }
        $seed = $row['display_name'] ?? $row['title'] ?? $row['label'] ?? $row['goal_text'] ?? $row['purpose'] ?? $row['description'] ?? $key ?? $kind;
        if (!is_string($seed)) {
          throw new \InvalidArgumentException('storyline_draft_identity_seed_invalid:' . $index);
        }
        if ($field === 'decision_id' && trim((string) ($row['source_task_id'] ?? '')) === '') {
          // Storyline-wide choices are independent conditions: resolving one
          // must not close the others, so each is its own decision.
          $row[$field] = $allocate($kind, $seed);
        }
        elseif ($field === 'decision_id') {
          $source = json_encode($row['source_task_id'] ?? '', JSON_THROW_ON_ERROR);
          if (isset($batch_decisions[$source])) {
            $row[$field] = $batch_decisions[$source];
          }
          elseif (count($decisions[$source] ?? []) === 1) {
            $row[$field] = (string) array_key_first($decisions[$source]);
          }
          else {
            $row[$field] = $allocate($kind, substr(hash('sha256', $source), 0, 16));
          }
          $batch_decisions[$source] = $row[$field];
        }
        else {
          $row[$field] = $allocate($kind, $seed);
        }
        $allocated[] = ['operation_index' => $index, 'field' => $field, 'id' => $row[$field]];
      }
      unset($row);
    }
    unset($operation);

    $resolve = static function (mixed $value, array $trail = []) use (&$resolve, &$operations, $symbols): mixed {
      if (!is_array($value)) {
        return $value;
      }
      if (array_key_exists('$draft_ref', $value)) {
        $ref = $value['$draft_ref'];
        if (count($value) !== 1 || !is_string($ref)
          || preg_match('/^([a-zA-Z][a-zA-Z0-9_-]{0,63})\\.([a-z_]+_id)$/D', $ref, $match) !== 1
          || !isset($symbols[$match[1]]) || isset($trail[$ref])) {
          throw new \InvalidArgumentException('storyline_draft_batch_reference_invalid');
        }
        $trail[$ref] = TRUE;
        $target = $operations[$symbols[$match[1]]]['value'][$match[2]] ?? NULL;
        $resolved = $resolve($target, $trail);
        if (!is_string($resolved) || trim($resolved) === '') {
          throw new \InvalidArgumentException('storyline_draft_batch_reference_target_invalid:' . $ref);
        }
        return $resolved;
      }
      foreach ($value as &$item) {
        $item = $resolve($item, $trail);
      }
      unset($item);
      return $value;
    };
    foreach ($operations as $index => $operation) {
      if (is_array($operation) && array_key_exists('value', $operation)) {
        $operations[$index]['value'] = $resolve($operation['value']);
      }
    }
    return [$operations, $allocated];
  }

  /**
   * Hard-fail before an invalid authoring payload can reach draft storage.
   */
  private function assertDraftPayloadValid(array $payload): void {
    $this->draftValidator ??= new DefinitionSchemaValidator();
    $findings = $this->draftValidator->validate(self::loadAuthoringSchema(), $payload);
    if ($findings !== []) {
      throw new StorylineDraftValidationException('storyline_draft_schema_invalid', $findings);
    }
    $this->assertInstitutionsValid($payload);
    $this->assertActorRelationshipsValid($payload);
  }

  /**
   * The storyline's institutions and the cast's ties to them hold together.
   *
   * @throws \InvalidArgumentException
   *   `storyline_draft_institution_duplicate:<id>`,
   *   `storyline_draft_institution_name_duplicate:<domain>:<name>`,
   *   `storyline_draft_institution_domain_unsupported:<id>:<domain>`,
   *   `storyline_draft_institution_parent_unknown:<id>:<parent>`,
   *   `storyline_draft_institution_parent_cycle:<id>`,
   *   `storyline_draft_institution_stance_unknown:<id>:<toward>`,
   *   `storyline_draft_institution_stance_self:<id>`,
   *   `storyline_draft_actor_affiliation_institution_unknown:<actor>:<id>`,
   *   `storyline_draft_actor_affiliation_duplicate:<actor>:<id>` or
   *   `storyline_draft_actor_affiliation_single:<actor>:<domain>`.
   */
  private function assertInstitutionsValid(array $payload): void {
    $normalizer = new InstitutionNormalizationService();
    $institutions = [];
    $names = [];
    foreach ((array) ($payload['objects']['institutions'] ?? []) as $institution) {
      $id = (string) $institution['institution_id'];
      $domain = (string) $institution['domain'];
      if (isset($institutions[$id])) {
        throw new \InvalidArgumentException('storyline_draft_institution_duplicate:' . $id);
      }
      // The schema's list must stay the engine's list.
      if (!isset(InstitutionNormalizationService::AUTHORABLE_DOMAINS[$domain]) || $normalizer->normalizeDomain($domain) !== $domain) {
        throw new \InvalidArgumentException('storyline_draft_institution_domain_unsupported:' . $id . ':' . $domain);
      }
      $name_key = $domain . ':' . mb_strtolower(trim((string) $institution['name']));
      if (isset($names[$name_key])) {
        throw new \InvalidArgumentException('storyline_draft_institution_name_duplicate:' . $name_key);
      }
      $names[$name_key] = TRUE;
      $institutions[$id] = $institution;
    }
    foreach ($institutions as $id => $institution) {
      // A part belongs to one whole, and no institution contains itself.
      $ancestor = trim((string) ($institution['parent_institution_id'] ?? ''));
      $visited = [$id => TRUE];
      while ($ancestor !== '') {
        if (!isset($institutions[$ancestor])) {
          throw new \InvalidArgumentException('storyline_draft_institution_parent_unknown:' . $id . ':' . $ancestor);
        }
        if (isset($visited[$ancestor])) {
          throw new \InvalidArgumentException('storyline_draft_institution_parent_cycle:' . $id);
        }
        $visited[$ancestor] = TRUE;
        $ancestor = trim((string) ($institutions[$ancestor]['parent_institution_id'] ?? ''));
      }
      foreach ((array) ($institution['stances'] ?? []) as $stance) {
        $toward = (string) $stance['toward_institution_id'];
        if ($toward === $id) {
          throw new \InvalidArgumentException('storyline_draft_institution_stance_self:' . $id);
        }
        if (!isset($institutions[$toward])) {
          throw new \InvalidArgumentException('storyline_draft_institution_stance_unknown:' . $id . ':' . $toward);
        }
      }
    }
    // An actor has one home settlement and one state.
    $single = InstitutionMembershipService::singleMembershipDomains();
    foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $actor) {
      $seen = [];
      foreach ((array) ($actor['affiliations'] ?? []) as $affiliation) {
        $id = (string) $affiliation['institution_id'];
        if (!isset($institutions[$id])) {
          throw new \InvalidArgumentException('storyline_draft_actor_affiliation_institution_unknown:' . $actor['actor_id'] . ':' . $id);
        }
        if (isset($seen[$id])) {
          throw new \InvalidArgumentException('storyline_draft_actor_affiliation_duplicate:' . $actor['actor_id'] . ':' . $id);
        }
        $seen[$id] = TRUE;
        $domain = (string) $institutions[$id]['domain'];
        if (self::belongs($affiliation) && in_array($domain, $single, TRUE)) {
          if (isset($seen['domain:' . $domain])) {
            throw new \InvalidArgumentException('storyline_draft_actor_affiliation_single:' . $actor['actor_id'] . ':' . $domain);
          }
          $seen['domain:' . $domain] = TRUE;
        }
      }
    }
  }

  /**
   * Whether an affiliation makes the actor a member of the institution.
   */
  private static function belongs(array $affiliation): bool {
    return in_array((string) ($affiliation['standing'] ?? 'member'), ['member', 'leader'], TRUE);
  }

  /**
   * An actor's memberships as the institution engine's structured
   * selections: one institution per storyline, domain and name. Exiles and
   * allies are tied to an institution without belonging to it.
   */
  private function contactAffiliations(array $actor, array $payload): array {
    $storyline_id = (string) ($payload['storyline_id'] ?? '');
    $institutions = array_column((array) ($payload['objects']['institutions'] ?? []), NULL, 'institution_id');
    $normalizer = new InstitutionNormalizationService();
    $selections = [];
    foreach ((array) ($actor['affiliations'] ?? []) as $affiliation) {
      $institution = $institutions[(string) $affiliation['institution_id']];
      if (!self::belongs($affiliation)) {
        continue;
      }
      $normalized = $normalizer->normalizeInstitutionInput(['domain' => $institution['domain'], 'display_name' => trim((string) $institution['name'])]);
      $selections[] = [
        'domain' => $normalized['domain'],
        'display_name' => $normalized['display_name'],
        'source_asset_type' => 'storyline',
        'source_asset_id' => $storyline_id . ':' . $normalized['domain'] . ':' . $normalized['normalized_label'],
      ];
    }

    return $selections;
  }

  /**
   * Every actor relationship joins two different actors of this draft, once.
   *
   * @throws \InvalidArgumentException
   *   `storyline_draft_actor_relationship_actor_unknown:<id>`,
   *   `storyline_draft_actor_relationship_self:<id>` or
   *   `storyline_draft_actor_relationship_duplicate:<source>:<target>`.
   */
  private function assertActorRelationshipsValid(array $payload): void {
    $actor_ids = array_column((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'actor_id');
    $seen = [];
    foreach ((array) ($payload['objects']['actors']['relationships'] ?? []) as $relationship) {
      foreach (['source_actor_id', 'target_actor_id'] as $end) {
        if (!in_array($relationship[$end], $actor_ids, TRUE)) {
          throw new \InvalidArgumentException('storyline_draft_actor_relationship_actor_unknown:' . $relationship[$end]);
        }
      }
      if ($relationship['source_actor_id'] === $relationship['target_actor_id']) {
        throw new \InvalidArgumentException('storyline_draft_actor_relationship_self:' . $relationship['source_actor_id']);
      }
      // One actor holds one attitude toward another.
      $key = $relationship['source_actor_id'] . ':' . $relationship['target_actor_id'];
      if (isset($seen[$key])) {
        throw new \InvalidArgumentException('storyline_draft_actor_relationship_duplicate:' . $key);
      }
      $seen[$key] = TRUE;
    }
  }

  /**
   * A contact's outgoing relationships in the published definition shape.
   */
  private function contactRelationships(string $actor_id, array $payload): array {
    $names = array_column((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'display_name', 'actor_id');
    $links = [];
    foreach ((array) ($payload['objects']['actors']['relationships'] ?? []) as $relationship) {
      if ($relationship['source_actor_id'] === $actor_id) {
        $links[] = [
          'entity_type' => 'campaign_npc',
          'entity_id' => $relationship['target_actor_id'],
          // The relationship engine's actor-to-actor edge.
          'relationship_type' => 'knows',
          'attitude' => $relationship['attitude'],
          'display_name' => (string) ($names[$relationship['target_actor_id']] ?? $relationship['target_actor_id']),
          'notes' => (string) ($relationship['notes'] ?? ''),
          'relationship_state' => [
            'authored_in' => 'storyline_actor_relationships',
            'bond' => trim((string) ($relationship['bond'] ?? '')),
          ],
        ];
      }
    }

    return $links;
  }

  /**
   * Load the single authoring schema used by UI, GM grounding, and storage.
   */
  private static function loadAuthoringSchema(): array {
    $path = dirname(__DIR__, 2) . '/config/schemas/storyline_authoring_draft.schema.json';
    $schema = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
    if (!is_array($schema)) {
      throw new \RuntimeException('storyline_authoring_schema_missing');
    }
    return $schema;
  }

  /**
   * Applies one revision-bound operation batch exactly once.
   */
  public function applyDraftOperations(
    string $draft_id,
    int $expected_revision,
    string $operation_id,
    array $operations,
  ): array {
    $this->assertCanEdit();
    $operation_id = trim($operation_id);
    if (
      $operation_id === ''
      || strlen($operation_id) > 64
      || preg_match('/^[A-Za-z0-9._:-]+$/', $operation_id) !== 1
    ) {
      throw new \InvalidArgumentException('storyline_draft_operation_id_invalid');
    }
    $request_hash = hash('sha256', $this->encodePayload([
      'expected_revision' => $expected_revision,
      'operations' => array_values($operations),
    ]));
    $receipt = $this->loadOperationReceipt($draft_id, $operation_id);
    if ($receipt !== NULL) {
      return $this->replayOperationReceipt($receipt, $request_hash);
    }

    $transaction = $this->database->startTransaction();
    try {
      $plan = $this->planDraftOperations($draft_id, $expected_revision, $operations);
      if ($plan['has_changes']) {
        $saved = $this->saveDraft($draft_id, $expected_revision, $plan['payload']);
        $result_revision = (int) $saved['revision'];
      }
      else {
        $saved = $this->getDraft($draft_id);
        $result_revision = (int) $saved['revision'];
      }
      $result = [
        'draft_id' => $draft_id,
        'operation_id' => $operation_id,
        'base_revision' => $expected_revision,
        'result_revision' => $result_revision,
        'changed' => (bool) $plan['has_changes'],
        'idempotent_replay' => FALSE,
        'changes' => $plan['changes'],
        'allocated_ids' => $plan['allocated_ids'],
        'saved' => $saved,
      ];
      $this->database->insert(self::OPERATION_TABLE)
        ->fields([
          'operation_id' => $operation_id,
          'draft_id' => $draft_id,
          'owner_uid' => (int) $this->currentUser->id(),
          'base_revision' => $expected_revision,
          'result_revision' => $result_revision,
          'request_hash' => $request_hash,
          'response_payload' => $this->encodePayload($result),
          'created_at' => $this->time->getRequestTime(),
        ])
        ->execute();
      return $result;
    }
    catch (\Drupal\Core\Database\IntegrityConstraintViolationException $exception) {
      $transaction->rollBack();
      $receipt = $this->loadOperationReceipt($draft_id, $operation_id);
      if ($receipt === NULL) {
        throw $exception;
      }
      return $this->replayOperationReceipt($receipt, $request_hash);
    }
  }

  /**
   * Applies one validated operation to an in-memory payload.
   */
  private function applyDraftOperation(array &$payload, array $operation, int $index): ?array {
    $type = trim((string) ($operation['type'] ?? ''));
    $path = trim((string) ($operation['path'] ?? ''));
    if (!array_key_exists($type, self::OPERATION_CONTRACT)) {
      throw new \InvalidArgumentException(sprintf('storyline_draft_operation_type_invalid:%d', $index));
    }
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '/canonical_template')) {
      throw new \InvalidArgumentException(sprintf('storyline_draft_operation_path_invalid:%d', $index));
    }

    if ($type === 'set') {
      if (!array_key_exists('value', $operation)) {
        throw new \InvalidArgumentException(sprintf('storyline_draft_operation_value_required:%d', $index));
      }
      $before = $this->pointerValue($payload, $path, FALSE);
      $this->setPointerValue($payload, $path, $operation['value'], TRUE);
      return $before === $operation['value'] ? NULL : [
        'type' => $type,
        'path' => $path,
        'before' => $before,
        'after' => $operation['value'],
      ];
    }

    if ($type === 'append') {
      $this->initializeOptionalCollectionForAppend($payload, $path);
    }
    $collection =& $this->pointerReference($payload, $path);
    if (!array_is_list($collection)) {
      throw new \InvalidArgumentException(sprintf('storyline_draft_operation_collection_required:%d', $index));
    }
    if ($type === 'append') {
      if (!array_key_exists('value', $operation)) {
        throw new \InvalidArgumentException(sprintf('storyline_draft_operation_value_required:%d', $index));
      }
      $collection[] = $operation['value'];
      return [
        'type' => $type,
        'path' => $path . '/' . (count($collection) - 1),
        'before' => NULL,
        'after' => $operation['value'],
      ];
    }

    $match = is_array($operation['match'] ?? NULL) ? $operation['match'] : [];
    $match_fields = $this->normalizeCollectionMatch($match);
    if ($match_fields === []) {
      throw new \InvalidArgumentException(sprintf('storyline_draft_operation_match_required:%d', $index));
    }
    $row_index = $this->findCollectionRow($collection, $match_fields, $index);
    $before = $collection[$row_index];
    if ($type === 'patch') {
      $fields = $operation['value'] ?? NULL;
      if (!is_array($fields) || array_is_list($fields) || !is_array($before) || array_is_list($before)) {
        throw new \InvalidArgumentException(sprintf('storyline_draft_operation_patch_object_required:%d', $index));
      }
      $after = array_replace($before, $fields);
      $collection[$row_index] = $after;
      return $before === $after ? NULL : [
        'type' => $type,
        'path' => $path . '/' . $row_index,
        'before' => $before,
        'after' => $after,
      ];
    }
    if ($type === 'replace') {
      if (!array_key_exists('value', $operation)) {
        throw new \InvalidArgumentException(sprintf('storyline_draft_operation_value_required:%d', $index));
      }
      $collection[$row_index] = $operation['value'];
      return $before === $operation['value'] ? NULL : [
        'type' => $type,
        'path' => $path . '/' . $row_index,
        'before' => $before,
        'after' => $operation['value'],
      ];
    }
    if ($type === 'remove') {
      array_splice($collection, $row_index, 1);
      return [
        'type' => $type,
        'path' => $path . '/' . $row_index,
        'before' => $before,
        'after' => NULL,
      ];
    }

    $to_index = $operation['to_index'] ?? NULL;
    if (!is_int($to_index) || $to_index < 0 || $to_index >= count($collection)) {
      throw new \InvalidArgumentException(sprintf('storyline_draft_operation_destination_invalid:%d', $index));
    }
    if ($to_index === $row_index) {
      return NULL;
    }
    array_splice($collection, $row_index, 1);
    array_splice($collection, $to_index, 0, [$before]);
    return [
      'type' => $type,
      'path' => $path,
      'identity' => $match_fields,
      'before_index' => $row_index,
      'after_index' => $to_index,
    ];
  }

  /**
   * Returns a JSON-pointer value or NULL when a set target is absent.
   */
  private function pointerValue(array $payload, string $path, bool $required = TRUE): mixed {
    $segments = $this->pointerSegments($path);
    $value = $payload;
    foreach ($segments as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
        if (!$required) {
          return NULL;
        }
        throw new \InvalidArgumentException('storyline_draft_operation_path_not_found');
      }
      $value = $value[$segment];
    }
    return $value;
  }

  /**
   * Returns a writable JSON-pointer reference.
   */
  private function &pointerReference(array &$payload, string $path): mixed {
    $segments = $this->pointerSegments($path);
    $value =& $payload;
    foreach ($segments as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) {
        throw new \InvalidArgumentException('storyline_draft_operation_path_not_found');
      }
      $value =& $value[$segment];
    }
    return $value;
  }

  /**
   * Initializes an omitted schema-defined collection before its first append.
   */
  private function initializeOptionalCollectionForAppend(array &$payload, string $path): void {
    if (!array_key_exists($path, self::collectionAuthoringContract())) {
      return;
    }
    $segments = $this->pointerSegments($path);
    $key = array_pop($segments);
    $parent =& $payload;
    foreach ($segments as $segment) {
      if (!is_array($parent) || !array_key_exists($segment, $parent)) {
        throw new \InvalidArgumentException('storyline_draft_operation_path_not_found');
      }
      $parent =& $parent[$segment];
    }
    if (!is_array($parent)) {
      throw new \InvalidArgumentException('storyline_draft_operation_path_not_found');
    }
    if (!array_key_exists($key, $parent)) {
      $parent[$key] = [];
    }
  }

  /**
   * Sets a JSON-pointer value while requiring its parent to exist.
   */
  private function setPointerValue(array &$payload, string $path, mixed $new_value, bool $allow_new): void {
    $segments = $this->pointerSegments($path);
    $key = array_pop($segments);
    $parent =& $payload;
    foreach ($segments as $segment) {
      if (!is_array($parent) || !array_key_exists($segment, $parent)) {
        throw new \InvalidArgumentException('storyline_draft_operation_path_not_found');
      }
      $parent =& $parent[$segment];
    }
    if (!is_array($parent) || (!$allow_new && !array_key_exists($key, $parent))) {
      throw new \InvalidArgumentException('storyline_draft_operation_path_not_found');
    }
    $parent[$key] = $new_value;
  }

  /**
   * Decodes one RFC 6901 JSON pointer.
   */
  private function pointerSegments(string $path): array {
    return array_map(
      static fn(string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
      explode('/', substr($path, 1))
    );
  }

  /**
   * Finds exactly one stable collection row.
   */
  private function normalizeCollectionMatch(array $match): array {
    $fields = $match['fields'] ?? NULL;
    if (is_array($fields) && $fields !== [] && !array_is_list($fields)) {
      $normalized = [];
      foreach ($fields as $field => $value) {
        $field = trim((string) $field);
        if ($field === '' || is_array($value) || is_object($value)) {
          return [];
        }
        $normalized[$field] = $value;
      }
      return $normalized;
    }

    $field = trim((string) ($match['field'] ?? ''));
    if ($field === '' || !array_key_exists('value', $match)) {
      return [];
    }
    return [$field => $match['value']];
  }

  /**
   * Finds exactly one stable collection row.
   *
   * @param array<string, mixed> $match_fields
   *   Exact field/value pairs that together identify one row.
   */
  private function findCollectionRow(array $collection, array $match_fields, int $operation_index): int {
    $matches = [];
    foreach ($collection as $index => $row) {
      if (!is_array($row)) {
        continue;
      }
      $matches_all_fields = TRUE;
      foreach ($match_fields as $field => $value) {
        if (!array_key_exists($field, $row) || $row[$field] !== $value) {
          $matches_all_fields = FALSE;
          break;
        }
      }
      if ($matches_all_fields) {
        $matches[] = $index;
      }
    }
    if (count($matches) !== 1) {
      throw new \InvalidArgumentException(sprintf(
        'storyline_draft_operation_match_count_invalid:%d:%d',
        $operation_index,
        count($matches)
      ));
    }
    return $matches[0];
  }

  /**
   * Loads one durable operation receipt.
   */
  private function loadOperationReceipt(string $draft_id, string $operation_id): ?array {
    $row = $this->database->select(self::OPERATION_TABLE, 'o')
      ->fields('o')
      ->condition('draft_id', $draft_id)
      ->condition('operation_id', $operation_id)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  /**
   * Replays an operation receipt only for the identical request.
   */
  private function replayOperationReceipt(array $receipt, string $request_hash): array {
    if (!hash_equals((string) $receipt['request_hash'], $request_hash)) {
      throw new \DomainException('storyline_draft_operation_id_reused');
    }
    $result = json_decode((string) $receipt['response_payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($result)) {
      throw new \UnexpectedValueException('storyline_draft_operation_receipt_invalid');
    }
    $result['idempotent_replay'] = TRUE;
    $result['saved'] = $this->projectOwnership($result['saved']);
    return $result;
  }

  /**
   * Publish a saved draft as a canonical storyline template.
   */
  public function publishDraft(string $draft_id, int $expected_revision): array {
    if (!$this->currentUser->hasPermission('publish canonical dungeoncrawler storylines')) {
      throw new \UnexpectedValueException('storyline_draft_publish_access_denied');
    }
    $transaction = $this->database->startTransaction();
    try {
      $this->database->select(self::TABLE, 'd')->fields('d', ['draft_id'])
        ->condition('draft_id', $draft_id)->forUpdate()->execute()->fetchField();
      return $this->publishLockedDraft($draft_id, $expected_revision);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Publishes the locked revision, or replays its existing native signoff.
   */
  private function publishLockedDraft(string $draft_id, int $expected_revision): array {
    $draft = $this->getDraft($draft_id);
    $current_revision = (int) ($draft['revision'] ?? 0);
    if ($expected_revision !== $current_revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    if (($draft['status'] ?? '') === 'published') {
      $receipt = $draft['publication_receipt'] ?? NULL;
      if (!is_array($receipt)
        || ($receipt['draft_id'] ?? '') !== $draft_id
        || ($receipt['draft_revision'] ?? NULL) !== $current_revision
        || ($receipt['draft_payload_hash'] ?? '') !== $draft['payload_hash']
        || ($receipt['template_id'] ?? '') !== $draft['published_template_id']) {
        throw new \DomainException('storyline_publication_receipt_invalid:' . $draft_id);
      }
      return [
        'draft_id' => $draft_id,
        'revision' => $current_revision,
        'template_id' => $receipt['template_id'],
        'publication_receipt' => $receipt,
        'idempotent_replay' => TRUE,
      ];
    }
    if (($draft['status'] ?? '') !== 'editing') {
      throw new \DomainException('storyline_draft_not_publishable');
    }
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $definition = $this->storylineManager->prepareStorylineDefinition(
      $this->buildTemplateDefinitionFromDraftPayload($payload)
    );
    $draft_validation = $this->validateDraft($draft_id);
    if (($draft_validation['valid'] ?? FALSE) !== TRUE) {
      throw new StorylineDraftValidationException('storyline_draft_validation_failed', $this->validationFindings($draft_validation));
    }
    if (!is_array($definition['metadata'] ?? NULL)) {
      $definition['metadata'] = [];
    }
    $definition['metadata']['authorship'] = [
      'author_uid' => (int) $this->currentUser->id(),
      'authored_at' => $this->time->getRequestTime(),
      'source' => 'storyline_builder',
      'draft_id' => $draft_id,
      'draft_revision' => $current_revision,
    ];
    $template = $this->storylineManager->saveTemplate($definition);

    $updated_at = $this->time->getRequestTime();
    $published_template_id = (string) ($template['template_id'] ?? '');
    $quest_records = $this->publishTaskQuestRecords($payload, $published_template_id);
    $publication_receipt = [
      'published_at' => $updated_at,
      'author_uid' => (int) $this->currentUser->id(),
      'draft_id' => $draft_id,
      'draft_revision' => $current_revision,
      'template_id' => $published_template_id,
      'draft_payload_hash' => (string) ($draft['payload_hash'] ?? ''),
      'canonical_template_hash' => hash('sha256', $this->encodePayload(
        is_array($template['template_data'] ?? NULL) ? $template['template_data'] : $definition
      )),
    ];
    $affected = $this->database->update(self::TABLE)
      ->fields([
        'status' => 'published',
        'updated_at' => $updated_at,
        'published_template_id' => $published_template_id !== '' ? $published_template_id : NULL,
        'published_at' => $updated_at,
        'publication_receipt' => $this->encodePayload($publication_receipt),
      ])
      ->condition('draft_id', $draft_id)
      ->condition('owner_uid', (int) $draft['owner_uid'])
      ->condition('revision', $current_revision)
      ->condition('status', 'editing')
      ->execute();
    if ($affected !== 1) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    $draft['status'] = 'published';
    $draft['updated_at'] = $updated_at;
    $draft['published_template_id'] = $published_template_id;

    return [
      'draft_id' => $draft_id,
      'revision' => $current_revision,
      'template_id' => (string) ($template['template_id'] ?? ''),
      'template' => $template,
      'quest_records' => $quest_records,
      'publication_receipt' => $publication_receipt,
    ];
  }

  /**
   * Writes the quest record each task is played as, from the task's own rows.
   *
   * Runs inside the publication transaction: a task whose record cannot be
   * written fails the whole publication.
   */
  private function publishTaskQuestRecords(array $payload, string $storyline_id): array {
    if ($this->questTemplates === NULL || $this->objectiveTypes === NULL) {
      throw new \LogicException('storyline_publication_quest_record_writer_unavailable');
    }
    $builder = new StorylineQuestRecordBuilder($this->objectiveTypes);
    $rows_by_task = StorylineQuestRecordBuilder::rowsByTask((array) ($payload['process_flow']['objective_completion_criteria'] ?? []));
    $receipts = [];
    foreach (array_values(array_filter((array) ($payload['process_flow']['tasks'] ?? []), 'is_array')) as $task) {
      $task_id = trim((string) ($task['task_id'] ?? ''));
      $receipts[] = $this->questTemplates->saveStorylineQuestRecord(
        $builder->questRecord($task, $rows_by_task[$task_id] ?? [], $payload, $storyline_id)
      );
    }
    return $receipts;
  }

  /**
   * Convert draft payload into storyline definition accepted by saveTemplate().
   */
  private function buildTemplateDefinitionFromDraftPayload(array $payload): array {
    if (is_array($payload['canonical_template'] ?? NULL)) {
      return $this->applyDraftEditsToCanonicalTemplate($payload, $payload['canonical_template']);
    }

    $title = trim((string) ($payload['title'] ?? 'Untitled Storyline'));
    $template_id = $this->sanitizeIdentifier((string) ($payload['storyline_id'] ?? $title));
    if ($template_id === '') {
      $template_id = 'storyline-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
    }

    $process_flow = is_array($payload['process_flow'] ?? NULL) ? $payload['process_flow'] : [];
    $objects = is_array($payload['objects'] ?? NULL) ? $payload['objects'] : [];
    $journal = array_values(array_filter((array) ($process_flow['journal_entries'] ?? []), 'is_array'));
    $tasks = array_values(array_filter((array) ($process_flow['tasks'] ?? []), 'is_array'));
    $actors = array_values(array_filter((array) (($objects['actors']['generation_specs'] ?? [])), 'is_array'));
    $dungeons = array_values(array_filter((array) (($objects['dungeons']['generation_specs'] ?? [])), 'is_array'));
    $rooms = array_values(array_filter((array) (($objects['rooms']['generation_specs'] ?? [])), 'is_array'));
    $items = array_values(array_filter((array) (($objects['items']['generation_specs'] ?? [])), 'is_array'));

    $default_chapter_id = $this->sanitizeIdentifier((string) ($journal[0]['chapter_id'] ?? ($template_id . '-chapter')));
    if ($default_chapter_id === '') {
      $default_chapter_id = $template_id . '-chapter';
    }
    $chapter_rows = [];
    foreach ($journal as $index => $entry) {
      $chapter_id = $this->sanitizeIdentifier((string) ($entry['chapter_id'] ?? ''));
      if ($chapter_id === '') {
        continue;
      }
      if (isset($chapter_rows[$chapter_id])) {
        continue;
      }
      $chapter_rows[$chapter_id] = [
        'chapter_id' => $chapter_id,
        'name' => ucwords(str_replace(['-', '_'], ' ', $chapter_id)),
        'summary' => trim((string) ($payload['description'] ?? '')),
        'order' => $index,
        'quest_ids' => [],
        'asset_references' => [],
        'gates' => [],
        'scenes' => [],
      ];
    }
    if ($chapter_rows === []) {
      $chapter_rows[$default_chapter_id] = [
        'chapter_id' => $default_chapter_id,
        'name' => 'Primary chapter',
        'summary' => trim((string) ($payload['description'] ?? '')),
        'order' => 0,
        'quest_ids' => [],
        'asset_references' => [],
        'gates' => [],
        'scenes' => [],
      ];
    }
    $tasks_by_scene = [];
    foreach ($tasks as $task_index => $task) {
      $task_id = trim((string) ($task['task_id'] ?? ''));
      $scene_id = trim((string) ($task['scene_id'] ?? $journal[$task_index]['scene_id'] ?? ''));
      if ($task_id !== '' && $scene_id !== '') {
        $tasks_by_scene[$scene_id][] = $task_id;
      }
    }
    foreach ($items as $item) {
      $task_id = trim((string) ($item['quest_id'] ?? ''));
      $scene_id = trim((string) ($item['scene_id'] ?? ''));
      if ($task_id !== '' && $scene_id !== '') {
        $tasks_by_scene[$scene_id][] = $task_id;
      }
    }

    $all_scene_rows = [];
    foreach ($journal as $index => $entry) {
      $scene_name = trim((string) ($entry['title'] ?? ('Scene ' . ($index + 1))));
      $scene_id = $this->sanitizeIdentifier((string) ($entry['scene_id'] ?? $scene_name));
      if ($scene_id === '') {
        $scene_id = $default_chapter_id . '-scene-' . ($index + 1);
      }
      $chapter_id = $this->sanitizeIdentifier((string) ($entry['chapter_id'] ?? $default_chapter_id)) ?: $default_chapter_id;
      if (!isset($chapter_rows[$chapter_id])) {
        $chapter_rows[$chapter_id] = [
          'chapter_id' => $chapter_id,
          'name' => ucwords(str_replace(['-', '_'], ' ', $chapter_id)),
          'summary' => '',
          'order' => count($chapter_rows),
          'quest_ids' => [],
          'asset_references' => [],
          'gates' => [],
          'scenes' => [],
        ];
      }
      $room_ids = $this->normalizeCsvReferences($entry['room_ids'] ?? []);
      if ($room_ids === []) {
        foreach ($rooms as $room) {
          if (trim((string) ($room['scene_id'] ?? '')) === $scene_id) {
            $room_id = trim((string) ($room['room_id'] ?? ''));
            if ($room_id !== '') {
              $room_ids[] = $room_id;
            }
          }
        }
      }
      $scene = [
        'scene_id' => $scene_id,
        'name' => $scene_name,
        'summary' => trim((string) ($entry['summary'] ?? $entry['trigger'] ?? '')),
        'order' => (int) ($entry['order'] ?? $index),
        'quest_ids' => array_values(array_unique($tasks_by_scene[$scene_id] ?? [])),
        'asset_references' => array_map(static fn(string $room_id): array => [
          'asset_type' => 'room',
          'asset_id' => $room_id,
          'asset_role' => 'scene-room',
          'source_scope' => 'scene',
        ], array_values(array_unique($room_ids))),
        'gates' => trim((string) ($entry['trigger'] ?? '')) !== '' ? [[
          'trigger' => trim((string) ($entry['trigger'] ?? '')),
          'progression_gate' => trim((string) ($entry['trigger'] ?? '')),
        ]] : [],
      ];
      $chapter_rows[$chapter_id]['scenes'][] = $scene;
      $chapter_rows[$chapter_id]['quest_ids'] = array_values(array_unique(array_merge(
        $chapter_rows[$chapter_id]['quest_ids'],
        $scene['quest_ids']
      )));
      $all_scene_rows[] = $scene;
    }

    if ($all_scene_rows === []) {
      $scene = [
        'scene_id' => $default_chapter_id . '-scene-1',
        'name' => 'Opening Scene',
        'summary' => trim((string) ($payload['description'] ?? '')),
        'order' => 0,
        'quest_ids' => [],
        'asset_references' => [],
        'gates' => [],
      ];
      $chapter_rows[$default_chapter_id]['scenes'][] = $scene;
      $all_scene_rows[] = $scene;
    }

    // Future enhancement seam: project authored $actor['level_scaling'] and
    // $actor['retinue_scaling'] into runtime_materialization here.
    // @see docs/dungeoncrawler/STORYLINE_ACTOR_LEVEL_AND_RETINUE_SCALING_DESIGN.md
    $contacts = [];
    foreach ($actors as $index => $actor) {
      $entity_id = trim((string) ($actor['actor_id'] ?? ''));
      if ($entity_id === '') {
        continue;
      }
      $contact_id = $this->sanitizeIdentifier((string) ($actor['contact_id'] ?? ('contact-' . $entity_id)));
      if ($contact_id === '') {
        $contact_id = 'contact-' . ($index + 1);
      }
      $contacts[] = [
        'contact_id' => $contact_id,
        'entity_type' => 'campaign_npc',
        'entity_id' => $entity_id,
        'role' => trim((string) ($actor['role'] ?? 'contact')) ?: 'contact',
        'display_name' => trim((string) ($actor['display_name'] ?? $entity_id)) ?: $entity_id,
        'attitude' => trim((string) ($actor['attitude'] ?? 'indifferent')) ?: 'indifferent',
        'availability' => trim((string) ($actor['availability'] ?? 'available')) ?: 'available',
        'notes' => trim((string) ($actor['motivation'] ?? '')),
        'relationship_state' => [
          'affiliations' => $this->contactAffiliations($actor, $payload),
          'authored_affiliations' => array_values(array_filter((array) ($actor['affiliations'] ?? []), 'is_array')),
          'chapter_id' => trim((string) ($actor['chapter_id'] ?? '')),
          'scene_id' => trim((string) ($actor['scene_id'] ?? '')),
          'location_id' => trim((string) ($actor['location_id'] ?? '')),
          'runtime_materialization' => [
            'canonical_actor_id' => $entity_id,
            'canonical_location_id' => trim((string) ($actor['location_id'] ?? '')),
          ],
          'authored_details' => [
            'dialogue' => trim((string) ($actor['dialogue'] ?? '')),
            'knowledge' => trim((string) ($actor['knowledge'] ?? '')),
          ],
        ],
        'introduces_to' => $this->contactRelationships($entity_id, $payload),
      ];
    }

    $asset_references = [];
    foreach ($actors as $actor) {
      $actor_id = trim((string) ($actor['actor_id'] ?? ''));
      if ($actor_id !== '') {
        $asset_references[] = [
          'asset_type' => 'npc',
          'asset_id' => $actor_id,
          'asset_role' => trim((string) ($actor['role'] ?? 'supporting-actor')) ?: 'supporting-actor',
          'chapter_id' => trim((string) ($actor['chapter_id'] ?? '')),
          'scene_id' => trim((string) ($actor['scene_id'] ?? '')),
          'link_data' => [
            'location_id' => trim((string) ($actor['location_id'] ?? '')),
          ],
          'source_scope' => 'asset_reference',
        ];
      }
    }
    foreach ($rooms as $room) {
      $room_id = trim((string) ($room['room_id'] ?? ''));
      if ($room_id !== '') {
        $narrative = trim((string) ($room['narrative'] ?? ''));
        $asset_references[] = [
          'asset_type' => 'room',
          'asset_id' => $room_id,
          'asset_role' => $narrative === 'connector-room'
            ? 'road-connector'
            : $this->normalizeAssetRole($narrative, 'story-room'),
          'chapter_id' => trim((string) ($room['chapter_id'] ?? '')),
          'scene_id' => trim((string) ($room['scene_id'] ?? '')),
          'link_data' => [
            'dungeon_id' => trim((string) ($room['dungeon_id'] ?? '')),
            'narrative' => $narrative,
          ],
          'source_scope' => 'asset_reference',
        ];
      }
    }
    foreach ($dungeons as $dungeon) {
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id !== '') {
        $purpose = trim((string) ($dungeon['purpose'] ?? ''));
        $asset_references[] = [
          'asset_type' => 'dungeon',
          'asset_id' => $dungeon_id,
          'asset_role' => $this->normalizeAssetRole($purpose, 'story-dungeon'),
          'chapter_id' => trim((string) ($dungeon['chapter_id'] ?? '')),
          'source_scope' => 'asset_reference',
          'link_data' => [
            'entrance_room_id' => trim((string) ($dungeon['entrance_room'] ?? '')),
            'purpose' => $purpose,
          ],
        ];
      }
    }
    foreach ($items as $item) {
      $item_id = trim((string) ($item['item_id'] ?? ''));
      if ($item_id !== '') {
        $purpose = trim((string) ($item['purpose'] ?? ''));
        $asset_references[] = [
          'asset_type' => 'item',
          'asset_id' => $item_id,
          'asset_role' => $this->normalizeAssetRole($purpose, 'story-item'),
          'notes' => trim((string) ($item['acquisition'] ?? '')),
          'chapter_id' => trim((string) ($item['chapter_id'] ?? '')),
          'scene_id' => trim((string) ($item['scene_id'] ?? '')),
          'link_data' => [
            'quest_id' => trim((string) ($item['quest_id'] ?? '')),
            'actor_id' => trim((string) ($item['actor_id'] ?? '')),
            'prerequisite_ids' => $this->normalizeCsvReferences($item['prerequisite_ids'] ?? []),
            'reward' => trim((string) ($item['reward'] ?? '')),
            'purpose' => $purpose,
          ],
          'source_scope' => 'asset_reference',
        ];
      }
    }

    return [
      'template_id' => $template_id,
      'name' => $title,
      'synopsis' => trim((string) ($payload['description'] ?? '')),
      'level_range' => trim((string) ($payload['level_range'] ?? '')),
      'source' => 'user_authored',
      'tags' => array_values(array_unique(array_merge(
        ['storyline-builder'],
        array_values(array_filter(array_map('strval', (array) ($payload['tags'] ?? []))))
      ))),
      'goal' => trim((string) ($payload['premise'] ?? $payload['description'] ?? $title)),
      'metadata' => [
        'goal' => trim((string) ($payload['premise'] ?? $payload['description'] ?? $title)),
        'generated_outline' => [
          'entry_point' => [
            'primary_quest_giver_id' => trim((string) ($payload['entry_actor_id'] ?? '')),
            'primary_location_id' => trim((string) ($payload['entry_location_id'] ?? '')),
          ],
        ],
        'storyline_builder' => [
          'identity' => [
            'player_hook' => trim((string) ($payload['player_hook'] ?? '')),
            'ending' => trim((string) ($payload['ending'] ?? '')),
          ],
          'narrative_architecture' => $this->normalizeNarrativeArchitecture($process_flow['narrative_architecture'] ?? []),
          'objective_completion_criteria' => array_values(array_filter((array) ($process_flow['objective_completion_criteria'] ?? []), 'is_array')),
          'institutions' => array_values(array_filter((array) ($objects['institutions'] ?? []), 'is_array')),
          'actor_validation_criteria' => array_values(array_filter((array) (($objects['actors']['validation_criteria'] ?? [])), static fn ($value): bool => is_scalar($value) || is_array($value))),
          'room_validation_criteria' => array_values(array_filter((array) (($objects['rooms']['validation_criteria'] ?? [])), static fn ($value): bool => is_scalar($value) || is_array($value))),
          'item_validation_criteria' => array_values(array_filter((array) (($objects['items']['validation_criteria'] ?? [])), static fn ($value): bool => is_scalar($value) || is_array($value))),
          'generation_orchestration' => array_values(array_filter((array) ($payload['orchestration']['generation'] ?? []), static fn ($value): bool => is_scalar($value) || is_array($value))),
          'validation_orchestration' => array_values(array_filter((array) ($payload['orchestration']['validation'] ?? []), static fn ($value): bool => is_scalar($value) || is_array($value))),
        ],
        'storyline_analysis' => $this->buildStorylineAnalysisMetadata($payload, $all_scene_rows),
      ],
      'chapters' => array_values($chapter_rows),
      'questline' => $this->buildQuestlineFromTaskRows(
        $tasks,
        array_values(array_filter((array) ($process_flow['branch_choices'] ?? []), 'is_array')),
      ),
      'contacts' => $contacts,
      'asset_references' => $asset_references,
    ];
  }

  /**
   * Normalize identifiers to the storyline schema pattern.
   */
  private function sanitizeIdentifier(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    $value = trim($value, '-_');
    if ($value === '') {
      return '';
    }
    return preg_replace('/-+/', '-', $value) ?? '';
  }

  /**
   * Project canonical storyline fields into draft row-oriented authoring sections.
   */
  private function hydrateDraftRowsFromCanonicalTemplate(array $payload, array $template_data): array {
    $chapters = array_values(array_filter((array) ($template_data['chapters'] ?? []), 'is_array'));
    $journal_rows = [];
    $task_rows = [];
    $objective_rows = [];
    foreach ($chapters as $chapter) {
      $scenes = array_values(array_filter((array) ($chapter['scenes'] ?? []), 'is_array'));
      foreach ($scenes as $scene) {
        $gates = is_array($scene['gates'][0] ?? NULL) ? $scene['gates'][0] : [];
        $journal_rows[] = [
          'chapter_id' => (string) ($chapter['chapter_id'] ?? ''),
          'scene_id' => (string) ($scene['scene_id'] ?? ''),
          'order' => (string) ($scene['order'] ?? 0),
          'title' => (string) ($scene['name'] ?? ''),
          'summary' => (string) ($scene['summary'] ?? ''),
          'trigger' => (string) ($gates['trigger'] ?? ''),
          'room_ids' => implode(', ', array_values(array_filter(array_map(
            static fn(array $reference): string => strtolower(trim((string) ($reference['asset_type'] ?? ''))) === 'room'
              ? trim((string) ($reference['asset_id'] ?? ''))
              : '',
            array_values(array_filter((array) ($scene['asset_references'] ?? []), 'is_array'))
          )))),
        ];
        foreach (array_values(array_filter((array) ($scene['quest_ids'] ?? []), 'is_string')) as $quest_id) {
          $task_rows[] = [
            'task_id' => $quest_id,
            'scene_id' => (string) ($scene['scene_id'] ?? ''),
            'owner' => 'party',
            'requirement' => '',
          ];
        }
      }
    }
    $questline = is_array($template_data['questline'] ?? NULL) ? $template_data['questline'] : [];
    $quest_nodes = array_values(array_filter((array) ($questline['quest_nodes'] ?? []), 'is_array'));
    $branch_rows = [];
    if ($quest_nodes !== []) {
      $task_rows = [];
      foreach ($quest_nodes as $node) {
        $task_rows[] = [
          'task_id' => (string) ($node['quest_id'] ?? ''),
          'chapter_id' => (string) ($node['chapter_id'] ?? ''),
          'scene_id' => (string) ($node['scene_id'] ?? ''),
          'status' => (string) ($node['status'] ?? 'available'),
          'unlocks_after' => implode(', ', array_values(array_filter((array) ($node['unlocks_after'] ?? []), 'is_string'))),
          'unlocks_to' => implode(', ', array_values(array_filter((array) ($node['unlocks_to'] ?? []), 'is_string'))),
          'unlock_condition' => (string) ($node['unlock_condition'] ?? ''),
        ] + (is_array($node['goal'] ?? NULL) ? [
          'goal_text' => (string) $node['goal']['goal_text'],
          'gm_notes' => (string) $node['goal']['gm_notes'],
          'open_resolution' => (bool) $node['goal']['open_resolution'],
        ] : []);
        foreach (array_values(array_filter((array) ($node['choices'] ?? []), 'is_array')) as $choice) {
          $branch_rows[] = (is_array($choice['approach'] ?? NULL) ? ['approach' => $choice['approach']] : []) + [
            'decision_id' => (string) ($choice['decision_id'] ?? ''),
            'source_task_id' => (string) ($node['quest_id'] ?? ''),
            'choice_id' => (string) ($choice['choice_id'] ?? ''),
            'label' => (string) ($choice['label'] ?? ''),
            'trigger' => is_array($choice['trigger'] ?? NULL) ? $choice['trigger'] : [],
            'effects' => is_array($choice['effects'] ?? NULL) ? $choice['effects'] : [],
            'target_task_id' => (string) ($choice['target_quest_id'] ?? ''),
            'target_scene_id' => (string) ($choice['target_scene_id'] ?? ''),
            'terminal' => !empty($choice['terminal']),
          ];
        }
      }
    }
    foreach ($quest_nodes as $node) {
      $objective_rows[] = [
        'objective_id' => (string) ($node['quest_id'] ?? ''),
        'kind' => 'flag',
        'metric' => 'quest_completion',
        'description' => 'Quest progression completion',
        'required' => 'true',
      ];
    }

    $actors = [];
    $actor_relationships = [];
    foreach (array_values(array_filter((array) ($template_data['contacts'] ?? []), 'is_array')) as $contact) {
      foreach ((array) ($contact['introduces_to'] ?? []) as $link) {
        if (is_array($link) && ($link['relationship_state']['authored_in'] ?? '') === 'storyline_actor_relationships') {
          $actor_relationships[] = [
            'source_actor_id' => (string) ($contact['entity_id'] ?? ''),
            'target_actor_id' => (string) $link['entity_id'],
            'attitude' => (string) $link['attitude'],
            'bond' => (string) ($link['relationship_state']['bond'] ?? ''),
            'notes' => (string) $link['notes'],
          ];
        }
      }
      $actors[] = [
        'affiliations' => array_values(array_filter((array) ($contact['relationship_state']['authored_affiliations'] ?? []), 'is_array')),
        'contact_id' => (string) ($contact['contact_id'] ?? ''),
        'actor_id' => (string) ($contact['entity_id'] ?? ''),
        'role' => (string) ($contact['role'] ?? 'contact'),
        'display_name' => (string) ($contact['display_name'] ?? ''),
        'motivation' => (string) ($contact['notes'] ?? ''),
        'dialogue' => (string) ($contact['relationship_state']['authored_details']['dialogue'] ?? ''),
        'knowledge' => (string) ($contact['relationship_state']['authored_details']['knowledge'] ?? ''),
        'attitude' => (string) ($contact['attitude'] ?? 'indifferent'),
        'availability' => (string) ($contact['availability'] ?? 'available'),
        'chapter_id' => (string) ($contact['relationship_state']['chapter_id'] ?? ''),
        'scene_id' => (string) ($contact['relationship_state']['scene_id'] ?? ''),
        'location_id' => (string) ($contact['relationship_state']['runtime_materialization']['canonical_location_id'] ?? ''),
      ];
    }
    $dungeons = [];
    $rooms = [];
    foreach (array_values(array_filter((array) ($template_data['asset_references'] ?? []), 'is_array')) as $reference) {
      $asset_type = strtolower(trim((string) ($reference['asset_type'] ?? '')));
      if ($asset_type === 'dungeon') {
        $dungeons[] = [
          'dungeon_id' => (string) ($reference['asset_id'] ?? ''),
          'chapter_id' => (string) ($reference['chapter_id'] ?? ''),
          'purpose' => (string) ($reference['link_data']['purpose'] ?? $reference['asset_role'] ?? ''),
          'entrance_room' => (string) ($reference['link_data']['entrance_room_id'] ?? ''),
        ];
      }
      elseif ($asset_type === 'room') {
        $rooms[] = [
          'dungeon_id' => (string) ($reference['link_data']['dungeon_id'] ?? ''),
          'chapter_id' => (string) ($reference['chapter_id'] ?? ''),
          'scene_id' => (string) ($reference['scene_id'] ?? ''),
          'room_id' => (string) ($reference['asset_id'] ?? ''),
          'narrative' => (string) ($reference['link_data']['narrative'] ?? $reference['asset_role'] ?? ''),
        ];
      }
    }
    $items = [];
    foreach (array_values(array_filter((array) ($template_data['asset_references'] ?? []), 'is_array')) as $reference) {
      if (strtolower(trim((string) ($reference['asset_type'] ?? ''))) !== 'item') {
        continue;
      }
      $items[] = [
        'item_id' => (string) ($reference['asset_id'] ?? ''),
        'purpose' => (string) ($reference['link_data']['purpose'] ?? $reference['asset_role'] ?? ''),
        'acquisition' => (string) ($reference['notes'] ?? ''),
        'chapter_id' => (string) ($reference['chapter_id'] ?? ''),
        'scene_id' => (string) ($reference['scene_id'] ?? ''),
        'quest_id' => (string) ($reference['link_data']['quest_id'] ?? ''),
        'actor_id' => (string) ($reference['link_data']['actor_id'] ?? ''),
        'prerequisite_ids' => implode(', ', array_values(array_filter((array) ($reference['link_data']['prerequisite_ids'] ?? []), 'is_string'))),
        'reward' => (string) ($reference['link_data']['reward'] ?? ''),
      ];
    }

    $metadata = is_array($template_data['metadata'] ?? NULL) ? $template_data['metadata'] : [];
    $builder = is_array($metadata['storyline_builder'] ?? NULL) ? $metadata['storyline_builder'] : [];
    $analysis = is_array($metadata['storyline_analysis'] ?? NULL) ? $metadata['storyline_analysis'] : [];
    $scene_annotations = is_array($analysis['scene_annotations'] ?? NULL) ? $analysis['scene_annotations'] : [];
    foreach ($journal_rows as &$journal_row) {
      $scene_id = trim((string) ($journal_row['scene_id'] ?? ''));
      $annotation = is_array($scene_annotations[$scene_id] ?? NULL) ? $scene_annotations[$scene_id] : [];
      $journal_row['summary'] = (string) ($annotation['summary'] ?? $journal_row['summary'] ?? '');
      $journal_row['central_conflict'] = (string) ($annotation['central_conflict'] ?? '');
      $journal_row['dramatic_function'] = (string) ($annotation['dramatic_function'] ?? '');
      $journal_row['themes'] = implode(', ', array_values(array_filter((array) ($annotation['themes'] ?? []), 'is_string')));
      $journal_row['character_ids'] = implode(', ', array_values(array_filter((array) ($annotation['character_ids'] ?? []), 'is_string')));
      $foreshadowing = array_values(array_filter((array) ($annotation['foreshadowing'] ?? []), 'is_array'));
      $journal_row['foreshadows_scene_id'] = (string) ($foreshadowing[0]['target_scene_id'] ?? '');
    }
    unset($journal_row);
    $payload['process_flow']['journal_entries'] = $journal_rows;
    $payload['process_flow']['tasks'] = $task_rows;
    $payload['process_flow']['branch_choices'] = $branch_rows;
    $payload['process_flow']['objective_completion_criteria'] = array_values(
      array_filter(
        (array) ($builder['objective_completion_criteria'] ?? $objective_rows),
        'is_array'
      )
    );
    $payload['process_flow']['conflicts'] = array_values(array_filter((array) ($analysis['conflicts'] ?? []), 'is_array'));
    $payload['process_flow']['narrative_architecture'] = $this->normalizeNarrativeArchitecture($builder['narrative_architecture'] ?? []);
    $payload['objects']['actors']['generation_specs'] = $actors;
    $payload['objects']['actors']['relationships'] = $actor_relationships;
    $payload['objects']['institutions'] = array_values(array_filter((array) ($builder['institutions'] ?? []), 'is_array'));
    $payload['objects']['actors']['validation_criteria'] = array_values((array) ($builder['actor_validation_criteria'] ?? []));
    $payload['objects']['dungeons']['generation_specs'] = $dungeons;
    $payload['objects']['rooms']['generation_specs'] = $this->storylineManager
      ->resolveAuthoredRoomDungeonBindings($rooms, $dungeons)['rooms'];
    $payload['objects']['rooms']['validation_criteria'] = array_values((array) ($builder['room_validation_criteria'] ?? []));
    $payload['objects']['items']['generation_specs'] = $items;
    $payload['objects']['items']['validation_criteria'] = array_values((array) ($builder['item_validation_criteria'] ?? []));
    $payload['orchestration']['generation'] = array_values((array) ($builder['generation_orchestration'] ?? []));
    $payload['orchestration']['validation'] = array_values((array) ($builder['validation_orchestration'] ?? []));
    return $payload;
  }

  /**
   * Apply editable draft fields back onto an existing canonical template.
   */
  private function applyDraftEditsToCanonicalTemplate(array $payload, array $template): array {
    $original_template_id = $this->sanitizeIdentifier((string) ($template['template_id'] ?? ''));
    $template['template_id'] = $this->sanitizeIdentifier((string) ($payload['storyline_id'] ?? $template['template_id'] ?? ''));
    if ($template['template_id'] === '') {
      $template['template_id'] = $this->sanitizeIdentifier((string) ($template['name'] ?? 'storyline')) ?: 'storyline';
    }
    $template['name'] = trim((string) ($payload['title'] ?? $template['name'] ?? 'Untitled Storyline'));
    $template['synopsis'] = trim((string) ($payload['description'] ?? $template['synopsis'] ?? ''));
    $template['level_range'] = trim((string) ($payload['level_range'] ?? $template['level_range'] ?? ''));
    $template['tags'] = array_values(array_unique(array_filter(array_map(
      static fn(mixed $value): string => trim((string) $value),
      (array) ($payload['tags'] ?? $template['tags'] ?? [])
    ))));
    $template['source'] = trim((string) ($template['source'] ?? 'user_authored')) ?: 'user_authored';
    if (!is_array($template['metadata'] ?? NULL)) {
      $template['metadata'] = [];
    }
    if ($original_template_id !== '' && $original_template_id !== $template['template_id']) {
      $template['metadata']['source_template_id'] = trim((string) ($template['metadata']['source_template_id'] ?? '')) ?: $original_template_id;
    }
    $template['metadata']['goal'] = trim((string) ($payload['premise'] ?? $template['metadata']['goal'] ?? ''));
    if (!is_array($template['metadata']['storyline_builder'] ?? NULL)) {
      $template['metadata']['storyline_builder'] = [];
    }
    $existing_identity = is_array($template['metadata']['storyline_builder']['identity'] ?? NULL)
      ? $template['metadata']['storyline_builder']['identity']
      : [];
    $template['metadata']['storyline_builder']['identity'] = [
      'player_hook' => trim((string) ($payload['player_hook'] ?? $existing_identity['player_hook'] ?? '')),
      'ending' => trim((string) ($payload['ending'] ?? $existing_identity['ending'] ?? '')),
    ];
    if (!is_array($template['metadata']['generated_outline'] ?? NULL)) {
      $template['metadata']['generated_outline'] = [];
    }
    if (!is_array($template['metadata']['generated_outline']['entry_point'] ?? NULL)) {
      $template['metadata']['generated_outline']['entry_point'] = [];
    }
    $template['metadata']['generated_outline']['entry_point']['primary_quest_giver_id'] = trim((string) (
      $payload['entry_actor_id']
      ?? $template['metadata']['generated_outline']['entry_point']['primary_quest_giver_id']
      ?? ''
    ));
    $template['metadata']['generated_outline']['entry_point']['primary_location_id'] = trim((string) (
      $payload['entry_location_id']
      ?? $template['metadata']['generated_outline']['entry_point']['primary_location_id']
      ?? ''
    ));
    $template['metadata']['storyline_builder']['institutions'] = array_values(array_filter((array) ($payload['objects']['institutions'] ?? []), 'is_array'));
    $template['metadata']['storyline_builder']['objective_completion_criteria'] = array_values(array_filter((array) ($payload['process_flow']['objective_completion_criteria'] ?? []), 'is_array'));
    $template['metadata']['storyline_builder']['narrative_architecture'] = $this->normalizeNarrativeArchitecture(
      $payload['process_flow']['narrative_architecture'] ?? []
    );
    $template['metadata']['storyline_builder']['actor_validation_criteria'] = array_values((array) ($payload['objects']['actors']['validation_criteria'] ?? []));
    $template['metadata']['storyline_builder']['room_validation_criteria'] = array_values((array) ($payload['objects']['rooms']['validation_criteria'] ?? []));
    $template['metadata']['storyline_builder']['item_validation_criteria'] = array_values((array) ($payload['objects']['items']['validation_criteria'] ?? []));
    $template['metadata']['storyline_builder']['generation_orchestration'] = array_values((array) ($payload['orchestration']['generation'] ?? []));
    $template['metadata']['storyline_builder']['validation_orchestration'] = array_values((array) ($payload['orchestration']['validation'] ?? []));
    $template = $this->applyCanonicalTemplateProcessFlowEdits($payload, $template);
    $task_rows = array_values(array_filter((array) ($payload['process_flow']['tasks'] ?? []), 'is_array'));
    $template['questline'] = $this->buildQuestlineFromTaskRows(
      $task_rows,
      array_values(array_filter((array) ($payload['process_flow']['branch_choices'] ?? []), 'is_array')),
    );
    $template['linked_quests'] = $this->synchronizeLinkedQuestsWithTasks(
      is_array($template['linked_quests'] ?? NULL) ? $template['linked_quests'] : [],
      $task_rows,
    );
    $template = $this->applyCanonicalTemplateObjectEdits($payload, $template);
    $template['metadata']['storyline_analysis'] = $this->buildStorylineAnalysisMetadata(
      $payload,
      $this->collectCanonicalScenes($template)
    );

    $template['asset_references'] = array_values(array_filter(
      (array) ($template['asset_references'] ?? []),
      'is_array'
    ));
    return $template;
  }

  /**
   * Build the explicit authored quest graph represented by task rows.
   */
  private function buildQuestlineFromTaskRows(array $tasks, array $branch_choices = []): array {
    $nodes = [];
    $ordered_ids = [];
    foreach (array_values(array_filter($tasks, 'is_array')) as $task) {
      $quest_id = trim((string) ($task['task_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      $ordered_ids[] = $quest_id;
      $nodes[] = [
        'quest_id' => $quest_id,
        'chapter_id' => trim((string) ($task['chapter_id'] ?? '')),
        'scene_id' => trim((string) ($task['scene_id'] ?? '')),
        'status' => trim((string) ($task['status'] ?? 'available')) ?: 'available',
        'unlocks_after' => [],
        'unlocks_to' => $this->normalizeCsvReferences($task['unlocks_to'] ?? []),
        'unlock_condition' => trim((string) ($task['unlock_condition'] ?? '')),
        'prerequisite_mode' => 'all',
        'goal' => $this->normalizeTaskGoal($task),
        'choices' => [],
      ];
    }
    $node_indexes = [];
    foreach ($nodes as $index => $node) {
      $node_indexes[$node['quest_id']] = $index;
    }
    $storyline_choices = [];
    foreach (array_values(array_filter($branch_choices, 'is_array')) as $choice) {
      $source_quest_id = trim((string) ($choice['source_task_id'] ?? ''));
      // A source that names no existing task cannot be placed; validateDraft
      // reports it as choice_source_task_unknown.
      if ($source_quest_id !== '' && !isset($node_indexes[$source_quest_id])) {
        continue;
      }
      $target_quest_id = trim((string) ($choice['target_task_id'] ?? ''));
      $terminal = $this->normalizeBranchTerminal($choice['terminal'] ?? FALSE);
      $projected_choice = [
        'decision_id' => trim((string) ($choice['decision_id'] ?? '')),
        'choice_id' => trim((string) ($choice['choice_id'] ?? '')),
        'label' => trim((string) ($choice['label'] ?? '')),
        'trigger' => $this->normalizeBranchTrigger($choice['trigger'] ?? []),
        'effects' => $this->normalizeBranchEffects($choice['effects'] ?? []),
        'target_quest_id' => $target_quest_id,
        'target_scene_id' => trim((string) ($choice['target_scene_id'] ?? '')),
        'terminal' => $terminal,
      ] + (is_array($choice['approach'] ?? NULL) ? ['approach' => $this->normalizeChoiceApproach($choice['approach'])] : []);
      if ($source_quest_id === '') {
        $storyline_choices[] = $projected_choice;
      }
      else {
        $nodes[$node_indexes[$source_quest_id]]['choices'][] = $projected_choice;
      }
    }
    foreach ($nodes as &$node) {
      if ($node['choices'] === []) {
        continue;
      }
      $current_quest_id = $node['quest_id'];
      $node['unlocks_to'] = array_values(array_unique(array_filter(array_map(
        static fn(array $choice): string => (!$choice['terminal'] && $choice['target_quest_id'] !== $current_quest_id)
          ? $choice['target_quest_id']
          : '',
        $node['choices'],
      ))));
      $node['unlock_condition'] = 'player_choice';
    }
    unset($node);
    foreach ($nodes as $index => &$node) {
      $next_id = $nodes[$index + 1]['quest_id'] ?? '';
      if ($node['choices'] === [] && $node['unlocks_to'] === [] && $next_id !== '') {
        $node['unlocks_to'] = $next_id !== '' ? [$next_id] : [];
      }
    }
    unset($node);
    $predecessors = [];
    foreach ($nodes as $source) {
      foreach (array_values(array_unique((array) ($source['unlocks_to'] ?? []))) as $target_quest_id) {
        if (isset($node_indexes[$target_quest_id])) {
          $predecessors[$target_quest_id][] = $source['quest_id'];
        }
      }
    }
    foreach ($nodes as &$node) {
      $quest_id = $node['quest_id'];
      $unlocks_after = array_values(array_unique((array) ($predecessors[$quest_id] ?? [])));
      $node['unlocks_after'] = $unlocks_after;
      $node['prerequisite_mode'] = 'all';
      foreach ($unlocks_after as $source_quest_id) {
        if (isset($node_indexes[$source_quest_id]) && $nodes[$node_indexes[$source_quest_id]]['choices'] !== []) {
          $node['prerequisite_mode'] = 'any';
          break;
        }
      }
      if ($node['unlock_condition'] === '') {
        $node['unlock_condition'] = $unlocks_after !== [] ? 'complete_previous_quest' : 'initially_available';
      }
    }
    unset($node);

    return [
      'primary_quest_id' => $ordered_ids[0] ?? '',
      'ordered_quest_ids' => array_values(array_unique($ordered_ids)),
      'quest_nodes' => $nodes,
    ] + ($storyline_choices !== [] ? ['storyline_choices' => $storyline_choices] : []);
  }

  /**
   * Normalize author-entered terminal flags without truthy-string ambiguity.
   */
  /**
   * The goal a task states, or NULL when it states none.
   *
   * @return array{goal_text:string, gm_notes:string, open_resolution:bool}|null
   */
  private function normalizeTaskGoal(array $task): ?array {
    $goal_text = trim((string) ($task['goal_text'] ?? ''));
    if ($goal_text === '') {
      return NULL;
    }

    return [
      'goal_text' => $goal_text,
      'gm_notes' => trim((string) ($task['gm_notes'] ?? '')),
      'open_resolution' => $this->normalizeBranchTerminal($task['open_resolution'] ?? FALSE),
    ];
  }

  /**
   * A choice's approach fields in the shape QuestGoalContract reads.
   */
  private function normalizeChoiceApproach(array $approach): array {
    $normalized = [
      'visibility' => (string) ($approach['visibility'] ?? ''),
      'outcome_label' => trim((string) ($approach['outcome_label'] ?? '')),
      'objective_ids' => $this->normalizeCsvReferences($approach['objective_ids'] ?? []),
    ];
    if (isset($approach['reveal_when'])) {
      $normalized['reveal_when'] = $approach['reveal_when'];
    }
    if (isset($approach['rewards'])) {
      $normalized['rewards'] = $approach['rewards'];
    }

    return $normalized;
  }

  private function normalizeBranchTerminal(mixed $value): bool {
    if (is_bool($value)) {
      return $value;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'terminal'], TRUE);
  }

  /**
   * Normalize one deterministic choice trigger.
   */
  private function normalizeBranchTrigger(mixed $trigger): array {
    $trigger = is_array($trigger) ? $trigger : [];
    return [
      'type' => trim((string) ($trigger['type'] ?? 'player_choice')) ?: 'player_choice',
      'condition' => trim((string) ($trigger['condition'] ?? '')),
      'interaction' => trim((string) ($trigger['interaction'] ?? 'other')) ?: 'other',
      'source_id' => trim((string) ($trigger['source_id'] ?? '')),
    ];
  }

  /**
   * Normalize actor, environment, and storyline effects for one choice.
   */
  private function normalizeBranchEffects(mixed $effects): array {
    $effects = is_array($effects) ? $effects : [];
    $normalize_state_changes = static function (mixed $changes): array {
      $normalized = [];
      foreach (array_values(array_filter((array) $changes, 'is_array')) as $change) {
        $normalized[] = [
          'key' => trim((string) ($change['key'] ?? '')),
          'operation' => trim((string) ($change['operation'] ?? 'set')) ?: 'set',
          'value' => $change['value'] ?? NULL,
        ];
      }
      return $normalized;
    };
    $actors = [];
    foreach (array_values(array_filter((array) ($effects['actors'] ?? []), 'is_array')) as $effect) {
      $actors[] = [
        'actor_id' => trim((string) ($effect['actor_id'] ?? '')),
        'interaction' => trim((string) ($effect['interaction'] ?? 'other')) ?: 'other',
        'reaction' => trim((string) ($effect['reaction'] ?? '')),
        'effect' => trim((string) ($effect['effect'] ?? '')),
        'state_changes' => $normalize_state_changes($effect['state_changes'] ?? []),
      ];
    }
    $environment = [];
    foreach (array_values(array_filter((array) ($effects['environment'] ?? []), 'is_array')) as $effect) {
      $environment[] = [
        'target_type' => trim((string) ($effect['target_type'] ?? 'location')) ?: 'location',
        'target_id' => trim((string) ($effect['target_id'] ?? '')),
        'effect' => trim((string) ($effect['effect'] ?? '')),
        'state_changes' => $normalize_state_changes($effect['state_changes'] ?? []),
      ];
    }
    $storyline = [];
    foreach (array_values(array_filter((array) ($effects['storyline'] ?? []), 'is_array')) as $effect) {
      $storyline[] = [
        'target_type' => trim((string) ($effect['target_type'] ?? 'storyline')) ?: 'storyline',
        'target_id' => trim((string) ($effect['target_id'] ?? '')),
        'source_actor_id' => trim((string) ($effect['source_actor_id'] ?? '')),
        'operation' => trim((string) ($effect['operation'] ?? 'set')) ?: 'set',
        'effect' => trim((string) ($effect['effect'] ?? '')),
        'follow_up_required' => $this->normalizeBranchTerminal($effect['follow_up_required'] ?? FALSE),
        'state_changes' => $normalize_state_changes($effect['state_changes'] ?? []),
      ];
    }
    return [
      'summary' => trim((string) ($effects['summary'] ?? '')),
      'actors' => $actors,
      'environment' => $environment,
      'storyline' => $storyline,
    ];
  }

  /**
   * Normalize a comma-delimited or array reference list.
   */
  private function normalizeCsvReferences(mixed $value): array {
    $values = is_array($value) ? $value : explode(',', (string) $value);
    return array_values(array_unique(array_filter(array_map(
      static fn(mixed $item): string => trim((string) $item),
      $values
    ))));
  }

  /**
   * Apply canonical process-flow row edits for existing templates.
   */
  private function applyCanonicalTemplateProcessFlowEdits(array $payload, array $template): array {
    $journal_rows = array_values(array_filter((array) ($payload['process_flow']['journal_entries'] ?? []), 'is_array'));
    $task_rows = array_values(array_filter((array) ($payload['process_flow']['tasks'] ?? []), 'is_array'));
    $item_rows = array_values(array_filter((array) ($payload['objects']['items']['generation_specs'] ?? []), 'is_array'));

    $chapters = array_values(array_filter((array) ($template['chapters'] ?? []), 'is_array'));
    if ($chapters === []) {
      throw new \InvalidArgumentException('storyline_draft_chapter_required');
    }
    $chapter_indexes = [];
    $existing_scenes = [];
    foreach ($chapters as $chapter_index => &$chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      $chapter_indexes[$chapter_id] = $chapter_index;
      foreach (array_values(array_filter((array) ($chapter['scenes'] ?? []), 'is_array')) as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id !== '') {
          $existing_scenes[$scene_id] = [
            'chapter_id' => $chapter_id,
            'scene' => $scene,
          ];
        }
      }
      $chapter['scenes'] = [];
      $chapter['quest_ids'] = [];
    }
    unset($chapter);

    $tasks_by_scene = [];
    foreach ($task_rows as $task_index => $row) {
      $task_id = trim((string) ($row['task_id'] ?? ''));
      $scene_id = trim((string) ($row['scene_id'] ?? $journal_rows[$task_index]['scene_id'] ?? ''));
      if ($task_id === '' || $scene_id === '') {
        throw new \InvalidArgumentException('storyline_draft_task_scene_required');
      }
      $tasks_by_scene[$scene_id][] = $task_id;
    }
    foreach ($item_rows as $row) {
      $task_id = trim((string) ($row['quest_id'] ?? ''));
      $scene_id = trim((string) ($row['scene_id'] ?? ''));
      if ($task_id !== '' && $scene_id !== '') {
        $tasks_by_scene[$scene_id][] = $task_id;
      }
    }

    $seen_scene_ids = [];
    foreach ($journal_rows as $index => $row) {
      $scene_id = trim((string) ($row['scene_id'] ?? ''));
      if ($scene_id === '') {
        throw new \InvalidArgumentException('storyline_draft_scene_id_required');
      }
      if (isset($seen_scene_ids[$scene_id])) {
        throw new \InvalidArgumentException('storyline_draft_scene_id_duplicate');
      }
      $seen_scene_ids[$scene_id] = TRUE;
      $existing = is_array($existing_scenes[$scene_id]['scene'] ?? NULL) ? $existing_scenes[$scene_id]['scene'] : [];
      $chapter_id = trim((string) ($row['chapter_id'] ?? $existing_scenes[$scene_id]['chapter_id'] ?? ''));
      if ($chapter_id === '') {
        $chapter_id = (string) array_key_first($chapter_indexes);
      }
      if (!isset($chapter_indexes[$chapter_id])) {
        throw new \InvalidArgumentException("storyline_draft_unknown_chapter:{$chapter_id}");
      }
      $trigger = trim((string) ($row['trigger'] ?? ''));
      $gates = array_values(array_filter((array) ($existing['gates'] ?? []), 'is_array'));
      if ($trigger === '') {
        $gates = [];
      }
      else {
        $gates[0] = is_array($gates[0] ?? NULL) ? $gates[0] : [];
        $gates[0]['trigger'] = $trigger;
        $gates[0]['progression_gate'] = $trigger;
      }
      $scene = $existing + [
        'asset_references' => [],
      ];
      $scene['scene_id'] = $scene_id;
      $scene['name'] = trim((string) ($row['title'] ?? '')) ?: ('Scene ' . ($index + 1));
      $scene['summary'] = trim((string) ($row['summary'] ?? ''));
      $scene['order'] = (int) ($row['order'] ?? $index);
      $scene['quest_ids'] = array_values(array_unique($tasks_by_scene[$scene_id] ?? []));
      $scene['gates'] = $gates;
      if (array_key_exists('room_ids', $row)) {
        $scene['asset_references'] = array_values(array_filter(
          (array) ($scene['asset_references'] ?? []),
          static fn(mixed $reference): bool => !is_array($reference)
            || strtolower(trim((string) ($reference['asset_type'] ?? ''))) !== 'room'
        ));
        foreach ($this->normalizeCsvReferences($row['room_ids']) as $room_id) {
          $scene['asset_references'][] = [
            'asset_type' => 'room',
            'asset_id' => $room_id,
            'asset_role' => 'scene-room',
            'source_scope' => 'scene',
          ];
        }
      }
      $chapter_index = $chapter_indexes[$chapter_id];
      $chapters[$chapter_index]['scenes'][] = $scene;
      $chapters[$chapter_index]['quest_ids'] = array_values(array_unique(array_merge(
        (array) ($chapters[$chapter_index]['quest_ids'] ?? []),
        $scene['quest_ids']
      )));
    }

    $template['chapters'] = $chapters;
    return $template;
  }

  /**
   * Build canonical narrative-analysis metadata from structured draft rows.
   */
  private function buildStorylineAnalysisMetadata(array $payload, array $scenes): array {
    $journal_rows = array_values(array_filter((array) ($payload['process_flow']['journal_entries'] ?? []), 'is_array'));
    $scene_annotations = [];
    foreach ($journal_rows as $index => $row) {
      $canonical_scene = is_array($scenes[$index] ?? NULL) ? $scenes[$index] : [];
      $scene_id = trim((string) ($row['scene_id'] ?? $canonical_scene['scene_id'] ?? ''));
      if ($scene_id === '') {
        continue;
      }
      $themes = $this->normalizeCsvReferences($row['themes'] ?? []);
      $character_ids = $this->normalizeCsvReferences($row['character_ids'] ?? []);
      $foreshadows_scene_id = trim((string) ($row['foreshadows_scene_id'] ?? ''));
      $scene_annotations[$scene_id] = [
        'summary' => trim((string) ($row['summary'] ?? $canonical_scene['summary'] ?? '')),
        'central_conflict' => trim((string) ($row['central_conflict'] ?? '')),
        'dramatic_function' => trim((string) ($row['dramatic_function'] ?? '')),
        'themes' => $themes,
        'character_ids' => $character_ids,
        'foreshadowing' => $foreshadows_scene_id !== '' ? [[
          'target_scene_id' => $foreshadows_scene_id,
          'description' => trim((string) ($row['foreshadowing_note'] ?? '')),
        ]] : [],
        'relationship_changes' => array_values(array_filter((array) ($row['relationship_changes'] ?? []), 'is_array')),
      ];
    }
    return [
      'schema_version' => 'storyline-narrative-authoring-v1',
      'scene_annotations' => $scene_annotations,
      'conflicts' => array_values(array_filter((array) ($payload['process_flow']['conflicts'] ?? []), 'is_array')),
      'branch_choices' => array_values(array_filter((array) ($payload['process_flow']['branch_choices'] ?? []), 'is_array')),
    ];
  }

  /**
   * Flatten canonical chapter scenes in authoring order.
   */
  private function collectCanonicalScenes(array $template): array {
    $scenes = [];
    foreach (array_values(array_filter((array) ($template['chapters'] ?? []), 'is_array')) as $chapter) {
      foreach (array_values(array_filter((array) ($chapter['scenes'] ?? []), 'is_array')) as $scene) {
        $scenes[] = $scene;
      }
    }
    return $scenes;
  }

  /**
   * Apply canonical object row edits for contacts and asset references.
   */
  private function applyCanonicalTemplateObjectEdits(array $payload, array $template): array {
    $actor_rows = array_values(array_filter((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'is_array'));
    $dungeon_rows = array_values(array_filter((array) ($payload['objects']['dungeons']['generation_specs'] ?? []), 'is_array'));
    $room_rows = array_values(array_filter((array) ($payload['objects']['rooms']['generation_specs'] ?? []), 'is_array'));
    $item_rows = array_values(array_filter((array) ($payload['objects']['items']['generation_specs'] ?? []), 'is_array'));

    $existing_contacts = [];
    foreach (array_values(array_filter((array) ($template['contacts'] ?? []), 'is_array')) as $contact) {
      $contact_id = trim((string) ($contact['contact_id'] ?? ''));
      if ($contact_id === '') {
        throw new \InvalidArgumentException('storyline_template_contact_id_required');
      }
      if (isset($existing_contacts[$contact_id])) {
        throw new \InvalidArgumentException("storyline_template_contact_id_duplicate:{$contact_id}");
      }
      $existing_contacts[$contact_id] = $contact;
    }
    $contacts = [];
    foreach ($actor_rows as $row) {
      $actor_id = trim((string) ($row['actor_id'] ?? ''));
      if ($actor_id === '') {
        throw new \InvalidArgumentException('storyline_draft_actor_id_required');
      }
      $contact_id = $this->sanitizeIdentifier((string) ($row['contact_id'] ?? ('contact-' . $actor_id)));
      if ($contact_id === '') {
        throw new \InvalidArgumentException('storyline_draft_contact_id_required');
      }
      if (isset($contacts[$contact_id])) {
        throw new \InvalidArgumentException("storyline_draft_contact_id_duplicate:{$contact_id}");
      }
      $contact = is_array($existing_contacts[$contact_id] ?? NULL) ? $existing_contacts[$contact_id] : [
        'contact_id' => $contact_id,
        'entity_type' => 'campaign_npc',
        'entity_id' => $actor_id,
        'display_name' => $actor_id,
        'attitude' => 'indifferent',
        'availability' => 'available',
        'relationship_state' => [],
        'introduces_to' => [],
      ];
      $contact['entity_id'] = $actor_id;
      $contact['contact_id'] = $contact_id;
      $contact['role'] = trim((string) ($row['role'] ?? '')) ?: 'contact';
      $contact['display_name'] = trim((string) ($row['display_name'] ?? $contact['display_name'] ?? $actor_id)) ?: $actor_id;
      $contact['attitude'] = trim((string) ($row['attitude'] ?? $contact['attitude'] ?? 'indifferent')) ?: 'indifferent';
      $contact['availability'] = trim((string) ($row['availability'] ?? $contact['availability'] ?? 'available')) ?: 'available';
      $contact['notes'] = trim((string) ($row['motivation'] ?? ''));
      if (!is_array($contact['relationship_state'] ?? NULL)) {
        $contact['relationship_state'] = [];
      }
      $contact['relationship_state']['affiliations'] = $this->contactAffiliations($row, $payload);
      $contact['relationship_state']['authored_affiliations'] = array_values(array_filter((array) ($row['affiliations'] ?? []), 'is_array'));
      // Authored actor relationships replace the ones this draft authored
      // before; introductions from any other source are kept.
      $contact['introduces_to'] = array_values(array_merge(
        array_filter((array) ($contact['introduces_to'] ?? []), static fn($link): bool => is_array($link) && ($link['relationship_state']['authored_in'] ?? '') !== 'storyline_actor_relationships'),
        $this->contactRelationships($actor_id, $payload),
      ));
      $contact['relationship_state']['chapter_id'] = trim((string) ($row['chapter_id'] ?? ''));
      $contact['relationship_state']['scene_id'] = trim((string) ($row['scene_id'] ?? ''));
      $contact['relationship_state']['location_id'] = trim((string) ($row['location_id'] ?? ''));
      $contact['relationship_state']['runtime_materialization'] = is_array($contact['relationship_state']['runtime_materialization'] ?? NULL)
        ? $contact['relationship_state']['runtime_materialization']
        : [];
      $contact['relationship_state']['runtime_materialization']['canonical_actor_id'] = $actor_id;
      $contact['relationship_state']['runtime_materialization']['canonical_location_id'] = trim((string) ($row['location_id'] ?? ''));
      $contact['relationship_state']['authored_details'] = [
        'dialogue' => trim((string) ($row['dialogue'] ?? '')),
        'knowledge' => trim((string) ($row['knowledge'] ?? '')),
      ];
      $contacts[$contact_id] = $contact;
    }
    $template['contacts'] = array_values($contacts);

    $managed_types = ['npc', 'dungeon', 'room', 'item'];
    $asset_refs = [];
    $existing_refs = [];
    foreach (array_values(array_filter((array) ($template['asset_references'] ?? []), 'is_array')) as $reference) {
      $asset_type = strtolower(trim((string) ($reference['asset_type'] ?? '')));
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if (in_array($asset_type, $managed_types, TRUE)) {
        $existing_refs[$asset_type][$asset_id] = $reference;
      }
      else {
        $asset_refs[] = $reference;
      }
    }
    foreach ($actor_rows as $row) {
      $actor_id = trim((string) ($row['actor_id'] ?? ''));
      $reference = $this->buildAuthoredAssetReference(
        is_array($existing_refs['npc'][$actor_id] ?? NULL) ? $existing_refs['npc'][$actor_id] : [],
        'npc',
        $actor_id,
        trim((string) ($row['role'] ?? '')) ?: 'supporting-actor'
      );
      $reference['chapter_id'] = trim((string) ($row['chapter_id'] ?? ''));
      $reference['scene_id'] = trim((string) ($row['scene_id'] ?? ''));
      $reference['link_data']['location_id'] = trim((string) ($row['location_id'] ?? ''));
      $asset_refs[] = $reference;
    }
    foreach ($dungeon_rows as $row) {
      $dungeon_id = trim((string) ($row['dungeon_id'] ?? ''));
      if ($dungeon_id === '') {
        throw new \InvalidArgumentException('storyline_draft_dungeon_id_required');
      }
      $purpose = trim((string) ($row['purpose'] ?? ''));
      $asset_refs[] = $this->buildAuthoredAssetReference(
        is_array($existing_refs['dungeon'][$dungeon_id] ?? NULL) ? $existing_refs['dungeon'][$dungeon_id] : [],
        'dungeon',
        $dungeon_id,
        $this->normalizeAssetRole($purpose, 'story-dungeon')
      );
      $last_index = array_key_last($asset_refs);
      $asset_refs[$last_index]['chapter_id'] = trim((string) ($row['chapter_id'] ?? ''));
      $asset_refs[$last_index]['link_data']['entrance_room_id'] = trim((string) ($row['entrance_room'] ?? ''));
      $asset_refs[$last_index]['link_data']['purpose'] = $purpose;
    }
    foreach ($room_rows as $row) {
      $room_id = trim((string) ($row['room_id'] ?? ''));
      if ($room_id === '') {
        throw new \InvalidArgumentException('storyline_draft_room_id_required');
      }
      $chapter_id = trim((string) ($row['chapter_id'] ?? ''));
      $scene_id = trim((string) ($row['scene_id'] ?? ''));
      $dungeon_id = trim((string) ($row['dungeon_id'] ?? ''));
      $narrative = trim((string) ($row['narrative'] ?? ''));
      $reference = $this->buildAuthoredAssetReference(
        is_array($existing_refs['room'][$room_id] ?? NULL) ? $existing_refs['room'][$room_id] : [],
        'room',
        $room_id,
        $narrative === 'connector-room'
          ? 'road-connector'
          : $this->normalizeAssetRole($narrative, 'story-room')
      );
      $reference['chapter_id'] = $chapter_id;
      $reference['scene_id'] = $scene_id;
      $reference['link_data']['dungeon_id'] = $dungeon_id;
      $reference['link_data']['narrative'] = $narrative;
      $asset_refs[] = $reference;
    }
    foreach ($item_rows as $row) {
      $item_id = trim((string) ($row['item_id'] ?? ''));
      if ($item_id === '') {
        throw new \InvalidArgumentException('storyline_draft_item_id_required');
      }
      $purpose = trim((string) ($row['purpose'] ?? ''));
      $acquisition = trim((string) ($row['acquisition'] ?? ''));
      $reference = $this->buildAuthoredAssetReference(
        is_array($existing_refs['item'][$item_id] ?? NULL) ? $existing_refs['item'][$item_id] : [],
        'item',
        $item_id,
        $this->normalizeAssetRole($purpose, 'story-item')
      );
      unset($reference['content_id']);
      $reference['notes'] = $acquisition;
      $reference['chapter_id'] = trim((string) ($row['chapter_id'] ?? ''));
      $reference['scene_id'] = trim((string) ($row['scene_id'] ?? ''));
      $reference['link_data']['quest_id'] = trim((string) ($row['quest_id'] ?? ''));
      $reference['link_data']['actor_id'] = trim((string) ($row['actor_id'] ?? ''));
      $reference['link_data']['prerequisite_ids'] = $this->normalizeCsvReferences($row['prerequisite_ids'] ?? []);
      $reference['link_data']['reward'] = trim((string) ($row['reward'] ?? ''));
      $reference['link_data']['purpose'] = $purpose;
      $asset_refs[] = $reference;
    }
    $template['asset_references'] = $asset_refs;
    return $template;
  }

  /**
   * Synchronize linked_quests map with authored task rows.
   */
  private function synchronizeLinkedQuestsWithTasks(array $existing_linked_quests, array $task_rows): array {
    $linked_quests = [];
    foreach ($task_rows as $row) {
      $quest_id = trim((string) ($row['task_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      $existing = is_array($existing_linked_quests[$quest_id] ?? NULL) ? $existing_linked_quests[$quest_id] : [];
      $linked_quests[$quest_id] = array_replace($existing, [
        'quest_id' => $quest_id,
        'chapter_id' => trim((string) ($row['chapter_id'] ?? $existing['chapter_id'] ?? '')),
        'scene_id' => trim((string) ($row['scene_id'] ?? $existing['scene_id'] ?? '')),
        'status' => trim((string) ($row['status'] ?? $existing['status'] ?? 'available')) ?: 'available',
      ]);
    }
    return $linked_quests;
  }

  /**
   * Normalize an authored role/narrative/purpose string to the <=128 char asset_role contract.
   */
  private function normalizeAssetRole(string $raw, string $default): string {
    $raw = trim($raw);
    if ($raw === '') {
      return $default;
    }
    if (preg_match('/^([a-z0-9][a-z0-9_-]{0,63}):/i', $raw, $matches) === 1) {
      return strtolower($matches[1]);
    }
    if (mb_strlen($raw) <= 128) {
      return $raw;
    }
    return rtrim(mb_substr($raw, 0, 128));
  }

  /**
   * Complete one canonical asset reference from an authored object row.
   */
  private function buildAuthoredAssetReference(
    array $existing,
    string $asset_type,
    string $asset_id,
    string $asset_role
  ): array {
    return array_replace([
      'chapter_id' => '',
      'scene_id' => '',
      'source_scope' => 'asset_reference',
      'notes' => '',
      'link_data' => [],
    ], $existing, [
      'asset_type' => $asset_type,
      'asset_id' => $asset_id,
      'asset_role' => $asset_role,
    ]);
  }

  private function loadDraftRow(string $draft_id): ?array {
    $query = $this->database->select(self::TABLE, 'd');
    $query->leftJoin(self::REVISION_TABLE, 'r', 'r.draft_id = d.draft_id AND r.revision = d.revision');
    $query->leftJoin('users_field_data', 'editor', 'editor.uid = r.owner_uid AND editor.default_langcode = 1');
    $query->addField('r', 'owner_uid', 'updated_by_uid');
    $query->addField('editor', 'name', 'updated_by_name');
    $row = $query
      ->fields('d', [
        'draft_id',
        'owner_uid',
        'source_draft_id',
        'source_revision',
        'source_template_id',
        'revision',
        'status',
        'draft_payload',
        'payload_hash',
        'created_at',
        'updated_at',
        'published_template_id',
        'published_at',
        'publication_receipt',
      ])
      ->condition('d.draft_id', $draft_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }
    $payload = $this->decodePayload((string) ($row['draft_payload'] ?? ''));
    if (!is_array($payload['process_flow'] ?? NULL)) {
      $payload['process_flow'] = [];
    }
    $payload['process_flow']['narrative_architecture'] = $this->normalizeNarrativeArchitecture(
      $payload['process_flow']['narrative_architecture'] ?? []
    );
    return [
      'draft_id' => (string) $row['draft_id'],
      'owner_uid' => (int) $row['owner_uid'],
      'updated_by_uid' => (int) $row['updated_by_uid'],
      'updated_by_name' => (string) $row['updated_by_name'],
      'source_draft_id' => (string) ($row['source_draft_id'] ?? ''),
      'source_revision' => isset($row['source_revision']) ? (int) $row['source_revision'] : NULL,
      'source_template_id' => (string) ($row['source_template_id'] ?? ''),
      'revision' => (int) $row['revision'],
      'status' => (string) $row['status'],
      'created_at' => (int) $row['created_at'],
      'updated_at' => (int) $row['updated_at'],
      'published_template_id' => (string) ($row['published_template_id'] ?? ''),
      'published_at' => isset($row['published_at']) ? (int) $row['published_at'] : NULL,
      'publication_receipt' => !empty($row['publication_receipt'])
        ? $this->decodePayload((string) $row['publication_receipt'])
        : NULL,
      'payload_hash' => (string) ($row['payload_hash'] ?? ''),
      'payload' => $payload,
    ];
  }

  /**
   * Persists one immutable draft payload snapshot.
   */
  private function insertRevisionSnapshot(
    string $draft_id,
    int $revision,
    int $editor_uid,
    string $payload_json,
    string $payload_hash,
    int $created_at,
  ): void {
    $this->database->insert(self::REVISION_TABLE)
      ->fields([
        'draft_id' => $draft_id,
        'revision' => $revision,
        'owner_uid' => $editor_uid,
        'draft_payload' => $payload_json,
        'payload_hash' => $payload_hash,
        'created_at' => $created_at,
      ])
      ->execute();
  }

  /**
   * Return the complete empty narrative contract without inventing content.
   */
  private function emptyNarrativeArchitecture(): array {
    $beats = [];
    foreach (self::NARRATIVE_BEATS as $beat_key => $definition) {
      $beats[$beat_key] = array_fill_keys($definition['fields'], '');
    }
    return [
      'beats' => $beats,
      'adventure_requirements' => array_fill_keys(array_keys(self::ADVENTURE_REQUIREMENTS), ''),
    ];
  }

  /**
   * Assigns a derivative its own canonical identity before it can be published.
   */
  private function deriveOwnedDraftIdentity(array $payload, int $uid): array {
    $source_id = trim((string) ($payload['storyline_id'] ?? 'storyline'));
    $suffix = 'u' . $uid . '-' . substr($this->uuid->generate(), 0, 8);
    $payload['storyline_id'] = substr($this->sanitizeIdentifier($source_id . '-' . $suffix), 0, 191);
    return $payload;
  }

  /**
   * Adds caller-relative ownership capabilities to a draft projection.
   */
  private function projectOwnership(array $draft): array {
    $uid = (int) $this->currentUser->id();
    $is_owner = $uid > 0 && (int) ($draft['owner_uid'] ?? 0) === $uid;
    $draft['is_owner'] = $is_owner;
    $draft['can_edit'] = $uid > 0 && (string) ($draft['status'] ?? '') === 'editing';
    $draft['can_copy'] = (string) ($draft['status'] ?? '') !== 'editing';
    return $draft;
  }

  /**
   * Shared editing does not grant destructive management of another's draft.
   */
  private function assertCanManageDraft(array $draft): void {
    if ((int) $draft['owner_uid'] !== (int) $this->currentUser->id()) {
      throw new \UnexpectedValueException('storyline_draft_not_owner');
    }
  }

  /**
   * Keep only canonical narrative fields and normalize them to strings.
   */
  private function normalizeNarrativeArchitecture(mixed $value): array {
    $source = is_array($value) ? $value : [];
    $normalized = $this->emptyNarrativeArchitecture();
    foreach (self::NARRATIVE_BEATS as $beat_key => $definition) {
      $beat = is_array($source['beats'][$beat_key] ?? NULL) ? $source['beats'][$beat_key] : [];
      foreach ($definition['fields'] as $field) {
        $normalized['beats'][$beat_key][$field] = trim((string) ($beat[$field] ?? ''));
      }
    }
    foreach (self::ADVENTURE_REQUIREMENTS as $key => $label) {
      $normalized['adventure_requirements'][$key] = trim((string) (
        $source['adventure_requirements'][$key] ?? ''
      ));
    }
    return $normalized;
  }

  /**
   * Project narrative completion for status and synopsis.
   */
  private function narrativeArchitectureStatus(mixed $value): array {
    $architecture = $this->normalizeNarrativeArchitecture($value);
    $beat_checks = [];
    foreach (self::NARRATIVE_BEATS as $beat_key => $definition) {
      $beat_checks[$beat_key] = count(array_filter(
        $architecture['beats'][$beat_key],
        static fn(string $entry): bool => $entry !== '',
      )) === count($definition['fields']);
    }
    $requirement_checks = array_map(
      static fn(string $entry): bool => $entry !== '',
      $architecture['adventure_requirements'],
    );
    $missing = [];
    foreach ($beat_checks as $key => $complete) {
      if (!$complete) {
        $missing[] = $key;
      }
    }
    foreach ($requirement_checks as $key => $complete) {
      if (!$complete) {
        $missing[] = $key;
      }
    }
    return [
      'beats_complete' => count(array_filter($beat_checks)),
      'beats_total' => count($beat_checks),
      'requirements_complete' => count(array_filter($requirement_checks)),
      'requirements_total' => count($requirement_checks),
      'beat_checks' => $beat_checks,
      'requirement_checks' => $requirement_checks,
      'missing' => $missing,
      'complete' => $missing === [],
    ];
  }

  /**
   * Converts validator errors into API findings.
   */
  private function validationFindings(array $validation): array {
    $findings = [];
    foreach ((array) ($validation['stages'] ?? []) as $stage_key => $stage) {
      foreach ((array) ($stage['errors'] ?? []) as $error) {
        $findings[] = [
          'severity' => 'error',
          'stage' => (string) $stage_key,
          'message' => (string) $error,
        ];
      }
    }
    return $findings;
  }

  /**
   * Require the dedicated storyline authoring capability.
   */
  private function assertCanEdit(): void {
    if ((int) $this->currentUser->id() <= 0 || !$this->currentUser->hasPermission('edit canonical dungeoncrawler storylines')) {
      throw new \UnexpectedValueException('storyline_draft_access_denied');
    }
  }

  private function encodePayload(array $payload): string {
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }

  private function decodePayload(string $payload_json): array {
    try {
      $decoded = json_decode($payload_json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \RuntimeException('storyline_draft_payload_corrupt');
    }
    if (!is_array($decoded) || array_is_list($decoded)) {
      throw new \RuntimeException('storyline_draft_payload_corrupt');
    }
    return $decoded;
  }

}
