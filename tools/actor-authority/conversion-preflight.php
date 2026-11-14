<?php

/**
 * @file
 * Read-only conversion preflight for every actor row.
 *
 * Run: drush php:script tools/actor-authority/conversion-preflight.php
 * Converts each dc_campaign_characters row in memory, validates the result,
 * and prints aggregate counts. Writes nothing and prints no row content.
 */

declare(strict_types=1);

$database = \Drupal::database();
$converter = \Drupal::service('dungeoncrawler_content.actor_document_converter');
$validation = \Drupal::service('dungeoncrawler_content.state_validation_service');

$totals = ['rows' => 0, 'converted_without_issue' => 0, 'valid_for_save' => 0, 'ready_for_publication' => 0];
$by_kind = [];
$issue_codes = [];
$finding_codes = [];
$rows = $database->select('dc_campaign_characters', 'c')
  ->fields('c', ['id', 'uuid', 'campaign_id', 'type', 'role', 'name', 'level', 'hp_current', 'hp_max', 'armor_class', 'experience_points', 'character_data', 'state_data'])
  ->orderBy('id')
  ->execute();
foreach ($rows as $row) {
  $decode = static function (?string $json): array {
    $decoded = json_decode((string) $json, TRUE);
    return is_array($decoded) ? $decoded : [];
  };
  $result = $converter->convertRow((array) $row, $decode($row->character_data), $decode($row->state_data));
  $report = $validation->validateActor($result['document'], 'actor_authoring');
  $kind = $result['document']['identity']['kind'] . '/' . ((int) $row->campaign_id > 0 ? 'instance' : 'library');
  $by_kind[$kind] ??= ['rows' => 0, 'clean' => 0, 'valid_for_save' => 0];
  $totals['rows']++;
  $by_kind[$kind]['rows']++;
  $clean = $result['issues'] === [];
  $totals['converted_without_issue'] += (int) $clean;
  $by_kind[$kind]['clean'] += (int) $clean;
  $ok = $clean && $report['valid_for_save'];
  $totals['valid_for_save'] += (int) $ok;
  $by_kind[$kind]['valid_for_save'] += (int) $ok;
  $totals['ready_for_publication'] += (int) ($clean && $report['ready_for_publication']);
  foreach (array_unique(array_column($result['issues'], 'code')) as $code) {
    $issue_codes[$code] = ($issue_codes[$code] ?? 0) + 1;
  }
  foreach ($report['findings'] as $finding) {
    if ($finding['blocking']) {
      $key = $finding['code'] . ' ' . preg_replace('/\/\d+/', '/*', $finding['path']);
      $finding_codes[$key] = ($finding_codes[$key] ?? 0) + 1;
    }
  }
}
arsort($issue_codes);
arsort($finding_codes);
ksort($by_kind);
echo json_encode([
  'contract' => 'actor-conversion-preflight-v1',
  'totals' => $totals,
  'by_kind' => $by_kind,
  'rows_with_conversion_issue_code' => $issue_codes,
  'blocking_validation_findings' => array_slice($finding_codes, 0, 25, TRUE),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
