/**
 * @file
 * Contract test: unified combat runtime Slice A schema + authority matrix freeze.
 *
 * Run with:
 *   node tests/unified_combat_runtime_slice_a_contract_freeze_test.js
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

function readJson(relPath) {
  return JSON.parse(read(relPath));
}

console.log('\n=== Unified combat runtime Slice A contract freeze ===');

const executionSchema = readJson('../config/schemas/combat_execution_request.schema.json');
assert(
  executionSchema?.properties?.contract_version?.enum?.includes('combat.execution_request.v1')
    && executionSchema?.properties?.kind?.enum?.includes('combat_execution_request')
    && Array.isArray(executionSchema?.required)
    && executionSchema.required.includes('action_type')
    && executionSchema.required.includes('actor_entity_ref'),
  'combat_execution_request schema freezes canonical execution-request contract'
);

const damageSchema = readJson('../config/schemas/damage_application_request.schema.json');
assert(
  damageSchema?.properties?.contract_version?.enum?.includes('combat.damage_packet.v1')
    && damageSchema?.properties?.kind?.enum?.includes('damage_application')
    && Array.isArray(damageSchema?.required)
    && damageSchema.required.includes('source_entity_ref')
    && damageSchema.required.includes('target_entity_ref')
    && damageSchema.required.includes('delivery_mode'),
  'damage_application_request schema freezes canonical damage mutation contract'
);

const eventSchema = readJson('../config/schemas/combat_event.schema.json');
assert(
  eventSchema?.properties?.contract_version?.enum?.includes('combat.event.v1')
    && Array.isArray(eventSchema?.required)
    && eventSchema.required.includes('type')
    && eventSchema.required.includes('phase')
    && eventSchema.required.includes('data'),
  'combat_event schema freezes canonical event envelope contract'
);

const matrix = read('../UNIFIED_COMBAT_RUNTIME_SLICE_A_AUTHORITY_MATRIX.md');
assert(
  matrix.includes('| Strike execution + damage |')
    && matrix.includes('| Spell execution + damage |')
    && matrix.includes('| Hazard damage during forced movement/terrain |')
    && matrix.includes('| Movement resolution (stride/forced) |')
    && matrix.includes('| State/effect lifecycle packetization |')
    && matrix.includes('| Reactions/interrupts |')
    && matrix.includes('| Event envelope + projection |'),
  'authority matrix covers all required runtime lanes'
);

assert(
  matrix.includes('authoritative')
    && matrix.includes('hybrid')
    && matrix.includes('bypass')
    && matrix.includes('EncounterActionExecutor.php')
    && matrix.includes('EncounterPhaseHandler.php')
    && matrix.includes('GameEventLogger.php'),
  'authority matrix is code-anchored and classifies authoritative/hybrid/bypass states'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
