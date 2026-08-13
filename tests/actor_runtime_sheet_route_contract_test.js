/**
 * @file
 * Contract test: runtime actor sheet route wiring.
 *
 * Run with:
 *   node tests/actor_runtime_sheet_route_contract_test.js
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

const routingSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.routing.yml'),
  'utf8',
);
const characterControllerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/CharacterViewController.php'),
  'utf8',
);
const projectorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/MapVisualStateProjector.php'),
  'utf8',
);
const panelSource = fs.readFileSync(
  path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'),
  'utf8',
);

console.log('\n=== Runtime actor sheet route contract ===');

assert(
  routingSource.includes('dungeoncrawler_content.actor_view:')
    && routingSource.includes("path: '/actors/{actor_id}'")
    && routingSource.includes("CharacterViewController::viewRuntimeActor"),
  'Routing declares actor_view path mapped to CharacterViewController::viewRuntimeActor'
);

assert(
  characterControllerSource.includes('public function viewRuntimeActor(string $actor_id): array')
    && characterControllerSource.includes("dungeoncrawler_content.campaign_authorization")
    && characterControllerSource.includes('canAccessCampaign($campaign_id')
    && characterControllerSource.includes('throw new NotFoundHttpException();'),
  'Controller enforces campaign access and returns not-found for unknown runtime actors'
);

assert(
  projectorSource.includes("'route_name' => 'dungeoncrawler_content.actor_view'")
    && projectorSource.includes("'sheet_type' => 'actor'"),
  'Actor roster projector emits actor sheet_ref route to actor_view'
);

assert(
  panelSource.includes("if (routeName === 'dungeoncrawler_content.actor_view' && actorId)")
    && panelSource.includes('`/actors/${encodeURIComponent(actorId)}?campaign_id=${campaignId}`'),
  'CharacterPanel resolves actor sheet_ref into /actors/{actor_id} links with campaign_id'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

