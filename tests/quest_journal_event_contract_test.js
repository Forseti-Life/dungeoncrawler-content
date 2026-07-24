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
const questPanelSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/QuestPanel.js'), 'utf8');
const hexmapSource = fs.readFileSync(path.resolve(__dirname, '../js/hexmap.js'), 'utf8');
const gameShellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');

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

assert(
  questSystemSource.includes('return String(quest.quest_id || quest.quest_key || quest.id || \'\').trim();'),
  'QuestSystem resolves quest identity using quest_id/quest_key/id fallback'
);

assert(
  gameShellSource.includes("import { QuestSystem } from './systems/QuestSystem.js?v=20260608-v2-quest-summary-merge-2';")
    && gameShellSource.includes("import { QuestPanel } from './panels/QuestPanel.js?v=20260723-v2-quest-storyline-grouping-2';"),
  'GameShell uses a cache-busted QuestSystem import so refactors load immediately'
);

assert(
  questPanelSource.includes('renderStorylineFirstJournal(')
    && questPanelSource.includes('buildStorylineContextIndexes(')
    && questPanelSource.includes('quest-entry quest-entry--storyline'),
  'QuestPanel groups quest entries under storyline parent nodes in the character journal'
);

console.log('\n=============================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
