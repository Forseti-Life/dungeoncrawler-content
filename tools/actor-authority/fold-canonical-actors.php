<?php

/**
 * @file
 * Folds canonical actor definitions into library actor rows.
 *
 * Run: drush php:script tools/actor-authority/fold-canonical-actors.php
 * Dry run by default: converts and validates each definition in memory and
 * prints aggregate counts. Set ACTOR_FOLD_APPLY=1 to create or update the
 * library rows through the actor authority. Never guesses missing values.
 */

declare(strict_types=1);

use Drupal\user\Entity\User;

$apply = getenv('ACTOR_FOLD_APPLY') === '1';
$database = \Drupal::database();
$mirror = \Drupal::service('dungeoncrawler_content.actor_definition_mirror');
$converter = \Drupal::service('dungeoncrawler_content.actor_document_converter');
$validation = \Drupal::service('dungeoncrawler_content.state_validation_service');
$principal = User::load(1);
\Drupal::currentUser()->setAccount($principal);

$totals = ['definitions' => 0, 'not_an_actor' => 0, 'convertible' => 0, 'blocked' => 0];
$statuses = [];
$blocked = [];
foreach ($database->query('SELECT actor_id, version, actor_type, display_name, source_module, state_data FROM {dc_canonical_actors} ORDER BY actor_id') as $record) {
  $totals['definitions']++;
  $definition = [
    'actor_type' => $record->actor_type,
    'display_name' => $record->display_name,
    'version' => $record->version,
    'state_data' => json_decode((string) $record->state_data, TRUE, 512, JSON_THROW_ON_ERROR),
  ] + ($record->source_module !== NULL ? ['source_module' => $record->source_module] : []);
  $values = $mirror->rowValues($record->actor_id, $definition);
  if ($values === NULL) {
    $totals['not_an_actor']++;
    continue;
  }
  $converted = $converter->convertRow($values + ['campaign_id' => 0], json_decode($values['character_data'], TRUE), []);
  $report = $converted['issues'] === [] ? $validation->validateActor($converted['document'], 'actor_authoring') : NULL;
  if ($report === NULL || !$report['valid_for_save']) {
    $totals['blocked']++;
    foreach ($converted['issues'] as $issue) {
      $blocked[$issue['code']] = ($blocked[$issue['code']] ?? 0) + 1;
    }
    foreach ($report['findings'] ?? [] as $finding) {
      if ($finding['blocking']) {
        $blocked[$finding['code']] = ($blocked[$finding['code']] ?? 0) + 1;
      }
    }
    continue;
  }
  $totals['convertible']++;
  if ($apply) {
    $status = $mirror->mirror($record->actor_id, $definition, $principal)['status'];
    $statuses[$status] = ($statuses[$status] ?? 0) + 1;
  }
}
echo json_encode(['contract' => 'actor-definition-fold-v1', 'applied' => $apply, 'totals' => $totals, 'blocked_by' => $blocked, 'applied_statuses' => $statuses], JSON_PRETTY_PRINT), "\n";
