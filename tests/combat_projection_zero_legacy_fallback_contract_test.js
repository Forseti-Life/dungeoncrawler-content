/**
 * @file
 * Freeze canonical-only combat projection consumers.
 *
 * Run with:
 *   node tests/combat_projection_zero_legacy_fallback_contract_test.js
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

console.log('\n=== Combat projection zero-legacy-fallback contract ===');

const chatPanelSource = read('../js/v2/panels/ChatPanel.js');
assert(
  chatPanelSource.includes('combat_projection_contract_violation:${normalizedType}:resolution_envelope')
    && chatPanelSource.includes("'party_recovery_action'")
    && chatPanelSource.includes('Array.isArray(resolutionEnvelope?.result?.healed)')
    && !chatPanelSource.includes('data?.damage_packet')
    && !chatPanelSource.includes('data?.movement_packet')
    && !chatPanelSource.includes('data?.state_effect_packet')
    && !chatPanelSource.includes('data?.state_effect_packets')
    && !chatPanelSource.includes('data?.reaction_packet')
    && !chatPanelSource.includes('data?.condition_applied')
    && !chatPanelSource.includes('data?.forced_to')
    && !chatPanelSource.includes('data?.hazard_resolution_envelope'),
  'Chat and action-log fallback messages use only canonical resolution-envelope packets'
);

const encounterSystemSource = read('../js/v2/systems/EncounterSystem.js');
const damageResolverStart = encounterSystemSource.indexOf('  _resolveActionDamageFromResult(payload = null) {');
const damageResolverEnd = encounterSystemSource.indexOf('\n  _buildSpellCastSummary(', damageResolverStart);
const damageResolverSource = encounterSystemSource.slice(damageResolverStart, damageResolverEnd);
assert(
  damageResolverSource.includes('combat_action_result_contract_violation:resolution_envelope')
    && damageResolverSource.includes('envelope.packets.find(')
    && !damageResolverSource.includes('damage_packet')
    && !damageResolverSource.includes('payload?.damage')
    && !damageResolverSource.includes('data?.damage'),
  'Action summaries resolve damage only from canonical resolution-envelope packets'
);
assert(
  encounterSystemSource.includes("const response = await fetch(`/api/character/${context.characterId}/cast-spell`")
    && encounterSystemSource.includes('this._buildSpellCastSummary(\n        context.actorLabel,\n        spellName,\n        null,'),
  'Non-combat spell casting does not invoke the combat-envelope damage resolver'
);

const gameShellSource = read('../js/v2/GameShell.js');
assert(
  gameShellSource.includes('combat_movement_projection_contract_violation:${normalizedType}:resolution_envelope')
    && gameShellSource.includes('combat_movement_projection_contract_violation:${normalizedType}:movement_packet'),
  'Movement projection hard-fails missing canonical envelopes and packets'
);

const eventLoggerSource = read('../src/Service/GameEventLogger.php');
assert(
  eventLoggerSource.includes('combat_event_telemetry_contract_violation:resolution_envelope')
    && !eventLoggerSource.includes("$event_data['state_effect_packets']")
    && !eventLoggerSource.includes("$event_data['state_effect_packet']"),
  'State-effect telemetry reads only the canonical action resolution envelope'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
