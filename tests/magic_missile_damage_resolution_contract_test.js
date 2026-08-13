/**
 * @file
 * Contract test: magic missile cast resolves encounter damage + action-log output.
 *
 * Run with:
 *   node tests/magic_missile_damage_resolution_contract_test.js
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

console.log('\n=== Magic missile damage resolution contract ===');

const executorSource = read('../src/Service/EncounterActionExecutor.php');
const damageEngineSource = read('../src/Service/UnifiedDamageEngine.php');
assert(
  executorSource.includes('applySupportedSpellDamageToEncounterTarget(')
    && executorSource.includes("$this->unifiedDamageEngine->applySupportedSpellDamageToEncounterTarget("),
  'EncounterActionExecutor delegates deterministic spell damage resolution to UnifiedDamageEngine'
);
assert(
  damageEngineSource.includes("$canonical_spell !== 'magicmissile'")
    && damageEngineSource.includes("$this->encounterStore->updateParticipant($target_participant_id, ["),
  'UnifiedDamageEngine canonicalizes spell identifiers and persists participant HP changes for Magic Missile'
);
assert(
  executorSource.includes('isMagicMissileSpell(')
    && executorSource.includes('Magic Missile requires selecting a valid target.'),
  'cast_spell rejects Magic Missile execution when no valid target is resolved'
);
assert(
  damageEngineSource.includes('buildDamageApplicationPacket(')
    && damageEngineSource.includes("$source_actor_id,\n        $target_id,\n        'spell',")
    && damageEngineSource.includes("'damage_type' => 'force'")
    && damageEngineSource.includes("'damage_packet' => $this->combatResolutionContractService->buildDamageApplicationPacket(")
    && damageEngineSource.includes("'missiles_fired' => $missiles_fired")
    && damageEngineSource.includes("'field' => 'hp'"),
  'magic missile damage payload includes canonical damage packet, force typing, missiles fired, and HP mutation descriptors'
);

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');
assert(
  phaseHandlerSource.includes("'damage' => $resolved_damage")
    && phaseHandlerSource.includes("'damage_type' => is_string($result['damage_type'] ?? NULL)")
    && phaseHandlerSource.includes("'damage_packet' => $damage_packet")
    && phaseHandlerSource.includes("'missiles_fired' => is_numeric($result['missiles_fired'] ?? NULL)"),
  'cast_spell encounter events include canonical damage packet metadata for downstream logs'
);

const chatPanelSource = read('../js/v2/panels/ChatPanel.js');
assert(
  chatPanelSource.includes("case 'cast_spell':")
    && chatPanelSource.includes('damage applied to')
    && chatPanelSource.includes('casts ${spellLabel}'),
  'map action-log cast_spell formatting renders applied damage text when present'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
