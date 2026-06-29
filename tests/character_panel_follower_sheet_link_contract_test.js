/**
 * @file
 * Contract checks for CharacterPanel full-sheet link routing.
 *
 * Run with:
 *   node tests/character_panel_follower_sheet_link_contract_test.js
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

const source = fs.readFileSync(path.resolve(__dirname, '../js/v2/panels/CharacterPanel.js'), 'utf8');
const sheetTemplate = fs.readFileSync(path.resolve(__dirname, '../templates/character-sheet.html.twig'), 'utf8');

console.log('\n=== CharacterPanel follower sheet link contract ===');

assert(
  source.includes('resolveEntityFollowerKind(entity = null) {')
    && source.includes('metadata?.follower_kind')
    && source.includes('metadata?.bond_contract?.follower_kind')
    && source.includes("if (entityRef.startsWith('familiar-'))")
    && source.includes('|| entity?.dcStatePayload?.role')
    && source.includes('|| entity?.dcStatePayload?.content_id'),
  'CharacterPanel resolves follower kind from metadata plus runtime role/ref fallbacks'
);

assert(
  source.includes('buildCharacterSheetHref(characterId, followerKind = \'\') {')
    && source.includes('`/characters/${normalizedCharacterId}/followers/${encodeURIComponent(normalizedFollowerKind)}`')
    && source.includes('return `${basePath}?campaign_id=${campaignId}`;'),
  'CharacterPanel builds follower sheet links with campaign query context'
);

assert(
  source.includes('resolveSheetHrefForEntity(entity = null) {')
    && source.includes('const selectedFollowerKind = this.resolveEntityFollowerKind(entity);')
    && source.includes('const selectedFollowerOwnerId = this.resolveEntityOwnerCharacterId(entity);')
    && source.includes('return this.buildCharacterSheetHref(resolvedCharacterId, selectedFollowerKind);'),
  'CharacterPanel points full-sheet link at follower sheet route when a follower actor is selected'
);

assert(
  source.includes('const refMatch = entityRef.match(/^(?:familiar|follower|companion)-(\\d+)$/i);'),
  'CharacterPanel derives follower owner ID from runtime entity reference when metadata is absent'
);

assert(
  sheetTemplate.includes("path('dungeoncrawler_content.character_follower_view', follower_route_params, follower_route_options)")
    && sheetTemplate.includes("{'query': {'campaign_id': campaign_id}}"),
  'character sheet follower links preserve campaign context when present'
);

console.log('\n===================================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
}
console.log('ALL TESTS PASSED');
