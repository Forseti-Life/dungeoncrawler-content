/*
 * Contract test: HexMapController exposes lightweight graph-version confirmation
 * and includes version tokens in client bootstrap payloads.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const controllerSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Controller', 'HexMapController.php'),
    'utf8'
  );
  const routingSource = fs.readFileSync(
    path.join(__dirname, '..', 'dungeoncrawler_content.routing.yml'),
    'utf8'
  );

  assert(
    controllerSource.includes('public function graphVersion(): JsonResponse')
      && controllerSource.includes("$version_metadata = $this->graphVersionService->buildVersionMetadata($campaign_id, $dungeon_id);"),
    'HexMapController should expose a lightweight graph version confirmation endpoint backed by GraphVersionService',
  );

  assert(
    controllerSource.includes("'canonical_graph_version' => (string) ($dungeon_payload['canonical_graph_version'] ?? '')")
      && controllerSource.includes("'campaign_graph_version' => (string) ($dungeon_payload['campaign_graph_version'] ?? '')"),
    'HexMapController bootstrap payload should expose canonical and campaign graph version tokens',
  );

  assert(
    controllerSource.includes("'requires_reload' => !$is_current,"),
    'graph version confirmation response should tell the client when a reload is required',
  );

  assert(
    routingSource.includes("path: '/api/map/graph-version'")
      && routingSource.includes("HexMapController::graphVersion"),
    'routing should expose the graph version confirmation API endpoint',
  );

  console.log('OK graph version confirmation contract');
})();
