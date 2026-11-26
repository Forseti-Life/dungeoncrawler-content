/**
 * @file
 * Focused source contract checks for the Site Guide launcher.
 *
 * Run with: node tests/site_guide_contract_test.js
 */

const fs = require('fs');
const path = require('path');

const script = fs.readFileSync(path.resolve(__dirname, '../js/site-guide.js'), 'utf8');
const template = fs.readFileSync(
  path.resolve(__dirname, '../../dungeoncrawler-theme/templates/page/page--front.html.twig'),
  'utf8',
);

let failed = 0;
function assert(condition, message) {
  if (condition) {
    console.log(`  ✓ ${message}`);
    return;
  }
  failed++;
  console.error(`  ✗ ${message}`);
}

console.log('\n=== Site Guide launcher contract ===');
assert(
  template.includes("attach_library('dungeoncrawler_content/site_guide')")
    && template.includes('data-site-guide-toggle')
    && template.includes('data-site-guide-form'),
  'homepage attaches and renders the Site Guide launcher and form',
);
assert(
  script.includes("fetch('/api/site-guide'")
    && script.includes("credentials: 'same-origin'")
    && script.includes("route: window.location.pathname + window.location.search"),
  'launcher discovers context and submits questions through the same-origin API',
);
assert(
  script.includes("event.key === 'Escape'")
    && script.includes("event.key !== 'Tab'")
    && script.includes('lastActiveElement.focus()'),
  'dialog supports Escape, focus trapping, and focus return',
);
assert(
  script.includes('send.disabled = true')
    && script.includes("Waiting on Site Guide…")
    && script.includes('send.disabled = false'),
  'request state visibly explains the server dependency',
);
assert(
  script.includes('data.handoff')
    && script.includes('site-guide__handoff')
    && script.includes('data.links || []'),
  'answers render permission-filtered links and owning-agent handoff cards',
);
assert(
  !script.includes('GM privileged tools'),
  'browser code contains no privileged tool execution path',
);

process.exitCode = failed ? 1 : 0;
