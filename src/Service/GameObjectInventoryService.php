<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Provides Dungeon Crawler object table inventory and row loading services.
 */
class GameObjectInventoryService {

  /**
   * Maximum number of rows returned in table row browse mode.
   */
  private const MAX_ROWS = 100;

  /**
   * Dungeon Crawler table descriptions.
   */
  private const TABLE_OBJECT_MAP = [
    'dc_campaigns' => 'Campaign headers, lifecycle state, and campaign-level metadata.',
    'dc_campaign_characters' => 'Unified character/entity records (library and campaign runtime state).',
    'dc_campaign_rooms' => 'Generated room objects for active campaigns.',
    'dc_campaign_room_states' => 'Per-room state flags and progression data.',
    'dc_campaign_dungeons' => 'Dungeon-layer records tied to campaigns.',
    'dc_campaign_log' => 'Campaign event log entries and timeline history.',
    'dc_campaign_item_instances' => 'Item instance objects spawned in campaigns.',
    'dc_campaign_encounter_instances' => 'Encounter instance objects generated during play.',
    'dc_campaign_encounter_templates' => 'Encounter template objects available to campaigns.',
    'dc_campaign_loot_tables' => 'Loot table objects used by campaign generation.',
    'dc_campaign_content_registry' => 'Campaign content object registry and lookup records.',
    'dc_generated_images' => 'Canonical generated image asset records (provider, prompts, URI, metadata).',
    'dc_generated_image_links' => 'Polymorphic links from generated images to campaign/template objects and slots.',
    'dungeoncrawler_content_registry' => 'Global content registry objects for generator lookups.',
    'dungeoncrawler_content_loot_tables' => 'Global loot table objects used by the generator.',
    'dungeoncrawler_content_encounter_templates' => 'Global encounter template objects.',
    'dungeoncrawler_content_campaigns' => 'Global campaign template records and baseline metadata.',
    'dungeoncrawler_content_characters' => 'Global character/actor template mappings.',
    'dungeoncrawler_content_rooms' => 'Global room template objects.',
    'dungeoncrawler_content_dungeons' => 'Global dungeon template objects.',
    'dungeoncrawler_content_encounter_instances' => 'Global encounter instance templates.',
    'dungeoncrawler_content_room_states' => 'Global room-state template baselines.',
    'dungeoncrawler_content_item_instances' => 'Global item instance templates.',
    'dungeoncrawler_content_log' => 'Global narrative/event log templates.',
    'dungeoncrawler_content_image_prompt_cache' => 'Cached image prompt requests and responses for Vertex lookups.',
  ];

  /**
   * Database connection service.
   */
  protected Connection $database;

  /**
   * Constructs the inventory service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Gets Dungeon Crawler table inventory metadata.
   */
  public function getDungeonCrawlerTableInventory(): array {
    $tables = $this->discoverDungeonCrawlerTables();

    $inventory = [];
    foreach ($tables as $table_name) {
      $columns = $this->getTableColumns($table_name);
      $primary_keys = [];
      foreach ($columns as $column_name => $column) {
        if ($column['column_key'] === 'PRI') {
          $primary_keys[] = $column_name;
        }
      }

      $inventory[$table_name] = [
        'columns' => $columns,
        'primary_keys' => $primary_keys,
        'row_count' => $this->getTableRowCount($table_name),
        'object_description' => $this->describeTableObjects($table_name),
        'object_type' => $this->classifyObjectType($table_name),
      ];
    }

    return $inventory;
  }

  /**
   * Loads rows from a selected table.
   */
  public function loadTableRows(string $table_name, array $primary_keys, string $search = ''): array {
    $columns = $this->getTableColumns($table_name);
    $query = $this->database->select($table_name, 't');

    if (empty($columns)) {
      // Fallback if schema info is unavailable.
      $query->fields('t');
    }
    else {
      // Truncate large text/blob columns to avoid OOM when fetching many rows.
      // Full values are available in the single-row editor (loadTableRowByPrimaryKey).
      $large_types = ['text', 'mediumtext', 'longtext', 'blob', 'mediumblob', 'longblob'];
      foreach ($columns as $column_name => $column_meta) {
        if (in_array(strtolower($column_meta['data_type']), $large_types, TRUE)) {
          $safe_col = $this->database->escapeField($column_name);
          $query->addExpression('SUBSTRING(t.' . $safe_col . ', 1, 255)', $column_name);
        }
        else {
          $query->addField('t', $column_name);
        }
      }
    }

    if (count($primary_keys) === 1) {
      $query->orderBy($primary_keys[0], 'DESC');
    }

    $search = trim($search);
    if ($search !== '' && !empty($columns)) {
      $where_parts = [];
      $arguments = [];
      foreach (array_keys($columns) as $index => $column_name) {
        $placeholder = ':row_search_' . $index;
        $where_parts[] = 'CAST(t.' . $this->database->escapeField($column_name) . ' AS CHAR) LIKE ' . $placeholder;
        $arguments[$placeholder] = '%' . $search . '%';
      }
      $query->where('(' . implode(' OR ', $where_parts) . ')', $arguments);
    }

    $query->range(0, self::MAX_ROWS);
    $stmt = $query->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    unset($stmt);
    return $rows;
  }

  /**
   * Gets maximum row count used for browse tables.
   */
  public function getMaxRows(): int {
    return self::MAX_ROWS;
  }

  /**
   * Loads a single row using primary key values.
   */
  public function loadTableRowByPrimaryKey(string $table_name, array $primary_key_values): array {
    $query = $this->database->select($table_name, 't');
    $query->fields('t');
    foreach ($primary_key_values as $key => $value) {
      $query->condition($key, $value);
    }
    $query->range(0, 1);

    $stmt = $query->execute();
    $row = $stmt->fetchAssoc();
    unset($stmt);
    return is_array($row) ? $row : [];
  }

  /**
   * Discovers Dungeon Crawler-owned table names from the active schema.
   */
  protected function discoverDungeonCrawlerTables(): array {
    $stmt = $this->database->query(
      'SELECT TABLE_NAME
       FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND (TABLE_NAME LIKE :dc_pattern OR TABLE_NAME LIKE :content_pattern)
       ORDER BY TABLE_NAME',
      [
        ':dc_pattern' => 'dc\\_%',
        ':content_pattern' => 'dungeoncrawler_content\\_%',
      ],
    );

    $table_names_raw = $stmt->fetchCol();
    unset($stmt);

    $tables = [];
    foreach ($table_names_raw as $table_name) {
      if (is_string($table_name) && $table_name !== '') {
        $tables[] = $table_name;
      }
    }

    return $tables;
  }

  /**
   * Loads table column metadata from information_schema.
   */
  protected function getTableColumns(string $table_name): array {
    $stmt = $this->database->query(
      'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
       ORDER BY ORDINAL_POSITION',
      [':table' => $table_name],
    );

    $columns_raw = $stmt->fetchAllAssoc('COLUMN_NAME');
    unset($stmt);

    $columns = [];
    foreach ($columns_raw as $column_name => $row) {
      $columns[$column_name] = [
        'data_type' => (string) $row->DATA_TYPE,
        'is_nullable' => (string) $row->IS_NULLABLE,
        'column_key' => (string) $row->COLUMN_KEY,
      ];
    }

    return $columns;
  }

  /**
   * Gets row count for a table.
   */
  protected function getTableRowCount(string $table_name): int {
    // Explicitly store and unset the statement to ensure mysqlnd releases
    // the cursor via the PDOStatement destructor (mysql_free_result).
    // Without explicit unset(), buffered queries on tables with LONGTEXT
    // columns can trigger SQLSTATE 2014 during session_write_close().
    $stmt = $this->database->select($table_name, 't')
      ->countQuery()
      ->execute();
    $rows = $stmt->fetchAll();
    unset($stmt);
    return isset($rows[0]->expression) ? (int) $rows[0]->expression : 0;
  }

  /**
   * Gets object description for a table.
   */
  protected function describeTableObjects(string $table_name): string {
    if (isset(self::TABLE_OBJECT_MAP[$table_name])) {
      return self::TABLE_OBJECT_MAP[$table_name];
    }

    if (str_starts_with($table_name, 'dc_campaign_')) {
      return 'Campaign runtime objects and generated world state records.';
    }

    if (str_starts_with($table_name, 'dc_')) {
      return 'Dungeon Crawler domain objects and game data records.';
    }

    return 'Custom Dungeon Crawler data objects.';
  }

  /**
   * Classifies table data as template, active campaign, or fact.
   */
  protected function classifyObjectType(string $table_name): string {
    if (str_starts_with($table_name, 'dungeoncrawler_content_')) {
      return 'template';
    }

    if (str_starts_with($table_name, 'dc_campaign_') || $table_name === 'dc_campaigns') {
      return 'campaign';
    }

    return 'fact';
  }

}
