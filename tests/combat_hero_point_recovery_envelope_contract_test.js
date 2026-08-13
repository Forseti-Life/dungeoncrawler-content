/**
 * @file
 * Contract test: hero-point reroll and heroic recovery emit canonical envelopes.
 *
 * Run with:
 *   node tests/combat_hero_point_recovery_envelope_contract_test.js
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

function read(relPath) {
  return fs.readFileSync(path.resolve(__dirname, relPath), 'utf8');
}

console.log('\n=== Combat hero-point/recovery envelope contract ===');

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'hero_point_reroll'")
    && phaseHandlerSource.includes("buildEvent('hero_point_reroll', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("'hero_points_spent' => 1")
    && phaseHandlerSource.includes("'original_roll' => $original_roll"),
  'Hero-point reroll emits canonical execution/envelope metadata with reroll outcomes'
);

assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'heroic_recovery_all_points'")
    && phaseHandlerSource.includes("buildEvent('heroic_recovery_all_points', 'encounter', $actor_id, [")
    && phaseHandlerSource.includes("'dying_removed' => $result['dying_removed'] ?? FALSE")
    && phaseHandlerSource.includes("'state_effect_packets' => $state_effect_packets"),
  'Heroic recovery emits canonical execution/envelope metadata with dying removal state packets'
);

assert(
  phaseHandlerSource.includes("'action' => 'heroic_recovery_all_points'")
    && phaseHandlerSource.includes("'dying'")
    && phaseHandlerSource.includes("$this->unifiedStateEffectEngine->buildStateEffectChangePacket("),
  'Heroic recovery routes dying-condition removal through UnifiedStateEffectEngine packet seam'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
