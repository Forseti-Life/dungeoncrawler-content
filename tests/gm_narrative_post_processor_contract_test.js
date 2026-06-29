/**
 * @file
 * Contract checks for GM narrative post-processor extraction.
 *
 * Run with:
 *   node tests/gm_narrative_post_processor_contract_test.js
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

const processorSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/GmNarrativePostProcessor.php'),
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

console.log('\n=== GM narrative post-processor contract ===');

assert(
  processorSource.includes('class GmNarrativePostProcessor'),
  'narrative post-processor service class exists'
);
assert(
  processorSource.includes('public function process(')
    && processorSource.includes("'narrative' => $narrative"),
  'post-processor exposes process() and returns normalized narrative'
);
assert(
  processorSource.includes('createBacklogSuggestion(')
    && processorSource.includes('strip_player_visible_action_blocks')
    && processorSource.includes("cache('default')->set"),
  'post-processor owns suggestion-tag side effects, narrative cleanup, and cache writeback policy'
);
assert(
  roomChatSource.includes('protected GmNarrativePostProcessor $gmNarrativePostProcessor;')
    && roomChatSource.includes('$this->gmNarrativePostProcessor = $gm_narrative_post_processor ?? new GmNarrativePostProcessor($ai_api_service);'),
  'RoomChatService wires narrative post-processor dependency'
);
assert(
  roomChatSource.includes('$post_process_result = $this->gmNarrativePostProcessor->process(')
    && roomChatSource.includes("[$this, 'stripPlayerVisibleActionBlocks']")
    && roomChatSource.includes("[$this, 'sanitizePlayerVisibleNarrative']"),
  'generateGmReply delegates narrative post-processing to subsystem service'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_narrative_post_processor:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_narrative_post_processor'"),
  'service container registers and injects narrative post-processor'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

