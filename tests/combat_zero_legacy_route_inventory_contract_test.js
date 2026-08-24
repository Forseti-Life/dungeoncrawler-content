/**
 * @file
 * Contract test: all encounter action routes emit canonical execution requests.
 *
 * Run with:
 *   node tests/combat_zero_legacy_route_inventory_contract_test.js
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
  const phaseHandlerSource = fs.readFileSync(path.join(serviceDir, 'EncounterPhaseHandler.php'), 'utf8');
  const traitSource = fs.readdirSync(serviceDir)
    .filter((name) => name.startsWith('EncounterPhaseHandler') && name.endsWith('Trait.php'))
    .sort()
    .map((name) => fs.readFileSync(path.join(serviceDir, name), 'utf8'))
    .join('\n');
  return `${phaseHandlerSource}\n${traitSource}`;
}

console.log('\n=== Combat zero-legacy route inventory contract ===');

const phaseHandlerSource = readEncounterPhaseHandlerCompositeSource();
const routeRegex = /protected function (route\w+IntentExecution)\([\s\S]*?\): array \{/g;

const routes = [];
let match;
while ((match = routeRegex.exec(phaseHandlerSource)) !== null) {
  routes.push({ name: match[1], index: match.index });
}

const legacyRoutes = [];
for (let i = 0; i < routes.length; i++) {
  const start = routes[i].index;
  const end = i + 1 < routes.length ? routes[i + 1].index : phaseHandlerSource.length;
  const body = phaseHandlerSource.slice(start, end);
  if (!body.includes('buildCombatExecutionRequest(')) {
    legacyRoutes.push(routes[i].name);
  }
}

assert(routes.length > 0, 'Route inventory parsed from EncounterPhaseHandler');
assert(
  phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'strike'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'stride'")
    && phaseHandlerSource.includes("buildCombatExecutionRequest(\n      'cast_spell'"),
  'Strike/stride/cast_spell now emit direct action-level execution requests'
);
assert(legacyRoutes.length === 0, `No legacy action routes remain (found: ${legacyRoutes.join(', ') || 'none'})`);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
