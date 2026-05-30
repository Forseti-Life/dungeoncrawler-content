<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Backfills deterministic institution memberships for existing campaign actors.
 */
class CampaignInstitutionBackfillService {

  protected const OVERRIDE_KEY = 'institution_review_overrides';

  public function __construct(
    protected Connection $database,
    protected InstitutionMembershipService $institutionMembership,
  ) {}

  /**
   * Backfills one campaign or all campaigns when no id is provided.
   *
   * @return array<string, int>
   *   Summary counts.
   */
  public function backfillCampaignActors(?int $campaign_id = NULL): array {
    if (!$this->isBackfillStorageReady()) {
      throw new \RuntimeException('Campaign institution backfill review storage is not installed.');
    }

    $summary = [
      'processed' => 0,
      'actors_backfilled' => 0,
      'memberships_synced' => 0,
      'review_rows' => 0,
      'skipped' => 0,
    ];

    foreach ($this->loadRuntimeActors($campaign_id) as $row) {
      $summary['processed']++;
      $analysis = $this->analyzeRuntimeActorRow($row);
      $summary['review_rows'] += $this->replaceReviewRows($analysis);

      if ($analysis['status'] !== 'backfillable') {
        $summary['skipped']++;
        continue;
      }

      $count = $this->institutionMembership->syncMemberships(
        (int) $analysis['campaign_id'],
        (string) $analysis['source_type'],
        (string) $analysis['source_id'],
        $analysis['institution_inputs']
      );

      $summary['actors_backfilled']++;
      $summary['memberships_synced'] += $count;
    }

    return $summary;
  }

  /**
   * Returns whether campaign review storage is installed.
   */
  public function isBackfillStorageReady(): bool {
    return $this->database->schema()->tableExists('dc_campaign_institution_backfill_review');
  }

  /**
   * Analyzes one runtime actor row.
   *
   * @return array<string, mixed>
   */
  public function analyzeRuntimeActorRow(array $row): array {
    $campaign_id = (int) ($row['campaign_id'] ?? 0);
    $actor_row_id = (int) ($row['id'] ?? 0);
    $instance_id = trim((string) ($row['instance_id'] ?? ''));
    $review_source_id = $instance_id !== '' ? $instance_id : ($actor_row_id > 0 ? 'campaign_row_' . $actor_row_id : '');
    $type = trim((string) ($row['type'] ?? 'pc'));
    $character_data = $this->decodeJsonArray($row['character_data'] ?? NULL);
    $state_data = $this->decodeJsonArray($row['state_data'] ?? NULL);
    $manual_overrides = $this->extractInstitutionOverrides($state_data);

    $payload = [];
    if ($type === 'pc') {
      $payload = $this->mergePayloadFallbacks($character_data, [
        'ancestry' => (string) ($row['ancestry'] ?? ''),
        'class' => (string) ($row['class'] ?? ''),
      ]);
      $institution_inputs = $this->institutionMembership->buildCharacterInstitutionInputs($payload, 'campaign_backfill');
      $source_type = 'campaign_character';
    }
    elseif ($type === 'npc') {
      $payload = $this->mergePayloadFallbacks($character_data, $state_data);
      $payload = $this->mergePayloadFallbacks($payload, [
        'ancestry' => (string) ($row['ancestry'] ?? ''),
        'class' => (string) ($row['class'] ?? ''),
      ]);
      $institution_inputs = $this->institutionMembership->buildNpcInstitutionInputs($payload, 'campaign_backfill');
      $source_type = 'campaign_npc';
    }
    else {
      $institution_inputs = [];
      $source_type = 'campaign_character';
    }

    $institution_inputs = $this->applyInstitutionOverrides($institution_inputs, $manual_overrides, 'campaign_review');

    $review_reasons = [];
    if (in_array($type, ['pc', 'npc'], TRUE)) {
      if ($this->extractNonEmptyString($payload, ['ancestry', 'species']) === '' && !$this->hasResolvedOverride($manual_overrides, 'missing_ancestry')) {
        $review_reasons[] = 'missing_ancestry';
      }

      $class_value = $this->extractNonEmptyString($payload, ['occupation', 'class']);
      if ($class_value !== '' && !$this->hasInstitutionDomain($institution_inputs, 'profession') && !$this->hasResolvedOverride($manual_overrides, 'ambiguous_profession_label')) {
        $review_reasons[] = 'ambiguous_profession_label';
      }
    }

    $status = 'skipped';
    if ($campaign_id > 0 && $review_source_id !== '' && in_array($type, ['pc', 'npc'], TRUE) && $institution_inputs !== []) {
      $status = 'backfillable';
    }
    elseif ($review_reasons !== []) {
      $status = 'review_required';
    }

    return [
      'campaign_id' => $campaign_id,
      'actor_row_id' => $actor_row_id,
      'actor_type' => $type,
      'source_type' => $source_type,
      'source_id' => $review_source_id,
      'status' => $status,
      'institution_inputs' => $institution_inputs,
      'review_reasons' => array_values(array_unique($review_reasons)),
      'details' => [
        'instance_id' => $instance_id,
        'name' => (string) ($row['name'] ?? ''),
        'candidate_fields' => [
          'ancestry' => $this->extractNonEmptyString($payload, ['ancestry', 'species']),
          'class' => $this->extractNonEmptyString($payload, ['class']),
          'occupation' => $this->extractNonEmptyString($payload, ['occupation']),
        ],
        'manual_overrides' => $manual_overrides,
      ],
    ];
  }

  /**
   * Refreshes one runtime actor after a review decision changes its source state.
   *
   * @return array<string, mixed>
   */
  public function refreshRuntimeActor(int $actor_row_id): array {
    if (!$this->isBackfillStorageReady()) {
      throw new \RuntimeException('Campaign institution backfill review storage is not installed.');
    }

    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'campaign_id', 'instance_id', 'type', 'name', 'ancestry', 'class', 'character_data', 'state_data'])
      ->condition('id', $actor_row_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      throw new \InvalidArgumentException(sprintf('Campaign runtime actor row %d was not found.', $actor_row_id));
    }

    $analysis = $this->analyzeRuntimeActorRow($row);
    $review_count = $this->replaceReviewRows($analysis);
    $membership_count = 0;

    if ($analysis['status'] === 'backfillable') {
      $membership_count = $this->institutionMembership->syncMemberships(
        (int) $analysis['campaign_id'],
        (string) $analysis['source_type'],
        (string) $analysis['source_id'],
        $analysis['institution_inputs']
      );
    }

    return [
      'status' => $analysis['status'],
      'review_rows' => $review_count,
      'memberships_synced' => $membership_count,
      'review_reasons' => $analysis['review_reasons'],
    ];
  }

  /**
   * Loads campaign runtime actors in PC/NPC scope.
   *
   * @return array<int, array<string, mixed>>
   */
  protected function loadRuntimeActors(?int $campaign_id): array {
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'campaign_id', 'instance_id', 'type', 'name', 'ancestry', 'class', 'character_data', 'state_data'])
      ->condition('campaign_id', 0, '>');

    if ($campaign_id !== NULL && $campaign_id > 0) {
      $query->condition('campaign_id', $campaign_id);
    }

    return $query
      ->condition('type', ['pc', 'npc'], 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Replaces review rows for one runtime actor.
   */
  protected function replaceReviewRows(array $analysis): int {
    if ((int) $analysis['campaign_id'] <= 0 || ($analysis['source_id'] ?? '') === '') {
      return 0;
    }

    $existing_rows = $this->database->select('dc_campaign_institution_backfill_review', 'r')
      ->fields('r')
      ->condition('campaign_id', (int) $analysis['campaign_id'])
      ->condition('source_type', (string) $analysis['source_type'])
      ->condition('source_id', (string) $analysis['source_id'])
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $now = time();
    $active_reasons = array_values(array_unique(array_map('strval', $analysis['review_reasons'] ?? [])));
    $active_lookup = array_fill_keys($active_reasons, TRUE);
    $count = count($active_reasons);
    $rows_by_reason = [];

    foreach ($existing_rows as $row) {
      $reason = (string) ($row['review_reason'] ?? '');
      if ($reason === '') {
        continue;
      }
      $rows_by_reason[$reason][] = $row;
    }

    foreach ($rows_by_reason as $reason => $rows) {
      if (isset($active_lookup[$reason])) {
        $open_row = $this->findOpenReviewRow($rows);
        if ($open_row !== NULL) {
          $this->database->update('dc_campaign_institution_backfill_review')
            ->fields([
              'details_json' => json_encode($analysis['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
              'changed' => $now,
              'status' => 'open',
            ])
            ->condition('id', (int) $open_row['id'])
            ->execute();
          continue;
        }
      }

      foreach ($rows as $row) {
        if (($row['status'] ?? 'open') !== 'open') {
          continue;
        }

        if (isset($active_lookup[$reason])) {
          continue;
        }

        $fields = [
          'id' => (int) $row['id'],
        ];
        $this->database->delete('dc_campaign_institution_backfill_review')
          ->condition('id', $fields['id'])
          ->execute();
      }
    }

    foreach ($analysis['review_reasons'] as $reason) {
      if ($this->findOpenReviewRow($rows_by_reason[$reason] ?? []) !== NULL) {
        continue;
      }

      $this->database->insert('dc_campaign_institution_backfill_review')
        ->fields([
          'campaign_id' => (int) $analysis['campaign_id'],
          'source_type' => (string) $analysis['source_type'],
          'source_id' => (string) $analysis['source_id'],
          'actor_row_id' => (int) $analysis['actor_row_id'],
          'actor_type' => (string) $analysis['actor_type'],
          'review_reason' => (string) $reason,
          'details_json' => json_encode($analysis['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'status' => 'open',
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
    }

    return $count;
  }

  /**
   * Returns whether an institution input list includes a given domain.
   *
   * @param array<int, array<string, mixed>> $inputs
   */
  protected function hasInstitutionDomain(array $inputs, string $domain): bool {
    foreach ($inputs as $input) {
      if (($input['domain'] ?? '') === $domain) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Removes any existing institution input for the target domain.
   *
   * @param array<int, array<string, mixed>> $inputs
   *
   * @return array<int, array<string, mixed>>
   */
  protected function removeInstitutionDomain(array $inputs, string $domain): array {
    return array_values(array_filter($inputs, static fn (array $input): bool => ($input['domain'] ?? '') !== $domain));
  }

  /**
   * Applies persisted review overrides to the normalized institution inputs.
   *
   * @param array<int, array<string, mixed>> $institution_inputs
   * @param array<string, array<string, mixed>> $manual_overrides
   *
   * @return array<int, array<string, mixed>>
   */
  protected function applyInstitutionOverrides(array $institution_inputs, array $manual_overrides, string $seed_source): array {
    foreach ($manual_overrides as $override) {
      $domain = trim((string) ($override['domain'] ?? ''));
      if ($domain !== '') {
        $institution_inputs = $this->removeInstitutionDomain($institution_inputs, $domain);
      }

      $input = $this->buildInstitutionInputFromOverride($override, $seed_source);
      if ($input !== NULL) {
        $institution_inputs[] = $input;
      }
    }

    return array_values($institution_inputs);
  }

  /**
   * Builds a normalized institution input from one stored override.
   *
   * @return array<string, mixed>|null
   */
  protected function buildInstitutionInputFromOverride(array $override, string $seed_source): ?array {
    $action = strtolower(trim((string) ($override['action'] ?? '')));
    if ($action === 'mark_blank') {
      return NULL;
    }

    $domain = trim((string) ($override['domain'] ?? ''));
    $display_name = trim((string) ($override['display_name'] ?? ''));
    $subject_id = trim((string) ($override['subject_id'] ?? ''));
    if ($display_name === '' && $subject_id !== '') {
      $display_name = $this->humanizeSubjectIdentifier($subject_id);
    }

    if ($domain === '' || $display_name === '') {
      return NULL;
    }

    return [
      'domain' => $domain,
      'display_name' => $display_name,
      'subject_id' => $subject_id !== '' ? $subject_id : NULL,
      'metadata' => [
        'seed_source' => $seed_source,
        'review_reason' => (string) ($override['review_reason'] ?? ''),
        'resolution_action' => $action,
      ],
    ];
  }

  /**
   * Returns persisted review overrides from source state.
   *
   * @return array<string, array<string, mixed>>
   */
  protected function extractInstitutionOverrides(array $state_data): array {
    $overrides = $state_data[self::OVERRIDE_KEY] ?? [];
    if (!is_array($overrides)) {
      return [];
    }

    $normalized = [];
    foreach ($overrides as $reason => $override) {
      if (!is_array($override)) {
        continue;
      }
      $normalized[(string) $reason] = $override;
    }

    return $normalized;
  }

  /**
   * Returns whether a review reason already has a persisted resolution override.
   */
  protected function hasResolvedOverride(array $manual_overrides, string $reason): bool {
    $action = strtolower(trim((string) (($manual_overrides[$reason]['action'] ?? ''))));
    return in_array($action, ['map_existing', 'create_institution', 'mark_blank'], TRUE);
  }

  /**
   * Decodes a JSON payload to an array.
   */
  protected function decodeJsonArray(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Finds the newest open review row in a grouped review history.
   *
   * @param array<int, array<string, mixed>> $rows
   *
   * @return array<string, mixed>|null
   */
  protected function findOpenReviewRow(array $rows): ?array {
    foreach ($rows as $row) {
      if (($row['status'] ?? 'open') === 'open') {
        return $row;
      }
    }

    return NULL;
  }

  /**
   * Extracts the first non-empty string from a keyed array.
   *
   * @param array<string, mixed> $values
   * @param string[] $keys
   */
  protected function extractNonEmptyString(array $values, array $keys): string {
    foreach ($keys as $key) {
      $value = trim((string) ($values[$key] ?? ''));
      if ($value !== '') {
        return $value;
      }
    }

    return '';
  }

  /**
   * Merges fallback payload values without letting empty strings override data.
   *
   * @param array<string, mixed> $payload
   * @param array<string, mixed> $fallbacks
   *
   * @return array<string, mixed>
   */
  protected function mergePayloadFallbacks(array $payload, array $fallbacks): array {
    foreach ($fallbacks as $key => $value) {
      if (is_array($value) || is_object($value)) {
        continue;
      }

      $fallback_value = trim((string) $value);
      if ($fallback_value === '') {
        continue;
      }

      $current_value = trim((string) ($payload[$key] ?? ''));
      if ($current_value === '') {
        $payload[$key] = $fallback_value;
      }
    }

    return $payload;
  }

  /**
   * Humanizes an institution subject identifier into a display label fallback.
   */
  protected function humanizeSubjectIdentifier(string $subject_id): string {
    if (preg_match('/^institution_[a-z0-9]+_(.+)$/', $subject_id, $matches)) {
      $subject_id = $matches[1];
    }

    $subject_id = preg_replace('/[_-]+/', ' ', trim($subject_id)) ?? $subject_id;
    $subject_id = preg_replace('/\s+/', ' ', $subject_id) ?? $subject_id;
    return ucwords(trim($subject_id));
  }

}
