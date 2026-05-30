<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Builds a staged normalization manifest for library actor institution data.
 */
class LibraryInstitutionBackfillService {

  protected const OVERRIDE_KEY = 'institution_review_overrides';

  public function __construct(
    protected Connection $database,
    protected ModuleExtensionList $moduleExtensionList,
    protected InstitutionMembershipService $institutionMembership,
  ) {}

  /**
   * Rebuilds the library character-template manifest and review queue.
   *
   * @return array<string, int>
   *   Summary counts.
   */
  public function rebuildCharacterTemplateManifest(): array {
    if (!$this->isBackfillStorageReady()) {
      throw new \RuntimeException('Library institution backfill storage is not installed.');
    }

    $summary = [
      'processed' => 0,
      'normalized' => 0,
      'review_required' => 0,
      'context_only' => 0,
      'skipped' => 0,
      'review_rows' => 0,
    ];

    foreach ($this->loadCharacterTemplateRows() as $record) {
      $summary['processed']++;
      $analysis = $this->analyzeCharacterTemplateRow($record['row'], $record['source_file'], (int) $record['row_index']);
      $manifest_id = $this->upsertManifestRow($analysis);
      $review_count = $this->replaceReviewRows($manifest_id, $analysis);
      $summary['review_rows'] += $review_count;

      switch ($analysis['status']) {
        case 'normalized':
          $summary['normalized']++;
          break;

        case 'review_required':
          $summary['review_required']++;
          break;

        case 'context_only':
          $summary['context_only']++;
          break;

        default:
          $summary['skipped']++;
      }
    }

    return $summary;
  }

  /**
   * Returns whether manifest and review storage is installed.
   */
  public function isBackfillStorageReady(): bool {
    $schema = $this->database->schema();
    return $schema->tableExists('dc_library_institution_manifest')
      && $schema->tableExists('dc_library_institution_review');
  }

  /**
   * Analyzes one library character template row.
   *
   * @return array<string, mixed>
   */
  public function analyzeCharacterTemplateRow(array $row, string $source_file, int $row_index = 0): array {
    $row_type = trim((string) ($row['type'] ?? ''));
    $instance_id = trim((string) ($row['instance_id'] ?? ''));
    $character_id = (int) ($row['character_id'] ?? 0);
    $location_type = trim((string) ($row['location_type'] ?? ''));
    $location_ref = trim((string) ($row['location_ref'] ?? ''));
    $role = trim((string) ($row['role'] ?? ''));
    $state_data = is_array($row['state_data'] ?? NULL) ? $row['state_data'] : [];
    $manual_overrides = $this->extractInstitutionOverrides($state_data);

    $classification = $this->classifyCharacterTemplateRow($row_type, $role, $location_type, $state_data);
    $review_reasons = [];
    $institution_inputs = [];

    if ($classification === 'social_actor_thin') {
      if ($row_type === 'pc') {
        $institution_inputs = $this->buildLibraryCharacterInputs($state_data);
      }
      elseif ($row_type === 'npc') {
        $institution_inputs = $this->buildLibraryNpcInputs($state_data);
      }

      $institution_inputs = $this->applyInstitutionOverrides($institution_inputs, $manual_overrides, 'library_review');

      if ($this->extractNonEmptyString($state_data, ['ancestry', 'species']) === '' && !$this->hasResolvedOverride($manual_overrides, 'missing_ancestry')) {
        $review_reasons[] = 'missing_ancestry';
      }

      $class_value = $this->extractNonEmptyString($state_data, ['class']);
      if ($class_value !== '' && !$this->hasInstitutionDomain($institution_inputs, 'profession') && !$this->hasResolvedOverride($manual_overrides, 'ambiguous_profession_label')) {
        $review_reasons[] = 'ambiguous_profession_label';
      }

      if ($location_ref !== '' && !$this->hasResolvedOverride($manual_overrides, 'unresolved_location_ref')) {
        $review_reasons[] = 'unresolved_location_ref';
      }
    }

    $status = match ($classification) {
      'non_social_skip' => 'skipped',
      'collective_context_only' => 'context_only',
      default => $review_reasons === [] ? 'normalized' : 'review_required',
    };

    $payload = [
      'institution_inputs' => array_values($institution_inputs),
      'candidate_fields' => [
        'ancestry' => $this->extractNonEmptyString($state_data, ['ancestry', 'species']),
        'class' => $this->extractNonEmptyString($state_data, ['class']),
        'occupation' => $this->extractNonEmptyString($state_data, ['occupation']),
      ],
      'location' => [
        'location_type' => $location_type,
        'location_ref' => $location_ref,
      ],
      'manual_overrides' => $manual_overrides,
    ];

    return [
      'source_table' => 'dungeoncrawler_content_characters',
      'source_file' => $source_file,
      'source_asset_id' => $instance_id !== '' ? $instance_id : ($character_id > 0 ? (string) $character_id : 'row_' . $row_index),
      'library_character_id' => $character_id ?: NULL,
      'row_type' => $row_type,
      'classification' => $classification,
      'status' => $status,
      'normalized_payload' => $payload,
      'review_reasons' => array_values(array_unique($review_reasons)),
      'provenance' => [
        'instance_id' => $instance_id,
        'character_id' => $character_id,
        'role' => $role,
      ],
    ];
  }

  /**
   * Refreshes one source asset after a review decision changes its source state.
   *
   * @return array<string, mixed>
   */
  public function refreshCharacterTemplateRow(string $source_file, string $source_asset_id): array {
    if (!$this->isBackfillStorageReady()) {
      throw new \RuntimeException('Library institution backfill storage is not installed.');
    }

    $record = $this->loadCharacterTemplateRecord($source_file, $source_asset_id);
    if ($record === NULL) {
      throw new \InvalidArgumentException(sprintf('Library source asset "%s" was not found in %s.', $source_asset_id, $source_file));
    }

    $analysis = $this->analyzeCharacterTemplateRow($record['row'], $record['source_file'], (int) $record['row_index']);
    $manifest_id = $this->upsertManifestRow($analysis);
    $review_count = $this->replaceReviewRows($manifest_id, $analysis);

    return [
      'manifest_id' => $manifest_id,
      'status' => $analysis['status'],
      'review_rows' => $review_count,
      'review_reasons' => $analysis['review_reasons'],
    ];
  }

  /**
   * Loads all packaged character template rows with their source file path.
   *
   * @return array<int, array{source_file: string, row: array<string, mixed>}>
   */
  protected function loadCharacterTemplateRows(): array {
    $records = [];
    $files = glob($this->getCharacterTemplatesPath() . '/*.json') ?: [];
    sort($files);
    foreach ($files as $file) {
      $payload = json_decode((string) file_get_contents($file), TRUE);
      $rows = is_array($payload['rows'] ?? NULL) ? $payload['rows'] : [];
      foreach ($rows as $row_index => $row) {
        if (is_array($row)) {
          $records[] = [
            'source_file' => $file,
            'row_index' => $row_index,
            'row' => $row,
          ];
        }
      }
    }

    return $records;
  }

  /**
   * Loads one packaged character template row by stable source asset id.
   *
   * @return array{source_file: string, row_index: int, row: array<string, mixed>}|null
   */
  protected function loadCharacterTemplateRecord(string $source_file, string $source_asset_id): ?array {
    $payload = json_decode((string) file_get_contents($source_file), TRUE);
    $rows = is_array($payload['rows'] ?? NULL) ? $payload['rows'] : [];
    foreach ($rows as $row_index => $row) {
      if (!is_array($row)) {
        continue;
      }
      if ($this->resolveSourceAssetId($row, (int) $row_index) !== $source_asset_id) {
        continue;
      }

      return [
        'source_file' => $source_file,
        'row_index' => (int) $row_index,
        'row' => $row,
      ];
    }

    return NULL;
  }

  /**
   * Builds deterministic library inputs for a PC-like row.
   *
   * @return array<int, array<string, mixed>>
   */
  protected function buildLibraryCharacterInputs(array $state_data): array {
    $normalized = $state_data;
    if (empty($normalized['ancestry']) && !empty($normalized['species'])) {
      $normalized['ancestry'] = $normalized['species'];
    }

    return $this->institutionMembership->buildCharacterInstitutionInputs($normalized, 'library_backfill');
  }

  /**
   * Builds deterministic library inputs for an NPC-like row.
   *
   * @return array<int, array<string, mixed>>
   */
  protected function buildLibraryNpcInputs(array $state_data): array {
    $normalized = $state_data;
    if (empty($normalized['ancestry']) && !empty($normalized['species'])) {
      $normalized['ancestry'] = $normalized['species'];
    }

    return $this->institutionMembership->buildNpcInstitutionInputs($normalized, 'library_backfill');
  }

  /**
   * Classifies one character template row for backfill handling.
   */
  protected function classifyCharacterTemplateRow(string $row_type, string $role, string $location_type, array $state_data): string {
    if (in_array($row_type, ['trap', 'obstacle', 'hazard'], TRUE)) {
      return 'non_social_skip';
    }

    if ($row_type === 'party') {
      return 'collective_context_only';
    }

    $class_value = strtolower($this->extractNonEmptyString($state_data, ['class']));
    if ($row_type === 'npc' && (($role === 'enemy' && $class_value === 'creature') || $location_type === 'encounter_template')) {
      return 'non_social_skip';
    }

    if (in_array($row_type, ['pc', 'npc'], TRUE)) {
      return 'social_actor_thin';
    }

    return 'non_social_skip';
  }

  /**
   * Writes or updates the manifest row for one source asset.
   */
  protected function upsertManifestRow(array $analysis): int {
    $now = time();
    $fields = [
      'source_table' => (string) $analysis['source_table'],
      'source_file' => (string) $analysis['source_file'],
      'source_asset_id' => (string) $analysis['source_asset_id'],
      'library_character_id' => $analysis['library_character_id'],
      'row_type' => (string) $analysis['row_type'],
      'classification' => (string) $analysis['classification'],
      'status' => (string) $analysis['status'],
      'normalized_payload_json' => json_encode($analysis['normalized_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'review_reasons_json' => json_encode($analysis['review_reasons'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'provenance_json' => json_encode($analysis['provenance'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changed' => $now,
    ];

    $existing = $this->database->select('dc_library_institution_manifest', 'm')
      ->fields('m', ['id'])
      ->condition('source_file', $analysis['source_file'])
      ->condition('source_asset_id', $analysis['source_asset_id'])
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($existing) {
      $this->database->update('dc_library_institution_manifest')
        ->fields($fields)
        ->condition('id', (int) $existing)
        ->execute();
      return (int) $existing;
    }

    $fields['created'] = $now;
    return (int) $this->database->insert('dc_library_institution_manifest')
      ->fields($fields)
      ->execute();
  }

  /**
   * Replaces review rows for a manifest entry.
   */
  protected function replaceReviewRows(int $manifest_id, array $analysis): int {
    $existing_rows = $this->database->select('dc_library_institution_review', 'r')
      ->fields('r')
      ->condition('manifest_id', $manifest_id)
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
          $this->database->update('dc_library_institution_review')
            ->fields([
              'details_json' => json_encode($analysis['normalized_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

        $this->database->delete('dc_library_institution_review')
          ->condition('id', (int) $row['id'])
          ->execute();
      }
    }

    foreach ($analysis['review_reasons'] as $reason) {
      if ($this->findOpenReviewRow($rows_by_reason[$reason] ?? []) !== NULL) {
        continue;
      }

      $this->database->insert('dc_library_institution_review')
        ->fields([
          'manifest_id' => $manifest_id,
          'source_table' => (string) $analysis['source_table'],
          'source_file' => (string) $analysis['source_file'],
          'source_asset_id' => (string) $analysis['source_asset_id'],
          'review_reason' => (string) $reason,
          'details_json' => json_encode($analysis['normalized_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          'status' => 'open',
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
    }

    return $count;
  }

  /**
   * Returns the packaged character template directory path.
   */
  protected function getCharacterTemplatesPath(): string {
    return DRUPAL_ROOT . '/' . $this->moduleExtensionList->getPath('dungeoncrawler_content') . '/config/examples/templates/dungeoncrawler_content_characters';
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
   * Resolves the stable source asset identifier for a template row.
   */
  protected function resolveSourceAssetId(array $row, int $row_index): string {
    $instance_id = trim((string) ($row['instance_id'] ?? ''));
    $character_id = (int) ($row['character_id'] ?? 0);
    return $instance_id !== '' ? $instance_id : ($character_id > 0 ? (string) $character_id : 'row_' . $row_index);
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

}
