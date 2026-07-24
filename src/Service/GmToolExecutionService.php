<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Canonical GM privileged tool contract boundary.
 */
class GmToolExecutionService {

  public const TOOL_CONTRACT_VERSION = 'gm-tool-contract-v1';
  protected const OWNERSHIP_DOMAIN_DUNGEON_BLOB = 'dungeon_blob';
  protected const OWNERSHIP_DOMAIN_NORMALIZED_TABLES = 'normalized_tables';
  protected const GM_ROLE_ALLOWLIST = ['gm', 'game_master', 'gamemaster'];
  protected const CAMPAIGN_TABLE_PREFIX = 'dc_campaign_';

  protected const TOOL_DESCRIPTIONS = [
    'modify_dungeon_state' => 'Modify authoritative top-level dungeon state payload.',
    'modify_room_state' => 'Modify authoritative room state and metadata.',
    'modify_actor_state' => 'Modify authoritative actor state, effects, or flags.',
    'modify_inventory' => 'Modify authoritative inventory ownership or quantities.',
    'modify_quest_state' => 'Modify authoritative quest progression state.',
    'modify_encounter_state' => 'Modify authoritative encounter phase/turn state.',
    'modify_storyline_state' => 'Modify authoritative storyline state and progression markers.',
    'modify_world_flag' => 'Modify authoritative world or campaign flags.',
    'modify_campaign_character_instance' => 'Modify a campaign character instance row.',
    'modify_campaign_room_state' => 'Modify campaign room-state or room instance rows.',
    'modify_campaign_quest_progress' => 'Modify campaign quest-progress or quest rows.',
    'modify_campaign_storyline_instance' => 'Modify campaign storyline instance rows.',
    'modify_campaign_relationships' => 'Modify campaign relationship rows.',
    'modify_campaign_item_instances' => 'Modify campaign item-instance rows.',
    'modify_campaign_settings_and_flags' => 'Modify campaign settings rows.',
    'modify_setting_variable' => 'Modify one campaign setting variable/state value.',
    'modify_campaign_connections_and_locations' => 'Modify campaign connection/location rows.',
    'modify_campaign_storyline_artifacts' => 'Modify campaign storyline artifact rows (links/log/objective/location).',
    'modify_campaign_quest_artifacts' => 'Modify campaign quest artifact rows (log/reward/confirmation).',
    'modify_campaign_encounter_instances' => 'Modify campaign encounter instance/template rows.',
    'query_campaign_database' => 'Query campaign-instance tables for grounded lore, geography, and history context.',
  ];

  protected Connection $database;
  protected GameCoordinatorService $coordinator;
  protected AccountProxyInterface $currentUser;
  protected H3ProjectionQueueService $h3ProjectionQueue;

  public function __construct(Connection $database, GameCoordinatorService $coordinator, AccountProxyInterface $current_user, H3ProjectionQueueService $h3_projection_queue) {
    $this->database = $database;
    $this->coordinator = $coordinator;
    $this->currentUser = $current_user;
    $this->h3ProjectionQueue = $h3_projection_queue;
  }

  /**
   * List canonical GM privileged tools.
   *
   * @return array<string, string>
   *   Canonical tool IDs mapped to descriptions.
   */
  public function listTools(): array {
    return self::TOOL_DESCRIPTIONS;
  }

  /**
   * Execute one GM privileged tool call.
   */
  public function execute(string $tool, array $payload, array $context = []): array {
    $tool = trim($tool);
    if ($tool === '') {
      throw new \InvalidArgumentException('tool is required for GM tool execution.', 400);
    }
    if (!array_key_exists($tool, self::TOOL_DESCRIPTIONS)) {
      throw new \InvalidArgumentException(sprintf('Unsupported GM tool: %s', $tool), 400);
    }

    $campaign_id = (int) ($payload['campaign_id'] ?? 0);
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('GM tool payload requires campaign_id.', 400);
    }
    $principal = $this->assertGmAuthority($campaign_id, $context);
    $ownership_domain = $this->assertOwnershipDomain($tool, $payload);
    $correlation_id = $this->resolveCorrelationId($context, $payload);
    $payload_hash = $this->hashState($payload);
    $transaction = $this->database->startTransaction();

    try {
      switch ($tool) {
      case 'modify_campaign_character_instance':
        $mutation = $this->applyCampaignTablePatch('dc_campaign_characters', $campaign_id, $payload);
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_room_state':
        $mutation = $this->applyCampaignTablePatch(
          $this->resolveAllowedTargetTable((string) ($payload['target_table'] ?? ''), [
            'dc_campaign_room_states',
            'dc_campaign_rooms',
          ], 'dc_campaign_room_states'),
          $campaign_id,
          $payload
        );
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_quest_progress':
        $mutation = $this->applyCampaignTablePatch(
          $this->resolveAllowedTargetTable((string) ($payload['target_table'] ?? ''), [
            'dc_campaign_quest_progress',
            'dc_campaign_quests',
          ], 'dc_campaign_quest_progress'),
          $campaign_id,
          $payload
        );
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_storyline_instance':
        $mutation = $this->applyCampaignTablePatch('dc_campaign_storylines', $campaign_id, $payload);
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_relationships':
        $mutation = $this->applyCampaignTablePatch('dc_campaign_relationships', $campaign_id, $payload);
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_item_instances':
        $mutation = $this->applyCampaignTablePatch('dc_campaign_item_instances', $campaign_id, $payload);
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_settings_and_flags':
        $mutation = $this->applyCampaignTablePatch('dc_campaign_settings', $campaign_id, $payload);
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_setting_variable':
        $mutation = $this->applyCampaignTablePatch('dc_campaign_settings', $campaign_id, $payload);
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_connections_and_locations':
        $mutation = $this->applyCampaignTablePatch(
          $this->resolveAllowedTargetTable((string) ($payload['target_table'] ?? ''), [
            'dc_campaign_connections',
            'dc_campaign_locations',
          ], ''),
          $campaign_id,
          $payload
        );
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_storyline_artifacts':
        $mutation = $this->applyCampaignTablePatch(
          $this->resolveAllowedTargetTable((string) ($payload['target_table'] ?? ''), [
            'dc_campaign_storyline_links',
            'dc_campaign_storyline_log',
            'dc_campaign_objective_refs',
            'dc_campaign_locations',
          ], ''),
          $campaign_id,
          $payload
        );
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_quest_artifacts':
        $mutation = $this->applyCampaignTablePatch(
          $this->resolveAllowedTargetTable((string) ($payload['target_table'] ?? ''), [
            'dc_campaign_quest_log',
            'dc_campaign_quest_rewards_claimed',
            'dc_campaign_quest_confirmations',
          ], ''),
          $campaign_id,
          $payload
        );
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'modify_campaign_encounter_instances':
        $mutation = $this->applyCampaignTablePatch(
          $this->resolveAllowedTargetTable((string) ($payload['target_table'] ?? ''), [
            'dc_campaign_encounter_instances',
            'dc_campaign_encounter_templates',
          ], ''),
          $campaign_id,
          $payload
        );
        return $this->finalizeToolExecution(
          $this->buildCampaignMutationResult(
            $tool,
            $campaign_id,
            $mutation
          ),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          $mutation
        );

      case 'query_campaign_database':
        $query_result = $this->queryCampaignTable($campaign_id, $payload);
        return [
          'success' => TRUE,
          'contract_version' => self::TOOL_CONTRACT_VERSION,
          'tool' => $tool,
          'campaign_id' => $campaign_id,
          'target_table' => (string) ($query_result['target_table'] ?? ''),
          'query_limit' => (int) ($query_result['query_limit'] ?? 0),
          'rows' => is_array($query_result['rows'] ?? NULL) ? $query_result['rows'] : [],
        ];

      case 'modify_dungeon_state':
        $patch = $this->requireNonEmptyPatchPayload($tool, $payload);
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyDungeonStatePatch($before, $patch);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

      case 'modify_room_state':
        $room_id = $this->requireNonEmptyStringPayload($tool, $payload, 'room_id');
        $patch = $this->requireNonEmptyPatchPayload($tool, $payload);
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyRoomPatch($before, $room_id, $patch);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

      case 'modify_actor_state':
        $actor_id = $this->requireNonEmptyStringPayload($tool, $payload, 'actor_id');
        $patch = $this->requireNonEmptyPatchPayload($tool, $payload);
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyActorPatch($before, $actor_id, $patch);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

      case 'modify_inventory':
        $actor_id = $this->requireNonEmptyStringPayload($tool, $payload, 'actor_id');
        $inventory = $payload['inventory'] ?? NULL;
        if (!is_array($inventory)) {
          throw new \InvalidArgumentException('modify_inventory requires non-empty actor_id and inventory object.', 400);
        }
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyInventoryPatch($before, $actor_id, $inventory);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

      case 'modify_quest_state':
        $quest_id = $this->requireNonEmptyStringPayload($tool, $payload, 'quest_id');
        $patch = $this->requireNonEmptyPatchPayload($tool, $payload);
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyQuestPatch($before, $quest_id, $patch);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

      case 'modify_encounter_state':
        $patch = $this->requireNonEmptyPatchPayload($tool, $payload);
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyEncounterPatch($before, $patch);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

      case 'modify_storyline_state':
        $patch = $this->requireNonEmptyPatchPayload($tool, $payload);
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyStorylinePatch($before, $patch);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

      case 'modify_world_flag':
        $flag_path = $this->requireNonEmptyStringPayload($tool, $payload, 'flag_path');
        if (!array_key_exists('value', $payload)) {
          throw new \InvalidArgumentException('modify_world_flag requires value.', 400);
        }
        $record = $this->loadLatestDungeonRecord($campaign_id);
        $before = $record['dungeon_data'];
        $after = $this->applyWorldFlagPatch($before, $flag_path, $payload['value']);
        $this->persistDungeonRecord($campaign_id, $record['id'], $record['dungeon_id'], $after);
        return $this->finalizeToolExecution(
          $this->buildDungeonMutationResult($tool, $campaign_id, $record),
          $tool,
          $campaign_id,
          $principal,
          $ownership_domain,
          $correlation_id,
          $payload_hash,
          [
            'operation' => 'update',
            'target_table' => 'dc_campaign_dungeons',
            'before_hash' => $this->hashState($before),
            'after_hash' => $this->hashState($after),
          ]
        );

        default:
          throw new \InvalidArgumentException(sprintf('Unsupported GM tool: %s', $tool), 400);
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    finally {
      unset($transaction);
    }
  }

  protected function buildCampaignMutationResult(string $tool, int $campaign_id, array $mutation): array {
    return [
      'success' => TRUE,
      'contract_version' => self::TOOL_CONTRACT_VERSION,
      'tool' => $tool,
      'campaign_id' => $campaign_id,
      'target_table' => (string) ($mutation['target_table'] ?? ''),
      'operation' => (string) ($mutation['operation'] ?? 'update'),
      'mutated_rows' => (int) ($mutation['mutated_rows'] ?? 0),
      'insert_id' => isset($mutation['insert_id']) ? (int) $mutation['insert_id'] : NULL,
    ];
  }

  protected function resolveAllowedTargetTable(string $requested, array $allowed, string $default): string {
    $requested = trim($requested);
    if ($requested === '') {
      if ($default !== '') {
        return $default;
      }
      throw new \InvalidArgumentException('target_table is required for this tool.', 400);
    }
    if (!in_array($requested, $allowed, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unsupported target_table: %s', $requested), 400);
    }
    $this->assertCampaignInstanceTable($requested);
    return $requested;
  }

  protected function assertCampaignInstanceTable(string $table): void {
    $table = trim($table);
    if ($table === '') {
      throw new \InvalidArgumentException('Campaign instance table name is required.', 400);
    }
    if ($table !== 'dc_campaigns' && !str_starts_with($table, self::CAMPAIGN_TABLE_PREFIX)) {
      throw new \InvalidArgumentException(sprintf('GM tools may only modify campaign-instance tables. Unsupported target_table: %s', $table), 403);
    }
    if (str_starts_with($table, 'dungeoncrawler_content_') || str_starts_with($table, 'dc_library_') || str_starts_with($table, 'dc_canonical_')) {
      throw new \InvalidArgumentException(sprintf('GM tools cannot modify canonical/library tables: %s', $table), 403);
    }
  }

  protected function applyCampaignTablePatch(string $table, int $campaign_id, array $payload): array {
    $this->assertCampaignInstanceTable($table);
    $operation = strtolower(trim((string) ($payload['operation'] ?? 'update')));
    if (!in_array($operation, ['update', 'insert', 'upsert', 'delete'], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unsupported campaign table operation: %s', $operation), 400);
    }

    $where = is_array($payload['where'] ?? NULL) ? $payload['where'] : [];
    $fields = is_array($payload['fields'] ?? NULL) ? $payload['fields'] : [];
    $keys = is_array($payload['keys'] ?? NULL) ? $payload['keys'] : [];

    $json_fields = array_values(array_filter(array_map('strval', is_array($payload['json_fields'] ?? NULL) ? $payload['json_fields'] : [])));
    foreach ($json_fields as $field_name) {
      if (array_key_exists($field_name, $fields)) {
        $encoded = json_encode($fields[$field_name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
          throw new \RuntimeException(sprintf('Failed to encode JSON field "%s".', $field_name));
        }
        $fields[$field_name] = $encoded;
      }
      if (array_key_exists($field_name, $keys)) {
        $encoded = json_encode($keys[$field_name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
          throw new \RuntimeException(sprintf('Failed to encode JSON key field "%s".', $field_name));
        }
        $keys[$field_name] = $encoded;
      }
      if (array_key_exists($field_name, $where)) {
        $encoded = json_encode($where[$field_name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
          throw new \RuntimeException(sprintf('Failed to encode JSON where field "%s".', $field_name));
        }
        $where[$field_name] = $encoded;
      }
    }
    $audit_seed = [
      'table' => $table,
      'operation' => $operation,
      'campaign_id' => $campaign_id,
      'where' => $where,
      'fields' => $fields,
      'keys' => $keys,
    ];
    $before_hash = $this->hashState(['phase' => 'before'] + $audit_seed);

    $schema = $this->database->schema();
    $has_updated = $schema->fieldExists($table, 'updated');
    $has_updated_at = $schema->fieldExists($table, 'updated_at');
    $has_last_updated = $schema->fieldExists($table, 'last_updated');
    $has_created = $schema->fieldExists($table, 'created');
    $has_created_at = $schema->fieldExists($table, 'created_at');
    $has_started_at = $schema->fieldExists($table, 'started_at');

    if ($has_updated && !array_key_exists('updated', $fields)) {
      $fields['updated'] = time();
    }
    if ($has_updated_at && !array_key_exists('updated_at', $fields)) {
      $fields['updated_at'] = time();
    }
    if ($has_last_updated && !array_key_exists('last_updated', $fields)) {
      $fields['last_updated'] = time();
    }
    if ($operation === 'insert') {
      if ($fields === []) {
        throw new \InvalidArgumentException('Campaign table insert requires non-empty fields object.', 400);
      }
      if (isset($fields['campaign_id']) && (int) $fields['campaign_id'] !== $campaign_id) {
        throw new \InvalidArgumentException('fields.campaign_id must match payload campaign_id.', 400);
      }
      $fields['campaign_id'] = $campaign_id;
      if ($has_created && !array_key_exists('created', $fields)) {
        $fields['created'] = time();
      }
      if ($has_created_at && !array_key_exists('created_at', $fields)) {
        $fields['created_at'] = time();
      }
      if ($has_started_at && !array_key_exists('started_at', $fields)) {
        $fields['started_at'] = time();
      }
      $insert_id = $this->database->insert($table)->fields($fields)->execute();
      return [
        'target_table' => $table,
        'operation' => $operation,
        'mutated_rows' => 1,
        'insert_id' => is_numeric($insert_id) ? (int) $insert_id : NULL,
        'before_hash' => $before_hash,
        'after_hash' => $this->hashState(['phase' => 'after', 'mutated_rows' => 1, 'insert_id' => is_numeric($insert_id) ? (int) $insert_id : NULL] + $audit_seed),
      ];
    }

    if ($operation === 'upsert') {
      if ($keys === []) {
        throw new \InvalidArgumentException('Campaign table upsert requires non-empty keys object.', 400);
      }
      if ($fields === []) {
        throw new \InvalidArgumentException('Campaign table upsert requires non-empty fields object.', 400);
      }
      if (isset($keys['campaign_id']) && (int) $keys['campaign_id'] !== $campaign_id) {
        throw new \InvalidArgumentException('keys.campaign_id must match payload campaign_id.', 400);
      }
      if (isset($fields['campaign_id']) && (int) $fields['campaign_id'] !== $campaign_id) {
        throw new \InvalidArgumentException('fields.campaign_id must match payload campaign_id.', 400);
      }
      $keys['campaign_id'] = $campaign_id;
      $fields['campaign_id'] = $campaign_id;
      if ($has_created && !array_key_exists('created', $fields)) {
        $fields['created'] = time();
      }
      if ($has_created_at && !array_key_exists('created_at', $fields)) {
        $fields['created_at'] = time();
      }
      if ($has_started_at && !array_key_exists('started_at', $fields)) {
        $fields['started_at'] = time();
      }
      $this->database->merge($table)
        ->keys($keys)
        ->fields($fields)
        ->execute();
      return [
        'target_table' => $table,
        'operation' => $operation,
        'mutated_rows' => 1,
        'before_hash' => $before_hash,
        'after_hash' => $this->hashState(['phase' => 'after', 'mutated_rows' => 1] + $audit_seed),
      ];
    }

    if ($where === []) {
      throw new \InvalidArgumentException('Campaign table mutation requires non-empty where object.', 400);
    }
    if (isset($where['campaign_id']) && (int) $where['campaign_id'] !== $campaign_id) {
      throw new \InvalidArgumentException('where.campaign_id must match payload campaign_id.', 400);
    }
    $where['campaign_id'] = $campaign_id;

    if ($operation === 'delete') {
      $query = $this->database->delete($table);
      foreach ($where as $column => $value) {
        if ($value === NULL) {
          $query->isNull((string) $column);
        }
        else {
          $query->condition((string) $column, $value);
        }
      }
      $deleted_rows = (int) $query->execute();
      if ($deleted_rows !== 1) {
        throw new \RuntimeException(sprintf('Expected exactly 1 row delete in %s, got %d.', $table, $deleted_rows));
      }
      return [
        'target_table' => $table,
        'operation' => $operation,
        'mutated_rows' => $deleted_rows,
        'before_hash' => $before_hash,
        'after_hash' => $this->hashState(['phase' => 'after', 'mutated_rows' => $deleted_rows] + $audit_seed),
      ];
    }

    if ($fields === []) {
      throw new \InvalidArgumentException('Campaign table update requires non-empty fields object.', 400);
    }
    $query = $this->database->update($table)->fields($fields);
    foreach ($where as $column => $value) {
      if ($value === NULL) {
        $query->isNull((string) $column);
      }
      else {
        $query->condition((string) $column, $value);
      }
    }
    $updated_rows = (int) $query->execute();
    if ($updated_rows !== 1) {
      throw new \RuntimeException(sprintf('Expected exactly 1 row update in %s, got %d.', $table, $updated_rows));
    }

    return [
      'target_table' => $table,
      'operation' => $operation,
      'mutated_rows' => $updated_rows,
      'before_hash' => $before_hash,
      'after_hash' => $this->hashState(['phase' => 'after', 'mutated_rows' => $updated_rows] + $audit_seed),
    ];
  }

  protected function assertGmAuthority(int $campaign_id, array $context): array {
    $actor_role = strtolower(trim((string) ($context['actor_role'] ?? '')));
    if (!in_array($actor_role, self::GM_ROLE_ALLOWLIST, TRUE)) {
      throw new \InvalidArgumentException('GM tool execution requires actor_role=gm.', 403);
    }

    $gm_actor_id = trim((string) ($context['gm_actor_id'] ?? ''));
    $gm_character_id = (int) ($context['gm_character_id'] ?? 0);
    if ($gm_actor_id === '' || $gm_character_id <= 0) {
      throw new \InvalidArgumentException('GM tool execution requires gm_actor_id and gm_character_id principal context.', 403);
    }

    $resolved_actor_id = trim((string) ($this->coordinator->resolveActorIdForCharacterId($campaign_id, $gm_character_id) ?? ''));
    if ($resolved_actor_id === '' || $resolved_actor_id !== $gm_actor_id) {
      throw new \InvalidArgumentException('GM tool execution principal binding failed: character does not resolve to provided gm_actor_id.', 403);
    }

    $uid = (int) $this->currentUser->id();
    if ($uid <= 0) {
      throw new \InvalidArgumentException('GM tool execution requires authenticated user context.', 403);
    }
    $campaign_owner_uid = $this->loadCampaignOwnerUid($campaign_id);
    if ($uid !== $campaign_owner_uid) {
      throw new \InvalidArgumentException('GM tool execution principal entitlement failed: current user is not campaign owner GM.', 403);
    }

    $character_principal = $this->loadCampaignCharacterPrincipal($campaign_id, $gm_character_id);
    $character_uid = (int) ($character_principal['uid'] ?? 0);
    if ($character_uid !== $campaign_owner_uid) {
      throw new \InvalidArgumentException('GM tool execution principal entitlement failed: GM character principal is not owned by campaign GM.', 403);
    }

    return [
      'gm_actor_id' => $gm_actor_id,
      'gm_character_id' => $gm_character_id,
      'uid' => $uid,
      'actor_role' => $actor_role,
    ];
  }

  protected function loadCampaignOwnerUid(int $campaign_id): int {
    $owner_uid = (int) $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($owner_uid <= 0) {
      throw new \InvalidArgumentException(sprintf('GM tool execution principal entitlement failed: campaign %d has no valid GM owner.', $campaign_id), 403);
    }
    return $owner_uid;
  }

  protected function loadCampaignCharacterPrincipal(int $campaign_id, int $gm_character_id): array {
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'uid', 'role', 'is_active'])
      ->condition('campaign_id', $campaign_id)
      ->condition('is_active', 1);
    $or = $query->orConditionGroup()
      ->condition('id', $gm_character_id)
      ->condition('character_id', $gm_character_id);
    $row = $query
      ->condition($or)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      throw new \InvalidArgumentException('GM tool execution principal entitlement failed: gm_character_id is not an active campaign principal.', 403);
    }
    return $row;
  }

  protected function assertOwnershipDomain(string $tool, array $payload): string {
    $domain = strtolower(trim((string) ($payload['ownership_domain'] ?? '')));
    if ($domain === '') {
      throw new \InvalidArgumentException('GM tool payload requires ownership_domain.', 400);
    }

    $table_tools = [
      'modify_campaign_character_instance',
      'modify_campaign_room_state',
      'modify_campaign_quest_progress',
      'modify_campaign_storyline_instance',
      'modify_campaign_relationships',
      'modify_campaign_item_instances',
      'modify_campaign_settings_and_flags',
      'modify_setting_variable',
      'modify_campaign_connections_and_locations',
      'modify_campaign_storyline_artifacts',
      'modify_campaign_quest_artifacts',
      'modify_campaign_encounter_instances',
      'query_campaign_database',
    ];
    $dungeon_tools = [
      'modify_dungeon_state',
      'modify_room_state',
      'modify_actor_state',
      'modify_inventory',
      'modify_quest_state',
      'modify_encounter_state',
      'modify_storyline_state',
      'modify_world_flag',
    ];

    if (in_array($tool, $table_tools, TRUE) && $domain !== self::OWNERSHIP_DOMAIN_NORMALIZED_TABLES) {
      throw new \InvalidArgumentException('Campaign table tools require ownership_domain=normalized_tables.', 400);
    }
    if (in_array($tool, $dungeon_tools, TRUE) && $domain !== self::OWNERSHIP_DOMAIN_DUNGEON_BLOB) {
      throw new \InvalidArgumentException('Dungeon mutation tools require ownership_domain=dungeon_blob.', 400);
    }

    return $domain;
  }

  protected function resolveCorrelationId(array $context, array $payload): string {
    $candidate = trim((string) ($context['correlation_id'] ?? $payload['correlation_id'] ?? ''));
    if ($candidate === '') {
      throw new \InvalidArgumentException('GM tool execution requires correlation_id in context or payload.', 400);
    }
    return $candidate;
  }

  protected function requireNonEmptyPatchPayload(string $tool, array $payload): array {
    $patch = $payload['patch'] ?? NULL;
    if (!is_array($patch) || $patch === []) {
      throw new \InvalidArgumentException(sprintf('%s requires non-empty patch object.', $tool), 400);
    }
    return $patch;
  }

  protected function requireNonEmptyStringPayload(string $tool, array $payload, string $key): string {
    $value = trim((string) ($payload[$key] ?? ''));
    if ($value === '') {
      throw new \InvalidArgumentException(sprintf('%s requires non-empty %s.', $tool, $key), 400);
    }
    return $value;
  }

  protected function buildDungeonMutationResult(string $tool, int $campaign_id, array $record): array {
    return [
      'success' => TRUE,
      'contract_version' => self::TOOL_CONTRACT_VERSION,
      'tool' => $tool,
      'campaign_id' => $campaign_id,
      'dungeon_id' => (string) $record['dungeon_id'],
      'mutated_record_id' => (int) $record['id'],
    ];
  }

  protected function finalizeToolExecution(
    array $result,
    string $tool,
    int $campaign_id,
    array $principal,
    string $ownership_domain,
    string $correlation_id,
    string $payload_hash,
    array $mutation
  ): array {
    $this->recordMutationAudit(
      $campaign_id,
      $tool,
      (string) ($mutation['operation'] ?? 'update'),
      (string) ($mutation['target_table'] ?? 'dc_campaign_dungeons'),
      $principal,
      $ownership_domain,
      $correlation_id,
      $payload_hash,
      (string) ($mutation['before_hash'] ?? ''),
      (string) ($mutation['after_hash'] ?? '')
    );

    return $result;
  }

  protected function recordMutationAudit(
    int $campaign_id,
    string $tool,
    string $operation,
    string $target_table,
    array $principal,
    string $ownership_domain,
    string $correlation_id,
    string $payload_hash,
    string $before_hash,
    string $after_hash
  ): void {
    $inserted = $this->database->insert('dc_gm_mutation_audit')
      ->fields([
        'campaign_id' => $campaign_id,
        'tool' => $tool,
        'operation' => $operation,
        'target_table' => $target_table,
        'principal_actor_id' => (string) ($principal['gm_actor_id'] ?? ''),
        'principal_character_id' => (int) ($principal['gm_character_id'] ?? 0),
        'principal_uid' => (int) ($principal['uid'] ?? 0),
        'actor_role' => (string) ($principal['actor_role'] ?? 'gm'),
        'ownership_domain' => $ownership_domain,
        'correlation_id' => $correlation_id,
        'payload_hash' => $payload_hash,
        'before_hash' => $before_hash,
        'after_hash' => $after_hash,
        'created' => time(),
      ])
      ->execute();

    if ((int) $inserted <= 0) {
      throw new \RuntimeException('Failed to persist immutable GM mutation audit ledger entry.');
    }
  }

  protected function hashState(mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if (!is_string($json)) {
      throw new \RuntimeException('Failed to encode state for hashing.');
    }
    return hash('sha256', $json);
  }

  protected function queryCampaignTable(int $campaign_id, array $payload): array {
    $allowed_tables = [
      'dc_campaigns',
      'dc_campaign_characters',
      'dc_campaign_dungeons',
      'dc_campaign_rooms',
      'dc_campaign_room_states',
      'dc_campaign_quests',
      'dc_campaign_quest_progress',
      'dc_campaign_quest_log',
      'dc_campaign_storylines',
      'dc_campaign_storyline_log',
      'dc_campaign_relationships',
      'dc_campaign_item_instances',
      'dc_campaign_locations',
      'dc_campaign_settings',
      'dc_campaign_connections',
      'dc_campaign_encounter_instances',
    ];

    $target_table = $this->resolveAllowedTargetTable((string) ($payload['target_table'] ?? ''), $allowed_tables, '');
    $filters = is_array($payload['filters'] ?? NULL) ? $payload['filters'] : [];
    if ($filters === []) {
      throw new \InvalidArgumentException('query_campaign_database requires non-empty filters object.', 400);
    }
    $requested_fields = array_values(array_filter(array_map('strval', is_array($payload['fields'] ?? NULL) ? $payload['fields'] : [])));
    $query_limit = max(1, min(25, (int) ($payload['limit'] ?? 10)));

    $schema = $this->database->schema();
    if (!$schema->tableExists($target_table)) {
      throw new \InvalidArgumentException(sprintf('query_campaign_database target_table does not exist: %s', $target_table), 400);
    }

    $fields = $requested_fields !== [] ? $requested_fields : ['id', 'campaign_id'];
    foreach ($fields as $field_name) {
      if (!$schema->fieldExists($target_table, $field_name)) {
        throw new \InvalidArgumentException(sprintf('query_campaign_database unsupported field "%s" for table %s.', $field_name, $target_table), 400);
      }
    }

    $query = $this->database->select($target_table, 't')
      ->fields('t', $fields)
      ->condition('campaign_id', $campaign_id)
      ->range(0, $query_limit);
    foreach ($filters as $column => $value) {
      $column = (string) $column;
      if ($column === 'campaign_id') {
        continue;
      }
      if (!$schema->fieldExists($target_table, $column)) {
        throw new \InvalidArgumentException(sprintf('query_campaign_database unsupported filter field "%s" for table %s.', $column, $target_table), 400);
      }
      if ($value === NULL) {
        $query->isNull($column);
      }
      else {
        $query->condition($column, $value);
      }
    }

    return [
      'target_table' => $target_table,
      'query_limit' => $query_limit,
      'rows' => $query->execute()->fetchAll(\PDO::FETCH_ASSOC),
    ];
  }

  /**
   * @return array{id:int,dungeon_id:string,dungeon_data:array}
   */
  protected function loadLatestDungeonRecord(int $campaign_id): array {
    $row = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['id', 'dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \InvalidArgumentException(sprintf('No dungeon snapshot found for campaign_id %d.', $campaign_id), 404);
    }

    $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? ''), TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException(sprintf('Dungeon payload is invalid for campaign_id %d.', $campaign_id));
    }

    return [
      'id' => (int) ($row['id'] ?? 0),
      'dungeon_id' => (string) ($row['dungeon_id'] ?? ''),
      'dungeon_data' => $dungeon_data,
    ];
  }

  protected function persistDungeonRecord(int $campaign_id, int $record_id, string $dungeon_id, array $dungeon_data): void {
    $json = json_encode($dungeon_data);
    if (!is_string($json) || $json === '') {
      throw new \RuntimeException('Failed to encode updated dungeon payload.');
    }
    $dungeon_id = trim($dungeon_id);
    if ($campaign_id <= 0 || $dungeon_id === '') {
      throw new \InvalidArgumentException('persistDungeonRecord requires campaign_id and dungeon_id.');
    }

    $updated_rows = (int) $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => $json,
        'updated' => time(),
      ])
      ->condition('id', $record_id)
      ->execute();
    if ($updated_rows !== 1) {
      throw new \RuntimeException(sprintf('Failed to persist dungeon payload for record_id %d.', $record_id));
    }

    $launch_slice_scope = $this->resolveLaunchSliceRoomScopeFromDungeonData($dungeon_data);
    if ($launch_slice_scope === []) {
      throw new \RuntimeException(sprintf(
        'H3 projection trigger contract violation: no launch-slice room scope found after GM dungeon mutation (campaign_id=%d dungeon_id=%s).',
        $campaign_id,
        $dungeon_id
      ));
    }
    $this->h3ProjectionQueue->provisionLaunchSliceNow($campaign_id, $dungeon_id, $launch_slice_scope);
  }

  /**
   * Resolve launch-slice room scope from campaign dungeon payload.
   *
   * @return array<int, string>
   *   Active room plus direct neighbors.
   */
  protected function resolveLaunchSliceRoomScopeFromDungeonData(array $dungeon_data): array {
    $rooms = [];
    foreach ((array) ($dungeon_data['rooms'] ?? []) as $room) {
      if (!is_array($room)) {
        continue;
      }
      $room_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
      if ($room_id !== '') {
        $rooms[$room_id] = TRUE;
      }
    }

    $active_room_id = trim((string) ($dungeon_data['active_room_id'] ?? $dungeon_data['current_room_id'] ?? ''));
    $scope = [];
    if ($active_room_id !== '' && isset($rooms[$active_room_id])) {
      $scope[$active_room_id] = TRUE;
    }

    $connections = [];
    if (is_array($dungeon_data['connections'] ?? NULL)) {
      $connections = array_merge($connections, $dungeon_data['connections']);
    }
    if (is_array($dungeon_data['hex_map']['connections'] ?? NULL)) {
      $connections = array_merge($connections, $dungeon_data['hex_map']['connections']);
    }
    foreach ($connections as $connection) {
      if (!is_array($connection)) {
        continue;
      }
      $from_room_id = trim((string) ($connection['from_room_id'] ?? $connection['from_room'] ?? ''));
      $to_room_id = trim((string) ($connection['to_room_id'] ?? $connection['to_room'] ?? ''));
      if ($from_room_id === '' || $to_room_id === '') {
        continue;
      }
      if ($active_room_id !== '' && $from_room_id !== $active_room_id && $to_room_id !== $active_room_id) {
        continue;
      }
      if (isset($rooms[$from_room_id])) {
        $scope[$from_room_id] = TRUE;
      }
      if (isset($rooms[$to_room_id])) {
        $scope[$to_room_id] = TRUE;
      }
    }

    if ($scope === [] && $active_room_id !== '') {
      $scope[$active_room_id] = TRUE;
    }
    if ($scope === [] && $rooms !== []) {
      $scope[(string) array_key_first($rooms)] = TRUE;
    }
    return array_values(array_keys($scope));
  }

  protected function applyRoomPatch(array $dungeon_data, string $room_id, array $patch): array {
    if (!is_array($dungeon_data['rooms'] ?? NULL)) {
      throw new \RuntimeException('Dungeon payload has no rooms array.');
    }
    foreach ($dungeon_data['rooms'] as $index => $room) {
      if (!is_array($room)) {
        continue;
      }
      $candidate_id = trim((string) ($room['room_id'] ?? $room['id'] ?? ''));
      if ($candidate_id === $room_id) {
        $dungeon_data['rooms'][$index] = array_replace_recursive($room, $patch);
        return $dungeon_data;
      }
    }
    throw new \InvalidArgumentException(sprintf('Room not found for room_id %s.', $room_id), 404);
  }

  protected function applyDungeonStatePatch(array $dungeon_data, array $patch): array {
    return array_replace_recursive($dungeon_data, $patch);
  }

  protected function applyActorPatch(array $dungeon_data, string $actor_id, array $patch): array {
    if (!is_array($dungeon_data['entities'] ?? NULL)) {
      throw new \RuntimeException('Dungeon payload has no entities array.');
    }
    foreach ($dungeon_data['entities'] as $index => $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $candidate_id = trim((string) ($entity['entity_id'] ?? $entity['id'] ?? ''));
      if ($candidate_id === $actor_id) {
        $dungeon_data['entities'][$index] = array_replace_recursive($entity, $patch);
        return $dungeon_data;
      }
    }
    throw new \InvalidArgumentException(sprintf('Actor not found for actor_id %s.', $actor_id), 404);
  }

  protected function applyInventoryPatch(array $dungeon_data, string $actor_id, array $inventory): array {
    if (!is_array($dungeon_data['entities'] ?? NULL)) {
      throw new \RuntimeException('Dungeon payload has no entities array.');
    }
    foreach ($dungeon_data['entities'] as $index => $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $candidate_id = trim((string) ($entity['entity_id'] ?? $entity['id'] ?? ''));
      if ($candidate_id === $actor_id) {
        $entity['inventory'] = $inventory;
        $dungeon_data['entities'][$index] = $entity;
        return $dungeon_data;
      }
    }
    throw new \InvalidArgumentException(sprintf('Actor not found for actor_id %s.', $actor_id), 404);
  }

  protected function applyQuestPatch(array $dungeon_data, string $quest_id, array $patch): array {
    $quest_state = is_array($dungeon_data['quest_state'] ?? NULL) ? $dungeon_data['quest_state'] : [];
    $existing = is_array($quest_state[$quest_id] ?? NULL) ? $quest_state[$quest_id] : [];
    $quest_state[$quest_id] = array_replace_recursive($existing, $patch);
    $dungeon_data['quest_state'] = $quest_state;
    return $dungeon_data;
  }

  protected function applyEncounterPatch(array $dungeon_data, array $patch): array {
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    $game_state['encounter_context'] = array_replace_recursive(
      is_array($game_state['encounter_context'] ?? NULL) ? $game_state['encounter_context'] : [],
      $patch
    );
    $dungeon_data['game_state'] = $game_state;
    return $dungeon_data;
  }

  protected function applyStorylinePatch(array $dungeon_data, array $patch): array {
    $storyline_state = is_array($dungeon_data['storyline_state'] ?? NULL) ? $dungeon_data['storyline_state'] : [];
    $dungeon_data['storyline_state'] = array_replace_recursive($storyline_state, $patch);
    return $dungeon_data;
  }

  protected function applyWorldFlagPatch(array $dungeon_data, string $flag_path, mixed $value): array {
    $segments = array_values(array_filter(array_map(static fn(string $segment): string => trim($segment), explode('.', $flag_path))));
    if ($segments === []) {
      throw new \InvalidArgumentException('flag_path must contain one or more segments.', 400);
    }

    $cursor = &$dungeon_data;
    foreach ($segments as $segment) {
      if (!is_array($cursor)) {
        throw new \RuntimeException('Cannot set world flag on non-object path segment.');
      }
      if (!array_key_exists($segment, $cursor)) {
        $cursor[$segment] = [];
      }
      $cursor = &$cursor[$segment];
    }
    $cursor = $value;
    unset($cursor);

    return $dungeon_data;
  }

}
