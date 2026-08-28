/**
 * @file
 * Contract test for shared authoritative /state actor-ref guardrails.
 *
 * Run with:
 *   node tests/authoritative_state_actor_guard_contract_test.js
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

const helperSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/utils/authoritative-state-utils.js'),
  'utf8'
);
const characterPanelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'),
  'utf8'
);
const encounterSystemSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/systems/EncounterSystem.js'),
  'utf8'
);
const gameShellSource = require('./helpers/js-source.js').readGameShellSource();

console.log('\n=== Authoritative /state actor guard contracts ===');

assert(
  helperSource.includes("startsWith('npc_')")
    && helperSource.includes("startsWith('npc-')")
    && helperSource.includes("startsWith('npc:')")
    && helperSource.includes("startsWith('pc_')")
    && helperSource.includes("startsWith('pc-')")
    && helperSource.includes("startsWith('pc:')"),
  'shared helper blocks npc refs and allows player refs by canonical prefixes'
);

assert(
  characterPanelSource.includes("from '../utils/authoritative-state-utils.js';")
    && characterPanelSource.includes('shouldRequestAuthoritativeStateForActorRef as shouldRequestAuthoritativeStateForActorRefShared')
    && characterPanelSource.includes('normalizeAuthoritativeStateActorRef'),
  'CharacterPanel imports the shared authoritative actor-ref guard for its actor-scoped refresh paths'
);

assert(
  encounterSystemSource.includes("from '../utils/authoritative-state-utils.js';")
    && encounterSystemSource.includes('normalizeAuthoritativeStateActorRef(actorRef, { runtimeContext })')
    && encounterSystemSource.includes('actor: refreshActorRef || undefined'),
  'EncounterSystem actor-scoped state refreshes use the shared guard'
);

assert(
  gameShellSource.includes("from './utils/authoritative-state-utils.js';")
    && gameShellSource.includes('normalizeAuthoritativeStateActorRef(actorRef, { runtimeContext: fallbackRuntimeContext })')
    && gameShellSource.includes('actor: requestActorRef || undefined'),
  'GameShell actor-scoped state resync paths use the shared guard'
);

console.log('\n===============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
} else {
  console.log('ALL TESTS PASSED');
}
