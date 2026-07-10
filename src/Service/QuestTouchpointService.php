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
    $objective_id_hint = trim((string) ($touchpoint['objective_id'] ?? ''));
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
    if ($candidates === []) {
      $room_id = trim((string) ($touchpoint['room_id'] ?? $touchpoint['location_id'] ?? ''));
      if ($room_id !== '') {
        $offered_quests = $this->questTracker->getOfferQuests($campaign_id, $room_id, $character_id);
        $offered_candidates = $this->findObjectiveCandidates($offered_quests, $touchpoint, $objective_type);
        if ($offered_candidates !== []) {
          foreach ($offered_candidates as &$candidate) {
            $candidate['requires_start'] = TRUE;
          }
          unset($candidate);
          $candidates = $offered_candidates;
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
      $started_quests = [];
      foreach ($candidates as $candidate) {
        $progress_character_id = (int) ($candidate['progress_character_id'] ?? 0);
        if ($progress_character_id <= 0) {
          $progress_character_id = $character_id;
        }

        if (!empty($candidate['requires_start'])) {
          $started = $this->questTracker->startQuest($campaign_id, (string) $candidate['quest_id'], $character_id);
          if (!$started) {
            return [
              'success' => FALSE,
              'decision' => 'NO_ACTION',
              'error' => sprintf('Failed to start offered quest "%s" before applying touchpoint progress.', (string) $candidate['quest_id']),
            ];
          }
          if ($this->shouldDeferStartedQuestObjectiveProgress($touchpoint, $objective_id_hint)) {
            $started_quests[] = [
              'quest_id' => (string) $candidate['quest_id'],
              'objective_id' => (string) $candidate['objective_id'],
            ];
            continue;
          }
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

      if ($applied_objectives === [] && $started_quests !== []) {
        return [
          'success' => TRUE,
          'decision' => 'STARTED_QUEST',
          'requires_confirmation' => FALSE,
          'started_quests' => $started_quests,
          'reason' => 'Offered quest started; explicit follow-up interaction is required before objective progress is recorded.',
        ];
      }

      return [
        'success' => TRUE,
        'decision' => 'APPLY_PROGRESS',
        'requires_confirmation' => FALSE,
        'applied_objectives' => $applied_objectives,
        'started_quests' => $started_quests,
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

    if (!empty($match['requires_start'])) {
      $started = $this->questTracker->startQuest($campaign_id, (string) $match['quest_id'], $character_id);
      if (!$started) {
        return [
          'success' => FALSE,
          'decision' => 'NO_ACTION',
          'error' => 'Failed to start offered quest before applying touchpoint progress',
        ];
      }
      if ($this->shouldDeferStartedQuestObjectiveProgress($touchpoint, $objective_id_hint)) {
        $this->fingerprintStore->set($fingerprint, [
          'campaign_id' => $campaign_id,
          'character_id' => $character_id,
          'quest_id' => $match['quest_id'],
          'objective_id' => $match['objective_id'],
          'applied_at' => $this->time->getRequestTime(),
        ]);

        return [
          'success' => TRUE,
          'decision' => 'STARTED_QUEST',
          'requires_confirmation' => FALSE,
          'quest_id' => $match['quest_id'],
          'objective_id' => $match['objective_id'],
          'reason' => 'Offered quest started; explicit follow-up interaction is required before objective progress is recorded.',
        ];
      }
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
   * Require explicit objective resolution after direct dialogue starts an offered quest.
   */
  protected function shouldDeferStartedQuestObjectiveProgress(array $touchpoint, string $objective_id_hint): bool {
    if ($objective_id_hint !== '') {
      return FALSE;
    }

    $matching_mode = strtolower(trim((string) ($touchpoint['matching_mode'] ?? '')));
    return $matching_mode === 'direct_npc_dialogue';
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
          'progress_character_id' => (int) ($quest['character_id'] ?? 0),
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
