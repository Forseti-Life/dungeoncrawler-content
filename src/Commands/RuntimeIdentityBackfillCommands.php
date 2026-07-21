<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for campaign-runtime PC identity contract backfills.
 */
class RuntimeIdentityBackfillCommands extends DrushCommands {

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
  ) {
    parent::__construct();
  }

  /**
   * Backfill campaign/runtime identity fields in PC state_data payloads.
   *
   * @command dungeoncrawler_content:backfill-runtime-pc-identity
   * @aliases dc:backfill-runtime-pc-identity
   * @option campaign_id Restrict backfill to one campaign id.
   * @option limit Max rows to scan (0 = no limit).
   * @option dry-run Show repairs without writing updates.
   */
  public function backfillRuntimePcIdentity(array $options = [
    'campaign_id' => NULL,
    'limit' => 0,
    'dry-run' => FALSE,
  ]): int {
    $campaign_id = !empty($options['campaign_id']) ? (int) $options['campaign_id'] : NULL;
    $limit = max(0, (int) ($options['limit'] ?? 0));
    $dry_run = (bool) ($options['dry-run'] ?? FALSE);

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'campaign_id', 'instance_id', 'state_data'])
      ->condition('cc.type', 'pc')
      ->condition('cc.campaign_id', 0, '>');
    if ($campaign_id !== NULL && $campaign_id > 0) {
      $query->condition('cc.campaign_id', $campaign_id);
    }
    $query->orderBy('cc.id', 'ASC');
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $rows = $query->execute()->fetchAllAssoc('id');
    $scanned = 0;
    $repaired = 0;
    $unchanged = 0;
    $repaired_rows = [];

    foreach ($rows as $row) {
      $row_id = (int) ($row->id ?? 0);
      $row_campaign_id = (int) ($row->campaign_id ?? 0);
      $row_instance_id = trim((string) ($row->instance_id ?? ''));
      if ($row_id <= 0 || $row_campaign_id <= 0 || $row_instance_id === '') {
        throw new \RuntimeException(sprintf(
          'Backfill contract violation: row %d has incomplete identity columns (campaign_id=%d instance_id="%s").',
          $row_id,
          $row_campaign_id,
          $row_instance_id
        ));
      }

      $state_data = json_decode((string) ($row->state_data ?? ''), TRUE);
      if (!is_array($state_data)) {
        throw new \RuntimeException(sprintf(
          'Backfill contract violation: row %d has invalid JSON state_data.',
          $row_id
        ));
      }

      $scanned++;
      $actual_campaign_id = trim((string) ($state_data['campaignId'] ?? ''));
      $actual_instance_id = trim((string) ($state_data['instanceId'] ?? ''));
      $expected_campaign_id = (string) $row_campaign_id;
      $expected_instance_id = $row_instance_id;

      if ($actual_campaign_id === $expected_campaign_id && $actual_instance_id === $expected_instance_id) {
        $unchanged++;
        continue;
      }

      $state_data['campaignId'] = $expected_campaign_id;
      $state_data['instanceId'] = $expected_instance_id;
      $encoded_state = json_encode($state_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($encoded_state)) {
        throw new \RuntimeException(sprintf(
          'Backfill contract violation: row %d state_data encoding failed.',
          $row_id
        ));
      }

      $repaired_rows[] = [
        'id' => $row_id,
        'from_campaign' => $actual_campaign_id,
        'to_campaign' => $expected_campaign_id,
        'from_instance' => $actual_instance_id,
        'to_instance' => $expected_instance_id,
      ];

      if (!$dry_run) {
        $this->database->update('dc_campaign_characters')
          ->fields([
            'state_data' => $encoded_state,
            'updated' => $this->time->getRequestTime(),
          ])
          ->condition('id', $row_id)
          ->condition('campaign_id', $row_campaign_id)
          ->execute();
      }

      $repaired++;
    }

    $this->io()->title($dry_run ? 'Runtime PC identity backfill (dry run)' : 'Runtime PC identity backfill');
    $this->io()->table(
      ['Metric', 'Count'],
      [
        ['Scanned', $scanned],
        ['Repaired', $repaired],
        ['Already valid', $unchanged],
      ]
    );

    if ($repaired_rows !== []) {
      $table_rows = [];
      foreach ($repaired_rows as $entry) {
        $table_rows[] = [
          (string) $entry['id'],
          (string) $entry['from_campaign'],
          (string) $entry['to_campaign'],
          (string) $entry['from_instance'],
          (string) $entry['to_instance'],
        ];
      }
      $this->io()->table(
        ['row_id', 'campaignId (from)', 'campaignId (to)', 'instanceId (from)', 'instanceId (to)'],
        $table_rows
      );
    }

    if ($dry_run) {
      $this->io()->success('Dry run complete. No rows were modified.');
      return self::EXIT_SUCCESS;
    }

    $this->io()->success('Runtime PC identity backfill complete.');
    return self::EXIT_SUCCESS;
  }

}
