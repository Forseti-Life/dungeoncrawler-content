/**
 * @file
 * Contract test: stride destinations are blocked by ANY occupant, including
 * defeated combatants.
 *
 * Campaign 907 regression: Skeleton Guard Alpha struck Mimi, Mimi dropped to
 * 0 HP, and Alpha then strode onto Mimi's hex because both the authoritative
 * executor check and the autoplay planner's occupancy index skipped
 * participants flagged `is_defeated`. Two tokens then rendered on hex (1,3).
 *
 * A downed body still physically occupies its hex (PF2E: you cannot end your
 * movement in a square occupied by another creature), so `is_defeated` must
 * never be a reason to treat a hex as free.
 *
 * Run with:
 *   node tests/stride_destination_occupancy_contract_test.js
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

/**
 * Extracts the body of a brace-delimited block starting at `startIndex`.
 */
function blockFrom(source, startIndex) {
  const open = source.indexOf('{', startIndex);
  if (open === -1) {
    return '';
  }
  let depth = 0;
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') {
      depth++;
    }
    else if (source[i] === '}') {
      depth--;
      if (depth === 0) {
        return source.slice(open, i + 1);
      }
    }
  }
  return '';
}

console.log('\n=== Stride destination occupancy contract ===');

const executorSource = read('../src/Service/EncounterActionExecutor.php');
const coordinatorSource = read('../src/Service/ActorAutoplayCoordinator.php');

// --- Authoritative executor check -----------------------------------------

const occupancyLoopStart = executorSource.indexOf(
  "foreach ((array) ($encounter_for_actor['participants'] ?? []) as $candidate_participant) {"
);

assert(
  occupancyLoopStart !== -1,
  'processStride scans encounter participants to validate the stride destination'
);

const occupancyLoop = blockFrom(executorSource, occupancyLoopStart);

assert(
  occupancyLoop !== ''
    && !/is_defeated/.test(occupancyLoop),
  'processStride destination-occupancy loop never skips defeated participants'
);

assert(
  occupancyLoop.includes("(string) ($candidate_participant['entity_id'] ?? '') === $actor_id"),
  'only the moving actor is exempt from the destination-occupancy check'
);

assert(
  occupancyLoop.includes("sprintf('Destination hex is occupied by %s.', $blocker_name)")
    && occupancyLoop.includes("'Destination hex is occupied.'"),
  'a blocked stride names the blocking occupant, falling back to a generic message'
);

// --- Autoplay planner occupancy index -------------------------------------

const indexStart = coordinatorSource.indexOf('protected function buildOccupiedHexIndex(');

assert(
  indexStart !== -1,
  'ActorAutoplayCoordinator builds an occupied-hex index for stride planning'
);

const indexBody = blockFrom(coordinatorSource, indexStart);

assert(
  indexBody !== ''
    && !/is_defeated/.test(indexBody),
  'buildOccupiedHexIndex counts defeated combatants as occupying their hex'
);

if (failed > 0) {
  console.error(`\nFAILED: ${failed} failing assertion(s)`);
  process.exit(1);
}

console.log(`\nOK: ${passed} passing assertion(s)`);
