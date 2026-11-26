/**
 * @file
 * Focused contracts for Storyline actor portraits and room/dungeon previews.
 */

const fs = require('fs');
const path = require('path');

const graph = fs.readFileSync(path.resolve(__dirname, '../js/gm-storyline-actor-graph.js'), 'utf8');
const builder = fs.readFileSync(path.resolve(__dirname, '../js/gm-storyline-builder.js'), 'utf8');
const graphCss = fs.readFileSync(path.resolve(__dirname, '../css/gm-storyline-actor-graph.css'), 'utf8');
const builderCss = fs.readFileSync(path.resolve(__dirname, '../css/gm-storyline-builder.css'), 'utf8');

let failed = 0;
function assert(condition, message) {
  if (condition) console.log(`  ✓ ${message}`);
  else {
    failed++;
    console.error(`  ✗ ${message}`);
  }
}

console.log('\n=== Storyline object previews ===');
assert(
  graph.includes('actor.portrait_url')
    && graph.includes('Generate portrait')
    && graph.includes('Regenerate portrait')
    && graph.includes('portraitUrlTemplate'),
  'actor graph renders an existing portrait or placeholder and exposes generation',
);
assert(
  graph.includes('Waiting on image provider…')
    && graph.includes('Portrait generation failed:'),
  'portrait generation has explicit waiting and failure feedback',
);
assert(
  graphCss.includes('.actor-graph__portrait')
    && graphCss.includes('.actor-graph__node-portrait-placeholder'),
  'actor portraits use dedicated graph and panel styling',
);
assert(
  builder.includes("['dungeon_entries', 'room_entries']")
    && builder.includes('Generate / Regenerate in Dungeon Editor')
    && builder.includes('Generate / Regenerate in Room Editor')
    && builder.includes('objectEditorTemplates[kind]'),
  'room and dungeon rows expose preview space plus generation/editor handoff links',
);
assert(
  builderCss.includes('.gm-storyline-wizard__object-media')
    && builderCss.includes('.gm-storyline-wizard__object-preview'),
  'place previews and editor actions have dedicated responsive styling',
);

process.exitCode = failed ? 1 : 0;
