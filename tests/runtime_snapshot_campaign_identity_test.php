<?php

/**
 * @file
 * Behavioral test: nested runtime_snapshot campaign identity.
 *
 * Run with:
 *   php tests/runtime_snapshot_campaign_identity_test.php
 *
 * Proves that RuntimeStateReadModelAssembler resolves the snapshot campaign id
 * from dungeon_data when game_state omits it, so a nested runtime_snapshot and
 * the top-level snapshot resolve to the SAME opaque snapshot id (and therefore
 * social state is loaded for the correct campaign, not campaign 0).
 *
 * Only the pure static identity helpers are exercised, so no Drupal container
 * bootstrap is required.
 */

require_once __DIR__ . '/../src/Service/RuntimeStateReadModelAssembler.php';

use Drupal\dungeoncrawler_content\Service\RuntimeStateReadModelAssembler as Assembler;

$passed = 0;
$failed = 0;
function check(bool $condition, string $msg): void {
  global $passed, $failed;
  if ($condition) {
    $passed++;
    echo "  \u{2713} {$msg}\n";
  }
  else {
    $failed++;
    echo "  \u{2717} {$msg}\n";
  }
}

// Shared committed runtime identity fields.
$state_version = 12;
$event_cursor = 1071;
$phase = 'encounter';
$encounter_id = 99005;
$active_room_id = 'undead_crypt_entry_hall';

// Top-level game_state carries the campaign id.
$top_game_state = [
  'campaign_id' => 849,
  'state_version' => $state_version,
  'event_log_cursor' => $event_cursor,
  'phase' => $phase,
  'encounter_id' => $encounter_id,
  'active_room_id' => $active_room_id,
];

// Nested runtime_snapshot game_state OMITS the campaign id; the dungeon context
// carries it. This is the exact fracture case from the finding.
$nested_game_state = [
  'state_version' => $state_version,
  'event_log_cursor' => $event_cursor,
  'phase' => $phase,
  'encounter_id' => $encounter_id,
  'active_room_id' => $active_room_id,
];
$dungeon_data = [
  'campaign_id' => 849,
  'active_room_id' => $active_room_id,
];

echo "=== Campaign id resolution ===\n";
check(
  Assembler::resolveSnapshotCampaignId($nested_game_state, $dungeon_data) === 849,
  'nested game_state without campaign_id derives 849 from dungeon_data'
);
check(
  Assembler::resolveSnapshotCampaignId($top_game_state, []) === 849,
  'top-level game_state campaign_id is honored'
);
check(
  Assembler::resolveSnapshotCampaignId($nested_game_state, $dungeon_data, 849) === 849,
  'explicit campaign argument wins'
);
check(
  Assembler::resolveSnapshotCampaignId($nested_game_state, []) === 0,
  'missing campaign everywhere resolves to 0 (not a wrong campaign)'
);

echo "=== Nested vs top-level snapshot id equality ===\n";
$top_id = Assembler::computeSnapshotId(
  Assembler::resolveSnapshotCampaignId($top_game_state, $dungeon_data),
  $state_version, $event_cursor, $phase, $encounter_id, $active_room_id
);
$nested_id = Assembler::computeSnapshotId(
  Assembler::resolveSnapshotCampaignId($nested_game_state, $dungeon_data),
  $state_version, $event_cursor, $phase, $encounter_id, $active_room_id
);
check($top_id === $nested_id, 'nested and top-level snapshot ids are identical for the same committed state');
check(str_starts_with($top_id, 'rtsnap_'), 'snapshot id is an opaque server-side identity');

// A different campaign must not collide.
$other_id = Assembler::computeSnapshotId(850, $state_version, $event_cursor, $phase, $encounter_id, $active_room_id);
check($other_id !== $top_id, 'different campaigns produce distinct snapshot ids');

// A wrong (campaign 0) derivation — the pre-fix bug — would have diverged.
$buggy_zero_id = Assembler::computeSnapshotId(0, $state_version, $event_cursor, $phase, $encounter_id, $active_room_id);
check($buggy_zero_id !== $top_id, 'a campaign-0 nested derivation (the old bug) would have diverged from top-level');

echo "\n";
echo "===================================\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
if ($failed > 0) {
  echo "SOME TESTS FAILED\n";
  exit(1);
}
echo "ALL TESTS PASSED\n";
