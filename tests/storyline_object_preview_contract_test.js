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
const dungeonShell = fs.readFileSync(path.resolve(__dirname, '../js/v2/editor/DungeonEditorShell.js'), 'utf8');

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
  builder.includes("['actor_entries', 'dungeon_entries', 'room_entries', 'institution_entries']")
    && builder.includes('Generate / Regenerate in Dungeon Editor')
    && builder.includes('Generate / Regenerate in Room Editor')
    && builder.includes('objectEditorTemplates[kind]'),
  'room and dungeon rows expose preview space plus generation/editor handoff links',
);
assert(
  builder.includes("['actor_entries', 'dungeon_entries', 'room_entries', 'institution_entries']")
    && builder.includes('Waiting on image provider…')
    && builder.includes('source.portrait_url'),
  'main Builder Step 4 actor rows show portraits and Generate or Regenerate controls',
);
assert(
  builder.includes('objectImageUrlTemplate')
    && builder.includes("kind === 'institution'")
    && builder.includes('Institution image generation failed:'),
  'institution rows generate and display images through the shared storyline image endpoint',
);
assert(
  builderCss.includes('.gm-storyline-wizard__object-media')
    && builderCss.includes('.gm-storyline-wizard__object-preview'),
  'place previews and editor actions have dedicated responsive styling',
);
assert(
  dungeonShell.includes('function placementTint(index, count)')
    && dungeonShell.includes('placementTint(index, this.model.placements.length)')
    && dungeonShell.includes('placementTint(index, placements.length)'),
  'dungeon map and placement legend share a collision-free tint function',
);

process.exitCode = failed ? 1 : 0;
