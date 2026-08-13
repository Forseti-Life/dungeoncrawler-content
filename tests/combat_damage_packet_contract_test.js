/**
 * @file
 * Contract test: strike/cast damage metadata flows through one damage packet shape.
 *
 * Run with:
 *   node tests/combat_damage_packet_contract_test.js
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

console.log('\n=== Combat damage packet contract ===');

const contractSource = read('../src/Service/CombatResolutionContractService.php');
assert(
  contractSource.includes('DAMAGE_PACKET_CONTRACT_VERSION')
    && contractSource.includes('buildDamageApplicationPacket(')
    && contractSource.includes("'kind' => 'damage_application'"),
  'CombatResolutionContractService defines the canonical damage packet contract'
);

const executorSource = read('../src/Service/EncounterActionExecutor.php');
const unifiedDamageEngineSource = read('../src/Service/UnifiedDamageEngine.php');
assert(
  executorSource.includes('$this->unifiedDamageEngine->resolveStrikeDamage(')
    && executorSource.includes('$this->unifiedDamageEngine->applySupportedSpellDamageToEncounterTarget(')
    && executorSource.includes("'damage_packet' => $damage_packet")
    && unifiedDamageEngineSource.includes("buildDamageApplicationPacket(\n      $actor_id,\n      $target_id,\n      'attack',")
    && unifiedDamageEngineSource.includes("buildDamageApplicationPacket(\n        $source_actor_id,\n        $target_id,\n        'spell',"),
  'Strike and supported spell damage paths route through UnifiedDamageEngine and preserve canonical packet metadata'
);

const phaseHandlerSource = read('../src/Service/EncounterPhaseHandler.php');
assert(
  phaseHandlerSource.includes("buildEvent('strike'")
    && phaseHandlerSource.includes("'damage_packet' => $damage_packet")
    && phaseHandlerSource.includes("buildEvent('cast_spell'")
    && phaseHandlerSource.includes('requireOptionalContractPayload('),
  'EncounterPhaseHandler validates and forwards damage_packet through strike and cast_spell event payloads'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
