/**
 * @file
 * Focused contract checks for Storyline Builder object headlines.
 *
 * Run with: node tests/storyline_builder_object_headline_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const script = fs.readFileSync(path.resolve(__dirname, '../js/gm-storyline-builder.js'), 'utf8');
const stylesheet = fs.readFileSync(path.resolve(__dirname, '../css/gm-storyline-builder.css'), 'utf8');

let failed = 0;
function assert(condition, message) {
  if (condition) {
    console.log(`  ✓ ${message}`);
    return;
  }
  failed++;
  console.error(`  ✗ ${message}`);
}

console.log('\n=== Storyline Builder object headlines ===');
assert(
  script.includes('institution_entries:')
    && script.includes("label: 'Institution'")
    && script.includes("category: 'domain'"),
  'institution headlines include the institution name and kind',
);
assert(
  [
    'journal_entries',
    'conflict_entries',
    'task_entries',
    'branch_choice_entries',
    'objective_entries',
    'institution_entries',
    'actor_entries',
    'dungeon_entries',
    'room_entries',
    'item_entries',
  ].every((collection) => script.includes(`${collection}:`)),
  'every primary storyline object step has a headline contract',
);
assert(
  script.includes('updateCollectionRowHeadline(collection, row)')
    && script.includes('updateCollectionRowHeadline(collection, row);')
    && script.includes("wizard.addEventListener('change'"),
  'row headlines initialize for loaded/new rows and refresh after edits',
);
assert(
  stylesheet.includes('.gm-storyline-wizard__row-headline')
    && stylesheet.includes('.gm-storyline-wizard__row-name')
    && stylesheet.includes('.gm-storyline-wizard__row-category'),
  'headline name and category receive distinct visual hierarchy',
);
assert(
  stylesheet.includes('data-collection="institution_entries"')
    && stylesheet.includes('.gm-storyline-wizard__rows > .gm-storyline-wizard__row'),
  'object rows have visible card separation with stronger institution grouping',
);

process.exitCode = failed ? 1 : 0;
