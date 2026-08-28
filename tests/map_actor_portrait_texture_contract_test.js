/**
 * @file
 * Contract test: map tokens must render the same actor art the chat tab shows.
 *
 * The chat tab reads `entity.state.metadata.portrait_url` directly, but the v2
 * map token renderer only draws a sprite when `PIXI.utils.TextureCache` holds an
 * entry keyed by the blueprint's `render.spriteKey`. Three links must hold for
 * the two tabs to agree:
 *
 *   1. Actors with a server-resolved portrait must get a sprite key.
 *   2. `_preloadSpriteUrls` must seed the portrait URL under that key.
 *   3. A texture that finishes loading asynchronously must notify listeners so
 *      the token layer can re-render instead of keeping its placeholder circle.
 *
 * These are executed against the real module sources, not asserted as text.
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const ROOT = path.resolve(__dirname, '..');

let passed = 0;
let failed = 0;

function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`  PASS: ${name}`);
  } catch (error) {
    failed += 1;
    console.error(`  FAIL: ${name}\n        ${error.message}`);
  }
}

// ---------------------------------------------------------------------------
// Load the projection helpers as a CommonJS-evaluable module.
// ---------------------------------------------------------------------------

function loadProjectionHelpers() {
  const source = fs.readFileSync(
    path.join(ROOT, 'js/v2/shell/GameShellProjectionHelpers.js'),
    'utf8',
  );
  const exportsCollected = {};
  // Strip ES module syntax so the file can be evaluated in a CommonJS sandbox.
  const transformed = source
    .replace(/^\s*import[^;]*;\s*$/gm, '')
    .replace(/^export function /gm, 'function ')
    .replace(/^export const /gm, 'const ')
    .replace(/^export class /gm, 'class ');

  const factory = new Function(
    '__collect',
    `${transformed}\n;__collect({ _buildActorPortraitSpriteId, _preloadSpriteUrls, _buildRenderableEntityBlueprints });`,
  );
  factory((collected) => Object.assign(exportsCollected, collected));
  return exportsCollected;
}

function loadSpriteService() {
  const source = fs.readFileSync(path.join(ROOT, 'js/SpriteService.js'), 'utf8');
  const transformed = source.replace(/^export class /gm, 'class ');
  const factory = new Function('PIXI', 'Image', `${transformed}\nreturn SpriteService;`);
  return factory;
}

const helpers = loadProjectionHelpers();
const spriteServiceFactory = loadSpriteService();

// ---------------------------------------------------------------------------
// 1. Sprite key derivation.
// ---------------------------------------------------------------------------

console.log('Map actor portrait texture contract');

check('player_character with a portrait_url gets a derived sprite key', () => {
  const key = helpers._buildActorPortraitSpriteId(
    'player_character',
    { portrait_url: '/sites/default/files/dc/portrait-burasco.png' },
    'pc-908-1033',
  );
  assert.strictEqual(key, 'portrait__pc-908-1033');
});

check('npc with a portrait_url gets a derived sprite key', () => {
  const key = helpers._buildActorPortraitSpriteId(
    'npc',
    { portrait: '/sites/default/files/dc/skeleton-alpha.png' },
    'npc_skeleton_guard_alpha',
  );
  assert.strictEqual(key, 'portrait__npc_skeleton_guard_alpha');
});

check('actor without a portrait gets no derived key (colored fallback stays correct)', () => {
  assert.strictEqual(helpers._buildActorPortraitSpriteId('npc', {}, 'npc_mimi'), '');
});

check('non-actor entity types never get a portrait sprite key', () => {
  assert.strictEqual(
    helpers._buildActorPortraitSpriteId('obstacle', { portrait_url: '/x.png' }, 'obs-1'),
    '',
  );
  assert.strictEqual(
    helpers._buildActorPortraitSpriteId('item', { portrait_url: '/x.png' }, 'item-1'),
    '',
  );
});

check('missing instance id yields no key rather than a colliding one', () => {
  assert.strictEqual(
    helpers._buildActorPortraitSpriteId('npc', { portrait_url: '/x.png' }, ''),
    '',
  );
});

// ---------------------------------------------------------------------------
// 2. Preload seeds the portrait URL under the token's sprite key.
// ---------------------------------------------------------------------------

check('_preloadSpriteUrls seeds a derived portrait key with the portrait URL', () => {
  const seeded = {};
  const spriteService = { preloadUrl: (id, url) => { seeded[id] = url; } };
  helpers._preloadSpriteUrls(
    spriteService,
    [{
      contentId: 'skeleton_guard',
      render: {
        spriteKey: 'portrait__npc_skeleton_guard_alpha',
        portraitUrl: '/sites/default/files/dc/skeleton-alpha.png',
      },
    }],
    {},
    null,
  );
  assert.strictEqual(
    seeded['portrait__npc_skeleton_guard_alpha'],
    '/sites/default/files/dc/skeleton-alpha.png',
  );
});

check('a server-resolved portrait outranks generic object-definition artwork', () => {
  const seeded = {};
  const spriteService = { preloadUrl: (id, url) => { seeded[id] = url; } };
  helpers._preloadSpriteUrls(
    spriteService,
    [{
      contentId: 'skeleton_guard',
      render: { spriteKey: 'skeleton_guard', portraitUrl: '/dc/portrait.png' },
    }],
    { skeleton_guard: { visual: { image_url: '/dc/generic-furniture.png' } } },
    null,
  );
  assert.strictEqual(seeded.skeleton_guard, '/dc/portrait.png');
});

check('object-definition artwork is still used when no portrait exists', () => {
  const seeded = {};
  const spriteService = { preloadUrl: (id, url) => { seeded[id] = url; } };
  helpers._preloadSpriteUrls(
    spriteService,
    [{ contentId: 'crate', render: { spriteKey: 'crate', portraitUrl: null } }],
    { crate: { visual: { image_url: '/dc/crate.png' } } },
    null,
  );
  assert.strictEqual(seeded.crate, '/dc/crate.png');
});

// ---------------------------------------------------------------------------
// 3. Blueprint carries the portrait through to render.
// ---------------------------------------------------------------------------

function buildDungeonWithActor(metadata) {
  return {
    object_definitions: {},
    rooms: [{ room_id: 'room-1', width: 10, height: 10 }],
    entities: [{
      instance_id: 'npc_skeleton_guard_alpha',
      entity_type: 'npc',
      entity_ref: { content_id: 'skeleton_guard' },
      placement: { room_id: 'room-1', hex: { q: 1, r: 1 } },
      state: { metadata },
    }],
  };
}

check('blueprint exposes portraitUrl and a derived sprite key for a portrait NPC', () => {
  const blueprints = helpers._buildRenderableEntityBlueprints(
    buildDungeonWithActor({
      display_name: 'Skeleton Guard Alpha',
      portrait_url: '/sites/default/files/dc/skeleton-alpha.png',
    }),
    'room-1',
    null,
    null,
  );
  const actor = blueprints.find((b) => b.instanceId === 'npc_skeleton_guard_alpha');
  assert.ok(actor, 'expected the NPC blueprint to be projected');
  assert.strictEqual(actor.render.portraitUrl, '/sites/default/files/dc/skeleton-alpha.png');
  assert.strictEqual(actor.render.spriteKey, 'portrait__npc_skeleton_guard_alpha');
});

check('an explicit metadata sprite_id still wins over the derived portrait key', () => {
  const blueprints = helpers._buildRenderableEntityBlueprints(
    buildDungeonWithActor({
      display_name: 'Skeleton Guard Alpha',
      sprite_id: 'authoritative_skeleton',
      portrait_url: '/dc/skeleton-alpha.png',
    }),
    'room-1',
    null,
    null,
  );
  const actor = blueprints.find((b) => b.instanceId === 'npc_skeleton_guard_alpha');
  assert.strictEqual(actor.render.spriteKey, 'authoritative_skeleton');
  assert.strictEqual(actor.render.portraitUrl, '/dc/skeleton-alpha.png');
});

// ---------------------------------------------------------------------------
// 4. Texture-ready notification (the async timing gap).
// ---------------------------------------------------------------------------

function makeSpriteEnv() {
  const textureCache = {};
  const loaders = [];
  const PIXI = {
    utils: { TextureCache: textureCache },
    BaseTexture: class { constructor(img) { this.img = img; } },
    Texture: class { constructor(base) { this.baseTexture = base; } },
  };
  class FakeImage {
    constructor() {
      this.onload = null;
      this.onerror = null;
      this._src = '';
      loaders.push(this);
    }

    set src(value) { this._src = value; }

    get src() { return this._src; }
  }
  const SpriteService = spriteServiceFactory(PIXI, FakeImage);
  return { SpriteService, textureCache, loaders };
}

function makeEntity() {
  const render = { spriteKey: 'portrait__npc_skeleton_guard_alpha', _generatedSpriteApplied: false };
  return {
    id: 1,
    dcEntityRef: 'npc_skeleton_guard_alpha',
    dcContentId: 'skeleton_guard',
    getComponent: (name) => (name === 'RenderComponent' ? render : null),
    _render: render,
  };
}

check('async texture load publishes a texture-ready notification', () => {
  const { SpriteService, textureCache, loaders } = makeSpriteEnv();
  const service = new SpriteService();
  const seen = [];
  service.onTextureReady((spriteId) => seen.push(spriteId));

  const entity = makeEntity();
  service.loadAndApplyTexture(entity, '/dc/skeleton.png', 'portrait__npc_skeleton_guard_alpha', {});

  assert.deepStrictEqual(seen, [], 'must not notify before the image has loaded');
  loaders[0].onload();

  assert.deepStrictEqual(seen, ['portrait__npc_skeleton_guard_alpha']);
  assert.ok(
    textureCache['portrait__npc_skeleton_guard_alpha'],
    'texture must be cached under the raw sprite key the token renderer looks up',
  );
});

check('notification fires even when the entity has no legacy placeholder sprite', () => {
  const { SpriteService, loaders } = makeSpriteEnv();
  const service = new SpriteService();
  let notified = 0;
  service.onTextureReady(() => { notified += 1; });

  // v2 map tokens are drawn by HexTokenRenderer, not RenderSystem, so the
  // RenderComponent legitimately has no `sprite` to replace.
  const entity = makeEntity();
  service.loadAndApplyTexture(entity, '/dc/skeleton.png', 'portrait__npc_skeleton_guard_alpha', {});
  loaders[0].onload();

  assert.strictEqual(notified, 1, 'texture availability must be announced regardless of placeholder state');
});

check('unsubscribe stops further notifications', () => {
  const { SpriteService, loaders } = makeSpriteEnv();
  const service = new SpriteService();
  let notified = 0;
  const unsub = service.onTextureReady(() => { notified += 1; });
  unsub();

  service.loadAndApplyTexture(makeEntity(), '/dc/a.png', 'portrait__a', {});
  loaders[0].onload();
  assert.strictEqual(notified, 0);
});

check('a synchronous texture-cache hit also notifies', () => {
  const { SpriteService, textureCache } = makeSpriteEnv();
  const service = new SpriteService();
  const seen = [];
  service.onTextureReady((spriteId) => seen.push(spriteId));

  textureCache['gen_portrait__x'] = { preloaded: true };
  service.loadAndApplyTexture(makeEntity(), '/dc/x.png', 'portrait__x', {});

  assert.deepStrictEqual(seen, ['portrait__x']);
  assert.ok(textureCache['portrait__x'], 'raw key must be aliased for the token renderer');
});

check('a failing texture-ready listener cannot break the others', () => {
  const { SpriteService, loaders } = makeSpriteEnv();
  const service = new SpriteService();
  let survived = 0;
  service.onTextureReady(() => { throw new Error('boom'); });
  service.onTextureReady(() => { survived += 1; });

  service.loadAndApplyTexture(makeEntity(), '/dc/a.png', 'portrait__a', {});
  loaders[0].onload();
  assert.strictEqual(survived, 1);
});

// ---------------------------------------------------------------------------
// 5. Portrait application must not be gated on furniture definitions.
// ---------------------------------------------------------------------------

check('applyFromCache applies portraits in a room with no object definitions', () => {
  const { SpriteService, loaders } = makeSpriteEnv();
  const service = new SpriteService();
  service.preloadUrl('portrait__npc_skeleton_guard_alpha', '/dc/skeleton.png');

  const entity = makeEntity();
  const entityManager = { getEntitiesWith: () => [entity] };
  service.applyFromCache(entityManager, {}, { entities: [] });

  assert.strictEqual(
    loaders.length,
    1,
    'a room without object_definitions must still load pre-seeded actor portraits',
  );
  assert.strictEqual(loaders[0].src, '/dc/skeleton.png');
});

// ---------------------------------------------------------------------------
// 6. GameShell must react to the notification.
// ---------------------------------------------------------------------------

const gameShellSource = fs.readFileSync(path.join(ROOT, 'js/v2/GameShell.js'), 'utf8');

check('GameShell subscribes to texture-ready notifications', () => {
  assert.ok(
    /onTextureReady\?\.\(/.test(gameShellSource),
    'GameShell must subscribe to SpriteService.onTextureReady',
  );
});

check('GameShell re-emits room:entities-changed so tokens rebuild with the new art', () => {
  const match = gameShellSource.match(/_scheduleTokenSpriteRefresh\(\)\s*\{[\s\S]*?\n  \}/);
  assert.ok(match, 'expected a _scheduleTokenSpriteRefresh method');
  assert.ok(
    match[0].includes("'room:entities-changed'"),
    'the refresh must re-emit room:entities-changed (the only event HexTokenRenderer rebuilds on)',
  );
  assert.ok(
    /_tokenSpriteRefreshHandle/.test(match[0]),
    'the refresh must coalesce so N portraits do not trigger N full token rebuilds',
  );
});

check('GameShell releases the texture-ready subscription on destroy', () => {
  assert.ok(
    /_spriteTextureReadyUnsub\s*\(\);/.test(gameShellSource),
    'the texture-ready subscription must be unsubscribed during teardown',
  );
});

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
process.exit(failed > 0 ? 1 : 0);
