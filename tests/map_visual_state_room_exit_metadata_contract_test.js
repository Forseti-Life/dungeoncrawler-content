/*
 * Contract test: projected room exits must preserve authored room-exit metadata.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const sourcePath = path.join(__dirname, '..', 'src', 'Service', 'MapVisualStateProjector.php');
  const source = fs.readFileSync(sourcePath, 'utf8');

  assert(
    source.includes('$authored_exit_index = $this->indexAuthoredRoomExits($rooms);'),
    'MapVisualStateProjector should index authored room exits before rebuilding projected exits',
  );

  assert(
    source.includes('protected function indexAuthoredRoomExits(array $rooms): array'),
    'MapVisualStateProjector should expose an authored-exit indexing helper',
  );

  assert(
    source.includes('protected function mergeAuthoredExitMetadata(string $source_room_id, array $projected_exit, array $authored_exit_index): array'),
    'MapVisualStateProjector should expose a helper to merge authored room-exit metadata into projected exits',
  );

  assert(
    source.includes("foreach (['label', 'link_type'] as $field) {"),
    'Projected room exits should preserve authored label and link_type metadata',
  );

  console.log('OK map visual state room exit metadata contract');
})();
