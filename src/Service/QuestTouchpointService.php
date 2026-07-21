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
  protected StorylineQuestLifecycleService $storylineQuestLifecycleService;

  /**
   * Constructor.
   */
  public function __construct(
    QuestTrackerService $quest_tracker,
    QuestConfirmationService $confirmation_service,
    KeyValueFactoryInterface $key_value_factory,
    TimeInterface $time,
    StorylineQuestLifecycleService $storyline_quest_lifecycle_service
  ) {
    $this->questTracker = $quest_tracker;
    $this->confirmationService = $confirmation_service;
    $this->fingerprintStore = $key_value_factory->get('dungeoncrawler_content.quest_touchpoint_fingerprints');
    $this->time = $time;
    $this->storylineQuestLifecycleService = $storyline_quest_lifecycle_service;
  }

  /**
   * Ingest a canonical quest touchpoint event.
   */
  public function ingestEvent(int $campaign_id, array $payload): array {
    $touchpoint = $this->resolveTouchpointPayload($payload);
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

    $room_id = trim((string) ($touchpoint['room_id'] ?? $touchpoint['location_id'] ?? ''));
    if ($room_id !== '') {
      $this->activateOpenRoomQuests($campaign_id, $character_id, $room_id);
    }

    $active_quests = $this->questTracker->getActiveQuests($campaign_id, $character_id);
    $candidates = $this->findObjectiveCandidates($active_quests, $touchpoint, $objective_type);
    if ($candidates === []) {
      if ($room_id !== '') {
        $open_quests = $this->loadOpenRoomQuests($campaign_id, $room_id);
        $open_candidates = $this->findObjectiveCandidates($open_quests, $touchpoint, $objective_type);
        if ($open_candidates !== []) {
          $started_quest_ids = $this->activateOpenQuestCandidates($campaign_id, $character_id, $open_candidates);
          if ($started_quest_ids === []) {
            return [
              'success' => TRUE,
              'decision' => 'NO_ACTION',
              'reason' => 'Matching objective belongs to an open quest, but activation failed.',
            ];
          }

          $candidates = array_values(array_filter(
            $open_candidates,
            static fn(array $candidate): bool => in_array((string) ($candidate['quest_id'] ?? ''), $started_quest_ids, TRUE)
          ));

          if ($candidates !== []) {
            $active_quests = $this->questTracker->getActiveQuests($campaign_id, $character_id);
            $active_candidates = $this->findObjectiveCandidates($active_quests, $touchpoint, $objective_type);
            if ($active_candidates !== []) {
              $candidates = $active_candidates;
            }
          }
        }
        else {
          return [
            'success' => TRUE,
            'decision' => 'NO_ACTION',
            'reason' => 'No active objective matched touchpoint',
          ];
        }
      }
    }

    if (empty($candidates)) {
      return [
        'success' => TRUE,
        'decision' => 'NO_ACTION',
        'reason' => 'No active objective matched touchpoint',
      ];
    }

    if (!$this->isDeterministicTouchpoint($touchpoint)) {
      $confirmation = $this->confirmationService->createPending(
        $campaign_id,
        $character_id,
        $payload,
        $candidates,
        'Text-only quest touchpoint requires confirmation before applying progress.'
      );

      return [
        'success' => TRUE,
        'decision' => 'REQUEST_CONFIRMATION',
        'requires_confirmation' => TRUE,
        'confirmation_id' => $confirmation['confirmation_id'],
        'candidates' => $confirmation['candidates'],
        'reason' => 'Text-only quest touchpoint requires confirmation before applying progress.',
      ];
    }

    $confidence = strtolower((string) ($touchpoint['confidence'] ?? 'high'));
    if (
      count($candidates) > 1
      && $this->shouldAutoApplyAllInteractCandidates($touchpoint, $candidates, $objective_type, $confidence)
    ) {
      $applied_objectives = [];
      foreach ($candidates as $candidate) {
        $progress_character_id = (int) ($candidate['progress_character_id'] ?? 0);
        if ($progress_character_id <= 0) {
          $progress_character_id = $character_id;
        }

        $result = $this->questTracker->updateObjectiveProgress(
          $campaign_id,
          (string) $candidate['quest_id'],
          (string) $candidate['objective_id'],
          max(1, (int) ($touchpoint['quantity'] ?? $touchpoint['amount'] ?? 1)),
          $progress_character_id
        );

        if (empty($result['success'])) {
          return [
            'success' => FALSE,
            'decision' => 'NO_ACTION',
            'error' => (string) ($result['error'] ?? 'Failed to apply quest progress'),
          ];
        }

        $objective_fingerprint = sha1($fingerprint . '|' . strtolower((string) ($candidate['objective_id'] ?? '')));
        $this->fingerprintStore->set($objective_fingerprint, [
          'campaign_id' => $campaign_id,
          'character_id' => $character_id,
          'quest_id' => $candidate['quest_id'],
          'objective_id' => $candidate['objective_id'],
          'applied_at' => $this->time->getRequestTime(),
        ]);

        $applied_objectives[] = [
          'quest_id' => (string) $candidate['quest_id'],
          'objective_id' => (string) $candidate['objective_id'],
        ];
      }

      $this->fingerprintStore->set($fingerprint, [
        'campaign_id' => $campaign_id,
        'character_id' => $character_id,
        'objective_ids' => array_column($applied_objectives, 'objective_id'),
        'applied_at' => $this->time->getRequestTime(),
      ]);

      return [
        'success' => TRUE,
        'decision' => 'APPLY_PROGRESS',
        'requires_confirmation' => FALSE,
        'applied_objectives' => $applied_objectives,
      ];
    }

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
    $progress_character_id = (int) ($match['progress_character_id'] ?? 0);
    if ($progress_character_id <= 0) {
      $progress_character_id = $character_id;
    }

    $result = $this->questTracker->updateObjectiveProgress(
      $campaign_id,
      (string) $match['quest_id'],
      (string) $match['objective_id'],
      $amount,
      $progress_character_id
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
   * Allow deterministic multi-objective hand-ins for direct NPC interactions.
   */
  protected function shouldAutoApplyAllInteractCandidates(array $touchpoint, array $candidates, string $objective_type, string $confidence): bool {
    if ($objective_type !== 'interact' || $confidence !== 'high') {
      return FALSE;
    }

    $matching_mode = strtolower(trim((string) ($touchpoint['matching_mode'] ?? '')));
    if (!in_array($matching_mode, ['direct_npc_dialogue', 'typed_receipt'], TRUE)) {
      return FALSE;
    }

    foreach ($candidates as $candidate) {
      if (strtolower((string) ($candidate['objective_type'] ?? '')) !== 'interact') {
        return FALSE;
      }
      if (trim((string) ($candidate['objective_id'] ?? '')) === '') {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Resolve the effective touchpoint payload, preferring typed receipts.
   */
  protected function resolveTouchpointPayload(array $payload): array {
    $touchpoint = is_array($payload['touchpoint'] ?? NULL) ? $payload['touchpoint'] : $payload;
    $receipt_touchpoint = $this->extractDeterministicReceiptTouchpoint($payload);
    if ($receipt_touchpoint !== []) {
      $touchpoint = $this->mergeTouchpointPayloads($touchpoint, $receipt_touchpoint);
    }

    if (empty($touchpoint['matching_mode'])) {
      $touchpoint['matching_mode'] = !empty($touchpoint['objective_id']) ? 'typed_receipt' : 'text_inference';
    }

    return $touchpoint;
  }

  /**
   * Extract a deterministic quest touchpoint from known receipt envelopes.
   */
  protected function extractDeterministicReceiptTouchpoint(array $payload): array {
    $receipt_candidates = [];
    foreach (['receipt', 'runtime_receipt', 'room_receipt'] as $receipt_key) {
      if (is_array($payload[$receipt_key] ?? NULL)) {
        $receipt_candidates[] = $payload[$receipt_key];
      }
    }
    if (is_array($payload['receipts'] ?? NULL)) {
      foreach ($payload['receipts'] as $receipt) {
        if (is_array($receipt)) {
          $receipt_candidates[] = $receipt;
        }
      }
    }

    foreach ($receipt_candidates as $receipt) {
      $touchpoint = is_array($receipt['touchpoint'] ?? NULL) ? $receipt['touchpoint'] : [];
      if ($touchpoint !== []) {
        $touchpoint['matching_mode'] = 'typed_receipt';
        return $touchpoint;
      }

      $route = strtolower(trim((string) ($receipt['route'] ?? '')));
      $tool = strtolower(trim((string) ($receipt['tool'] ?? '')));
      $schema = strtolower(trim((string) ($receipt['schema_version'] ?? $receipt['receipt_schema'] ?? $receipt['schema'] ?? '')));
      if (
        !in_array($route, ['quest_progression'], TRUE)
        && !in_array($tool, ['quest_turn_in'], TRUE)
        && !in_array($schema, ['quest_progress_receipt', 'quest-touchpoint-receipt-v1'], TRUE)
      ) {
        continue;
      }

      $resolved_arguments = is_array($receipt['resolved_arguments'] ?? NULL) ? $receipt['resolved_arguments'] : [];
      $quest = is_array($resolved_arguments['quest'] ?? NULL) ? $resolved_arguments['quest'] : [];
      if ($quest === [] && is_array($resolved_arguments['details']['quest'] ?? NULL)) {
        $quest = $resolved_arguments['details']['quest'];
      }
      $execution = is_array($receipt['execution'] ?? NULL) ? $receipt['execution'] : [];

      $receipt_touchpoint = [
        'objective_type' => (string) ($quest['objective_type'] ?? $execution['objective_type'] ?? ''),
        'objective_id' => (string) ($execution['objective_id'] ?? $quest['objective_id'] ?? ''),
        'item_ref' => (string) ($quest['item_ref'] ?? ''),
        'npc_ref' => (string) ($quest['npc_ref'] ?? ''),
        'entity_ref' => (string) ($quest['entity_ref'] ?? $quest['npc_ref'] ?? $quest['item_ref'] ?? ''),
        'quantity' => (int) ($quest['quantity'] ?? $execution['progress_delta'] ?? 1),
        'room_id' => (string) ($quest['room_id'] ?? $receipt['room_id'] ?? ''),
        'confidence' => 'high',
        'matching_mode' => 'typed_receipt',
      ];

      if ($receipt_touchpoint['objective_type'] !== '' || $receipt_touchpoint['objective_id'] !== '') {
        return $receipt_touchpoint;
      }
    }

    return [];
  }

  /**
   * Merge touchpoint payloads without dropping meaningful base values.
   */
  protected function mergeTouchpointPayloads(array $base, array $override): array {
    foreach ($override as $key => $value) {
      if ($value === NULL) {
        continue;
      }
      if (is_string($value) && trim($value) === '') {
        continue;
      }
      $base[$key] = $value;
    }

    return $base;
  }

  /**
   * Determine whether the touchpoint came from a deterministic typed receipt.
   */
  protected function isDeterministicTouchpoint(array $touchpoint): bool {
    $matching_mode = strtolower(trim((string) ($touchpoint['matching_mode'] ?? '')));
    if (in_array($matching_mode, ['typed_receipt', 'runtime_receipt', 'room_receipt', 'canonical_receipt', 'direct_npc_dialogue'], TRUE)) {
      return TRUE;
    }

    return trim((string) ($touchpoint['objective_id'] ?? '')) !== '';
  }

  /**
   * Start offered quests for matched candidates and return started quest IDs.
   *
   * @return array<int, string>
   *   Started quest IDs.
   */
  protected function activateOpenQuestCandidates(int $campaign_id, int $character_id, array $open_candidates): array {
    $candidates_by_quest_id = [];
    foreach ($open_candidates as $candidate) {
      if (!is_array($candidate)) {
        continue;
      }
      $quest_id = trim((string) ($candidate['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      if (!array_key_exists($quest_id, $candidates_by_quest_id)) {
        $candidates_by_quest_id[$quest_id] = $candidate;
      }
    }

    $started = [];
    foreach ($candidates_by_quest_id as $quest_id => $candidate) {
      $status = strtolower(trim((string) ($candidate['quest_status'] ?? '')));
      if (in_array($status, ['lead', 'available'], TRUE)) {
        $this->storylineQuestLifecycleService->setQuestStatusByQuestId(
          $campaign_id,
          $quest_id,
          'offered',
          [$status]
        );
      }
      if ($this->questTracker->startQuest($campaign_id, $quest_id, $character_id)) {
        $started[] = $quest_id;
      }
    }

    return $started;
  }

  /**
   * Build dedupe fingerprint for the touchpoint.
   */
  protected function buildFingerprint(int $campaign_id, int $character_id, array $touchpoint, int $occurred_at): string {
    $objective_id = (string) ($touchpoint['objective_id'] ?? '');
    $entity_ref = (string) ($touchpoint['entity_ref'] ?? $touchpoint['item_ref'] ?? $touchpoint['npc_ref'] ?? '');
    $room_id = (string) ($touchpoint['room_id'] ?? $touchpoint['location_id'] ?? '');
    $player_message = trim((string) ($touchpoint['player_message'] ?? $touchpoint['message'] ?? ''));
    $bucket = (int) floor($occurred_at / 30);
    $parts = [
      (string) $campaign_id,
      (string) $character_id,
      strtolower($objective_id),
      strtolower($entity_ref),
      strtolower($room_id),
      (string) $bucket,
    ];
    if ($player_message !== '') {
      $parts[] = strtolower((string) preg_replace('/\s+/', ' ', $player_message));
    }

    return sha1(implode('|', $parts));
  }

  /**
   * Find objective candidates that match the touchpoint.
   */
  protected function findObjectiveCandidates(array $active_quests, array $touchpoint, string $objective_type): array {
    $matches = [];
    $objective_id_hint = (string) ($touchpoint['objective_id'] ?? '');
    $item_ref = $this->normalizeToken((string) ($touchpoint['item_ref'] ?? $touchpoint['entity_ref'] ?? ''));
    $npc_tokens = $this->buildTouchpointNpcTokens($touchpoint);

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
          $npc_aliases = $this->buildNpcObjectiveAliases($objective);

          $item_match = $item_ref === '' || $target_item === '' || str_contains($item_ref, $target_item) || str_contains($target_item, $item_ref);
          $npc_match = $npc_tokens === [] || $this->matchesAnyNpcAlias($npc_tokens, $target_npc, $npc_aliases);

          if (!$item_match || !$npc_match) {
            continue;
          }
        }

        $matches[] = [
          'quest_id' => $quest_id,
          'quest_name' => $quest_name,
          'objective_id' => $candidate_objective_id,
          'objective_type' => $candidate_type,
          'quest_status' => strtolower((string) ($quest['status'] ?? '')),
          'progress_character_id' => (int) ($quest['character_id'] ?? 0),
          'label' => (string) ($objective['description'] ?? $candidate_objective_id),
        ];
      }

    }

    return $matches;
  }

  /**
   * Load open room quests that should be treated as activatable.
   *
   * @return array<int, array<string, mixed>>
   *   Open room quest rows.
   */
  protected function loadOpenRoomQuests(int $campaign_id, string $room_id): array {
    if ($campaign_id <= 0 || trim($room_id) === '') {
      return [];
    }
    $room_id = trim($room_id);
    $status_allowlist = ['active', 'ready_for_turn_in', 'offered', 'lead', 'available'];
    return array_values(array_filter(
      $this->questTracker->getCampaignQuestTracking($campaign_id),
      function (array $quest) use ($room_id, $status_allowlist): bool {
        if (!is_array($quest)) {
          return FALSE;
        }
        if (!$this->questTargetsRoom($quest, $room_id)) {
          return FALSE;
        }
        if (!empty($quest['completed_at'])) {
          return FALSE;
        }
        return in_array(strtolower(trim((string) ($quest['status'] ?? ''))), $status_allowlist, TRUE);
      }
    ));
  }

  /**
   * Determine whether a quest is anchored to the provided room.
   */
  protected function questTargetsRoom(array $quest, string $room_id): bool {
    $room_id = trim($room_id);
    if ($room_id === '') {
      return FALSE;
    }

    if (trim((string) ($quest['location_id'] ?? '')) === $room_id) {
      return TRUE;
    }

    foreach ($this->extractQuestObjectivePhases($quest) as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      foreach ($this->extractObjectivesFromPhase($phase) as $objective) {
        if (!is_array($objective)) {
          continue;
        }
        if (trim((string) ($objective['location_id'] ?? '')) === $room_id) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Extract objective phase rows from runtime or generated quest data.
   *
   * @return array<int, mixed>
   *   Objective phases.
   */
  protected function extractQuestObjectivePhases(array $quest): array {
    foreach (['objective_states', 'generated_objectives'] as $field) {
      $value = $quest[$field] ?? [];
      if (is_array($value) && $value !== []) {
        return $value;
      }
      if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, TRUE);
        if (is_array($decoded) && $decoded !== []) {
          return $decoded;
        }
      }
    }

    return [];
  }

  /**
   * Flatten phase objectives and nested child objective nodes.
   *
   * @return array<int, array<string, mixed>>
   *   Flat objective list.
   */
  protected function extractObjectivesFromPhase(array $phase): array {
    $stack = [];
    foreach ((array) ($phase['objectives'] ?? []) as $objective) {
      if (is_array($objective)) {
        $stack[] = $objective;
      }
    }

    $flattened = [];
    while ($stack !== []) {
      $objective = array_pop($stack);
      if (!is_array($objective)) {
        continue;
      }
      $flattened[] = $objective;
      foreach ((array) ($objective['children'] ?? []) as $child) {
        if (is_array($child)) {
          $stack[] = $child;
        }
      }
    }

    return $flattened;
  }

  /**
   * Promote and start all open room quests so journal state is active-aligned.
   */
  protected function activateOpenRoomQuests(int $campaign_id, int $character_id, string $room_id): void {
    if ($campaign_id <= 0 || $character_id <= 0 || trim($room_id) === '') {
      return;
    }
    foreach ($this->loadOpenRoomQuests($campaign_id, $room_id) as $quest) {
      if (!is_array($quest)) {
        continue;
      }
      $quest_id = trim((string) ($quest['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      $status = strtolower(trim((string) ($quest['status'] ?? '')));
      if (in_array($status, ['lead', 'available'], TRUE)) {
        $this->storylineQuestLifecycleService->setQuestStatusByQuestId(
          $campaign_id,
          $quest_id,
          'offered',
          [$status]
        );
      }
      $this->questTracker->startQuest($campaign_id, $quest_id, $character_id);
    }
  }

  /**
   * Return non-completed objectives for the quest's current phase.
   */
  protected function getActiveObjectivesForCurrentPhase(array $quest): array {
    $current_phase = (int) ($quest['current_phase'] ?? 1);
    if ($current_phase <= 0) {
      $current_phase = 1;
    }

    $phase_rows = [];

    $objective_states = $quest['objective_states'] ?? [];
    if (is_array($objective_states)) {
      $phase_rows = $objective_states;
    }
    elseif (is_string($objective_states) && trim($objective_states) !== '') {
      $decoded = json_decode($objective_states, TRUE);
      if (is_array($decoded)) {
        $phase_rows = $decoded;
      }
    }

    if ($phase_rows === []) {
      $generated_objectives = $quest['generated_objectives'] ?? [];
      if (is_array($generated_objectives)) {
        $phase_rows = $generated_objectives;
      }
      elseif (is_string($generated_objectives) && trim($generated_objectives) !== '') {
        $decoded = json_decode($generated_objectives, TRUE);
        if (is_array($decoded)) {
          $phase_rows = $decoded;
        }
      }
    }

    if (!is_array($phase_rows)) {
      return [];
    }

    foreach ($phase_rows as $phase) {
      if ((int) ($phase['phase'] ?? 0) !== $current_phase) {
        continue;
      }

      $objectives = is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [];
      $active = $this->collectActiveObjectives($objectives, TRUE);
      if ($active !== []) {
        return $active;
      }
      return $this->collectActiveObjectives($objectives, FALSE);
    }

    return [];
  }

  /**
   * Flatten active leaf objectives from a nested current-phase objective tree.
   *
   * @return array<int, array<string, mixed>>
   *   Active objectives.
   */
  protected function collectActiveObjectives(array $objectives, bool $require_revealed = TRUE): array {
    $active = [];
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }

      if ($require_revealed && !$this->isObjectiveCurrentlyRevealed($objective)) {
        continue;
      }

      $children = is_array($objective['children'] ?? NULL) ? $objective['children'] : [];
      if ($children !== []) {
        $active = array_merge($active, $this->collectActiveObjectives($children, $require_revealed));
      }

      if ($children !== []) {
        continue;
      }
      if (!empty($objective['completed'])) {
        continue;
      }

      $target_count = (int) ($objective['target_count'] ?? 0);
      $current = (int) ($objective['current'] ?? 0);
      if ($target_count > 0 && $current >= $target_count) {
        continue;
      }

      $active[] = $objective;
    }

    return array_values($active);
  }

  /**
   * Determine whether an objective is currently available to runtime matching.
   */
  protected function isObjectiveCurrentlyRevealed(array $objective): bool {
    return !array_key_exists('revealed', $objective) || !empty($objective['revealed']) || !empty($objective['completed']);
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

  /**
   * Build loose NPC aliases from the objective payload.
   *
   * @return array<int, string>
   *   Normalized aliases.
   */
  protected function buildNpcObjectiveAliases(array $objective): array {
    $aliases = [
      $this->normalizeToken((string) ($objective['target'] ?? '')),
      $this->normalizeToken((string) ($objective['npc_ref'] ?? '')),
      $this->normalizeToken((string) ($objective['objective_id'] ?? '')),
    ];

    $target = $this->normalizeToken((string) ($objective['target'] ?? ''));
    if ($target !== '') {
      $aliases = array_merge($aliases, $this->expandNpcReferenceAliases($target));
    }

    $description = trim((string) ($objective['description'] ?? ''));
    if ($description !== '' && preg_match('/speak to ([^.]+?)(?: and|\.|$)/i', $description, $matches) === 1) {
      $aliases[] = $this->normalizeToken($matches[1]);
    }
    if ($description !== '' && preg_match('/(?:return|give|hand)\b.+?\bto\s+(?:the\s+)?([^.,]+?)(?:\s+in\b|\s+after\b|\s+and\b|\.|$)/i', $description, $matches) === 1) {
      $aliases[] = $this->normalizeToken($matches[1]);
    }

    return array_values(array_unique(array_filter($aliases)));
  }

  /**
   * Build all NPC reference tokens from a touchpoint.
   *
   * @return array<int, string>
   *   Normalized NPC reference tokens.
   */
  protected function buildTouchpointNpcTokens(array $touchpoint): array {
    $tokens = [];
    foreach (['npc_ref', 'entity_ref'] as $field) {
      $token = $this->normalizeToken((string) ($touchpoint[$field] ?? ''));
      if ($token === '') {
        continue;
      }
      $tokens[] = $token;
      $tokens = array_merge($tokens, $this->expandNpcReferenceAliases($token));
    }
    return array_values(array_unique(array_filter($tokens)));
  }

  /**
   * Expand common NPC id/reference forms into comparable aliases.
   *
   * @return array<int, string>
   *   Normalized aliases.
   */
  protected function expandNpcReferenceAliases(string $token): array {
    $token = $this->normalizeToken($token);
    if ($token === '') {
      return [];
    }
    $aliases = [$token];
    $without_npc = trim((string) preg_replace('/\bnpc\b/', ' ', $token));
    $without_npc = $this->normalizeToken($without_npc);
    if ($without_npc !== '') {
      $aliases[] = $without_npc;
    }
    return array_values(array_unique(array_filter($aliases)));
  }

  /**
   * Determine whether a touchpoint NPC token matches any objective alias.
   */
  protected function matchesAnyNpcAlias(array $npc_tokens, string $target_npc, array $npc_aliases): bool {
    foreach ($npc_tokens as $npc_token) {
      if ($this->matchesNpcAlias($npc_token, $target_npc, $npc_aliases)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determine whether one touchpoint NPC token matches any objective alias.
   */
  protected function matchesNpcAlias(string $npc_ref, string $target_npc, array $npc_aliases): bool {
    if ($target_npc === '' && $npc_aliases === []) {
      return TRUE;
    }

    $candidates = array_values(array_unique(array_filter(array_merge([$target_npc], $npc_aliases))));
    foreach ($candidates as $candidate) {
      if (str_contains($npc_ref, $candidate) || str_contains($candidate, $npc_ref)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
