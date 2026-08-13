/**
 * @file
 * Contract coverage for GM tool execution semantics and authority guards.
 *
 * Run with:
 *   node tests/gm_tool_execution_contract_test.js
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
  path.resolve(__dirname, '../src/Service/GmToolExecutionService.php'),
  'utf8'
);

console.log('\n=== GM tool execution contract ===');

assert(
  source.includes('Unsupported campaign table operation')
    && source.includes("['update', 'insert', 'upsert', 'delete']"),
  'GM tool contract only allows update/insert/upsert/delete operations'
);
assert(
  source.includes('Campaign table insert requires non-empty fields object.')
    && source.includes('Campaign table update requires non-empty fields object.')
    && source.includes('Campaign table upsert requires non-empty keys object.')
    && source.includes('Campaign table mutation requires non-empty where object.'),
  'GM table operations enforce strict operation-specific payload requirements'
);
assert(
  source.includes('Expected exactly 1 row update')
    && source.includes('Expected exactly 1 row delete'),
  'GM table operations fail hard when row-count invariants are violated'
);
assert(
  source.includes('GM tool execution requires actor_role=gm.')
    && source.includes('requires gm_actor_id and gm_character_id principal context')
    && source.includes('principal binding failed')
    && source.includes('current user lacks campaign GM capability')
    && source.includes('gm_character_id is not an active campaign principal'),
  'GM tool execution enforces actor-role and server-side principal binding'
);
assert(
  source.includes('requires ownership_domain')
    && source.includes('ownership_domain=normalized_tables')
    && source.includes('ownership_domain=dungeon_blob')
    && source.includes('GM tools may only modify campaign-instance tables.')
    && source.includes('GM tools cannot modify canonical/library tables'),
  'GM tool execution enforces explicit data ownership domain contracts'
);
assert(
  source.includes("'modify_setting_variable'")
    && source.includes("'query_campaign_database'")
    && source.includes('query_campaign_database requires non-empty filters object.')
    && source.includes('unsupported filter field'),
  'GM tool execution includes setting-variable write and campaign-database query tools with strict filter validation'
);
assert(
  source.includes("insert('dc_gm_mutation_audit')")
    && source.includes('$transaction = $this->database->startTransaction();')
    && source.includes('$transaction->rollBack();')
    && source.includes('payload_hash')
    && source.includes('before_hash')
    && source.includes('after_hash'),
  'GM tool execution wraps privileged writes in transaction scope and persists immutable audit digests'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}
