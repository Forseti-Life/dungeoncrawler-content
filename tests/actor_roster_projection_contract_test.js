/**
 * @file
 * Contract test: canonical actor_roster projection and bootstrap delivery.
 *
 * Run with:
 *   node tests/actor_roster_projection_contract_test.js
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

const projectorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/MapVisualStateProjector.php'),
  'utf8',
);
const controllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/HexMapController.php'),
  'utf8',
);
const projectorUnitSource = fs.readFileSync(
  path.resolve(__dirname, '../tests/src/Unit/Service/MapVisualStateProjectorTest.php'),
  'utf8',
);

console.log('\n=== Actor roster projection contract ===');

assert(
  projectorSource.includes("'actor_roster' => $actor_roster")
    && projectorSource.includes('protected function buildActorRoster('),
  'MapVisualStateProjector emits actor_roster and defines projection builder'
);

assert(
  projectorSource.includes("'state' => $this->buildVisualOccupantStateProjection($entity_state, $metadata)")
    && projectorSource.includes('protected function buildVisualOccupantStateProjection(array $entity_state, array $metadata): array')
    && projectorSource.includes("$projected = $entity_state;")
    && projectorSource.includes("$projected['metadata'] = $metadata;"),
  'Visual occupant projection preserves canonical actor state/metadata for roster sheet hydration'
);

assert(
  projectorSource.includes("'schema_version' => 'actor-roster-v1'")
    && projectorSource.includes("'available_filters' => ['all', 'party', 'allied', 'hostile', 'neutral', 'hazard']")
    && projectorSource.includes("'sort_modes' => ['alpha', 'initiative']"),
  'Actor roster projection shape includes schema version, filters, and sort modes'
);

assert(
  projectorSource.includes("return 'hazard';")
    && projectorSource.includes("return 'hostile';")
    && projectorSource.includes("return 'party';"),
  'Actor side classification covers party/hostile/hazard outcomes'
);

assert(
  projectorSource.includes("'sheet_type' => 'character'")
    && projectorSource.includes("'sheet_type' => 'follower'")
    && projectorSource.includes("'sheet_type' => 'actor'"),
  'Sheet contract projection covers character, follower, and actor sheet refs'
);

assert(
  controllerSource.includes("'actor_roster' => [")
    && controllerSource.includes("$actor_roster_entries = array_values(array_filter("),
  'HexMap bootstrap trims and delivers actor_roster for active room payloads'
);

assert(
  projectorUnitSource.includes('testProjectBuildsActorRosterForMixedSides')
    && projectorUnitSource.includes("$this->assertSame('hostile', $byRuntimeId['npc-skeleton-a']['side']);"),
  'Unit coverage includes mixed-side roster projection including hostile actors'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
