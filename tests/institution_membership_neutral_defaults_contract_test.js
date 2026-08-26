/**
 * @file
 * Contract test: institutional defaults include ancestry/profession presence
 * and neutral seeded sentiment posture.
 *
 * Run with:
 *   node tests/institution_membership_neutral_defaults_contract_test.js
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
  path.resolve(__dirname, '../src/Service/InstitutionMembershipService.php'),
  'utf8'
);

console.log('\n=== Institution membership neutral-default contract ===');

assert(
  source.includes("protected const DEFAULT_ANCESTRY_LABEL = 'Unknown Ancestry';")
    && source.includes("protected const DEFAULT_PROFESSION_LABEL = 'Unknown Profession';")
    && source.includes("$inputs[] = $this->buildAncestryInstitutionInput($character_data, $seed_source);")
    && source.includes("$inputs[] = $this->buildAncestryInstitutionInput($npc_data, $seed_source);"),
  'Ancestry/profession defaults are always represented in baseline institution inputs'
);

assert(
  source.includes("'ancestry_default'")
    && source.includes("'profession_default'")
    && source.includes('return self::DEFAULT_PROFESSION_LABEL;'),
  'Missing ancestry/profession payloads fall back to explicit default seed metadata'
);

assert(
  source.includes('$score = 0;')
    && source.includes("? 'known-neutral-default'")
    && source.includes(": 'unknown-neutral-default';")
    && source.includes('STARTER_UNDEAD_HOSTILITY_PROFILE_KEY')
    && source.includes('isHostileUndeadNpcSentimentSource(')
    && source.includes("in_array(($state['seed_profile_key'] ?? ''), ['membership-self-default', 'known-neutral-default', 'unknown-neutral-default', self::STARTER_UNDEAD_HOSTILITY_PROFILE_KEY], TRUE);"),
  'Seeded institution sentiment defaults to neutral for known/unknown peers, with explicit hostile-undead bootstrap override support'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
