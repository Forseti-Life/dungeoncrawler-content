/**
 * @file
 * Contract checks for runtime road-network capability cutover.
 *
 * Run with:
 *   node tests/navigation_runtime_cutover_contract_test.js
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

const encounterPhaseHandlerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8'
);
const mapGeneratorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/MapGeneratorService.php'),
  'utf8'
);
const navigationServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/NavigationService.php'),
  'utf8'
);

function listPhpFiles(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...listPhpFiles(fullPath));
      continue;
    }
    if (entry.isFile() && fullPath.endsWith('.php')) {
      files.push(fullPath);
    }
  }
  return files;
}

console.log('\n=== Navigation runtime cutover contract ===');

assert(
  encounterPhaseHandlerSource.includes('buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $active_room_id)'),
  'EncounterPhaseHandler transition validation uses road-network-aware capabilities'
);

assert(
  encounterPhaseHandlerSource.includes('buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $room_id)'),
  'EncounterPhaseHandler fallback capability path also uses road-network-aware capabilities'
);

assert(
  mapGeneratorSource.includes('buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $room_id)'),
  'MapGeneratorService navigation receipt projection uses road-network-aware capabilities'
);

assert(
  navigationServiceSource.includes('$this->buildNavigationCapabilitiesWithRoadNetwork($dungeon_data, $room_id);'),
  'NavigationService entry points route through road-network-aware capability builder'
);

assert(
  navigationServiceSource.includes('Direct-only capability projection is a legacy compatibility layer.')
    && navigationServiceSource.includes('Runtime callers must use buildNavigationCapabilitiesWithRoadNetwork()'),
  'NavigationService documents deprecation of direct-only capability projection'
);

assert(
  encounterPhaseHandlerSource.includes('Transitional fallback kept only for isolated EncounterPhaseHandler tests')
    && encounterPhaseHandlerSource.includes('must stay on NavigationService::buildNavigationCapabilitiesWithRoadNetwork()'),
  'EncounterPhaseHandler documents fallback capability path as temporary/deprecated'
);

const phpServiceRoot = path.resolve(__dirname, '../src/Service');
const directOnlyCallsites = listPhpFiles(phpServiceRoot)
  .filter((filePath) => !filePath.endsWith(path.normalize('/NavigationService.php')))
  .filter((filePath) => {
    const source = fs.readFileSync(filePath, 'utf8');
    return source.includes('->buildNavigationCapabilities(');
  });

assert(
  directOnlyCallsites.length === 0,
  'Runtime services contain no direct-only buildNavigationCapabilities() callsites'
);

console.log('\n===========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
