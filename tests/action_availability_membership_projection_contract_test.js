/**
 * @file
 * Contract test: action-availability uses read-lane membership projection.
 *
 * Run with:
 *   node tests/action_availability_membership_projection_contract_test.js
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

const coordinatorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GameCoordinatorService.php'),
  'utf8',
);
const npcPsychologySource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/NpcPsychologyService.php'),
  'utf8',
);
const projectionServiceSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/InstitutionMembershipProjectionService.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Action availability membership projection contract ===');

assert(
  servicesSource.includes('dungeoncrawler_content.institution_membership_projection:')
    && servicesSource.includes('Drupal\\dungeoncrawler_content\\Service\\InstitutionMembershipProjectionService')
    && servicesSource.includes("- '@dungeoncrawler_content.institution_membership'"),
  'Service wiring exposes institution membership projection builder'
);

assert(
  projectionServiceSource.includes('class InstitutionMembershipProjectionService')
    && projectionServiceSource.includes('public function buildActorProjection(')
    && projectionServiceSource.includes("'freshness' => 'stale_safe'")
    && projectionServiceSource.includes("$payload['refresh_enqueued'] = TRUE;")
    && projectionServiceSource.includes('emitRefreshSignal('),
  'Projection service returns stale-safe payloads and emits refresh enqueue signals without writes'
);

assert(
  coordinatorSource.includes('$membership_projection_mode = $this->shouldEnableActionAvailabilityMembershipProjection($campaign_id);')
    && coordinatorSource.includes("'membership_projection_mode' => $membership_projection_mode,")
    && coordinatorSource.includes('$institution_membership_projection = $membership_projection_mode')
    && coordinatorSource.includes("'institution_membership_projection' => $institution_membership_projection !== [] ? $institution_membership_projection : NULL,")
    && coordinatorSource.includes("'membership_projection_freshness' => (string) ($institution_membership_projection['freshness'] ?? 'disabled')")
    && coordinatorSource.includes('protected function buildInstitutionMembershipProjection(')
    && coordinatorSource.includes("get('latency_toggle_canary_campaign_ids')"),
  'GameCoordinatorService routes action-availability reads through membership projection mode and diagnostics'
);

assert(
  npcPsychologySource.includes("'institution_membership_projection' => $institution_membership_projection !== []")
    && npcPsychologySource.includes("'action_context_membership_projection_freshness' =>")
    && npcPsychologySource.includes("'action_context_membership_projection_refresh_enqueued' =>"),
  'NPC monologue action-context payload includes membership projection dependencies and freshness diagnostics'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
