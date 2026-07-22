/*
 * Contract test: connected-room chat prefetch must propagate map_id so the
 * room chat API can resolve the authoritative dungeon without scanning all
 * campaign dungeon blobs.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const chatPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ChatPanel.js'), 'utf8');
  const gameShellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');

  assert(
    chatPanelSource.includes("const context = { campaignId, roomId, characterId, mapId };"),
    'ChatPanel connected-room prefetch should include mapId in chat history context',
  );

  assert(
    gameShellSource.includes("const context = { campaignId, roomId, characterId, mapId };"),
    'GameShell connected-room prefetch should include mapId in chat history context',
  );

  console.log('OK connected room chat prefetch contract');
})();
