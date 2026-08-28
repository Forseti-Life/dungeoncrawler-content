/**
 * @file
 * Contract test: Map tab must project authoritative movement updates from
 * coordinator snapshots and event polling into live entity placements.
 *
 * Run with:
 *   node tests/game_shell_authoritative_movement_projection_contract_test.js
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

const gameShellSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/GameShell.js'),
  'utf8'
);

console.log('\n=== GameShell authoritative movement projection contract ===');

assert(
  gameShellSource.includes("window.addEventListener('dungeoncrawler:game-events', this._authoritativeGameEventsHandler);")
    && gameShellSource.includes("window.removeEventListener('dungeoncrawler:game-events', this._authoritativeGameEventsHandler);"),
  'GameShell subscribes to and cleans up authoritative coordinator game-events'
);

assert(
  gameShellSource.includes("this.bus.on('runtime:state-committed', ({ snapshot } = {}) => {")
    && gameShellSource.includes('this._syncEncounterPlacementsFromRuntimeSnapshot(snapshot);'),
  'GameShell projects committed runtime snapshots into live encounter placements'
);

assert(
  gameShellSource.includes('_projectAuthoritativeMovementEvents(events = [])')
    && gameShellSource.includes("const movementTypes = new Set(['stride', 'step', 'crawl', 'climb', 'swim', 'fly', 'leap', 'sneak', 'burrow', 'forced_movement']);")
    && gameShellSource.includes("const normalizedType = rawType.startsWith('npc_') ? rawType.slice(4) : rawType;"),
  'GameShell recognizes both player and npc movement event types from the authoritative event stream'
);

assert(
  gameShellSource.includes("const movementPacket = resolutionPackets.find((packet) => String(packet?.kind || '').trim().toLowerCase() === 'movement_resolution')")
    && gameShellSource.includes('const actorRef = String(movementPacket?.actor_entity_ref || event.actor || \'\').trim();')
    && gameShellSource.includes('const toHex = movementPacket?.to_hex && typeof movementPacket.to_hex === \'object\''),
  'GameShell resolves movement actor and destination hex from canonical movement packets'
);

assert(
  gameShellSource.includes('_resolveEntityForAuthoritativeRef(actorRef = \'\')')
    && gameShellSource.includes('this.applyLocalEntityPlacement(entity, roomId, q, r);')
    && gameShellSource.includes("this.bus.emit('room:entities-changed', {"),
  'GameShell updates local entity placement and notifies map consumers after authoritative movement'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
