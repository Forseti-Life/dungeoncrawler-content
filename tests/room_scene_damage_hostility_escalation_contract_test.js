/**
 * @file
 * Contract test: room-scene hostility must escalate to hostile combat.
 *
 * Run with:
 *   node tests/room_scene_damage_hostility_escalation_contract_test.js
 */

const fs = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${message}`);
  } else {
    failed++;
    console.error(`  ✗ ${message}`);
  }
}

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8',
);

console.log('\n=== Room-scene hostility escalation contract ===');

assert(
  source.includes('buildRoomSceneHostilityEscalationTransition(')
    && source.includes("$mode !== 'room_scene'")
    && source.includes('isRoomSceneHostilityEscalationTrigger('),
  'GameCoordinatorService evaluates room-scene hostility triggers through a shared escalation seam'
);

assert(
  source.includes("'from' => self::DEFAULT_ACTIVE_PHASE")
    && source.includes("'to' => self::DEFAULT_ACTIVE_PHASE")
    && source.includes("'source_event_type' => (string) ($disposition_change['event_type'] ?? 'hostility_escalation')"),
  'hostility escalation creates an encounter→encounter phase transition through canonical lifecycle hooks'
);

assert(
  source.includes("if ((string) ($disposition_change['event_type'] ?? '') === 'damage_application_hostility_override')")
    && source.includes("DispositionAuthorityContract::LABEL_HOSTILE")
    && source.includes('DispositionAuthorityContract::isHostileScore'),
  'hostility trigger detection supports both explicit damage overrides and hostile after-state transitions'
);

assert(
  source.includes('collectCombatEscalationEnemiesForRoom(')
    && source.includes("$content_type === 'player_character'")
    && source.includes("['player', 'pc', 'ally', 'friendly', 'companion']"),
  'escalation enemy candidate collection excludes player/allied entities in the active room'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
