/**
 * @file
 * Contract test: map-tab initiative must flow from encounter_presentation.
 *
 * Run with:
 *   node tests/map_tab_initiative_encounter_presentation_contract_test.js
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

console.log('\n=== Map-tab initiative encounter presentation contract ===');

const combatApiController = read('../src/Controller/CombatEncounterApiController.php');
assert(combatApiController.includes("'encounter_presentation' =>"), 'Combat encounter API response includes encounter_presentation');
assert(combatApiController.includes("schema_version' => 'encounter-map-v1'"), 'Encounter presentation schema version is defined');
assert(combatApiController.includes("buildIdleEncounterPresentation"), 'Idle encounter presentation helper exists');

const hexMapController = read('../src/Controller/HexMapController.php');
assert(hexMapController.includes("buildEncounterPresentationFromGameState"), 'HexMap bootstrap can derive encounter presentation');
assert(
  hexMapController.includes("['encounter_presentation'] = $this->buildEncounterPresentationFromGameState"),
  'HexMap bootstrap injects encounter presentation when missing'
);

const turnManagement = read('../js/ecs/systems/TurnManagementSystem.js');
assert(turnManagement.includes('encounter_presentation'), 'Turn management hydration reads encounter_presentation');
assert(turnManagement.includes('mapEncounterStatusToCombatState'), 'Turn management maps authoritative encounter status into client combat state');
assert(turnManagement.includes('this.encounterStatus = encounterStatus;'), 'Turn management preserves raw encounter status from server payloads');
assert(turnManagement.includes('onOrderChange('), 'Turn management exposes initiative-order callback');
assert(turnManagement.includes('this.onOrderChangeCallback'), 'Turn management stores initiative-order callback');

const gameShell = read('../js/v2/GameShell.js');
assert(gameShell.includes("bus.emit('combat:order-changed'"), 'GameShell emits combat:order-changed from hydrated order');
assert(gameShell.includes('this.panels.combat.init(this.dungeonData, stateManager);'), 'GameShell passes state manager into CombatPanel');

const combatPanel = read('../js/v2/panels/CombatPanel.js');
assert(combatPanel.includes('_bindMapInitiativeHandlers()'), 'CombatPanel binds map initiative selection handlers');
assert(combatPanel.includes("const list = this._el.mapInitiativeList;"), 'CombatPanel selection targets the map initiative list');
assert(combatPanel.includes('hexmap?.entityManager?.getEntity?.(entityId)'), 'CombatPanel resolves clicked initiative card to the map entity');
assert(combatPanel.includes('hexmap.selectEntity?.(entity);'), 'CombatPanel selection routes clicked initiative cards to hexmap entity selection');
assert(combatPanel.includes('map-current-turn-name'), 'CombatPanel map tracker exposes current-turn summary slot');
assert(combatPanel.includes('map-next-turn-name'), 'CombatPanel map tracker exposes next-turn summary slot');
assert(combatPanel.includes('map-encounter-state'), 'CombatPanel map tracker exposes encounter state summary slot');
assert(combatPanel.includes('map-turn-counter'), 'CombatPanel map tracker exposes turn counter summary slot');
assert(combatPanel.includes('formatTurnCounter('), 'CombatPanel computes a numeric turn counter for initiative UI');
assert(combatPanel.includes('initiative-item--compact'), 'CombatPanel renders a compact map initiative list with name-only cards');
assert(combatPanel.includes('map-initiative-chat-log'), 'CombatPanel fallback tracker markup includes an embedded map initiative chat log');

const encounterSystem = read('../js/v2/systems/EncounterSystem.js');
assert(!encounterSystem.includes("this.bus.emit('combat:order-changed'"), 'Encounter fallback no longer emits canonical initiative order');

const template = read('../templates/hexmap-v2.html.twig');
assert(template.includes('id="map-initiative-chat-log"'), 'Map panel template exposes a dedicated initiative-side encounter feed log');
assert(template.includes('class="map-initiative-status"'), 'Map panel template nests status notifications under initiative tracker');
assert(template.includes('data-status="backend-wait"'), 'Map panel template provides backend-wait status element under initiative tracker');
assert(template.includes('data-status="unavail-banner"'), 'Map panel template provides server-unavailable status element under initiative tracker');

const chatPanel = read('../js/v2/panels/ChatPanel.js');
assert(chatPanel.includes('mapInitiativeChatLog:'), 'ChatPanel resolves the initiative-side encounter feed log element');
assert(chatPanel.includes('shouldMirrorToMapInitiativeFeed('), 'ChatPanel defines authoritative room-line mirroring for the map initiative feed');
assert(chatPanel.includes('appendMapInitiativeFeedLine('), 'ChatPanel appends mirrored encounter transcript lines into the initiative-side feed');

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
