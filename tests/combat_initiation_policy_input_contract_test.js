/**
 * @file
 * Contract test: combat initiation must provide normalized aggression-policy
 * input and enforce numeric multi-factor combat entry authorization.
 *
 * Run with:
 *   node tests/combat_initiation_policy_input_contract_test.js
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

const brokerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmOrchestrationBrokerService.php'),
  'utf8',
);
const policySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/AggressionPolicyService.php'),
  'utf8',
);

console.log('\n=== Combat initiation policy input contract ===');

assert(
  brokerSource.includes('$policy_input = $this->buildCombatPolicyInput')
    && brokerSource.includes('protected function buildCombatPolicyInput(int $campaign_id, string $room_id, array $combat, array $enemy_ids): array'),
  'Broker routes combat initiation policy through normalized policy-input builder'
);

assert(
  brokerSource.includes("'relationship_attitude' => $relationship_attitude")
    && brokerSource.includes("'relationship_score' => $relationship_score")
    && brokerSource.includes("'actor_score' => $actor_score")
    && brokerSource.includes("'fear_score' =>")
    && brokerSource.includes("'aggression_bias_score' =>")
    && brokerSource.includes("'explicit_attack_declared' => $explicit_attack_declared"),
  'Broker includes attitude labels, numeric scores, and explicit attack signal in policy input'
);

assert(
  brokerSource.includes('$aggression_signal = $this->resolveAggressionSignalFromCombatPayload($combat, $target_ids);')
    && brokerSource.includes('protected function resolveAggressionSignalFromCombatPayload(array $combat, array $target_ids): string')
    && brokerSource.includes("return 'coercive_threat';")
    && brokerSource.includes("return 'scripted_trigger';"),
  'Broker derives coercive/scripted aggression signals from combat payload semantics when explicit signal is absent'
);

assert(
  brokerSource.includes("protected function buildCombatPolicyInput(int $campaign_id, string $room_id, array $combat, array $enemy_ids): array")
  && brokerSource.includes("$actor_attitude_source = 'actor_disposition';")
  && brokerSource.includes('$actor_score = NULL;')
  && brokerSource.includes("$disposition = $this->getActorDispositionService()->getDispositionSummary($campaign_id, $source_entity_ref);")
  && brokerSource.includes("if (isset($disposition['current_score']) && is_numeric($disposition['current_score'])) {")
  && brokerSource.includes("if ($actor_attitude === '') {")
  && brokerSource.includes("$actor_attitude = trim((string) ($combat['actor_attitude'] ?? $combat['attitude'] ?? ''));")
  && brokerSource.includes("'actor_attitude_source' => $actor_attitude_source"),
  'Broker resolves actor disposition label and numeric score from ActorDispositionService first, with payload fallback'
);

assert(
  brokerSource.includes("$relationship_attitude_source = 'relationship_edge';")
    && brokerSource.includes('$selected_edge = NULL;')
    && brokerSource.includes('$edge = $this->getRelationshipAttitudeService()->resolveEdgeDispositionDetails($source_entity_ref, $target_ref, $campaign_id);')
    && brokerSource.includes('? DispositionAuthorityContract::normalizeScore($edge[\'score\'])')
    && brokerSource.includes('if ($edge_score < (int) ($selected_edge[\'score\'] ?? 0)) {')
    && brokerSource.includes('$relationship_attitude = (string) ($selected_edge[\'attitude\'] ?? \'\');')
    && brokerSource.includes('$relationship_score = (int) ($selected_edge[\'score\'] ?? 0);')
    && brokerSource.includes("$relationship_attitude = trim((string) ($combat['relationship_attitude'] ?? ''));")
    && brokerSource.includes("'relationship_attitude_source' => $relationship_attitude_source"),
  'Broker resolves relationship attitude and numeric score from the same most-hostile edge selection, with payload fallback'
);

assert(
  brokerSource.includes('$latest_state = $aggression_state_store instanceof AggressionStateStoreService')
    && brokerSource.includes('? $aggression_state_store->loadLatestState($campaign_id, $room_id)')
    && brokerSource.includes("'current_state_source' => $current_state_source"),
  'Broker can resolve missing current_state from AggressionStateStoreService with explicit state-source tracking'
);

assert(
  brokerSource.includes('$this->getActorDispositionService()->applyDispositionEvent(')
    && brokerSource.includes("'combat_initiation_declared'")
    && brokerSource.includes("'Combat initiation action declared hostility.'"),
  'Broker applies deterministic combat-initiation disposition trigger before combat-entry policy evaluation'
);

assert(
  policySource.includes('$relationship_attitude = strtolower(trim((string) ($input[\'relationship_attitude\'] ?? \'\')));')
    && policySource.includes('$hostility_pressure = (int) round(')
    && policySource.includes('$entry_authorized = FALSE;')
    && policySource.includes('if ($hostility_pressure <= -65) {')
    && policySource.includes('elseif ($hostility_pressure <= -40 && ($explicit_attack_declared || in_array($state, [\'threatened\', \'hostile\', \'engaged\'], TRUE))) {')
    && policySource.includes("'hostility_pressure' => $hostility_pressure")
    && policySource.includes("'actor_attitude_source' => $actor_attitude_source")
    && policySource.includes("'relationship_attitude_source' => $relationship_attitude_source"),
  'Aggression policy evaluator authorizes combat entry from numeric multi-factor hostility pressure with label fallback'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
