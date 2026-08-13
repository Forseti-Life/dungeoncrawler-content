<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Canonical effect-instance lifecycle persistence for actor-scoped effects.
 */
class EffectInstanceService {

  protected Connection $database;
  protected EffectDefinitionRegistryService $definitionRegistry;

  public function __construct(
    Connection $database,
    EffectDefinitionRegistryService $definition_registry
  ) {
    $this->database = $database;
    $this->definitionRegistry = $definition_registry;
  }

  /**
   * Returns true when canonical effect-instance storage exists.
   */
  public function hasStorage(): bool {
    return $this->database->schema()->tableExists('dc_effect_instances');
  }

  /**
   * Returns true when a canonical effect definition exists.
   */
  public function hasDefinition(string $definition_id): bool {
    return $this->definitionRegistry->hasDefinition($definition_id);
  }

  /**
   * Returns true when a definition is owned by effect-instance lifecycle rows.
   */
  public function isInstanceManagedDefinition(string $definition_id): bool {
    return $this->definitionRegistry->isInstanceManagedDefinition($definition_id);
  }

  /**
   * Creates or refreshes an actor-scoped canonical effect instance.
   */
  public function upsertPersistentActorEffectInstance(
    string $character_id,
    ?int $campaign_id,
    ?string $instance_id,
    string $definition_id,
    array $source = [],
    array $metadata = [],
  ): array {
    $character_id = trim($character_id);
    if ($character_id === '') {
      throw new \InvalidArgumentException('Effect instance upsert requires a non-empty character id.');
    }
    if (!$this->hasStorage()) {
      throw new \RuntimeException('Canonical effect instance storage missing: dc_effect_instances.');
    }

    $definition = $this->definitionRegistry->getDefinition($definition_id);
    if (!is_array($definition)) {
      throw new \InvalidArgumentException(sprintf('Unknown effect definition: %s', $definition_id));
    }

    $definition_id = strtolower(trim((string) ($definition['definition_id'] ?? $definition_id)));
    $scope_key = $this->buildActorScopeKey($character_id, $campaign_id, $instance_id, $definition_id);
    $now = time();

    $existing = $this->database->select('dc_effect_instances', 'ei')
      ->fields('ei', ['id', 'effect_instance_id'])
      ->condition('scope_key', $scope_key)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc() ?: NULL;

    $payload = [
      'scope_key' => $scope_key,
      'definition_id' => $definition_id,
      'character_id' => $character_id,
      'campaign_id' => $campaign_id,
      'instance_id' => $this->normalizeNullableString($instance_id),
      'source_type' => (string) ($source['type'] ?? 'system'),
      'source_id' => (string) ($source['id'] ?? $definition_id),
      'source_scope' => (string) ($source['scope'] ?? 'actor'),
      'target_scope_type' => 'actor',
      'target_scope_id' => $this->buildActorTargetScopeId($character_id, $instance_id),
      'target_subscope' => (string) ($definition['condition_code'] ?? $definition_id),
      'phase_scope' => (string) ($definition['phase_scope'] ?? 'persistent-sheet'),
      'stacking_type' => (string) ($definition['stacking_type'] ?? 'untyped'),
      'value_payload_json' => json_encode(
        ['impacts' => is_array($definition['impacts'] ?? NULL) ? $definition['impacts'] : []],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      ),
      'application_policy_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'expiration_policy_json' => json_encode(
        is_array($definition['expiration_policy'] ?? NULL) ? $definition['expiration_policy'] : [],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      ),
      'trigger_policy_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'is_active' => 1,
      'activated' => $now,
      'expires' => NULL,
      'expired' => NULL,
      'updated' => $now,
      'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];

    if ($existing) {
      $this->database->update('dc_effect_instances')
        ->fields($payload)
        ->condition('id', (int) $existing['id'])
        ->execute();

      return $this->loadEffectInstanceById((int) $existing['id']) ?? [];
    }

    $payload['effect_instance_id'] = $this->generateEffectInstanceId();
    $payload['created'] = $now;

    $id = $this->database->insert('dc_effect_instances')
      ->fields($payload)
      ->execute();

    return $this->loadEffectInstanceById((int) $id) ?? [];
  }

  /**
   * Lists active actor-scoped effect instances.
   */
  public function listActivePersistentActorEffectInstances(
    string $character_id,
    ?int $campaign_id = NULL,
    ?string $instance_id = NULL,
  ): array {
    $character_id = trim($character_id);
    if ($character_id === '' || !$this->hasStorage()) {
      return [];
    }

    $query = $this->database->select('dc_effect_instances', 'ei')
      ->fields('ei')
      ->condition('character_id', $character_id)
      ->condition('is_active', 1);

    if ($campaign_id === NULL) {
      $query->isNull('campaign_id');
    }
    else {
      $query->condition('campaign_id', $campaign_id);
    }

    $instance_id = $this->normalizeNullableString($instance_id);
    if ($instance_id === NULL) {
      $query->isNull('instance_id');
    }
    else {
      $query->condition('instance_id', $instance_id);
    }

    $rows = $query
      ->orderBy('definition_id')
      ->orderBy('created')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return array_map(fn (array $row): array => $this->normalizeStoredRow($row), $rows);
  }

  /**
   * Expires active actor effect instances whose definition matches a trigger.
   *
   * @return array{expired_count:int,expired_definition_ids:array<int,string>,expired_condition_codes:array<int,string>}
   */
  public function expirePersistentActorEffectsByTrigger(
    string $character_id,
    ?int $campaign_id,
    ?string $instance_id,
    string $trigger,
  ): array {
    $trigger = strtolower(trim($trigger));
    if ($trigger === '') {
      throw new \InvalidArgumentException('Effect expiration trigger is required.');
    }

    $active = $this->listActivePersistentActorEffectInstances($character_id, $campaign_id, $instance_id);
    if ($active === []) {
      return [
        'expired_count' => 0,
        'expired_definition_ids' => [],
        'expired_condition_codes' => [],
      ];
    }

    $expired_ids = [];
    $expired_definitions = [];
    $expired_condition_codes = [];
    $now = time();
    foreach ($active as $instance) {
      $policy = is_array($instance['expiration_policy'] ?? NULL) ? $instance['expiration_policy'] : [];
      $configured = strtolower(trim((string) ($policy['trigger'] ?? '')));
      if ($configured === '' || $configured !== $trigger) {
        continue;
      }
      $row_id = (int) ($instance['id'] ?? 0);
      if ($row_id <= 0) {
        continue;
      }

      $this->database->update('dc_effect_instances')
        ->fields([
          'is_active' => 0,
          'expired' => $now,
          'updated' => $now,
        ])
        ->condition('id', $row_id)
        ->execute();

      $definition_id = (string) ($instance['definition_id'] ?? '');
      if ($definition_id !== '') {
        $expired_definitions[] = $definition_id;
      }
      $condition_code = (string) ($instance['target_subscope'] ?? '');
      if ($condition_code !== '') {
        $expired_condition_codes[] = $condition_code;
      }
      $expired_ids[] = $row_id;
    }

    return [
      'expired_count' => count($expired_ids),
      'expired_definition_ids' => array_values(array_unique($expired_definitions)),
      'expired_condition_codes' => array_values(array_unique($expired_condition_codes)),
    ];
  }

  /**
   * Expires active actor effect instances by definition id.
   */
  public function expirePersistentActorEffectDefinition(
    string $character_id,
    ?int $campaign_id,
    ?string $instance_id,
    string $definition_id,
  ): int {
    $character_id = trim($character_id);
    $definition_id = strtolower(trim($definition_id));
    if ($character_id === '' || $definition_id === '' || !$this->hasStorage()) {
      return 0;
    }

    $update = $this->database->update('dc_effect_instances')
      ->fields([
        'is_active' => 0,
        'expired' => time(),
        'updated' => time(),
      ])
      ->condition('character_id', $character_id)
      ->condition('definition_id', $definition_id)
      ->condition('is_active', 1);

    if ($campaign_id === NULL) {
      $update->isNull('campaign_id');
    }
    else {
      $update->condition('campaign_id', $campaign_id);
    }

    $instance_id = $this->normalizeNullableString($instance_id);
    if ($instance_id === NULL) {
      $update->isNull('instance_id');
    }
    else {
      $update->condition('instance_id', $instance_id);
    }

    return (int) $update->execute();
  }

  /**
   * Builds aggregate adjustments from active effect-instance payloads.
   */
  public function buildPersistentAdjustmentProjection(array $instances): array {
    $projection = [
      'armor_class' => 0,
      'speed' => 0,
    ];

    foreach ($instances as $instance) {
      if (!is_array($instance)) {
        continue;
      }
      $payload = is_array($instance['value_payload'] ?? NULL) ? $instance['value_payload'] : [];
      $impacts = is_array($payload['impacts'] ?? NULL) ? $payload['impacts'] : [];
      foreach ($impacts as $impact) {
        if (!is_array($impact)) {
          continue;
        }
        $target = (string) ($impact['target'] ?? '');
        $operation = strtolower(trim((string) ($impact['operation'] ?? '')));
        $value = (int) ($impact['value'] ?? 0);
        if ($operation !== ImpactContractService::OPERATION_ADD || $value === 0) {
          continue;
        }

        if ($target === ImpactContractService::TARGET_AC_OTHER_BONUSES) {
          $projection['armor_class'] += $value;
          continue;
        }
        if ($target === ImpactContractService::TARGET_SPEED_TOTAL) {
          $projection['speed'] += $value;
        }
      }
    }

    return $projection;
  }

  /**
   * Builds a tooltip model for a concrete effect instance.
   */
  public function buildTooltipModelForInstance(array $instance): ?array {
    $definition_id = strtolower(trim((string) ($instance['definition_id'] ?? '')));
    if ($definition_id === '') {
      return NULL;
    }
    return $this->definitionRegistry->buildTooltipModel($definition_id, $instance, []);
  }

  /**
   * Builds a tooltip model for a definition id.
   */
  public function buildTooltipModelForDefinition(string $definition_id, array $context = []): ?array {
    return $this->definitionRegistry->buildTooltipModel($definition_id, [], $context);
  }

  /**
   * Loads one effect instance row by numeric id.
   */
  private function loadEffectInstanceById(int $id): ?array {
    if ($id <= 0) {
      return NULL;
    }

    $row = $this->database->select('dc_effect_instances', 'ei')
      ->fields('ei')
      ->condition('id', $id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $this->normalizeStoredRow($row) : NULL;
  }

  /**
   * Builds a stable scope key for actor-scoped effect instances.
   */
  private function buildActorScopeKey(
    string $character_id,
    ?int $campaign_id,
    ?string $instance_id,
    string $definition_id,
  ): string {
    return implode(':', [
      'actor',
      $character_id,
      (string) ($campaign_id ?? 0),
      trim((string) ($instance_id ?? '')),
      $definition_id,
    ]);
  }

  /**
   * Builds actor target scope reference.
   */
  private function buildActorTargetScopeId(string $character_id, ?string $instance_id): string {
    $instance_id = trim((string) ($instance_id ?? ''));
    if ($instance_id !== '') {
      return 'actor:' . $instance_id;
    }
    return 'actor:character:' . $character_id;
  }

  /**
   * Normalizes a stored effect-instance row.
   */
  private function normalizeStoredRow(array $row): array {
    $value_payload = json_decode((string) ($row['value_payload_json'] ?? ''), TRUE);
    $application_policy = json_decode((string) ($row['application_policy_json'] ?? ''), TRUE);
    $expiration_policy = json_decode((string) ($row['expiration_policy_json'] ?? ''), TRUE);
    $trigger_policy = json_decode((string) ($row['trigger_policy_json'] ?? ''), TRUE);
    $metadata = json_decode((string) ($row['metadata_json'] ?? ''), TRUE);

    return [
      'id' => (int) ($row['id'] ?? 0),
      'effect_instance_id' => (string) ($row['effect_instance_id'] ?? ''),
      'scope_key' => (string) ($row['scope_key'] ?? ''),
      'definition_id' => (string) ($row['definition_id'] ?? ''),
      'character_id' => (string) ($row['character_id'] ?? ''),
      'campaign_id' => isset($row['campaign_id']) ? (int) $row['campaign_id'] : NULL,
      'instance_id' => $this->normalizeNullableString($row['instance_id'] ?? NULL),
      'source_type' => (string) ($row['source_type'] ?? ''),
      'source_id' => (string) ($row['source_id'] ?? ''),
      'source_scope' => (string) ($row['source_scope'] ?? ''),
      'target_scope_type' => (string) ($row['target_scope_type'] ?? ''),
      'target_scope_id' => (string) ($row['target_scope_id'] ?? ''),
      'target_subscope' => (string) ($row['target_subscope'] ?? ''),
      'phase_scope' => (string) ($row['phase_scope'] ?? ''),
      'stacking_type' => (string) ($row['stacking_type'] ?? ''),
      'value_payload' => is_array($value_payload) ? $value_payload : [],
      'application_policy' => is_array($application_policy) ? $application_policy : [],
      'expiration_policy' => is_array($expiration_policy) ? $expiration_policy : [],
      'trigger_policy' => is_array($trigger_policy) ? $trigger_policy : [],
      'is_active' => (int) ($row['is_active'] ?? 0) === 1,
      'created' => (int) ($row['created'] ?? 0),
      'activated' => (int) ($row['activated'] ?? 0),
      'expires' => isset($row['expires']) ? (int) $row['expires'] : NULL,
      'expired' => isset($row['expired']) ? (int) $row['expired'] : NULL,
      'updated' => (int) ($row['updated'] ?? 0),
      'metadata' => is_array($metadata) ? $metadata : [],
    ];
  }

  /**
   * Generates a stable effect-instance identifier.
   */
  private function generateEffectInstanceId(): string {
    try {
      return 'eff_' . bin2hex(random_bytes(8));
    }
    catch (\Throwable $e) {
      return 'eff_' . str_replace('.', '', uniqid('', TRUE));
    }
  }

  /**
   * Normalizes nullable string inputs.
   */
  private function normalizeNullableString(mixed $value): ?string {
    if (!is_string($value)) {
      return NULL;
    }
    $trimmed = trim($value);
    return $trimmed === '' ? NULL : $trimmed;
  }

}
