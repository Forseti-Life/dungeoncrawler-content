'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const definitionForm = read('src/Form/SchemaDrivenDefinitionForm.php');
const roomTemplate = read('templates/room-editor.html.twig');
const dungeonTemplate = read('templates/dungeon-editor.html.twig');
const actorTemplate = read('templates/character-sheet.html.twig');
const storylineController = read('src/Controller/GmStorylineBuilderController.php');
const storylineJs = read('js/gm-storyline-builder.js');
const objectImageJs = read('js/object-image-panel.js');
const objectImageService = read('src/Service/ObjectImageService.php');
const storylineTemplate = read('templates/storyline-management-list.html.twig');
const storylineCss = read('css/storyline-management.css');
const libraries = read('dungeoncrawler_content.libraries.yml');
const storylineManagementController = read('src/Controller/StorylineManagementController.php');

assert(definitionForm.includes('data-object-image-panel'), 'canonical definitions expose the shared image panel');
assert(definitionForm.includes('dungeoncrawler_content.object_image'), 'canonical definitions call the generic object image API');
for (const family of ['item', 'obstacle', 'trap', 'hazard']) {
  assert(objectImageService.includes(`'${family}'`), `${family} definitions use the generic image service`);
}
assert(definitionForm.match(/'generate' => \[[\s\S]*?'#tag' => 'button'[\s\S]*?'type' => 'button'/), 'generate does not submit the definition form');
assert(definitionForm.match(/'promote' => \[[\s\S]*?'#tag' => 'button'[\s\S]*?'type' => 'button'/), 'promotion does not submit the definition form');
assert(objectImageJs.match(/event\.preventDefault\(\);[\s\S]*?request\(panel, true\)/), 'generation cancels any enclosing form submission');
assert(roomTemplate.includes('data-room-editor-place-version'), 'room editor keeps image version history');
assert(dungeonTemplate.includes('data-dungeon-editor-place-version'), 'dungeon editor keeps image version history');
assert(roomTemplate.includes('data-room-editor-terrain-image-panel'), 'terrain generation is available from the room editor');
assert(actorTemplate.includes('data-dc-portrait-version'), 'actor pages expose portrait version history');
assert(roomTemplate.includes('data-room-editor-place-promote'), 'room editor can roll an older image forward');
assert(dungeonTemplate.includes('data-dungeon-editor-place-promote'), 'dungeon editor can roll an older image forward');
assert(storylineController.includes('data-storyline-image-panel'), 'storyline builder exposes a cover image panel');
assert(storylineJs.includes("replace('{kind}', 'storyline')"), 'storyline covers use the generic object image endpoint');
assert(storylineJs.match(/fetchCsrfToken\(\)\s*\.then\(\(token\) => \{\s*csrfToken = token;/), 'storyline cover generation retains the CSRF token');
assert(storylineJs.includes('[data-institution-image-version]'), 'institution rows expose image version history');
assert(/gm-storyline-builder:[\s\S]*?dungeoncrawler_content\/object-image-panel/.test(libraries), 'storyline builder loads the shared cover image behavior');
assert(storylineTemplate.includes('dc-character-card--has-cover'), 'storyline manager cards apply cover backgrounds');
assert(storylineCss.includes('var(--storyline-cover-image)'), 'storyline manager cards render cover images as backgrounds');
assert(storylineManagementController.includes("$latest_draft['draft_id'] ?? $latest_status_draft['draft_id']"), 'storyline cards load the cover from the draft their edit action opens');

console.log('=== Object image editor parity ===\nAll object image editor parity assertions passed.');
