<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical runtime helper for active stance state transitions.
 */
class StanceRuntimeService {

  protected StanceEventStoreService $stanceEventStoreService;
  protected StanceStateStoreService $stanceStateStoreService;

  public function __construct(
    StanceEventStoreService $stance_event_store_service,
    StanceStateStoreService $stance_state_store_service
  ) {
    $this->stanceEventStoreService = $stance_event_store_service;
    $this->stanceStateStoreService = $stance_state_store_service;
  }

  /**
   * Enter a stance in a normalized character-data payload.
   *
   * @param array<string, mixed> $character_data
   * @param array<string, mixed> $source
   *
   * @return array<string, mixed>
   *   Updated character data.
   */
  public function enterStance(
    array $character_data,
    string $stance_id,
    int $max_active_stances = 1,
    array $source = [],
    int $campaign_id = 0,
    string $entity_ref = ''
  ): array {
    $stance_id = strtolower(trim($stance_id));
    if ($stance_id === '') {
      return $character_data;
    }

    $character_data['stance_state'] = is_array($character_data['stance_state'] ?? NULL) ? $character_data['stance_state'] : [];
    $character_data['stance_state']['max_active_stances'] = max(1, $max_active_stances);
    $active = is_array($character_data['stance_state']['active_stances'] ?? NULL)
      ? array_values($character_data['stance_state']['active_stances'])
      : [];
    $active = array_values(array_filter($active, static fn($entry): bool => is_array($entry) && trim((string) ($entry['stance_id'] ?? '')) !== $stance_id));

    if (count($active) >= $character_data['stance_state']['max_active_stances']) {
      $active = array_slice($active, count($active) - ($character_data['stance_state']['max_active_stances'] - 1));
    }

    $active[] = [
      'stance_id' => $stance_id,
      'source_type' => (string) ($source['source_type'] ?? 'command'),
      'source_id' => (string) ($source['source_id'] ?? ''),
      'entered_at' => date('c'),
    ];
    $character_data['stance_state']['active_stances'] = $active;
    $character_data['stance_state']['updated_at'] = date('c');

    $this->persistStanceProjection($character_data, $stance_id, 'stance_entered', $source, $campaign_id, $entity_ref);

    return $character_data;
  }

  /**
   * Resolve whether a stance is active from canonical state with legacy fallback.
   */
  public function isStanceActive(array $character_data, string $stance_id): bool {
    $stance_id = strtolower(trim($stance_id));
    if ($stance_id === '') {
      return FALSE;
    }

    $active = is_array($character_data['stance_state']['active_stances'] ?? NULL)
      ? array_values($character_data['stance_state']['active_stances'])
      : [];
    foreach ($active as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      if (strtolower(trim((string) ($entry['stance_id'] ?? ''))) === $stance_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Exit a stance in a normalized character-data payload.
   *
   * @param array<string, mixed> $character_data
   *
   * @return array<string, mixed>
   *   Updated character data.
   */
  public function exitStance(
    array $character_data,
    string $stance_id,
    array $source = [],
    int $campaign_id = 0,
    string $entity_ref = ''
  ): array {
    $stance_id = strtolower(trim($stance_id));
    if ($stance_id === '') {
      return $character_data;
    }

    $character_data['stance_state'] = is_array($character_data['stance_state'] ?? NULL) ? $character_data['stance_state'] : [];
    $active = is_array($character_data['stance_state']['active_stances'] ?? NULL)
      ? array_values($character_data['stance_state']['active_stances'])
      : [];
    $character_data['stance_state']['active_stances'] = array_values(array_filter($active, static function ($entry) use ($stance_id): bool {
      return !is_array($entry) || trim((string) ($entry['stance_id'] ?? '')) !== $stance_id;
    }));
    $character_data['stance_state']['updated_at'] = date('c');

    $this->persistStanceProjection($character_data, $stance_id, 'stance_exited', $source, $campaign_id, $entity_ref);

    return $character_data;
  }

  /**
   * Force-terminate all active stances in a normalized character-data payload.
   *
   * @param array<string, mixed> $character_data
   * @param array<string, mixed> $source
   *
   * @return array<string, mixed>
   *   Updated character data.
   */
  public function clearAllStances(
    array $character_data,
    array $source = [],
    int $campaign_id = 0,
    string $entity_ref = ''
  ): array {
    $character_data['stance_state'] = is_array($character_data['stance_state'] ?? NULL) ? $character_data['stance_state'] : [];
    $character_data['stance_state']['active_stances'] = [];
    $character_data['stance_state']['updated_at'] = date('c');
    $this->persistStanceProjection($character_data, 'all', 'stance_forced_termination', $source, $campaign_id, $entity_ref);
    return $character_data;
  }

  /**
   * Persist stance state and event projections when campaign/entity context exists.
   *
   * @param array<string, mixed> $character_data
   * @param array<string, mixed> $source
   */
  protected function persistStanceProjection(
    array $character_data,
    string $stance_id,
    string $event_type,
    array $source,
    int $campaign_id,
    string $entity_ref
  ): void {
    $entity_ref = trim($entity_ref);
    if ($campaign_id <= 0 || $entity_ref === '') {
      return;
    }

    $summary = [
      'active_stances' => is_array($character_data['stance_state']['active_stances'] ?? NULL)
        ? array_values($character_data['stance_state']['active_stances'])
        : [],
      'max_active_stances' => max(1, (int) ($character_data['stance_state']['max_active_stances'] ?? 1)),
      'updated_at' => (string) ($character_data['stance_state']['updated_at'] ?? date('c')),
      'arcane_cascade_active' => $this->isStanceActive($character_data, 'arcane_cascade'),
    ];
    $this->stanceStateStoreService->storeLatestState($campaign_id, $entity_ref, $summary, [
      'source_type' => (string) ($source['source_type'] ?? 'command'),
      'source_id' => (string) ($source['source_id'] ?? ''),
    ]);
    $this->stanceEventStoreService->recordStanceEvent($campaign_id, $entity_ref, [
      'event_type' => $event_type,
      'stance_id' => $stance_id,
      'summary' => $summary,
      'context' => $source,
    ]);
  }

}
