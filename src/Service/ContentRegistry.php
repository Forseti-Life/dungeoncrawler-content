<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for loading and managing game content from JSON schemas.
 *
 * This service handles importing, validating, and updating game content
 * (creatures, items, traps, hazards) from JSON files into the database.
 *
 * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
 *   Section: Service Layer Design > ContentRegistry Service
 */
class ContentRegistry {

  /**
   * Valid spell content subtypes accepted by canonical imports.
   */
  protected const SPELL_TYPES = ['spell', 'cantrip', 'focus', 'ritual'];

  /**
   * Valid canonical feat categories accepted by registry imports.
   */
  protected const FEAT_TYPES = ['ancestry', 'class', 'general', 'skill'];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Base path to content files.
   *
   * @var string
   */
  protected $contentPath;
  protected StateValidationService $stateValidationService;

  /**
   * Constructs a ContentRegistry object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    StateValidationService $state_validation_service
  ) {
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
    $this->stateValidationService = $state_validation_service;
    
    // Path: <module-root>/content/
    $this->contentPath = \Drupal::service('extension.list.module')
      ->getPath('dungeoncrawler_content') . '/content';
  }

  /**
   * Load all content from JSON files into database.
   *
   * Should be run during module installation/update.
   *
   * @param string|null $content_type
   *   Load specific type ('creature', 'item', 'trap', 'hazard') or all if NULL.
   * @param string|null $source_filter
   *   When set, only records whose normalized bestiary_source matches this
   *   value (e.g. 'b3') are upserted. Records with a different source are
   *   silently skipped. NULL means import all sources.
   *
   * @return int
   *   Number of items loaded.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 133: importContentFromJson method specification
   */
  public function importContentFromJson(?string $content_type = NULL, ?string $source_filter = NULL): int {
    $logger = $this->loggerFactory->get('dungeoncrawler_content');
    $count = 0;
    
    $types_to_load = $content_type ? [$content_type] : $this->getContentTypes();
    
    foreach ($types_to_load as $type) {
      $generated_records = $this->getGeneratedRegistryRecords($type);
      $type_dirs = array_values(array_filter(
        $this->getImportDirectories($type),
        static fn(string $dir): bool => is_dir($dir)
      ));

      if ($type_dirs === [] && $generated_records === []) {
        $logger->warning('Content directory not found: @dir', ['@dir' => implode(', ', $this->getImportDirectories($type))]);
        continue;
      }

      foreach ($generated_records as $record) {
        if ($this->importPreparedRecord($type, $record, $source_filter, '[generated]')) {
          $count++;
        }
      }

      foreach ($type_dirs as $type_dir) {
        $files = $this->scanForJsonFiles($type_dir);

        foreach ($files as $file) {
          try {
            $payload = $this->loadJsonFile($file);
            $records = $this->prepareRegistryRecords($type, $payload, $file);

            foreach ($records as $record) {
              if ($this->importPreparedRecord($type, $record, $source_filter, $file)) {
                $count++;
              }
            }
          }
          catch (\Exception $e) {
            $logger->error('Error loading @file: @message', [
              '@file' => $file,
              '@message' => $e->getMessage(),
            ]);
          }
        }
      }
    }
    
    $logger->notice('Imported @count content items', ['@count' => $count]);
    return $count;
  }

  /**
   * Imports a prepared registry record after source filtering and validation.
   *
   * @param string $content_type
   *   Registry content type.
   * @param array<string, mixed> $record
   *   Prepared registry record.
   * @param string|null $source_filter
   *   Optional creature-source filter.
   * @param string $source_label
   *   Human-readable source label for logging.
   *
   * @return bool
   *   TRUE when the record was imported; FALSE when skipped.
   */
  protected function importPreparedRecord(string $content_type, array $record, ?string $source_filter, string $source_label): bool {
    $logger = $this->loggerFactory->get('dungeoncrawler_content');

    if (empty($record['content_id']) || empty($record['name'])) {
      $logger->error('Invalid content in @source: missing id or name', ['@source' => $source_label]);
      return FALSE;
    }

    if ($source_filter !== NULL) {
      $record_source = $record['schema_data']['bestiary_source'] ?? NULL;
      if ($record_source !== $source_filter) {
        return FALSE;
      }
    }

    $validation = $this->validateContent($content_type, $record['schema_data']);
    if (!$validation['valid']) {
      $logger->error('Validation failed for @source: @errors', [
        '@source' => $source_label,
        '@errors' => implode(', ', $validation['errors']),
      ]);
      return FALSE;
    }

    $this->upsertRegistryRecord($content_type, $record);
    return TRUE;
  }

  /**
   * Returns the directories used for JSON imports of a given content type.
   *
   * Spell imports currently read batched intermediary payloads from
   * content/intermediary/ in addition to conventional content/spells/.
   *
   * @param string $content_type
   *   Content type being imported.
   *
   * @return array<int, string>
   *   Candidate directories.
   */
  protected function getImportDirectories(string $content_type): array {
    $directories = [
      $this->contentPath . '/' . $content_type . 's',
    ];

    if ($content_type === 'spell') {
      $directories[] = $this->contentPath . '/intermediary';
    }

    return array_values(array_unique($directories));
  }

  /**
   * Returns generated records for content types that still need synthetic rows.
   *
   * @param string $content_type
   *   Content type being imported.
   *
   * @return array<int, array<string, mixed>>
   *   Prepared registry rows.
   */
  protected function getGeneratedRegistryRecords(string $content_type): array {
    if ($content_type === 'obstacle_object') {
      $records = [];
      foreach ([
        dirname($this->contentPath) . '/config/examples/enhanced-obstacle-objects.json',
        dirname($this->contentPath) . '/config/examples/tavern-obstacle-objects.json',
        dirname($this->contentPath) . '/config/examples/legacy-migration-obstacle-objects.json',
      ] as $file) {
        if (!is_file($file)) {
          continue;
        }
        $payload = json_decode((string) file_get_contents($file), TRUE);
        if (!is_array($payload)) {
          throw new \RuntimeException('Invalid obstacle object catalog: ' . $file);
        }
        foreach ((array) ($payload['objects'] ?? []) as $object) {
          if (!is_array($object)) {
            continue;
          }
          $prepared = $this->prepareRegistryRecord($content_type, $object, $file);
          if ($prepared !== NULL) {
            $records[] = $prepared;
          }
        }
      }
      return $records;
    }
    return [];
  }

  /**
   * Expands a raw JSON payload into registry-ready records.
   *
   * @param string $content_type
   *   The requested content type.
   * @param array $payload
   *   Raw decoded JSON payload.
   * @param string $file
   *   Source file path.
   *
   * @return array<int, array<string, mixed>>
   *   Registry records ready for validation/upsert.
   */
  protected function prepareRegistryRecords(string $content_type, array $payload, string $file): array {
    if (isset($payload['records']) && is_array($payload['records'])) {
      $records = [];
      foreach ($payload['records'] as $record) {
        if (!is_array($record)) {
          continue;
        }
        $prepared = $this->prepareRegistryRecord($content_type, $record, $file);
        if ($prepared !== NULL) {
          $records[] = $prepared;
        }
      }
      return $records;
    }

    $prepared = $this->prepareRegistryRecord($content_type, $payload, $file);
    return $prepared !== NULL ? [$prepared] : [];
  }

  /**
   * Normalizes a JSON payload into a single registry row shape.
   *
   * @param string $content_type
   *   Requested content type.
   * @param array $content_data
   *   Raw record payload.
   * @param string $file
   *   Source JSON file path.
   *
   * @return array<string, mixed>|null
   *   Normalized record or NULL when the payload does not match the type.
   */
  protected function prepareRegistryRecord(string $content_type, array $content_data, string $file): ?array {
    $record_type = (string) ($content_data['content_type'] ?? $content_type);
    if ($record_type !== $content_type) {
      return NULL;
    }

    $schema_data = isset($content_data['schema_data']) && is_array($content_data['schema_data'])
      ? $content_data['schema_data']
      : $content_data;

    $schema_data = $this->sanitizeTextFields($schema_data);
    if ($record_type === 'obstacle_object') {
      $schema_data['content_id'] = $schema_data['content_id'] ?? $schema_data['object_id'] ?? NULL;
      $schema_data['name'] = $schema_data['name'] ?? $schema_data['label'] ?? NULL;
      $schema_data['type'] = $schema_data['type'] ?? $schema_data['category'] ?? NULL;
    }
    $schema_data = $this->normalizeContentData($record_type, $schema_data);

    $id_field = $record_type . '_id';
    $content_id = $content_data['content_id'] ?? $schema_data['content_id'] ?? $schema_data[$id_field] ?? $schema_data['id'] ?? NULL;
    if (!is_scalar($content_id) || trim((string) $content_id) === '') {
      return NULL;
    }

    if ($record_type === 'spell') {
      $content_id = $this->normalizeSpellContentId((string) $content_id);
      $schema_data['id'] = $content_id;
      $schema_data[$id_field] = $content_id;
      $schema_data['content_id'] = $content_id;

      $rank = isset($schema_data['rank']) ? (int) $schema_data['rank'] : (int) ($schema_data['level'] ?? $content_data['level'] ?? 0);
      $schema_data['rank'] = $rank;
      $schema_data['level'] = $rank;
      $schema_data['is_cantrip'] = !empty($schema_data['is_cantrip']) || $rank === 0;
      if (empty($schema_data['spell_type'])) {
        $schema_data['spell_type'] = $schema_data['is_cantrip'] ? 'cantrip' : 'spell';
      }
    }
    elseif ($record_type === 'feat') {
      $content_id = $this->normalizeFeatContentId((string) $content_id);
      $schema_data['id'] = $content_id;
      $schema_data[$id_field] = $content_id;
      $schema_data['content_id'] = $content_id;
    }
    else {
      $schema_data['content_id'] = (string) $content_id;
    }

    $name = $content_data['name'] ?? $schema_data['name'] ?? NULL;
    if (!is_scalar($name) || trim((string) $name) === '') {
      return NULL;
    }

    $level = $content_data['level'] ?? $schema_data['level'] ?? $schema_data['rank'] ?? NULL;
    $rarity = $content_data['rarity'] ?? $schema_data['rarity'] ?? NULL;
    $tags = $content_data['tags'] ?? $schema_data['tags'] ?? $schema_data['traits'] ?? [];
    if ($record_type === 'spell' && $tags === []) {
      $tags = $this->buildSpellTags($schema_data);
    }
    elseif ($record_type === 'feat' && $tags === []) {
      $tags = $this->buildFeatTags($schema_data);
    }

    $normalized_tags = $this->normalizeTagList($tags);
    if ($record_type === 'spell' && $normalized_tags === []) {
      $normalized_tags = ['none'];
    }

    return [
      'content_id' => (string) $content_id,
      'name' => trim((string) $name),
      'level' => $level !== NULL ? (int) $level : NULL,
      'rarity' => is_scalar($rarity) && trim((string) $rarity) !== '' ? strtolower((string) $rarity) : NULL,
      'tags' => $normalized_tags,
      'schema_data' => $schema_data,
      'source_file' => (string) ($content_data['source_file'] ?? str_replace($this->contentPath . '/', '', $file)),
      'version' => (string) ($content_data['version'] ?? $schema_data['parser_version'] ?? $content_data['schema_version'] ?? $schema_data['schema_version'] ?? '1.0'),
    ];
  }

  /**
   * Upserts a normalized registry record into storage.
   *
   * @param string $content_type
   *   Registry content type.
   * @param array<string, mixed> $record
   *   Normalized registry record.
   */
  protected function upsertRegistryRecord(string $content_type, array $record): void {
    $this->database->merge('dungeoncrawler_content_registry')
      ->keys([
        'content_type' => $content_type,
        'content_id' => $record['content_id'],
      ])
      ->fields([
        'name' => $record['name'],
        'level' => $record['level'],
        'rarity' => $record['rarity'],
        'tags' => $record['tags'] !== [] ? json_encode($record['tags']) : NULL,
        'schema_data' => json_encode($record['schema_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'source_file' => $record['source_file'],
        'version' => $record['version'],
        'updated' => time(),
      ])
      ->expression('created', 'COALESCE(created, :time)', [':time' => time()])
      ->execute();
  }
  
  /**
   * Recursively scan directory for JSON files.
   *
   * @param string $dir
   *   Directory to scan.
   *
   * @return array
   *   Array of file paths.
   */
  protected function scanForJsonFiles(string $dir): array {
    $files = [];
    
    if (!is_dir($dir)) {
      return $files;
    }
    
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'json') {
        $files[] = $file->getPathname();
      }
    }
    
    return $files;
  }
  
  /**
   * Load and parse JSON file.
   *
   * @param string $file
   *   File path.
   *
   * @return array
   *   Parsed JSON data.
   *
   * @throws \Exception
   *   If file cannot be read or parsed.
   */
  protected function loadJsonFile(string $file): array {
    if (!file_exists($file)) {
      throw new \Exception("File not found: {$file}");
    }
    
    $content = file_get_contents($file);
    if ($content === FALSE) {
      throw new \Exception("Cannot read file: {$file}");
    }
    
    $data = json_decode($content, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON in {$file}: " . json_last_error_msg());
    }
    
    return $data;
  }

  /**
   * Get content by ID and type.
   *
   * @param string $content_type
   *   Content type: 'creature', 'item', 'trap', 'hazard'.
   * @param string $content_id
   *   Unique identifier (e.g., 'goblin_warrior').
   *
   * @return array|null
   *   Full schema data or NULL if not found.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 144: getContent method specification
   */
  public function getContent(string $content_type, string $content_id): ?array {
    $result = $this->database->select('dungeoncrawler_content_registry', 'c')
      ->fields('c', ['schema_data'])
      ->condition('content_type', $content_type)
      ->condition('content_id', $content_id)
      ->execute()
      ->fetchField();
    
    if ($result === FALSE) {
      return NULL;
    }
    
    $data = json_decode($result, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Invalid JSON in database for @type/@id', [
          '@type' => $content_type,
          '@id' => $content_id,
        ]);
      return NULL;
    }
    
    return $data;
  }

  /**
   * Validate content against schema.
   *
   * @param string $content_type
   *   Content type to validate.
   * @param array $content_data
   *   Content data to validate.
   *
   * @return array
   *   Array with 'valid' boolean and optional 'errors' array.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 151: validateContent method specification
   *   Section: Content Validation Schema (lines 1080-1164)
   */
  public function validateContent(string $content_type, array $content_data): array {
    $errors = [];
    
    // Basic validation - check canonical required fields.
    if (empty($content_data['content_id'])) {
      $errors[] = 'Missing required field: content_id';
    }
    
    if (empty($content_data['name'])) {
      $errors[] = 'Missing required field: name';
    }
    
    $type_field = match ($content_type) {
      'creature' => 'creature_type',
      'item' => 'item_type',
      'spell' => 'spell_type',
      default => 'type',
    };
    if (empty($content_data[$type_field])) {
      $errors[] = 'Missing required field: ' . $type_field;
    }
    
    // Type-specific validation
    switch ($content_type) {
      case 'creature':
        $errors = array_merge($errors, $this->validateCreature($content_data));
        break;
        
      case 'item':
        $validation = $this->stateValidationService->validateItemDefinition($content_data);
        $errors = array_merge($errors, array_values(array_filter(array_map('strval', (array) ($validation['errors'] ?? [])))));
        break;
        
      case 'trap':
        $errors = array_merge($errors, $this->validateTrap($content_data));
        break;

      case 'hazard':
        $errors = array_merge($errors, $this->validateHazard($content_data));
        break;

      case 'obstacle_object':
        if (empty($content_data['object_id']) && empty($content_data['content_id'])) {
          $errors[] = 'Obstacle object requires object_id or content_id';
        }
        if (empty($content_data['label']) && empty($content_data['name'])) {
          $errors[] = 'Obstacle object requires label or name';
        }
        if (!isset($content_data['movement']) || !is_array($content_data['movement'])) {
          $errors[] = 'Obstacle object requires movement configuration';
        }
        break;

      case 'spell':
        $errors = array_merge($errors, $this->validateSpell($content_data));
        break;

      case 'feat':
        $errors = array_merge($errors, $this->validateFeat($content_data));
        break;
    }
    
    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }
  
  /**
   * Validate creature-specific fields.
   *
   * @param array $data
   *   Creature data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateCreature(array $data): array {
    $errors = [];
    
    // Level validation
    if (!isset($data['level']) || !is_numeric($data['level'])) {
      $errors[] = 'Creature must have a numeric level';
    } elseif ($data['level'] < -1 || $data['level'] > 25) {
      $errors[] = 'Creature level must be between -1 and 25';
    }
    
    // Support both old schema (abilities) and new schema (pf2e_stats.ability_scores)
    $has_old_abilities = !empty($data['abilities']) && is_array($data['abilities']);
    $has_new_abilities = !empty($data['pf2e_stats']['ability_scores']) && is_array($data['pf2e_stats']['ability_scores']);
    
    if (!$has_old_abilities && !$has_new_abilities) {
      $errors[] = 'Creature must have abilities or pf2e_stats.ability_scores';
    } elseif ($has_old_abilities) {
      // Validate old schema
      $required_abilities = ['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'];
      foreach ($required_abilities as $ability) {
        if (!isset($data['abilities'][$ability])) {
          $errors[] = "Missing ability: {$ability}";
        }
      }
    } elseif ($has_new_abilities) {
      // Validate new schema
      $required_abilities = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];
      foreach ($required_abilities as $ability) {
        if (!isset($data['pf2e_stats']['ability_scores'][$ability])) {
          $errors[] = "Missing ability score: {$ability}";
        }
      }
    }
    
    // Support both old schema (stats) and new schema (pf2e_stats)
    $has_old_stats = !empty($data['stats']) && is_array($data['stats']);
    $has_new_stats = !empty($data['pf2e_stats']) && is_array($data['pf2e_stats']);
    
    if (!$has_old_stats && !$has_new_stats) {
      $errors[] = 'Creature must have stats or pf2e_stats';
    } elseif ($has_old_stats) {
      // Validate old schema
      $required_stats = ['ac', 'hp', 'fortitude', 'reflex', 'will'];
      foreach ($required_stats as $stat) {
        if (!isset($data['stats'][$stat])) {
          $errors[] = "Missing stat: {$stat}";
        }
      }
    } elseif ($has_new_stats) {
      // Validate new schema
      if (!isset($data['pf2e_stats']['ac'])) {
        $errors[] = "Missing pf2e_stats.ac";
      }
      if (!isset($data['pf2e_stats']['hp'])) {
        $errors[] = "Missing pf2e_stats.hp";
      }
      if (empty($data['pf2e_stats']['saves'])) {
        $errors[] = "Missing pf2e_stats.saves";
      }
    }
    
    return $errors;
  }
  
  /**
   * Validate item-specific fields.
   *
   * @param array $data
   *   Item data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateItem(array $data): array {
    $errors = [];
    
    if (!isset($data['item_category'])) {
      $errors[] = 'Item must have item_category';
    }
    
    if (!isset($data['level']) || !is_numeric($data['level'])) {
      $errors[] = 'Item must have a numeric level';
    } elseif ($data['level'] < 0 || $data['level'] > 25) {
      $errors[] = 'Item level must be between 0 and 25';
    }
    
    return $errors;
  }
  
  /**
   * Validate trap-specific fields.
   *
   * @param array $data
   *   Trap data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateTrap(array $data): array {
    $errors = [];
    
    if (!isset($data['stealth_dc']) || !is_numeric($data['stealth_dc'])) {
      $errors[] = 'Trap must have numeric stealth_dc';
    }
    
    // Accept flat disable_dc OR nested disable.thievery_dc (preferred schema).
    $disable_dc = $data['disable_dc'] ?? ($data['disable']['thievery_dc'] ?? NULL);
    if (!isset($disable_dc) || !is_numeric($disable_dc)) {
      $errors[] = 'Trap must have numeric disable_dc';
    }
    
    return $errors;
  }

  /**
   * Validate hazard-specific fields.
   *
   * @param array $data
   *   Hazard data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateHazard(array $data): array {
    $errors = [];

    if (!isset($data['stealth_dc']) || !is_numeric($data['stealth_dc'])) {
      $errors[] = 'Hazard must have numeric stealth_dc';
    }

    // Disable DC may be nested under disable.dc or flat disable_dc.
    $disable_dc = $data['disable']['dc'] ?? $data['disable_dc'] ?? NULL;
    if (!isset($disable_dc) || !is_numeric($disable_dc)) {
      $errors[] = 'Hazard must have numeric disable DC (disable.dc or disable_dc)';
    }

    $valid_complexity = ['simple', 'complex'];
    if (isset($data['complexity']) && !in_array($data['complexity'], $valid_complexity, TRUE)) {
      $errors[] = 'Hazard complexity must be "simple" or "complex"';
    }

    if (!empty($data['is_magical'])) {
      if (!isset($data['spell_level']) || !is_numeric($data['spell_level'])) {
        $errors[] = 'Magical hazard must have numeric spell_level';
      }
      if (!isset($data['counteract_dc']) || !is_numeric($data['counteract_dc'])) {
        $errors[] = 'Magical hazard must have numeric counteract_dc';
      }
    }

    return $errors;
  }

  /**
   * Validate spell-specific fields.
   *
   * @param array $data
   *   Spell data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateSpell(array $data): array {
    $errors = [];

    $spell_type = strtolower((string) ($data['spell_type'] ?? $data['type'] ?? ''));
    if ($spell_type === '') {
      $errors[] = 'Spell must have spell_type';
    }
    elseif (!in_array($spell_type, self::SPELL_TYPES, TRUE)) {
      $errors[] = 'Spell type must be one of: ' . implode(', ', self::SPELL_TYPES);
    }

    $rank = $data['rank'] ?? $data['level'] ?? NULL;
    if (!isset($rank) || !is_numeric($rank)) {
      $errors[] = 'Spell must have a numeric rank';
    }
    elseif ((int) $rank < 0 || (int) $rank > 10) {
      $errors[] = 'Spell rank must be between 0 and 10';
    }

    if (isset($data['traditions']) && !is_array($data['traditions'])) {
      $errors[] = 'Spell traditions must be an array';
    }
    elseif (isset($data['traditions'])) {
      foreach ($data['traditions'] as $tradition) {
        $tradition = strtolower((string) $tradition);
        if ($tradition === '' || $tradition === 'none') {
          continue;
        }
        if (!in_array($tradition, SpellCatalogService::TRADITIONS, TRUE)) {
          $errors[] = "Invalid spell tradition: {$tradition}";
        }
      }
    }

    $school = strtolower((string) ($data['school'] ?? ''));
    if ($school === 'none') {
      $school = '';
    }
    if ($school !== '' && !in_array($school, SpellCatalogService::SPELL_SCHOOLS, TRUE)) {
      $errors[] = "Invalid spell school: {$school}";
    }

    if (isset($data['components']) && is_array($data['components'])) {
      foreach ($data['components'] as $component) {
        $component = strtolower((string) $component);
        if ($component === '' || $component === 'none') {
          continue;
        }
        if (!in_array($component, SpellCatalogService::SPELL_COMPONENTS, TRUE)) {
          $errors[] = "Invalid spell component: {$component}";
        }
      }
    }

    $save_type = SpellCatalogService::normalizeSaveType((string) ($data['save_type'] ?? ''));
    if ($save_type !== '' && !SpellCatalogService::isSupportedSaveType($save_type)) {
      $errors[] = "Invalid spell save type: {$save_type}";
    }

    $rarity = strtolower((string) ($data['rarity'] ?? 'common'));
    if ($rarity !== '' && !in_array($rarity, SpellCatalogService::RARITY_LEVELS, TRUE)) {
      $errors[] = "Invalid spell rarity: {$rarity}";
    }

    return $errors;
  }

  /**
   * Validate feat-specific fields.
   *
   * @param array $data
   *   Feat data.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateFeat(array $data): array {
    $errors = [];

    $feat_type = strtolower((string) ($data['type'] ?? $data['feat_type'] ?? ''));
    if ($feat_type === '') {
      $errors[] = 'Feat must have type';
    }
    elseif (!in_array($feat_type, self::FEAT_TYPES, TRUE)) {
      $errors[] = 'Feat type must be one of: ' . implode(', ', self::FEAT_TYPES);
    }

    $level = $data['level'] ?? NULL;
    if (!isset($level) || !is_numeric($level)) {
      $errors[] = 'Feat must have a numeric level';
    }
    elseif ((int) $level < 1 || (int) $level > 20) {
      $errors[] = 'Feat level must be between 1 and 20';
    }

    if (isset($data['traits']) && !is_array($data['traits'])) {
      $errors[] = 'Feat traits must be an array';
    }

    if (!empty($data['source_book']) && !is_scalar($data['source_book'])) {
      $errors[] = 'Feat source_book must be a string';
    }

    return $errors;
  }

  /**
   * Update content in registry.
   *
   * @param string $content_type
   *   Content type.
   * @param string $content_id
   *   Content identifier.
   * @param array $content_data
   *   Updated content data.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @see docs/dungeoncrawler/issues/issue-3-game-content-system-design.md
   *   Line 161: updateContent method specification
   */
  public function updateContent(string $content_type, string $content_id, array $content_data): bool {
    $content_data = $this->normalizeContentData($content_type, $content_data);

    // Validate content first
    $validation = $this->validateContent($content_type, $content_data);
    if (!$validation['valid']) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Cannot update invalid content @type/@id: @errors', [
          '@type' => $content_type,
          '@id' => $content_id,
          '@errors' => implode(', ', $validation['errors']),
        ]);
      return FALSE;
    }
    
    try {
      $num_updated = $this->database->update('dungeoncrawler_content_registry')
        ->fields([
          'name' => $content_data['name'],
          'level' => $content_data['level'] ?? NULL,
          'rarity' => $content_data['rarity'] ?? NULL,
          'tags' => isset($content_data['tags']) ? json_encode($content_data['tags']) : NULL,
          'schema_data' => json_encode($content_data),
          'version' => $content_data['version'] ?? '1.0',
          'updated' => time(),
        ])
        ->condition('content_type', $content_type)
        ->condition('content_id', $content_id)
        ->execute();
      
      return $num_updated > 0;
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Error updating content @type/@id: @message', [
          '@type' => $content_type,
          '@id' => $content_id,
          '@message' => $e->getMessage(),
        ]);
      return FALSE;
    }
  }

  /**
   * Delete content from registry.
   *
   * @param string $content_type
   *   Content type.
   * @param string $content_id
   *   Content identifier.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function deleteContent(string $content_type, string $content_id): bool {
    try {
      $num_deleted = $this->database->delete('dungeoncrawler_content_registry')
        ->condition('content_type', $content_type)
        ->condition('content_id', $content_id)
        ->execute();
      
      return $num_deleted > 0;
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('dungeoncrawler_content')
        ->error('Error deleting content @type/@id: @message', [
          '@type' => $content_type,
          '@id' => $content_id,
          '@message' => $e->getMessage(),
        ]);
      return FALSE;
    }
  }

  /**
   * Sanitize text fields in content data to prevent unsafe markup injection.
   *
   * Strips HTML tags and normalizes whitespace from string scalar fields
   * that contain creature flavor text, names, descriptions, and ability text.
   * Nested arrays are recursively sanitized.
   *
   * @param array $data
   *   Content data array.
   *
   * @return array
   *   Sanitized content data array.
   */
  protected function sanitizeTextFields(array $data): array {
    // Fields whose values must be preserved as-is (IDs, versions, numbers).
    static $skip_fields = [
      'content_id', 'creature_id', 'item_id', 'trap_id', 'hazard_id',
      'level', 'rarity', 'size', 'hex_footprint', 'schema_version', 'version',
    ];

    foreach ($data as $key => $value) {
      if (in_array($key, $skip_fields, TRUE)) {
        continue;
      }
      if (is_string($value)) {
        $data[$key] = trim(strip_tags($value));
      }
      elseif (is_array($value)) {
        $data[$key] = $this->sanitizeTextFields($value);
      }
    }
    return $data;
  }

  /**
   * Normalizes content data before validation and persistence.
   *
   * Ensures legacy creature imports that only carry source_book/tag metadata
   * still land with a canonical bestiary_source value in stored schema_data.
   */
  public function normalizeContentData(string $content_type, array $content_data): array {
    if ($content_type === 'spell') {
      $content_data = $this->normalizeContentIdAliases(
        $content_data,
        ['id', 'spell_id', 'content_id'],
        [$this, 'normalizeSpellContentId']
      );
      if (!empty($content_data['school']) && is_string($content_data['school'])) {
        $content_data['school'] = strtolower($content_data['school']);
      }
      if (!empty($content_data['rarity']) && is_string($content_data['rarity'])) {
        $content_data['rarity'] = strtolower($content_data['rarity']);
      }
      if (!empty($content_data['spell_type']) && is_string($content_data['spell_type'])) {
        $content_data['spell_type'] = strtolower($content_data['spell_type']);
      }
      if (isset($content_data['traditions']) && is_array($content_data['traditions'])) {
        $content_data['traditions'] = array_values(array_map(
          static fn($tradition): string => strtolower(trim((string) $tradition)),
          $content_data['traditions']
        ));
      }
      if (isset($content_data['components']) && is_array($content_data['components'])) {
        $content_data['components'] = array_values(array_map(
          static fn($component): string => strtolower(trim((string) $component)),
          $content_data['components']
        ));
      }
      if (!empty($content_data['save_type']) && is_string($content_data['save_type'])) {
        $content_data['save_type'] = SpellCatalogService::normalizeSaveType($content_data['save_type']);
      }
      return $content_data;
    }

    if ($content_type === 'feat') {
      $content_data = $this->normalizeContentIdAliases(
        $content_data,
        ['id', 'feat_id', 'content_id'],
        [$this, 'normalizeFeatContentId']
      );
      if (!empty($content_data['type']) && is_string($content_data['type'])) {
        $content_data['type'] = strtolower(trim($content_data['type']));
      }
      if (!empty($content_data['source_book']) && is_string($content_data['source_book'])) {
        $content_data['source_book'] = strtolower(trim($content_data['source_book']));
      }
      if (!empty($content_data['class']) && is_string($content_data['class'])) {
        $content_data['class'] = $this->normalizeFeatContentId($content_data['class']);
      }
      if (!empty($content_data['ancestry']) && is_string($content_data['ancestry'])) {
        $content_data['ancestry'] = $this->normalizeFeatContentId($content_data['ancestry']);
      }
      if (!empty($content_data['skill']) && is_string($content_data['skill'])) {
        $content_data['skill'] = strtolower(trim($content_data['skill']));
      }
      if (isset($content_data['traits']) && is_array($content_data['traits'])) {
        $content_data['traits'] = array_values(array_filter(array_map(
          static fn($trait): string => trim((string) $trait),
          $content_data['traits']
        ), static fn(string $trait): bool => $trait !== ''));
      }
      return $content_data;
    }

    if ($content_type !== 'creature') {
      return $content_data;
    }

    if (!empty($content_data['bestiary_source']) && is_string($content_data['bestiary_source'])) {
      return $content_data;
    }

    $source_map = [
      'bestiary_1' => 'b1',
      'bestiary_2' => 'b2',
      'bestiary_3' => 'b3',
    ];

    $source_book = $content_data['source_book'] ?? NULL;
    if (is_string($source_book) && isset($source_map[$source_book])) {
      $content_data['bestiary_source'] = $source_map[$source_book];
      return $content_data;
    }

    $tags = $content_data['tags'] ?? $content_data['traits'] ?? [];
    if (is_array($tags)) {
      foreach ($tags as $tag) {
        if (is_string($tag) && isset($source_map[$tag])) {
          $content_data['bestiary_source'] = $source_map[$tag];
          return $content_data;
        }
      }
    }

    return $content_data;
  }

  /**
   * Normalize canonical content ID aliases using one ID normalizer.
   *
   * @param array<string, mixed> $content_data
   *   Content payload to normalize.
   * @param array<int, string> $fields
   *   ID alias field names to normalize when present.
   * @param callable $normalizer
   *   Callback that maps one ID string to normalized form.
   *
   * @return array<string, mixed>
   *   Payload with normalized ID aliases.
   */
  protected function normalizeContentIdAliases(array $content_data, array $fields, callable $normalizer): array {
    foreach ($fields as $field) {
      if (!empty($content_data[$field]) && is_string($content_data[$field])) {
        $content_data[$field] = $normalizer($content_data[$field]);
      }
    }
    return $content_data;
  }

  /**
   * Get all content types.
   *
   * @return array
   *   Array of content type names.
   */
  public function getContentTypes(): array {
    return ['creature', 'item', 'trap', 'hazard', 'obstacle_object', 'spell', 'feat'];
  }

  /**
   * Normalize a spell content identifier to the canonical hyphenated form.
   */
  protected function normalizeSpellContentId(string $spell_id): string {
    return strtolower(str_replace('_', '-', trim($spell_id)));
  }

  /**
   * Normalize a feat content identifier to the canonical hyphenated form.
   */
  protected function normalizeFeatContentId(string $feat_id): string {
    $feat_id = strtolower(trim($feat_id));
    $feat_id = str_replace(['_', '\''], ['-', ''], $feat_id);
    $feat_id = preg_replace('/[^a-z0-9]+/', '-', $feat_id) ?? '';
    return trim($feat_id, '-');
  }

  /**
   * Normalize tag values into a stable lowercase list.
   *
   * @param mixed $tags
   *   Raw tags/traits payload.
   *
   * @return array<int, string>
   *   Normalized tag list.
   */
  protected function normalizeTagList(mixed $tags): array {
    if (!is_array($tags)) {
      return [];
    }

    $normalized = [];
    foreach ($tags as $tag) {
      if (!is_scalar($tag)) {
        continue;
      }
      $value = strtolower(trim((string) $tag));
      if ($value === '') {
        continue;
      }
      $normalized[] = $value;
    }

    return array_values(array_unique($normalized));
  }

  /**
   * Derive searchable top-level tags from spell schema metadata.
   *
   * @param array<string, mixed> $schema_data
   *   Spell schema data.
   *
   * @return array<int, string>
   *   Derived tags.
   */
  protected function buildSpellTags(array $schema_data): array {
    $tags = [];

    if (!empty($schema_data['traditions']) && is_array($schema_data['traditions'])) {
      $tags = array_merge($tags, $schema_data['traditions']);
    }
    if (!empty($schema_data['traits']) && is_array($schema_data['traits'])) {
      $tags = array_merge($tags, $schema_data['traits']);
    }
    if (!empty($schema_data['school'])) {
      $tags[] = $schema_data['school'];
    }
    if (!empty($schema_data['rarity'])) {
      $tags[] = $schema_data['rarity'];
    }
    if (!empty($schema_data['spell_type'])) {
      $tags[] = $schema_data['spell_type'];
    }

    return $this->normalizeTagList($tags);
  }

  /**
   * Derive searchable top-level tags from feat schema metadata.
   *
   * @param array<string, mixed> $schema_data
   *   Feat schema data.
   *
   * @return array<int, string>
   *   Derived tags.
   */
  protected function buildFeatTags(array $schema_data): array {
    $tags = ['feat'];

    if (!empty($schema_data['type'])) {
      $tags[] = (string) $schema_data['type'];
    }
    if (!empty($schema_data['traits']) && is_array($schema_data['traits'])) {
      $tags = array_merge($tags, $schema_data['traits']);
    }
    if (!empty($schema_data['source_book'])) {
      $tags[] = 'source:' . strtolower((string) $schema_data['source_book']);
    }
    if (!empty($schema_data['class'])) {
      $tags[] = 'class:' . strtolower((string) $schema_data['class']);
    }
    if (!empty($schema_data['ancestry'])) {
      $tags[] = 'ancestry:' . $this->normalizeFeatContentId((string) $schema_data['ancestry']);
    }
    if (!empty($schema_data['skill'])) {
      $tags[] = 'skill:' . strtolower((string) $schema_data['skill']);
    }
    if (!empty($schema_data['rarity'])) {
      $tags[] = strtolower((string) $schema_data['rarity']);
    }

    return $this->normalizeTagList($tags);
  }

}
