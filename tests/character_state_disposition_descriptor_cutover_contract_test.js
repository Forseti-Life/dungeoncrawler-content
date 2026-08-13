/**
 * @file
 * Contract test: character-state descriptor attitude should resolve from
 * canonical disposition authority before legacy descriptor payload values.
 *
 * Run with:
 *   node tests/character_state_disposition_descriptor_cutover_contract_test.js
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

const source = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/CharacterStateService.php'),
  'utf8',
);

console.log('\n=== Character state disposition descriptor cutover contract ===');

assert(
  source.includes('$state = $this->applyDispositionAttitudeToDescriptors($state);'),
  'CharacterStateService applies descriptor attitude cutover during state assembly'
);

assert(
  source.includes('protected function applyDispositionAttitudeToDescriptors(array $state): array')
    && source.includes("!\\Drupal::hasService('dungeoncrawler_content.actor_disposition_service')")
    && source.includes('$summary = $service->getDispositionSummary($campaign_id, $entity_ref);')
    && source.includes("$descriptors['attitude'] = $attitude;"),
  'Descriptor attitude resolution is sourced from ActorDispositionService when campaign/entity context is available'
);

assert(
  source.includes("preg_match('/(?:^|\\. )Attitude:\\s*[^.]+(?:\\.|$)/', $descriptor_summary)")
    && source.includes("preg_replace('/Attitude:\\s*[^.]+/', 'Attitude: ' . $attitude, $descriptor_summary)")
    && source.includes("$descriptor_summary .= '. Attitude: ' . $attitude;"),
  'Descriptor summary text updates or appends Attitude from canonical disposition source'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
