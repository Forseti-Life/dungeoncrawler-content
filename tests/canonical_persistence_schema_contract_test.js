/**
 * @file
 * Contract test: canonical persistence cutover tables and update hook exist.
 *
 * Run with:
 *   node tests/canonical_persistence_schema_contract_test.js
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
  path.resolve(__dirname, '../dungeoncrawler_content.install'),
  'utf8',
);

console.log('\n=== Canonical persistence schema contract ===');

for (const tableName of [
  'dc_aggression_events',
  'dc_aggression_state',
  'dc_disposition_events',
  'dc_disposition_state',
  'dc_relationship_attitude_events',
  'dc_relationship_attitude_state',
  'dc_stance_events',
  'dc_stance_state',
]) {
  assert(source.includes(`$schema['${tableName}'] = [`), `Schema defines ${tableName}`);
}

assert(
  source.includes('function dungeoncrawler_content_update_10188()')
    && source.includes("$target_tables = [")
    && source.includes("'dc_relationship_attitude_state'")
    && source.includes('$schema_handler->createTable($table_name, $schema[$table_name]);'),
  'Update hook 10188 creates canonical persistence tables when missing'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}

