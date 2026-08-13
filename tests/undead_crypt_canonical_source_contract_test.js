/**
 * Contract coverage for canonical undead-crypt starter room + actor sources.
 *
 * Run with:
 *   node tests/undead_crypt_canonical_source_contract_test.js
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

function read(relativePath) {
  return fs.readFileSync(path.resolve(__dirname, '..', relativePath), 'utf8');
}

function readJson(relativePath) {
  return JSON.parse(read(relativePath));
}

console.log('\n=== Undead crypt canonical source contract ===');

const roomTemplates = readJson('config/examples/templates/dungeoncrawler_content_rooms/default_room_templates.json');
const characterTemplates = readJson('config/examples/templates/dungeoncrawler_content_characters/default_character_templates.json');
const initService = read('src/Service/CampaignInitializationService.php');

const cryptRoom = (roomTemplates.rows || []).find((row) => row.room_id === 'tpl_room_crypt_anteroom') || null;
assert(!!cryptRoom, 'canonical room templates include tpl_room_crypt_anteroom');

assert(
  cryptRoom?.source_room_id === 'tpl_room_crypt_anteroom',
  'crypt starter room source_room_id is canonical and stable'
);

assert(
  Number(cryptRoom?.layout_data?.width) === 8 && Number(cryptRoom?.layout_data?.height) === 8,
  'crypt starter room dimensions are 8x8 (40x40 feet)'
);

assert(
  Array.isArray(cryptRoom?.layout_data?.hexes) && cryptRoom.layout_data.hexes.length >= 64,
  'crypt starter room includes authoritative 8x8 hex payload'
);

const hasWestEntry = Array.isArray(cryptRoom?.layout_data?.entry_points)
  && cryptRoom.layout_data.entry_points.some((entry) =>
    Number(entry?.q) === -4
    && Number(entry?.r) === 0
    && String(entry?.side || '').toLowerCase() === 'west'
  );
assert(hasWestEntry, 'crypt starter room includes required west entry point (-4,0)');

const cryptNpcs = Array.isArray(cryptRoom?.contents_data?.npcs) ? cryptRoom.contents_data.npcs : [];
const alpha = cryptNpcs.find((npc) => npc?.content_id === 'skeleton_guard_alpha');
const beta = cryptNpcs.find((npc) => npc?.content_id === 'skeleton_guard_beta');

assert(!!alpha && !!beta, 'crypt starter room contains canonical alpha/beta skeleton actor refs');

assert(
  alpha?.attitude === 'hostile'
    && Number(alpha?.position?.q) === 3
    && Number(alpha?.position?.r) === 2,
  'alpha skeleton is hostile and anchored at far-side position (3,2)'
);

assert(
  beta?.attitude === 'hostile'
    && Number(beta?.position?.q) === 2
    && Number(beta?.position?.r) === 3,
  'beta skeleton is hostile and anchored at far-side position (2,3)'
);

const characterRows = Array.isArray(characterTemplates.rows) ? characterTemplates.rows : [];
const alphaTemplate = characterRows.find((row) => row?.instance_id === 'skeleton_guard_alpha');
const betaTemplate = characterRows.find((row) => row?.instance_id === 'skeleton_guard_beta');

assert(!!alphaTemplate && !!betaTemplate, 'canonical character templates include skeleton_guard_alpha and skeleton_guard_beta');
assert(
  alphaTemplate?.location_ref === 'tpl_room_crypt_anteroom' && betaTemplate?.location_ref === 'tpl_room_crypt_anteroom',
  'canonical skeleton actor templates are bound to tpl_room_crypt_anteroom'
);

assert(
  initService.includes('is structurally incomplete for campaign bootstrap.'),
  'bootstrap hard-fails when canonical undead room exists but is structurally invalid'
);

assert(
  initService.includes('not found in dungeoncrawler_content_rooms; packaged JSON fallbacks are disabled.'),
  'bootstrap hard-fails when canonical undead room assets are missing'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
