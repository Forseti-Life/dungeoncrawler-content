<?php

/**
 * @file
 * Actor command integration checks.
 *
 * Run with the site's Drush php:script command. Uses one original fixture
 * actor inside a transaction that is always rolled back; no player rows,
 * model calls or lasting writes.
 */

declare(strict_types=1);

use Drupal\Core\Session\UserSession;

$database = \Drupal::database();
$manager = \Drupal::service('dungeoncrawler_content.character_manager');
$uuid = \Drupal::service('uuid');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new \RuntimeException('FAIL: ' . $message);
  }
  $checks++;
};

$transaction = $database->startTransaction();
try {
  $actor_uuid = $uuid->generate();
  $owner_uid = 987650001;
  $id = (int) $database->insert('dc_campaign_characters')->fields([
    'uuid' => $actor_uuid, 'campaign_id' => 0, 'character_id' => 0, 'uid' => $owner_uid, 'type' => 'npc', 'role' => 'contact',
    'name' => 'Odalys Fenwick', 'level' => 2, 'lifecycle_state' => 'canonical_template', 'status' => 0, 'version' => 4,
    'character_data' => json_encode([
      'name' => 'Odalys Fenwick', 'display_name' => 'Odalys Fenwick', 'level' => 2, 'backstory' => 'Keeps the toll ledger.',
      'basicInfo' => ['name' => 'Odalys Fenwick', 'backstory' => 'Keeps the toll ledger.'],
      'stats' => ['ac' => 16, 'maxHp' => 22],
    ]),
    'state_data' => json_encode(['description' => 'A ferry warden in a waxed coat.']),
    'created' => 1, 'changed' => 1,
  ])->execute();
  $account = static fn(int $uid): UserSession => new class(['uid' => $uid]) extends UserSession {

    public function hasPermission(string $permission): bool {
      return $permission === 'edit own dungeoncrawler characters';
    }

  };
  $owner = $account($owner_uid);
  $stranger = $account(987650002);
  $command = static fn(array $operations, string $type = 'actor.narrative.patch', array $target = []): array => [
    'contract_version' => 'actor-command-v1',
    'command_id' => \Drupal::service('uuid')->generate(),
    'target' => $target + ['scope' => 'template', 'actor_uuid' => $actor_uuid],
    'type' => $type,
    'base_revision' => 2,
    'payload' => ['operations' => $operations],
  ];
  $load = static fn(): array => $database->select('dc_campaign_characters', 'c')->fields('c')->condition('id', $id)->execute()->fetchAssoc();
  $unchanged = static function () use ($load): bool {
    $row = $load();
    return (int) $row['version'] === 4 && str_contains($row['character_data'], 'Keeps the toll ledger.');
  };

  // Preview is read-only.
  $edit = $command([
    ['op' => 'set', 'path' => '/narrative/biography', 'value' => 'Keeps the toll ledger and a second, private one.'],
    ['op' => 'set', 'path' => '/narrative/description', 'value' => 'A ferry warden with ink-stained cuffs.'],
  ]);
  $preview = $manager->previewActorCommand($edit, $owner);
  $check(($preview['intent'] ?? NULL) === 'preview', 'Preview must plan: ' . json_encode($preview));
  $check(array_column($preview['changed'], 'path') === ['/narrative/biography', '/narrative/description'], 'Preview lists exact changed paths.');
  $check($unchanged(), 'Preview must not write.');
  $check((int) $database->select('dc_actor_command_receipts', 'r')->condition('actor_uuid', $actor_uuid)->countQuery()->execute()->fetchField() === 0, 'Preview stores no receipt.');

  // Execute: an older inspected revision still applies to the latest row.
  $receipt = $manager->executeActorCommand($edit, $owner);
  $check($receipt['committed'] === TRUE, 'Valid edit commits: ' . json_encode($receipt));
  $check($receipt['inspected_revision'] === 2 && $receipt['applied_from_revision'] === 4 && $receipt['new_revision'] === 5, 'Receipt reports inspected, applied-from and new revisions.');
  $check($receipt['next_steps'] !== [], 'Receipt always carries next steps.');
  $row = $load();
  $data = json_decode($row['character_data'], TRUE);
  $state = json_decode($row['state_data'], TRUE);
  $check((int) $row['version'] === 5, 'Aggregate revision advances once.');
  $check($data['backstory'] === 'Keeps the toll ledger and a second, private one.' && $data['basicInfo']['backstory'] === $data['backstory'], 'Every stored copy of the fact is updated.');
  $check($state['description'] === 'A ferry warden with ink-stained cuffs.' && !isset($data['description']), 'A fact stays in the store it lives in.');
  $check($data['stats'] === ['ac' => 16, 'maxHp' => 22] && $data['level'] === 2, 'Unspecified fields are preserved.');
  $history = $database->select('dc_actor_revisions', 'h')->fields('h')->condition('actor_uuid', $actor_uuid)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $check(count($history) === 1 && (int) $history[0]['revision'] === 5 && (int) $history[0]['parent_revision'] === 4, 'One history record per changed revision.');
  $check(json_decode($history[0]['document'], TRUE)['narrative']['biography'] === $data['backstory'], 'History stores the validated canonical document.');

  // Idempotency.
  $replay = $manager->executeActorCommand($edit, $owner);
  $check($replay == $receipt && (int) $load()['version'] === 5, 'Replaying a command returns the same receipt without a second write.');
  $reused = $edit;
  $reused['payload']['operations'][0]['value'] = 'Different text under the same command id.';
  $check($manager->executeActorCommand($reused, $owner)['code'] === 'actor_command_id_reused', 'A reused command id with a different request is rejected.');

  // No-change command: receipt, no revision.
  $same = $manager->executeActorCommand($command([['op' => 'set', 'path' => '/narrative/description', 'value' => 'A ferry warden with ink-stained cuffs.']]), $owner);
  $check($same['committed'] === TRUE && $same['changed'] === [] && $same['new_revision'] === 5 && str_starts_with($same['next_steps'][0], 'Nothing changed'), 'A no-change command does not advance the revision.');

  // Rename keeps every copy and the hot column equal.
  $rename = $manager->executeActorCommand($command([['op' => 'set', 'path' => '/identity/name', 'value' => 'Odalys Fenwick-Marr']], 'actor.identity.patch'), $owner);
  $row = $load();
  $data = json_decode($row['character_data'], TRUE);
  $check($rename['new_revision'] === 6 && $row['name'] === 'Odalys Fenwick-Marr' && $data['name'] === $row['name'] && $data['display_name'] === $row['name'] && $data['basicInfo']['name'] === $row['name'], 'Rename updates the column and every stored copy.');

  // Failures change nothing.
  $baseline = $load();
  $failures = [
    'actor_contract_invalid path outside command' => [$command([['op' => 'set', 'path' => '/build/level', 'value' => 9]]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid unknown path' => [$command([['op' => 'set', 'path' => '/narrative/secret_plan', 'value' => 'x']]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid too long' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => str_repeat('a', 1001)]]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid wrong type' => [$command([['op' => 'set', 'path' => '/identity/name', 'value' => ['x']]], 'actor.identity.patch'), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid name removal' => [$command([['op' => 'unset', 'path' => '/identity/name']], 'actor.identity.patch'), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid duplicate path' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a'], ['op' => 'unset', 'path' => '/narrative/fears']]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid client force flag' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']]) + ['force' => TRUE], $owner, 'actor_contract_invalid'],
    'actor_scope_mismatch' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']], 'actor.narrative.patch', ['scope' => 'draft']), $owner, 'actor_scope_mismatch'],
    'actor_not_found for another user' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']]), $stranger, 'actor_not_found'],
    'actor_not_found unknown uuid' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']], 'actor.narrative.patch', ['actor_uuid' => \Drupal::service('uuid')->generate()]), $owner, 'actor_not_found'],
  ];
  foreach ($failures as $label => [$bad, $account, $code]) {
    $result = $manager->executeActorCommand($bad, $account);
    $check($result['committed'] === FALSE && $result['code'] === $code, $label . ' => ' . json_encode($result));
    $check($result['next_steps'] !== [], $label . ' explains the next step.');
    $check($load() == $baseline, $label . ' must change nothing.');
  }

  // Unset removes the stored fact everywhere.
  $removed = $manager->executeActorCommand($command([['op' => 'unset', 'path' => '/narrative/biography']]), $owner);
  $data = json_decode($load()['character_data'], TRUE);
  $check($removed['committed'] === TRUE && !isset($data['backstory']) && !isset($data['basicInfo']['backstory']), 'Unset removes every stored copy.');

  echo "PASS: {$checks} actor command integration checks; fixture rolled back.\n";
}
finally {
  $transaction->rollBack();
}
