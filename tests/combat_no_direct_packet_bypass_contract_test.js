/**
 * @file
 * Contract test: prevent direct packet-builder bypasses on main encounter seams.
 *
 * Run with:
 *   node tests/combat_no_direct_packet_bypass_contract_test.js
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

function readEncounterPhaseHandlerCompositeSource() {
  const serviceDir = path.resolve(__dirname, '../src/Service');
  const phaseHandlerSource = fs.readdirSync(serviceDir)
    .filter((name) => name === 'EncounterPhaseHandler.php' || (name.startsWith('EncounterPhaseHandler') && name.endsWith('Trait.php')))
    .sort()
    .map((name) => fs.readFileSync(path.join(serviceDir, name), 'utf8'))
    .join('\n');
  return phaseHandlerSource;
}

console.log('\n=== Combat packet bypass guard ===');

const executorSource = read('../src/Service/EncounterActionExecutor.php');
const phaseHandlerSource = readEncounterPhaseHandlerCompositeSource();

assert(
  !executorSource.includes('combatResolutionContractService->buildDamageApplicationPacket(')
    && !executorSource.includes('combatResolutionContractService->buildMovementResolutionPacket('),
  'EncounterActionExecutor does not bypass unified damage/movement seams with direct contract packet builders'
);

assert(
  !phaseHandlerSource.includes('combatResolutionContractService->buildStateEffectChangePacket(')
    && !phaseHandlerSource.includes('combatResolutionContractService->buildReactionResolutionPacket(')
    && !phaseHandlerSource.includes('combatResolutionContractService->buildDamageApplicationPacket('),
  'EncounterPhaseHandler does not bypass unified state/reaction/damage seams with direct contract packet builders'
);

assert(
  executorSource.includes('$this->unifiedDamageEngine->')
    && executorSource.includes('$this->unifiedMovementEngine->')
    && phaseHandlerSource.includes('$this->unifiedStateEffectEngine->')
    && phaseHandlerSource.includes('$this->unifiedReactionEngine->')
    && phaseHandlerSource.includes('$this->unifiedDamageEngine->'),
  'Main encounter seams route packet emission through unified engines'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
