const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.resolve(__dirname, '../js/v2/canvas/HexCanvas.js'), 'utf8')
  .replace(/^export\s+/gm, '');
const HexCanvas = new Function(source + '\nreturn HexCanvas;')();

class Container {
  constructor() {
    this.children = [];
    this.pivot = { set: (x, y) => { this.pivot.x = x; this.pivot.y = y; } };
    this.scale = { set: (value) => { this.scale.value = value; } };
  }
  addChild(...children) { this.children.push(...children); }
  removeChildren() {
    const removed = this.children;
    this.children = [];
    return removed;
  }
  destroy() {}
}
class Sprite extends Container {
  constructor(texture) {
    super();
    this.texture = texture;
    this.anchor = { set: (value) => { this.anchor.value = value; } };
    this.position = { set: (x, y) => { this.position.x = x; this.position.y = y; } };
  }
}
class Graphics extends Container {
  constructor() { super(); this.polygons = []; }
  beginFill() {}
  drawPolygon(points) { this.polygons.push(points); }
  endFill() {}
}

global.PIXI = { Container, Sprite, Graphics, Assets: { load: async (url) => ({ url, width: 500, height: 500 }) } };

async function main() {
  const canvas = Object.create(HexCanvas.prototype);
  canvas.config = { hexSize: 30 };
  canvas.backgroundContainer = new Container();
  canvas._sourceMapSerial = 1;
  canvas.bus = { emit: () => { throw new Error('Unexpected source-map failure'); } };
  const map = {
    width_px: 5000, height_px: 5000, background_url: '/map.png',
    background_width_px: 500, background_height_px: 500,
    alignment: {
      origin_x_px: 2500, origin_y_px: 2500,
      source_hex_radius_px: 100, rotation_degrees: 90,
    },
    tiles: [{
      url: '/tile.png', x: 200, y: 150, width: 80, height: 120,
      rotation: 45, alpha: 0.75,
    }],
  };
  await canvas._renderSourceMap(map, [
    { q: 0, r: 0 }, { q: 1, r: 0 }, { q: 2, r: 0, is_visible: false },
  ], 1);
  assert.equal(canvas.backgroundContainer.children.length, 2);
  const [art, mask] = canvas.backgroundContainer.children;
  assert.equal(art.mask, mask);
  assert.equal(art.scale.value, 0.3);
  assert.deepEqual([art.pivot.x, art.pivot.y], [2500, 2500]);
  assert.equal(art.rotation, Math.PI / 2);
  assert.equal(art.children[0].texture.url, '/map.png');
  assert.equal(art.children[0].scale.value, 10);
  assert.deepEqual([art.children[1].position.x, art.children[1].position.y], [240, 210]);
  assert.deepEqual([art.children[1].width, art.children[1].height], [80, 120]);
  assert.equal(art.children[1].rotation, Math.PI / 4);
  assert.equal(art.children[1].alpha, 0.75);
  assert.equal(mask.polygons.length, 2);

  await assert.rejects(
    canvas._renderSourceMap({ ...map, background_width_px: 501 }, [{ q: 0, r: 0 }], 1),
    /bitmap dimensions do not match/,
    'A staged bitmap contract violation must not be downgraded to a load failure',
  );
  canvas._sourceMapSerial = 2;
  await canvas._renderSourceMap(map, [{ q: 0, r: 0 }], 1);
  assert.equal(canvas.backgroundContainer.children.length, 2, 'Stale room images must not render');
  let emitted = null;
  const repainted = [];
  canvas.hexContainer = {
    removeChildren: () => [{ destroy: () => {} }],
  };
  canvas._createHex = (...args) => repainted.push(args);
  canvas.bus = { emit: (name) => { emitted = name; } };
  const load = PIXI.Assets.load;
  const log = console.error;
  PIXI.Assets.load = async () => { throw new Error('Asset unavailable'); };
  console.error = () => {};
  try {
    await canvas._renderSourceMap(map, [{ q: 0, r: 0, terrain_type: 'floor' }], 2);
  } finally {
    PIXI.Assets.load = load;
    console.error = log;
  }
  assert.equal(emitted, 'canvas:source-map-error');
  assert.equal(repainted.length, 1);
  assert.equal(repainted[0][3].fillAlpha, 1, 'Missing art restores the normal floor');

  const grid = Object.create(HexCanvas.prototype);
  grid.config = { hexSize: 30, gridWidth: 2, gridHeight: 2, showGrid: true };
  grid.backgroundContainer = new Container();
  grid.hexContainer = new Container();
  grid.gridContainer = new Container();
  grid.propsContainer = new Container();
  grid._sourceMapSerial = 0;
  grid._hideHexHoverInfo = () => {};
  const hexStyles = [];
  grid._createHex = (...args) => hexStyles.push(args[3]);
  let renderCalls = 0;
  grid._renderSourceMap = () => { renderCalls += 1; };
  grid.currentRoom = { source_map: map, hexes: [
    { q: 0, r: 0, terrain_type: 'floor', is_visible: true },
    { q: 1, r: 0, terrain_type: 'floor', is_visible: false },
    { q: 2, r: 0, terrain_type: 'wall', is_visible: true },
    { q: 3, r: 0, terrain_type: 'stone_floor', is_visible: true },
    { q: 4, r: 0, terrain_type: 'stone_floor', is_visible: true, objects: [{ category: 'hazard' }] },
  ] };
  grid.generateHexGrid();
  assert.equal(hexStyles[0].fillAlpha, 0.15, 'Only visible floor reveals reviewed art');
  assert.equal(hexStyles[1].fillAlpha, 0.55, 'Fog retains its normal styling');
  assert.ok(hexStyles[2].fillAlpha > 0.9, 'Non-floor terrain stays opaque');
  assert.equal(hexStyles[3].fillAlpha, 0.15, 'Generated stone floors reveal reviewed art');
  assert.ok(hexStyles[4].fillAlpha > 0.8, 'Hazard styling remains authoritative above art');
  assert.equal(renderCalls, 1);
  grid.currentRoom.source_map = { ...map, alignment: null };
  hexStyles.length = 0;
  grid.generateHexGrid();
  assert.equal(renderCalls, 1, 'Unaligned art must not render');
  assert.equal(hexStyles[0].fillAlpha, 1, 'Unaligned room retains normal floor');
  console.log('Aligned source-map rendering and stale-room guard passed.');
}

main().catch((error) => { console.error(error); process.exitCode = 1; });
