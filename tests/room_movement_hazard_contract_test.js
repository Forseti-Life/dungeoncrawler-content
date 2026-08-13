/**
 * @file
 * Contract test: direct room movement resolves passive hazards.
 *
 * Run with:
 *   node tests/room_movement_hazard_contract_test.js
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

console.log('\n=== Room movement hazard contract ===');

const controllerSource = read('../src/Controller/CampaignEntityController.php');
assert(
  controllerSource.includes('private HazardService $hazardService;')
    && controllerSource.includes("$container->get('dungeoncrawler_content.hazard_service')")
    && controllerSource.includes('resolvePassiveRoomMovementHazardEvents(')
    && controllerSource.includes("'hazardEvents' => $hazard_events"),
  'CampaignEntityController wires hazard service and returns hazardEvents on room moves'
);

assert(
  controllerSource.includes("$entity_type !== 'hazard'")
    && controllerSource.includes("$trigger_type !== 'passive'")
    && controllerSource.includes('$this->hazardService->triggerHazard($entity);')
    && controllerSource.includes("'type' => 'hazard_triggered'"),
  'room movement hazard resolver only triggers passive hazards and emits structured hazard events'
);

assert(
  controllerSource.includes('resolveTerrainMovementHazardEvent(')
    && controllerSource.includes('resolveRoomHexTerrainType(')
    && controllerSource.includes("'instance_id' => 'terrain:lava'")
    && controllerSource.includes("'damage_type' => 'fire'"),
  'room movement applies terrain hazard event contract for lava hex entry'
);

assert(
  controllerSource.includes('resolveTotalHazardDamage(')
    && controllerSource.includes('applyDamageToStateData(')
    && controllerSource.includes("$effect['damage_applied'] = (int) $effect['resolved_damage'];")
    && controllerSource.includes("'hp_current' => $this->extractCurrentHpFromStateData($state_data)"),
  'room movement hazard damage is resolved and persisted to actor HP fields'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
