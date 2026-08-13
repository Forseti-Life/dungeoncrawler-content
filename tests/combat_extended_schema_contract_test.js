/**
 * @file
 * Contract test: extended combat schema package coverage.
 *
 * Run with:
 *   node tests/combat_extended_schema_contract_test.js
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

function readJson(relPath) {
  const full = path.resolve(__dirname, relPath);
  return JSON.parse(fs.readFileSync(full, 'utf8'));
}

console.log('\n=== Combat extended schema contract ===');

const resolutionEnvelope = readJson('../config/schemas/combat_resolution_envelope.schema.json');
assert(
  resolutionEnvelope?.properties?.contract_version?.enum?.includes('combat.resolution_envelope.v1')
    && resolutionEnvelope?.properties?.kind?.enum?.includes('combat_resolution_envelope')
    && resolutionEnvelope?.required?.includes('request')
    && resolutionEnvelope?.required?.includes('packets')
    && resolutionEnvelope?.required?.includes('result'),
  'combat_resolution_envelope schema freezes canonical resolution envelope shape'
);

const movementPacket = readJson('../config/schemas/movement_resolution_packet.schema.json');
assert(
  movementPacket?.properties?.contract_version?.enum?.includes('combat.movement_packet.v1')
    && movementPacket?.properties?.kind?.enum?.includes('movement_resolution')
    && movementPacket?.required?.includes('from_hex')
    && movementPacket?.required?.includes('to_hex')
    && movementPacket?.required?.includes('distance_ft'),
  'movement_resolution_packet schema freezes canonical movement packet shape'
);

const stateEffectPacket = readJson('../config/schemas/state_effect_change_packet.schema.json');
assert(
  stateEffectPacket?.properties?.contract_version?.enum?.includes('combat.state_effect_packet.v1')
    && stateEffectPacket?.properties?.kind?.enum?.includes('state_effect_change')
    && stateEffectPacket?.required?.includes('effect_kind')
    && stateEffectPacket?.required?.includes('effect_name')
    && stateEffectPacket?.required?.includes('change_type'),
  'state_effect_change_packet schema freezes canonical state/effect packet shape'
);

const reactionPacket = readJson('../config/schemas/reaction_resolution_packet.schema.json');
assert(
  reactionPacket?.properties?.contract_version?.enum?.includes('combat.reaction_packet.v1')
    && reactionPacket?.properties?.kind?.enum?.includes('reaction_resolution')
    && reactionPacket?.required?.includes('reactor_entity_ref')
    && reactionPacket?.required?.includes('triggering_actor_entity_ref')
    && reactionPacket?.required?.includes('reaction_type'),
  'reaction_resolution_packet schema freezes canonical reaction packet shape'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
