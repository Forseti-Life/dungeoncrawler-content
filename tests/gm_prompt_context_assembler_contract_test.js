/**
 * @file
 * Contract checks for prompt-context assembly extraction from generateGmReply().
 *
 * Run with:
 *   node tests/gm_prompt_context_assembler_contract_test.js
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

const assemblerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/PromptContextAssembler.php'),
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

console.log('\n=== GM prompt-context assembler contract ===');

assert(
  assemblerSource.includes('class PromptContextAssembler'),
  'prompt-context assembler service class exists'
);
assert(
  assemblerSource.includes('public function assemble(array $input): array'),
  'assembler exposes a stable assemble() contract'
);
assert(
  assemblerSource.includes("'prompt' => $prompt")
    && assemblerSource.includes("'debug_meta' => ["),
  'assembler returns prompt and debug metadata'
);
assert(
  roomChatSource.includes('protected PromptContextAssembler $promptContextAssembler;')
    && roomChatSource.includes('$this->promptContextAssembler = $prompt_context_assembler ?? new PromptContextAssembler();'),
  'RoomChatService wires assembler dependency with fallback construction'
);
assert(
  roomChatSource.includes('$prompt_assembly = $this->promptContextAssembler->assemble([')
    && roomChatSource.includes("$this->recordDebugStage('gm.user_prompt_assembly', $stage_started_at, $prompt_assembly['debug_meta'] ?? []);"),
  'generateGmReply uses assembled prompt payload for debug-stage metadata'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_prompt_context_assembler:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_prompt_context_assembler'"),
  'service container registers and injects prompt-context assembler'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

