/**
 * @file
 * Contract test: room-scene hostility RCA capture helper shape.
 *
 * Run with:
 *   node tests/room_scene_hostility_rca_capture_contract_test.js
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
  path.resolve(__dirname, '../scripts/room-scene-hostility-rca.py'),
  'utf8'
);

console.log('\n=== Room-scene hostility RCA capture contract ===');

assert(
  source.includes('OUTPUT_ROOT = REPO_ROOT / "tmp" / "room-scene-hostility-rca"')
    && source.includes('def collect_snapshot(')
    && source.includes("'runtime_state'")
    && source.includes("'hostile_relationships'")
    && source.includes("'recent_events'"),
  'RCA helper captures deterministic runtime, relationship, and event sections'
);

assert(
  source.includes('"campaign_id":')
    && source.includes('"mode":')
    && source.includes('"room_id":')
    && source.includes('"encounter_id":')
    && source.includes('"recent_event_types":')
    && source.includes('"hostile_relationship_count":'),
  'RCA helper summary includes required incident triage fields'
);

assert(
  source.includes('summary_md_path')
    && source.includes('snapshot_path')
    && source.includes('output_dir'),
  'RCA helper emits deterministic artifact paths for QA/PM attachment'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
