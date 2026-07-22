/*
 * Contract test: room chat history snapshot loading must not fetch every
 * campaign dungeon_data blob up front for one room-history request.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const sourcePath = path.join(__dirname, '..', 'src', 'Service', 'RoomChatServiceGmPipelineTrait.php');
  const source = fs.readFileSync(sourcePath, 'utf8');

  assert(
    source.includes("->fields('d', ['dungeon_id', 'updated'])"),
    'fallback dungeon scan should fetch only dungeon_id and updated before loading any blob',
  );

  assert(
    source.includes("->condition('dungeon_id', $preferred_dungeon_id)"),
    'preferred_dungeon_id should short-circuit snapshot loading to the requested map first',
  );

  assert(
    !source.includes("->fields('d', ['dungeon_id', 'dungeon_data', 'updated'])\n      ->condition('campaign_id', $campaign_id)\n      ->orderBy('updated', 'DESC')\n      ->execute()\n      ->fetchAll"),
    'room chat history should not fetchAll full dungeon_data blobs for the whole campaign',
  );

  console.log('OK room chat history snapshot contract');
})();
