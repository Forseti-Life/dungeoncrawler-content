<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Imports template examples from file storage into template tables.
 */
class TemplateImportService {

  /**
   * Database connection.
   */
  protected Connection $database;

  /**
   * Module extension list.
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Logger channel.
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, ModuleExtensionList $module_extension_list, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->moduleExtensionList = $module_extension_list;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * Imports templates from module example files.
   */
  public function importTemplates(): array {
    $summary = [
      'table_rows_processed' => 0,
      'table_rows_inserted' => 0,
      'table_rows_updated' => 0,
      'table_rows_skipped' => 0,
      'library_portrait_links_added' => 0,
      'tables_processed' => [],
      'errors' => [],
      'missing_template_pairs' => [],
    ];

    $templates_root = $this->getTemplatesRootPath();
    if (!is_dir($templates_root)) {
      $summary['errors'][] = 'Templates directory not found: ' . $templates_root;
      $summary['missing_template_pairs'] = $this->getMissingTemplatePairs();
      return $summary;
    }

    $table_directories = array_values(array_filter(scandir($templates_root) ?: [], function (string $name) use ($templates_root): bool {
      return $name !== '.' && $name !== '..' && is_dir($templates_root . '/' . $name);
    }));

    foreach ($table_directories as $table_name) {
      $table_path = $templates_root . '/' . $table_name;
      $table_result = $this->importTableDirectory($table_name, $table_path);

      $summary['tables_processed'][$table_name] = $table_result;
      $summary['table_rows_processed'] += $table_result['processed'];
      $summary['table_rows_inserted'] += $table_result['inserted'];
      $summary['table_rows_updated'] += $table_result['updated'];
      $summary['table_rows_skipped'] += $table_result['skipped'];
      $summary['errors'] = array_merge($summary['errors'], $table_result['errors']);
    }

    $summary['missing_template_pairs'] = $this->getMissingTemplatePairs();
    $summary['library_portrait_links_added'] = $this->syncLibraryNpcPortraitLinks();
    $summary['canonical_contract_sync'] = $this->synchronizeCanonicalContracts();

    return $summary;
  }

  /**
   * Imports only bundled storyline template examples.
   */
  public function importStorylineTemplates(): array {
    $table_name = 'dungeoncrawler_content_storylines';
    $table_path = $this->getTemplatesRootPath() . '/' . $table_name;

    if (!is_dir($table_path)) {
      return [
        'processed' => 0,
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => ['Storyline template directory not found: ' . $table_path],
      ];
    }

    $result = $this->importTableDirectory($table_name, $table_path);
    $result['canonical_contract_sync'] = $this->synchronizeCanonicalContracts();
    return $result;
  }

  /**
   * Imports specific template JSON files grouped by table.
   *
   * @param array<string, array<int, string>|string> $table_files
   *   Mapping of table name to one or more JSON basenames within that table's
   *   template directory.
   */
  public function importTemplateFiles(array $table_files): array {
    $summary = [
      'table_rows_processed' => 0,
      'table_rows_inserted' => 0,
      'table_rows_updated' => 0,
      'table_rows_skipped' => 0,
      'library_portrait_links_added' => 0,
      'tables_processed' => [],
      'errors' => [],
      'missing_template_pairs' => [],
    ];

    foreach ($table_files as $table_name => $requested_files) {
      $resolved = $this->resolveTableJsonFiles($table_name, (array) $requested_files);
      $table_result = $this->importTableFiles($table_name, $resolved['files']);
      $table_result['errors'] = array_merge($resolved['errors'], $table_result['errors']);

      $summary['tables_processed'][$table_name] = $table_result;
      $summary['table_rows_processed'] += $table_result['processed'];
      $summary['table_rows_inserted'] += $table_result['inserted'];
      $summary['table_rows_updated'] += $table_result['updated'];
      $summary['table_rows_skipped'] += $table_result['skipped'];
      $summary['errors'] = array_merge($summary['errors'], $table_result['errors']);
    }

    $summary['missing_template_pairs'] = $this->getMissingTemplatePairs();
    $summary['library_portrait_links_added'] = $this->syncLibraryNpcPortraitLinks();
    $summary['canonical_contract_sync'] = $this->synchronizeCanonicalContracts();

    return $summary;
  }

  /**
   * Auto-links portraits for NPC template library characters when available.
   *
   * @return int
   *   Number of new portrait links inserted.
   */
  protected function syncLibraryNpcPortraitLinks(): int {
    if (!$this->database->schema()->tableExists('dungeoncrawler_content_characters')
      || !$this->database->schema()->tableExists('dc_generated_image_links')
      || !$this->database->schema()->tableExists('dc_generated_images')
      || !$this->database->schema()->tableExists('dc_campaign_characters')) {
      return 0;
    }

    $rows = $this->database->select('dungeoncrawler_content_characters', 'c')
      ->fields('c', ['id', 'type', 'state_data'])
      ->condition('type', 'npc')
      ->execute()
      ->fetchAllAssoc('id');

    if (empty($rows)) {
      return 0;
    }

    $now = time();
    $linked = 0;

    foreach ($rows as $row) {
      $library_object_id = (string) ((int) ($row->id ?? 0));
      if ($library_object_id === '0') {
        continue;
      }

      $already_linked = (bool) $this->database->select('dc_generated_image_links', 'l')
        ->fields('l', ['id'])
        ->condition('table_name', 'dungeoncrawler_content_characters')
        ->condition('object_id', $library_object_id)
        ->isNull('campaign_id')
        ->condition('slot', 'portrait')
        ->condition('variant', 'original')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($already_linked) {
        continue;
      }

      $state_data = json_decode((string) ($row->state_data ?? '{}'), TRUE);
      if (!is_array($state_data)) {
        continue;
      }

      $name = trim((string) ($state_data['name'] ?? ''));
      if ($name === '') {
        continue;
      }

      $image_id = $this->resolvePortraitImageIdForName($name);
      if ($image_id === NULL) {
        continue;
      }

      $this->database->insert('dc_generated_image_links')
        ->fields([
          'image_id' => $image_id,
          'scope_type' => 'global',
          'campaign_id' => NULL,
          'table_name' => 'dungeoncrawler_content_characters',
          'object_id' => $library_object_id,
          'slot' => 'portrait',
          'variant' => 'original',
          'is_primary' => 1,
          'sort_weight' => 0,
          'visibility' => 'public',
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();

      $linked++;
    }

    return $linked;
  }

  /**
   * Resolves an image_id to use as a global library portrait by NPC name.
   */
  protected function resolvePortraitImageIdForName(string $name): ?int {
    $campaign_image_id = $this->database->query(
      'SELECT l.image_id
       FROM dc_campaign_characters cc
       INNER JOIN dc_generated_image_links l
         ON l.table_name = :table_name
        AND l.object_id = CAST(cc.id AS CHAR)
        AND l.slot = :slot
        AND l.variant = :variant
       INNER JOIN dc_generated_images i ON i.id = l.image_id
       WHERE cc.name = :name
         AND i.deleted = 0
         AND i.status = :status
       ORDER BY l.is_primary DESC, l.created DESC
       LIMIT 1',
      [
        ':table_name' => 'dc_campaign_characters',
        ':slot' => 'portrait',
        ':variant' => 'original',
        ':name' => $name,
        ':status' => 'ready',
      ],
    )->fetchField();

    if ($campaign_image_id) {
      return (int) $campaign_image_id;
    }

    $sprite_object_id = $this->normalizeNameToSpriteObjectId($name);
    if ($sprite_object_id === '') {
      return NULL;
    }

    $sprite_image_id = $this->database->query(
      'SELECT l.image_id
       FROM dc_generated_image_links l
       INNER JOIN dc_generated_images i ON i.id = l.image_id
       WHERE l.table_name = :table_name
         AND l.object_id = :object_id
         AND l.slot = :slot
         AND l.variant = :variant
         AND i.deleted = 0
         AND i.status = :status
       ORDER BY l.is_primary DESC, l.created DESC
       LIMIT 1',
      [
        ':table_name' => 'dc_dungeon_sprites',
        ':object_id' => $sprite_object_id,
        ':slot' => 'portrait',
        ':variant' => 'original',
        ':status' => 'ready',
      ],
    )->fetchField();

    return $sprite_image_id ? (int) $sprite_image_id : NULL;
  }

  /**
   * Normalizes character names to sprite object IDs (snake_case).
   */
  protected function normalizeNameToSpriteObjectId(string $name): string {
    $normalized = strtolower(trim($name));
    if ($normalized === '') {
      return '';
    }

    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    return trim($normalized, '_');
  }

  /**
   * Returns module template examples root.
   */
  public function getTemplatesRootPath(): string {
    return $this->moduleExtensionList->getPath('dungeoncrawler_content') . '/config/examples/templates';
  }

  /**
   * Gets campaign tables that do not have matching template tables.
   */
  public function getMissingTemplatePairs(): array {
    $rows = $this->database->query(
      'SELECT TABLE_NAME
       FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND (TABLE_NAME = :campaigns OR TABLE_NAME LIKE :pattern)',
      [
        ':campaigns' => 'dc_campaigns',
        ':pattern' => 'dc\\_campaign\\_%',
      ],
    )->fetchCol();

    $campaign_tables = array_values(array_filter(array_map('strval', $rows), static fn(string $name): bool => $name !== ''));
    sort($campaign_tables);

    $missing = [];
    foreach ($campaign_tables as $campaign_table) {
      $expected_template = $this->getExpectedTemplateTable($campaign_table);
      if ($expected_template === '') {
        continue;
      }

      $exists = $this->database->query(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
        [':table' => $expected_template],
      )->fetchField();

      if (!$exists) {
        $missing[] = [
          'campaign_table' => $campaign_table,
          'expected_template_table' => $expected_template,
        ];
      }
    }

    return $missing;
  }

  /**
   * Gets expected template table name for a campaign table.
   */
  protected function getExpectedTemplateTable(string $campaign_table): string {
    $runtime_only_tables = [
      'dc_campaign_storyline_log',
      'dc_campaign_storyline_links',
      'dc_campaign_quest_progress',
      'dc_campaign_quest_log',
      'dc_campaign_quest_rewards_claimed',
      'dc_campaign_quest_confirmations',
      'dc_campaign_quests',
      'dc_campaign_settings',
      'dc_campaign_subject_registry',
      'dc_campaign_institution_backfill_review',
    ];

    if (in_array($campaign_table, $runtime_only_tables, TRUE)) {
      return '';
    }

    $explicit_mappings = [
      'dc_campaigns' => 'dungeoncrawler_content_campaigns',
      'dc_campaign_characters' => 'dungeoncrawler_content_characters',
      'dc_campaign_content_registry' => 'dungeoncrawler_content_registry',
      'dc_campaign_loot_tables' => 'dungeoncrawler_content_loot_tables',
      'dc_campaign_encounter_templates' => 'dungeoncrawler_content_encounter_templates',
    ];

    if (isset($explicit_mappings[$campaign_table])) {
      return $explicit_mappings[$campaign_table];
    }

    if (str_starts_with($campaign_table, 'dc_campaign_')) {
      $suffix = substr($campaign_table, strlen('dc_campaign_'));
      if ($suffix !== FALSE && $suffix !== '') {
        return 'dungeoncrawler_content_' . $suffix;
      }
    }

    return '';
  }

  /**
   * Imports all rows from one table directory.
   */
  protected function importTableDirectory(string $table_name, string $table_path): array {
    return $this->importTableFiles($table_name, $this->scanJsonFiles($table_path));
  }

  /**
   * Imports all rows from explicit JSON files for one table.
   *
   * @param array<int, string> $json_files
   *   Absolute JSON file paths to import.
   */
  protected function importTableFiles(string $table_name, array $json_files): array {
    $result = [
      'processed' => 0,
      'inserted' => 0,
      'updated' => 0,
      'skipped' => 0,
      'errors' => [],
    ];

    if (!$this->database->schema()->tableExists($table_name)) {
      $result['errors'][] = sprintf('Table %s does not exist. Skipped directory %s.', $table_name, $table_name);
      return $result;
    }

    $columns = $this->getTableColumns($table_name);
    $merge_keys = $this->getMergeKeys($table_name);

    foreach ($json_files as $json_file) {
      $rows = $this->extractRows($json_file);
      if (empty($rows)) {
        continue;
      }

      foreach ($rows as $row) {
        $result['processed']++;
        if (!is_array($row)) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Skipping non-object row in %s.', $this->relativePath($json_file));
          continue;
        }

        $normalized = $this->normalizeRow($table_name, $row, $columns, $json_file);
        if (empty($normalized)) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Skipping row with no matching columns for table %s in %s.', $table_name, $this->relativePath($json_file));
          continue;
        }

        $keys = [];
        foreach ($merge_keys as $key_column) {
          if (!array_key_exists($key_column, $normalized)) {
            $keys = [];
            break;
          }
          $keys[$key_column] = $normalized[$key_column];
        }

        if (empty($keys)) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Skipping row missing merge keys (%s) for table %s in %s.', implode(', ', $merge_keys), $table_name, $this->relativePath($json_file));
          continue;
        }

        $was_existing = $this->rowExists($table_name, $keys);

        try {
          $this->database->merge($table_name)
            ->keys($keys)
            ->fields($normalized)
            ->execute();

          if ($was_existing) {
            $result['updated']++;
          }
          else {
            $result['inserted']++;
          }
        }
        catch (\Throwable $throwable) {
          $result['skipped']++;
          $result['errors'][] = sprintf('Failed importing row into %s from %s: %s', $table_name, $this->relativePath($json_file), $throwable->getMessage());
          $this->logger->error('Failed importing row into @table from @file: @message', [
            '@table' => $table_name,
            '@file' => $json_file,
            '@message' => $throwable->getMessage(),
          ]);
        }
      }
    }

    return $result;
  }

  /**
   * Rebuild canonical relational contract tables from library template tables.
   *
   * @return array<string, int>
   *   Row counts written per canonical table family.
   */
  public function synchronizeCanonicalContracts(): array {
    $required_tables = [
      'dc_canonical_storylines',
      'dc_canonical_quests',
      'dc_canonical_actors',
      'dc_canonical_locations',
      'dc_canonical_storyline_quests',
      'dc_canonical_storyline_actors',
      'dc_canonical_storyline_locations',
      'dc_canonical_objective_actor_refs',
      'dc_canonical_objective_location_refs',
      'dc_canonical_objective_room_refs',
      'dc_canonical_objective_dungeon_refs',
      'dc_canonical_objective_item_refs',
      'dc_canonical_objective_hazard_refs',
      'dc_canonical_objective_refs',
    ];
    foreach ($required_tables as $required_table) {
      if (!$this->database->schema()->tableExists($required_table)) {
        throw new \RuntimeException('Canonical contract sync requires table: ' . $required_table);
      }
    }

    $counts = [
      'storylines' => 0,
      'quests' => 0,
      'actors' => 0,
      'locations' => 0,
      'storyline_quests' => 0,
      'storyline_actors' => 0,
      'storyline_locations' => 0,
      'objective_refs' => 0,
    ];
    $now = time();

    $transaction = $this->database->startTransaction();
    try {
      foreach ([
        'dc_canonical_storyline_quests',
        'dc_canonical_storyline_actors',
        'dc_canonical_storyline_locations',
        'dc_canonical_objective_actor_refs',
        'dc_canonical_objective_location_refs',
        'dc_canonical_objective_room_refs',
        'dc_canonical_objective_dungeon_refs',
        'dc_canonical_objective_item_refs',
        'dc_canonical_objective_hazard_refs',
        'dc_canonical_objective_refs',
        'dc_canonical_storylines',
        'dc_canonical_quests',
        'dc_canonical_actors',
        'dc_canonical_locations',
      ] as $table_name) {
        $this->database->truncate($table_name)->execute();
      }

      $actor_map = [];
      $actor_rows = $this->database->select('dungeoncrawler_content_characters', 'c')
        ->fields('c', ['instance_id', 'type', 'state_data'])
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
      foreach ($actor_rows as $actor_row) {
        $actor_id = trim((string) ($actor_row['instance_id'] ?? ''));
        if ($actor_id === '') {
          continue;
        }
        $state = $this->decodeJsonValue($actor_row['state_data'] ?? NULL);
        $version = trim((string) ($state['version'] ?? '1.0.0')) ?: '1.0.0';
        $actor_map[$actor_id] = $version;
        $this->database->insert('dc_canonical_actors')
          ->fields([
            'actor_id' => $actor_id,
            'version' => $version,
            'actor_type' => trim((string) ($actor_row['type'] ?? 'npc')) ?: 'npc',
            'display_name' => trim((string) ($state['name'] ?? $actor_id)),
            'source_module' => trim((string) ($state['source_module'] ?? '')),
            'state_data' => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
          ])
          ->execute();
        $counts['actors']++;
      }

      $location_map = [];
      $pending_storyline_location_links = [];
      $pending_storyline_quest_links = [];
      $storyline_rows = $this->database->select('dungeoncrawler_content_storylines', 's')
        ->fields('s', ['id', 'template_id', 'version', 'name', 'synopsis', 'source', 'template_data'])
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
      foreach ($storyline_rows as $storyline_row) {
        $template_id = trim((string) ($storyline_row['template_id'] ?? ''));
        if ($template_id === '') {
          continue;
        }
        $template_data = $this->decodeJsonValue($storyline_row['template_data'] ?? NULL);
        $version = trim((string) ($storyline_row['version'] ?? ($template_data['version'] ?? '1.0.0'))) ?: '1.0.0';
        $entry = $this->extractStorylineEntryPointRefs($template_data);
        $entry_actor_id = trim((string) ($entry['actor_id'] ?? '')) !== '' ? trim((string) $entry['actor_id']) : NULL;
        $entry_location_id = trim((string) ($entry['location_id'] ?? '')) !== '' ? trim((string) $entry['location_id']) : NULL;
        $primary_quest_id = $this->extractPrimaryQuestId($template_data);
        $contract_hash = hash('sha256', json_encode($template_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->database->insert('dc_canonical_storylines')
          ->fields([
            'template_id' => $template_id,
            'version' => $version,
            'name' => (string) ($storyline_row['name'] ?? $template_id),
            'synopsis' => (string) ($storyline_row['synopsis'] ?? ''),
            'source' => (string) ($storyline_row['source'] ?? ''),
            'entry_point_actor_id' => $entry_actor_id,
            'entry_point_location_id' => $entry_location_id,
            'primary_quest_id' => $primary_quest_id,
            'contract_hash' => $contract_hash,
            'template_data' => json_encode($template_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
          ])
          ->execute();
        $counts['storylines']++;

        $update_fields = [];
        foreach (['version', 'entry_point_actor_id', 'entry_point_location_id', 'primary_quest_id', 'contract_hash'] as $field_name) {
          if ($this->database->schema()->fieldExists('dungeoncrawler_content_storylines', $field_name)) {
            $update_fields[$field_name] = match ($field_name) {
              'version' => $version,
              'entry_point_actor_id' => $entry_actor_id,
              'entry_point_location_id' => $entry_location_id,
              'primary_quest_id' => $primary_quest_id,
              'contract_hash' => $contract_hash,
            };
          }
        }
        if ($update_fields !== []) {
          $this->database->update('dungeoncrawler_content_storylines')
            ->fields($update_fields)
            ->condition('id', (int) ($storyline_row['id'] ?? 0))
            ->execute();
        }

        foreach ($this->extractStorylineLocationRefs($template_data) as $location_ref) {
          if ($location_ref['location_id'] === '') {
            continue;
          }
          $location_map[$location_ref['location_id']] = $version;
          $pending_storyline_location_links[] = [
            'storyline_template_id' => $template_id,
            'storyline_version' => $version,
            'location_id' => $location_ref['location_id'],
            'location_version' => $version,
            'location_type' => $location_ref['location_type'],
            'location_role' => $location_ref['location_role'],
            'chapter_id' => $location_ref['chapter_id'],
            'scene_id' => $location_ref['scene_id'],
            'source_scope' => $location_ref['source_scope'],
          ];
        }

        foreach ($this->extractStorylineActorRefs($template_data) as $actor_ref) {
          if ($actor_ref['actor_id'] === '' || !isset($actor_map[$actor_ref['actor_id']])) {
            throw new \RuntimeException(sprintf('Canonical storyline %s references missing actor "%s".', $template_id, $actor_ref['actor_id']));
          }
          $this->database->merge('dc_canonical_storyline_actors')
            ->keys([
              'storyline_template_id' => $template_id,
              'storyline_version' => $version,
              'actor_id' => $actor_ref['actor_id'],
              'actor_version' => (string) $actor_map[$actor_ref['actor_id']],
              'actor_role' => $actor_ref['actor_role'],
              'chapter_id' => $actor_ref['chapter_id'],
              'scene_id' => $actor_ref['scene_id'],
              'source_scope' => $actor_ref['source_scope'],
            ])
            ->fields(['created_at' => $now, 'updated_at' => $now])
            ->execute();
          $counts['storyline_actors']++;
        }

        foreach ($this->extractStorylineQuestRefs($template_data) as $quest_ref) {
          $pending_storyline_quest_links[] = [
            'storyline_template_id' => $template_id,
            'storyline_version' => $version,
            'quest_template_id' => $quest_ref['quest_template_id'],
            'quest_version' => '1.0.0',
            'chapter_id' => $quest_ref['chapter_id'],
            'scene_id' => $quest_ref['scene_id'],
            'source_scope' => 'scene',
          ];
        }
      }

      $registry_type_map = [];
      if ($this->database->schema()->tableExists('dungeoncrawler_content_registry')) {
        $registry_rows = $this->database->select('dungeoncrawler_content_registry', 'r')
          ->fields('r', ['content_id', 'content_type'])
          ->execute()
          ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($registry_rows as $registry_row) {
          $content_id = trim((string) ($registry_row['content_id'] ?? ''));
          if ($content_id === '') {
            continue;
          }
          $registry_type_map[$content_id] = strtolower(trim((string) ($registry_row['content_type'] ?? '')));
        }
      }
      $canonical_room_map = [];
      if ($this->database->schema()->tableExists('dungeoncrawler_content_rooms')) {
        $room_rows = $this->database->select('dungeoncrawler_content_rooms', 'r')
          ->fields('r', ['room_id'])
          ->execute()
          ->fetchCol() ?: [];
        foreach ($room_rows as $room_id) {
          $room_id = trim((string) $room_id);
          if ($room_id !== '') {
            $canonical_room_map[$room_id] = TRUE;
          }
        }
      }
      $canonical_dungeon_map = [];
      if ($this->database->schema()->tableExists('dungeoncrawler_content_dungeons')) {
        $dungeon_rows = $this->database->select('dungeoncrawler_content_dungeons', 'd')
          ->fields('d', ['dungeon_id'])
          ->execute()
          ->fetchCol() ?: [];
        foreach ($dungeon_rows as $dungeon_id) {
          $dungeon_id = trim((string) $dungeon_id);
          if ($dungeon_id !== '') {
            $canonical_dungeon_map[$dungeon_id] = TRUE;
          }
        }
      }

      $quest_rows = $this->database->select('dungeoncrawler_content_quest_templates', 'q')
        ->fields('q', ['id', 'template_id', 'version', 'name', 'description', 'quest_type', 'level_min', 'level_max', 'tags', 'prerequisites', 'estimated_duration_minutes', 'story_impact', 'objectives_schema', 'rewards_schema'])
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
      foreach ($quest_rows as $quest_row) {
        $template_id = trim((string) ($quest_row['template_id'] ?? ''));
        if ($template_id === '') {
          continue;
        }
        $version = trim((string) ($quest_row['version'] ?? '1.0.0')) ?: '1.0.0';
        $story_impact = $this->decodeJsonValue($quest_row['story_impact'] ?? NULL);
        $objective_phases = $this->decodeJsonValue($quest_row['objectives_schema'] ?? NULL);
        $refs = $this->normalizeObjectiveRefs(
          $this->extractObjectiveRefs($objective_phases),
          $actor_map,
          $registry_type_map,
          $template_id
        );
        $entry_actor_id = $this->findFirstObjectiveRef($refs, 'actor');
        $entry_actor_id = trim((string) $entry_actor_id) !== '' ? trim((string) $entry_actor_id) : NULL;
        $entry_location_id = trim((string) ($story_impact['scene_id'] ?? $this->findFirstObjectiveRef($refs, 'location')));
        $entry_location_id = $entry_location_id !== '' ? $entry_location_id : NULL;
        $storyline_template_id = trim((string) ($story_impact['storyline_id'] ?? ''));
        $storyline_template_id = $storyline_template_id !== '' ? $storyline_template_id : NULL;
        $contract_hash = hash('sha256', json_encode([
          'template_id' => $template_id,
          'objectives_schema' => $objective_phases,
          'story_impact' => $story_impact,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->database->insert('dc_canonical_quests')
          ->fields([
            'template_id' => $template_id,
            'version' => $version,
            'name' => (string) ($quest_row['name'] ?? $template_id),
            'description' => (string) ($quest_row['description'] ?? ''),
            'quest_type' => (string) ($quest_row['quest_type'] ?? 'module_story'),
            'level_min' => max(1, (int) ($quest_row['level_min'] ?? 1)),
            'level_max' => max(1, (int) ($quest_row['level_max'] ?? 20)),
            'tags' => (string) ($quest_row['tags'] ?? '[]'),
            'prerequisites' => (string) ($quest_row['prerequisites'] ?? '[]'),
            'estimated_duration_minutes' => isset($quest_row['estimated_duration_minutes']) && $quest_row['estimated_duration_minutes'] !== '' ? (int) $quest_row['estimated_duration_minutes'] : NULL,
            'storyline_template_id' => $storyline_template_id,
            'entry_point_actor_id' => $entry_actor_id,
            'entry_point_location_id' => $entry_location_id,
            'contract_hash' => $contract_hash,
            'objectives_schema' => json_encode($objective_phases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'rewards_schema' => (string) ($quest_row['rewards_schema'] ?? '{}'),
            'story_impact' => json_encode($story_impact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
          ])
          ->execute();
        $counts['quests']++;

        $update_fields = [];
        foreach (['storyline_template_id', 'entry_point_actor_id', 'entry_point_location_id', 'contract_hash'] as $field_name) {
          if ($this->database->schema()->fieldExists('dungeoncrawler_content_quest_templates', $field_name)) {
            $update_fields[$field_name] = match ($field_name) {
              'storyline_template_id' => $storyline_template_id,
              'entry_point_actor_id' => $entry_actor_id,
              'entry_point_location_id' => $entry_location_id,
              'contract_hash' => $contract_hash,
            };
          }
        }
        if ($update_fields !== []) {
          $this->database->update('dungeoncrawler_content_quest_templates')
            ->fields($update_fields)
            ->condition('id', (int) ($quest_row['id'] ?? 0))
            ->execute();
        }

        foreach ($refs as $ref) {
          if ($ref['ref_type'] === 'actor') {
            $actor_id = trim((string) ($ref['ref_id'] ?? ''));
            if ($actor_id === '' || !isset($actor_map[$actor_id])) {
              throw new \RuntimeException(sprintf(
                'Canonical quest "%s" objective actor ref "%s" does not map to a concrete canonical actor.',
                $template_id,
                $actor_id
              ));
            }
            $this->database->merge('dc_canonical_objective_actor_refs')
              ->keys([
                'quest_template_id' => $template_id,
                'quest_version' => $version,
                'objective_path' => $ref['objective_path'],
                'ref_field' => $ref['ref_field'],
                'actor_id' => $actor_id,
                'actor_version' => (string) $actor_map[$actor_id],
              ])
              ->fields([
                'storyline_template_id' => $storyline_template_id,
                'phase' => $ref['phase'],
                'objective_id' => $ref['objective_id'],
                'objective_type' => $ref['objective_type'],
                'created_at' => $now,
                'updated_at' => $now,
              ])
              ->execute();
            $counts['objective_refs']++;
            continue;
          }

          if ($ref['ref_type'] === 'location') {
            $location_id = trim((string) ($ref['ref_id'] ?? ''));
            if ($location_id === '') {
              continue;
            }
            if (isset($canonical_room_map[$location_id])) {
              $this->database->merge('dc_canonical_objective_room_refs')
                ->keys([
                  'quest_template_id' => $template_id,
                  'quest_version' => $version,
                  'objective_path' => $ref['objective_path'],
                  'ref_field' => $ref['ref_field'],
                  'room_id' => $location_id,
                ])
                ->fields([
                  'storyline_template_id' => $storyline_template_id,
                  'phase' => $ref['phase'],
                  'objective_id' => $ref['objective_id'],
                  'objective_type' => $ref['objective_type'],
                  'created_at' => $now,
                  'updated_at' => $now,
                ])
                ->execute();
              $counts['objective_refs']++;
              continue;
            }
            if (isset($canonical_dungeon_map[$location_id])) {
              $this->database->merge('dc_canonical_objective_dungeon_refs')
                ->keys([
                  'quest_template_id' => $template_id,
                  'quest_version' => $version,
                  'objective_path' => $ref['objective_path'],
                  'ref_field' => $ref['ref_field'],
                  'dungeon_id' => $location_id,
                ])
                ->fields([
                  'storyline_template_id' => $storyline_template_id,
                  'phase' => $ref['phase'],
                  'objective_id' => $ref['objective_id'],
                  'objective_type' => $ref['objective_type'],
                  'created_at' => $now,
                  'updated_at' => $now,
                ])
                ->execute();
              $counts['objective_refs']++;
              continue;
            }

            if (!isset($location_map[$location_id])) {
              throw new \RuntimeException(sprintf(
                'Canonical quest "%s" objective location ref "%s" does not map to canonical location/room/dungeon tables.',
                $template_id,
                $location_id
              ));
            }

            $this->database->merge('dc_canonical_objective_location_refs')
              ->keys([
                'quest_template_id' => $template_id,
                'quest_version' => $version,
                'objective_path' => $ref['objective_path'],
                'ref_field' => $ref['ref_field'],
                'location_id' => $location_id,
                'location_version' => (string) $location_map[$location_id],
              ])
              ->fields([
                'storyline_template_id' => $storyline_template_id,
                'phase' => $ref['phase'],
                'objective_id' => $ref['objective_id'],
                'objective_type' => $ref['objective_type'],
                'created_at' => $now,
                'updated_at' => $now,
              ])
              ->execute();
            $counts['objective_refs']++;
            continue;
          }

          if ($ref['ref_type'] === 'item' || $ref['ref_type'] === 'hazard') {
            $content_id = trim((string) ($ref['ref_id'] ?? ''));
            if ($content_id === '') {
              continue;
            }
            $expected_type = $ref['ref_type'] === 'item' ? 'item' : 'hazard';
            $actual_type = strtolower((string) ($registry_type_map[$content_id] ?? ''));
            if ($actual_type !== $expected_type) {
              throw new \RuntimeException(sprintf(
                'Canonical quest "%s" objective %s ref "%s" is not present as registry content_type=%s.',
                $template_id,
                $expected_type,
                $content_id,
                $expected_type
              ));
            }
            $target_table = $expected_type === 'item'
              ? 'dc_canonical_objective_item_refs'
              : 'dc_canonical_objective_hazard_refs';
            $this->database->merge($target_table)
              ->keys([
                'quest_template_id' => $template_id,
                'quest_version' => $version,
                'objective_path' => $ref['objective_path'],
                'ref_field' => $ref['ref_field'],
                'content_type' => $expected_type,
                'content_id' => $content_id,
              ])
              ->fields([
                'storyline_template_id' => $storyline_template_id,
                'phase' => $ref['phase'],
                'objective_id' => $ref['objective_id'],
                'objective_type' => $ref['objective_type'],
                'created_at' => $now,
                'updated_at' => $now,
              ])
              ->execute();
            $counts['objective_refs']++;
            continue;
          }

          // Legacy catch-all mirror for unsupported ref types.
          $this->database->merge('dc_canonical_objective_refs')
            ->keys([
              'quest_template_id' => $template_id,
              'quest_version' => $version,
              'objective_path' => $ref['objective_path'],
              'ref_field' => $ref['ref_field'],
              'ref_id' => $ref['ref_id'],
            ])
            ->fields([
              'storyline_template_id' => $storyline_template_id,
              'phase' => $ref['phase'],
              'objective_id' => $ref['objective_id'],
              'objective_type' => $ref['objective_type'],
              'ref_type' => $ref['ref_type'],
              'created_at' => $now,
              'updated_at' => $now,
            ])
            ->execute();
          $counts['objective_refs']++;
        }
      }

      foreach (array_keys($location_map) as $location_id) {
        $this->database->merge('dc_canonical_locations')
          ->keys([
            'location_id' => $location_id,
            'version' => '1.0.0',
          ])
          ->fields([
            'location_type' => 'location',
            'display_name' => $location_id,
            'source_module' => '',
            'payload_json' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
          ])
          ->execute();
        $counts['locations']++;
      }

      foreach ($pending_storyline_location_links as $location_link) {
        $this->database->merge('dc_canonical_storyline_locations')
          ->keys([
            'storyline_template_id' => (string) ($location_link['storyline_template_id'] ?? ''),
            'storyline_version' => (string) ($location_link['storyline_version'] ?? '1.0.0'),
            'location_id' => (string) ($location_link['location_id'] ?? ''),
            'location_version' => (string) ($location_link['location_version'] ?? '1.0.0'),
            'location_type' => (string) ($location_link['location_type'] ?? 'location'),
            'location_role' => (string) ($location_link['location_role'] ?? 'linked'),
            'chapter_id' => (string) ($location_link['chapter_id'] ?? ''),
            'scene_id' => (string) ($location_link['scene_id'] ?? ''),
            'source_scope' => (string) ($location_link['source_scope'] ?? 'asset'),
          ])
          ->fields([
            'created_at' => $now,
            'updated_at' => $now,
          ])
          ->execute();
        $counts['storyline_locations']++;
      }

      foreach ($pending_storyline_quest_links as $quest_link) {
        $this->database->merge('dc_canonical_storyline_quests')
          ->keys([
            'storyline_template_id' => (string) ($quest_link['storyline_template_id'] ?? ''),
            'storyline_version' => (string) ($quest_link['storyline_version'] ?? '1.0.0'),
            'quest_template_id' => (string) ($quest_link['quest_template_id'] ?? ''),
            'quest_version' => (string) ($quest_link['quest_version'] ?? '1.0.0'),
            'chapter_id' => (string) ($quest_link['chapter_id'] ?? ''),
            'scene_id' => (string) ($quest_link['scene_id'] ?? ''),
            'source_scope' => (string) ($quest_link['source_scope'] ?? 'scene'),
          ])
          ->fields([
            'created_at' => $now,
            'updated_at' => $now,
          ])
          ->execute();
        $counts['storyline_quests']++;
      }
    }
    catch (\Throwable $throwable) {
      unset($transaction);
      throw $throwable;
    }
    unset($transaction);

    return $counts;
  }

  /**
   * Decode a JSON-ish DB value.
   */
  protected function decodeJsonValue(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }
    if (!is_string($value) || trim($value) === '') {
      return [];
    }
    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Resolve entry-point refs promoted from storyline JSON.
   *
   * @return array{actor_id: string, location_id: string}
   *   Entry-point actor/location refs.
   */
  protected function extractStorylineEntryPointRefs(array $template_data): array {
    $entry_point = is_array($template_data['metadata']['generated_outline']['entry_point'] ?? NULL)
      ? $template_data['metadata']['generated_outline']['entry_point']
      : [];
    return [
      'actor_id' => trim((string) ($entry_point['primary_quest_giver_id'] ?? '')),
      'location_id' => trim((string) ($entry_point['primary_location_id'] ?? ($entry_point['primary_scene_id'] ?? ''))),
    ];
  }

  /**
   * Resolve first quest id in storyline chapters/scenes.
   */
  protected function extractPrimaryQuestId(array $template_data): string {
    foreach ((array) ($template_data['chapters'] ?? []) as $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        if (!is_array($scene)) {
          continue;
        }
        foreach ((array) ($scene['quest_ids'] ?? []) as $quest_id) {
          $quest_id = trim((string) $quest_id);
          if ($quest_id !== '') {
            return $quest_id;
          }
        }
      }
    }
    return '';
  }

  /**
   * Extract storyline -> quest linkage rows from chapter scene contracts.
   *
   * @return array<int, array{quest_template_id: string, chapter_id: string, scene_id: string}>
   *   Canonical storyline quest references.
   */
  protected function extractStorylineQuestRefs(array $template_data): array {
    $rows = [];
    foreach ((array) ($template_data['chapters'] ?? []) as $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        if (!is_array($scene)) {
          continue;
        }
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        foreach ((array) ($scene['quest_ids'] ?? []) as $quest_id) {
          $quest_id = trim((string) $quest_id);
          if ($quest_id === '') {
            continue;
          }
          $rows[] = [
            'quest_template_id' => $quest_id,
            'chapter_id' => $chapter_id,
            'scene_id' => $scene_id,
          ];
        }
      }
    }
    return $rows;
  }

  /**
   * Extract storyline -> actor linkage rows from asset/contact contracts.
   *
   * @return array<int, array{actor_id: string, actor_role: string, chapter_id: string, scene_id: string, source_scope: string}>
   *   Canonical storyline actor references.
   */
  protected function extractStorylineActorRefs(array $template_data): array {
    $rows = [];
    foreach ((array) ($template_data['asset_references'] ?? []) as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      if (!in_array($asset_type, ['npc', 'character', 'character_group'], TRUE)) {
        continue;
      }
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_id === '') {
        continue;
      }
      $rows[] = [
        'actor_id' => $asset_id,
        'actor_role' => trim((string) ($reference['asset_role'] ?? 'linked')),
        'chapter_id' => trim((string) ($reference['chapter_id'] ?? '')),
        'scene_id' => trim((string) ($reference['scene_id'] ?? '')),
        'source_scope' => 'asset',
      ];
    }
    foreach ((array) ($template_data['contacts'] ?? []) as $contact) {
      if (!is_array($contact)) {
        continue;
      }
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      $entity_type = trim((string) ($contact['entity_type'] ?? ''));
      if ($entity_id === '' || !in_array($entity_type, ['npc_template', 'character_template'], TRUE)) {
        continue;
      }
      $relationship_state = is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [];
      $rows[] = [
        'actor_id' => $entity_id,
        'actor_role' => trim((string) ($contact['role'] ?? 'contact')),
        'chapter_id' => '',
        'scene_id' => trim((string) ($relationship_state['scene_id'] ?? '')),
        'source_scope' => 'contact',
      ];
    }
    return $rows;
  }

  /**
   * Extract storyline -> location linkage rows from chapters/scenes/assets.
   *
   * @return array<int, array{location_id: string, location_type: string, location_role: string, chapter_id: string, scene_id: string, source_scope: string}>
   *   Canonical storyline location references.
   */
  protected function extractStorylineLocationRefs(array $template_data): array {
    $rows = [];
    foreach ((array) ($template_data['chapters'] ?? []) as $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id !== '') {
        $rows[] = [
          'location_id' => $chapter_id,
          'location_type' => 'chapter',
          'location_role' => 'chapter',
          'chapter_id' => $chapter_id,
          'scene_id' => '',
          'source_scope' => 'chapter',
        ];
      }
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        if (!is_array($scene)) {
          continue;
        }
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id === '') {
          continue;
        }
        $rows[] = [
          'location_id' => $scene_id,
          'location_type' => 'scene',
          'location_role' => 'scene',
          'chapter_id' => $chapter_id,
          'scene_id' => $scene_id,
          'source_scope' => 'scene',
        ];
      }
    }
    foreach ((array) ($template_data['asset_references'] ?? []) as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = trim((string) ($reference['asset_type'] ?? ''));
      if (!in_array($asset_type, ['location', 'room', 'dungeon'], TRUE)) {
        continue;
      }
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($asset_id === '') {
        continue;
      }
      $rows[] = [
        'location_id' => $asset_id,
        'location_type' => $asset_type,
        'location_role' => trim((string) ($reference['asset_role'] ?? 'linked')),
        'chapter_id' => trim((string) ($reference['chapter_id'] ?? '')),
        'scene_id' => trim((string) ($reference['scene_id'] ?? '')),
        'source_scope' => 'asset',
      ];
    }
    return $rows;
  }

  /**
   * Flatten objective actor/location refs from objective phase payload.
   *
   * @return array<int, array{phase: int, objective_id: string, objective_type: string, objective_path: string, ref_type: string, ref_field: string, ref_id: string}>
   *   Flattened canonical objective references.
   */
  protected function extractObjectiveRefs(array $objective_phases): array {
    $rows = [];
    foreach ($objective_phases as $phase_index => $phase_row) {
      if (!is_array($phase_row)) {
        continue;
      }
      $phase = isset($phase_row['phase']) && is_numeric($phase_row['phase']) ? (int) $phase_row['phase'] : ($phase_index + 1);
      $objectives = is_array($phase_row['objectives'] ?? NULL) ? $phase_row['objectives'] : [];
      $this->collectObjectiveRefsRecursive($objectives, "phase[{$phase_index}].objectives", $phase, $rows);
    }
    return $rows;
  }

  /**
   * Recursively collect canonical objective references.
   */
  protected function collectObjectiveRefsRecursive(array $objectives, string $path_prefix, int $phase, array &$rows): void {
    foreach ($objectives as $index => $objective) {
      if (!is_array($objective)) {
        continue;
      }
      $objective_path = $path_prefix . '[' . $index . ']';
      $objective_id = trim((string) ($objective['objective_id'] ?? ''));
      $objective_type = trim((string) ($objective['type'] ?? ''));
      foreach (['target', 'target_id', 'giver_id', 'receiver_id', 'recipient_id', 'turn_in_target', 'actor_id', 'npc_id'] as $field_name) {
        $value = trim((string) ($objective[$field_name] ?? ''));
        if ($value === '') {
          continue;
        }
        $rows[] = [
          'phase' => $phase,
          'objective_id' => $objective_id,
          'objective_type' => $objective_type,
          'objective_path' => $objective_path,
          'ref_type' => 'actor',
          'ref_field' => $field_name,
          'ref_id' => $value,
        ];
      }
      foreach (['location_id', 'destination_id', 'destination', 'room_id', 'dungeon_id', 'scene_id'] as $field_name) {
        $value = trim((string) ($objective[$field_name] ?? ''));
        if ($value === '') {
          continue;
        }
        $rows[] = [
          'phase' => $phase,
          'objective_id' => $objective_id,
          'objective_type' => $objective_type,
          'objective_path' => $objective_path,
          'ref_type' => 'location',
          'ref_field' => $field_name,
          'ref_id' => $value,
        ];
      }
      foreach (['item_id', 'item', 'required_item_id', 'reward_item_id'] as $field_name) {
        $value = trim((string) ($objective[$field_name] ?? ''));
        if ($value === '') {
          continue;
        }
        $rows[] = [
          'phase' => $phase,
          'objective_id' => $objective_id,
          'objective_type' => $objective_type,
          'objective_path' => $objective_path,
          'ref_type' => 'item',
          'ref_field' => $field_name,
          'ref_id' => $value,
        ];
      }
      if (is_array($objective['children'] ?? NULL) && $objective['children'] !== []) {
        $this->collectObjectiveRefsRecursive($objective['children'], $objective_path . '.children', $phase, $rows);
      }
    }
  }

  /**
   * Normalize objective refs to concrete canonical entities or fail hard.
   *
   * @param array<string, string> $actor_map
   *   Canonical actor id => version map.
   * @param array<string, string> $registry_type_map
   *   Registry content id => content type map.
   *
   * @return array<int, array{phase: int, objective_id: string, objective_type: string, objective_path: string, ref_type: string, ref_field: string, ref_id: string}>
   *   Normalized reference rows.
   */
  protected function normalizeObjectiveRefs(array $refs, array $actor_map, array $registry_type_map, string $quest_template_id): array {
    $normalized = [];
    foreach ($refs as $ref) {
      if (!is_array($ref)) {
        continue;
      }

      $ref_type = trim((string) ($ref['ref_type'] ?? ''));
      $ref_id = trim((string) ($ref['ref_id'] ?? ''));
      if ($ref_id === '') {
        continue;
      }

      if ($this->isTemplateVariableReference($ref_id)) {
        continue;
      }

      if ($ref_type !== 'actor') {
        $normalized[] = $ref;
        continue;
      }

      $registry_type = strtolower((string) ($registry_type_map[$ref_id] ?? ''));
      if ($registry_type === 'hazard') {
        $ref['ref_type'] = 'hazard';
        $normalized[] = $ref;
        continue;
      }
      if ($registry_type === 'item') {
        $ref['ref_type'] = 'item';
        $normalized[] = $ref;
        continue;
      }
      if (in_array($registry_type, ['location', 'room', 'dungeon'], TRUE)) {
        $ref['ref_type'] = 'location';
        $normalized[] = $ref;
        continue;
      }

      $resolved_actor_id = $this->resolveCanonicalActorReference($ref_id, $actor_map);
      if ($resolved_actor_id !== '') {
        $ref['ref_id'] = $resolved_actor_id;
        $normalized[] = $ref;
        continue;
      }

      if ($this->isAbstractActorReferenceToken($ref_id)) {
        continue;
      }

      throw new \RuntimeException(sprintf(
        'Canonical quest "%s" objective ref "%s" in field "%s" (%s) does not map to a concrete canonical actor.',
        $quest_template_id,
        $ref_id,
        (string) ($ref['ref_field'] ?? ''),
        (string) ($ref['objective_path'] ?? 'objective')
      ));
    }

    return $normalized;
  }

  /**
   * Resolve one actor ref to a concrete canonical actor id.
   *
   * @param array<string, string> $actor_map
   *   Canonical actor id => version map.
   */
  protected function resolveCanonicalActorReference(string $actor_ref, array $actor_map): string {
    $raw = trim($actor_ref);
    if ($raw === '') {
      return '';
    }

    $normalized = strtolower($raw);
    $normalized = preg_replace('/\s+/', '_', $normalized) ?? $normalized;
    $normalized = preg_replace('/[^a-z0-9_:-]/', '', $normalized) ?? $normalized;
    $normalized = trim($normalized, '_');
    if ($normalized === '') {
      return '';
    }

    $candidates = [$normalized];
    if (str_starts_with($normalized, 'npc_')) {
      $candidates[] = substr($normalized, 4);
    }
    else {
      $candidates[] = 'npc_' . $normalized;
    }

    foreach (array_values(array_unique(array_filter($candidates, static fn(string $value): bool => $value !== ''))) as $candidate) {
      if (isset($actor_map[$candidate])) {
        return $candidate;
      }
    }

    return '';
  }

  /**
   * Template variable placeholders (e.g. {npc}) are not concrete actor ids.
   */
  protected function isTemplateVariableReference(string $value): bool {
    return preg_match('/^\{[^}]+\}$/', trim($value)) === 1;
  }

  /**
   * Generic actor tokens represent abstract roles, not canonical actor rows.
   */
  protected function isAbstractActorReferenceToken(string $value): bool {
    $token = strtolower(trim($value));
    return in_array($token, ['npc', 'target', 'giver', 'receiver'], TRUE);
  }

  /**
   * Return the first objective reference id matching the requested reference type.
   */
  protected function findFirstObjectiveRef(array $refs, string $ref_type): string {
    foreach ($refs as $ref) {
      if (!is_array($ref)) {
        continue;
      }
      if (trim((string) ($ref['ref_type'] ?? '')) !== $ref_type) {
        continue;
      }
      $ref_id = trim((string) ($ref['ref_id'] ?? ''));
      if ($ref_id !== '') {
        return $ref_id;
      }
    }
    return '';
  }

  /**
   * Resolves requested JSON basenames for a table directory.
   *
   * @param array<int, string> $requested_files
   *   JSON basenames relative to the table directory.
   *
   * @return array{files: array<int, string>, errors: array<int, string>}
   *   Resolved absolute file paths and any resolution errors.
   */
  protected function resolveTableJsonFiles(string $table_name, array $requested_files): array {
    $result = [
      'files' => [],
      'errors' => [],
    ];

    $table_path = $this->getTemplatesRootPath() . '/' . $table_name;
    if (!is_dir($table_path)) {
      $result['errors'][] = sprintf('Templates directory not found for table %s: %s', $table_name, $table_path);
      return $result;
    }

    foreach ($requested_files as $file_name) {
      $normalized_name = ltrim((string) $file_name, '/');
      if ($normalized_name === '') {
        continue;
      }

      $candidate = $table_path . '/' . $normalized_name;
      if (!is_file($candidate)) {
        $result['errors'][] = sprintf('Template file not found for table %s: %s', $table_name, $normalized_name);
        continue;
      }

      if (strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION)) !== 'json') {
        $result['errors'][] = sprintf('Template file is not JSON for table %s: %s', $table_name, $normalized_name);
        continue;
      }

      $result['files'][] = $candidate;
    }

    sort($result['files']);
    return $result;
  }

  /**
   * Extracts import rows from a JSON file.
   */
  protected function extractRows(string $json_file): array {
    $contents = file_get_contents($json_file);
    if ($contents === FALSE) {
      return [];
    }

    $data = json_decode($contents, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return [];
    }

    if (is_array($data) && isset($data['rows']) && is_array($data['rows'])) {
      return $data['rows'];
    }

    if ($this->isList($data)) {
      return $data;
    }

    return is_array($data) ? [$data] : [];
  }

  /**
   * Gets table columns and metadata.
   */
  protected function getTableColumns(string $table_name): array {
    $query = $this->database->query(
      'SELECT COLUMN_NAME, DATA_TYPE
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
      [':table' => $table_name],
    );

    $columns = [];
    foreach ($query->fetchAll() as $row) {
      $columns[(string) $row->COLUMN_NAME] = [
        'data_type' => (string) $row->DATA_TYPE,
      ];
    }

    return $columns;
  }

  /**
   * Selects merge keys for a table, preferring non-primary unique indexes.
   */
  protected function getMergeKeys(string $table_name): array {
    $query = $this->database->query(
      'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
       FROM information_schema.STATISTICS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
       ORDER BY INDEX_NAME, SEQ_IN_INDEX',
      [':table' => $table_name],
    );

    $indexes = [];
    foreach ($query->fetchAll() as $row) {
      if ((int) $row->NON_UNIQUE !== 0) {
        continue;
      }
      $index_name = (string) $row->INDEX_NAME;
      $indexes[$index_name][] = (string) $row->COLUMN_NAME;
    }

    foreach ($indexes as $index_name => $columns) {
      if ($index_name !== 'PRIMARY') {
        return $columns;
      }
    }

    return $indexes['PRIMARY'] ?? [];
  }

  /**
   * Converts and filters row values to match table columns.
   */
  protected function normalizeRow(string $table_name, array $row, array $columns, string $json_file): array {
    $normalized = [];
    foreach ($row as $column => $value) {
      if (!isset($columns[$column])) {
        continue;
      }

      $data_type = $columns[$column]['data_type'];
      if (is_array($value)) {
        if ($table_name === 'dungeoncrawler_content_registry' && $column === 'schema_data') {
          $value = $this->normalizeRegistrySchemaData($value, $row);
        }
        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      }
      elseif (is_bool($value)) {
        $value = in_array($data_type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'], TRUE) ? (int) $value : ($value ? '1' : '0');
      }

      $normalized[$column] = $value;
    }

    $timestamp = time();
    if (isset($columns['created']) && !isset($normalized['created'])) {
      $normalized['created'] = $timestamp;
    }
    if (isset($columns['updated']) && !isset($normalized['updated'])) {
      $normalized['updated'] = $timestamp;
    }
    if (isset($columns['source_file']) && !isset($normalized['source_file'])) {
      $normalized['source_file'] = $this->relativePath($json_file);
    }

    return $normalized;
  }

  /**
   * Normalizes legacy creature registry example metadata before persistence.
   */
  protected function normalizeRegistrySchemaData(array $schema_data, array $row): array {
    $content_type = strtolower(trim((string) ($row['content_type'] ?? '')));
    $content_id = trim((string) ($row['content_id'] ?? ''));
    if ($content_id !== '' && trim((string) ($schema_data['content_id'] ?? '')) === '') {
      $schema_data['content_id'] = $content_id;
    }

    $id_field_map = [
      'creature' => 'creature_id',
      'item' => 'item_id',
      'hazard' => 'hazard_id',
      'location' => 'location_id',
      'faction' => 'faction_id',
      'encounter' => 'encounter_id',
    ];
    if (isset($id_field_map[$content_type]) && $content_id !== '') {
      $id_field = $id_field_map[$content_type];
      if (trim((string) ($schema_data[$id_field] ?? '')) === '') {
        $schema_data[$id_field] = $content_id;
      }
    }

    $type_field_map = [
      'creature' => 'creature_type',
      'item' => 'item_type',
      'hazard' => 'hazard_type',
      'location' => 'location_type',
      'faction' => 'faction_type',
      'encounter' => 'encounter_type',
    ];
    if (trim((string) ($schema_data['type'] ?? '')) === '') {
      $candidate_type_field = $type_field_map[$content_type] ?? '';
      $candidate_type = $candidate_type_field !== '' ? trim((string) ($schema_data[$candidate_type_field] ?? '')) : '';
      $schema_data['type'] = $candidate_type !== '' ? $candidate_type : $content_type;
    }

    if ($content_type !== 'creature') {
      return $schema_data;
    }

    $source_map = [
      'bestiary_1' => 'b1',
      'bestiary_2' => 'b2',
      'bestiary_3' => 'b3',
    ];

    $tags = $row['tags'] ?? [];

    if (empty($schema_data['bestiary_source']) || !is_string($schema_data['bestiary_source'])) {
      $source_book = $schema_data['source_book'] ?? NULL;
      if (is_string($source_book) && isset($source_map[$source_book])) {
        $schema_data['bestiary_source'] = $source_map[$source_book];
      }
      elseif (is_array($tags)) {
        foreach ($tags as $tag) {
          if (is_string($tag) && isset($source_map[$tag])) {
            $schema_data['bestiary_source'] = $source_map[$tag];
            break;
          }
        }
      }
    }

    if (empty($schema_data['creature_id']) && !empty($row['content_id']) && is_string($row['content_id'])) {
      $schema_data['creature_id'] = $row['content_id'];
    }

    if (empty($schema_data['name']) && !empty($row['name']) && is_string($row['name'])) {
      $schema_data['name'] = $row['name'];
    }

    if (!array_key_exists('level', $schema_data) && isset($row['level']) && is_numeric($row['level'])) {
      $schema_data['level'] = (int) $row['level'];
    }

    if (empty($schema_data['rarity']) && !empty($row['rarity']) && is_string($row['rarity'])) {
      $schema_data['rarity'] = $row['rarity'];
    }

    if (
      array_key_exists('traits', $schema_data)
      && is_array($schema_data['traits'])
      && $schema_data['traits'] === []
      && is_array($tags)
    ) {
      $traits = [];
      foreach ($tags as $tag) {
        if (!is_string($tag) || $tag === 'creature' || isset($source_map[$tag])) {
          continue;
        }
        $traits[] = $tag;
      }
      if ($traits !== []) {
        $schema_data['traits'] = array_values(array_unique($traits));
      }
    }

    return $schema_data;
  }

  /**
   * Checks whether a row exists for merge keys.
   */
  protected function rowExists(string $table_name, array $keys): bool {
    $query = $this->database->select($table_name, 't');
    $query->addExpression('1');
    foreach ($keys as $column => $value) {
      $query->condition($column, $value);
    }
    $query->range(0, 1);

    return (bool) $query->execute()->fetchField();
  }

  /**
   * Returns all JSON files in a directory recursively.
   */
  protected function scanJsonFiles(string $directory): array {
    $files = [];
    if (!is_dir($directory)) {
      return $files;
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
      if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
        $files[] = $file->getPathname();
      }
    }

    sort($files);
    return $files;
  }

  /**
   * Returns path relative to module root.
   */
  protected function relativePath(string $absolute_path): string {
    $module_path = $this->moduleExtensionList->getPath('dungeoncrawler_content');
    return ltrim(str_replace($module_path, '', $absolute_path), '/');
  }

  /**
   * Determines whether an array is list-like.
   */
  protected function isList(mixed $value): bool {
    if (!is_array($value)) {
      return FALSE;
    }
    if ($value === []) {
      return TRUE;
    }
    return array_keys($value) === range(0, count($value) - 1);
  }

}
