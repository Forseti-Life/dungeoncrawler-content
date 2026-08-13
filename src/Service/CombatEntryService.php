<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical combat-entry authority for room-to-encounter transitions.
 */
class CombatEntryService {

  protected GameCoordinatorService $gameCoordinator;
  protected ActorContextProjectionService $actorContextProjectionService;
  protected AggressionEventStoreService $aggressionEventStoreService;
  protected AggressionStateStoreService $aggressionStateStoreService;

  public function __construct(
    GameCoordinatorService $game_coordinator,
    ActorContextProjectionService $actor_context_projection_service,
    AggressionEventStoreService $aggression_event_store_service,
    AggressionStateStoreService $aggression_state_store_service
  ) {
    $this->gameCoordinator = $game_coordinator;
    $this->actorContextProjectionService = $actor_context_projection_service;
    $this->aggressionEventStoreService = $aggression_event_store_service;
    $this->aggressionStateStoreService = $aggression_state_store_service;
  }

  /**
   * Start combat from a canonical-action room context.
   *
   * @param array<string, mixed> $combat
   *   combat payload from canonical action.
   * @param array<int, array<string, mixed>> $enemies
   *   Resolved enemy entities.
   * @param array<string, mixed> $policy
   *   Aggression-policy evaluation result.
   */
  public function requestCombatEntryFromCanonicalAction(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $combat,
    array $enemies,
    array $policy
  ): array {
    $aggression_summary = $this->actorContextProjectionService->buildAggressionSummary($policy);
    if (empty($policy['can_initiate_combat'])) {
      $blockers = is_array($policy['entry_blockers'] ?? NULL) ? $policy['entry_blockers'] : [];
      $combat_entry_summary = $this->actorContextProjectionService->buildCombatEntrySummary(
        'blocked',
        $campaign_id,
        $room_id,
        $policy
      );
      $this->aggressionEventStoreService->recordCombatEntryEvent($campaign_id, [
        'status' => 'blocked',
        'room_id' => $room_id,
        'reason' => (string) ($combat_entry_summary['aggression']['reason'] ?? ''),
        'aggression' => $combat_entry_summary['aggression'] ?? [],
        'enemy_count' => count($enemies),
      ]);
      $this->aggressionStateStoreService->storeLatestState(
        $campaign_id,
        $room_id,
        'blocked',
        is_array($aggression_summary) ? $aggression_summary : [],
        is_array($combat_entry_summary) ? $combat_entry_summary : []
      );
      return [
        'success' => FALSE,
        'error' => $blockers !== []
          ? 'Combat entry blocked by aggression policy: ' . implode(', ', array_map('strval', $blockers))
          : 'Combat entry blocked by aggression policy.',
        'aggression_summary' => $aggression_summary,
        'combat_entry_summary' => $combat_entry_summary,
      ];
    }

    if ($enemies === []) {
      $combat_entry_summary = $this->actorContextProjectionService->buildCombatEntrySummary(
        'blocked',
        $campaign_id,
        $room_id,
        $policy
      );
      $this->aggressionEventStoreService->recordCombatEntryEvent($campaign_id, [
        'status' => 'blocked',
        'room_id' => $room_id,
        'reason' => 'Combat entry requires at least one resolved enemy target.',
        'aggression' => $combat_entry_summary['aggression'] ?? [],
        'enemy_count' => 0,
      ]);
      $this->aggressionStateStoreService->storeLatestState(
        $campaign_id,
        $room_id,
        'blocked',
        is_array($aggression_summary) ? $aggression_summary : [],
        is_array($combat_entry_summary) ? $combat_entry_summary : []
      );
      return [
        'success' => FALSE,
        'error' => 'Combat entry requires at least one resolved enemy target.',
        'aggression_summary' => $aggression_summary,
        'combat_entry_summary' => $combat_entry_summary,
      ];
    }

    $result = $this->gameCoordinator->startCombatEncounter($campaign_id, [
      'reason' => (string) ($combat['reason'] ?? $policy['reason'] ?? 'Combat begins.'),
      'encounter_context' => [
        'room_id' => $room_id,
        'room_name' => $room_meta['name'] ?? $room_id,
        'enemies' => $enemies,
        'source_event_type' => 'canonical_combat_initiation',
        'aggression_policy' => $policy,
      ],
    ]);

    if (empty($result['success'])) {
      $combat_entry_summary = $this->actorContextProjectionService->buildCombatEntrySummary(
        'failed',
        $campaign_id,
        $room_id,
        $policy,
        $result
      );
      $this->aggressionEventStoreService->recordCombatEntryEvent($campaign_id, [
        'status' => 'failed',
        'room_id' => $room_id,
        'reason' => (string) ($result['error'] ?? 'Combat could not be started.'),
        'aggression' => $combat_entry_summary['aggression'] ?? [],
        'enemy_count' => count($enemies),
        'encounter_id' => (int) ($combat_entry_summary['encounter_id'] ?? 0),
      ]);
      $this->aggressionStateStoreService->storeLatestState(
        $campaign_id,
        $room_id,
        'failed',
        is_array($aggression_summary) ? $aggression_summary : [],
        is_array($combat_entry_summary) ? $combat_entry_summary : []
      );
      return [
        'success' => FALSE,
        'error' => (string) ($result['error'] ?? 'Combat could not be started.'),
        'transition' => $result,
        'aggression_summary' => $aggression_summary,
        'combat_entry_summary' => $combat_entry_summary,
      ];
    }

    $runtime_snapshot = $this->gameCoordinator->getRuntimeReadState($campaign_id);
    $combat_entry_summary = $this->actorContextProjectionService->buildCombatEntrySummary(
      'entered',
      $campaign_id,
      $room_id,
      $policy,
      $result,
      is_array($runtime_snapshot) ? $runtime_snapshot : []
    );
    $this->aggressionEventStoreService->recordCombatEntryEvent($campaign_id, [
      'status' => 'entered',
      'room_id' => $room_id,
      'reason' => (string) ($combat_entry_summary['aggression']['reason'] ?? ''),
      'aggression' => $combat_entry_summary['aggression'] ?? [],
      'enemy_count' => count($enemies),
      'encounter_id' => (int) ($combat_entry_summary['encounter_id'] ?? 0),
    ]);
    $this->aggressionStateStoreService->storeLatestState(
      $campaign_id,
      $room_id,
      'entered',
      is_array($aggression_summary) ? $aggression_summary : [],
      is_array($combat_entry_summary) ? $combat_entry_summary : []
    );
    return [
      'success' => TRUE,
      'transition' => $result,
      'runtime_snapshot' => $runtime_snapshot,
      'policy' => $policy,
      'aggression_summary' => $aggression_summary,
      'combat_entry_summary' => $combat_entry_summary,
    ];
  }

}
