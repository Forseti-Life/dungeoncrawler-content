<?php

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalTemplateGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Support\H3SpatialHelper;

/**
 * Registry for module conversion release identities and campaign source pins.
 */
class ModuleConversionReleaseService {

  private const TABLE = 'dungeoncrawler_content_module_releases';
  private const STATUS_REQUIRES_AUTHORING = 'requires_authoring';
  private const STATUS_RECEIPTS_RECORDED = 'receipts_recorded';
  private const STATUS_PUBLISHED = 'published';
  private const EXECUTION_STATES = ['pending', 'in_progress', 'done', 'failed'];
  private const ACTOR_REVIEW_STATUSES = ['placed', 'catalog_only'];
  private const ACTOR_REVIEW_ORIGINS = ['source', 'interpretive', 'project_original'];

  public function __construct(
    private readonly Connection $database,
    private readonly UuidInterface $uuid,
    private readonly TimeInterface $time,
    private readonly RoomEditorService $roomEditor,
    private readonly DungeonEditorService $dungeonEditor,
    private readonly CanonicalDefinitionService $canonicalDefinitions,
    private readonly ModuleSceneGenerationAdapter $sceneGeneration,
    private readonly ModuleDungeonAssemblyAdapter $dungeonAssembly,
    private readonly ModuleSceneMapStagingService $mapStaging,
    private readonly ?CanonicalDefinitionGenerationService $definitionGeneration = NULL,
    private readonly ?CanonicalTemplateGenerationService $questTemplates = NULL,
    private readonly ?CanonicalQuestTemplateService $canonicalQuests = NULL,
    private readonly ?FileSystemInterface $fileSystem = NULL,
    private readonly ?GeneratedImageRepository $images = NULL,
  ) {}

  /**
   * @return array<int,array<string,mixed>>
   */
  public function listReleases(int $limit = 1): array {
    if ($limit !== 1) {
      throw new \InvalidArgumentException('Full release details support one release at a time; use listCampaignSourceSummaries() for lists.');
    }
    $rows = $this->database->select(self::TABLE, 'r')
      ->fields('r')
      ->orderBy('created', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
  }

  /**
   * Lightweight release summaries for campaign source selectors.
   *
   * Excludes synthetic validation/gate-check artifacts and avoids loading
   * heavy inventory/plan payload columns.
   *
   * @return array<int,array<string,mixed>>
   */
  public function listCampaignSourceSummaries(int $limit = 100): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return [];
    }
    $limit = max(1, min(500, $limit));
    $query = $this->database->select(self::TABLE, 'r')
      ->fields('r', [
        'release_id',
        'module_id',
        'module_title',
        'module_version',
        'conversion_revision',
        'publication_status',
        'updated',
      ]);
    foreach ([
      'dungeon_id' => '$.dungeon_id',
      'version_id' => '$.version_id',
      'receipt_required' => '$.native_receipt_summary.required_count',
      'receipt_recorded' => '$.native_receipt_summary.recorded_count',
      'execution_done' => '$.execution_summary.states.done',
      'execution_in_progress' => '$.execution_summary.states.in_progress',
      'execution_failed' => '$.execution_summary.states.failed',
    ] as $alias => $path) {
      $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$path'))", $alias);
    }
    $rows = $query
      ->condition('module_id', 'project-authored-gate-check%', 'NOT LIKE')
      ->condition('module_id', '%--validation-%', 'NOT LIKE')
      ->condition('module_version', '%--validation-%', 'NOT LIKE')
      ->orderBy('updated', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $summaries = [];
    $launchable_ids = array_fill_keys(array_column($this->listPublishedCampaignSources(), 'release_id'), TRUE);
    foreach ($rows as $row) {
      $mapping = [
        'dungeon_id' => (string) ($row['dungeon_id'] ?? ''),
        'version_id' => (string) ($row['version_id'] ?? ''),
      ];
      if ($row['receipt_required'] !== NULL) {
        $mapping['native_receipt_summary'] = [
          'required_count' => (int) $row['receipt_required'],
          'recorded_count' => (int) ($row['receipt_recorded'] ?? 0),
        ];
      }
      if ($row['execution_done'] !== NULL) {
        $mapping['execution_summary'] = ['states' => [
          'done' => (int) $row['execution_done'],
          'in_progress' => (int) ($row['execution_in_progress'] ?? 0),
          'failed' => (int) ($row['execution_failed'] ?? 0),
        ]];
      }
      $summary = [
        'release_id' => (string) ($row['release_id'] ?? ''),
        'module_id' => (string) ($row['module_id'] ?? ''),
        'module_title' => (string) ($row['module_title'] ?? ''),
        'module_version' => (string) ($row['module_version'] ?? ''),
        'conversion_revision' => (string) ($row['conversion_revision'] ?? 'base'),
        'mapping' => $mapping,
        'publication_status' => (string) ($row['publication_status'] ?? ''),
        'updated' => (int) ($row['updated'] ?? 0),
      ];
      $summary['launchable'] = isset($launchable_ids[$summary['release_id']]);
      $summaries[] = $summary;
    }
    return $summaries;
  }

  /**
   * Campaign launch options pinned to currently published canonical dungeons.
   *
   * @return array<int,array<string,mixed>>
   */
  public function listPublishedCampaignSources(): array {
    return array_values(array_filter(
      $this->campaignSourceProjectionRows(),
      static fn(array $release): bool => $release['launchable']
    ));
  }

  /**
   * Resolve the compact campaign-source fields without decoding inventories.
   *
   * @return array<int,array<string,mixed>>
   */
  private function campaignSourceProjectionRows(?string $release_id = NULL): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      return [];
    }
    $query = $this->database->select(self::TABLE, 'r')
      ->fields('r', [
        'release_id',
        'module_id',
        'module_title',
        'module_version',
        'conversion_revision',
        'archive_sha256',
        'manifest_data',
        'publication_status',
        'created_by',
        'published_by',
        'created',
        'updated',
        'published_at',
      ]);
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.dungeon_id'))", 'mapped_dungeon_id');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.version_id'))", 'mapped_version_id');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.native_receipt_summary.complete'))", 'receipt_complete');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.published_manifest.schema_version'))", 'manifest_schema_version');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.published_manifest.dungeon_id'))", 'manifest_dungeon_id');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.published_manifest.version_id'))", 'manifest_version_id');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.published_manifest.receipt_resource_id'))", 'manifest_receipt_resource_id');
    $query->addExpression("JSON_EXTRACT(r.mapping_data, CONCAT('$.native_receipts.', JSON_QUOTE(JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.published_manifest.receipt_resource_id')))))", 'manifest_receipt_json');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.scene_coverage.complete'))", 'scene_coverage_complete');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.scene_coverage.scene_count'))", 'scene_coverage_scene_count');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.scene_coverage.dungeon_id'))", 'scene_coverage_dungeon_id');
    $query->addExpression("JSON_UNQUOTE(JSON_EXTRACT(r.mapping_data, '$.scene_coverage.version_id'))", 'scene_coverage_version_id');
    if ($release_id === NULL) {
      $query
        ->condition('publication_status', self::STATUS_PUBLISHED)
        ->condition('module_id', 'project-authored-gate-check%', 'NOT LIKE')
        ->condition('module_id', '%--validation-%', 'NOT LIKE')
        ->condition('module_version', '%--validation-%', 'NOT LIKE')
        ->orderBy('updated', 'DESC')
        ->range(0, 250);
    }
    else {
      $query->condition('release_id', $release_id)->range(0, 1);
    }
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $projected = [];
    foreach ($rows as $row) {
      $release = [
        'release_id' => (string) ($row['release_id'] ?? ''),
        'module_id' => (string) ($row['module_id'] ?? ''),
        'module_title' => (string) ($row['module_title'] ?? ''),
        'module_version' => (string) ($row['module_version'] ?? ''),
        'conversion_revision' => (string) ($row['conversion_revision'] ?? 'base'),
        'archive_sha256' => strtolower((string) ($row['archive_sha256'] ?? '')),
        'manifest' => $this->decodeJsonObject((string) ($row['manifest_data'] ?? '{}')),
        'mapping' => [
          'dungeon_id' => trim((string) ($row['mapped_dungeon_id'] ?? '')),
          'version_id' => trim((string) ($row['mapped_version_id'] ?? '')),
          'native_receipt_summary' => [
            'complete' => in_array(strtolower(trim((string) ($row['receipt_complete'] ?? ''))), ['1', 'true'], TRUE),
          ],
          'native_receipts' => $row['manifest_receipt_json'] === NULL
            ? []
            : [(string) $row['manifest_receipt_resource_id'] => $this->decodeJsonObject((string) $row['manifest_receipt_json'])],
          'published_manifest' => [
            'schema_version' => trim((string) ($row['manifest_schema_version'] ?? '')),
            'dungeon_id' => trim((string) ($row['manifest_dungeon_id'] ?? '')),
            'version_id' => trim((string) ($row['manifest_version_id'] ?? '')),
            'receipt_resource_id' => trim((string) ($row['manifest_receipt_resource_id'] ?? '')),
          ],
          'scene_coverage' => [
            'complete' => in_array(strtolower(trim((string) ($row['scene_coverage_complete'] ?? ''))), ['1', 'true'], TRUE),
            'scene_count' => (int) ($row['scene_coverage_scene_count'] ?? 0),
            'dungeon_id' => trim((string) ($row['scene_coverage_dungeon_id'] ?? '')),
            'version_id' => trim((string) ($row['scene_coverage_version_id'] ?? '')),
          ],
        ],
        'publication_status' => (string) ($row['publication_status'] ?? ''),
        'created_by' => (int) ($row['created_by'] ?? 0),
        'published_by' => isset($row['published_by']) ? (int) $row['published_by'] : NULL,
        'created' => (int) ($row['created'] ?? 0),
        'updated' => (int) ($row['updated'] ?? 0),
        'published_at' => isset($row['published_at']) ? (int) $row['published_at'] : NULL,
      ];
      $release['launchable'] = $this->isLaunchablePublishedRelease($release);
      $projected[] = $release;
    }
    return $projected;
  }

  /**
   * Selector labels include unavailable publications without granting launch.
   *
   * @return array{options:array<string,string>,unavailable_ids:array<int,string>,licence_notice:string}
   */
  public function campaignSourceSelectorOptions(): array {
    $options = [];
    $unavailable = [];
    $licence_notice = '';
    foreach ($this->listPublishedCampaignSources() as $release) {
      $release_id = (string) $release['release_id'];
      $mapping = $release['mapping'];
      $options[$release_id] = sprintf(
        '%s (%s v%s%s) → %s@%s',
        (string) ($release['module_title'] ?: $release['module_id']),
        (string) $release['module_id'],
        (string) $release['module_version'],
        ($release['conversion_revision'] ?? 'base') === 'base' ? '' : ' / ' . $release['conversion_revision'],
        (string) $mapping['dungeon_id'],
        (string) $mapping['version_id']
      );
      $notice = trim((string) ($release['manifest']['license'] ?? ''));
      if ($licence_notice === '' && $notice !== '') {
        $licence_notice = $notice;
      }
    }
    if ($this->database->schema()->tableExists(self::TABLE)) {
      $rows = $this->database->select(self::TABLE, 'r')
        ->fields('r', ['release_id', 'module_id', 'module_title', 'module_version', 'conversion_revision'])
        ->condition('publication_status', self::STATUS_PUBLISHED)
        ->condition('module_id', 'project-authored-gate-check%', 'NOT LIKE')
        ->condition('module_id', '%--validation-%', 'NOT LIKE')
        ->condition('module_version', '%--validation-%', 'NOT LIKE')
        ->orderBy('updated', 'DESC')
        ->range(0, 250)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $release_id = (string) $row['release_id'];
        if (isset($options[$release_id])) {
          continue;
        }
        $options[$release_id] = sprintf(
          '%s (%s v%s%s) — published, not playable: maps and routes not converted',
          (string) ($row['module_title'] ?: $row['module_id']),
          (string) $row['module_id'],
          (string) $row['module_version'],
          ($row['conversion_revision'] ?? 'base') === 'base' ? '' : ' / ' . $row['conversion_revision']
        );
        $unavailable[] = $release_id;
      }
    }
    return ['options' => $options, 'unavailable_ids' => $unavailable, 'licence_notice' => $licence_notice];
  }

  /**
   * Resolve one release into a pinned published_dungeon campaign source.
   *
   * @return array{release_id:string,module_id:string,module_title:string,module_version:string,conversion_revision:string,archive_sha256:string,dungeon_id:string,version_id:string}
   */
  public function resolveCampaignSource(string $release_id): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      throw $this->error('module_release_table_missing', '/release_id', 'Module release table is not installed.', 503);
    }
    $release_id = trim($release_id);
    if ($release_id === '') {
      throw $this->error('module_release_id_required', '/release_id', 'module_release_id is required.', 400);
    }
    $release = $this->campaignSourceProjectionRows($release_id)[0] ?? NULL;
    if (!is_array($release)) {
      throw $this->error('module_release_not_found', '/release_id', sprintf('module_release_id=%s was not found.', $release_id), 404);
    }
    if ((string) ($release['publication_status'] ?? '') !== self::STATUS_PUBLISHED) {
      throw $this->error('module_release_not_published', '/release_id', sprintf('module_release_id=%s is not published for campaign launch.', $release_id), 409);
    }
    if (!$this->isLaunchablePublishedRelease($release)) {
      throw $this->error('module_release_native_receipts_missing', '/release_id', 'Release lacks verified native receipts, source-scene coverage, or a valid published dungeon pin.', 409);
    }
    $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
    $dungeon_id = trim((string) ($mapping['dungeon_id'] ?? ''));
    $version_id = trim((string) ($mapping['version_id'] ?? ''));
    return [
      'release_id' => (string) ($release['release_id'] ?? ''),
      'module_id' => (string) ($release['module_id'] ?? ''),
      'module_title' => (string) ($release['module_title'] ?? ''),
      'module_version' => (string) ($release['module_version'] ?? ''),
      'conversion_revision' => (string) ($release['conversion_revision'] ?? 'base'),
      'archive_sha256' => (string) ($release['archive_sha256'] ?? ''),
      'dungeon_id' => $dungeon_id,
      'version_id' => $version_id,
    ];
  }

  /**
   * Persist one deterministic release record.
   *
   * @param array<string,mixed> $inventory
   * @param array<string,mixed> $plan
   * @param array<string,mixed>|null $mapping
   * @param array<string,mixed> $overrides
   *
   * @return array<string,mixed>
   */
  public function registerRelease(array $inventory, array $plan, ?array $mapping, int $uid, array $overrides = []): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      throw $this->error('module_release_table_missing', '/release', 'Module release table is not installed.', 503);
    }
    $manifest = is_array($inventory['manifest'] ?? NULL) ? $inventory['manifest'] : [];
    $archive = is_array($inventory['archive'] ?? NULL) ? $inventory['archive'] : [];
    $module_id = trim((string) ($manifest['id'] ?? ''));
    $module_title = trim((string) ($overrides['module_title'] ?? $manifest['title'] ?? $module_id));
    $module_version = trim((string) ($manifest['version'] ?? ''));
    $conversion_revision = trim((string) ($overrides['conversion_revision'] ?? 'base'));
    $archive_sha256 = strtolower(trim((string) ($archive['sha256'] ?? '')));
    $archive_path = trim((string) ($archive['path'] ?? ''));
    $plan_hash = strtolower(trim((string) ($plan['plan_hash'] ?? '')));
    if (array_key_exists('module_id', $overrides) || array_key_exists('module_version', $overrides)) {
      throw $this->error('module_release_identity_override_invalid', '/release', 'Release identity must match the inspected manifest.', 422);
    }

    if ($module_id === '' || $module_version === '') {
      throw $this->error('module_release_manifest_identity_missing', '/manifest', 'Module manifest id and version are required to register a release.', 422);
    }
    if (!preg_match('/^[a-z0-9][a-z0-9.-]{0,63}$/D', $conversion_revision)) {
      throw $this->error('module_release_conversion_revision_invalid', '/release/conversion_revision', 'conversion_revision must be a lowercase release slug.', 422);
    }
    if (strlen($archive_sha256) !== 64 || strlen($plan_hash) !== 64) {
      throw $this->error('module_release_hash_missing', '/archive', 'Archive and plan hashes are required to register a release.', 422);
    }
    $this->assertPlanContract($plan);

    if ($mapping !== NULL && $mapping !== []) {
      throw $this->error('module_release_mapping_requires_native_receipts', '/mapping', 'A canonical dungeon mapping must come from verified native publication receipts, not intake input.', 422);
    }
    $mapping_payload = [];

    $existing = $this->database->select(self::TABLE, 'r')
      ->fields('r')
      ->condition('module_id', $module_id)
      ->condition('module_version', $module_version)
      ->condition('conversion_revision', $conversion_revision)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (is_array($existing)) {
      $existing_release = $this->normalizeRow($existing);
      if (strtolower((string) ($existing_release['archive_sha256'] ?? '')) !== $archive_sha256) {
        throw $this->error(
          'module_release_version_hash_conflict',
          '/module_version',
          sprintf('module_id=%s version=%s already exists with different archive bytes.', $module_id, $module_version),
          409,
          [
            'module_id' => $module_id,
            'module_version' => $module_version,
            'conversion_revision' => $conversion_revision,
            'existing_archive_sha256' => (string) ($existing_release['archive_sha256'] ?? ''),
            'requested_archive_sha256' => $archive_sha256,
          ],
        );
      }
      if (strtolower((string) ($existing_release['plan_hash'] ?? '')) !== $plan_hash) {
        throw $this->error(
          'module_release_plan_hash_conflict',
          '/plan_hash',
          sprintf('module_id=%s version=%s already exists with a different deterministic plan hash.', $module_id, $module_version),
          409,
          [
            'module_id' => $module_id,
            'module_version' => $module_version,
            'existing_plan_hash' => (string) ($existing_release['plan_hash'] ?? ''),
            'requested_plan_hash' => $plan_hash,
          ],
        );
      }
      $existing_status = (string) ($existing_release['publication_status'] ?? '');
      if ($existing_status === 'published') {
        if ($this->isLaunchablePublishedRelease($existing_release)) {
          throw $this->error('module_release_immutable_published', '/release_id', 'Published module releases are immutable; register a new conversion revision for changes.', 409);
        }
        throw $this->error('module_release_unverified_publication', '/release_id', 'Published release has no native conversion receipt and must be reviewed.', 409);
      }
      $next_status = in_array($existing_status, [self::STATUS_REQUIRES_AUTHORING, self::STATUS_RECEIPTS_RECORDED], TRUE)
        ? $existing_status
        : self::STATUS_REQUIRES_AUTHORING;
      $existing_mapping = is_array($existing_release['mapping'] ?? NULL) ? $existing_release['mapping'] : [];
      $no_change = strtolower((string) ($existing_release['plan_hash'] ?? '')) === $plan_hash
        && strtolower((string) ($existing_release['archive_sha256'] ?? '')) === $archive_sha256
        && (string) ($existing_release['module_title'] ?? '') === $module_title
        && (string) ($existing_release['archive_path'] ?? '') === $archive_path
        && $existing_status === $next_status;
      if ($no_change) {
        return $existing_release + ['idempotent' => TRUE];
      }

      $this->database->update(self::TABLE)
        ->fields([
          'module_title' => $module_title,
          'archive_path' => $archive_path,
          'manifest_data' => $this->encode($manifest),
          'inventory_data' => $this->encode($inventory),
          'plan_hash' => $plan_hash,
          'plan_data' => $this->encode($plan),
          'mapping_data' => $this->encode($existing_mapping !== [] ? $existing_mapping : $mapping_payload),
          'publication_status' => $next_status,
          'published_by' => $next_status === self::STATUS_REQUIRES_AUTHORING ? NULL : (isset($existing_release['published_by']) ? (int) $existing_release['published_by'] : NULL),
          'published_at' => $next_status === self::STATUS_REQUIRES_AUTHORING ? NULL : (isset($existing_release['published_at']) ? (int) $existing_release['published_at'] : NULL),
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('release_id', (string) ($existing_release['release_id'] ?? ''))
        ->execute();

      $updated = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', (string) ($existing_release['release_id'] ?? ''))
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($updated)) {
        throw $this->error('module_release_update_failed', '/release_id', 'Release row was not found after update.', 500);
      }
      return $this->normalizeRow($updated) + ['idempotent' => FALSE];
    }

    $status = self::STATUS_REQUIRES_AUTHORING;
    $now = $this->time->getRequestTime();
    $release_id = $this->uuid->generate();
    $this->database->insert(self::TABLE)
      ->fields([
        'release_id' => $release_id,
        'module_id' => $module_id,
        'module_title' => $module_title,
        'module_version' => $module_version,
        'conversion_revision' => $conversion_revision,
        'archive_sha256' => $archive_sha256,
        'archive_path' => $archive_path,
        'manifest_data' => $this->encode($manifest),
        'inventory_data' => $this->encode($inventory),
        'plan_hash' => $plan_hash,
        'plan_data' => $this->encode($plan),
        'mapping_data' => $this->encode($mapping_payload),
        'publication_status' => $status,
        'created_by' => max(0, $uid),
        'published_by' => NULL,
        'created' => $now,
        'updated' => $now,
        'published_at' => NULL,
      ])
      ->execute();

    $created = $this->database->select(self::TABLE, 'r')
      ->fields('r')
      ->condition('release_id', $release_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($created)) {
      throw $this->error('module_release_insert_failed', '/release_id', 'Release row was not found after insert.', 500);
    }
    return $this->normalizeRow($created) + ['idempotent' => FALSE];
  }

  /**
   * Persist immutable native conversion receipts for one release.
   *
   * @param array<int,array<string,mixed>> $receipts
   *
   * @return array<string,mixed>
   */
  public function registerNativeReceipts(string $release_id, array $receipts, int $uid): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      throw $this->error('module_release_table_missing', '/release_id', 'Module release table is not installed.', 503);
    }
    $release_id = trim($release_id);
    if ($release_id === '') {
      throw $this->error('module_release_id_required', '/release_id', 'module_release_id is required.', 400);
    }
    if ($receipts === []) {
      throw $this->error('module_release_receipts_required', '/receipts', 'At least one native receipt is required.', 422);
    }

    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->forUpdate()
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($row)) {
        throw $this->error('module_release_not_found', '/release_id', sprintf('module_release_id=%s was not found.', $release_id), 404);
      }
      $release = $this->normalizeRow($row);
      if ((string) ($release['publication_status'] ?? '') === 'published') {
        throw $this->error('module_release_unverified_publication', '/release_id', 'Published release has no native conversion receipt and must be reviewed.', 409);
      }
      $plan = is_array($release['plan'] ?? NULL) ? $release['plan'] : [];
      $this->assertPlanContract($plan);
      $required = $this->requiredReceiptResourceIds($plan);
      $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
      $existing_receipts = is_array($mapping['native_receipts'] ?? NULL) ? $mapping['native_receipts'] : [];
      $execution_ledger = is_array($mapping['execution_ledger'] ?? NULL) ? $mapping['execution_ledger'] : [];

      foreach ($receipts as $index => $receipt) {
        if (!is_array($receipt)) {
          throw $this->error('module_release_receipt_invalid', '/receipts/' . $index, 'Receipt must be an object.', 422);
        }
        $resource_id = trim((string) ($receipt['resource_id'] ?? ''));
        $owner = trim((string) ($receipt['owner'] ?? ''));
        $target_id = trim((string) ($receipt['target_id'] ?? ''));
        $target_version = trim((string) ($receipt['target_version'] ?? ''));
        if ($resource_id === '' || $owner === '' || $target_id === '' || $target_version === '') {
          throw $this->error('module_release_receipt_invalid', '/receipts/' . $index, 'Receipt requires resource_id, owner, target_id, and target_version.', 422);
        }
        if (!isset($required[$resource_id])) {
          throw $this->error('module_release_receipt_resource_unknown', '/receipts/' . $index . '/resource_id', 'Receipt resource_id is not required by this plan.', 422, [
            'resource_id' => $resource_id,
          ]);
        }
        $allowed_owners = $this->allowedExecutionOwnersForResource($plan, $resource_id);
        if ($allowed_owners !== [] && !in_array($owner, $allowed_owners, TRUE)) {
          throw $this->error('module_release_receipt_owner_unsupported', '/receipts/' . $index . '/owner', 'Receipt owner must match this resource native command.', 422, [
            'resource_id' => $resource_id,
            'owner' => $owner,
          ]);
        }
        $execution = $execution_ledger[$resource_id] ?? NULL;
        if (!is_array($execution) || trim((string) ($execution['state'] ?? '')) !== 'done') {
          throw $this->error('module_release_receipt_execution_incomplete', '/receipts/' . $index . '/resource_id', 'Receipt registration requires execution state=done for this resource.', 409, [
            'resource_id' => $resource_id,
            'execution_state' => is_array($execution) ? (string) ($execution['state'] ?? '') : NULL,
          ]);
        }
        if (($execution['owner'] ?? NULL) !== $owner
          || ($execution['target_id'] ?? NULL) !== $target_id
          || ($execution['target_version'] ?? NULL) !== $target_version) {
          throw $this->error('module_release_receipt_execution_mismatch', '/receipts/' . $index, 'Receipt owner and target must match the completed execution step.', 409, [
            'resource_id' => $resource_id,
          ]);
        }
        $this->assertReceiptTargetExists($owner, $target_id, $target_version, '/receipts/' . $index);
        $digest_input = [
          'resource_id' => $resource_id,
          'owner' => $owner,
          'target_id' => $target_id,
          'target_version' => $target_version,
          'source_receipt' => is_array($receipt['source_receipt'] ?? NULL) ? $receipt['source_receipt'] : [],
        ];
        $payload = [
          'resource_id' => $resource_id,
          'owner' => $owner,
          'target_id' => $target_id,
          'target_version' => $target_version,
          'source_receipt' => $digest_input['source_receipt'],
          'recorded_by' => max(0, $uid),
          'recorded_at' => $this->time->getRequestTime(),
        ];
        $payload['receipt_sha256'] = hash('sha256', $this->encode($digest_input));
        $existing = $existing_receipts[$resource_id] ?? NULL;
        if (is_array($existing)) {
          if (strtolower((string) ($existing['receipt_sha256'] ?? '')) !== strtolower((string) $payload['receipt_sha256'])) {
            throw $this->error('module_release_receipt_conflict', '/receipts/' . $index, 'Immutable receipt conflict for resource_id.', 409, [
              'resource_id' => $resource_id,
            ]);
          }
          continue;
        }
        $existing_receipts[$resource_id] = $payload;
      }

      ksort($existing_receipts);
      $mapping['native_receipts'] = $existing_receipts;
      $required_count = count($required);
      $recorded_count = count(array_intersect_key($existing_receipts, $required));
      $mapping['native_receipt_summary'] = [
        'required_count' => $required_count,
        'recorded_count' => $recorded_count,
        'complete' => $required_count > 0 && $recorded_count === $required_count,
        'recorded_at' => $this->time->getRequestTime(),
      ];
      $next_status = ($mapping['native_receipt_summary']['complete'] ?? FALSE) ? self::STATUS_RECEIPTS_RECORDED : self::STATUS_REQUIRES_AUTHORING;

      $this->database->update(self::TABLE)
        ->fields([
          'mapping_data' => $this->encode($mapping),
          'publication_status' => $next_status,
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('release_id', $release_id)
        ->execute();
      $updated = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($updated)) {
        throw $this->error('module_release_update_failed', '/release_id', 'Release row was not found after receipt update.', 500);
      }
      return $this->normalizeRow($updated);
    }
    catch (ModuleConversionReadException $e) {
      $transaction->rollBack();
      throw $e;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $this->error('module_release_receipt_registration_failed', '/receipts', 'Failed to register native receipts.', 500, ['exception' => $e->getMessage()]);
    }
  }

  /**
   * Bind a separately reviewed canonical definition through the native ledger.
   */
  public function bindReviewedDefinition(string $release_id, array $review, int $uid): array {
    $resource_id = $review['resource_id'] ?? NULL;
    $plan_hash = $review['expected_plan_hash'] ?? NULL;
    $family = $review['family'] ?? NULL;
    $definition_id = $review['definition_id'] ?? NULL;
    $version = $review['version'] ?? NULL;
    if ($uid <= 0 || !is_string($resource_id) || $resource_id === ''
      || !is_string($plan_hash) || !preg_match('/^[a-f0-9]{64}$/D', $plan_hash)
      || !in_array($family, ['actor', 'item'], TRUE)
      || !is_string($definition_id) || $definition_id === ''
      || !is_string($version) || $version === ''
      || ($review['rights_status'] ?? NULL) !== 'project_original'
      || ($review['review_confirmed'] ?? NULL) !== TRUE) {
      throw $this->error('module_release_definition_review_invalid', '/review', 'Binding requires a reviewed project-original canonical definition and exact release plan hash.', 422);
    }
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->forUpdate()
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($row)) {
        throw $this->error('module_release_not_found', '/release_id', 'Module release was not found.', 404);
      }
      $release = $this->normalizeRow($row);
      if ($release['publication_status'] === self::STATUS_PUBLISHED) {
        throw $this->error('module_release_immutable_published', '/release_id', 'Published releases cannot accept definition bindings.', 409);
      }
      if (!hash_equals($release['plan_hash'], $plan_hash)) {
        throw $this->error('module_release_plan_hash_conflict', '/review/expected_plan_hash', 'Reviewed definition was pinned to a different plan.', 409);
      }
      $this->assertPlanContract($release['plan']);
      $resource = NULL;
      foreach ($release['plan']['resources'] as $candidate) {
        if ($candidate['resource_id'] === $resource_id) {
          $resource = $candidate;
          break;
        }
      }
      $expected_family = match ($resource['resource_type'] ?? NULL) {
        'actor' => 'actor',
        'item', 'embedded_item' => 'item',
        default => NULL,
      };
      if (($resource['disposition'] ?? NULL) !== 'requires_authoring' || $expected_family !== $family) {
        throw $this->error('module_release_definition_resource_invalid', '/review/resource_id', 'Resource is not an authorable definition in this plan.', 422);
      }
      $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
      if (isset($mapping['execution_ledger'][$resource_id]) || isset($mapping['native_receipts'][$resource_id])) {
        throw $this->error('module_release_definition_already_executed', '/review/resource_id', 'This resource already has an execution or immutable receipt.', 409);
      }
      if (!$this->canonicalDefinitions->definitionExists($family, $definition_id, $version)) {
        throw $this->error('module_release_definition_not_found', '/review/definition_id', 'Reviewed canonical definition version does not exist.', 422);
      }
      $source_receipt = [
        'command_type' => 'saveDefinition',
        'authoring_mode' => 'reviewed_original_definition',
        'rights_status' => 'project_original',
        'reviewed_by' => $uid,
        'plan_hash' => $plan_hash,
      ];
      $step = [
        'resource_id' => $resource_id,
        'owner' => 'CanonicalDefinitionService',
        'state' => 'done',
        'target_id' => $definition_id,
        'target_version' => $version,
        'note' => 'reviewed project-original definition',
        'source_receipt' => $source_receipt,
      ];
      $this->registerExecutionStep($release_id, $step, $uid);
      return $this->registerNativeReceipts($release_id, [[
        'resource_id' => $resource_id,
        'owner' => $step['owner'],
        'target_id' => $definition_id,
        'target_version' => $version,
        'source_receipt' => $source_receipt,
      ]], $uid);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Persist one reviewed actor placement/categorization decision.
   */
  public function registerActorPlacementReview(string $release_id, array $review, int $uid): array {
    $resource_id = trim((string) ($review['resource_id'] ?? ''));
    $plan_hash = strtolower(trim((string) ($review['expected_plan_hash'] ?? '')));
    $status = strtolower(trim((string) ($review['status'] ?? '')));
    $origin = strtolower(trim((string) ($review['origin'] ?? 'interpretive')));
    $rationale = trim((string) ($review['rationale'] ?? ''));
    if ($uid <= 0 || $resource_id === '' || !preg_match('/^[a-f0-9]{64}$/D', $plan_hash)
      || !in_array($status, self::ACTOR_REVIEW_STATUSES, TRUE)
      || !in_array($origin, self::ACTOR_REVIEW_ORIGINS, TRUE)
      || $rationale === '') {
      throw $this->error(
        'module_release_actor_review_invalid',
        '/review',
        'Actor review requires resource_id, expected_plan_hash, status, origin, and rationale.',
        422
      );
    }
    $source_evidence = $this->normalizeActorReviewEvidence($review['source_evidence'] ?? []);
    $placement = $status === 'placed'
      ? $this->normalizeActorReviewPlacement($review['placement'] ?? NULL)
      : NULL;

    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', trim($release_id))
        ->forUpdate()
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($row)) {
        throw $this->error('module_release_not_found', '/release_id', 'Module release was not found.', 404);
      }
      $release = $this->normalizeRow($row);
      if ($release['publication_status'] === self::STATUS_PUBLISHED) {
        throw $this->error('module_release_immutable_published', '/release_id', 'Published releases cannot accept actor-placement reviews.', 409);
      }
      if (!hash_equals((string) $release['plan_hash'], $plan_hash)) {
        throw $this->error('module_release_plan_hash_conflict', '/review/expected_plan_hash', 'Actor review was pinned to a different plan.', 409);
      }
      $this->assertPlanContract((array) ($release['plan'] ?? []));
      $resource = NULL;
      foreach ((array) ($release['plan']['resources'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['resource_id'] ?? NULL) === $resource_id) {
          $resource = $candidate;
          break;
        }
      }
      if (!is_array($resource)
        || ($resource['resource_type'] ?? NULL) !== 'actor'
        || ($resource['disposition'] ?? NULL) !== 'requires_authoring') {
        throw $this->error('module_release_actor_review_resource_invalid', '/review/resource_id', 'Actor review resource_id must reference an authorable actor resource in this release plan.', 422);
      }
      $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
      $reviews = is_array($mapping['actor_placement_reviews'] ?? NULL) ? $mapping['actor_placement_reviews'] : [];
      $reviews[$resource_id] = [
        'resource_id' => $resource_id,
        'status' => $status,
        'origin' => $origin,
        'rationale' => $rationale,
        'plan_hash' => $plan_hash,
        'reviewed_by' => $uid,
        'reviewed_at' => $this->time->getRequestTime(),
        'source_evidence' => $source_evidence,
      ] + ($placement === NULL ? [] : ['placement' => $placement]);
      ksort($reviews);
      $mapping['actor_placement_reviews'] = $reviews;
      $this->database->update(self::TABLE)
        ->fields([
          'mapping_data' => $this->encode($mapping),
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('release_id', trim($release_id))
        ->execute();
      $updated = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', trim($release_id))
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($updated)) {
        throw $this->error('module_release_update_failed', '/release_id', 'Release row was not found after actor review update.', 500);
      }
      return $this->normalizeRow($updated);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Reuse verified native executions for unchanged resources of the same archive.
   *
   * Scene and adventure authoring always runs anew. Presence-only intake
   * diagnostics do not change a native command or its source payload.
   *
   * @return array{reused_count:int,changed_resource_ids:array<int,string>}
   */
  public function reuseVerifiedExecution(string $release_id, string $source_release_id, int $uid): array {
    if (trim($release_id) === '' || trim($source_release_id) === '' || $release_id === $source_release_id || $uid <= 0) {
      throw $this->error('module_release_reuse_identity_invalid', '/release_id', 'Receipt reuse requires distinct release identities and an author.', 422);
    }
    $transaction = $this->database->startTransaction();
    try {
      $source_row = $this->database->select(self::TABLE, 'r')
        ->fields('r', ['module_id', 'module_version', 'archive_sha256', 'publication_status', 'plan_data', 'mapping_data'])
        ->condition('release_id', $source_release_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      $target_row = $this->database->select(self::TABLE, 'r')
        ->fields('r', ['module_id', 'module_version', 'archive_sha256', 'publication_status', 'plan_data', 'mapping_data'])
        ->condition('release_id', $release_id)
        ->forUpdate()
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($source_row) || !is_array($target_row)) {
        throw $this->error('module_release_reuse_source_missing', '/release_id', 'Receipt reuse requires both release rows.', 404);
      }
      $source = $source_row + [
        'plan' => json_decode((string) $source_row['plan_data'], TRUE, 512, JSON_THROW_ON_ERROR),
        'mapping' => json_decode((string) $source_row['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR),
      ];
      $target = $target_row + [
        'plan' => json_decode((string) $target_row['plan_data'], TRUE, 512, JSON_THROW_ON_ERROR),
        'mapping' => json_decode((string) $target_row['mapping_data'], TRUE, 512, JSON_THROW_ON_ERROR),
      ];
      unset($source['plan_data'], $source['mapping_data'], $target['plan_data'], $target['mapping_data']);
      unset($source_row, $target_row);
      if (!is_array($source['plan']) || !is_array($target['plan'])
        || !is_array($source['mapping']) || !is_array($target['mapping'])) {
        throw $this->error('module_release_reuse_source_invalid', '/release_id', 'Release plan and mapping must be JSON objects.', 422);
      }
      if ($source['publication_status'] !== self::STATUS_PUBLISHED
        || $target['publication_status'] === self::STATUS_PUBLISHED
        || $source['module_id'] !== $target['module_id']
        || $source['module_version'] !== $target['module_version']
        || $source['archive_sha256'] !== $target['archive_sha256']
        || ($source['mapping']['native_receipt_summary']['complete'] ?? FALSE) !== TRUE) {
        throw $this->error('module_release_reuse_source_invalid', '/release_id', 'Only a complete published release from identical archive bytes can supply reusable executions.', 422);
      }
      $this->assertPlanContract($source['plan']);
      $this->assertPlanContract($target['plan']);
      $source_resources = array_column($source['plan']['resources'], NULL, 'resource_id');
      $target_resources = array_column($target['plan']['resources'], NULL, 'resource_id');
      $required = $this->requiredReceiptResourceIds($target['plan']);
      $source_receipts = $source['mapping']['native_receipts'] ?? [];
      $source_ledger = $source['mapping']['execution_ledger'] ?? [];
      $mapping = $target['mapping'];
      $ledger = is_array($mapping['execution_ledger'] ?? NULL) ? $mapping['execution_ledger'] : [];
      $reused = 0;
      $changed = [];
      foreach ($required as $resource_id => $_) {
        $resource = $target_resources[$resource_id];
        if (in_array($resource['resource_type'], ['scene', 'adventure'], TRUE)) {
          continue;
        }
        $previous = $source_resources[$resource_id] ?? NULL;
        if (!is_array($previous) || !$this->resourceExecutionInputsEqual($previous, $resource)) {
          $changed[] = $resource_id;
          continue;
        }
        $receipt = $source_receipts[$resource_id] ?? NULL;
        $execution = $source_ledger[$resource_id] ?? NULL;
        if (!is_array($receipt) || !is_array($execution)
          || ($execution['state'] ?? NULL) !== 'done'
          || ($receipt['resource_id'] ?? NULL) !== $resource_id
          || ($execution['resource_id'] ?? NULL) !== $resource_id
          || ($receipt['owner'] ?? NULL) !== ($execution['owner'] ?? NULL)
          || ($receipt['target_id'] ?? NULL) !== ($execution['target_id'] ?? NULL)
          || ($receipt['target_version'] ?? NULL) !== ($execution['target_version'] ?? NULL)
          || ($receipt['source_receipt'] ?? []) !== ($execution['source_receipt'] ?? [])) {
          throw $this->error('module_release_reuse_receipt_invalid', '/mapping/native_receipts/' . $resource_id, 'Source execution and native receipt disagree.', 422);
        }
        $digest = [
          'resource_id' => $resource_id,
          'owner' => $receipt['owner'],
          'target_id' => $receipt['target_id'],
          'target_version' => $receipt['target_version'],
          'source_receipt' => $receipt['source_receipt'] ?? [],
        ];
        if (!hash_equals((string) ($receipt['receipt_sha256'] ?? ''), hash('sha256', $this->encode($digest)))) {
          throw $this->error('module_release_reuse_receipt_invalid', '/mapping/native_receipts/' . $resource_id, 'Source native receipt digest is invalid.', 422);
        }
        $this->assertReceiptTargetExists((string) $receipt['owner'], (string) $receipt['target_id'], (string) $receipt['target_version'], '/mapping/native_receipts/' . $resource_id);
        $existing = $ledger[$resource_id] ?? NULL;
        if (is_array($existing) && ($existing['state'] ?? NULL) === 'done'
          && (($existing['owner'] ?? NULL) !== $receipt['owner']
            || ($existing['target_id'] ?? NULL) !== $receipt['target_id']
            || ($existing['target_version'] ?? NULL) !== $receipt['target_version']
            || ($existing['source_receipt'] ?? []) !== ($receipt['source_receipt'] ?? []))) {
          throw $this->error('module_release_reuse_conflict', '/mapping/execution_ledger/' . $resource_id, 'Existing completed execution has a different immutable target.', 409);
        }
        if (!is_array($existing) || ($existing['state'] ?? NULL) !== 'done') {
          $ledger[$resource_id] = $this->buildExecutionEntry(
            $resource_id,
            (string) $receipt['owner'],
            'done',
            (string) $receipt['target_id'],
            (string) $receipt['target_version'],
            'verified execution reused from ' . $source_release_id,
            $uid,
            (array) ($receipt['source_receipt'] ?? [])
          );
        }
        $reused++;
      }
      ksort($ledger);
      $mapping['execution_ledger'] = $ledger;
      $mapping['execution_summary'] = $this->buildExecutionSummary($required, $ledger);
      $this->database->update(self::TABLE)
        ->fields(['mapping_data' => $this->encode($mapping), 'updated' => $this->time->getRequestTime()])
        ->condition('release_id', $release_id)
        ->execute();
      return ['reused_count' => $reused, 'changed_resource_ids' => $changed];
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      if ($e instanceof ModuleConversionReadException) {
        throw $e;
      }
      throw $this->error('module_release_reuse_failed', '/release_id', 'Failed to reuse verified native executions.', 500, ['exception' => $e->getMessage()], $e);
    }
  }

  private function resourceExecutionInputsEqual(array $previous, array $current): bool {
    if (!is_array($previous['source'] ?? NULL) || !is_array($current['source'] ?? NULL)) {
      return FALSE;
    }
    unset($previous['source']['missing_source_fields'], $current['source']['missing_source_fields']);
    return $previous === $current;
  }

  /**
   * Persist deterministic execution state for one planned resource.
   *
   * @param array<string,mixed> $step
   *
   * @return array<string,mixed>
   */
  public function registerExecutionStep(string $release_id, array $step, int $uid): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      throw $this->error('module_release_table_missing', '/release_id', 'Module release table is not installed.', 503);
    }
    $release_id = trim($release_id);
    if ($release_id === '') {
      throw $this->error('module_release_id_required', '/release_id', 'module_release_id is required.', 400);
    }
    $resource_id = trim((string) ($step['resource_id'] ?? ''));
    $owner = trim((string) ($step['owner'] ?? ''));
    $state = strtolower(trim((string) ($step['state'] ?? '')));
    if ($resource_id === '' || $owner === '' || !in_array($state, self::EXECUTION_STATES, TRUE)) {
      throw $this->error('module_release_execution_step_invalid', '/step', 'Execution step requires resource_id, owner, and valid state.', 422);
    }
    $target_id = trim((string) ($step['target_id'] ?? ''));
    $target_version = trim((string) ($step['target_version'] ?? ''));
    if ($state === 'done' && ($target_id === '' || $target_version === '')) {
      throw $this->error('module_release_execution_step_invalid', '/step', 'Execution step state=done requires target_id and target_version.', 422);
    }
    $source_receipt = $step['source_receipt'] ?? [];
    if (!is_array($source_receipt) || ($source_receipt !== [] && array_is_list($source_receipt))) {
      throw $this->error('module_release_execution_step_invalid', '/step/source_receipt', 'Execution provenance must be a JSON object.', 422);
    }

    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->forUpdate()
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($row)) {
        throw $this->error('module_release_not_found', '/release_id', sprintf('module_release_id=%s was not found.', $release_id), 404);
      }
      $release = $this->normalizeRow($row);
      if ((string) ($release['publication_status'] ?? '') === self::STATUS_PUBLISHED) {
        throw $this->error('module_release_immutable_published', '/release_id', 'Published module releases are immutable; execution state cannot be changed.', 409);
      }
      $plan = is_array($release['plan'] ?? NULL) ? $release['plan'] : [];
      $this->assertPlanContract($plan);
      $required = $this->requiredReceiptResourceIds($plan);
      if (!isset($required[$resource_id])) {
        throw $this->error('module_release_execution_resource_unknown', '/step/resource_id', 'Execution resource_id is not required by this plan.', 422, [
          'resource_id' => $resource_id,
        ]);
      }
      $allowed_owners = $this->allowedExecutionOwnersForResource($plan, $resource_id);
      if ($allowed_owners !== [] && !in_array($owner, $allowed_owners, TRUE)) {
        throw $this->error('module_release_execution_owner_mismatch', '/step/owner', 'Execution owner must match a native command owner declared in the plan for this resource.', 422, [
          'resource_id' => $resource_id,
          'owner' => $owner,
          'allowed_owners' => $allowed_owners,
        ]);
      }
      $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
      $execution_ledger = is_array($mapping['execution_ledger'] ?? NULL) ? $mapping['execution_ledger'] : [];
      $existing = $execution_ledger[$resource_id] ?? NULL;
      if (is_array($existing)) {
        $existing_state = strtolower(trim((string) ($existing['state'] ?? '')));
        if ($existing_state === 'done' && $state !== 'done') {
          throw $this->error('module_release_execution_state_regression', '/step/state', 'Execution state cannot regress once done.', 409, [
            'resource_id' => $resource_id,
            'existing_state' => $existing_state,
            'requested_state' => $state,
          ]);
        }
        if ($existing_state === 'done'
          && (($existing['owner'] ?? NULL) !== $owner
            || ($existing['target_id'] ?? NULL) !== $target_id
            || ($existing['target_version'] ?? NULL) !== $target_version)) {
          throw $this->error('module_release_execution_target_conflict', '/step', 'Completed execution target is immutable.', 409, [
            'resource_id' => $resource_id,
          ]);
        }
      }

      if ($state === 'done') {
        $this->assertReceiptTargetExists($owner, $target_id, $target_version, '/step');
      }
      $entry = [
        'resource_id' => $resource_id,
        'owner' => $owner,
        'state' => $state,
        'target_id' => $target_id === '' ? NULL : $target_id,
        'target_version' => $target_version === '' ? NULL : $target_version,
        'note' => isset($step['note']) ? trim((string) $step['note']) : '',
        'source_receipt' => $source_receipt,
        'updated_by' => max(0, $uid),
        'updated_at' => $this->time->getRequestTime(),
      ];
      $entry['entry_sha256'] = hash('sha256', $this->encode([
        'resource_id' => $entry['resource_id'],
        'owner' => $entry['owner'],
        'state' => $entry['state'],
        'target_id' => $entry['target_id'],
        'target_version' => $entry['target_version'],
        'note' => $entry['note'],
        'source_receipt' => $entry['source_receipt'],
      ]));
      $execution_ledger[$resource_id] = $entry;
      ksort($execution_ledger);
      $mapping['execution_ledger'] = $execution_ledger;
      $mapping['execution_summary'] = $this->buildExecutionSummary($required, $execution_ledger);

      $this->database->update(self::TABLE)
        ->fields([
          'mapping_data' => $this->encode($mapping),
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('release_id', $release_id)
        ->execute();
      $updated = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($updated)) {
        throw $this->error('module_release_update_failed', '/release_id', 'Release row was not found after execution update.', 500);
      }
      return $this->normalizeRow($updated);
    }
    catch (ModuleConversionReadException $e) {
      $transaction->rollBack();
      throw $e;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $this->error('module_release_execution_registration_failed', '/step', 'Failed to register execution step.', 500, ['exception' => $e->getMessage()]);
    }
  }

  /**
   * Execute deterministic plan commands and persist execution states.
   *
   * @param array<string,mixed> $request
   *
   * @return array{release:array<string,mixed>,run:array<string,mixed>}
   */
  public function executePlannedCommands(string $release_id, array $request, int $uid): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      throw $this->error('module_release_table_missing', '/release_id', 'Module release table is not installed.', 503);
    }
    $release_id = trim($release_id);
    if ($release_id === '') {
      throw $this->error('module_release_id_required', '/release_id', 'module_release_id is required.', 400);
    }
    $resource_filter = $this->normalizeResourceFilter($request['resource_ids'] ?? NULL);
    $publish_version = trim((string) ($request['publish_version'] ?? '1.0.0'));
    $register_receipts = (bool) ($request['register_receipts'] ?? FALSE);
    if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $publish_version)) {
      throw $this->error('module_release_execution_publish_version_invalid', '/publish_version', 'publish_version must be semantic version text (for example 1.0.0).', 422);
    }

    $staged_maps = [];
    $prepared_rooms = [];
    $snapshot = NULL;
    $transaction = NULL;
    try {
      // Canonical plans need real editor drafts, but provider latency must not
      // hold the release lock. Recheck the complete snapshot under that lock.
      if (array_key_exists('original_scene_generation', $request) && $request['original_scene_generation'] !== NULL) {
        $snapshot = $this->database->select(self::TABLE, 'r')
          ->fields('r')
          ->condition('release_id', $release_id)
          ->range(0, 1)
          ->execute()
          ->fetchAssoc();
        if (!is_array($snapshot)) {
          throw $this->error('module_release_not_found', '/release_id', sprintf('module_release_id=%s was not found.', $release_id), 404);
        }
        $preflight_release = $this->normalizeRow($snapshot);
        if ($preflight_release['publication_status'] === self::STATUS_PUBLISHED) {
          throw $this->error('module_release_immutable_published', '/release_id', 'Published module releases are immutable; execution state cannot be changed.', 409);
        }
        $preflight_plan = $preflight_release['plan'];
        $this->assertPlanContract($preflight_plan);
        $preflight_resources = array_column($preflight_plan['resources'], NULL, 'resource_id');
        $preflight_generation = $this->originalGenerationRequest($request['original_scene_generation'], $preflight_resources, $resource_filter, $uid, $publish_version);
        $preflight_mapping = $preflight_release['mapping'];
        $this->assertOriginalGenerationAvailable($preflight_generation['execution_order'], $preflight_mapping);
        foreach ($preflight_generation['execution_order'] as $resource_id) {
          $resource = $preflight_resources[$resource_id];
          if ($resource['resource_type'] !== 'scene') {
            continue;
          }
          $draft = $this->roomEditor->createDraft(NULL);
          $draft_id = (string) ($draft['draft_id'] ?? '');
          if ($draft_id === '') {
            throw $this->error('module_release_execution_draft_missing', '/resource', 'Room conversion draft_id is missing.', 500, ['resource_id' => $resource_id]);
          }
          $scene_index = count($prepared_rooms);
          $prepared_rooms[$resource_id] = ['draft_id' => $draft_id];
          $context = new RoomEditorGmToolContext($draft_id, 'publication', $this->roomEditor, $this->canonicalDefinitions);
          try {
            $prepared_rooms[$resource_id]['plan'] = $this->sceneGeneration->prepareRoom(
              $resource, $context, $preflight_generation['scene_definitions'][$resource_id]['definitions'],
              $preflight_generation['seed'] + $scene_index,
              $preflight_generation['scene_definitions'][$resource_id]['original_geometry']['size_category'] ?? NULL
            );
          }
          catch (\Throwable $e) {
            throw $this->error('module_release_original_generation_failed', '/original_scene_generation', 'Original scene generation failed; no execution receipts were committed.', 422, $this->generationFailureContext($resource_id, $e), $e);
          }
        }
      }
      $transaction = $this->database->startTransaction();
      $row = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->forUpdate()
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($row)) {
        throw $this->error('module_release_not_found', '/release_id', sprintf('module_release_id=%s was not found.', $release_id), 404);
      }
      if ($snapshot !== NULL && $row !== $snapshot) {
        throw $this->error('module_release_original_generation_conflict', '/release_id', 'Release changed while generating scenes; retry with current release state.', 409);
      }
      $release = $this->normalizeRow($row);
      if ((string) ($release['publication_status'] ?? '') === self::STATUS_PUBLISHED) {
        throw $this->error('module_release_immutable_published', '/release_id', 'Published module releases are immutable; execution state cannot be changed.', 409);
      }
      $plan = is_array($release['plan'] ?? NULL) ? $release['plan'] : [];
      $this->assertPlanContract($plan);
      $required = $this->requiredReceiptResourceIds($plan);
      if ($required === []) {
        throw $this->error('module_release_execution_no_required_resources', '/plan', 'No executable resources are declared for this plan.', 422);
      }
      $resources = [];
      foreach ((array) ($plan['resources'] ?? []) as $resource) {
        if (!is_array($resource)) {
          continue;
        }
        $resource_id = trim((string) ($resource['resource_id'] ?? ''));
        if ($resource_id !== '') {
          $resources[$resource_id] = $resource;
        }
      }
      $generation = $this->originalGenerationRequest(
        array_key_exists('original_scene_generation', $request) ? $request['original_scene_generation'] : NULL,
        $resources, $resource_filter, $uid, $publish_version
      );
      if (array_key_exists('original_scene_generation', $request) && $generation === NULL) {
        throw $this->error('module_release_original_generation_invalid', '/original_scene_generation', 'Original generation opt-in must be a reviewed object.', 422);
      }
      $selected_resource_ids = $resource_filter;
      if ($selected_resource_ids === []) {
        $selected_resource_ids = $generation === NULL
          ? array_values(array_keys($required))
          : $generation['execution_order'];
      }
      if ($generation !== NULL) {
        $selected_resource_ids = $generation['execution_order'];
      }
      $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
      $execution_ledger = is_array($mapping['execution_ledger'] ?? NULL) ? $mapping['execution_ledger'] : [];
      if ($generation !== NULL) {
        $this->assertOriginalGenerationAvailable($selected_resource_ids, $mapping);
      }
      $result = [
        'selected_resource_ids' => $selected_resource_ids,
        'succeeded' => 0,
        'failed' => 0,
        'details' => [],
        'updated_at' => $this->time->getRequestTime(),
      ];
      $scene_receipts = [];
      foreach ($selected_resource_ids as $resource_id) {
        if (!isset($required[$resource_id])) {
          throw $this->error('module_release_execution_resource_unknown', '/resource_ids', 'Execution resource_id is not required by this plan.', 422, [
            'resource_id' => $resource_id,
          ]);
        }
        $resource = $resources[$resource_id] ?? NULL;
        if (!is_array($resource)) {
          throw $this->error('module_release_execution_resource_missing', '/plan/resources', 'Plan resource was not found for required resource_id.', 500, [
            'resource_id' => $resource_id,
          ]);
        }
        $owners = $this->allowedExecutionOwnersForResource($plan, $resource_id);
        if ($owners === []) {
          $owners = ['ModuleConversionRunner'];
        }
        $owner = $owners[0];
        $execution_ledger[$resource_id] = $this->buildExecutionEntry($resource_id, $owner, 'in_progress', NULL, NULL, 'conversion command execution started', $uid);
        try {
          if ($generation !== NULL && ($resource['resource_type'] ?? NULL) === 'scene') {
            $settings = $generation['scene_definitions'][$resource_id];
            $maps = array_key_exists('maps', $settings)
              ? $this->mapStaging->stageSceneAssets((string) $release['archive_path'], $release['inventory'], $resource_id, $settings['maps'], $uid)
              : (array_key_exists('original_map', $settings)
                ? ['maps' => $this->reviewedOriginalMapAssets($settings['original_map']), 'stored' => []]
                : NULL);
            if ($maps !== NULL) {
              array_push($staged_maps, ...$maps['stored']);
            }
            $receipt = $this->executeRoomMetadataCommand($resource, $resource['native_commands'][0], $publish_version, $settings, $resource_id === $generation['terminal_scene_resource_id'], $maps['maps'] ?? NULL, $prepared_rooms[$resource_id], $uid);
            $scene_receipts[] = array_intersect_key($receipt, array_flip(['resource_id', 'owner', 'target_id', 'target_version']));
          }
          elseif ($generation !== NULL) {
            $quest_author = isset($generation['quest'])
              ? fn(array $dungeon): string => $this->authorOriginalQuest($generation['quest'], $scene_receipts, $dungeon, $release_id, $uid)
              : NULL;
            $assembly = $this->dungeonAssembly->assembleDungeon($scene_receipts, $generation['dungeon'], $publish_version, $uid, $generation['seed'], $generation['map_attachment_state'], $quest_author);
            $published = $assembly['published'];
            $receipt = [
              'resource_id' => $resource_id,
              'owner' => 'DungeonEditorService',
              'target_id' => (string) ($published['dungeon_id'] ?? ''),
              'target_version' => (string) ($published['published_version_id'] ?? $published['version_id'] ?? ''),
              'source_receipt' => ['command_type' => 'set_dungeon_metadata', 'publish_version' => $publish_version, 'execution_mode' => 'original_scene_generation', 'topology_origin' => $assembly['topology_origin'], ...(isset($generation['quest']) ? ['quest_template_id' => $generation['quest']['template_id']] : [])],
            ];
          }
          else {
            $receipt = $this->executeResourceCommand($resource, $publish_version);
          }
          $this->assertReceiptTargetExists(
            (string) $receipt['owner'],
            (string) $receipt['target_id'],
            (string) $receipt['target_version'],
            '/resource'
          );
          $execution_ledger[$resource_id] = $this->buildExecutionEntry(
            $resource_id,
            (string) $receipt['owner'],
            'done',
            (string) $receipt['target_id'],
            (string) $receipt['target_version'],
            'conversion command execution complete',
            $uid,
            is_array($receipt['source_receipt'] ?? NULL) ? $receipt['source_receipt'] : []
          );
          $result['succeeded']++;
          $result['details'][] = [
            'resource_id' => $resource_id,
            'state' => 'done',
            'owner' => $receipt['owner'],
            'target_id' => $receipt['target_id'],
            'target_version' => $receipt['target_version'],
          ];
        }
        catch (\Throwable $e) {
          if ($generation !== NULL) {
            throw $this->error('module_release_original_generation_failed', '/original_scene_generation', 'Original scene generation or dungeon assembly failed; no execution receipts were committed.', 422, $this->generationFailureContext($resource_id, $e), $e);
          }
          $execution_ledger[$resource_id] = $this->buildExecutionEntry(
            $resource_id,
            $owner,
            'failed',
            NULL,
            NULL,
            'conversion command execution failed: ' . strtok($e->getMessage(), "\n"),
            $uid
          );
          $result['failed']++;
          $result['details'][] = [
            'resource_id' => $resource_id,
            'state' => 'failed',
            'owner' => $owner,
            'error' => $e->getMessage(),
          ];
        }
      }
      ksort($execution_ledger);
      $mapping['execution_ledger'] = $execution_ledger;
      $mapping['execution_summary'] = $this->buildExecutionSummary($required, $execution_ledger);
      $mapping['execution_last_run'] = $result;

      $this->database->update(self::TABLE)
        ->fields([
          'mapping_data' => $this->encode($mapping),
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('release_id', $release_id)
        ->execute();
      $updated = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($updated)) {
        throw $this->error('module_release_update_failed', '/release_id', 'Release row was not found after execution run.', 500);
      }
      $normalized_release = $this->normalizeRow($updated);
      $summary = is_array($mapping['execution_summary'] ?? NULL) ? $mapping['execution_summary'] : [];
      $result['receipts_registered'] = FALSE;
      if ($register_receipts && (($summary['complete'] ?? FALSE) === TRUE)) {
        $receipts = [];
        foreach (array_keys($required) as $resource_id) {
          $entry = $execution_ledger[$resource_id] ?? NULL;
          if (!is_array($entry)) {
            continue;
          }
          $target_id = trim((string) ($entry['target_id'] ?? ''));
          $target_version = trim((string) ($entry['target_version'] ?? ''));
          if (strtolower(trim((string) ($entry['state'] ?? ''))) !== 'done'
            || $target_id === ''
            || $target_version === '') {
            continue;
          }
          $receipts[] = [
            'resource_id' => $resource_id,
            'owner' => trim((string) ($entry['owner'] ?? '')),
            'target_id' => $target_id,
            'target_version' => $target_version,
            'source_receipt' => is_array($entry['source_receipt'] ?? NULL) ? $entry['source_receipt'] : [],
          ];
        }
        if (count($receipts) === count($required)) {
          $normalized_release = $this->registerNativeReceipts($release_id, $receipts, $uid);
          $result['receipts_registered'] = TRUE;
        }
      }
      return [
        'release' => $normalized_release,
        'run' => $result,
      ];
    }
    catch (ModuleConversionReadException $e) {
      try {
        $this->mapStaging->discardSceneAssets($staged_maps);
      }
      finally {
        try {
          if ($transaction !== NULL) {
            $transaction->rollBack();
          }
        }
        finally {
          $this->discardPreparedRooms($prepared_rooms);
        }
      }
      throw $e;
    }
    catch (\Throwable $e) {
      try {
        $this->mapStaging->discardSceneAssets($staged_maps);
      }
      finally {
        try {
          if ($transaction !== NULL) {
            $transaction->rollBack();
          }
        }
        finally {
          $this->discardPreparedRooms($prepared_rooms);
        }
      }
      throw $this->error('module_release_execution_run_failed', '/resource_ids', 'Failed to execute planned conversion commands.', 500, ['exception' => $e->getMessage()]);
    }

  }

  /**
   * Remove only drafts this execution created before its transaction began.
   */
  private function discardPreparedRooms(array $prepared_rooms): void {
    foreach ($prepared_rooms as $prepared) {
      $draft_id = $prepared['draft_id'];
      $this->database->delete('dungeoncrawler_content_room_editor_commands')->condition('draft_id', $draft_id)->execute();
      $this->database->delete('dungeoncrawler_content_room_editor_drafts')->condition('draft_id', $draft_id)->execute();
    }
  }

  /**
   * Preserve structured rejection codes without copying finding messages.
   */
  private function generationFailureContext(string $resource_id, \Throwable $exception): array {
    $context = ['resource_id' => $resource_id, 'exception' => $exception->getMessage()];
    if ($exception instanceof DungeonEditorFindingsInterface) {
      $context['cause_codes'] = array_values(array_map(
        static fn(array $finding): string => (string) $finding['code'],
        $exception->getFindings()
      ));
    }
    return $context;
  }

  private function assertOriginalGenerationAvailable(array $resource_ids, array $mapping): void {
    foreach ($resource_ids as $resource_id) {
      if (($mapping['execution_ledger'][$resource_id]['state'] ?? NULL) === 'done'
        || isset($mapping['native_receipts'][$resource_id])) {
        throw $this->error('module_release_original_generation_conflict', '/original_scene_generation', 'Reviewed batch cannot replace an existing scene or adventure receipt.', 409, ['resource_id' => $resource_id]);
      }
    }
  }

  /**
   * Require one reviewed, complete batch so no metadata-only room can be
   * substituted for a generated scene when assembling the adventure.
   */
  private function originalGenerationRequest(mixed $input, array $resources, array $filter, int $uid, string $version): ?array {
    if ($input === NULL) {
      return NULL;
    }
    $invalid = fn(): ModuleConversionReadException => $this->error('module_release_original_generation_invalid', '/original_scene_generation', 'Provide exactly one adventure, every scene, a single terminal scene, reviewed original definitions and publication-safe original metadata.', 422);
    if (!is_array($input) || array_is_list($input)
      || array_diff(array_keys($input), ['scene_definitions', 'terminal_scene_resource_id', 'dungeon', 'seed', 'quest']) !== []
      || !in_array(count($input), [4, 5], TRUE) || !is_int($input['seed'] ?? NULL)
      || $input['seed'] < 0 || $input['seed'] > 2147483631
      || $uid <= 0 || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
      throw $invalid();
    }
    $scenes = [];
    $adventures = [];
    foreach ($resources as $id => $resource) {
      if (($resource['resource_type'] ?? NULL) === 'scene') {
        $scenes[$id] = $resource;
      }
      elseif (($resource['resource_type'] ?? NULL) === 'adventure') {
        $adventures[$id] = $resource;
      }
    }
    $definitions = $input['scene_definitions'] ?? NULL;
    if (count($scenes) < 3 || count($scenes) > 16 || count($adventures) !== 1
      || !is_array($definitions) || array_is_list($definitions)
      || array_diff_key($scenes, $definitions) !== [] || array_diff_key($definitions, $scenes) !== []
      || !isset($scenes[$input['terminal_scene_resource_id'] ?? ''])
      || !is_array($input['dungeon'] ?? NULL)) {
      throw $invalid();
    }
    $expected = array_merge(array_keys($scenes), array_keys($adventures));
    $selected = $filter;
    sort($selected);
    $sorted = $expected;
    sort($sorted);
    if ($filter !== [] && $selected !== $sorted) {
      throw $invalid();
    }
    foreach (array_merge($scenes, $adventures) as $resource) {
      if (($resource['disposition'] ?? NULL) !== 'requires_authoring'
        || count((array) ($resource['native_commands'] ?? [])) !== 1
        || ($resource['native_commands'][0]['command_type'] ?? NULL) !== (($resource['resource_type'] === 'scene') ? 'set_room_metadata' : 'set_dungeon_metadata')) {
        throw $invalid();
      }
    }
    foreach ($definitions as $settings) {
      if (!is_array($settings) || !in_array(count($settings), [2, 3, 4, 5, 6], TRUE)
        || array_diff(array_keys($settings), ['metadata', 'definitions', 'maps', 'original_map', 'map_alignment', 'map_review', 'original_geometry']) !== []
        || !is_array($settings['metadata'] ?? NULL)
        || !is_array($settings['definitions'] ?? NULL)
        || !array_is_list($settings['definitions']) || $settings['definitions'] === []) {
        throw $invalid();
      }
      $metadata = $settings['metadata'];
      if (count($metadata) !== 4
        || array_diff(array_keys($metadata), ['name', 'description', 'theme', 'rights_status']) !== []
        || ($metadata['rights_status'] ?? NULL) !== 'project_original'
        || !is_string($metadata['name'] ?? NULL) || trim($metadata['name']) === '' || mb_strlen($metadata['name']) > 200
        || !is_string($metadata['description'] ?? NULL) || trim($metadata['description']) === '' || mb_strlen($metadata['description']) > 2000
        || !is_string($metadata['theme'] ?? NULL) || trim($metadata['theme']) === '' || mb_strlen($metadata['theme']) > 100) {
        throw $invalid();
      }
    }
    $map_count = count(array_filter($definitions, static fn(array $settings): bool => array_key_exists('maps', $settings)));
    $original_map_count = count(array_filter($definitions, static fn(array $settings): bool => array_key_exists('original_map', $settings)));
    if ($map_count + $original_map_count !== 0 && $map_count + $original_map_count !== count($scenes)) {
      throw $invalid();
    }
    foreach ($definitions as $resource_id => $settings) {
      if (array_key_exists('original_map', $settings)) {
        $original_map = $settings['original_map'];
        if (array_key_exists('maps', $settings) || array_key_exists('map_alignment', $settings)
          || array_key_exists('map_review', $settings) || !is_array($original_map)
          || array_is_list($original_map) || count($original_map) !== 4
          || array_diff(array_keys($original_map), ['image_id', 'sha256', 'rights_status', 'review_notes']) !== []
          || !is_int($original_map['image_id'] ?? NULL) || $original_map['image_id'] <= 0
          || !is_string($original_map['sha256'] ?? NULL) || !preg_match('/^[a-f0-9]{64}$/D', $original_map['sha256'])
          || ($original_map['rights_status'] ?? NULL) !== 'project_original'
          || !is_string($original_map['review_notes'] ?? NULL)
          || mb_strlen(trim($original_map['review_notes'])) < 30 || mb_strlen($original_map['review_notes']) > 1000) {
          throw $this->error('module_release_original_map_review_invalid', '/original_scene_generation/scene_definitions/' . $resource_id . '/original_map', 'Original map requires an existing generated-image ID, exact image hash, and explicit project-original review.', 422);
        }
      }
      $scene_source = $scenes[$resource_id]['source'] ?? [];
      $source_geometry = $scene_source['source_context'] ?? [];
      $missing_geometry = array_intersect(['width', 'height', 'grid_size'], (array) ($scene_source['missing_source_fields'] ?? [])) !== []
        || !is_int($source_geometry['width'] ?? NULL) || $source_geometry['width'] <= 0
        || !is_int($source_geometry['height'] ?? NULL) || $source_geometry['height'] <= 0
        || !is_int($source_geometry['grid_size'] ?? NULL) || $source_geometry['grid_size'] <= 0;
      $original_geometry = $settings['original_geometry'] ?? NULL;
      if ($missing_geometry) {
        if (!is_array($original_geometry) || array_is_list($original_geometry)
          || count($original_geometry) !== 4
          || array_diff(array_keys($original_geometry), ['size_category', 'basis', 'rights_status', 'review_notes']) !== []
          || !in_array($original_geometry['size_category'] ?? NULL, ['small', 'medium', 'large'], TRUE)
          || ($original_geometry['basis'] ?? NULL) !== 'invented_for_missing_source_geometry'
          || ($original_geometry['rights_status'] ?? NULL) !== 'project_original'
          || !is_string($original_geometry['review_notes'] ?? NULL)
          || mb_strlen(trim($original_geometry['review_notes'])) < 30
          || mb_strlen($original_geometry['review_notes']) > 1000) {
          throw $this->error('module_release_original_geometry_review_required', '/original_scene_generation/scene_definitions/' . $resource_id . '/original_geometry', 'Missing source dimensions require a reviewed project-original size category and rationale.', 422);
        }
      }
      elseif ($original_geometry !== NULL) {
        throw $this->error('module_release_original_geometry_not_missing', '/original_scene_generation/scene_definitions/' . $resource_id . '/original_geometry', 'Original-size substitution is only available when source dimensions are missing.', 422);
      }
      if (array_key_exists('maps', $settings) && (!is_array($settings['maps'])
        || array_diff(array_keys($settings['maps']), ['background', 'tiles']) !== []
        || count($settings['maps']) !== 2)) {
        throw $invalid();
      }
      if (array_key_exists('map_alignment', $settings)) {
        if (!array_key_exists('maps', $settings)) {
          throw $this->error('module_release_map_alignment_invalid', '/original_scene_generation/scene_definitions/' . $resource_id . '/map_alignment', 'Reviewed map alignment requires staged scene maps.', 422);
        }
        $this->assertReviewedMapAlignment($settings['map_alignment'], $scenes[$resource_id], $resource_id);
      }
      if (array_key_exists('map_review', $settings)) {
        $review = $settings['map_review'];
        if (!array_key_exists('maps', $settings) || array_key_exists('map_alignment', $settings)
          || !is_array($review) || array_is_list($review)
          || array_diff(array_keys($review), ['role', 'terrain_basis', 'notes']) !== [] || count($review) !== 3
          || !in_array($review['role'] ?? NULL, ['interpretive_gallery', 'overland_overview'], TRUE)
          || ($review['terrain_basis'] ?? NULL) !== 'original_canonical'
          || !is_string($review['notes'] ?? NULL) || mb_strlen(trim($review['notes'])) < 30 || mb_strlen($review['notes']) > 1000) {
          throw $this->error('module_release_map_review_invalid', '/original_scene_generation/scene_definitions/' . $resource_id . '/map_review', 'Interpretive source art requires an explicit original-terrain review and must not claim tactical alignment.', 422);
        }
      }
    }
    if (array_key_exists('quest', $input)) {
      $quest = $input['quest'];
      if (!is_array($quest) || array_is_list($quest)
        || array_diff(array_keys($quest), ['template_id', 'name', 'description', 'rights_status', 'objectives']) !== []
        || count($quest) !== 5
        || !is_string($quest['template_id'] ?? NULL)
        || !preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $quest['template_id'])
        || !is_string($quest['name'] ?? NULL) || trim($quest['name']) === '' || mb_strlen($quest['name']) > 255
        || !is_string($quest['description'] ?? NULL) || trim($quest['description']) === '' || mb_strlen($quest['description']) > 4000
        || ($quest['rights_status'] ?? NULL) !== 'project_original'
        || !is_array($quest['objectives'] ?? NULL) || array_is_list($quest['objectives'])
        || array_diff_key($scenes, $quest['objectives']) !== [] || array_diff_key($quest['objectives'], $scenes) !== []) {
        throw $invalid();
      }
      $interaction_items = [];
      foreach ($quest['objectives'] as $resource_id => $objective) {
        if (!is_array($objective) || !in_array(count($objective), [2, 3], TRUE)
          || array_diff(array_keys($objective), ['description', 'next_step', 'interaction']) !== []
          || !is_string($objective['description'] ?? NULL) || trim($objective['description']) === '' || mb_strlen($objective['description']) > 1000
          || !is_string($objective['next_step'] ?? NULL) || trim($objective['next_step']) === '' || mb_strlen($objective['next_step']) > 1000) {
          throw $invalid();
        }
        if (isset($objective['interaction'])) {
          $interaction = $objective['interaction'];
          if (!is_array($interaction) || count($interaction) !== 2
            || array_diff(array_keys($interaction), ['description', 'item_definition_id']) !== []
            || !is_string($interaction['description'] ?? NULL)
            || trim($interaction['description']) === '' || mb_strlen($interaction['description']) > 1000) {
            throw $invalid();
          }
          $item_id = $interaction['item_definition_id'] ?? NULL;
          if (!is_string($item_id) || $item_id === '' || isset($interaction_items[$item_id])) {
            throw $invalid();
          }
          $matching = array_filter($definitions[$resource_id]['definitions'], static fn($ref): bool =>
            is_array($ref) && ($ref['family'] ?? NULL) === 'item' && ($ref['definition_id'] ?? NULL) === $item_id);
          if (count($matching) !== 1) {
            throw $invalid();
          }
          $interaction_items[$item_id] = TRUE;
        }
      }
      if ($interaction_items !== [] && count($interaction_items) !== count($scenes)) {
        throw $invalid();
      }
      $review_count = count(array_filter($definitions, static fn(array $settings): bool => array_key_exists('map_review', $settings)));
      if (($review_count !== 0 && $review_count !== $map_count)
        || (($review_count !== 0 || $original_map_count !== 0) && !isset($input['quest']))) {
        throw $invalid();
      }
    }
    // The assembly adapter validates the dungeon metadata and the scene
    // adapter validates each definition against the canonical registry.
    return $input + [
      'execution_order' => $expected,
      'map_attachment_state' => $map_count + $original_map_count === count($scenes) ? 'staged' : 'unattached',
    ];
  }

  /**
   * Publish reviewed original objectives against the simulated, exact placements.
   */
  private function authorOriginalQuest(array $quest, array $receipts, array $dungeon, string $release_id, int $uid): string {
    if ($this->questTemplates === NULL) {
      throw new \LogicException('module_release_quest_template_service_required');
    }
    $template_id = $quest['template_id'];
    $existing = $this->database->select('dc_canonical_quests', 'q')
      ->fields('q', ['id'])
      ->condition('template_id', $template_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($existing) {
      throw $this->error('module_release_quest_template_conflict', '/original_scene_generation/quest/template_id', 'A canonical quest already owns this template ID.', 409);
    }
    $resources = [];
    foreach ($receipts as $receipt) {
      $resources[$receipt['target_id']] = $receipt['resource_id'];
    }
    $objectives = [];
    $entrance = NULL;
    $scene_index = 0;
    foreach ((array) ($dungeon['room_placements'] ?? []) as $placement) {
      $resource_id = $resources[$placement['room_id'] ?? ''] ?? NULL;
      $location = $placement['placement_id'] ?? NULL;
      if (!is_string($resource_id) || !is_string($location) || $location === ''
        || !is_array($quest['objectives'][$resource_id] ?? NULL)
        || isset($objectives[$resource_id])) {
        throw new \DomainException('module_release_quest_placements_invalid');
      }
      if (!empty($placement['is_level_entrance'])) {
        $entrance = $location;
      }
      $reviewed = $quest['objectives'][$resource_id];
      $scene_index++;
      $objectives[$resource_id] = [
        'objective_id' => 'explore-scene-' . $scene_index,
        'type' => 'explore',
        'description' => trim($reviewed['description']),
        'next_step' => trim($reviewed['next_step']),
        'location' => $location,
        'location_id' => $location,
        'completion_criteria' => [
          'kind' => 'flag',
          'metric' => 'discovered',
          'required_value' => TRUE,
          'description' => 'Complete when this location is discovered.',
        ],
      ];
      if (isset($reviewed['interaction'])) {
        $interaction = $reviewed['interaction'];
        $objectives[$resource_id . ':interaction'] = [
          'objective_id' => 'interact-scene-' . $scene_index,
          'type' => 'interact',
          'description' => trim($interaction['description']),
          'next_step' => trim($reviewed['next_step']),
          'target' => $interaction['item_definition_id'],
          'item' => $interaction['item_definition_id'],
          'location' => $location,
          'location_id' => $location,
          'completion_criteria' => [
            'kind' => 'flag',
            'metric' => 'completed',
            'required_value' => TRUE,
            'description' => 'Complete by interacting with the placed item.',
          ],
        ];
      }
    }
    $expected_objectives = count($receipts) * (isset($quest['objectives'][array_key_first($quest['objectives'])]['interaction']) ? 2 : 1);
    if (count($objectives) !== $expected_objectives || $entrance === NULL) {
      throw new \DomainException('module_release_quest_placements_invalid');
    }
    $this->questTemplates->publishQuestTemplate([
      'schema_version' => 'quest-template-v1',
      'template_id' => $template_id,
      'version' => '1.0.0',
      'name' => trim($quest['name']),
      'description' => trim($quest['description']),
      'quest_type' => 'main',
      'level_min' => 1,
      'level_max' => 4,
      'tags' => ['module_conversion', 'original_exploration'],
      'entry_point_location_id' => $entrance,
      'objectives_schema' => [['phase' => 1, 'objectives' => array_values($objectives)]],
      'rewards_schema' => ['xp' => ['base' => 100, 'per_level' => 0]],
      'metadata' => ['rights_status' => 'project_original', 'reviewed_by' => $uid, 'source_release_id' => $release_id],
    ]);
    return $template_id;
  }

  /**
   * Require a reviewed source-space transform and, once staged, an undistorted bitmap.
   */
  private function assertReviewedMapAlignment(mixed $alignment, array $resource, string $resource_id, ?array $map_assets = NULL): void {
    $invalid = fn(): ModuleConversionReadException => $this->error('module_release_map_alignment_invalid', '/original_scene_generation/scene_definitions/' . $resource_id . '/map_alignment', 'Reviewed map alignment must fit the source scene and an aspect-preserving staged background.', 422);
    $context = $resource['source']['source_context'] ?? NULL;
    $width = is_array($context) ? ($context['width'] ?? NULL) : NULL;
    $height = is_array($context) ? ($context['height'] ?? NULL) : NULL;
    $fields = ['status', 'origin_x_px', 'origin_y_px', 'source_hex_radius_px', 'rotation_degrees'];
    if (!is_array($alignment) || array_is_list($alignment) || array_diff(array_keys($alignment), $fields) !== []
      || array_diff($fields, array_keys($alignment)) !== [] || ($alignment['status'] ?? NULL) !== 'reviewed'
      || (!is_int($width) && !is_float($width)) || !is_finite((float) $width) || $width <= 0
      || (!is_int($height) && !is_float($height)) || !is_finite((float) $height) || $height <= 0) {
      throw $invalid();
    }
    foreach (array_slice($fields, 1) as $field) {
      if ((!is_int($alignment[$field]) && !is_float($alignment[$field])) || !is_finite((float) $alignment[$field])) {
        throw $invalid();
      }
    }
    if ($alignment['origin_x_px'] < 0 || $alignment['origin_x_px'] > $width
      || $alignment['origin_y_px'] < 0 || $alignment['origin_y_px'] > $height
      || $alignment['source_hex_radius_px'] <= 0 || abs($alignment['rotation_degrees']) > 360) {
      throw $invalid();
    }
    if ($map_assets !== NULL) {
      $image_width = $map_assets['background']['width'] ?? NULL;
      $image_height = $map_assets['background']['height'] ?? NULL;
      if (!is_int($image_width) || $image_width <= 0 || !is_int($image_height) || $image_height <= 0
        || abs($width * $image_height - $height * $image_width) > 0.000001 * $width * $image_height) {
        throw $invalid();
      }
    }
  }

  /**
   * Every canonical hex polygon must fall on the staged background.
   */
  private function assertReviewedMapFootprint(array $alignment, array $resource, string $resource_id, array $hexes): void {
    $context = $resource['source']['source_context'] ?? NULL;
    $width = is_array($context) ? ($context['width'] ?? NULL) : NULL;
    $height = is_array($context) ? ($context['height'] ?? NULL) : NULL;
    $invalid = fn(): ModuleConversionReadException => $this->error(
      'module_release_map_footprint_outside_source',
      '/original_scene_generation/scene_definitions/' . $resource_id . '/map_alignment',
      'Reviewed source map alignment does not cover every generated canonical hex.',
      422,
      ['resource_id' => $resource_id]
    );
    if (!is_int($width) || !is_int($height) || $hexes === [] || !array_is_list($hexes)) {
      throw $invalid();
    }
    $angle = deg2rad((float) $alignment['rotation_degrees']);
    $cos = cos($angle);
    $sin = sin($angle);
    $radius = (float) $alignment['source_hex_radius_px'];
    foreach ($hexes as $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        throw $invalid();
      }
      $x = 1.5 * $hex['q'];
      $y = sqrt(3) * ($hex['q'] / 2 + $hex['r']);
      for ($corner = 0; $corner < 6; $corner++) {
        $corner_angle = M_PI * $corner / 3;
        $canvas_x = $x + cos($corner_angle);
        $canvas_y = $y + sin($corner_angle);
        $source_x = $alignment['origin_x_px'] + $radius * ($canvas_x * $cos + $canvas_y * $sin);
        $source_y = $alignment['origin_y_px'] + $radius * ($canvas_y * $cos - $canvas_x * $sin);
        if (!is_finite($source_x) || !is_finite($source_y)
          || $source_x < -0.000001 || $source_x > $width + 0.000001
          || $source_y < -0.000001 || $source_y > $height + 0.000001) {
          throw $invalid();
        }
      }
    }
  }

  /**
   * Register one verified published-dungeon manifest for a release.
   *
   * @param array<string,mixed> $manifest
   *
   * @return array<string,mixed>
   */
  public function registerPublishedManifest(string $release_id, array $manifest, int $uid): array {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      throw $this->error('module_release_table_missing', '/release_id', 'Module release table is not installed.', 503);
    }
    $release_id = trim($release_id);
    if ($release_id === '') {
      throw $this->error('module_release_id_required', '/release_id', 'module_release_id is required.', 400);
    }
    $dungeon_id = trim((string) ($manifest['dungeon_id'] ?? ''));
    $version_id = trim((string) ($manifest['version_id'] ?? ''));
    $receipt_resource_id = trim((string) ($manifest['receipt_resource_id'] ?? ''));
    $validation_profile = $this->validationProfile($manifest['validation_profile'] ?? 'encounter_complete_adaptation');
    if ($dungeon_id === '' || $version_id === '' || $receipt_resource_id === '') {
      throw $this->error('module_release_manifest_invalid', '/manifest', 'Manifest requires dungeon_id, version_id, and receipt_resource_id.', 422);
    }

    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->forUpdate()
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($row)) {
        throw $this->error('module_release_not_found', '/release_id', sprintf('module_release_id=%s was not found.', $release_id), 404);
      }
      $release = $this->normalizeRow($row);
      if ((string) ($release['publication_status'] ?? '') === self::STATUS_PUBLISHED && $this->isLaunchablePublishedRelease($release)) {
        return $release + ['idempotent' => TRUE];
      }
      if ((string) ($release['publication_status'] ?? '') !== self::STATUS_RECEIPTS_RECORDED) {
        throw $this->error('module_release_manifest_requires_receipts', '/release_id', 'Release requires complete native receipts before publication manifest registration.', 409);
      }
      $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
      $native_receipts = is_array($mapping['native_receipts'] ?? NULL) ? $mapping['native_receipts'] : [];
      $summary = is_array($mapping['native_receipt_summary'] ?? NULL) ? $mapping['native_receipt_summary'] : [];
      if (($summary['complete'] ?? FALSE) !== TRUE) {
        throw $this->error('module_release_manifest_requires_receipts', '/mapping/native_receipt_summary', 'Release native receipt coverage is incomplete.', 409);
      }
      $receipt = $native_receipts[$receipt_resource_id] ?? NULL;
      if (!is_array($receipt)) {
        throw $this->error('module_release_manifest_receipt_missing', '/manifest/receipt_resource_id', 'Manifest receipt_resource_id was not found in registered native receipts.', 422);
      }
      if (trim((string) ($receipt['owner'] ?? '')) !== 'DungeonEditorService'
        || trim((string) ($receipt['target_id'] ?? '')) !== $dungeon_id
        || trim((string) ($receipt['target_version'] ?? '')) !== $version_id) {
        throw $this->error('module_release_manifest_receipt_mismatch', '/manifest', 'Manifest dungeon/version does not match the referenced DungeonEditor receipt.', 422);
      }
      $this->assertPublishedDungeonVersionExists($dungeon_id, $version_id);
      $conversion_validation = $this->validateConversion($release_id, [
        'profile' => $validation_profile,
        'manifest' => ['dungeon_id' => $dungeon_id, 'version_id' => $version_id],
      ]);
      if (($conversion_validation['complete'] ?? FALSE) !== TRUE) {
        throw $this->error(
          'module_release_conversion_validation_incomplete',
          '/manifest',
          'Release conversion validation has blocking findings for required object completeness or placement.',
          422,
          ['validation' => $conversion_validation]
        );
      }
      $coverage = $this->sceneCoverageForRelease($release, $dungeon_id, $version_id);
      if (!$coverage['complete']) {
        throw $this->error('module_release_scene_coverage_incomplete', '/manifest', 'Converted scene rooms or the reviewed quest failed publication coverage.', 422, $coverage);
      }
      $manifest_payload = [
        'schema_version' => 'module-conversion-release-manifest-v1',
        'dungeon_id' => $dungeon_id,
        'version_id' => $version_id,
        'receipt_resource_id' => $receipt_resource_id,
        'validation_profile' => $validation_profile,
        'recorded_by' => max(0, $uid),
        'recorded_at' => $this->time->getRequestTime(),
      ];
      $manifest_payload['manifest_sha256'] = hash('sha256', $this->encode($manifest_payload));
      $mapping['published_manifest'] = $manifest_payload;
      $mapping['dungeon_id'] = $dungeon_id;
      $mapping['version_id'] = $version_id;
      $mapping['conversion_validation'] = $conversion_validation;
      $mapping['scene_coverage'] = $coverage;

      $this->database->update(self::TABLE)
        ->fields([
          'mapping_data' => $this->encode($mapping),
          'publication_status' => self::STATUS_PUBLISHED,
          'published_by' => max(0, $uid),
          'published_at' => $this->time->getRequestTime(),
          'updated' => $this->time->getRequestTime(),
        ])
        ->condition('release_id', $release_id)
        ->execute();
      $updated = $this->database->select(self::TABLE, 'r')
        ->fields('r')
        ->condition('release_id', $release_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($updated)) {
        throw $this->error('module_release_update_failed', '/release_id', 'Release row was not found after manifest update.', 500);
      }
      $normalized = $this->normalizeRow($updated);
      if ($coverage['scene_count'] > 0 && !$this->isLaunchablePublishedRelease($normalized)) {
        throw $this->error('module_release_manifest_invalid', '/manifest', 'Stored manifest is not launchable after verification.', 409);
      }
      return $normalized + ['idempotent' => FALSE];
    }
    catch (ModuleConversionReadException $e) {
      $transaction->rollBack();
      throw $e;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $this->error('module_release_manifest_registration_failed', '/manifest', 'Failed to register published release manifest.', 500, ['exception' => $e->getMessage()]);
    }
  }

  /**
   * @param array<string,mixed> $row
   *
   * @return array<string,mixed>
   */
  private function normalizeRow(array $row): array {
    $normalized = [
      'release_id' => (string) ($row['release_id'] ?? ''),
      'module_id' => (string) ($row['module_id'] ?? ''),
      'module_title' => (string) ($row['module_title'] ?? ''),
      'module_version' => (string) ($row['module_version'] ?? ''),
      'conversion_revision' => (string) ($row['conversion_revision'] ?? 'base'),
      'archive_sha256' => strtolower((string) ($row['archive_sha256'] ?? '')),
      'archive_path' => (string) ($row['archive_path'] ?? ''),
      'manifest' => $this->decodeJsonObject((string) ($row['manifest_data'] ?? '{}')),
      'inventory' => $this->decodeJsonObject((string) ($row['inventory_data'] ?? '{}')),
      'plan_hash' => strtolower((string) ($row['plan_hash'] ?? '')),
      'plan' => $this->decodeJsonObject((string) ($row['plan_data'] ?? '{}')),
      'mapping' => $this->decodeJsonObject((string) ($row['mapping_data'] ?? '{}')),
      'publication_status' => (string) ($row['publication_status'] ?? ''),
      'created_by' => (int) ($row['created_by'] ?? 0),
      'published_by' => isset($row['published_by']) ? (int) $row['published_by'] : NULL,
      'created' => (int) ($row['created'] ?? 0),
      'updated' => (int) ($row['updated'] ?? 0),
      'published_at' => isset($row['published_at']) ? (int) $row['published_at'] : NULL,
    ];
    $normalized['launchable'] = $this->isLaunchablePublishedRelease($normalized);
    return $normalized;
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeJsonObject(string $raw): array {
    if (trim($raw) === '') {
      return [];
    }
    try {
      $decoded = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return [];
    }
    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
  }

  /**
   * @param array<string,mixed> $plan
   */
  private function assertPlanContract(array $plan): void {
    if (($plan['schema_version'] ?? '') !== 'module-conversion-plan-v1') {
      throw $this->error('module_release_plan_contract_invalid', '/plan', 'Deterministic plan schema_version is missing or unsupported.', 422);
    }
    $resources = $plan['resources'] ?? NULL;
    if (!is_array($resources) || !array_is_list($resources) || $resources === []) {
      throw $this->error('module_release_plan_contract_invalid', '/plan/resources', 'Deterministic plan resources are required.', 422);
    }
    foreach ($resources as $index => $resource) {
      if (!is_array($resource)) {
        throw $this->error('module_release_plan_contract_invalid', '/plan/resources/' . $index, 'Plan resource entry must be an object.', 422);
      }
      $source = $resource['source'] ?? NULL;
      $commands = $resource['native_commands'] ?? NULL;
      if (!is_array($source) || !is_array($commands) || !array_is_list($commands) || $commands === []) {
        throw $this->error('module_release_plan_contract_invalid', '/plan/resources/' . $index, 'Plan resource is missing source metadata or command proposals.', 422);
      }
      foreach (['pack', 'key', 'type', 'id', 'collection', 'top_collection', 'document_type'] as $field) {
        if (!array_key_exists($field, $source)) {
          throw $this->error('module_release_plan_contract_invalid', '/plan/resources/' . $index . '/source/' . $field, 'Plan source metadata is incomplete.', 422);
        }
      }
      foreach ($commands as $command_index => $command) {
        if (!is_array($command)) {
          throw $this->error('module_release_plan_contract_invalid', '/plan/resources/' . $index . '/native_commands/' . $command_index, 'Plan command proposal must be an object.', 422);
        }
        foreach (['owner', 'operation', 'command_type', 'target_hint', 'payload_template'] as $field) {
          if (!array_key_exists($field, $command)) {
            throw $this->error('module_release_plan_contract_invalid', '/plan/resources/' . $index . '/native_commands/' . $command_index . '/' . $field, 'Plan command proposal is missing required metadata.', 422);
          }
        }
      }

    }
  }

  /**
   * @return array<int,string>
   */
  private function normalizeResourceFilter(mixed $raw): array {
    if ($raw === NULL || $raw === []) {
      return [];
    }
    if (!is_array($raw) || !array_is_list($raw)) {
      throw $this->error('module_release_execution_resource_filter_invalid', '/resource_ids', 'resource_ids must be an array of resource_id strings.', 422);
    }
    $resource_ids = [];
    foreach ($raw as $value) {
      $resource_id = trim((string) $value);
      if ($resource_id === '') {
        continue;
      }
      $resource_ids[$resource_id] = TRUE;
    }
    return array_values(array_keys($resource_ids));
  }

  /**
   * @param array<string,mixed> $resource
   *
   * @return array<string,mixed>
   */
  private function executeResourceCommand(array $resource, string $publish_version): array {
    $resource_id = trim((string) ($resource['resource_id'] ?? ''));
    $commands = (array) ($resource['native_commands'] ?? []);
    if ($resource_id === '' || $commands === [] || !is_array($commands[0])) {
      throw $this->error('module_release_execution_command_missing', '/resource/native_commands', 'Plan resource is missing executable command metadata.', 422, [
        'resource_id' => $resource_id,
      ]);
    }
    $command = $commands[0];
    $command_type = trim((string) ($command['command_type'] ?? ''));
    return match ($command_type) {
      'set_room_metadata' => $this->executeRoomMetadataCommand($resource, $command, $publish_version),
      'set_dungeon_metadata' => $this->executeDungeonMetadataCommand($resource, $command, $publish_version),
      'saveDefinition' => $this->executeSaveDefinitionCommand($resource, $command),
      'stage_story_fact_draft' => $this->executeStoryFactDraftCommand($resource, $command, $publish_version),
      'record_reference', 'record_folder' => $this->executeReferenceRecordCommand($resource, $command, $publish_version),
      default => throw $this->error(
        'module_release_execution_command_unsupported',
        '/resource/native_commands/0/command_type',
        'Conversion command type is not implemented for native execution.',
        422,
        [
          'resource_id' => $resource_id,
          'command_type' => $command_type,
          'owner' => trim((string) ($command['owner'] ?? '')),
        ],
      ),
    };
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   *
   * @return array<string,mixed>
   */
  private function executeSaveDefinitionCommand(array $resource, array $command): array {
    $resource_id = trim((string) ($resource['resource_id'] ?? ''));
    $family = $this->resolveDefinitionFamily($resource, $command);
    $definition_id = $this->resolveDefinitionId($resource, $command, $family);
    $payload = $this->buildDefinitionPayloadFromResource($resource, $command, $family, $definition_id);
    $validation = $this->canonicalDefinitions->validateDefinition($family, $payload);
    if ($validation !== []) {
      throw $this->error(
        'module_release_execution_definition_payload_invalid',
        '/resource/native_commands/0/payload_template',
        'Generated canonical definition payload did not pass object validation.',
        422,
        [
          'resource_id' => $resource_id,
          'family' => $family,
          'definition_id' => $definition_id,
          'sample_findings' => array_slice($validation, 0, 5),
        ],
      );
    }
    $current_version = $this->canonicalDefinitions->currentVersion($family, $definition_id);
    $result = $current_version === NULL
      ? $this->canonicalDefinitions->saveDefinition($family, NULL, $payload, NULL)
      : $this->canonicalDefinitions->saveDefinition($family, $definition_id, $payload, $current_version);
    $target_id = trim((string) ($result['definition_id'] ?? $definition_id));
    $target_version = trim((string) ($result['version'] ?? ''));
    if ($target_id === '' || $target_version === '') {
      throw $this->error('module_release_execution_publish_result_invalid', '/resource', 'Definition publication receipt is missing definition_id or version.', 500, [
        'resource_id' => $resource_id,
        'family' => $family,
      ]);
    }
    return [
      'resource_id' => $resource_id,
      'owner' => 'CanonicalDefinitionService',
      'target_id' => $target_id,
      'target_version' => $target_version,
      'source_receipt' => [
        'command_type' => 'saveDefinition',
        'family' => $family,
      ],
    ];
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   *
   * @return array<string,mixed>
   */
  private function executeStoryFactDraftCommand(array $resource, array $command, string $publish_version): array {
    return $this->persistRegistryReceiptResource(
      $resource,
      $command,
      $publish_version,
      'module_story_fact_draft',
      'StorylineManagerService'
    );
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   *
   * @return array<string,mixed>
   */
  private function executeReferenceRecordCommand(array $resource, array $command, string $publish_version): array {
    return $this->persistRegistryReceiptResource(
      $resource,
      $command,
      $publish_version,
      'module_conversion_reference',
      'ModuleConversionReferenceLedger'
    );
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   *
   * @return array<string,mixed>
   */
  private function persistRegistryReceiptResource(
    array $resource,
    array $command,
    string $publish_version,
    string $content_type,
    string $owner
  ): array {
    $resource_id = trim((string) ($resource['resource_id'] ?? ''));
    if ($resource_id === '') {
      throw $this->error('module_release_execution_resource_missing', '/resource/resource_id', 'Registry receipt command requires resource_id.', 422);
    }
    $name = trim((string) (($resource['source']['label'] ?? $resource['label'] ?? $resource_id)));
    if ($name === '') {
      $name = $resource_id;
    }
    $source = is_array($resource['source'] ?? NULL) ? $resource['source'] : [];
    $source_context = is_array($source['source_context'] ?? NULL) ? $source['source_context'] : [];
    $payload = [
      'resource_id' => $resource_id,
      'resource_type' => (string) ($resource['resource_type'] ?? ''),
      'source_key' => (string) ($source['key'] ?? ''),
      'source_pack' => (string) ($source['pack'] ?? ''),
      'source_document_type' => (string) ($source['document_type'] ?? ''),
      'command_type' => (string) ($command['command_type'] ?? ''),
      'source_context' => $source_context,
    ];
    $existing = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['id'])
      ->condition('content_type', $content_type)
      ->condition('content_id', $resource_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $now = $this->time->getRequestTime();
    $fields = [
      'name' => mb_substr($name, 0, 255),
      'version' => $publish_version,
      'schema_data' => $this->encode($payload),
      'source_file' => 'module_conversion',
      'updated' => $now,
    ];
    if ($existing) {
      $this->database->update('dungeoncrawler_content_registry')
        ->fields($fields)
        ->condition('id', (int) $existing)
        ->execute();
    }
    else {
      $this->database->insert('dungeoncrawler_content_registry')
        ->fields($fields + [
          'content_type' => $content_type,
          'content_id' => $resource_id,
          'created' => $now,
        ])
        ->execute();
    }
    return [
      'resource_id' => $resource_id,
      'owner' => $owner,
      'target_id' => $resource_id,
      'target_version' => $publish_version,
      'source_receipt' => [
        'command_type' => (string) ($command['command_type'] ?? ''),
        'content_type' => $content_type,
      ],
    ];
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   */
  private function resolveDefinitionFamily(array $resource, array $command): string {
    $family = trim((string) (($command['payload_template']['family'] ?? '')));
    if ($family === '') {
      $resource_type = trim((string) ($resource['resource_type'] ?? ''));
      $family = match ($resource_type) {
        'actor' => 'actor',
        'item', 'embedded_item' => 'item',
        default => '',
      };
    }
    if ($family === '' || !in_array($family, $this->canonicalDefinitions->families(), TRUE)) {
      throw $this->error(
        'module_release_execution_definition_family_missing',
        '/resource/native_commands/0/payload_template/family',
        'Definition family could not be resolved for conversion resource.',
        422,
        [
          'resource_id' => trim((string) ($resource['resource_id'] ?? '')),
          'resource_type' => trim((string) ($resource['resource_type'] ?? '')),
        ],
      );
    }
    return $family;
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   */
  private function resolveDefinitionId(array $resource, array $command, string $family): string {
    $candidate = trim((string) (($command['payload_template']['definition_id'] ?? '')));
    $source = is_array($resource['source'] ?? NULL) ? $resource['source'] : [];
    $source_id = trim((string) ($source['id'] ?? ''));
    $label = trim((string) ($source['label'] ?? $resource['label'] ?? ''));
    if ($candidate === '' || str_contains($candidate, ':') || str_starts_with($candidate, 'definition_draft')) {
      $candidate = $source_id !== '' ? $source_id : $label;
    }
    $prefix = match ($family) {
      'actor' => 'actor',
      'item' => 'item',
      'creature' => 'creature',
      'obstacle' => 'obstacle',
      'trap' => 'trap',
      'hazard' => 'hazard',
      default => 'definition',
    };
    $fingerprint = substr(hash('sha256', trim((string) ($resource['resource_id'] ?? ''))), 0, 8);
    return $this->canonicalSlug($prefix . '-' . $candidate . '-' . $fingerprint, $prefix);
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   *
   * @return array<string,mixed>
   */
  private function buildDefinitionPayloadFromResource(
    array $resource,
    array $command,
    string $family,
    string $definition_id
  ): array {
    $payload = is_array($command['payload_template']['payload'] ?? NULL) ? $command['payload_template']['payload'] : [];
    $source = is_array($resource['source'] ?? NULL) ? $resource['source'] : [];
    $source_context = is_array($source['source_context'] ?? NULL) ? $source['source_context'] : [];
    $display_name = trim((string) ($payload['name'] ?? $source['label'] ?? $resource['label'] ?? $definition_id));
    if ($display_name === '') {
      $display_name = $definition_id;
    }

    if ($family === 'actor') {
      if ($this->definitionGeneration === NULL) {
        throw $this->error(
          'module_release_actor_generation_service_missing',
          '/resource/native_commands/0',
          'Canonical actor generation service is required for actor conversion execution.',
          500,
          ['resource_id' => trim((string) ($resource['resource_id'] ?? ''))],
        );
      }
      $seed = (int) (hexdec(substr(hash('sha256', trim((string) ($resource['resource_id'] ?? ''))), 0, 7)) % 2147483647);
      $role = trim((string) ($source_context['actor_type'] ?? $source['document_type'] ?? 'npc'));
      if ($role === '') {
        $role = 'npc';
      }
      $description = trim((string) ($source_context['description'] ?? ''));
      $prompt = 'Create a project-original NPC canonical actor definition for module conversion. '
        . 'Use the source label as inspiration only; do not copy copyrighted text or settings. '
        . 'Name: ' . $display_name . '. '
        . ($description !== '' ? 'Source notes: ' . mb_substr($description, 0, 600) . '. ' : '')
        . 'Produce complete actionable combat/runtime fields in state_data.';
      $generation_definition_id = $this->canonicalSlug(
        $definition_id . '-proposal-' . substr(hash('sha256', $definition_id . ':' . $this->time->getRequestTime()), 0, 8),
        'actor-proposal'
      );
      $proposal = $this->definitionGeneration->generateNpcDefinition([
        'prompt' => $prompt,
        'seed' => $seed,
        'role' => mb_substr($role, 0, 100),
        'definition_id' => mb_substr($generation_definition_id, 0, 100),
      ], [
        'family' => 'actor',
        'definition_id' => $definition_id,
        'validation_profile' => 'editing',
      ]);
      $generated = $proposal['payload'] ?? NULL;
      if (!is_array($generated)) {
        throw $this->error(
          'module_release_actor_generation_payload_invalid',
          '/resource/native_commands/0',
          'Generated actor payload is missing or invalid.',
          500,
          ['resource_id' => trim((string) ($resource['resource_id'] ?? ''))],
        );
      }
      $generated['actor_id'] = $definition_id;
      $generated['display_name'] = $display_name;
      $generated['source_module'] = mb_substr((string) ($source['pack'] ?? 'module_conversion'), 0, 128);
      if (!is_array($generated['state_data'] ?? NULL)) {
        throw $this->error(
          'module_release_actor_generation_state_invalid',
          '/resource/native_commands/0',
          'Generated actor payload must include state_data.',
          500,
          ['resource_id' => trim((string) ($resource['resource_id'] ?? ''))],
        );
      }
      $generated['state_data']['name'] = $display_name;
      if ($description !== '' && trim((string) ($generated['state_data']['description'] ?? '')) === '') {
        $generated['state_data']['description'] = mb_substr($description, 0, 4000);
      }
      return $generated;
    }

    if ($family === 'item') {
      $payload = [
        'schema_version' => '1.0.0',
        'item_id' => $definition_id,
        'name' => $display_name,
        'item_type' => 'adventuring_gear',
        'level' => 0,
        'rarity' => 'common',
        'traits' => [],
      ];
      $description = trim((string) ($source_context['description'] ?? ''));
      if ($description !== '') {
        $payload['description'] = mb_substr($description, 0, 2000);
      }
      return $payload;
    }

    return $payload;
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   *
   * @return array<string,mixed>
   */
  private function executeRoomMetadataCommand(array $resource, array $command, string $publish_version, ?array $generation = NULL, bool $terminal = FALSE, ?array $map_assets = NULL, ?array $prepared = NULL, int $uid = 0): array {
    $resource_id = trim((string) ($resource['resource_id'] ?? ''));
    $payload = (array) (($command['payload_template']['command']['payload'] ?? []));
    $target_hint = trim((string) (($command['target_hint'] ?? ($resource['target']['id_hint'] ?? ''))));
    $base_room_id = $this->canonicalSlug(
      trim((string) ($payload['room_id'] ?? $target_hint)),
      'module-room'
    );
    $room_id = $this->canonicalSlug($base_room_id . '-' . substr(hash('sha256', $resource_id . ':' . $this->time->getRequestTime()), 0, 8), 'module-room');
    $name = trim((string) ($payload['name'] ?? ($resource['source']['label'] ?? 'Imported Room')));
    if ($name === '') {
      $name = 'Imported Room';
    }
    $description = trim((string) ($payload['description'] ?? ''));
    $source = is_array($resource['source'] ?? NULL) ? $resource['source'] : [];
    $source_context = is_array($source['source_context'] ?? NULL) ? $source['source_context'] : [];
    $source_counts = is_array($source['counts'] ?? NULL) ? $source['counts'] : [];
    $tile_placements = array_key_exists('tile_placements', $source_context) ? $source_context['tile_placements'] : [];
    if (!is_array($tile_placements) || !array_is_list($tile_placements)
      || (array_key_exists('tile_placements', $source_context)
        && (!is_array($source_context['tile_image_refs'] ?? NULL)
          || !array_is_list($source_context['tile_image_refs'])
          || count($tile_placements) !== count($source_context['tile_image_refs'])
          || !is_array($source_context['tile_archive_entries'] ?? NULL)
          || !array_is_list($source_context['tile_archive_entries'])
          || count($tile_placements) !== count($source_context['tile_archive_entries'])))) {
      throw $this->error('module_release_scene_tile_placements_invalid', '/resource/source/source_context/tile_placements', 'Tile placements must align with source image and archive-entry references.', 422, ['resource_id' => $resource_id]);
    }
    $tile_fields = ['x', 'y', 'width', 'height', 'rotation', 'alpha', 'elevation', 'sort'];
    foreach ($tile_placements as $placement) {
      if (!is_array($placement) || array_is_list($placement)
        || array_diff(array_keys($placement), $tile_fields) !== []
        || array_diff($tile_fields, array_keys($placement)) !== []) {
        throw $this->error('module_release_scene_tile_placements_invalid', '/resource/source/source_context/tile_placements', 'Tile placement has an invalid field contract.', 422, ['resource_id' => $resource_id]);
      }
      foreach ($tile_fields as $field) {
        $value = $placement[$field];
        if ($value !== NULL && ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
          || (in_array($field, ['width', 'height'], TRUE) && $value <= 0)
          || ($field === 'alpha' && ($value < 0 || $value > 1)))) {
          throw $this->error('module_release_scene_tile_placements_invalid', '/resource/source/source_context/tile_placements/' . $field, 'Tile placement contains an invalid numeric value.', 422, ['resource_id' => $resource_id]);
        }
      }
    }
    $source_scene = [
      'width_px' => $source_context['width'] ?? NULL,
      'height_px' => $source_context['height'] ?? NULL,
      'grid_type' => $source_context['grid_type'] ?? NULL,
      'grid_size_px' => $source_context['grid_size'] ?? NULL,
      'grid_distance' => $source_context['grid_distance'] ?? NULL,
      'grid_units' => $source_context['grid_units'] ?? NULL,
      'nav_order' => $source_context['nav_order'] ?? NULL,
      'background_ref' => $source_context['background_src'] ?? NULL,
      'background_archive_entry' => $source_context['background_archive_entry'] ?? NULL,
      'tile_image_refs' => (array) ($source_context['tile_image_refs'] ?? []),
      'tile_placements' => $tile_placements,
      'tile_archive_entries' => (array) ($source_context['tile_archive_entries'] ?? []),
      'tiles_count' => (int) ($source_counts['tiles'] ?? 0),
      'drawings_count' => (int) ($source_counts['drawings'] ?? 0),
      'missing_source_fields' => (array) ($source['missing_source_fields'] ?? []),
      'conversion_state' => $generation === NULL ? 'source_metadata_only' : ($map_assets === NULL ? 'original_gameplay_generated_map_unattached' : (isset($generation['map_review']) || isset($generation['original_map']) ? 'playable_validated' : 'original_gameplay_generated_map_staged')),
    ];
    if ($map_assets !== NULL) {
      $source_scene['map_assets'] = $map_assets;
    }
    if ($generation !== NULL && isset($generation['map_review'])) {
      $source_scene['map_review'] = $generation['map_review'];
    }
    if ($generation !== NULL && isset($generation['original_map'])) {
      $source_scene['original_map_review'] = $generation['original_map'] + ['reviewed_by' => $uid];
    }
    if ($generation !== NULL && array_key_exists('map_alignment', $generation)) {
      if ($map_assets === NULL) {
        throw $this->error('module_release_map_alignment_invalid', '/original_scene_generation/scene_definitions/' . $resource_id . '/map_alignment', 'Reviewed map alignment requires staged scene maps.', 422);
      }
      $this->assertReviewedMapAlignment($generation['map_alignment'], $resource, $resource_id, $map_assets);
      $source_scene['map_alignment'] = $generation['map_alignment'];
    }

    if ($generation !== NULL && $prepared === NULL) {
      throw new \LogicException('module_scene_prepared_plan_required');
    }
    $draft = $prepared === NULL ? $this->roomEditor->createDraft(NULL) : $this->roomEditor->getDraft($prepared['draft_id']);
    $draft_id = (string) ($draft['draft_id'] ?? '');
    if ($draft_id === '') {
      throw $this->error('module_release_execution_draft_missing', '/resource', 'Room conversion draft_id is missing.', 500, [
        'resource_id' => $resource_id,
      ]);
    }
    if ($generation !== NULL) {
      $context = new RoomEditorGmToolContext($draft_id, 'publication', $this->roomEditor, $this->canonicalDefinitions);
      $generated = $this->sceneGeneration->applyPreparedRoom($prepared['plan'], $context, $generation['definitions'], $terminal);
      if (($generated['validation']['valid'] ?? FALSE) !== TRUE) {
        throw new \DomainException('module_scene_publication_validation_failed');
      }
      $draft = $generated['draft'];
      $payload = $generation['metadata'];
      $name = trim($payload['name']);
      $description = trim($payload['description']);
      if (array_key_exists('map_alignment', $generation)) {
        $this->assertReviewedMapFootprint(
          $generation['map_alignment'],
          $resource,
          $resource_id,
          (array) ($draft['room']['hexes'] ?? [])
        );
      }
    }
    $command_result = $this->roomEditor->applyCommand($draft_id, [
      'command_id' => $this->uuid->generate(),
      'expected_revision' => (int) ($draft['revision'] ?? 0),
      'type' => 'set_room_metadata',
      'issued_at' => gmdate(DATE_RFC3339),
      'payload' => [
        'changes' => [
          'room_id' => $room_id,
          'name' => $name,
          'description' => $description,
          'room_type' => 'chamber',
          'size_category' => $generation === NULL ? 'medium' : ($draft['room']['size_category'] ?? 'medium'),
          'metadata' => [
            'module_conversion' => [
              'resource_id' => $resource_id,
              'source_scene' => $source_scene,
              ...($generation === NULL ? [] : ['reviewed_original' => [
                'rights_status' => 'project_original',
                'theme' => trim($generation['metadata']['theme']),
                'definition_refs' => array_map(static fn(array $ref): array => [
                  'family' => $ref['family'],
                  'definition_id' => $ref['definition_id'],
                  'version' => $ref['version'],
                ], $generation['definitions']),
                'map_attachment_state' => $map_assets === NULL ? 'unattached' : 'staged',
                ...(isset($generation['original_geometry']) ? ['original_geometry' => $generation['original_geometry'] + ['reviewed_by' => $uid]] : []),
              ]]),
            ],
          ],
        ],
      ],
    ]);
    $updated_draft = $this->applyAuthoritativeRoomHexIndexes(
      $draft_id,
      (array) ($command_result['draft'] ?? [])
    );
    if ($generation !== NULL && ($this->roomEditor->validateDraft($draft_id, 'publication')['valid'] ?? FALSE) !== TRUE) {
      throw new \DomainException('module_scene_publication_validation_failed');
    }
    $published = $this->roomEditor->publish($draft_id, [
      'expected_revision' => (int) ($updated_draft['revision'] ?? 0),
      'expected_base_version_id' => $updated_draft['base_version_id'] ?? NULL,
      'version' => $publish_version,
    ]);
    $target_id = trim((string) ($published['room_id'] ?? ''));
    $target_version = trim((string) ($published['version_id'] ?? ''));
    if ($target_id === '' || $target_version === '') {
      throw $this->error('module_release_execution_publish_result_invalid', '/resource', 'Room publication receipt is missing room_id or version_id.', 500, [
        'resource_id' => $resource_id,
      ]);
    }
    return [
      'resource_id' => $resource_id,
      'owner' => 'RoomEditorService',
      'target_id' => $target_id,
      'target_version' => $target_version,
      'source_receipt' => [
        'command_type' => 'set_room_metadata',
        'publish_version' => $publish_version,
        ...($generation === NULL ? [] : [
          'execution_mode' => 'original_scene_generation',
          'map_attachment_state' => $map_assets === NULL ? 'unattached' : 'staged',
          ...(array_key_exists('map_alignment', $generation) ? ['map_alignment' => $generation['map_alignment']] : []),
          ...(array_key_exists('map_review', $generation) ? ['map_review' => $generation['map_review']] : []),
          ...(isset($generation['original_geometry']) ? ['original_geometry' => $generation['original_geometry'] + ['reviewed_by' => $uid]] : []),
          ...(isset($generation['original_map']) ? ['original_map' => $generation['original_map'] + ['reviewed_by' => $uid]] : []),
        ]),
      ],
    ];
  }

  /**
   * @param array<string,mixed> $draft
   *
   * @return array<string,mixed>
   */
  private function applyAuthoritativeRoomHexIndexes(string $draft_id, array $draft): array {
    $room = is_array($draft['room'] ?? NULL) ? $draft['room'] : [];
    $room_id = trim((string) ($room['room_id'] ?? ''));
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    $revision = (int) ($draft['revision'] ?? 0);
    if ($room_id === '' || $hexes === []) {
      return $draft;
    }

    $latest = $draft;
    foreach ($hexes as $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        continue;
      }
      $latlng = H3SpatialHelper::projectAxialHexToLatLng($room_id, (float) $hex['q'], (float) $hex['r']);
      $h3_index = H3SpatialHelper::latLngToH3Index(
        (float) $latlng['latitude'],
        (float) $latlng['longitude'],
        14
      );
      $command_result = $this->roomEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => $revision,
        'type' => 'set_hex_terrain',
        'issued_at' => gmdate(DATE_RFC3339),
        'payload' => [
          'hex' => [
            'q' => (int) $hex['q'],
            'r' => (int) $hex['r'],
            'h3_index_res14' => $h3_index,
            'h3_index' => $h3_index,
            'lat' => (float) $latlng['latitude'],
            'lng' => (float) $latlng['longitude'],
          ],
          'terrain_type' => (string) ($hex['terrain_type'] ?? 'stone_floor'),
        ],
      ]);
      $latest = (array) ($command_result['draft'] ?? []);
      $revision = (int) ($latest['revision'] ?? $revision + 1);
    }
    return $latest;
  }

  /**
   * @param array<string,mixed> $resource
   * @param array<string,mixed> $command
   *
   * @return array<string,mixed>
   */
  private function executeDungeonMetadataCommand(array $resource, array $command, string $publish_version): array {
    $resource_id = trim((string) ($resource['resource_id'] ?? ''));
    $payload = (array) (($command['payload_template']['command']['payload'] ?? []));
    $name = trim((string) ($payload['name'] ?? ($resource['source']['label'] ?? 'Imported Dungeon')));
    if ($name === '') {
      $name = 'Imported Dungeon';
    }
    $description = trim((string) ($payload['description'] ?? ''));
    $theme = trim((string) ($payload['theme'] ?? 'unthemed'));
    if ($theme === '') {
      $theme = 'unthemed';
    }

    $draft = $this->dungeonEditor->createDraft(NULL);
    $draft_id = (string) ($draft['draft_id'] ?? '');
    if ($draft_id === '') {
      throw $this->error('module_release_execution_draft_missing', '/resource', 'Dungeon conversion draft_id is missing.', 500, [
        'resource_id' => $resource_id,
      ]);
    }
    $command_result = $this->dungeonEditor->applyCommand($draft_id, [
      'command_id' => $this->uuid->generate(),
      'expected_revision' => (int) ($draft['revision'] ?? 0),
      'type' => 'set_dungeon_metadata',
      'issued_at' => gmdate(DATE_RFC3339),
      'payload' => [
        'changes' => [
          'name' => $name,
          'description' => $description,
          'theme' => $theme,
        ],
      ],
    ]);
    $updated_draft = (array) ($command_result['draft'] ?? []);
    $seed_room_receipt = $this->executeRoomMetadataCommand([
      'resource_id' => $resource_id . ':seed-room',
      'source' => [
        'label' => $name . ' Entry Room',
      ],
      'target' => [
        'id_hint' => $this->canonicalSlug($name . '-entry', 'module-room-entry'),
      ],
    ], [
      'target_hint' => $this->canonicalSlug($name . '-entry', 'module-room-entry'),
      'payload_template' => [
        'command' => [
          'payload' => [
            'name' => $name . ' Entry Room',
            'description' => 'Auto-generated entry room for module conversion.',
          ],
        ],
      ],
    ], $publish_version);
    $place_room_result = $this->dungeonEditor->applyCommand($draft_id, [
      'command_id' => $this->uuid->generate(),
      'expected_revision' => (int) ($updated_draft['revision'] ?? 0),
      'type' => 'place_room',
      'issued_at' => gmdate(DATE_RFC3339),
      'payload' => [
        'room_id' => (string) $seed_room_receipt['target_id'],
        'version_id' => (string) $seed_room_receipt['target_version'],
        'origin' => ['q' => 0, 'r' => 0],
        'rotation_steps' => 0,
        'label' => $name . ' Entry',
      ],
    ]);
    $placement_id = trim((string) ($place_room_result['placement_id'] ?? ''));
    $placement_draft = (array) ($place_room_result['draft'] ?? []);
    if ($placement_id !== '') {
      $set_entrance_result = $this->dungeonEditor->applyCommand($draft_id, [
        'command_id' => $this->uuid->generate(),
        'expected_revision' => (int) ($placement_draft['revision'] ?? 0),
        'type' => 'set_placement_metadata',
        'issued_at' => gmdate(DATE_RFC3339),
        'payload' => [
          'placement_id' => $placement_id,
          'changes' => [
            'is_level_entrance' => TRUE,
          ],
        ],
      ]);
      $published_draft = (array) ($set_entrance_result['draft'] ?? []);
    }
    else {
      $published_draft = $placement_draft;
    }
    $published = $this->dungeonEditor->publish(
      $draft_id,
      (int) ($published_draft['revision'] ?? 0),
      0,
      [
        'version' => $publish_version,
        'publication_note' => 'module conversion resource ' . $resource_id,
      ]
    );
    $target_id = trim((string) ($published['dungeon_id'] ?? ''));
    $target_version = trim((string) ($published['published_version_id'] ?? $published['version_id'] ?? ''));
    if ($target_id === '' || $target_version === '') {
      throw $this->error('module_release_execution_publish_result_invalid', '/resource', 'Dungeon publication receipt is missing dungeon_id or version_id.', 500, [
        'resource_id' => $resource_id,
      ]);
    }
    return [
      'resource_id' => $resource_id,
      'owner' => 'DungeonEditorService',
      'target_id' => $target_id,
      'target_version' => $target_version,
      'source_receipt' => [
        'command_type' => 'set_dungeon_metadata',
        'publish_version' => $publish_version,
      ],
    ];
  }

  /**
   * @return array<string,mixed>
   */
  private function buildExecutionEntry(
    string $resource_id,
    string $owner,
    string $state,
    ?string $target_id,
    ?string $target_version,
    string $note,
    int $uid,
    array $source_receipt = []
  ): array {
    $entry = [
      'resource_id' => $resource_id,
      'owner' => $owner,
      'state' => $state,
      'target_id' => $target_id,
      'target_version' => $target_version,
      'note' => $note,
      'updated_by' => max(0, $uid),
      'updated_at' => $this->time->getRequestTime(),
    ];
    if ($source_receipt !== []) {
      $entry['source_receipt'] = $source_receipt;
    }
    $entry['entry_sha256'] = hash('sha256', $this->encode([
      'resource_id' => $entry['resource_id'],
      'owner' => $entry['owner'],
      'state' => $entry['state'],
      'target_id' => $entry['target_id'],
      'target_version' => $entry['target_version'],
      'note' => $entry['note'],
      'source_receipt' => $entry['source_receipt'] ?? [],
    ]));
    return $entry;
  }

  private function canonicalSlug(string $candidate, string $prefix): string {
    $slug = strtolower(trim($candidate));
    $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-_');
    if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $slug)) {
      $slug = $prefix . '-' . substr(str_replace('-', '', $this->uuid->generate()), 0, 8);
    }
    return substr($slug, 0, 100);
  }

  /**
   * @param array<string,mixed> $plan
   *
   * @return array<string,bool>
   */
  private function requiredReceiptResourceIds(array $plan): array {
    $declared = $plan['required_receipt_resource_ids'] ?? NULL;
    if (is_array($declared) && array_is_list($declared)) {
      $required = [];
      foreach ($declared as $resource_id) {
        $resource_id = trim((string) $resource_id);
        if ($resource_id !== '') {
          $required[$resource_id] = TRUE;
        }
      }
      if ($required !== []) {
        return $required;
      }
    }
    $required = [];
    foreach ((array) ($plan['resources'] ?? []) as $resource) {
      if (!is_array($resource)) {
        continue;
      }
      $resource_id = trim((string) ($resource['resource_id'] ?? ''));
      $action = trim((string) ($resource['action'] ?? ''));
      if ($resource_id === '' || $action === 'reject') {
        continue;
      }
      $required[$resource_id] = TRUE;
    }
    return $required;
  }

  /**
   * @param array<string,bool> $required
   * @param array<string,mixed> $execution_ledger
   *
   * @return array<string,mixed>
   */
  private function buildExecutionSummary(array $required, array $execution_ledger): array {
    $states = [
      'pending' => 0,
      'in_progress' => 0,
      'done' => 0,
      'failed' => 0,
      'unknown' => 0,
    ];
    foreach (array_keys($required) as $resource_id) {
      $entry = $execution_ledger[$resource_id] ?? NULL;
      $state = is_array($entry) ? strtolower(trim((string) ($entry['state'] ?? ''))) : 'pending';
      if (!array_key_exists($state, $states)) {
        $state = 'unknown';
      }
      $states[$state]++;
    }
    return [
      'required_count' => count($required),
      'states' => $states,
      'complete' => $states['done'] === count($required),
      'updated_at' => $this->time->getRequestTime(),
    ];
  }

  /**
   * @param array<string,mixed> $plan
   *
   * @return array<int,string>
   */
  private function allowedExecutionOwnersForResource(array $plan, string $resource_id): array {
    $owners = [];
    foreach ((array) ($plan['resources'] ?? []) as $resource) {
      if (!is_array($resource)) {
        continue;
      }
      if (trim((string) ($resource['resource_id'] ?? '')) !== $resource_id) {
        continue;
      }
      foreach ((array) ($resource['native_commands'] ?? []) as $command) {
        if (!is_array($command)) {
          continue;
        }
        $owner = trim((string) ($command['owner'] ?? ''));
        if ($owner !== '') {
          $owners[$owner] = TRUE;
        }
      }
    }
    return array_values(array_keys($owners));
  }

  /**
   * @param array<string,mixed> $release
   */
  private function isLaunchablePublishedRelease(array $release): bool {
    if ((string) ($release['publication_status'] ?? '') !== self::STATUS_PUBLISHED) {
      return FALSE;
    }
    $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
    $summary = is_array($mapping['native_receipt_summary'] ?? NULL) ? $mapping['native_receipt_summary'] : [];
    if (($summary['complete'] ?? FALSE) !== TRUE) {
      return FALSE;
    }
    $manifest = is_array($mapping['published_manifest'] ?? NULL) ? $mapping['published_manifest'] : [];
    if (($manifest['schema_version'] ?? '') !== 'module-conversion-release-manifest-v1') {
      return FALSE;
    }
    $dungeon_id = trim((string) ($manifest['dungeon_id'] ?? ''));
    $version_id = trim((string) ($manifest['version_id'] ?? ''));
    $receipt_resource_id = trim((string) ($manifest['receipt_resource_id'] ?? ''));
    if ($dungeon_id === '' || $version_id === '' || $receipt_resource_id === '') {
      return FALSE;
    }
    $coverage = is_array($mapping['scene_coverage'] ?? NULL) ? $mapping['scene_coverage'] : [];
    if (($coverage['complete'] ?? FALSE) !== TRUE
      || (int) ($coverage['scene_count'] ?? 0) < 1
      || ($coverage['dungeon_id'] ?? '') !== $dungeon_id
      || ($coverage['version_id'] ?? '') !== $version_id) {
      return FALSE;
    }
    $native_receipts = is_array($mapping['native_receipts'] ?? NULL) ? $mapping['native_receipts'] : [];
    if ($native_receipts !== []) {
      $receipt = $native_receipts[$receipt_resource_id] ?? NULL;
      if (!is_array($receipt)) {
        return FALSE;
      }
      if (trim((string) ($receipt['owner'] ?? '')) !== 'DungeonEditorService'
        || trim((string) ($receipt['target_id'] ?? '')) !== $dungeon_id
        || trim((string) ($receipt['target_version'] ?? '')) !== $version_id) {
        return FALSE;
      }
    }
    if (!$this->publishedDungeonVersionExists($dungeon_id, $version_id)) {
      return FALSE;
    }
    $row = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
      ->fields('v', ['dungeon_payload', 'payload_hash'])
      ->condition('v.dungeon_id', $dungeon_id)
      ->condition('v.version_id', $version_id)
      ->range(0, 1)->execute()->fetchAssoc();
    if (!is_array($row) || !hash_equals((string) $row['payload_hash'], hash('sha256', (string) $row['dungeon_payload']))) {
      return FALSE;
    }
    $dungeon = $this->decodeJsonObject((string) $row['dungeon_payload']);
    if (($dungeon['metadata']['module_conversion']['execution_mode'] ?? NULL) !== 'original_scene_generation') {
      return TRUE;
    }
    return $this->reviewedQuestCoversDungeon(
      $dungeon, (array) ($dungeon['room_placements'] ?? []),
      $native_receipts, $dungeon_id, $version_id
    );
  }

  /**
   * Review the canonical dungeon against this release's converted scene receipts.
   *
   * @return array<string,mixed>
   */
  public function sceneCoverageReport(string $release_id): array {
    $row = $this->database->select(self::TABLE, 'r')
      ->fields('r')
      ->condition('release_id', trim($release_id))
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw $this->error('module_release_not_found', '/release_id', 'Release was not found for scene coverage audit.', 404);
    }
    $release = $this->normalizeRow($row);
    $mapping = $release['mapping'];
    $dungeon_id = trim((string) ($mapping['dungeon_id'] ?? ''));
    $version_id = trim((string) ($mapping['version_id'] ?? ''));
    if ($dungeon_id === '' || $version_id === '') {
      throw $this->error('module_release_scene_coverage_pin_missing', '/mapping', 'Release has no pinned dungeon/version for scene coverage audit.', 422);
    }
    return $this->sceneCoverageForRelease($release, $dungeon_id, $version_id);
  }

  /**
   * Validate conversion completeness across source objects and native outputs.
   *
   * This is read-only: it inspects one release and reports blocking findings.
   *
   * @param array<string,mixed> $options
   *
   * @return array<string,mixed>
   */
  public function validateConversion(string $release_id, array $options = []): array {
    $row = $this->database->select(self::TABLE, 'r')
      ->fields('r')
      ->condition('release_id', trim($release_id))
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw $this->error('module_release_not_found', '/release_id', 'Release was not found for conversion validation.', 404);
    }
    $release = $this->normalizeRow($row);
    $this->assertPlanContract((array) ($release['plan'] ?? []));
    $profile = $this->validationProfile($options['profile'] ?? NULL);
    $plan = (array) ($release['plan'] ?? []);
    $resources = is_array($plan['resources'] ?? NULL) ? $plan['resources'] : [];
    $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
    $receipts = is_array($mapping['native_receipts'] ?? NULL) ? $mapping['native_receipts'] : [];

    $pins = $this->resolveValidationPins($release, $options);
    $scene_coverage = NULL;
    $scene_coverage_findings = [];
    if ($pins !== NULL) {
      try {
        $scene_coverage = $this->sceneCoverageForRelease($release, $pins['dungeon_id'], $pins['version_id']);
      }
      catch (ModuleConversionReadException $exception) {
        $scene_coverage_findings = $exception->getFindings();
      }
    }
    else {
      $scene_coverage_findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_scene_coverage_pin_missing',
        'pointer' => '/manifest',
        'message' => 'Conversion validation requires a pinned dungeon/version to validate scene placement and runtime object placement.',
        'context' => [],
      ];
    }

    $placements = [];
    if ($pins !== NULL) {
      $placements = $this->collectDungeonPlacements($pins['dungeon_id'], $pins['version_id']);
    }

    $resource_totals = [];
    $receipt_totals = [];
    $required_total = 0;
    $with_receipts = 0;
    $missing_receipts = [];
    $missing_metadata = [];
    $unresolved_dispositions = [];
    $actor_total = 0;
    $actor_with_receipt = 0;
    $actor_reviewed = 0;
    $actor_placed = 0;
    $actor_missing_review = [];
    $actor_invalid_review = [];
    $actor_unplaced = [];
    $actor_source_evidence_missing = [];
    $claimed_instances = [];
    $findings = $scene_coverage_findings;
    $family_coverage = [];
    $review_state = $this->normalizeActorPlacementReviews((array) ($mapping['actor_placement_reviews'] ?? []), $release['plan_hash']);
    $actor_reviews = $review_state['reviews'];
    $actor_invalid_review = $review_state['invalid'];

    foreach ($resources as $resource) {
      if (!is_array($resource)) {
        continue;
      }
      $resource_id = trim((string) ($resource['resource_id'] ?? ''));
      if ($resource_id === '') {
        continue;
      }
      $type = trim((string) ($resource['resource_type'] ?? 'unknown'));
      $disposition = trim((string) ($resource['disposition'] ?? 'unknown'));
      $resource_totals[$type] = ($resource_totals[$type] ?? 0) + 1;
      if (!isset($family_coverage[$type])) {
        $family_coverage[$type] = [
          'expected' => 0,
          'requires_authoring' => 0,
          'with_receipts' => 0,
          'metadata_complete' => 0,
          'placed' => 0,
        ];
      }
      $family_coverage[$type]['expected']++;
      $requires_authoring = $disposition === 'requires_authoring';
      if ($requires_authoring) {
        $required_total++;
        $family_coverage[$type]['requires_authoring']++;
      }
      if ($disposition === 'unsupported') {
        $unresolved_dispositions[] = $resource_id;
      }
      $source = is_array($resource['source'] ?? NULL) ? $resource['source'] : [];
      $source_context = is_array($source['source_context'] ?? NULL) ? $source['source_context'] : [];
      $missing_fields = is_array($source['missing_source_fields'] ?? NULL) ? $source['missing_source_fields'] : [];
      if ($requires_authoring && $missing_fields !== []) {
        $missing_metadata[$resource_id] = array_values(array_filter($missing_fields, static fn(mixed $field): bool => is_string($field) && $field !== ''));
      }
      else {
        $family_coverage[$type]['metadata_complete']++;
      }

      $receipt = $receipts[$resource_id] ?? NULL;
      if (is_array($receipt)) {
        $with_receipts += $requires_authoring ? 1 : 0;
        $family_coverage[$type]['with_receipts'] += $requires_authoring ? 1 : 0;
        $owner = trim((string) ($receipt['owner'] ?? 'unknown'));
        $receipt_totals[$owner] = ($receipt_totals[$owner] ?? 0) + 1;
      }
      elseif ($requires_authoring) {
        $missing_receipts[] = $resource_id;
      }

      if ($type !== 'actor') {
        continue;
      }
      $actor_total++;
      if (is_array($receipt)) {
        $actor_with_receipt++;
      }
      $review = is_array($actor_reviews[$resource_id] ?? NULL) ? $actor_reviews[$resource_id] : NULL;
      $review_status = is_array($review) ? ($review['status'] ?? NULL) : NULL;
      if (!is_string($review_status) || !in_array($review_status, ['placed', 'catalog_only'], TRUE)) {
        $actor_missing_review[] = $resource_id;
      }
      else {
        $actor_reviewed++;
      }
      if ($profile === 'source_faithful' && is_array($review) && $review_status === 'placed'
        && (($review['origin'] ?? NULL) !== 'source' || (array) ($review['source_evidence'] ?? []) === [])) {
        $actor_source_evidence_missing[] = $resource_id;
      }
      if (!is_array($receipt)) {
        continue;
      }
      $family = $this->expectedDefinitionFamily($type);
      $target_id = trim((string) ($receipt['target_id'] ?? ''));
      $target_version = trim((string) ($receipt['target_version'] ?? ''));
      $matches = array_values(array_filter($placements, static fn(array $object): bool =>
        $object['family'] === $family
        && $object['definition_id'] === $target_id
        && $object['definition_version'] === $target_version
      ));
      if ($review_status === 'placed') {
        $expected = $review['placement'];
        $instance_id = $this->matchingReviewedPlacement($matches, $expected, $claimed_instances);
        if ($instance_id !== NULL) {
          $claimed_instances[$instance_id] = $resource_id;
          $actor_placed++;
          $family_coverage[$type]['placed']++;
        }
        else {
          $actor_unplaced[] = $resource_id;
        }
      }
      elseif ($review_status === 'catalog_only' && $matches !== []) {
        $actor_unplaced[] = $resource_id;
      }
      elseif ($profile !== 'original_adaptation' && $review_status !== 'catalog_only') {
        $actor_unplaced[] = $resource_id;
      }
      if (!in_array((string) ($source_context['actor_type'] ?? ''), ['npc', 'hazard', 'loot', 'familiar', 'character'], TRUE)) {
        $missing_metadata[$resource_id] = array_values(array_unique(array_merge($missing_metadata[$resource_id] ?? [], ['actor_type'])));
      }
    }

    if ($missing_receipts !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_required_receipts_missing',
        'pointer' => '/mapping/native_receipts',
        'message' => 'Required authoring resources are missing immutable native receipts.',
        'context' => $this->boundedIdContext($missing_receipts),
      ];
    }
    if ($missing_metadata !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_source_metadata_incomplete',
        'pointer' => '/plan/resources',
        'message' => 'One or more source objects are missing required metadata fields and require reviewed completion.',
        'context' => $this->boundedResourceFieldContext($missing_metadata),
      ];
    }
    if ($unresolved_dispositions !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_resource_disposition_unsupported',
        'pointer' => '/plan/resources',
        'message' => 'Unsupported resources require an explicit reviewed disposition before conversion can be complete.',
        'context' => $this->boundedIdContext($unresolved_dispositions),
      ];
    }
    if ($actor_invalid_review !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_actor_review_invalid',
        'pointer' => '/mapping/actor_placement_reviews',
        'message' => 'One or more actor placement reviews are malformed or stale for the current plan hash.',
        'context' => $this->boundedIdContext($actor_invalid_review),
      ];
    }
    if ($profile !== 'original_adaptation' && $actor_missing_review !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_actor_review_missing',
        'pointer' => '/mapping/actor_placement_reviews',
        'message' => 'Encounter-complete and source-faithful profiles require a reviewed placement decision for each source actor.',
        'context' => $this->boundedIdContext($actor_missing_review),
      ];
    }
    if ($profile !== 'original_adaptation' && $actor_unplaced !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_actor_placement_unverified',
        'pointer' => '/manifest/room_placements',
        'message' => 'Reviewed actors marked for placement must be present in the pinned canonical room versions.',
        'context' => $this->boundedIdContext($actor_unplaced),
      ];
    }
    if ($profile === 'source_faithful' && $actor_source_evidence_missing !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_source_actor_evidence_missing',
        'pointer' => '/mapping/actor_placement_reviews',
        'message' => 'Source-faithful validation requires placed actor decisions backed by source-origin evidence.',
        'context' => $this->boundedIdContext($actor_source_evidence_missing),
      ];
    }
    if ($profile === 'source_faithful') {
      if ($actor_total > 0) {
        $findings[] = [
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_release_source_actor_evidence_unverified',
          'pointer' => '/mapping/actor_placement_reviews',
          'message' => 'Source actor placement claims cannot be certified until reviewed evidence is matched to actual source scene token instances and coordinates.',
          'context' => ['actor_count' => $actor_total],
        ];
      }
      $source_without_tokens = array_values(array_map(
        static fn(array $resource): string => (string) ($resource['resource_id'] ?? ''),
        array_filter($resources, static fn(mixed $resource): bool =>
          is_array($resource)
          && ($resource['resource_type'] ?? NULL) === 'scene'
          && in_array('scene_has_no_tokens', (array) ($resource['reasons'] ?? []), TRUE))
      ));
      if ($source_without_tokens !== []) {
        $findings[] = [
          'scope' => 'module_conversion',
          'severity' => 'error',
          'code' => 'module_release_source_actor_evidence_missing',
          'pointer' => '/plan/resources',
          'message' => 'Source-faithful validation requires source scene token evidence or reviewed, source-backed placement evidence.',
          'context' => $this->boundedIdContext($source_without_tokens, 'scene_resource_ids'),
        ];
      }
    }

    ksort($family_coverage);
    $blocking = array_values(array_filter($findings, static fn(array $finding): bool =>
      in_array($finding['severity'] ?? 'error', ['error', 'critical'], TRUE)));
    return [
      'schema_version' => 'module-conversion-validation-v1',
      'release_id' => $release['release_id'],
      'profile' => $profile,
      'manifest' => $pins === NULL ? NULL : [
        'dungeon_id' => $pins['dungeon_id'],
        'version_id' => $pins['version_id'],
      ],
      'resource_totals' => $resource_totals,
      'receipt_totals_by_owner' => $receipt_totals,
      'family_coverage' => $family_coverage,
      'required_resources' => [
        'total' => $required_total,
        'with_receipts' => $with_receipts,
        'without_receipts' => count(array_unique($missing_receipts)),
      ],
      'actor_coverage' => [
        'source_total' => $actor_total,
        'with_receipts' => $actor_with_receipt,
        'reviewed_decisions' => $actor_reviewed,
        'placed_in_pinned_rooms' => $actor_placed,
        'missing_review' => count(array_unique($actor_missing_review)),
        'unplaced_or_unverified' => count(array_unique($actor_unplaced)),
      ],
      'scene_coverage' => $scene_coverage,
      'complete' => $blocking === [],
      'remediation_queue' => $this->buildRemediationQueue($findings),
      'blocking_findings' => $blocking,
      'all_findings' => $findings,
    ];
  }

  /**
   * @param array<int,array<string,mixed>> $matches
   * @param array<string,mixed> $expected
   * @param array<string,string> $claimed_instances
   */
  private function matchingReviewedPlacement(array $matches, array $expected, array $claimed_instances): ?string {
    $exact = array_values(array_filter($matches, static fn(array $object): bool =>
      $object['room_id'] === $expected['room_id']
      && $object['room_version'] === $expected['room_version']
      && $object['anchor_hex'] === $expected['anchor_hex']
      && $object['instance_id'] === $expected['instance_id']
    ));
    return count($exact) === 1 && !isset($claimed_instances[$exact[0]['instance_id']])
      ? $exact[0]['instance_id']
      : NULL;
  }

  /**
   * @param array<string,mixed> $release
   * @param array<string,mixed> $options
   *
   * @return array{dungeon_id:string,version_id:string}|null
   */
  private function resolveValidationPins(array $release, array $options): ?array {
    $manifest = is_array($options['manifest'] ?? NULL) ? $options['manifest'] : [];
    $dungeon_id = trim((string) ($manifest['dungeon_id'] ?? ''));
    $version_id = trim((string) ($manifest['version_id'] ?? ''));
    if ($dungeon_id !== '' && $version_id !== '') {
      return ['dungeon_id' => $dungeon_id, 'version_id' => $version_id];
    }
    $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
    $mapped_dungeon = trim((string) ($mapping['dungeon_id'] ?? ''));
    $mapped_version = trim((string) ($mapping['version_id'] ?? ''));
    if ($mapped_dungeon !== '' && $mapped_version !== '') {
      return ['dungeon_id' => $mapped_dungeon, 'version_id' => $mapped_version];
    }
    $receipts = is_array($mapping['native_receipts'] ?? NULL) ? $mapping['native_receipts'] : [];
    $dungeon_receipts = array_values(array_filter($receipts, static fn(mixed $receipt): bool =>
      is_array($receipt) && ($receipt['owner'] ?? NULL) === 'DungeonEditorService'
      && is_string($receipt['target_id'] ?? NULL) && trim((string) $receipt['target_id']) !== ''
      && is_string($receipt['target_version'] ?? NULL) && trim((string) $receipt['target_version']) !== ''
    ));
    if (count($dungeon_receipts) !== 1) {
      return NULL;
    }
    return [
      'dungeon_id' => trim((string) $dungeon_receipts[0]['target_id']),
      'version_id' => trim((string) $dungeon_receipts[0]['target_version']),
    ];
  }

  private function validationProfile(mixed $profile): string {
    if ($profile === NULL) {
      return 'original_adaptation';
    }
    if (!is_string($profile) || !in_array(trim($profile), ['original_adaptation', 'encounter_complete_adaptation', 'source_faithful'], TRUE)) {
      throw $this->error('module_release_validation_profile_invalid', '/profile', 'Unknown conversion validation profile.', 422);
    }
    return trim($profile);
  }

  /**
   * @param array<string,mixed> $reviews
   *
   * @return array{reviews:array<string,array<string,mixed>>,invalid:array<int,string>}
   */
  private function normalizeActorPlacementReviews(array $reviews, string $plan_hash): array {
    $normalized = [];
    $invalid = [];
    foreach ($reviews as $resource_id => $review) {
      $key = trim((string) $resource_id);
      if ($key === '' || !is_array($review)) {
        if ($key !== '') {
          $invalid[] = $key;
        }
        continue;
      }
      $status = strtolower(trim((string) ($review['status'] ?? '')));
      $origin = strtolower(trim((string) ($review['origin'] ?? '')));
      $reviewed_by = (int) ($review['reviewed_by'] ?? 0);
      $reviewed_at = (int) ($review['reviewed_at'] ?? 0);
      $review_plan_hash = strtolower(trim((string) ($review['plan_hash'] ?? '')));
      $rationale = trim((string) ($review['rationale'] ?? ''));
      if (!in_array($status, self::ACTOR_REVIEW_STATUSES, TRUE)
        || !in_array($origin, self::ACTOR_REVIEW_ORIGINS, TRUE)
        || $reviewed_by <= 0
        || $reviewed_at <= 0
        || !preg_match('/^[a-f0-9]{64}$/D', $review_plan_hash)
        || !hash_equals($plan_hash, $review_plan_hash)
        || $rationale === '') {
        $invalid[] = $key;
        continue;
      }
      $normalized[$key] = [
        'resource_id' => $key,
        'status' => $status,
        'origin' => $origin,
        'reviewed_by' => $reviewed_by,
        'reviewed_at' => $reviewed_at,
        'plan_hash' => $review_plan_hash,
        'rationale' => $rationale,
        'source_evidence' => $this->normalizeActorReviewEvidence($review['source_evidence'] ?? []),
      ];
      if ($status === 'placed') {
        try {
          $normalized[$key]['placement'] = $this->normalizeActorReviewPlacement($review['placement'] ?? NULL);
        }
        catch (ModuleConversionReadException) {
          $invalid[] = $key;
          unset($normalized[$key]);
        }
      }
    }
    ksort($normalized);
    sort($invalid);
    return ['reviews' => $normalized, 'invalid' => array_values(array_unique($invalid))];
  }

  /**
   * @param mixed $raw
   *
   * @return array{room_id:string,room_version:string,instance_id:string,anchor_hex:array{q:int,r:int}}
   */
  private function normalizeActorReviewPlacement(mixed $raw): array {
    if (!is_array($raw) || array_is_list($raw)) {
      throw $this->error(
        'module_release_actor_review_placement_invalid',
        '/review/placement',
        'Placed actor review decisions require placement metadata.',
        422
      );
    }
    $room_id = trim((string) ($raw['room_id'] ?? ''));
    $room_version = trim((string) ($raw['room_version'] ?? ''));
    $instance_id = trim((string) ($raw['instance_id'] ?? ''));
    $anchor_hex = $raw['anchor_hex'] ?? NULL;
    if ($room_id === '' || $room_version === '' || $instance_id === ''
      || !is_array($anchor_hex) || array_is_list($anchor_hex)
      || !is_int($anchor_hex['q'] ?? NULL) || !is_int($anchor_hex['r'] ?? NULL)) {
      throw $this->error(
        'module_release_actor_review_placement_invalid',
        '/review/placement',
        'Placed actor review decisions require room_id, room_version, instance_id, and an anchor_hex with integer q and r.',
        422
      );
    }
    return [
      'room_id' => $room_id,
      'room_version' => $room_version,
      'instance_id' => $instance_id,
      'anchor_hex' => ['q' => $anchor_hex['q'], 'r' => $anchor_hex['r']],
    ];
  }

  /**
   * @param mixed $raw
   *
   * @return array<string,mixed>
   */
  private function normalizeActorReviewEvidence(mixed $raw): array {
    if ($raw === NULL || $raw === []) {
      return [];
    }
    if (!is_array($raw) || array_is_list($raw)) {
      throw $this->error(
        'module_release_actor_review_evidence_invalid',
        '/review/source_evidence',
        'source_evidence must be an object when present.',
        422
      );
    }
    $normalized = [];
    foreach ($raw as $key => $value) {
      $field = trim((string) $key);
      if ($field === '') {
        continue;
      }
      if (is_scalar($value)) {
        $normalized[$field] = $value;
      }
    }
    ksort($normalized);
    return $normalized;
  }

  /**
   * @param array<int,array<string,mixed>> $findings
   *
   * @return array<int,array<string,mixed>>
   */
  private function buildRemediationQueue(array $findings): array {
    $queue = [];
    foreach ($findings as $finding) {
      if (!is_array($finding)) {
        continue;
      }
      $code = trim((string) ($finding['code'] ?? ''));
      if ($code === '') {
        continue;
      }
      $queue[] = [
        'code' => $code,
        'severity' => (string) ($finding['severity'] ?? 'error'),
        'route' => match ($code) {
          'module_release_required_receipts_missing' => 'native_execution',
          'module_release_source_metadata_incomplete' => 'metadata_review_or_generation',
          'module_release_resource_disposition_unsupported' => 'scope_or_owner_decision',
          'module_release_actor_review_missing',
          'module_release_actor_review_invalid' => 'actor_review',
          'module_release_actor_placement_unverified' => 'canonical_room_placement',
          'module_release_source_actor_evidence_missing',
          'module_release_source_actor_evidence_unverified' => 'source_evidence_review',
          default => 'manual_triage',
        },
        'pointer' => (string) ($finding['pointer'] ?? ''),
      ];
    }
    usort($queue, static fn(array $a, array $b): int => strnatcasecmp(
      (string) $a['severity'] . ':' . (string) $a['code'],
      (string) $b['severity'] . ':' . (string) $b['code']
    ));
    return $queue;
  }

  /**
   * @param array<int,string> $resource_ids
   *
   * @return array<string,mixed>
   */
  private function boundedIdContext(array $resource_ids, string $field = 'resource_ids', int $limit = 200): array {
    $unique = array_values(array_unique(array_filter(array_map(
      static fn(mixed $id): string => trim((string) $id),
      $resource_ids
    ))));
    sort($unique);
    $slice = array_slice($unique, 0, $limit);
    return [
      $field => $slice,
      'count' => count($unique),
      'truncated' => count($unique) > count($slice),
    ];
  }

  /**
   * @param array<string,array<int,string>> $resources
   *
   * @return array<string,mixed>
   */
  private function boundedResourceFieldContext(array $resources, int $resource_limit = 150, int $field_limit = 20): array {
    ksort($resources);
    $normalized = [];
    $count = 0;
    foreach ($resources as $resource_id => $fields) {
      $id = trim((string) $resource_id);
      if ($id === '') {
        continue;
      }
      $count++;
      if (count($normalized) >= $resource_limit) {
        continue;
      }
      $unique = array_values(array_unique(array_filter(array_map(
        static fn(mixed $field): string => trim((string) $field),
        (array) $fields
      ))));
      sort($unique);
      $normalized[$id] = array_slice($unique, 0, $field_limit);
    }
    return [
      'resources' => $normalized,
      'count' => $count,
      'truncated' => $count > count($normalized),
    ];
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function collectDungeonPlacements(string $dungeon_id, string $version_id): array {
    $version = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
      ->fields('v', ['dungeon_payload', 'payload_hash'])
      ->condition('v.dungeon_id', $dungeon_id)
      ->condition('v.version_id', $version_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($version) || !hash_equals((string) $version['payload_hash'], hash('sha256', (string) $version['dungeon_payload']))) {
      throw $this->error('module_release_placement_dungeon_integrity_invalid', '/manifest', 'Pinned dungeon version is missing or failed integrity verification.', 422);
    }
    $dungeon = $this->decodeJsonObject((string) $version['dungeon_payload']);
    $objects = [];
    $seen_instances = [];
    foreach ((array) ($dungeon['room_placements'] ?? []) as $placement) {
      if (!is_array($placement)
        || !is_string($placement['room_id'] ?? NULL)
        || !is_string($placement['version_id'] ?? NULL)
        || trim($placement['room_id']) === '' || trim($placement['version_id']) === '') {
        throw $this->error('module_release_placement_room_invalid', '/manifest/room_placements', 'Pinned dungeon has an invalid room placement.', 422);
      }
      $room_version = $this->database->select('dungeoncrawler_content_room_versions', 'v')
        ->fields('v', ['room_payload', 'payload_hash'])
        ->condition('v.room_id', (string) $placement['room_id'])
        ->condition('v.version_id', (string) $placement['version_id'])
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($room_version) || !hash_equals((string) $room_version['payload_hash'], hash('sha256', (string) $room_version['room_payload']))) {
        throw $this->error('module_release_placement_room_integrity_invalid', '/manifest/room_placements', 'Pinned room version is missing or failed integrity verification.', 422, [
          'room_id' => $placement['room_id'], 'room_version' => $placement['version_id'],
        ]);
      }
      $room = $this->decodeJsonObject((string) $room_version['room_payload']);
      foreach ((array) ($room['placements'] ?? []) as $object) {
        if (!is_array($object)) {
          throw $this->error('module_release_placement_object_invalid', '/manifest/room_placements', 'Pinned room contains an invalid object placement.', 422);
        }
        $family = trim((string) ($object['definition_ref']['family'] ?? ''));
        $definition_id = trim((string) ($object['definition_ref']['definition_id'] ?? ''));
        $object_version = trim((string) ($object['definition_ref']['version'] ?? ''));
        $instance_id = trim((string) ($object['instance_id'] ?? ''));
        $anchor_hex = $object['anchor_hex'] ?? NULL;
        if ($family === '' || $definition_id === '' || $object_version === '' || $instance_id === ''
          || !is_array($anchor_hex) || !is_int($anchor_hex['q'] ?? NULL) || !is_int($anchor_hex['r'] ?? NULL)
          || isset($seen_instances[$instance_id])) {
          throw $this->error('module_release_placement_object_invalid', '/manifest/room_placements', 'Pinned room contains a malformed or duplicate object instance.', 422, ['instance_id' => $instance_id]);
        }
        $seen_instances[$instance_id] = TRUE;
        $objects[] = [
          'room_id' => $placement['room_id'],
          'room_version' => $placement['version_id'],
          'instance_id' => $instance_id,
          'anchor_hex' => ['q' => $anchor_hex['q'], 'r' => $anchor_hex['r']],
          'family' => $family,
          'definition_id' => $definition_id,
          'definition_version' => $object_version,
        ];
      }
    }
    return $objects;
  }

  private function expectedDefinitionFamily(string $resource_type): string {
    return match ($resource_type) {
      'embedded_item', 'item' => 'item',
      default => $resource_type,
    };
  }

  /**
   * @param array<string,mixed> $release
   *
   * @return array<string,mixed>
   */
  private function sceneCoverageForRelease(array $release, string $dungeon_id, string $version_id): array {
    $plan = is_array($release['plan'] ?? NULL) ? $release['plan'] : [];
    $mapping = is_array($release['mapping'] ?? NULL) ? $release['mapping'] : [];
    $receipts = is_array($mapping['native_receipts'] ?? NULL) ? $mapping['native_receipts'] : [];
    $dungeon_version = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
      ->fields('v', ['dungeon_payload', 'payload_hash'])
      ->condition('v.dungeon_id', $dungeon_id)
      ->condition('v.version_id', $version_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($dungeon_version)) {
      throw $this->error('module_release_scene_coverage_dungeon_missing', '/manifest', 'Pinned dungeon version has no canonical payload.', 422);
    }
    $payload_json = (string) $dungeon_version['dungeon_payload'];
    if (!hash_equals((string) $dungeon_version['payload_hash'], hash('sha256', $payload_json))) {
      throw $this->error('module_release_scene_coverage_dungeon_integrity_invalid', '/manifest', 'Pinned dungeon payload does not match its published hash.', 422);
    }
    $dungeon = $this->decodeJsonObject($payload_json);
    $placements = $dungeon['room_placements'] ?? NULL;
    if (!is_array($placements)) {
      throw $this->error('module_release_scene_coverage_placements_invalid', '/manifest', 'Pinned dungeon has no room_placements array.', 422);
    }
    $placed_versions = [];
    foreach ($placements as $placement) {
      if (!is_array($placement)) {
        throw $this->error('module_release_scene_coverage_placements_invalid', '/manifest', 'Pinned dungeon has a malformed room placement.', 422);
      }
      $key = (string) ($placement['room_id'] ?? '') . ':' . (string) ($placement['version_id'] ?? '');
      $placed_versions[$key] = ($placed_versions[$key] ?? 0) + 1;
    }

    $scene_count = 0;
    $placed_count = 0;
    $missing = [];
    $unrealized = [];
    $reviewed_original_count = 0;
    foreach ((array) ($plan['resources'] ?? []) as $resource) {
      if (!is_array($resource) || ($resource['resource_type'] ?? '') !== 'scene') {
        continue;
      }
      $scene_count++;
      $resource_id = trim((string) ($resource['resource_id'] ?? ''));
      $receipt = $receipts[$resource_id] ?? NULL;
      if (!is_array($receipt)
        || ($receipt['owner'] ?? '') !== 'RoomEditorService'
        || ($placed_versions[(string) ($receipt['target_id'] ?? '') . ':' . (string) ($receipt['target_version'] ?? '')] ?? 0) !== 1) {
        $missing[] = $resource_id;
        continue;
      }
      $placed_count++;
      $room_version = $this->database->select('dungeoncrawler_content_room_versions', 'v')
        ->fields('v', ['room_payload', 'payload_hash'])
        ->condition('v.room_id', (string) $receipt['target_id'])
        ->condition('v.version_id', (string) $receipt['target_version'])
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!is_array($room_version)) {
        throw $this->error('module_release_scene_coverage_room_missing', '/manifest', 'Placed source room version is missing from the canonical room store.', 422, [
          'resource_id' => $resource_id,
        ]);
      }
      $room_json = (string) $room_version['room_payload'];
      if (!hash_equals((string) $room_version['payload_hash'], hash('sha256', $room_json))) {
        $unrealized[] = $resource_id;
        continue;
      }
      $room = $this->decodeJsonObject($room_json);
      $original = ($receipt['source_receipt']['execution_mode'] ?? NULL) === 'original_scene_generation';
      $scene = $room['metadata']['module_conversion']['source_scene'] ?? [];
      $review = $scene['map_review'] ?? NULL;
      $original_map_review = $scene['original_map_review'] ?? NULL;
      $source_art_valid = is_array($review)
        && in_array($review['role'] ?? NULL, ['interpretive_gallery', 'overland_overview'], TRUE)
        && ($review['terrain_basis'] ?? NULL) === 'original_canonical'
        && is_string($review['notes'] ?? NULL) && mb_strlen(trim($review['notes'])) >= 30
        && !isset($scene['map_alignment'])
        && $this->reviewedSceneAssetsPresent($scene)
        && ($receipt['source_receipt']['map_review'] ?? NULL) === $review;
      $original_art_valid = is_array($original_map_review)
        && ($original_map_review['rights_status'] ?? NULL) === 'project_original'
        && ($receipt['source_receipt']['original_map'] ?? NULL) === $original_map_review
        && $this->reviewedOriginalMapPresent($scene, $original_map_review);
      if (($room['metadata']['module_conversion']['resource_id'] ?? '') !== $resource_id
        || ($room['metadata']['module_conversion']['source_scene']['conversion_state'] ?? '') !== 'playable_validated'
        || ($original && (!is_array($scene['map_assets']['background'] ?? NULL)
          || ($source_art_valid === $original_art_valid)))
        || !is_array($room['placements'] ?? NULL)
        || $room['placements'] === []) {
        $unrealized[] = $resource_id;
        continue;
      }

      $room_validation = $this->roomEditor->validateAggregate($room, 'publication');
      if (($room_validation['errors'] ?? []) !== []) {
        $unrealized[] = $resource_id;
      }
      elseif ($original) {
        $reviewed_original_count++;
      }
    }
    $quest_verified = $reviewed_original_count === 0;
    if ($reviewed_original_count > 0 && $missing === [] && $unrealized === []) {
      $quest_verified = $this->reviewedQuestCoversDungeon($dungeon, $placements, $receipts, $dungeon_id, $version_id);
    }
    if ($scene_count > 0 && $missing === [] && $unrealized === []) {
      $validation = $this->dungeonEditor->validateAggregate([
        'draft_id' => $version_id,
        'revision' => 0,
        'dungeon' => $dungeon,
      ], 'publication');
      if (($validation['is_valid'] ?? FALSE) !== TRUE) {
        throw $this->error('module_release_scene_coverage_dungeon_invalid', '/manifest', 'Pinned dungeon fails canonical publication validation.', 422, [
          'findings' => $validation['findings'] ?? [],
        ]);
      }
    }
    $findings = [];
    if ($missing !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_scene_receipts_unplaced',
        'pointer' => '/manifest/room_placements',
        'message' => 'Converted scene receipts must each match one exact pinned room placement.',
        'context' => ['resource_ids' => $missing],
      ];
    }
    if ($unrealized !== []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_scene_rooms_unrealized',
        'pointer' => '/manifest/room_placements',
        'message' => 'Pinned scene rooms must pass canonical publication and reviewed asset validation.',
        'context' => ['resource_ids' => $unrealized],
      ];
    }
    if (!$quest_verified && $missing === [] && $unrealized === []) {
      $findings[] = [
        'scope' => 'module_conversion',
        'severity' => 'error',
        'code' => 'module_release_reviewed_quest_incomplete',
        'pointer' => '/manifest/quest_template_id',
        'message' => 'The canonical quest must cover every pinned scene and any authored item interaction in its exact room version.',
        'context' => ['quest_template_id' => $dungeon['metadata']['module_conversion']['quest_template_id'] ?? NULL],
      ];
    }
    return [
      'complete' => $missing === [] && $unrealized === [] && $quest_verified,
      'dungeon_id' => $dungeon_id,
      'version_id' => $version_id,
      'scene_count' => $scene_count,
      'placed_count' => $placed_count,
      'missing_resource_ids' => $missing,
      'unrealized_resource_ids' => $unrealized,
      'quest_verified' => $quest_verified,
      'blocking_findings' => $findings,
    ];
  }

  /**
   * Pinned gallery art must still exist with its attested bytes and notice.
   */
  private function reviewedSceneAssetsPresent(array $scene): bool {
    if ($this->fileSystem === NULL || !is_array($scene['map_assets']['tiles'] ?? NULL)) {
      return FALSE;
    }
    foreach ([$scene['map_assets']['background'], ...$scene['map_assets']['tiles']] as $asset) {
      if (!is_array($asset) || !is_int($asset['image_id'] ?? NULL) || !is_string($asset['sha256'] ?? NULL)
        || !is_string($asset['license_notice_url'] ?? NULL) || $asset['license_notice_url'] === '') {
        return FALSE;
      }
      $row = $this->database->select('dc_generated_images', 'i')
        ->fields('i', ['file_uri', 'sha256', 'provider'])
        ->condition('id', $asset['image_id'])
        ->range(0, 1)->execute()->fetchAssoc();
      if (!is_array($row) || $row['provider'] !== 'module-import' || $row['sha256'] !== $asset['sha256']) {
        return FALSE;
      }
      $path = $this->fileSystem->realpath((string) $row['file_uri']);
      $notice = $this->fileSystem->realpath((string) $row['file_uri'] . '.LICENSE.txt');
      if (!is_string($path) || !is_file($path) || !is_string($notice) || !is_file($notice)
        || !hash_equals($asset['sha256'], hash_file('sha256', $path))) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Resolve an existing original generated image without creating another copy.
   */
  private function originalMapDescriptor(array $review): ?array {
    if ($this->fileSystem === NULL || $this->images === NULL || !is_int($review['image_id'] ?? NULL)
      || !is_string($review['sha256'] ?? NULL)) {
      return NULL;
    }
    $row = $this->database->select('dc_generated_images', 'i')
      ->fields('i', ['id', 'file_uri', 'public_url', 'sha256', 'provider', 'mime_type', 'width', 'height', 'status', 'deleted'])
      ->condition('id', $review['image_id'])
      ->range(0, 1)->execute()->fetchAssoc();
    if (!is_array($row) || $row['sha256'] !== $review['sha256']
      || $row['provider'] === 'module-import' || trim((string) $row['provider']) === ''
      || $row['status'] !== 'ready' || (int) $row['deleted'] !== 0
      || !in_array($row['mime_type'], ['image/png', 'image/jpeg', 'image/webp'], TRUE)
      || (int) $row['width'] < 1 || (int) $row['height'] < 1) {
      return NULL;
    }
    $path = $this->fileSystem->realpath((string) $row['file_uri']);
    if (!is_string($path) || !is_file($path)
      || !hash_equals($review['sha256'], hash_file('sha256', $path) ?: '')) {
      return NULL;
    }
    $url = $this->images->resolveClientUrl($row);
    if (!is_string($url) || $url === '') {
      return NULL;
    }
    return [
      'image_id' => (int) $row['id'],
      'url' => $url,
      'sha256' => $row['sha256'],
      'width' => (int) $row['width'],
      'height' => (int) $row['height'],
      'asset_kind' => 'generated_original_scene_map',
    ];
  }

  private function reviewedOriginalMapAssets(array $review): array {
    $background = $this->originalMapDescriptor($review);
    if ($background === NULL) {
      throw $this->error('module_release_original_map_missing', '/original_scene_generation/scene_definitions/original_map', 'Reviewed original map image is missing, nonpublic, or changed from its pinned bytes.', 422);
    }
    return ['background' => $background, 'tiles' => []];
  }

  private function reviewedOriginalMapPresent(array $scene, array $review): bool {
    $background = $this->originalMapDescriptor($review);
    return $background !== NULL
      && ($scene['map_assets']['background'] ?? NULL) === $background
      && ($scene['map_assets']['tiles'] ?? NULL) === [];
  }

  /**
   * Require one canonical, non-quarantined objective for every exact placement.
   */
  private function reviewedQuestCoversDungeon(array $dungeon, array $placements, array $receipts, string $dungeon_id, string $version_id): bool {
    $template_id = $dungeon['metadata']['module_conversion']['quest_template_id'] ?? NULL;
    if (!is_string($template_id) || trim($template_id) === '' || $this->canonicalQuests === NULL) {
      return FALSE;
    }
    $dungeon_receipt = NULL;
    foreach ($receipts as $receipt) {
      if (($receipt['owner'] ?? NULL) === 'DungeonEditorService'
        && ($receipt['target_id'] ?? NULL) === $dungeon_id
        && ($receipt['target_version'] ?? NULL) === $version_id) {
        $dungeon_receipt = $receipt;
      }
    }
    if (($dungeon_receipt['source_receipt']['quest_template_id'] ?? NULL) !== $template_id) {
      return FALSE;
    }
    $rows = $this->database->select('dc_canonical_quests', 'q')
      ->fields('q', ['template_id', 'version', 'objectives_schema'])
      ->condition('template_id', $template_id)
      ->range(0, 2)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if (count($rows) !== 1 || $rows[0]['template_id'] !== $template_id) {
      return FALSE;
    }
    $this->canonicalQuests->requireCanonicalQuestTemplateRef($template_id, (string) $rows[0]['version']);
    $phases = json_decode((string) $rows[0]['objectives_schema'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($phases) || count($phases) !== 1 || !is_array($phases[0]['objectives'] ?? NULL)) {
      return FALSE;
    }
    $locations = [];
    $interaction_items = [];
    foreach ($phases[0]['objectives'] as $objective) {
      if (!is_array($objective)
        || !is_string($objective['location_id'] ?? NULL)
        || ($objective['location'] ?? NULL) !== $objective['location_id']) {
        return FALSE;
      }
      if (($objective['type'] ?? NULL) === 'explore'
        && ($objective['completion_criteria']['metric'] ?? NULL) === 'discovered'
        && ($objective['completion_criteria']['required_value'] ?? NULL) === TRUE) {
        $locations[] = $objective['location_id'];
      }
      elseif (($objective['type'] ?? NULL) === 'interact'
        && ($objective['completion_criteria']['metric'] ?? NULL) === 'completed'
        && ($objective['completion_criteria']['required_value'] ?? NULL) === TRUE
        && is_string($objective['item'] ?? NULL) && $objective['item'] !== ''
        && ($objective['target'] ?? NULL) === $objective['item']
        && !isset($interaction_items[$objective['location_id']])) {
        $interaction_items[$objective['location_id']] = $objective['item'];
      }
      else {
        return FALSE;
      }
    }
    $pinned = array_column($placements, 'placement_id');
    sort($locations);
    sort($pinned);
    if ($pinned === [] || $locations !== $pinned) {
      return FALSE;
    }
    if ($interaction_items === []) {
      return TRUE;
    }
    $items = array_values($interaction_items);
    if (count($items) !== count($placements) || count(array_unique($items)) !== count($items)) {
      return FALSE;
    }
    foreach ($placements as $placement) {
      $item_id = $interaction_items[$placement['placement_id'] ?? ''] ?? NULL;
      if (!is_string($item_id)) {
        return FALSE;
      }
      $row = $this->database->select('dungeoncrawler_content_room_versions', 'v')
        ->fields('v', ['room_payload', 'payload_hash'])
        ->condition('v.room_id', (string) ($placement['room_id'] ?? ''))
        ->condition('v.version_id', (string) ($placement['version_id'] ?? ''))
        ->range(0, 1)->execute()->fetchAssoc();
      if (!is_array($row) || !hash_equals((string) $row['payload_hash'], hash('sha256', (string) $row['room_payload']))) {
        return FALSE;
      }
      $room = $this->decodeJsonObject((string) $row['room_payload']);
      $matches = array_filter((array) ($room['placements'] ?? []), static fn($entity): bool =>
        is_array($entity)
        && ($entity['definition_ref']['family'] ?? NULL) === 'item'
        && ($entity['definition_ref']['definition_id'] ?? NULL) === $item_id);
      if (count($matches) !== 1) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function assertPublishedDungeonVersionExists(string $dungeon_id, string $version_id): void {
    if (!$this->publishedDungeonVersionExists($dungeon_id, $version_id)) {
      throw $this->error('module_release_manifest_dungeon_version_missing', '/manifest', 'Published dungeon/version pin is not available in canonical stores.', 422, [
        'dungeon_id' => $dungeon_id,
        'version_id' => $version_id,
      ]);
    }
  }

  private function publishedDungeonVersionExists(string $dungeon_id, string $version_id): bool {
    if (!$this->database->schema()->tableExists('dungeoncrawler_content_dungeon_versions')
      || !$this->database->schema()->tableExists('dungeoncrawler_content_dungeons')) {
      return FALSE;
    }
    $version = $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
      ->fields('v', ['version_id'])
      ->condition('v.version_id', $version_id)
      ->condition('v.dungeon_id', $dungeon_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!$version) {
      return FALSE;
    }
    $published = $this->database->select('dungeoncrawler_content_dungeons', 'd')
      ->fields('d', ['dungeon_id'])
      ->condition('d.dungeon_id', $dungeon_id)
      ->condition('d.published_version_id', $version_id)
      ->condition('d.publication_status', 'published')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return (bool) $published;
  }

  private function assertReceiptTargetExists(string $owner, string $target_id, string $target_version, string $pointer): void {
    if (str_starts_with($target_id, 'moduleconv:')) {
      throw $this->error('module_release_receipt_target_synthetic', $pointer, 'Synthetic module conversion targets cannot satisfy native publication receipts.', 422, [
        'owner' => $owner,
        'target_id' => $target_id,
        'target_version' => $target_version,
      ]);
    }
    $exists = match ($owner) {
      'DungeonEditorService' => (bool) $this->database->select('dungeoncrawler_content_dungeon_versions', 'v')
        ->fields('v', ['version_id'])
        ->condition('v.version_id', $target_version)
        ->condition('v.dungeon_id', $target_id)
        ->range(0, 1)
        ->execute()
        ->fetchField(),
      'RoomEditorService' => (bool) $this->database->select('dungeoncrawler_content_room_versions', 'v')
        ->fields('v', ['version_id'])
        ->condition('v.version_id', $target_version)
        ->condition('v.room_id', $target_id)
        ->range(0, 1)
        ->execute()
        ->fetchField(),
      'CanonicalDefinitionService' => (bool) (
      $this->database->select('dc_canonical_actors', 'a')
        ->fields('a', ['actor_id'])
        ->condition('a.actor_id', $target_id)
        ->condition('a.version', $target_version)
        ->range(0, 1)
        ->execute()
        ->fetchField()
      ?: $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['content_id'])
        ->condition('r.content_id', $target_id)
        ->condition('r.version', $target_version)
        ->range(0, 1)
        ->execute()
        ->fetchField()
      ),
      'StorylineManagerService' => (bool) $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['id'])
        ->condition('r.content_type', 'module_story_fact_draft')
        ->condition('r.content_id', $target_id)
        ->condition('r.version', $target_version)
        ->range(0, 1)
        ->execute()
        ->fetchField(),
      'ModuleConversionReferenceLedger' => (bool) $this->database->select('dungeoncrawler_content_registry', 'r')
        ->fields('r', ['id'])
        ->condition('r.content_type', 'module_conversion_reference')
        ->condition('r.content_id', $target_id)
        ->condition('r.version', $target_version)
        ->range(0, 1)
        ->execute()
        ->fetchField(),
      default => throw $this->error('module_release_receipt_owner_unsupported', $pointer . '/owner', 'Receipt owner is not supported for native target verification.', 422, [
        'owner' => $owner,
      ]),
    };
    if (!$exists) {
      throw $this->error('module_release_receipt_target_missing', $pointer, 'Receipt target_id/target_version was not found in native published stores.', 422, [
        'owner' => $owner,
        'target_id' => $target_id,
        'target_version' => $target_version,
      ]);
    }
  }

  /**
   * @param array<string,mixed> $context
   */
  private function error(string $code, string $pointer, string $message, int $http_status = 422, array $context = [], ?\Throwable $previous = NULL): ModuleConversionReadException {
    return new ModuleConversionReadException($code, [[
      'scope' => 'module_conversion',
      'severity' => 'error',
      'code' => $code,
      'pointer' => $pointer,
      'message' => $message,
      'context' => $context,
    ]], $http_status, $previous);
  }

  /**
   * @param array<string,mixed> $value
   */
  private function encode(array $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      throw $this->error('module_release_json_encoding_failed', '/json', 'Failed to encode release payload.', 500);
    }
    return $encoded;
  }

}
