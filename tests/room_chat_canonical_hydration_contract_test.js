/**
 * @file
 * Contract coverage for room-chat canonical hydration.
 *
 * Run with:
 *   node tests/room_chat_canonical_hydration_contract_test.js
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

(function run() {
  const sourcePath = path.join(__dirname, '..', 'src', 'Service', 'RoomChatServiceGmPipelineTrait.php');
  const source = fs.readFileSync(sourcePath, 'utf8');

  assert(
    source.includes('$this->runtimeGraphAssembler->buildRuntimeGraph('),
    'room-chat snapshot hydration must use runtime graph assembler as the canonical authority',
  );

  assert(
    source.includes("'requested_room_id' => $requested_room_id"),
    'room-chat hydration must thread requested_room_id into runtime graph assembly',
  );

  assert(
    source.includes('Room chat hydration contract violation: campaign %d dungeon %s requested room %s was not materialized'),
    'room-chat loader must fail fast when requested room is absent from hydrated rooms[]',
  );

  assert(
    source.includes('!$allow_room_absent'),
    'requested room materialization invariant must be explicitly gated by allow_room_absent',
  );

  console.log('OK room chat canonical hydration contract');
})();
