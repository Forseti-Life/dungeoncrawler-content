/**
 * @file
 * Contract checks for transcript projector extraction.
 *
 * Run with:
 *   node tests/gm_transcript_projector_contract_test.js
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

const projectorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmTranscriptProjector.php'),
  'utf8'
);
const roomChatSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);

console.log('\n=== GM transcript projector contract ===');

assert(
  projectorSource.includes('class GmTranscriptProjector'),
  'transcript projector service class exists'
);
assert(
  projectorSource.includes('public function project(')
    && projectorSource.includes("'visible_gm_narrative' =>"),
  'projector exposes project() and returns visible_gm_narrative payload'
);
assert(
  projectorSource.includes('$build_visible_gm_narrative(')
    && projectorSource.includes('$build_encounter_prefix_for_speaker(')
    && projectorSource.includes('$prefix_encounter_chat_text('),
  'projector owns visibility and encounter-prefix projection callbacks'
);
assert(
  roomChatSource.includes('protected GmTranscriptProjector $gmTranscriptProjector;')
    && roomChatSource.includes('$this->gmTranscriptProjector = $gm_transcript_projector ?? new GmTranscriptProjector();'),
  'RoomChatService wires transcript projector dependency'
);
assert(
  roomChatSource.includes('$projection_result = $this->gmTranscriptProjector->project(')
    && roomChatSource.includes("[$this, 'buildVisibleGmNarrative']")
    && roomChatSource.includes("[$this, 'buildEncounterPrefixForSpeaker']")
    && roomChatSource.includes("[$this, 'prefixEncounterChatText']"),
  'generateGmReply delegates transcript projection to subsystem projector'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_transcript_projector:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_transcript_projector'"),
  'service container registers and injects transcript projector'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

