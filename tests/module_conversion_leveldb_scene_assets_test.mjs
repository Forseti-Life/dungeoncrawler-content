import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ClassicLevel } from 'classic-level';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'module-scene-reader-'));

try {
  const db = new ClassicLevel(directory, { valueEncoding: 'utf8' });
  await db.open();
  await db.put('!adventures!original', JSON.stringify({
    _id: 'original',
    name: 'Project-authored test adventure',
    scenes: [
      {
        _id: 'scene-present',
        name: 'Project-authored map',
        width: 1800,
        height: 1200,
        grid: { type: 1, size: 100, distance: 5, units: 'ft' },
        background: { src: 'modules/original/assets/map.svg' },
        navOrder: 2,
        tiles: [
          { _id: 'original-tile', texture: { src: 'modules/original/assets/lantern.svg' }, x: 120, y: 240, width: 200, height: 180, rotation: 30, alpha: 0.8, elevation: 2, sort: 3 },
          { _id: 'second-tile', texture: { src: 'modules/original/assets/lantern.svg' } },
        ],
        drawings: [{ _id: 'original-drawing' }],
        tokens: [],
        walls: [],
      },
      { _id: 'scene-missing', name: 'Scene requiring enrichment' },
    ],
    journal: [{
      _id: 'original-journal',
      pages: [{ _id: 'original-page', text: { content: 'Original test content.' } }],
    }],
  }));
  await db.close();

  const raw = execFileSync('node', [
    path.join(root, 'tools/module-conversion/read-leveldb-pack.mjs'),
    '--db-dir', directory,
  ], { encoding: 'utf8' });
  const documents = JSON.parse(raw).documents;
  const present = documents.find((entry) => entry.id === 'scene-present');
  const missing = documents.find((entry) => entry.id === 'scene-missing');
  const page = documents.find((entry) => entry.id === 'original-page');
  assert.deepEqual(present.source_context, {
    width: 1800,
    height: 1200,
    grid_type: 1,
    grid_size: 100,
    grid_distance: 5,
    grid_units: 'ft',
    background_src: 'modules/original/assets/map.svg',
    tile_image_refs: ['modules/original/assets/lantern.svg', 'modules/original/assets/lantern.svg'],
    tile_placements: [
      { x: 120, y: 240, width: 200, height: 180, rotation: 30, alpha: 0.8, elevation: 2, sort: 3 },
      { x: null, y: null, width: null, height: null, rotation: null, alpha: null, elevation: null, sort: null },
    ],
    nav_order: 2,
  });
  assert.equal(present.counts.tiles, 2);
  assert.equal(present.counts.drawings, 1);
  assert.equal(present.counts.tokens, undefined);
  assert.equal(missing.source_context.width, null);
  assert.equal(missing.source_context.background_src, null);
  assert.equal(page.source_context.text_content_length, 'Original test content.'.length);

  const invalid = new ClassicLevel(directory, { valueEncoding: 'utf8' });
  await invalid.open();
  await invalid.put('!adventures!invalid', JSON.stringify({
    _id: 'invalid',
    scenes: [{ _id: 'invalid-scene', width: '1800' }],
  }));
  await invalid.close();
  const rejected = spawnSync('node', [
    path.join(root, 'tools/module-conversion/read-leveldb-pack.mjs'),
    '--db-dir', directory,
  ], { encoding: 'utf8' });
  assert.equal(rejected.status, 1);
  assert.match(rejected.stderr, /scene_source_geometry_invalid:width/);
  const invalidTile = new ClassicLevel(directory, { valueEncoding: 'utf8' });
  await invalidTile.open();
  await invalidTile.del('!adventures!invalid');
  await invalidTile.put('!adventures!invalid-tile', JSON.stringify({
    _id: 'invalid-tile',
    scenes: [{ _id: 'invalid-scene', tiles: [{ width: -2 }] }],
  }));
  await invalidTile.close();
  const rejectedTile = spawnSync('node', [
    path.join(root, 'tools/module-conversion/read-leveldb-pack.mjs'),
    '--db-dir', directory,
  ], { encoding: 'utf8' });
  assert.equal(rejectedTile.status, 1);
  assert.match(rejectedTile.stderr, /scene_source_geometry_invalid:tile.width/);
  console.log('Module conversion LevelDB scene asset summary passed.');
}
finally {
  fs.rmSync(directory, { recursive: true, force: true });
}
