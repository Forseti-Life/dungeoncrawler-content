/*
 * Contract test: room generation emits canonical object/hex payload contracts.
 *
 * Run with:
 *   node tests/room_generation_object_contract_test.js
 */

const fs = require('fs');
const path = require('path');

let passed = 0;
let failed = 0;

function assert(condition, message) {
  if (condition) {
    passed += 1;
    console.log(`  ✓ ${message}`);
  } else {
    failed += 1;
    console.error(`  ✗ ${message}`);
  }
}

(function run() {
  const roomGeneratorPath = path.join(__dirname, '..', 'src', 'Service', 'RoomGeneratorService.php');
  const roomLibraryPath = path.join(__dirname, '..', 'src', 'Service', 'RoomLibraryService.php');
  const dungeonGeneratorPath = path.join(__dirname, '..', 'src', 'Service', 'DungeonGeneratorService.php');

  const roomGeneratorSrc = fs.readFileSync(roomGeneratorPath, 'utf8');
  const roomLibrarySrc = fs.readFileSync(roomLibraryPath, 'utf8');
  const dungeonGeneratorSrc = fs.readFileSync(dungeonGeneratorPath, 'utf8');

  console.log('\n=== Room generation canonical object/hex contract ===');

  assert(
    roomGeneratorSrc.includes('$this->ensureCanonicalContracts($cached, $cached_room_id);'),
    'Cached rooms are canonicalized before returning'
  );
  assert(
    roomGeneratorSrc.includes('$this->ensureCanonicalContracts($library_room, $library_room_id);'),
    'Library-instantiated rooms are canonicalized before persistence'
  );
  assert(
    roomGeneratorSrc.includes("'object_id' => $object_id"),
    'Canonical object_id is emitted for generated room objects'
  );
  assert(
    roomGeneratorSrc.includes("'object_instance_id' => $instance_id"),
    'Canonical object_instance_id is emitted for generated room objects'
  );
  assert(
    roomGeneratorSrc.includes("'placement' => ["),
    'Generated room objects emit deterministic placement metadata'
  );
  assert(
    roomGeneratorSrc.includes('$room_data[\'exits\'] = $this->normalizeCanonicalExits('),
    'Room exits are normalized through the canonical exits contract helper'
  );
  assert(
    roomGeneratorSrc.includes("'exits' => $room_data['exits'] ?? []"),
    'Generated room persistence stores canonical exits in layout_data'
  );
  assert(
    roomLibrarySrc.includes("'exits' => $room_data['exits'] ?? []"),
    'Room library templates preserve canonical exits'
  );
  assert(
    roomLibrarySrc.includes("'exits' => $layout['exits'] ?? []"),
    'Room library instantiation restores canonical exits'
  );
  assert(
    dungeonGeneratorSrc.includes("'exits' => $room['exits'] ?? []"),
    'Dungeon persistence stores room canonical exits during level generation'
  );

  if (failed > 0) {
    console.error(`\nFAILED: ${failed} failing assertion(s)`);
    process.exit(1);
  }

  console.log(`\nOK: ${passed} passing assertion(s)`);
})();
