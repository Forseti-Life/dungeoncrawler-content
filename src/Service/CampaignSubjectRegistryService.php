<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Manages campaign-scoped subject-registry records for collective actors.
 */
class CampaignSubjectRegistryService {
  public function __construct(
    protected Connection $database,
    protected InstitutionNormalizationService $institutionNormalization,
    protected RelationshipManagerService $relationshipManager,
  ) {}

  /**
   * Returns whether campaign subject-registry storage is installed.
   */
  public function isSubjectRegistryReady(): bool {
    return $this->database->schema()->tableExists('dc_campaign_subject_registry');
  }

  /**
   * Resolve or create a campaign institution subject.
   *
   * @return array<string, mixed>
   *   Canonical registry row fields plus normalized helper fields.
   */
  public function resolveOrCreateInstitutionSubject(int $campaign_id, array $input): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    if (!$this->isSubjectRegistryReady()) {
      throw new \RuntimeException('Campaign subject registry storage is not installed.');
    }

    $normalized = $this->institutionNormalization->normalizeInstitutionInput($input);
    $now = time();
    $source_asset_type = trim((string) ($input['source_asset_type'] ?? ''));
    $source_asset_id = trim((string) ($input['source_asset_id'] ?? ''));
    $existing = $this->loadExistingInstitutionRegistryRow(
      $campaign_id,
      (string) $normalized['domain'],
      (string) $normalized['normalized_label']
    );

    $metadata = is_array($input['metadata'] ?? NULL) ? $input['metadata'] : [];
    $metadata += [
      'domain' => $normalized['domain'],
      'normalized_label' => $normalized['normalized_label'],
    ];

    $fields = [
      'campaign_id' => $campaign_id,
      'subject_id' => $normalized['subject_id'],
      'subject_kind' => 'institution',
      'subject_domain' => $normalized['domain'],
      'subject_key' => $normalized['domain'] . ':' . $normalized['normalized_label'],
      'display_name' => $normalized['display_name'],
      'normalized_label' => $normalized['normalized_label'],
      'entity_ref' => array_key_exists('entity_ref', $input) ? (trim((string) $input['entity_ref']) ?: NULL) : NULL,
      'source_asset_type' => $source_asset_type ?: NULL,
      'source_asset_id' => $source_asset_id ?: NULL,
      'status' => array_key_exists('status', $input) ? (trim((string) $input['status']) ?: 'active') : 'active',
      'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changed' => $now,
    ];

    if ($existing) {
      $existing_subject_id = trim((string) ($existing['subject_id'] ?? ''));
      if ($existing_subject_id !== '') {
        $fields['subject_id'] = $existing_subject_id;
      }
      $existing_source_asset_type = trim((string) ($existing['source_asset_type'] ?? ''));
      $existing_source_asset_id = trim((string) ($existing['source_asset_id'] ?? ''));
      if ($existing_source_asset_type !== '' && $existing_source_asset_id !== '') {
        $fields['source_asset_type'] = $existing_source_asset_type;
        $fields['source_asset_id'] = $existing_source_asset_id;
      }
      if (!array_key_exists('entity_ref', $input)) {
        $fields['entity_ref'] = trim((string) ($existing['entity_ref'] ?? '')) ?: NULL;
      }
      if (!array_key_exists('status', $input)) {
        $fields['status'] = trim((string) ($existing['status'] ?? '')) ?: 'active';
      }
      $fields['metadata_json'] = json_encode(
        array_replace($this->decodeJsonColumn($existing['metadata_json'] ?? NULL), $metadata),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
      $this->database->update('dc_campaign_subject_registry')
        ->fields($fields)
        ->condition('id', (int) $existing['id'])
        ->execute();
      $fields['id'] = (int) $existing['id'];
      $fields['created'] = (int) ($existing['created'] ?? $now);
    }
    else {
      $fields['created'] = $now;
      $fields['id'] = (int) $this->database->insert('dc_campaign_subject_registry')
        ->fields($fields)
        ->execute();
    }

    $this->syncInstitutionParentRelationship($campaign_id, (string) $fields['subject_id'], $normalized['parent_subject_id']);

    return $fields + [
      'parent_subject_id' => $normalized['parent_subject_id'],
    ];
  }

  /**
   * Loads an existing campaign institution subject by stable subject id.
   *
   * @return array<string, mixed>
   *   Registry row fields.
   */
  public function loadInstitutionSubject(int $campaign_id, string $subject_id): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    $subject_id = trim($subject_id);
    if ($subject_id === '') {
      throw new \InvalidArgumentException('Institution subject id is required.');
    }
    if (!$this->isSubjectRegistryReady()) {
      throw new \RuntimeException('Campaign subject registry storage is not installed.');
    }

    $existing = $this->database->select('dc_campaign_subject_registry', 'r')
      ->fields('r')
      ->condition('campaign_id', $campaign_id)
      ->condition('subject_kind', 'institution')
      ->condition('subject_id', $subject_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($existing) || $existing === []) {
      throw new \InvalidArgumentException(sprintf('Campaign institution subject "%s" was not found.', $subject_id));
    }

    return $existing;
  }

  /**
   * Reconciles the single active parent edge for a campaign institution subject.
   */
  protected function syncInstitutionParentRelationship(int $campaign_id, string $subject_id, string $parent_subject_id): void {
    $subject_id = trim($subject_id);
    $parent_subject_id = trim($parent_subject_id);
    if ($subject_id === '' || !$this->relationshipManager->isRelationshipStorageReady()) {
      return;
    }

    $this->database->delete('dc_campaign_relationships')
      ->condition('campaign_id', $campaign_id)
      ->condition('source_type', 'institution')
      ->condition('source_id', $subject_id)
      ->condition('target_type', 'institution')
      ->condition('relationship_type', 'institution_parent')
      ->execute();

    if ($parent_subject_id === '') {
      return;
    }

    $this->relationshipManager->upsertRuntimeRelationship($campaign_id, [
      'source_type' => 'institution',
      'source_id' => $subject_id,
      'target_type' => 'institution',
      'target_id' => $parent_subject_id,
      'relationship_type' => 'institution_parent',
      'status' => 'active',
      'relationship_state' => [
        'edge_kind' => 'institution_hierarchy',
        'source_scope' => 'subject_registry',
      ],
    ]);
  }

  /**
   * Loads one existing institution registry row by canonical subject identity.
   *
   * Provenance is tracked on the row, but it is not the row's identity. Matching
   * only on source assets can collapse distinct affiliations that share the same
   * upstream authoring asset or fail to reuse a pre-existing canonical row that
   * was created before provenance metadata was attached.
   *
   * @return array<string, mixed>|null
   *   Matching registry row or NULL when none exists.
   */
  protected function loadExistingInstitutionRegistryRow(int $campaign_id, string $subject_domain, string $normalized_label): ?array {
    $existing = $this->database->select('dc_campaign_subject_registry', 'r')
      ->fields('r')
      ->condition('campaign_id', $campaign_id)
      ->condition('subject_kind', 'institution')
      ->condition('subject_domain', $subject_domain)
      ->condition('normalized_label', $normalized_label)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($existing) && $existing !== [] ? $existing : NULL;
  }

  /**
   * Decodes a JSON payload into an array.
   *
   * @return array<string, mixed>
   *   Decoded associative array.
   */
  protected function decodeJsonColumn(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

}
