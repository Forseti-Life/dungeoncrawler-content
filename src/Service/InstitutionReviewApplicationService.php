<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Applies resolved institution review decisions back into source state.
 */
class InstitutionReviewApplicationService {

  protected const OVERRIDE_KEY = 'institution_review_overrides';

  public function __construct(
    protected Connection $database,
    protected InstitutionReviewDecisionService $reviewDecisionService,
    protected CampaignInstitutionBackfillService $campaignInstitutionBackfill,
    protected LibraryInstitutionBackfillService $libraryInstitutionBackfill,
    protected TimeInterface $time,
  ) {}

  /**
   * Applies one resolved decision and refreshes the affected source state.
   *
   * @return array<string, mixed>
   */
  public function applyDecision(string $queue_type, int $row_id): array {
    $review_row = $this->reviewDecisionService->loadReviewRow($queue_type, $row_id);
    if ($review_row === []) {
      throw new \InvalidArgumentException(sprintf('Institution review row %d was not found for queue %s.', $row_id, $queue_type));
    }

    if (($review_row['status'] ?? '') !== InstitutionReviewDecisionService::STATUS_RESOLVED) {
      return [
        'applied' => FALSE,
        'status' => (string) ($review_row['status'] ?? ''),
      ];
    }

    return match (strtolower(trim($queue_type))) {
      'library' => $this->applyLibraryDecision($review_row),
      'campaign' => $this->applyCampaignDecision($review_row),
      default => throw new \InvalidArgumentException(sprintf('Unsupported institution review queue "%s".', $queue_type)),
    };
  }

  /**
   * Applies a validated resolved decision before it is persisted to the queue row.
   *
   * @return array<string, mixed>
   */
  public function applyPendingDecision(string $queue_type, int $row_id, string $status, string $action, array $payload, int $actor_uid): array {
    $review_row = $this->reviewDecisionService->loadReviewRow($queue_type, $row_id);
    if ($review_row === []) {
      throw new \InvalidArgumentException(sprintf('Institution review row %d was not found for queue %s.', $row_id, $queue_type));
    }

    $decision_update = $this->reviewDecisionService->buildDecisionUpdate(
      $status,
      $action,
      $payload,
      $actor_uid,
      $this->time->getRequestTime()
    );
    if (($decision_update['status'] ?? '') !== InstitutionReviewDecisionService::STATUS_RESOLVED) {
      return [
        'applied' => FALSE,
        'status' => (string) ($decision_update['status'] ?? ''),
      ];
    }

    $review_row = array_merge($review_row, $decision_update);

    return match (strtolower(trim($queue_type))) {
      'library' => $this->applyLibraryDecision($review_row),
      'campaign' => $this->applyCampaignDecision($review_row),
      default => throw new \InvalidArgumentException(sprintf('Unsupported institution review queue "%s".', $queue_type)),
    };
  }

  /**
   * Applies a resolved library review decision.
   *
   * @param array<string, mixed> $review_row
   *
   * @return array<string, mixed>
   */
  protected function applyLibraryDecision(array $review_row): array {
    $source_file = (string) ($review_row['source_file'] ?? '');
    $source_asset_id = (string) ($review_row['source_asset_id'] ?? '');
    if ($source_file === '' || $source_asset_id === '') {
      throw new \InvalidArgumentException('Library review rows require source_file and source_asset_id.');
    }

    if ($source_file === FactionGenerationService::MANIFEST_SOURCE_FILE) {
      return $this->applyGeneratedFactionDecision($review_row);
    }

    $payload = json_decode((string) file_get_contents($source_file), TRUE);
    if (!is_array($payload)) {
      throw new \RuntimeException(sprintf('Library source file %s could not be decoded.', $source_file));
    }

    $rows = is_array($payload['rows'] ?? NULL) ? $payload['rows'] : [];
    $row_index = $this->findLibraryRowIndex($rows, $source_asset_id);
    if ($row_index === NULL) {
      throw new \InvalidArgumentException(sprintf('Library source asset "%s" was not found in %s.', $source_asset_id, $source_file));
    }

    $row = is_array($rows[$row_index]) ? $rows[$row_index] : [];
    $state_data = is_array($row['state_data'] ?? NULL) ? $row['state_data'] : [];
    $state_data[self::OVERRIDE_KEY] = $this->upsertOverrideCollection(
      $state_data[self::OVERRIDE_KEY] ?? [],
      $this->buildOverrideRecord($review_row)
    );
    $row['state_data'] = $state_data;
    $rows[$row_index] = $row;
    $payload['rows'] = $rows;

    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === FALSE || file_put_contents($source_file, $encoded . PHP_EOL) === FALSE) {
      throw new \RuntimeException(sprintf('Failed to write updated library source state to %s.', $source_file));
    }

    $this->syncLibraryTemplateRow($review_row, $state_data);
    $refresh = $this->libraryInstitutionBackfill->refreshCharacterTemplateRow($source_file, $source_asset_id);

    return [
      'applied' => TRUE,
      'queue_type' => 'library',
      'source_asset_id' => $source_asset_id,
      'refresh' => $refresh,
    ];
  }

  /**
   * Applies a resolved campaign review decision.
   *
   * @param array<string, mixed> $review_row
   *
   * @return array<string, mixed>
   */
  protected function applyCampaignDecision(array $review_row): array {
    $actor_row_id = (int) ($review_row['actor_row_id'] ?? 0);
    if ($actor_row_id <= 0) {
      throw new \InvalidArgumentException('Campaign review rows require actor_row_id.');
    }

    $actor_row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'state_data', 'updated'])
      ->condition('id', $actor_row_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($actor_row)) {
      throw new \InvalidArgumentException(sprintf('Campaign runtime actor row %d was not found.', $actor_row_id));
    }

    $state_data = $this->decodeJsonArray($actor_row['state_data'] ?? NULL);
    $state_data[self::OVERRIDE_KEY] = $this->upsertOverrideCollection(
      $state_data[self::OVERRIDE_KEY] ?? [],
      $this->buildOverrideRecord($review_row)
    );

    $this->database->update('dc_campaign_characters')
      ->fields([
        'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ])
      ->condition('id', $actor_row_id)
      ->execute();

    $refresh = $this->campaignInstitutionBackfill->refreshRuntimeActor($actor_row_id);

    return [
      'applied' => TRUE,
      'queue_type' => 'campaign',
      'actor_row_id' => $actor_row_id,
      'refresh' => $refresh,
    ];
  }

  /**
   * Synchronizes the imported library template row with the file-backed override.
   *
   * @param array<string, mixed> $review_row
   * @param array<string, mixed> $state_data
   */
  protected function syncLibraryTemplateRow(array $review_row, array $state_data): void {
    $update = $this->database->update('dungeoncrawler_content_characters')
      ->fields([
        'state_data' => json_encode($state_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated' => $this->time->getRequestTime(),
      ]);

    $library_character_id = (int) ($review_row['library_character_id'] ?? 0);
    if ($library_character_id > 0) {
      $update->condition('character_id', $library_character_id);
    }
    else {
      $update->condition('instance_id', (string) ($review_row['source_asset_id'] ?? ''));
    }

    $update->execute();
  }

  /**
   * Builds a normalized persisted override record from a resolved review row.
   *
   * @param array<string, mixed> $review_row
   *
   * @return array<string, mixed>
   */
  protected function buildOverrideRecord(array $review_row): array {
    $action = strtolower(trim((string) ($review_row['resolution_action'] ?? '')));
    $payload = $this->decodeJsonArray($review_row['resolution_payload_json'] ?? NULL);
    $review_reason = (string) ($review_row['review_reason'] ?? '');
    $domain = $this->resolveOverrideDomain($review_reason, $payload, $review_row);
    $display_name = $this->resolveOverrideDisplayName($payload, $review_row);
    $subject_id = '';

    if ($action === 'map_existing') {
      $subject_id = trim((string) ($payload['target_identifier'] ?? ''));
    }
    elseif ($action === 'create_institution' && $domain !== '' && $display_name !== '') {
      $subject_id = sprintf(
        'institution_%s_%s',
        $domain,
        $this->slugify($display_name)
      );
    }

    return [
      'review_reason' => $review_reason,
      'action' => $action,
      'domain' => $domain,
      'display_name' => $display_name,
      'subject_id' => $subject_id,
      'decision_summary' => trim((string) ($payload['decision_summary'] ?? '')),
      'note' => trim((string) ($payload['note'] ?? '')),
      'resolved_at' => (int) ($review_row['resolved_at'] ?? $this->time->getRequestTime()),
      'resolution_actor_uid' => (int) ($review_row['resolution_actor_uid'] ?? 0),
    ];
  }

  /**
   * Updates or inserts one override in an override collection.
   *
   * @param mixed $overrides
   * @param array<string, mixed> $override
   *
   * @return array<string, array<string, mixed>>
   */
  protected function upsertOverrideCollection(mixed $overrides, array $override): array {
    $collection = is_array($overrides) ? $overrides : [];
    $collection[(string) $override['review_reason']] = $override;
    return $collection;
  }

  /**
   * Resolves the canonical domain stored with an override.
   *
   * @param array<string, mixed> $payload
   * @param array<string, mixed> $review_row
   */
  protected function resolveOverrideDomain(string $review_reason, array $payload, array $review_row): string {
    $domain = strtolower(trim((string) ($payload['canonical_domain'] ?? '')));
    if ($domain !== '') {
      return $domain;
    }

    $target_identifier = trim((string) ($payload['target_identifier'] ?? ''));
    if (preg_match('/^institution_([a-z0-9]+)_.+$/', $target_identifier, $matches)) {
      return $matches[1];
    }

    if ($review_reason === 'missing_ancestry') {
      return 'ancestry';
    }
    if ($review_reason === 'ambiguous_profession_label') {
      return 'profession';
    }

    return $this->lookupCampaignSubjectDomain((int) ($review_row['campaign_id'] ?? 0), $target_identifier);
  }

  /**
   * Resolves the canonical display label stored with an override.
   *
   * @param array<string, mixed> $payload
   * @param array<string, mixed> $review_row
   */
  protected function resolveOverrideDisplayName(array $payload, array $review_row): string {
    $label = trim((string) ($payload['canonical_label'] ?? ''));
    if ($label !== '') {
      return $label;
    }

    $target_identifier = trim((string) ($payload['target_identifier'] ?? ''));
    $label = $this->lookupCampaignSubjectLabel((int) ($review_row['campaign_id'] ?? 0), $target_identifier);
    if ($label !== '') {
      return $label;
    }

    return $this->humanizeSubjectIdentifier($target_identifier);
  }

  /**
   * Looks up a campaign registry label for a target subject identifier.
   */
  protected function lookupCampaignSubjectLabel(int $campaign_id, string $subject_id): string {
    if ($campaign_id <= 0 || $subject_id === '') {
      return '';
    }

    $label = $this->database->select('dc_campaign_subject_registry', 'r')
      ->fields('r', ['display_name'])
      ->condition('campaign_id', $campaign_id)
      ->condition('subject_id', $subject_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_string($label) ? trim($label) : '';
  }

  /**
   * Looks up a campaign registry domain for a target subject identifier.
   */
  protected function lookupCampaignSubjectDomain(int $campaign_id, string $subject_id): string {
    if ($campaign_id <= 0 || $subject_id === '') {
      return '';
    }

    $domain = $this->database->select('dc_campaign_subject_registry', 'r')
      ->fields('r', ['subject_domain'])
      ->condition('campaign_id', $campaign_id)
      ->condition('subject_id', $subject_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_string($domain) ? trim($domain) : '';
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
   * Finds a library row index by its stable source asset identifier.
   *
   * @param array<int, mixed> $rows
   */
  protected function findLibraryRowIndex(array $rows, string $source_asset_id): ?int {
    foreach ($rows as $index => $row) {
      if (!is_array($row)) {
        continue;
      }
      if ($this->resolveLibrarySourceAssetId($row, (int) $index) === $source_asset_id) {
        return (int) $index;
      }
    }

    return NULL;
  }

  /**
   * Resolves the stable source asset identifier for a library row.
   */
  protected function resolveLibrarySourceAssetId(array $row, int $row_index): string {
    $instance_id = trim((string) ($row['instance_id'] ?? ''));
    $character_id = (int) ($row['character_id'] ?? 0);
    return $instance_id !== '' ? $instance_id : ($character_id > 0 ? (string) $character_id : 'row_' . $row_index);
  }

  /**
   * Humanizes an institution subject identifier into a display label fallback.
   */
  protected function humanizeSubjectIdentifier(string $subject_id): string {
    if ($subject_id === '') {
      return '';
    }
    if (preg_match('/^institution_[a-z0-9]+_(.+)$/', $subject_id, $matches)) {
      $subject_id = $matches[1];
    }

    $subject_id = preg_replace('/[_-]+/', ' ', trim($subject_id)) ?? $subject_id;
    $subject_id = preg_replace('/\s+/', ' ', $subject_id) ?? $subject_id;
    return ucwords(trim($subject_id));
  }

  /**
   * Applies a resolved near-match decision for a generated faction manifest row.
   *
   * Handles approve_faction, reject_faction, and merge_with_existing actions
   * instead of reading from a packaged source file.
   *
   * @param array<string, mixed> $review_row
   *
   * @return array<string, mixed>
   */
  protected function applyGeneratedFactionDecision(array $review_row): array {
    $action = strtolower(trim((string) ($review_row['resolution_action'] ?? '')));
    $source_asset_id = (string) ($review_row['source_asset_id'] ?? '');
    $manifest_id = (int) ($review_row['manifest_id'] ?? 0);

    $new_manifest_status = match ($action) {
      'approve_faction' => 'normalized',
      'reject_faction' => 'rejected',
      'merge_with_existing' => 'merged',
      default => throw new \InvalidArgumentException(sprintf('Unsupported generated faction review action "%s".', $action)),
    };

    $now = $this->time->getRequestTime();
    if ($manifest_id > 0) {
      $this->database->update('dc_library_institution_manifest')
        ->fields(['status' => $new_manifest_status, 'changed' => $now])
        ->condition('id', $manifest_id)
        ->execute();
    }
    elseif ($source_asset_id !== '') {
      $this->database->update('dc_library_institution_manifest')
        ->fields(['status' => $new_manifest_status, 'changed' => $now])
        ->condition('source_asset_id', $source_asset_id)
        ->condition('source_table', FactionGenerationService::MANIFEST_SOURCE_TABLE)
        ->execute();
    }
    else {
      throw new \InvalidArgumentException('Generated faction review rows require manifest_id or source_asset_id.');
    }

    return [
      'applied' => TRUE,
      'queue_type' => 'library',
      'action' => $action,
      'manifest_status' => $new_manifest_status,
      'source_asset_id' => $source_asset_id,
    ];
  }

  /**
   * Normalizes a display label into the module slug format.
   */
  protected function slugify(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    return trim($value, '-_');
  }

}
