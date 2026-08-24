/**
 * @file
 * Contract test: server-side targeting service constraints and cast spell bridge.
 *
 * Run with:
 *   node tests/action_targeting_service_contract_test.js
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

const targetingService = fs.readFileSync(path.resolve(__dirname, '../src/Service/ActionTargetingService.php'), 'utf8');
const encounterExecutor = fs.readFileSync(path.resolve(__dirname, '../src/Service/EncounterActionExecutor.php'), 'utf8');
const encounterPhaseHandler = fs.readFileSync(path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'), 'utf8');
const encounterPhaseHandlerRuntimeTrait = fs.readFileSync(path.resolve(__dirname, '../src/Service/EncounterPhaseHandlerRuntimeTrait.php'), 'utf8');
const explorationPhaseHandler = fs.readFileSync(path.resolve(__dirname, '../src/Service/ExplorationPhaseHandler.php'), 'utf8');

console.log('\n=== Action targeting service contracts ===');

assert(
  targetingService.includes('public function normalizeTargetRefs(?string $intent_target, array $params = []): array {')
    && targetingService.includes("$target_rows = $params['targets'] ?? NULL;")
    && targetingService.includes("$params['target_entity_id'] ?? NULL,")
    && targetingService.includes("$params['targetEntityId'] ?? NULL,")
    && targetingService.includes('if (is_scalar($row)) {')
    && targetingService.includes('$normalized = trim((string) $row);')
    && targetingService.includes('$row_refs = [];')
    && targetingService.includes("$row['target_entity_id'] ?? NULL,")
    && targetingService.includes("$row['targetEntityId'] ?? NULL,")
    && targetingService.includes('return $row_refs;')
    && targetingService.includes("return $normalized_refs;"),
  'ActionTargetingService supports canonical targets[] normalization for object rows and scalar target-ref rows'
);

assert(
  targetingService.includes('public function validateTargetSelectionConstraints(array $target_refs, array $params = []): array {')
    && targetingService.includes("$min_targets = is_scalar($params['min_targets'] ?? NULL)")
    && targetingService.includes("$max_targets = is_scalar($params['max_targets'] ?? NULL)")
    && targetingService.includes("$allow_duplicate_targets = !empty($params['allow_duplicate_targets']);"),
  'ActionTargetingService enforces target cardinality and duplicate-selection policy'
);

assert(
  targetingService.includes('public function validateRangeConstraint(?array $origin_hex, ?array $target_hex, array $params = []): array {')
    && targetingService.includes("$range_ft = is_scalar($params['range_ft'] ?? NULL)")
    && targetingService.includes("return ['valid' => TRUE, 'error' => NULL, 'distance_ft' => $distance_ft];"),
  'ActionTargetingService exposes canonical server-side range validation for target picks'
);

assert(
  encounterExecutor.includes('$target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);')
    && encounterExecutor.includes('$target_constraint_check = $this->actionTargetingService->validateTargetSelectionConstraints($target_refs, $params);')
    && encounterExecutor.includes('$range_check = $this->actionTargetingService->validateRangeConstraint($origin_hex, $target_hex, $params);')
    && encounterExecutor.includes("$params['selected_targets'] = $target_refs;"),
  'EncounterActionExecutor cast spell path consumes targets[] and validates target constraints and range before execution'
);

assert(
  encounterExecutor.includes("$targeting_mode = $this->actionTargetingService->resolveTargetingMode('talk', $params);")
    && encounterExecutor.includes("Talk targeting mode '%s' requires a target.")
    && encounterExecutor.includes("$targeting_mode = $this->actionTargetingService->resolveTargetingMode('interact', $params);")
    && encounterExecutor.includes("Interact targeting mode '%s' requires a target.")
    && encounterExecutor.includes("return ['error' => (string) ($target_constraint_check['error'] ?? 'Invalid target selection.')];"),
  'EncounterActionExecutor extends canonical target normalization and constraint validation beyond cast-spell into talk/interact/strike lanes'
);

assert(
  (
    encounterPhaseHandler.includes('$normalized_target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);')
      && encounterPhaseHandler.includes("$params['selected_targets'] = $normalized_target_refs;")
      && encounterPhaseHandler.includes('$target_id = $normalized_target_refs[0] ?? $target_id;')
  ) || (
    encounterPhaseHandlerRuntimeTrait.includes('$normalized_target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);')
      && encounterPhaseHandlerRuntimeTrait.includes("$params['selected_targets'] = $normalized_target_refs;")
      && encounterPhaseHandlerRuntimeTrait.includes('$target_id = $normalized_target_refs[0] ?? $target_id;')
  ),
  'EncounterPhaseHandler normalizes intent targets through ActionTargetingService before routing targeted actions'
);

assert(
  explorationPhaseHandler.includes('$normalized_target_refs = $this->actionTargetingService->normalizeTargetRefs($target_id, $params);')
    && explorationPhaseHandler.includes("$params['selected_targets'] = $normalized_target_refs;")
    && explorationPhaseHandler.includes('$target_id = $normalized_target_refs[0] ?? $target_id;'),
  'ExplorationPhaseHandler normalizes intent targets through ActionTargetingService before dispatching target-bearing actions'
);

console.log('\n=========================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
