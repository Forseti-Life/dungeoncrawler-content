<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates state payloads against JSON schemas.
 */
class StateValidationService {

  private LoggerInterface $logger;
  private string $schemaBasePath;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('dungeoncrawler');
    // Schema files are in config/schemas/ directory.
    $this->schemaBasePath = dirname(__DIR__) . '/../config/schemas';
  }

  /**
   * Validate campaign state against schema.
   *
   * @param array $state
   *   Campaign state data.
   *
   * @return array
   *   Validation result with 'valid' boolean and 'errors' array.
   */
  public function validateCampaignState(array $state): array {
    return $this->validateAgainstSchema($state, 'campaign.schema.json');
  }

  /**
   * Validate dungeon state against schema.
   *
   * @param array $state
   *   Dungeon state data.
   *
   * @return array
   *   Validation result with 'valid' boolean and 'errors' array.
   */
  public function validateDungeonState(array $state): array {
    return $this->validateAgainstSchema($state, 'dungeon_level.schema.json');
  }

  /**
   * Validate room state against schema.
   *
   * @param array $state
   *   Room state data.
   *
   * @return array
   *   Validation result with 'valid' boolean and 'errors' array.
   */
  public function validateRoomState(array $state): array {
    return $this->validateAgainstSchema($state, 'room.schema.json');
  }

  /**
   * Validate NPC definition against schema.
   *
   * @param array $npc
   *   NPC definition data.
   *
   * @return array
   *   Validation result with 'valid' boolean and 'errors' array.
   */
  public function validateNpcDefinition(array $npc): array {
    // NPC schema is in docs/dungeoncrawler/schemas.
    $schemaPath = dirname(__DIR__) . '/../../../../../docs/dungeoncrawler/schemas/pf2e-npc-definition.schema.json';
    return $this->validateAgainstSchemaFile($npc, $schemaPath);
  }

  /**
   * Validate data against a schema file.
   *
   * @param array $data
   *   Data to validate.
   * @param string $schema_filename
   *   Schema filename relative to schemaBasePath.
   *
   * @return array
   *   Validation result with 'valid' boolean and 'errors' array.
   */
  private function validateAgainstSchema(array $data, string $schema_filename): array {
    $schemaPath = $this->schemaBasePath . '/' . $schema_filename;
    return $this->validateAgainstSchemaFile($data, $schemaPath);
  }

  /**
   * Validate data against a schema file path.
   *
   * @param array $data
   *   Data to validate.
   * @param string $schema_path
   *   Full path to schema file.
   *
   * @return array
   *   Validation result with 'valid' boolean and 'errors' array.
   */
  private function validateAgainstSchemaFile(array $data, string $schema_path): array {
    // Check if schema file exists.
    if (!file_exists($schema_path)) {
      $this->logger->error('Schema file not found: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Schema file not found: {$schema_path}"]];
    }

    // Load schema.
    $schema_content = file_get_contents($schema_path);
    $schema = json_decode($schema_content, TRUE);
    
    if (!is_array($schema)) {
      $this->logger->error('Invalid schema file: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Invalid schema file: {$schema_path}"]];
    }

    // Perform basic validation.
    $errors = $this->basicValidate($data, $schema);
    
    if (empty($errors)) {
      return ['valid' => TRUE, 'errors' => []];
    }

    return ['valid' => FALSE, 'errors' => $errors];
  }

  /**
   * Basic JSON schema validation.
   *
   * This is a simplified validator that checks:
   * - Required fields
   * - Type validation
   * - Basic constraints
   *
   * For production, consider using a full JSON Schema validator library.
   *
   * @param array $data
   *   Data to validate.
   * @param array $schema
   *   Schema definition.
   *
   * @return array
   *   Array of error messages.
   */
  private function basicValidate(array $data, array $schema): array {
    $errors = [];

    // Check required fields.
    if (isset($schema['required']) && is_array($schema['required'])) {
      foreach ($schema['required'] as $required_field) {
        if (!array_key_exists($required_field, $data)) {
          $errors[] = "Missing required field: {$required_field}";
        }
      }
    }

    // Check properties types.
    if (isset($schema['properties']) && is_array($schema['properties'])) {
      foreach ($data as $key => $value) {
        if (!isset($schema['properties'][$key])) {
          // Skip unknown properties if additionalProperties is allowed.
          if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === FALSE) {
            $errors[] = "Unknown property: {$key}";
          }
          continue;
        }

        $property_schema = $schema['properties'][$key];
        $type_errors = $this->validateType($value, $property_schema, $key);
        $errors = array_merge($errors, $type_errors);
      }
    }

    return $errors;
  }

  /**
   * Validate a value against a type schema.
   *
   * @param mixed $value
   *   Value to validate.
   * @param array $schema
   *   Property schema.
   * @param string $field_name
   *   Field name for error messages.
   *
   * @return array
   *   Array of error messages.
   */
  private function validateType($value, array $schema, string $field_name): array {
    $errors = [];

    if (!isset($schema['type'])) {
      return $errors;
    }

    $expected_type = $schema['type'];
    $actual_type = gettype($value);

    // Map PHP types to JSON Schema types.
    // For arrays: check if keys are sequential integers starting from 0.
    $is_sequential_array = is_array($value) && (
      empty($value) || array_keys($value) === range(0, count($value) - 1)
    );
    
    $type_map = [
      'boolean' => 'boolean',
      'integer' => 'integer',
      'double' => 'number',
      'string' => 'string',
      'array' => $is_sequential_array ? 'array' : 'object',
      'NULL' => 'null',
    ];

    $json_type = $type_map[$actual_type] ?? 'unknown';

    // Allow multiple types.
    $allowed_types = is_array($expected_type) ? $expected_type : [$expected_type];

    if (!in_array($json_type, $allowed_types, TRUE)) {
      $errors[] = "Field '{$field_name}' has invalid type. Expected " . implode('|', $allowed_types) . ", got {$json_type}";
    }

    // Validate constraints.
    if ($json_type === 'integer' || $json_type === 'number') {
      if (isset($schema['minimum']) && $value < $schema['minimum']) {
        $errors[] = "Field '{$field_name}' is below minimum value {$schema['minimum']}";
      }
      if (isset($schema['maximum']) && $value > $schema['maximum']) {
        $errors[] = "Field '{$field_name}' is above maximum value {$schema['maximum']}";
      }
    }

    if ($json_type === 'string') {
      if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
        $errors[] = "Field '{$field_name}' is too short (minimum {$schema['minLength']} characters)";
      }
      if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
        $errors[] = "Field '{$field_name}' is too long (maximum {$schema['maxLength']} characters)";
      }
    }

    if ($json_type === 'array') {
      if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
        $errors[] = "Field '{$field_name}' has too few items (minimum {$schema['minItems']})";
      }
      if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
        $errors[] = "Field '{$field_name}' has too many items (maximum {$schema['maxItems']})";
      }
    }

    return $errors;
  }

}
