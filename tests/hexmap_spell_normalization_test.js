/**
 * @file
 * Lightweight regression tests for hexmap spell normalization helpers.
 *
 * Run with:
 *   node tests/hexmap_spell_normalization_test.js
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

function extractFunctionSource(source, signature) {
  const start = source.indexOf(signature);
  if (start === -1) {
    throw new Error(`Could not find function signature: ${signature}`);
  }

  let braceStart = -1;
  let parenDepth = 0;
  for (let index = start; index < source.length; index++) {
    const char = source[index];
    if (char === '(') {
      parenDepth++;
    } else if (char === ')') {
      parenDepth = Math.max(0, parenDepth - 1);
    } else if (char === '{' && parenDepth === 0) {
      braceStart = index;
      break;
    }
  }
  if (braceStart === -1) {
    throw new Error(`Could not find opening brace for: ${signature}`);
  }

  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        return source.slice(start, index + 1);
      }
    }
  }

  throw new Error(`Could not find closing brace for: ${signature}`);
}

const sourcePath = path.resolve(__dirname, '../js/hexmap.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const getSpellRankNumberSource = extractFunctionSource(source, 'function getSpellRankNumber(rankKey) {');
const formatOrdinalRankSource = extractFunctionSource(source, 'function formatOrdinalRank(rank) {');
const formatSpellRankLabelSource = extractFunctionSource(source, 'function formatSpellRankLabel(rankOrKey, { longForm = false } = {}) {');
const collectSpellRankGroupsSource = extractFunctionSource(source, 'function collectSpellRankGroups(spells) {');
const normalizeSpellcastingDataSource = extractFunctionSource(source, 'function normalizeSpellcastingData(spells, ...sources) {');
const factory = new Function(`${getSpellRankNumberSource}\n${formatOrdinalRankSource}\n${formatSpellRankLabelSource}\n${collectSpellRankGroupsSource}\n${normalizeSpellcastingDataSource}\nreturn { getSpellRankNumber, collectSpellRankGroups, normalizeSpellcastingData };`);
const methods = factory();

console.log('\n=== Hexmap spell normalization ===');

{
  const normalized = methods.normalizeSpellcastingData(
    {
      tradition: 'arcane',
      casting_ability: 'intelligence',
      cantrips: ['detect-magic'],
      slots: { cantrips: 5, first: 2 },
    },
    {
      spellbook: {
        1: ['magic-missile', 'grease'],
      },
    }
  );
  const groups = methods.collectSpellRankGroups(normalized);
  const cantripGroup = groups.find((group) => group.rank === 0);
  const firstRankGroup = groups.find((group) => group.rank === 1);

  assert(Boolean(cantripGroup), 'Keeps cantrip group');
  assert(Boolean(firstRankGroup), 'Builds a first-rank group from legacy spellbook data');
  assert(firstRankGroup && firstRankGroup.spells.includes('magic-missile'), 'Includes legacy spellbook entries in first-rank group');
  assert(firstRankGroup && firstRankGroup.spells.includes('grease'), 'Includes all legacy spellbook entries');
}

{
  const normalized = methods.normalizeSpellcastingData(
    {
      tradition: 'arcane',
      casting_ability: 'intelligence',
      cantrips: ['light'],
      slots: { cantrips: 5, first: 2 },
    },
    {
      spellbook: ['magic-weapon', 'mage-armor'],
    }
  );
  const groups = methods.collectSpellRankGroups(normalized);
  const firstRankGroup = groups.find((group) => group.rank === 1);

  assert(Boolean(firstRankGroup), 'Treats flat legacy spellbook arrays as first-rank spells');
  assert(firstRankGroup && firstRankGroup.spells.includes('magic-weapon'), 'Maps flat legacy spellbook entries into the first-rank group');
  assert(firstRankGroup && firstRankGroup.spells.includes('mage-armor'), 'Maps every flat legacy spellbook entry');
}

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
