/**
 * @file
 * Contract checks for transcript persistence/session bridge pipeline extraction.
 *
 * Run with:
 *   node tests/gm_transcript_persistence_pipeline_contract_test.js
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

const pipelineSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmTranscriptPersistencePipeline.php'),
  'utf8'
);
const roomChatSource = require('./helpers/php-source.js').readGmPipelineSource();
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);

console.log('\n=== GM transcript persistence pipeline contract ===');

assert(
  pipelineSource.includes('class GmTranscriptPersistencePipeline'),
  'transcript persistence pipeline service class exists'
);
assert(
  pipelineSource.includes('public function persistVisibleReply(')
    && pipelineSource.includes("'gm_message' => $gm_message"),
  'pipeline exposes persistVisibleReply() and returns gm_message payload'
);
assert(
  pipelineSource.includes("update('dc_campaign_dungeons')")
    && pipelineSource.includes("appendMessage($session_key, $campaign_id, 'assistant', $visible_gm_narrative)")
    && pipelineSource.includes('$bridge_gm_reply_to_session_system('),
  'pipeline owns DB transcript persistence and session bridge side effects'
);
assert(
  roomChatSource.includes('protected GmTranscriptPersistencePipeline $gmTranscriptPersistencePipeline;')
    && roomChatSource.includes('$this->gmTranscriptPersistencePipeline = $gm_transcript_persistence_pipeline ?? new GmTranscriptPersistencePipeline($database, $session_manager);'),
  'RoomChatService wires transcript persistence pipeline dependency'
);
assert(
  roomChatSource.includes('$persistence_result = $this->gmTranscriptPersistencePipeline->persistVisibleReply(')
    && roomChatSource.includes('$callbacks->buildGmRoomResponsePayload(')
    && roomChatSource.includes('$callbacks->bridgeGmReplyToSessionSystem('),
  'generateGmReply delegates transcript persistence/session bridge through subsystem pipeline'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_transcript_persistence_pipeline:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_transcript_persistence_pipeline'"),
  'service container registers and injects transcript persistence pipeline'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

