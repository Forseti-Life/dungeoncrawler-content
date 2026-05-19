<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Stores canonical active effect rows for character state.
 */
class ActiveEffectStoreService {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Returns persisted active effect rows for a character scope.
   */
  public function listActiveEffects(string $character_id, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    if ($character_id === '' || !$this->hasStorage()) {
      return [];
    }

    $query = $this->database->select('dc_active_effects', 'ae')
      ->fields('ae')
      ->condition('character_id', $character_id)
      ->condition('is_active', 1);

    if ($campaign_id === NULL) {
      $query->isNull('campaign_id');
    }
    else {
      $query->condition('campaign_id', $campaign_id);
    }

    if ($instance_id === NULL || $instance_id === '') {
      $query->isNull('instance_id');
    }
    else {
      $query->condition('instance_id', $instance_id);
    }

    $rows = $query
      ->orderBy('source_type')
      ->orderBy('target')
      ->orderBy('source_id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return array_map(function (array $row): array {
      $impact = json_decode((string) ($row['impact_json'] ?? ''), TRUE);
      return [
        'id' => (int) ($row['id'] ?? 0),
        'scope_key' => (string) ($row['scope_key'] ?? ''),
        'character_id' => (string) ($row['character_id'] ?? ''),
        'campaign_id' => isset($row['campaign_id']) ? (int) $row['campaign_id'] : NULL,
        'instance_id' => $row['instance_id'] ?? NULL,
        'effect_type' => (string) ($row['effect_type'] ?? 'impact_contract'),
        'source_type' => (string) ($row['source_type'] ?? ''),
        'source_id' => (string) ($row['source_id'] ?? ''),
        'target' => (string) ($row['target'] ?? ''),
        'operation' => (string) ($row['operation'] ?? ''),
        'stacking' => (string) ($row['stacking'] ?? ''),
        'phase' => (string) ($row['phase'] ?? ''),
        'breakdown_key' => $row['breakdown_key'] ?? NULL,
        'impact' => is_array($impact) ? $impact : [],
        'created' => (int) ($row['created'] ?? 0),
        'updated' => (int) ($row['updated'] ?? 0),
      ];
    }, $rows);
  }

  /**
   * Replaces persisted active effect rows for a character scope.
   */
  public function syncCharacterImpacts(
    string $character_id,
    array $impacts,
    ?int $campaign_id = NULL,
    ?string $instance_id = NULL,
  ): void {
    if ($character_id === '' || !$this->hasStorage()) {
      return;
    }

    $delete = $this->database->delete('dc_active_effects')
      ->condition('character_id', $character_id);

    if ($campaign_id === NULL) {
      $delete->isNull('campaign_id');
    }
    else {
      $delete->condition('campaign_id', $campaign_id);
    }

    if ($instance_id === NULL || $instance_id === '') {
      $delete->isNull('instance_id');
    }
    else {
      $delete->condition('instance_id', $instance_id);
    }

    $delete->execute();

    $now = time();
    foreach ($impacts as $impact) {
      if (!is_array($impact)) {
        continue;
      }

      $source_type = (string) ($impact['source_type'] ?? '');
      $source_id = (string) ($impact['source_id'] ?? '');
      $target = (string) ($impact['target'] ?? '');
      if ($source_type === '' || $source_id === '' || $target === '') {
        continue;
      }

      $this->database->insert('dc_active_effects')
        ->fields([
          'scope_key' => $this->buildScopeKey($character_id, $campaign_id, $instance_id, $impact),
          'character_id' => $character_id,
          'campaign_id' => $campaign_id,
          'instance_id' => ($instance_id === '' ? NULL : $instance_id),
          'effect_type' => 'impact_contract',
          'source_type' => $source_type,
          'source_id' => $source_id,
          'target' => $target,
          'operation' => (string) ($impact['operation'] ?? ''),
          'stacking' => (string) ($impact['stacking'] ?? ''),
          'phase' => (string) ($impact['phase'] ?? ''),
          'breakdown_key' => $impact['breakdown_key'] ?? NULL,
          'impact_json' => json_encode($impact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'is_active' => 1,
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
  }

  /**
   * Returns TRUE when the active effects table exists.
   */
  public function hasStorage(): bool {
    return $this->database->schema()->tableExists('dc_active_effects');
  }

  /**
   * Extracts canonical impact payloads from stored active-effect rows.
   */
  public function extractStoredImpacts(array $rows): array {
    return array_values(array_filter(array_map(static function ($row): ?array {
      if (!is_array($row)) {
        return NULL;
      }
      return is_array($row['impact'] ?? NULL) ? $row['impact'] : NULL;
    }, $rows)));
  }

  /**
   * Builds a canonical identity key for an impact contract.
   */
  public function buildImpactIdentity(array $impact): string {
    return implode(':', [
      (string) ($impact['source_type'] ?? ''),
      (string) ($impact['source_id'] ?? ''),
      (string) ($impact['target'] ?? ''),
      (string) ($impact['phase'] ?? ''),
    ]);
  }

  /**
   * Builds a unique scope key for an impact row.
   */
  protected function buildScopeKey(
    string $character_id,
    ?int $campaign_id,
    ?string $instance_id,
    array $impact,
  ): string {
    return implode(':', [
      $character_id,
      (string) ($campaign_id ?? 0),
      (string) ($instance_id ?? ''),
      $this->buildImpactIdentity($impact),
    ]);
  }

}
