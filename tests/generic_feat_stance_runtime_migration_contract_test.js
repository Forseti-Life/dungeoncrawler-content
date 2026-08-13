/**
 * @file
 * Contract test: generic feat stance transitions route through
 * StanceRuntimeService in encounter execution.
 *
 * Run with:
 *   node tests/generic_feat_stance_runtime_migration_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8',
);

console.log('\n=== Generic feat stance runtime migration contract ===');

assert(
  source.includes('protected ?StanceRuntimeService $stanceRuntimeService;')
    && source.includes("$this->stanceRuntimeService = $stance_runtime_service ?? (\\Drupal::hasService('dungeoncrawler_content.stance_runtime_service')"),
  'EncounterPhaseHandler resolves stance runtime service dependency'
);

assert(
  source.includes('$stance_transition = $this->applyFeatStanceRuntimeTransition(')
    && source.includes("'stance_transition' => $stance_transition,"),
  'Feat execution pipeline computes and emits stance transition metadata'
);

assert(
  source.includes("$feat_id = $params['feat_id'] ?? $params['featId'] ?? $params['option_id'] ?? $params['optionId'] ?? NULL;")
    && source.includes("$stance_transition = is_array($params['stance_transition'] ?? NULL)")
    && source.includes("$stance_transition['action']")
    && source.includes("$stance_transition['stance_id']")
    && source.includes("if (!in_array($stance_action, ['enter', 'exit'], TRUE)) {")
    && source.includes('$stance_action = $this->inferFeatStanceAction($params, $feat_id);')
    && source.includes('protected function inferFeatStanceAction(array $params, mixed $feat_id): string')
    && source.includes("if (str_contains($candidate, 'stance') || str_contains($candidate, 'arcane_cascade')) {")
    && source.includes("$character_state = $this->stanceRuntimeService->enterStance(")
    && source.includes("$character_state = $this->stanceRuntimeService->exitStance("),
  'Feat stance transition handler normalizes option/nested inputs, infers stance-tagged actions, and routes enter/exit through StanceRuntimeService'
);

assert(
  source.includes('$this->persistCanonicalCharacterState($actor_entity, $campaign_id, $character_state);')
    && source.includes("$dungeon_data['entities'][$actor_index]['state']['stance_state'] = $character_state['stance_state'] ?? [];"),
  'Feat stance transition persists canonical character state and mirrors runtime stance state'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
