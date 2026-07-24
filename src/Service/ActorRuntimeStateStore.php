<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Campaign-scoped actor runtime state persistence lane.
 */
class ActorRuntimeStateStore {

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * Load actor runtime payloads for one campaign.
   *
   * @return array<int, array<string,mixed>>
   *   Actor payload objects.
   */
  public function loadActorEntities(int $campaign_id): array {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_actor_runtime_state')) {
      return [];
    }

    $rows = $this->database->select('dc_campaign_actor_runtime_state', 'a')
      ->fields('a', ['entity_payload'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $entities = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $payload = json_decode((string) ($row['entity_payload'] ?? '{}'), TRUE);
      if (is_array($payload)) {
        $entities[] = $payload;
      }
    }
    return $entities;
  }

  /**
   * Upsert actor runtime payloads from composed entity rows.
   *
   * @param array<int, mixed> $entities
   *   Runtime entity list.
   */
  public function syncFromEntities(int $campaign_id, array $entities): void {
    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_actor_runtime_state')) {
      return;
    }

    $now = time();
    foreach ($entities as $entity) {
      if (!is_array($entity)) {
        continue;
      }
      $actor_instance_id = trim((string) (
        $entity['entity_instance_id']
        ?? $entity['instance_id']
        ?? $entity['id']
        ?? ''
      ));
      if ($actor_instance_id === '') {
        continue;
      }
      $entity_payload = json_encode($entity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($entity_payload) || $entity_payload === '') {
        throw new \RuntimeException(sprintf(
          'Actor runtime state store contract violation: failed to encode entity payload for campaign %d actor %s.',
          $campaign_id,
          $actor_instance_id
        ));
      }

      $character_id = 0;
      if (is_numeric($entity['character_id'] ?? NULL)) {
        $character_id = (int) $entity['character_id'];
      }
      elseif (is_numeric($entity['source_id'] ?? NULL)) {
        $character_id = (int) $entity['source_id'];
      }
      $room_id = trim((string) ($entity['placement']['room_id'] ?? ''));

      $field_values = [
        'entity_type' => strtolower(trim((string) ($entity['entity_type'] ?? 'unknown'))),
        'character_id' => $character_id > 0 ? $character_id : NULL,
        'room_id' => $room_id !== '' ? $room_id : NULL,
        'entity_payload' => $entity_payload,
        'updated' => $now,
      ];
      $this->database->merge('dc_campaign_actor_runtime_state')
        ->keys([
          'campaign_id' => $campaign_id,
          'actor_instance_id' => $actor_instance_id,
        ])
        ->fields($field_values)
        ->insertFields($field_values + [
          'campaign_id' => $campaign_id,
          'actor_instance_id' => $actor_instance_id,
          'created' => $now,
        ])
        ->execute();
    }
  }

}
