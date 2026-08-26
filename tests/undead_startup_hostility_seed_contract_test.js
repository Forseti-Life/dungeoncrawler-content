/**
 * @file
 * Contract test: undead starter NPCs seed hostile institution sentiment.
 *
 * Run with:
 *   node tests/undead_startup_hostility_seed_contract_test.js
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

console.log('\n=== Undead startup hostility seed contract ===');

assert(
  source.includes("protected const STARTER_UNDEAD_HOSTILITY_SENTIMENT_SCORE = -100;")
    && source.includes("protected const STARTER_UNDEAD_HOSTILITY_PROFILE_KEY = 'starter-undead-hostility-default';"),
  'Service defines explicit undead starter hostility seed constants'
);

assert(
  source.includes('isHostileUndeadNpcSentimentSource(')
    && source.includes("if ($subject_id === 'institution_ancestry_undead')"),
  'Undead hostility seeding activates only for undead NPC sentiment sources'
);

assert(
  source.includes('shouldApplyUndeadHostilitySeedBias(')
    && source.includes("$sentiment_domain === 'ancestry'")
    && source.includes("$sentiment_domain === 'class'")
    && source.includes("str_starts_with($target_id, 'institution_profession_')"),
  'Undead hostility seed bias applies only to ancestry/class institutional sentiment targets'
);

assert(
  source.includes('$score = self::STARTER_UNDEAD_HOSTILITY_SENTIMENT_SCORE;')
    && source.includes("$knowledge_state = 'known';")
    && source.includes('$profile_key = self::STARTER_UNDEAD_HOSTILITY_PROFILE_KEY;'),
  'Undead startup bias writes deterministic hostile known sentiment seed rows'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
