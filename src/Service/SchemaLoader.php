<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Service for loading and managing character creation schemas.
 */
class SchemaLoader {

  /**
   * Base path to schema files.
   */
  private string $schemaPath;

  public function __construct() {
    $this->schemaPath = \Drupal::service('extension.list.module')
      ->getPath('dungeoncrawler_content') . '/config/schemas';
  }

  /**
   * Load schema for a specific step.
   *
   * @param int $step
   *   The step number (1-8).
   *
   * @return array|null
   *   Decoded schema data or NULL if not found.
   */
  public function loadStepSchema(int $step): ?array {
    $file = "{$this->schemaPath}/character_options_step{$step}.json";
    
    if (!file_exists($file)) {
      \Drupal::logger('dungeoncrawler_content')->error('Schema file not found: @file', ['@file' => $file]);
      return NULL;
    }

    $content = file_get_contents($file);
    $schema = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      \Drupal::logger('dungeoncrawler_content')->error('Invalid JSON in schema: @error', ['@error' => json_last_error_msg()]);
      return NULL;
    }

    return $schema;
  }

  /**
   * Load the master character schema.
   *
   * @return array|null
   *   Decoded schema data or NULL if not found.
   */
  public function loadCharacterSchema(): ?array {
    $file = "{$this->schemaPath}/character.schema.json";
    
    if (!file_exists($file)) {
      return NULL;
    }

    $content = file_get_contents($file);
    return json_decode($content, TRUE);
  }

  /**
   * Load the campaign schema.
   *
   * @return array|null
   *   Decoded schema data or NULL if not found.
   */
  public function loadCampaignSchema(): ?array {
    $file = "{$this->schemaPath}/campaign.schema.json";

    if (!file_exists($file)) {
      return NULL;
    }

    $content = file_get_contents($file);
    $schema = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      \Drupal::logger('dungeoncrawler_content')->error('Invalid JSON in campaign schema: @error', ['@error' => json_last_error_msg()]);
      return NULL;
    }

    return $schema;
  }

  /**
   * Validate campaign payload data against campaign schema rules.
   *
   * @param array $data
   *   Campaign payload data.
   *
   * @return array
   *   Validation result with keys: valid(bool), errors(array).
   */
  public function validateCampaignData(array $data): array {
    $schema = $this->loadCampaignSchema();
    if (!$schema) {
      return ['valid' => FALSE, 'errors' => ['Campaign schema not found']];
    }

    $errors = [];
    $required = $schema['required'] ?? [];
    $properties = $schema['properties'] ?? [];

    foreach ($required as $field) {
      if (!array_key_exists($field, $data)) {
        $errors[] = "Missing required field: {$field}";
      }
    }

    foreach ($properties as $field => $definition) {
      if (!array_key_exists($field, $data)) {
        continue;
      }

      $value = $data[$field];
      $allowed_types = $definition['type'] ?? NULL;
      if ($allowed_types !== NULL && !$this->isValidType($value, $allowed_types)) {
        $expected = is_array($allowed_types) ? implode('|', $allowed_types) : $allowed_types;
        $errors[] = "Invalid type for {$field}. Expected {$expected}.";
      }
    }

    if (isset($data['progress']) && is_array($data['progress'])) {
      foreach ($data['progress'] as $index => $entry) {
        if (!is_array($entry)) {
          $errors[] = "Progress entry #{$index} must be an object.";
          continue;
        }
        if (!array_key_exists('type', $entry) || !is_string($entry['type']) || $entry['type'] === '') {
          $errors[] = "Progress entry #{$index} requires string field: type.";
        }
        if (!array_key_exists('timestamp', $entry) || !is_int($entry['timestamp']) || $entry['timestamp'] < 0) {
          $errors[] = "Progress entry #{$index} requires non-negative integer field: timestamp.";
        }
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Check if a value matches one or more allowed schema types.
   *
   * @param mixed $value
   *   Value to check.
   * @param string|array $allowed_types
   *   Allowed JSON Schema type(s).
   *
   * @return bool
   *   TRUE if type is valid.
   */
  private function isValidType($value, $allowed_types): bool {
    $types = is_array($allowed_types) ? $allowed_types : [$allowed_types];

    foreach ($types as $type) {
      switch ($type) {
        case 'string':
          if (is_string($value)) {
            return TRUE;
          }
          break;

        case 'integer':
          if (is_int($value)) {
            return TRUE;
          }
          break;

        case 'number':
          if (is_int($value) || is_float($value)) {
            return TRUE;
          }
          break;

        case 'boolean':
          if (is_bool($value)) {
            return TRUE;
          }
          break;

        case 'array':
          if (is_array($value) && array_is_list($value)) {
            return TRUE;
          }
          break;

        case 'object':
          if (is_array($value) && !array_is_list($value)) {
            return TRUE;
          }
          break;

        case 'null':
          if ($value === NULL) {
            return TRUE;
          }
          break;
      }
    }

    return FALSE;
  }

  /**
   * Determine if a submitted value is empty for validation.
   *
   * @param mixed $value
   *   Value to check.
   *
   * @return bool
   *   TRUE when the value should be treated as empty.
   */
  private function isEmptyValue($value): bool {
    if ($value === NULL) {
      return TRUE;
    }

    if (is_string($value)) {
      return trim($value) === '';
    }

    if (is_array($value)) {
      return $value === [];
    }

    return FALSE;
  }

  /**
   * Read a constraint value from a schema node.
   *
   * @param array|null $constraint
   *   Schema node containing const/default values.
   *
   * @return mixed|null
   *   Constraint value, or NULL when absent.
   */
  private function getSchemaConstraintValue(?array $constraint) {
    if (!is_array($constraint)) {
      return NULL;
    }

    return $constraint['const'] ?? $constraint['default'] ?? NULL;
  }

  /**
   * Get field configuration for a specific step.
   *
   * @param int $step
   *   The step number.
   *
   * @return array
   *   Field definitions from schema.
   */
  public function getStepFields(int $step): array {
    $schema = $this->loadStepSchema($step);
    return $schema['properties']['fields']['properties'] ?? [];
  }

  /**
   * Get navigation rules for a step.
   *
   * @param int $step
   *   The step number.
   *
   * @return array
   *   Navigation configuration.
   */
  public function getStepNavigation(int $step): array {
    $schema = $this->loadStepSchema($step);
    return $schema['properties']['navigation']['properties'] ?? [];
  }

  /**
   * Get tips for a step.
   *
   * @param int $step
   *   The step number.
   *
   * @return array
   *   Array of tip strings.
   */
  public function getStepTips(int $step): array {
    $schema = $this->loadStepSchema($step);
    return $schema['properties']['tips']['default'] ?? [];
  }

  /**
   * Validate step data against schema.
   *
   * @param int $step
   *   The step number.
   * @param array $data
   *   The data to validate.
   *
   * @return array
   *   Array with 'valid' boolean and optional 'errors' array.
   */
  public function validateStepData(int $step, array $data): array {
    $schema = $this->loadStepSchema($step);
    if (!$schema) {
      return ['valid' => FALSE, 'errors' => ['Schema not found']];
    }

    $errors = [];
    $fields = $schema['properties']['fields']['properties'] ?? [];

    foreach ($fields as $field_name => $field_config) {
      $properties = $field_config['properties'] ?? [];
      $required = $properties['required']['const'] ?? FALSE;
      $validation = $properties['validation']['properties'] ?? [];
      $error_messages = $validation['error_messages']['properties'] ?? [];
      $value = $data[$field_name] ?? NULL;

      if ($required && $this->isEmptyValue($value)) {
        $error_msg = $this->getSchemaConstraintValue($error_messages['required'] ?? NULL)
          ?? "Field {$field_name} is required.";
        $errors[] = $error_msg;
        continue;
      }

      if ($this->isEmptyValue($value)) {
        continue;
      }

      if (is_string($value)) {
        $min_length = $this->getSchemaConstraintValue($validation['min_length'] ?? NULL);
        if ($min_length !== NULL && mb_strlen($value) < (int) $min_length) {
          $errors[] = $this->getSchemaConstraintValue($error_messages['min_length'] ?? NULL)
            ?? "{$field_name} must be at least {$min_length} characters.";
        }

        $max_length = $this->getSchemaConstraintValue($validation['max_length'] ?? NULL);
        if ($max_length !== NULL && mb_strlen($value) > (int) $max_length) {
          $errors[] = $this->getSchemaConstraintValue($error_messages['max_length'] ?? NULL)
            ?? "{$field_name} must be no more than {$max_length} characters.";
        }

        $pattern = $this->getSchemaConstraintValue($validation['pattern'] ?? NULL);
        if ($pattern !== NULL && @preg_match('~' . $pattern . '~', $value) !== 1) {
          $errors[] = $this->getSchemaConstraintValue($error_messages['pattern'] ?? NULL)
            ?? "{$field_name} has an invalid format.";
        }
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Get class data by class ID.
   *
   * @param string $classId
   *   Class identifier (e.g., 'fighter', 'wizard', 'rogue').
   *
   * @return array
   *   Class data including hit_points, key_ability, proficiencies.
   *
   * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
   *   Section: "Mock Service Designs" - SchemaLoader Service
   *
   * @see docs/dungeoncrawler/testing/fixtures/schemas/classes_test.json
   *   Example class data structure
   *
   * @see docs/dungeoncrawler/issues/issue-1-character-class-hp-design.md
   *   Original design for class HP lookup
   *
   * TODO: Implement schema loading from step 2 schema for class data
   */
  public function getClassData(string $classId): array {
    // PSEUDOCODE:
    // 1. Load step 2 schema (character_options_step2.json)
    // 2. Extract classes array
    // 3. Find class by ID
    // 4. Validate structure
    // 5. Return class data with hit_points
    
    throw new \Exception('Not yet implemented - see issue-1-character-class-hp-design.md');
  }

  /**
   * Get ancestry data by ancestry ID.
   *
   * @param string $ancestryId
   *   Ancestry identifier (e.g., 'human', 'elf', 'dwarf').
   *
   * @return array
   *   Ancestry data including HP bonus, size, speed, ability boosts.
   *
   * @see docs/dungeoncrawler/testing/fixtures/schemas/ancestries_test.json
   *   Example ancestry data structure
   *
   * TODO: Implement ancestry schema loading from step 1 schema
   */
  public function getAncestryData(string $ancestryId): array {
    // PSEUDOCODE:
    // 1. Load step 1 schema (character_options_step1.json)
    // 2. Extract ancestries array
    // 3. Find ancestry by ID
    // 4. Return ancestry data
    
    throw new \Exception('Not yet implemented - see testing strategy design');
  }

  /**
   * Get background data by background ID.
   *
   * @param string $backgroundId
   *   Background identifier (e.g., 'warrior', 'scholar').
   *
   * @return array
   *   Background data including ability boosts, skill training.
   *
   * @see docs/dungeoncrawler/testing/fixtures/schemas/backgrounds_test.json
   *   Example background data structure
   *
   * TODO: Implement background schema loading
   */
  public function getBackgroundData(string $backgroundId): array {
    // PSEUDOCODE:
    // 1. Load appropriate schema file
    // 2. Find background by ID
    // 3. Return background data
    
    throw new \Exception('Not yet implemented - see testing strategy design');
  }

  /**
   * Validate schema structure.
   *
   * @param array $schema
   *   Schema data to validate.
   * @param string $schemaType
   *   Type of schema (classes, ancestries, backgrounds).
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   *
   * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
   *   Section: "Mock Service Designs" for validation patterns
   *
   * TODO: Implement schema validation based on type
   */
  public function validateSchemaStructure(array $schema, string $schemaType): bool {
    // PSEUDOCODE:
    // 1. Check required fields based on schema type
    // 2. Validate data types
    // 3. Check for required nested structures
    // 4. Return validation result
    
    throw new \Exception('Not yet implemented - see schema validation design');
  }

}
