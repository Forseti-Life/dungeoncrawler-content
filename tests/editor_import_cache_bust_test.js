/**
 * @file
 * Contract test for editor ES module cache-busting stamps.
 *
 * Run with:
 *   node tests/editor_import_cache_bust_test.js
 */

const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..');
const editorChains = {
  'dungeon-room': [
    'js/dungeon-editor.js',
    'js/room-editor.js',
    'js/v2/editor/DungeonEditorShell.js',
    'js/v2/editor/RoomEditorShell.js',
    'js/v2/canvas/HexCanvas.js',
    'js/v2/editor/placementTransform.js',
    'js/v2/GameEventBus.js',
  ],
  definition: [
    'js/definition-editor.js',
    'js/v2/editor/DefinitionEditorShell.js',
  ],
};

const relativeImportRe = /^\s*import\s+(?:(?:[\s\S]*?\s+from\s+)?['"])(\.\.?\/[^'"]+\.js(?:\?v=([^'"]+))?)['"]/gm;
let failures = 0;

function fail(message) {
  failures += 1;
  console.error(`  ✗ ${message}`);
}

function pass(message) {
  console.log(`  ✓ ${message}`);
}

console.log('\n=== editor import cache busting ===');
for (const [chain, files] of Object.entries(editorChains)) {
  const stamps = new Set();
  for (const rel of files) {
    const src = fs.readFileSync(path.join(repoRoot, rel), 'utf8');
    let match;
    let imports = 0;
    relativeImportRe.lastIndex = 0;
    while ((match = relativeImportRe.exec(src)) !== null) {
      imports += 1;
      const specifier = match[1];
      const stamp = match[2];
      if (!stamp) {
        fail(`${rel} imports ${specifier} without ?v=`);
        continue;
      }
      stamps.add(stamp);
      pass(`${rel} imports ${specifier}`);
    }
    if (imports === 0) {
      pass(`${rel} has no relative static imports`);
    }
  }

  if (stamps.size === 0) {
    fail(`${chain} editor chain has no cache-busted relative imports`);
  } else if (stamps.size !== 1) {
    fail(`${chain} editor-chain import stamps must be identical; found: ${Array.from(stamps).join(', ')}`);
  } else {
    pass(`${chain} editor-chain stamp is ${Array.from(stamps)[0]}`);
  }
}

if (failures > 0) {
  console.error(`\n${failures} editor import cache-busting assertion(s) failed.`);
  process.exit(1);
}

console.log('\nAll editor import cache-busting assertions passed.');
