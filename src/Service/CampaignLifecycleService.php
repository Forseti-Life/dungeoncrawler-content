<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;

/**
 * Single-path owner of campaign lifecycle transitions and the canonical
 * campaign-authority cutover invariant.
 *
 * Every archive/unarchive caller (CampaignController, CampaignArchiveForm,
 * CampaignUnarchiveForm) and the game launch/state guard route through this
 * service so the transition logic and the canonical `campaign_data.authority`
 * contract are defined exactly once.
 *
 * Board cutover decision (HQ 57871ad098): there is no backward compatibility
 * for legacy campaigns. A campaign whose `campaign_data.authority` marker is
 * missing or invalid is legacy: it may be archived but can never be unarchived
 * into, or launched by, the current runtime. Those paths hard-fail visibly with
 * `legacy_campaign_archived`. No compatibility adapters, fallback payloads, or
 * default authority are ever synthesized.
 */
class CampaignLifecycleService {

  /**
   * Canonical authority marker key inside campaign_data.
   */
  public const AUTHORITY_KEY = 'authority';

  /**
   * Canonical graph source value written by current campaign creation.
   */
  public const GRAPH_SOURCE = 'campaign_tables';

  /**
   * Canonical delivery snapshot policy value written by current creation.
   */
  public const DELIVERY_SNAPSHOT_POLICY = 'projection_only';

  /**
   * Archive reason: an explicit user/controller-initiated archive.
   */
  public const REASON_MANUAL = 'manual';

  /**
   * Archive reason: campaign lacked the canonical authority contract.
   */
  public const REASON_LEGACY_CONTRACT = 'legacy_campaign_contract';

  /**
   * Statuses a campaign may be restored to on unarchive.
   */
  private const RESTORABLE_STATUSES = ['draft', 'ready', 'active', 'completed'];

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Whether campaign_data carries the canonical authority marker.
   *
   * @param array<string,mixed> $campaign_data
   */
  public function hasCanonicalAuthority(array $campaign_data): bool {
    $authority = $campaign_data[self::AUTHORITY_KEY] ?? NULL;
    if (!is_array($authority)) {
      return FALSE;
    }
    return ($authority['graph_source'] ?? NULL) === self::GRAPH_SOURCE
      && ($authority['delivery_snapshot_policy'] ?? NULL) === self::DELIVERY_SNAPSHOT_POLICY;
  }

  /**
   * Hard-fail unless campaign_data carries the canonical authority marker.
   *
   * @param array<string,mixed> $campaign_data
   *
   * @throws \Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException
   */
  public function assertCanonicalAuthority(int $campaign_id, array $campaign_data): void {
    if (!$this->hasCanonicalAuthority($campaign_data)) {
      throw new LegacyCampaignArchivedException(sprintf(
        'legacy_campaign_archived: campaign %d lacks the canonical campaign_data.authority contract (graph_source=%s, delivery_snapshot_policy=%s) and cannot be used by the current runtime.',
        $campaign_id,
        self::GRAPH_SOURCE,
        self::DELIVERY_SNAPSHOT_POLICY
      ));
    }
  }

  /**
   * Archive a campaign (idempotent). No campaign is ever deleted here.
   *
   * @return array{status:string,name:string,previous_status:string}
   *   status is one of: archived, already_archived, not_found.
   */
  public function archive(int $campaign_id, string $reason = self::REASON_MANUAL): array {
    $campaign = $this->loadCampaign($campaign_id);
    if ($campaign === NULL) {
      return ['status' => 'not_found', 'name' => '', 'previous_status' => ''];
    }

    $name = (string) $campaign->name;
    $previous_status = (string) $campaign->status;
    if ($previous_status === 'archived') {
      return ['status' => 'already_archived', 'name' => $name, 'previous_status' => $previous_status];
    }

    $campaign_data = $this->decodeCampaignData($campaign->campaign_data);
    $campaign_data['_archive_meta'] = [
      'previous_status' => $previous_status,
      'archived_at' => $this->time->getRequestTime(),
      'reason' => $reason,
    ];

    $this->persist($campaign_id, 'archived', $campaign_data);

    return ['status' => 'archived', 'name' => $name, 'previous_status' => $previous_status];
  }

  /**
   * Unarchive a canonical campaign, or hard-fail for legacy campaigns.
   *
   * @return array{status:string,name:string,restored_status:string}
   *   status is one of: unarchived, not_archived, not_found.
   *
   * @throws \Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException
   *   When the archived campaign lacks/has invalid canonical authority.
   */
  public function unarchive(int $campaign_id): array {
    $campaign = $this->loadCampaign($campaign_id);
    if ($campaign === NULL) {
      return ['status' => 'not_found', 'name' => '', 'restored_status' => ''];
    }

    $name = (string) $campaign->name;
    if ((string) $campaign->status !== 'archived') {
      return ['status' => 'not_archived', 'name' => $name, 'restored_status' => (string) $campaign->status];
    }

    $campaign_data = $this->decodeCampaignData($campaign->campaign_data);

    // Board invariant: legacy archived campaigns can never re-enter runtime.
    $this->assertCanonicalAuthority($campaign_id, $campaign_data);

    $restored_status = (string) ($campaign_data['_archive_meta']['previous_status'] ?? 'draft');
    if (!in_array($restored_status, self::RESTORABLE_STATUSES, TRUE)) {
      $restored_status = 'draft';
    }
    unset($campaign_data['_archive_meta']);

    $this->persist($campaign_id, $restored_status, $campaign_data);

    return ['status' => 'unarchived', 'name' => $name, 'restored_status' => $restored_status];
  }

  /**
   * Reject archived or legacy campaigns at game launch/state entrypoints.
   *
   * @throws \InvalidArgumentException
   *   When the campaign does not exist.
   * @throws \Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException
   *   When the campaign is archived or lacks the canonical authority contract.
   */
  public function assertLaunchable(int $campaign_id): void {
    $campaign = $this->loadCampaign($campaign_id);
    if ($campaign === NULL) {
      throw new \InvalidArgumentException(sprintf('Campaign not found: %d', $campaign_id));
    }

    if ((string) $campaign->status === 'archived') {
      throw new LegacyCampaignArchivedException(sprintf(
        'legacy_campaign_archived: campaign %d is archived and cannot be launched or read by the current runtime.',
        $campaign_id
      ));
    }

    $this->assertCanonicalAuthority($campaign_id, $this->decodeCampaignData($campaign->campaign_data));
  }

  /**
   * Archive every non-archived campaign lacking the canonical authority marker.
   *
   * Idempotent convergence path used by the update hook. On the live deployment
   * (where the 53 legacy campaigns were already archived) this is a no-op; on
   * other deployments it converges them using the same lifecycle invariant.
   *
   * @return array{scanned:int,archived:int,ids:list<int>}
   */
  public function archiveLegacyCampaignsWithoutAuthority(): array {
    $rows = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'campaign_data'])
      ->condition('status', 'archived', '<>')
      ->execute();

    $scanned = 0;
    $archived_ids = [];
    foreach ($rows as $row) {
      $scanned++;
      $campaign_data = $this->decodeCampaignData($row->campaign_data);
      if ($this->hasCanonicalAuthority($campaign_data)) {
        continue;
      }
      $result = $this->archive((int) $row->id, self::REASON_LEGACY_CONTRACT);
      if ($result['status'] === 'archived') {
        $archived_ids[] = (int) $row->id;
      }
    }

    return ['scanned' => $scanned, 'archived' => count($archived_ids), 'ids' => $archived_ids];
  }

  /**
   * Load the minimal campaign row for lifecycle decisions.
   */
  private function loadCampaign(int $campaign_id): ?object {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name', 'uid', 'status', 'campaign_data'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    return $campaign ?: NULL;
  }

  /**
   * @return array<string,mixed>
   */
  private function decodeCampaignData(mixed $raw): array {
    $decoded = json_decode((string) ($raw ?? '{}'), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * @param array<string,mixed> $campaign_data
   */
  private function persist(int $campaign_id, string $status, array $campaign_data): void {
    $this->database->update('dc_campaigns')
      ->fields([
        'status' => $status,
        'campaign_data' => json_encode($campaign_data, JSON_UNESCAPED_UNICODE),
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', $campaign_id)
      ->execute();

    $this->cacheTagsInvalidator->invalidateTags(['dc_campaigns', 'dc_campaign:' . $campaign_id]);
  }

}
