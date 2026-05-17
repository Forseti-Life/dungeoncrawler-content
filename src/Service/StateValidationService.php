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
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->schemaBasePath = dirname(__DIR__) . '/../config/schemas';
  }

  /**
   * Validate campaign state against schema.
   */
  public function validateCampaignState(array $state): array {
    return $this->validateAgainstSchema($state, 'campaign.schema.json');
  }

  /**
   * Validate dungeon state against schema.
   */
  public function validateDungeonState(array $state): array {
    return $this->validateAgainstSchema($state, 'dungeon_level.schema.json');
  }

  /**
   * Validate room state against schema.
   */
  public function validateRoomState(array $state): array {
    return $this->validateAgainstSchema($state, 'room.schema.json');
  }

  /**
   * Validate NPC definition against schema.
   */
  public function validateNpcDefinition(array $npc): array {
    $schema_path = dirname(__DIR__) . '/../../../../../docs/dungeoncrawler/schemas/pf2e-npc-definition.schema.json';
    return $this->validateAgainstSchemaFile($npc, $schema_path);
  }

  /**
   * Validate a generated NPC sheet against the canonical contract schema.
   */
  public function validateNpcSheet(array $sheet): array {
    return $this->validateAgainstSchema($sheet, 'npc_sheet.schema.json');
  }

  /**
   * Validate a normalized storyline definition against the canonical contract.
   */
  public function validateStorylineDefinition(array $definition): array {
    return $this->validateAgainstSchema($definition, 'storyline_definition.schema.json');
  }

  /**
   * Validate a normalized storyline bootstrap request payload.
   */
  public function validateStorylineBootstrapRequest(array $request): array {
    return $this->validateAgainstSchema($request, 'storyline_bootstrap_request.schema.json');
  }

  /**
   * Validate a queued storyline expansion job payload.
   */
  public function validateStorylineExpansionJob(array $payload): array {
    return $this->validateAgainstSchema($payload, 'storyline_expansion_job.schema.json');
  }

  /**
   * Validate stored storyline runtime state against the canonical questline contract.
   */
  public function validateStorylineRuntime(array $runtime): array {
    return $this->validateAgainstSchema($runtime, 'storyline_runtime.schema.json');
  }

  /**
   * Validate a hexmap quest summary payload against the canonical contract.
   */
  public function validateQuestSummary(array $summary): array {
    return $this->validateAgainstSchema($summary, 'quest_summary.schema.json');
  }

  /**
   * Validate a room-chat quest update payload against the canonical contract.
   */
  public function validateQuestUpdate(array $update): array {
    return $this->validateAgainstSchema($update, 'quest_update.schema.json');
  }

  /**
   * Validate data against a schema file.
   */
  private function validateAgainstSchema(array $data, string $schema_filename): array {
    $schema_path = $this->schemaBasePath . '/' . $schema_filename;
    return $this->validateAgainstSchemaFile($data, $schema_path);
  }

  /**
   * Validate data against a schema file path.
   */
  private function validateAgainstSchemaFile(array $data, string $schema_path): array {
    if (!file_exists($schema_path)) {
      $this->logger->error('Schema file not found: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Schema file not found: {$schema_path}"]];
    }

    $schema_content = file_get_contents($schema_path);
    $schema = json_decode((string) $schema_content, TRUE);

    if (!is_array($schema)) {
      $this->logger->error('Invalid schema file: {path}', ['path' => $schema_path]);
      return ['valid' => FALSE, 'errors' => ["Invalid schema file: {$schema_path}"]];
    }

    $errors = $this->validateValueAgainstSchema($data, $schema, '');
    return ['valid' => empty($errors), 'errors' => $errors];
  }

  /**
   * Recursively validate a value against a schema fragment.
   *
   * @param mixed $value
   *   Value to validate.
   * @param array $schema
   *   Schema fragment.
   * @param string $field_path
   *   Dot-notated field path.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  private function validateValueAgainstSchema($value, array $schema, string $field_path): array {
    $errors = [];
    $field_name = $field_path === '' ? 'root' : $field_path;

    $type_errors = $this->validateType($value, $schema, $field_name);
    if ($type_errors !== []) {
      return $type_errors;
    }

    if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], TRUE)) {
      $errors[] = "Field '{$field_name}' must be one of: " . implode(', ', array_map('strval', $schema['enum']));
    }

    if (is_string($value) && isset($schema['pattern']) && is_string($schema['pattern'])) {
      $pattern = '/' . str_replace('/', '\/', $schema['pattern']) . '/';
      if (@preg_match($pattern, '') !== FALSE && !preg_match($pattern, $value)) {
        $errors[] = "Field '{$field_name}' does not match required pattern";
      }
    }

    $json_type = $this->resolveJsonTypeForSchema($value, $schema);

    if ($json_type === 'object') {
      if (isset($schema['required']) && is_array($schema['required'])) {
        foreach ($schema['required'] as $required_field) {
          if (!array_key_exists($required_field, $value)) {
            $required_path = $field_path === '' ? (string) $required_field : $field_path . '.' . $required_field;
            $errors[] = "Missing required field: {$required_path}";
          }
        }
      }

      $properties = is_array($schema['properties'] ?? NULL) ? $schema['properties'] : [];
      foreach ($value as $key => $property_value) {
        $property_path = $field_path === '' ? (string) $key : $field_path . '.' . $key;
        if (!isset($properties[$key])) {
          if (($schema['additionalProperties'] ?? TRUE) === FALSE) {
            $errors[] = "Unknown property: {$property_path}";
          }
          continue;
        }
        $errors = array_merge($errors, $this->validateValueAgainstSchema($property_value, $properties[$key], $property_path));
      }
    }
    elseif ($json_type === 'array' && isset($schema['items']) && is_array($schema['items'])) {
      foreach ($value as $index => $item) {
        $item_path = $field_name . '[' . $index . ']';
        $errors = array_merge($errors, $this->validateValueAgainstSchema($item, $schema['items'], $item_path));
      }
    }

    return $errors;
  }

  /**
   * Resolve a PHP value to a JSON schema type label.
   */
  private function resolveJsonType($value): string {
    $actual_type = gettype($value);
    $is_sequential_array = is_array($value) && (empty($value) || array_keys($value) === range(0, count($value) - 1));

    return [
      'boolean' => 'boolean',
      'integer' => 'integer',
      'double' => 'number',
      'string' => 'string',
      'array' => $is_sequential_array ? 'array' : 'object',
      'NULL' => 'null',
    ][$actual_type] ?? 'unknown';
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
   * @return array<int, string>
   *   Type validation errors.
   */
  private function validateType($value, array $schema, string $field_name): array {
    $errors = [];

    if (!isset($schema['type'])) {
      return $errors;
    }

    $json_type = $this->resolveJsonTypeForSchema($value, $schema);
    $allowed_types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
    if (!in_array($json_type, $allowed_types, TRUE)) {
      $errors[] = "Field '{$field_name}' has invalid type. Expected " . implode('|', $allowed_types) . ", got {$json_type}";
      return $errors;
    }

    if (($json_type === 'integer' || $json_type === 'number') && is_numeric($value)) {
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
      if (!empty($schema['uniqueItems'])) {
        $encoded = array_map(static fn($item) => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $value);
        if (count($encoded) !== count(array_unique($encoded))) {
          $errors[] = "Field '{$field_name}' must contain unique items";
        }
      }
    }

    return $errors;
  }

  /**
   * Resolve the effective JSON type for a schema-aware validation pass.
   *
   * Empty JSON objects decode to [] when using json_decode(..., TRUE), so treat
   * an empty PHP array as an object when the schema expects an object and does
   * not also allow arrays.
   */
  private function resolveJsonTypeForSchema($value, array $schema): string {
    $json_type = $this->resolveJsonType($value);
    if (
      $json_type === 'array'
      && is_array($value)
      && $value === []
      && $this->schemaAllowsType($schema, 'object')
      && !$this->schemaAllowsType($schema, 'array')
    ) {
      return 'object';
    }

    return $json_type;
  }

  /**
   * Determine whether a schema allows a given JSON type.
   */
  private function schemaAllowsType(array $schema, string $type): bool {
    if (!isset($schema['type'])) {
      return FALSE;
    }

    $allowed_types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
    return in_array($type, $allowed_types, TRUE);
  }

}
