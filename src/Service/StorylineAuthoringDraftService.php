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
  ];

  private const TABLE = 'dungeoncrawler_content_storyline_authoring_drafts';
  private const REVISION_TABLE = 'dungeoncrawler_content_storyline_draft_revisions';
  private const REDIRECT_TABLE = 'dungeoncrawler_content_storyline_draft_redirects';
  private const OPERATION_TABLE = 'dungeoncrawler_content_storyline_draft_operations';
  public const OPERATION_CONTRACT = [
    'set' => ['required' => ['type', 'path', 'value']],
    'append' => ['required' => ['type', 'path', 'value']],
    'replace' => ['required' => ['type', 'path', 'match', 'value'], 'match' => ['field + value or fields']],
    'remove' => ['required' => ['type', 'path', 'match'], 'match' => ['field + value or fields']],
    'move' => ['required' => ['type', 'path', 'match', 'to_index'], 'match' => ['field + value or fields']],
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
  ) {}

  /**
   * Describe the exact operation objects accepted by the draft authority.
   */
  public static function operationContractDescription(): string {
    return 'Each item must use one exact shape: '
      . '{"type":"set","path":"/json/pointer","value":...}; '
      . '{"type":"append","path":"/collection","value":...}; '
      . '{"type":"replace","path":"/collection","match":{"field":"id_field","value":"id"},"value":...}; '
      . '{"type":"remove","path":"/collection","match":{"fields":{"id_field":"id","second_field":"value"}}}; '
      . '{"type":"move","path":"/collection","match":{"field":"id_field","value":"id"},"to_index":0}. '
      . 'The key is type, never operation or op. Paths are RFC 6901 JSON pointers. '
      . 'Use only paths present in the grounded storyline payload. A set path points to a property whose parent already exists. '
      . 'Append paths may point to a schema-declared collection omitted from the payload because it has no rows yet. '
      . 'Replace, remove, and move paths point to an existing collection. '
      . 'A match must identify exactly one row; when one field is not unique, use match.fields with enough exact field/value pairs to make it unique. '
      . 'Never add /- or an array index.';
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
      '/objects/dungeons/generation_specs' => 'dungeon',
      '/objects/rooms/generation_specs' => 'room',
      '/objects/items/generation_specs' => 'item',
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
        'action_interactions' => ['other', 'attacked', 'killed'],
        'types' => [
          'player_choice',
          'action',
          'objective_result',
          'skill_result',
          'actor_state',
          'environment_state',
          'item_state',
          'timer',
          'gm_decision',
        ],
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
        'environment_fields' => ['target_type', 'target_id', 'effect', 'state_changes'],
        'environment_field_types' => [
          'target_type' => 'enum',
          'target_id' => 'string',
          'effect' => 'string',
          'state_changes' => 'array',
        ],
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
        'storyline_operations' => [
          'unlock',
          'lock',
          'reveal',
          'conceal',
          'redirect',
          'escalate',
          'resolve',
          'set',
        ],
        'state_change_fields' => ['key', 'operation', 'value'],
        'state_change_field_types' => [
          'key' => 'string',
          'operation' => 'enum',
          'value' => 'scalar_or_structured_value',
        ],
        'state_change_operations' => ['set', 'increment', 'decrement', 'add', 'remove'],
      ],
      'invariants' => [
        'A decision has at least two choices on one source task.',
        'choice_id is unique across the storyline draft.',
        'A non-terminal choice names a target task and that task\'s scene.',
        'A terminal choice names neither a target task nor target scene.',
        'Actor or environment effects require at least one called-out storyline implication.',
        'Action triggers identify the affected actor in source_id and classify the interaction.',
        'Attacked or killed actors have an explicit reaction.',
        'Killing an actor has a storyline implication linked by source_actor_id.',
        'Choice destinations are authoritative; unlock graph edges are derived from them.',
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
        ->condition('owner_uid', $uid)
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
      $source_author_uid = (int) ($template_data['metadata']['authorship']['author_uid'] ?? 0);
      if ($source_author_uid !== $uid) {
        $payload = $this->deriveOwnedCopyIdentity($payload, $uid);
      }
    }

    return $this->insertDraft($uid, $payload, $template_id, NULL);
  }

  /**
   * Lists drafts every storyline editor may load, with ownership metadata.
   */
  public function listDrafts(): array {
    $this->assertCanEdit();
    $uid = (int) $this->currentUser->id();
    $query = $this->database->select(self::TABLE, 'd');
    $query->leftJoin('users_field_data', 'u', 'u.uid = d.owner_uid AND u.default_langcode = 1');
    $query->fields('d', ['draft_id', 'owner_uid', 'source_draft_id', 'source_revision', 'source_template_id', 'published_template_id', 'revision', 'status', 'draft_payload', 'created_at', 'updated_at']);
    $query->addField('u', 'name', 'owner_name');
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
      return [
        'draft_id' => (string) $row['draft_id'],
        'owner_uid' => (int) $row['owner_uid'],
        'owner_name' => (string) ($row['owner_name'] ?? ('User ' . $row['owner_uid'])),
        'is_owner' => (int) $row['owner_uid'] === $uid,
        'can_edit' => (int) $row['owner_uid'] === $uid && (string) $row['status'] === 'editing',
        'can_copy' => (int) $row['owner_uid'] !== $uid || (string) $row['status'] !== 'editing',
        'source_draft_id' => (string) ($row['source_draft_id'] ?? ''),
        'source_revision' => isset($row['source_revision']) ? (int) $row['source_revision'] : NULL,
        'source_template_id' => (string) ($row['source_template_id'] ?? ''),
        'published_template_id' => (string) ($row['published_template_id'] ?? ''),
        'revision' => (int) $row['revision'],
        'revision_count' => (int) $row['owner_uid'] === $uid
          ? ($revision_counts[(string) $row['draft_id']] ?? 0)
          : 1,
        'status' => (string) $row['status'],
        'title' => (string) ($payload['title'] ?? $payload['storyline_id'] ?? 'Untitled storyline'),
        'storyline_id' => (string) ($payload['storyline_id'] ?? ''),
        'created_at' => (int) $row['created_at'],
        'updated_at' => (int) $row['updated_at'],
      ];
    }, $rows);
  }

  /**
   * Lists one selector entry per canonical storyline plus unbound drafts.
   */
  public function listStorylineOptions(): array {
    $drafts = $this->listDrafts();
    $mapped_draft_ids = [];
    $options = [];
    foreach ($this->storylineManager->listTemplates() as $template) {
      $template_id = trim((string) ($template['template_id'] ?? ''));
      if ($template_id === '') {
        continue;
      }
      $candidates = array_values(array_filter($drafts, static fn(array $draft): bool =>
        (string) ($draft['source_template_id'] ?? '') === $template_id
        || (string) ($draft['published_template_id'] ?? '') === $template_id
      ));
      usort($candidates, static fn(array $left, array $right): int =>
        [(int) !empty($right['is_owner']), (int) ($right['updated_at'] ?? 0)]
        <=> [(int) !empty($left['is_owner']), (int) ($left['updated_at'] ?? 0)]
      );
      $draft = $candidates[0] ?? NULL;
      if (is_array($draft)) {
        $mapped_draft_ids[(string) $draft['draft_id']] = TRUE;
        $draft['selector_type'] = 'draft';
        $draft['template_id'] = $template_id;
        $options[] = $draft;
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
    foreach ($drafts as $draft) {
      $draft_id = (string) ($draft['draft_id'] ?? '');
      $template_id = (string) ($draft['source_template_id'] ?? '');
      if ($draft_id === '' || isset($mapped_draft_ids[$draft_id]) || $template_id !== '') {
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
  public function findLatestDraftForTemplate(string $template_id): ?array {
    $this->assertCanEdit();
    $template_id = trim($template_id);
    if ($template_id === '') {
      throw new \InvalidArgumentException('storyline_template_id_required');
    }
    $query = $this->database->select(self::TABLE, 'd');
    $template_match = $query->orConditionGroup()
      ->condition('source_template_id', $template_id)
      ->condition('published_template_id', $template_id);
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
    $rows = $this->database->select(self::REVISION_TABLE, 'r')
      ->fields('r', ['revision', 'payload_hash', 'created_at'])
      ->condition('draft_id', $draft_id)
      ->orderBy('revision', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => [
      'revision' => (int) $row['revision'],
      'payload_hash' => (string) $row['payload_hash'],
      'created_at' => (int) $row['created_at'],
      'is_current' => (int) $row['revision'] === (int) $draft['revision'],
    ], $rows);
  }

  /**
   * Loads one immutable shared draft snapshot without mutation authority.
   */
  public function getDraftRevisionForViewing(string $draft_id, int $revision): array {
    $draft = $this->getDraft($draft_id);
    $row = $this->database->select(self::REVISION_TABLE, 'r')
      ->fields('r', ['revision', 'draft_payload', 'payload_hash', 'created_at'])
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
      ->countQuery()
      ->execute()
      ->fetchField();
    $mine = (int) $this->database->select(self::TABLE, 'd')
      ->condition('owner_uid', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();
    $editing = (int) $this->database->select(self::TABLE, 'd')
      ->condition('owner_uid', $uid)
      ->condition('status', 'editing')
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
      'my_saved_versions' => $versions,
    ];
  }

  /**
   * Permanently deletes one owner-bound draft and its private history.
   */
  public function deleteDraft(string $draft_id): array {
    $draft = $this->getDraft($draft_id);
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
    $payload = $this->deriveOwnedCopyIdentity((array) $source['payload'], $uid);
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
    $now = $this->time->getRequestTime();
    $payload_json = $this->encodePayload($payload);
    $payload_hash = hash('sha256', $payload_json);
    $draft = [
      'draft_id' => $draft_id,
      'owner_uid' => $uid,
      'source_draft_id' => $source_draft_id ?? '',
      'source_revision' => $source_revision,
      'source_template_id' => $template_id,
      'revision' => 1,
      'status' => 'editing',
      'created_at' => $now,
      'updated_at' => $now,
      'payload' => $payload,
      'payload_hash' => $payload_hash,
    ];
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
    return $this->projectOwnership($draft);
  }

  /**
   * Load an existing draft with strict owner enforcement.
   */
  public function getDraft(string $draft_id): array {
    $this->assertCanEdit();
    $draft = $this->loadDraftRow($draft_id);
    if ($draft === NULL) {
      throw new \OutOfBoundsException('storyline_draft_not_found');
    }
    $uid = (int) $this->currentUser->id();
    if ($uid <= 0 || (int) ($draft['owner_uid'] ?? 0) !== $uid) {
      throw new \UnexpectedValueException('storyline_draft_not_owner');
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
    $validation['stages']['authored_room_dungeon_bindings'] = [
      'valid' => $binding_errors === [],
      'errors' => $binding_errors,
    ];
    $stages = is_array($validation['stages'] ?? NULL) ? $validation['stages'] : [];
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
        'valid' => $errors === [],
        'error_count' => count($errors),
        'warning_count' => count($stage_warnings),
        'errors' => $errors,
        'warnings' => $stage_warnings,
        'findings' => array_values(array_filter((array) ($stage_result['findings'] ?? []), 'is_array')),
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
        'decisions' => count(array_filter(
          $decision_choice_counts,
          static fn(int $choice_count): bool => $choice_count >= 2,
        )),
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
          $counts['decisions'] > 0 && $counts['choices'] >= 2,
        ], array_values($narrative['beat_checks']), array_values($narrative['requirement_checks'])),
        3 => [
          $counts['actors'] > 0,
          $all_have($rows($objects['actors']['generation_specs'] ?? []), ['contact_id', 'actor_id', 'role', 'display_name']),
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
        3 => ['actors' => $counts['actors']],
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
      $next_steps = [];
      if ($missing_content !== []) {
        $next_steps[] = 'Complete: ' . implode(', ', array_slice($missing_content, 0, 4)) . '.';
      }
      if (($validation['valid'] ?? FALSE) !== TRUE) {
        $next_steps[] = sprintf('Resolve %d canonical validation error(s).', (int) ($validation['error_count'] ?? 0));
      }
      $warning_count = (int) ($validation['warning_count'] ?? 0);
      if ($warning_count > 0) {
        $next_steps[] = sprintf(
          'Resolve %d canonical validation warning(s), including any missing referenced assets.',
          $warning_count
        );
      }
      if ($current_stage <= 6) {
        $next_steps[] = "Continue editing Stage {$current_stage}.";
      }
      elseif ($warning_count === 0) {
        $next_steps[] = 'Review the complete canonical object and create the storyline when satisfied.';
      }

    return [
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
        'next_steps' => array_slice($next_steps, 0, 3),
    ];
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
      (int) $draft['owner_uid'],
      $payload_json,
      $payload_hash,
      $updated_at,
    );
    $draft['revision'] = $next_revision;
    $draft['updated_at'] = $updated_at;
    $draft['payload'] = $payload;
    $draft['payload_hash'] = $payload_hash;
    return $draft;
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
    if (($after['canonical_template'] ?? NULL) !== ($before['canonical_template'] ?? NULL)) {
      throw new \InvalidArgumentException('storyline_draft_canonical_source_immutable');
    }
    $this->assertDraftPayloadValid($after);

    return [
      'draft_id' => $draft_id,
      'expected_revision' => $expected_revision,
      'result_revision' => $expected_revision + ($changes === [] ? 0 : 1),
      'has_changes' => $changes !== [],
      'changes' => $changes,
      'payload' => $after,
    ];
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
      ->condition('owner_uid', (int) $this->currentUser->id())
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
    return $result;
  }

  /**
   * Publish a saved draft as a canonical storyline template.
   */
  public function publishDraft(string $draft_id, int $expected_revision): array {
    if (!$this->currentUser->hasPermission('publish canonical dungeoncrawler storylines')) {
      throw new \UnexpectedValueException('storyline_draft_publish_access_denied');
    }
    $draft = $this->getDraft($draft_id);
    if (($draft['status'] ?? '') === 'published') {
      throw new \DomainException('storyline_draft_already_published');
    }
    if (($draft['status'] ?? '') !== 'editing') {
      throw new \DomainException('storyline_draft_not_publishable');
    }
    $current_revision = (int) ($draft['revision'] ?? 0);
    if ($expected_revision !== $current_revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
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
      'publication_receipt' => $publication_receipt,
    ];
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
        'introduces_to' => [],
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
        $asset_references[] = [
          'asset_type' => 'room',
          'asset_id' => $room_id,
          'asset_role' => trim((string) ($room['narrative'] ?? '')) === 'connector-room'
            ? 'road-connector'
            : 'story-room',
          'chapter_id' => trim((string) ($room['chapter_id'] ?? '')),
          'scene_id' => trim((string) ($room['scene_id'] ?? '')),
          'link_data' => [
            'dungeon_id' => trim((string) ($room['dungeon_id'] ?? '')),
          ],
          'source_scope' => 'asset_reference',
        ];
      }
    }
    foreach ($dungeons as $dungeon) {
      $dungeon_id = trim((string) ($dungeon['dungeon_id'] ?? ''));
      if ($dungeon_id !== '') {
        $asset_references[] = [
          'asset_type' => 'dungeon',
          'asset_id' => $dungeon_id,
          'asset_role' => trim((string) ($dungeon['purpose'] ?? '')) ?: 'story-dungeon',
          'chapter_id' => trim((string) ($dungeon['chapter_id'] ?? '')),
          'source_scope' => 'asset_reference',
          'link_data' => [
            'entrance_room_id' => trim((string) ($dungeon['entrance_room'] ?? '')),
          ],
        ];
      }
    }
    foreach ($items as $item) {
      $item_id = trim((string) ($item['item_id'] ?? ''));
      if ($item_id !== '') {
        $asset_references[] = [
          'asset_type' => 'item',
          'asset_id' => $item_id,
          'asset_role' => trim((string) ($item['purpose'] ?? '')) ?: 'story-item',
          'notes' => trim((string) ($item['acquisition'] ?? '')),
          'chapter_id' => trim((string) ($item['chapter_id'] ?? '')),
          'scene_id' => trim((string) ($item['scene_id'] ?? '')),
          'link_data' => [
            'quest_id' => trim((string) ($item['quest_id'] ?? '')),
            'actor_id' => trim((string) ($item['actor_id'] ?? '')),
            'prerequisite_ids' => $this->normalizeCsvReferences($item['prerequisite_ids'] ?? []),
            'reward' => trim((string) ($item['reward'] ?? '')),
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
        ];
        foreach (array_values(array_filter((array) ($node['choices'] ?? []), 'is_array')) as $choice) {
          $branch_rows[] = [
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
    foreach (array_values(array_filter((array) ($template_data['contacts'] ?? []), 'is_array')) as $contact) {
      $actors[] = [
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
          'purpose' => (string) ($reference['asset_role'] ?? ''),
          'entrance_room' => (string) ($reference['link_data']['entrance_room_id'] ?? ''),
        ];
      }
      elseif ($asset_type === 'room') {
        $rooms[] = [
          'dungeon_id' => (string) ($reference['link_data']['dungeon_id'] ?? ''),
          'chapter_id' => (string) ($reference['chapter_id'] ?? ''),
          'scene_id' => (string) ($reference['scene_id'] ?? ''),
          'room_id' => (string) ($reference['asset_id'] ?? ''),
          'narrative' => (string) ($reference['asset_role'] ?? ''),
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
        'purpose' => (string) ($reference['asset_role'] ?? ''),
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
    $payload['objects']['actors']['validation_criteria'] = array_values((array) ($builder['actor_validation_criteria'] ?? []));
    $payload['objects']['dungeons']['generation_specs'] = $dungeons;
    $payload['objects']['rooms']['generation_specs'] = $rooms;
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
    $template['questline'] = $this->buildQuestlineFromTaskRows(
      array_values(array_filter((array) ($payload['process_flow']['tasks'] ?? []), 'is_array')),
      array_values(array_filter((array) ($payload['process_flow']['branch_choices'] ?? []), 'is_array')),
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
        'unlocks_after' => $this->normalizeCsvReferences($task['unlocks_after'] ?? []),
        'unlocks_to' => $this->normalizeCsvReferences($task['unlocks_to'] ?? []),
        'unlock_condition' => trim((string) ($task['unlock_condition'] ?? '')),
        'prerequisite_mode' => 'all',
        'choices' => [],
      ];
    }
    $node_indexes = [];
    foreach ($nodes as $index => $node) {
      $node_indexes[$node['quest_id']] = $index;
    }
    foreach (array_values(array_filter($branch_choices, 'is_array')) as $choice) {
      $source_quest_id = trim((string) ($choice['source_task_id'] ?? ''));
      if ($source_quest_id === '' || !isset($node_indexes[$source_quest_id])) {
        continue;
      }
      $target_quest_id = trim((string) ($choice['target_task_id'] ?? ''));
      $terminal = $this->normalizeBranchTerminal($choice['terminal'] ?? FALSE);
      $nodes[$node_indexes[$source_quest_id]]['choices'][] = [
        'decision_id' => trim((string) ($choice['decision_id'] ?? '')),
        'choice_id' => trim((string) ($choice['choice_id'] ?? '')),
        'label' => trim((string) ($choice['label'] ?? '')),
        'trigger' => $this->normalizeBranchTrigger($choice['trigger'] ?? []),
        'effects' => $this->normalizeBranchEffects($choice['effects'] ?? []),
        'target_quest_id' => $target_quest_id,
        'target_scene_id' => trim((string) ($choice['target_scene_id'] ?? '')),
        'terminal' => $terminal,
      ];
    }
    $has_choices = FALSE;
    foreach ($nodes as &$node) {
      if ($node['choices'] === []) {
        continue;
      }
      $has_choices = TRUE;
      $node['unlocks_to'] = array_values(array_unique(array_filter(array_map(
        static fn(array $choice): string => !$choice['terminal'] ? $choice['target_quest_id'] : '',
        $node['choices'],
      ))));
      $node['unlock_condition'] = 'player_choice';
    }
    unset($node);
    if ($has_choices) {
      foreach ($nodes as &$node) {
        $choice_predecessors = [];
        foreach ($nodes as $source) {
          foreach ($source['choices'] as $choice) {
            if (!$choice['terminal'] && $choice['target_quest_id'] === $node['quest_id']) {
              $choice_predecessors[] = $source['quest_id'];
            }
          }
        }
        if ($choice_predecessors !== []) {
          $node['unlocks_after'] = array_values(array_unique(array_merge(
            $node['unlocks_after'],
            $choice_predecessors,
          )));
          $node['prerequisite_mode'] = 'any';
        }
      }
      unset($node);
    }
    foreach ($nodes as $index => &$node) {
      $previous_id = $nodes[$index - 1]['quest_id'] ?? '';
      $next_id = $nodes[$index + 1]['quest_id'] ?? '';
      if ($node['unlocks_after'] === [] && $previous_id !== '') {
        $node['unlocks_after'] = $previous_id !== '' ? [$previous_id] : [];
      }
      if ($node['choices'] === [] && $node['unlocks_to'] === [] && $next_id !== '') {
        $node['unlocks_to'] = $next_id !== '' ? [$next_id] : [];
      }
      if ($node['unlock_condition'] === '') {
        $node['unlock_condition'] = $previous_id !== '' ? 'complete_previous_quest' : 'initially_available';
      }
    }
    unset($node);

    return [
      'primary_quest_id' => $ordered_ids[0] ?? '',
      'ordered_quest_ids' => array_values(array_unique($ordered_ids)),
      'quest_nodes' => $nodes,
    ];
  }

  /**
   * Normalize author-entered terminal flags without truthy-string ambiguity.
   */
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
      $themes = array_values(array_filter(array_map('trim', explode(',', (string) ($row['themes'] ?? ''))), static fn(string $value): bool => $value !== ''));
      $character_ids = array_values(array_filter(array_map('trim', explode(',', (string) ($row['character_ids'] ?? ''))), static fn(string $value): bool => $value !== ''));
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
      $asset_refs[] = $this->buildAuthoredAssetReference(
        is_array($existing_refs['dungeon'][$dungeon_id] ?? NULL) ? $existing_refs['dungeon'][$dungeon_id] : [],
        'dungeon',
        $dungeon_id,
        trim((string) ($row['purpose'] ?? '')) ?: 'story-dungeon'
      );
      $last_index = array_key_last($asset_refs);
      $asset_refs[$last_index]['chapter_id'] = trim((string) ($row['chapter_id'] ?? ''));
      $asset_refs[$last_index]['link_data']['entrance_room_id'] = trim((string) ($row['entrance_room'] ?? ''));
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
          : ($narrative !== '' ? $narrative : 'story-room')
      );
      $reference['chapter_id'] = $chapter_id;
      $reference['scene_id'] = $scene_id;
      $reference['link_data']['dungeon_id'] = $dungeon_id;
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
        $purpose !== '' ? $purpose : 'story-item'
      );
      unset($reference['content_id']);
      $reference['notes'] = $acquisition;
      $reference['chapter_id'] = trim((string) ($row['chapter_id'] ?? ''));
      $reference['scene_id'] = trim((string) ($row['scene_id'] ?? ''));
      $reference['link_data']['quest_id'] = trim((string) ($row['quest_id'] ?? ''));
      $reference['link_data']['actor_id'] = trim((string) ($row['actor_id'] ?? ''));
      $reference['link_data']['prerequisite_ids'] = $this->normalizeCsvReferences($row['prerequisite_ids'] ?? []);
      $reference['link_data']['reward'] = trim((string) ($row['reward'] ?? ''));
      $asset_refs[] = $reference;
    }
    $template['asset_references'] = $asset_refs;
    return $template;
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
    $row = $this->database->select(self::TABLE, 'd')
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
      ->condition('draft_id', $draft_id)
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
    int $owner_uid,
    string $payload_json,
    string $payload_hash,
    int $created_at,
  ): void {
    $this->database->insert(self::REVISION_TABLE)
      ->fields([
        'draft_id' => $draft_id,
        'revision' => $revision,
        'owner_uid' => $owner_uid,
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
  private function deriveOwnedCopyIdentity(array $payload, int $uid): array {
    $source_id = trim((string) ($payload['storyline_id'] ?? 'storyline'));
    $suffix = 'u' . $uid . '-' . substr($this->uuid->generate(), 0, 8);
    $payload['storyline_id'] = substr($this->sanitizeIdentifier($source_id . '-' . $suffix), 0, 191);
    $title = trim((string) ($payload['title'] ?? 'Untitled storyline'));
    if (!str_starts_with($title, 'Copy of ')) {
      $payload['title'] = 'Copy of ' . $title;
    }
    return $payload;
  }

  /**
   * Adds caller-relative ownership capabilities to a draft projection.
   */
  private function projectOwnership(array $draft): array {
    $uid = (int) $this->currentUser->id();
    $is_owner = $uid > 0 && (int) ($draft['owner_uid'] ?? 0) === $uid;
    $draft['is_owner'] = $is_owner;
    $draft['can_edit'] = $is_owner && (string) ($draft['status'] ?? '') === 'editing';
    $draft['can_copy'] = !$is_owner || (string) ($draft['status'] ?? '') !== 'editing';
    return $draft;
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
