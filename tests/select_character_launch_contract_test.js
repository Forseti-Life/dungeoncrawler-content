/*
 * Contract test: selectCharacter should not fetch or decode full dungeon_data
 * blobs just to build the hexmap launch redirect.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const sourcePath = path.join(__dirname, '..', 'src', 'Controller', 'CampaignController.php');
  const source = fs.readFileSync(sourcePath, 'utf8');

  assert(
    source.includes('$campaign_dungeon = $this->loadLatestCampaignDungeonSummary($campaign_id);'),
    'selectCharacter should use the lightweight latest dungeon summary loader',
  );

  assert(
    source.includes("->fields('d', ['dungeon_id'])"),
    'latest dungeon summary loader should fetch only dungeon_id from dc_campaign_dungeons',
  );

  assert(
    !source.includes("->fields('d', ['dungeon_id', 'dungeon_data'])"),
    'selectCharacter hot path should not fetch full dungeon_data blobs',
  );

  assert(
    !source.includes("$decoded = json_decode((string) ($campaign_dungeon->dungeon_data ?? '{}'), TRUE);"),
    'selectCharacter hot path should not decode full dungeon_data to build redirect query',
  );

  assert(
    !source.includes('$this->runtimeBootstrap->ensureRuntimeReady($campaign_id, $selected_row_id);'),
    'selectCharacter should not eagerly bootstrap full runtime dungeon state before redirecting to hexmap',
  );

  console.log('OK select character launch contract');
})();
