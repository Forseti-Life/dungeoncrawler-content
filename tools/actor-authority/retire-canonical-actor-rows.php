<?php

/**
 * @file
 * Moves pc/npc actor definitions out of dc_canonical_actors for good.
 *
 * Run: drush php:script tools/actor-authority/retire-canonical-actor-rows.php
 * Dry run by default: reports how many definitions would move. Set
 * ACTOR_RETIRE_APPLY=1 to write each definition to its library actor row
 * through the actor authority, prove the row reads back as exactly the stored
 * definition, and only then delete the dc_canonical_actors row. One
 * transaction: any mismatch rolls everything back.
 */

declare(strict_types=1);

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\user\Entity\User;

$apply = getenv('ACTOR_RETIRE_APPLY') === '1';
$database = \Drupal::database();
$mirror = \Drupal::service('dungeoncrawler_content.actor_definition_mirror');
$definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
$principal = User::load(1);
\Drupal::currentUser()->setAccount($principal);

$totals = ['definitions' => 0, 'moved' => 0, 'left_non_actor' => 0];
$statuses = [];
$transaction = $database->startTransaction();
try {
  foreach ($database->query('SELECT * FROM {dc_canonical_actors} ORDER BY actor_id')->fetchAll(\PDO::FETCH_ASSOC) as $record) {
    if (!in_array($record['actor_type'], CanonicalDefinitionService::LIBRARY_ACTOR_TYPES, TRUE)) {
      $totals['left_non_actor']++;
      continue;
    }
    $totals['definitions']++;
    if (!$apply) {
      continue;
    }
    $state = json_decode((string) $record['state_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (($state['name'] ?? $record['display_name']) !== $record['display_name']) {
      throw new \RuntimeException("actor_definition_name_mismatch:{$record['actor_id']}");
    }
    $expected = [
      'actor_id' => $record['actor_id'],
      'version' => $record['version'],
      'actor_type' => $record['actor_type'],
      'display_name' => $record['display_name'],
      'state_data' => ['name' => $record['display_name']] + $state,
      'source_module' => $record['source_module'],
    ];
    $metadata = json_decode((string) ($record['metadata'] ?? ''), TRUE);
    if (is_array($metadata) && $metadata !== []) {
      $expected['metadata'] = $metadata;
    }
    $status = $mirror->mirror($record['actor_id'], $expected, $principal)['status'];
    $statuses[$status] = ($statuses[$status] ?? 0) + 1;
    $database->delete('dc_canonical_actors')->condition('id', $record['id'])->execute();
    $stored = $definitions->definitionPayload('actor', $record['actor_id']);
    ksort($stored['state_data']);
    ksort($expected['state_data']);
    if ($stored != $expected || $definitions->validateDefinition('actor', $stored) !== []) {
      throw new \RuntimeException("actor_definition_read_back_mismatch:{$record['actor_id']}");
    }
    $totals['moved']++;
  }
}
catch (\Throwable $exception) {
  $transaction->rollBack();
  throw $exception;
}
unset($transaction);
echo json_encode(['contract' => 'actor-definition-retire-v1', 'applied' => $apply, 'totals' => $totals, 'mirror_statuses' => $statuses, 'remaining_rows' => (int) $database->query('SELECT COUNT(*) FROM {dc_canonical_actors}')->fetchField()], JSON_PRETTY_PRINT), "\n";
