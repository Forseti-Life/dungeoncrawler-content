/**
 * @file
 * Contract test: institution score integration across remaining consumers.
 *
 * Run with:
 *   node tests/institution_score_remaining_consumers_contract_test.js
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

const actorContextSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/ActorContextProjectionService.php'),
  'utf8',
);
const runtimeAssemblerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RuntimeStateReadModelAssembler.php'),
  'utf8',
);
const roomChatIntentSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatServiceIntentAndDeterminismTrait.php'),
  'utf8',
);
const encounterPhaseSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/EncounterPhaseHandler.php'),
  'utf8',
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8',
);

console.log('\n=== Institution score remaining consumers contract ===');

assert(
  actorContextSource.includes('InstitutionDispositionScoreAssemblerService')
    && actorContextSource.includes('buildActorTargetInstitutionAdjustment(')
    && actorContextSource.includes('resolveActorTargetDisposition(')
    && actorContextSource.includes("'institution_score' =>"),
  'ActorContextProjectionService injects institution assembler and applies per-target institution_score in resolver context'
);

assert(
  runtimeAssemblerSource.includes('InstitutionDispositionScoreAssemblerService')
    && runtimeAssemblerSource.includes('buildActorTargetInstitutionAdjustment(')
    && runtimeAssemblerSource.includes('resolveActorTargetDisposition(')
    && runtimeAssemblerSource.includes("'institution_score' =>"),
  'RuntimeStateReadModelAssembler applies centralized institution score when projecting resolved target disposition'
);

assert(
  roomChatIntentSource.includes('buildInstitutionAwareResolverContext(')
    && roomChatIntentSource.includes('institution_disposition_score_assembler')
    && roomChatIntentSource.includes('resolveActorTargetDisposition('),
  'Room chat disposition intent trait enriches resolver calls with centralized institutional context'
);

assert(
  encounterPhaseSource.includes('buildInstitutionAwareResolverContext(')
    && encounterPhaseSource.includes('institution_disposition_score_assembler')
    && encounterPhaseSource.includes('resolveActorTargetDisposition('),
  'Encounter phase hostility checks use centralized institutional context for resolver decisions'
);

assert(
  servicesSource.includes('dungeoncrawler_content.actor_context_projection_service:')
    && servicesSource.includes("- '@?dungeoncrawler_content.institution_disposition_score_assembler'"),
  'Service wiring injects institution score assembler into actor context projection service'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
