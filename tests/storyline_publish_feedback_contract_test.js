/**
 * @file
 * Focused publish feedback contract for the Storyline Builder.
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

console.log('\n=== Storyline publication feedback ===');
assert(
  script.includes('setPublishBusy(true)')
    && script.includes('Publishing revision ${draftRevision}')
    && script.includes("publishBtn.setAttribute('aria-busy', busy ? 'true' : 'false')"),
  'publish click immediately exposes a busy state and revision-specific feedback',
);
assert(
  script.includes("setPublishStatus(`Published ${templateId}. Final signoff recorded")
    && script.includes('await loadDraftById(draftId)')
    && script.includes('setPublishBusy(false)'),
  'success reloads the published draft, confirms signoff, and restores controls',
);
assert(
  script.includes("setPublishStatus(`Publication failed: ${error.message}`, 'error')")
    && script.includes('Publication stopped: validation found'),
  'publication and validation failures are visible and distinct',
);
assert(
  stylesheet.includes('.gm-storyline-wizard__publish-status[data-state="working"]')
    && stylesheet.includes('.gm-storyline-wizard__publish-status[data-state="success"]')
    && stylesheet.includes('.gm-storyline-wizard__publish-status[data-state="error"]'),
  'working, success, and error feedback have distinct visual treatment',
);

process.exitCode = failed ? 1 : 0;
