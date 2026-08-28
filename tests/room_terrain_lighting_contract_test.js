/**
 * @file
 * Contract test: GameShell must treat room terrain + lighting as single-shape contracts.
 *
 * We do not support backward-compatible multi-format room metadata (e.g. lighting.level or terrain arrays).
 * This keeps the HexMap V2 room->map display contract strict and prevents drift.
 *
 * Run with:
 *   node tests/room_terrain_lighting_contract_test.js
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

const source = require('./helpers/js-source.js').readGameShellSource();

console.log('\n=== Room terrain/lighting strict contract ===');

assert(!source.includes('lighting?.level'), 'Does not fallback to lighting.level');
assert(!source.includes('Array.isArray(room?.terrain)'), 'Does not treat room.terrain as an array');
assert(!source.includes('Array.isArray(activeRoom?.terrain)'), 'Does not treat activeRoom.terrain as an array');

assert(source.includes("typeof room?.terrain?.type === 'string'"), 'Requires room.terrain.type string');
assert(source.includes("typeof room?.lighting === 'string'"), 'Requires room.lighting string');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
