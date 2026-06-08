/**
 * @file
 * Contract tests for quest journal payload wiring and action-bar description defaults.
 *
 * Run with:
 *   node tests/quest_journal_event_contract_test.js
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

const questSystemSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/systems/QuestSystem.js'), 'utf8');
const actionRailPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/ActionRailPanel.js'), 'utf8');
const hexmapSource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');

console.log('\n=== Quest journal and action-bar contracts ===');

assert(
  questSystemSource.includes("this.bus.emit('quest:progress-updated', {")
    && questSystemSource.includes('questSummary: this._buildQuestSummarySnapshot(overrideQuest)'),
  'QuestSystem emits quest:progress-updated with questSummary payload for QuestPanel'
);

assert(
  questSystemSource.includes("this.bus.emit('quest:completed', {")
    && questSystemSource.includes('questSummary: this._buildQuestSummarySnapshot(quest)'),
  'QuestSystem emits quest:completed with questSummary payload for QuestPanel'
);

assert(
  actionRailPanelSource.includes('this.actionRailDescriptionsCollapsed = true;')
    && hexmapSource.includes('this.actionRailDescriptionsCollapsed = true;'),
  'Action-bar descriptions default to hidden in v2 and monolith UI paths'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
