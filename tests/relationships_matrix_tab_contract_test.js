/**
 * @file
 * Contract test: actor relationships matrix tab wiring.
 *
 * Run with:
 *   node tests/relationships_matrix_tab_contract_test.js
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

const templateSource = fs.readFileSync(
  path.resolve(__dirname, '../templates/hexmap-v2.html.twig'),
  'utf8',
);
const panelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'),
  'utf8',
);
const routingSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.routing.yml'),
  'utf8',
);
const settingsControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/CampaignSettingsController.php'),
  'utf8',
);
const matrixReadModelSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipsMatrixReadModelService.php'),
  'utf8',
);
const identityResolverSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RelationshipsActorIdentityResolverService.php'),
  'utf8',
);
const actorDispositionSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorDispositionService.php'),
  'utf8',
);

console.log('\n=== Relationships matrix tab contract ===');

assert(
  templateSource.includes('data-sidebar-tab="relationships"')
    && templateSource.includes('id="sidebar-panel-relationships"')
    && templateSource.includes('id="actor-relationships-matrix"'),
  'Template includes relationships tab and matrix panel mount point'
);

assert(
  panelSource.includes("if (tab.dataset.sidebarTab === 'relationships')")
    && panelSource.includes('renderRelationshipsMatrix()')
    && panelSource.includes('resolveRelationshipMatrixActors()')
    && panelSource.includes('resolveSelectedRelationshipSourceRef(actors)')
    && panelSource.includes('buildLocalRelationshipCalculations(actors, localMatrix)')
    && panelSource.includes('buildLocalRelationshipsMatrix(actors)')
    && panelSource.includes('response.status === 404')
    && panelSource.includes('/relationships/matrix?'),
  'Character panel renders relationships matrix from campaign API'
);

assert(
  routingSource.includes('dungeoncrawler_content.api.campaign_relationships_matrix:')
    && routingSource.includes("path: '/api/campaign/{campaign_id}/relationships/matrix'")
    && routingSource.includes('CampaignSettingsController::getRelationshipsMatrix'),
  'Routing includes campaign relationships matrix endpoint'
);

assert(
  settingsControllerSource.includes('public function getRelationshipsMatrix(int $campaign_id, Request $request): JsonResponse')
    && settingsControllerSource.includes('relationshipsMatrixReadModelService->buildPayload(')
    && settingsControllerSource.includes('if ($actor_refs === [])')
    && settingsControllerSource.includes("'selected_actor_ref' => ''"),
  'Controller endpoint delegates matrix payload generation to read-model service'
);

assert(
  matrixReadModelSource.includes('class RelationshipsMatrixReadModelService')
    && matrixReadModelSource.includes('relationshipsActorIdentityResolver->resolveInstitutionActorIdentity(')
    && !matrixReadModelSource.includes('protected function resolveInstitutionActorIdentity(')
    && matrixReadModelSource.includes("'source_summary_override' =>")
    && matrixReadModelSource.includes("'relationship_edge_override' =>")
    && matrixReadModelSource.includes('resolveEdgeDispositionDetails($source_ref, $target_ref, $campaign_id)')
    && matrixReadModelSource.includes('getDispositionSummary($campaign_id, $source_ref, [], FALSE)')
    && matrixReadModelSource.includes('actorStanceResolverService->projectStance(')
    && matrixReadModelSource.includes('aggressionPolicyService->evaluateAggressionState([')
    && matrixReadModelSource.includes("'calculations' => $calculations")
    && matrixReadModelSource.includes("'institution_score' => (int) ($institution['score'] ?? 0),")
    && matrixReadModelSource.includes("'formula' => 'final_score = resolver(source_default_score, edge_score_or_0, institution_score, scene_components...)'")
    && matrixReadModelSource.includes("'institution_breakdown' =>")
    && matrixReadModelSource.includes("'stage_errors' =>"),
  'Read-model service resolves weighted calculations with stage error reporting'
);

assert(
  panelSource.includes('const hostilityRows = Array.isArray(aggressionPolicy?.rows)')
    && panelSource.includes('const hostilityPressureLabel = `Hostility pressure ${hostilityPressure.toFixed(0)}`;')
    && panelSource.includes('<span style="${hostilityPressureStyle}">${escapeQuestHtml(hostilityPressureLabel)}</span>')
    && panelSource.includes('Number(row.contribution || 0).toFixed(2)')
    && !panelSource.includes("variable: 'relationship_score',"),
  'Character panel renders hostility-pressure rows and summary badge from backend payload instead of recomputing them locally'
);

assert(
  identityResolverSource.includes('class RelationshipsActorIdentityResolverService')
    && identityResolverSource.includes('public function resolveInstitutionActorIdentity(')
    && identityResolverSource.includes('public function buildFallbackTargetInstitutionMemberships(')
    && identityResolverSource.includes('protected function buildEntityRefCandidates('),
  'Identity resolver service owns actor candidate and fallback membership resolution'
);

assert(
  actorDispositionSource.includes('public function getDispositionSummary(int $campaign_id, string $entity_ref, array $live_entity = [], bool $converge_state = FALSE): array')
    && actorDispositionSource.includes('if ($converge_state) {')
    && actorDispositionSource.includes("'source' => 'psychology_projection_sync'"),
  'Disposition summary defaults to read-only behavior and converges only when explicitly requested'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
