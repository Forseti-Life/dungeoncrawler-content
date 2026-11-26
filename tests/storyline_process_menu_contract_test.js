/**
 * @file
 * Focused contract checks for the combined Storyline process/state menu.
 *
 * Run with: node tests/storyline_process_menu_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const controller = fs.readFileSync(
  path.resolve(__dirname, '../src/Controller/GmStorylineBuilderController.php'),
  'utf8',
);
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

console.log('\n=== Storyline process and state menu ===');
assert(
  controller.includes("'{% block stages %}' . self::STATUS_CARD_MARKUP . self::STAGE_TABS_MARKUP")
    && controller.includes("'{% block state %}{% endblock %}'"),
  'current state and process tabs occupy the same sidebar card',
);
assert(
  controller.includes('gm-storyline-status--menu')
    && !controller.includes('character-setup-page__sidebar-card gm-storyline-status'),
  'the compact state summary does not introduce a second sidebar card',
);
assert(
  script.includes("status.setAttribute('data-step-tab-status', '')")
    && script.includes('tab.classList.toggle(\'is-complete\', complete)')
    && script.includes('Complete'),
  'each process menu item reflects its completion state',
);
assert(
  stylesheet.includes('.gm-storyline-status--menu')
    && stylesheet.includes('.character-setup-page__tab-status')
    && stylesheet.includes('.character-setup-page__tab.is-complete'),
  'combined state summary and per-step status have distinct menu styling',
);

process.exitCode = failed ? 1 : 0;
