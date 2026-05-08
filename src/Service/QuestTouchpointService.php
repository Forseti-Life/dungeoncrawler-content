<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Ingests quest touchpoint events and applies quest progress decisions.
 */
class QuestTouchpointService {

  /**
   * @var \Drupal\dungeoncrawler_content\Service\QuestTrackerService
   */
  protected QuestTrackerService $questTracker;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\QuestConfirmationService
   */
  protected QuestConfirmationService $confirmationService;

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $fingerprintStore;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructor.
   */
  public function __construct(
    QuestTrackerService $quest_tracker,
    QuestConfirmationService $confirmation_service,
    KeyValueFactoryInterface $key_value_factory,
    TimeInterface $time
  ) {
    $this->questTracker = $quest_tracker;
    $this->confirmationService = $confirmation_service;
    $this->fingerprintStore = $key_value_factory->get('dungeoncrawler_content.quest_touchpoint_fingerprints');
    $this->time = $time;
  }

  /**
   * Ingest a canonical quest touchpoint event.
   */
  public function ingestEvent(int $campaign_id, array $payload): array {
    $touchpoint = is_array($payload['touchpoint'] ?? NULL) ? $payload['touchpoint'] : $payload;
    $character_id = (int) ($payload['character_id'] ?? $touchpoint['character_id'] ?? 0);

    if ($character_id <= 0) {
      return [
        'success' => FALSE,
        'decision' => 'NO_ACTION',
        'error' => 'character_id is required',
      ];
    }

    $objective_type = strtolower((string) ($touchpoint['objective_type'] ?? ''));
    if ($objective_type === '') {
      return [
        'success' => FALSE,
        'decision' => 'NO_ACTION',
        'error' => 'touchpoint.objective_type is required',
      ];
    }

    $occurred_at = (int) ($payload['occurred_at'] ?? $touchpoint['occurred_at'] ?? $this->time->getRequestTime());
    $fingerprint = $this->buildFingerprint($campaign_id, $character_id, $touchpoint, $occurred_at);

    if ($this->fingerprintStore->get($fingerprint)) {
      return [
        'success' => TRUE,
        'decision' => 'NO_ACTION',
        'duplicate' => TRUE,
        'reason' => 'Duplicate touchpoint suppressed',
      ];
    }

    $active_quests = $this->questTracker->getActiveQuests($campaign_id, $character_id);
    $candidates = $this->findObjectiveCandidates($active_quests, $touchpoint, $objective_type);

    if (empty($candidates)) {
      return [
        'success' => TRUE,
        'decision' => 'NO_ACTION',
        'reason' => 'No active objective matched touchpoint',
      ];
    }

    $confidence = strtolower((string) ($touchpoint['confidence'] ?? 'high'));
    if (count($candidates) > 1 || in_array($confidence, ['low', 'medium'], TRUE)) {
      $confirmation = $this->confirmationService->createPending(
        $campaign_id,
        $character_id,
        $payload,
        $candidates,
        'Ambiguous quest touchpoint. Confirm objective mapping before applying progress.'
      );

      return [
        'success' => TRUE,
        'decision' => 'REQUEST_CONFIRMATION',
        'requires_confirmation' => TRUE,
        'confirmation_id' => $confirmation['confirmation_id'],
        'candidates' => $confirmation['candidates'],
      ];
    }

    $match = $candidates[0];
    $amount = max(1, (int) ($touchpoint['quantity'] ?? $touchpoint['amount'] ?? 1));

    $result = $this->questTracker->updateObjectiveProgress(
      $campaign_id,
      (string) $match['quest_id'],
      (string) $match['objective_id'],
      $amount,
      $character_id
    );

    if (empty($result['success'])) {
      return [
        'success' => FALSE,
        'decision' => 'NO_ACTION',
        'error' => (string) ($result['error'] ?? 'Failed to apply quest progress'),
      ];
    }

    $this->fingerprintStore->set($fingerprint, [
      'campaign_id' => $campaign_id,
      'character_id' => $character_id,
      'quest_id' => $match['quest_id'],
      'objective_id' => $match['objective_id'],
      'applied_at' => $this->time->getRequestTime(),
    ]);

    return [
      'success' => TRUE,
      'decision' => 'APPLY_PROGRESS',
      'requires_confirmation' => FALSE,
      'quest_id' => $match['quest_id'],
      'objective_id' => $match['objective_id'],
      'progress_delta' => $amount,
      'objective_state' => $result,
    ];
  }

  /**
   * Build dedupe fingerprint for the touchpoint.
   */
  protected function buildFingerprint(int $campaign_id, int $character_id, array $touchpoint, int $occurred_at): string {
    $objective_id = (string) ($touchpoint['objective_id'] ?? '');
    $entity_ref = (string) ($touchpoint['entity_ref'] ?? $touchpoint['item_ref'] ?? $touchpoint['npc_ref'] ?? '');
    $room_id = (string) ($touchpoint['room_id'] ?? $touchpoint['location_id'] ?? '');
    $bucket = (int) floor($occurred_at / 30);

    return sha1(implode('|', [
      (string) $campaign_id,
      (string) $character_id,
      strtolower($objective_id),
      strtolower($entity_ref),
      strtolower($room_id),
      (string) $bucket,
    ]));
  }

  /**
   * Find objective candidates that match the touchpoint.
   */
  protected function findObjectiveCandidates(array $active_quests, array $touchpoint, string $objective_type): array {
    $matches = [];
    $objective_id_hint = (string) ($touchpoint['objective_id'] ?? '');
    $item_ref = $this->normalizeToken((string) ($touchpoint['item_ref'] ?? $touchpoint['entity_ref'] ?? ''));
    $npc_ref = $this->normalizeToken((string) ($touchpoint['npc_ref'] ?? $touchpoint['entity_ref'] ?? ''));

    foreach ($active_quests as $quest) {
      $quest_id = (string) ($quest['quest_id'] ?? '');
      if ($quest_id === '') {
        continue;
      }

      $quest_name = (string) ($quest['quest_name'] ?? $quest_id);
      $active_objectives = $this->getActiveObjectivesForCurrentPhase($quest);
      foreach ($active_objectives as $objective) {
        $candidate_objective_id = (string) ($objective['objective_id'] ?? '');
        if ($candidate_objective_id === '') {
          continue;
        }

        $candidate_type = strtolower((string) ($objective['type'] ?? ''));
        if ($candidate_type !== $objective_type) {
          continue;
        }

        if ($objective_id_hint !== '' && $objective_id_hint !== $candidate_objective_id) {
          continue;
        }

        if ($objective_id_hint === '' || $objective_id_hint !== $candidate_objective_id) {
          $target_item = $this->normalizeToken((string) ($objective['item'] ?? ''));
          $target_npc = $this->normalizeToken((string) ($objective['target'] ?? ''));

          $item_match = $item_ref === '' || $target_item === '' || str_contains($item_ref, $target_item) || str_contains($target_item, $item_ref);
          $npc_match = $npc_ref === '' || $target_npc === '' || str_contains($npc_ref, $target_npc) || str_contains($target_npc, $npc_ref);

          if (!$item_match || !$npc_match) {
            continue;
          }
        }

        $matches[] = [
          'quest_id' => $quest_id,
          'quest_name' => $quest_name,
          'objective_id' => $candidate_objective_id,
          'objective_type' => $candidate_type,
          'label' => (string) ($objective['description'] ?? $candidate_objective_id),
        ];
      }
    }

    return $matches;
  }

  /**
   * Return non-completed objectives for the quest's current phase.
   */
  protected function getActiveObjectivesForCurrentPhase(array $quest): array {
    $current_phase = (int) ($quest['current_phase'] ?? 1);
    if ($current_phase <= 0) {
      $current_phase = 1;
    }

    $phase_rows = json_decode((string) ($quest['objective_states'] ?? '[]'), TRUE);
    if (!is_array($phase_rows) || $phase_rows === []) {
      $phase_rows = json_decode((string) ($quest['generated_objectives'] ?? '[]'), TRUE);
    }

    if (!is_array($phase_rows)) {
      return [];
    }

    foreach ($phase_rows as $phase) {
      if ((int) ($phase['phase'] ?? 0) !== $current_phase) {
        continue;
      }

      $objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      return array_values(array_filter($objectives, static function (array $objective): bool {
        if (!empty($objective['completed'])) {
          return FALSE;
        }

        $target_count = (int) ($objective['target_count'] ?? 0);
        $current = (int) ($objective['current'] ?? 0);
        if ($target_count > 0 && $current >= $target_count) {
          return FALSE;
        }

        return TRUE;
      }));
    }

    return [];
  }

  /**
   * Normalize strings for loose matching.
   */
  protected function normalizeToken(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return (string) $value;
  }

}
