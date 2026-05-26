/**
 * @file
 * Lightweight regression tests for fullscreen viewport sizing helpers.
 *
 * Run with:
 *   node tests/hexmap_fullscreen_layout_test.js
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

function extractMethodSource(source, signature) {
  const start = source.indexOf(signature);
  if (start === -1) {
    throw new Error(`Could not find method signature: ${signature}`);
  }

  let braceStart = -1;
  let parenDepth = 0;
  for (let index = start; index < source.length; index++) {
    const char = source[index];
    if (char === '(') {
      parenDepth++;
    } else if (char === ')') {
      parenDepth = Math.max(0, parenDepth - 1);
    } else if (char === '{' && parenDepth === 0) {
      braceStart = index;
      break;
    }
  }

  if (braceStart === -1) {
    throw new Error(`Could not find opening brace for: ${signature}`);
  }

  const signatureMatch = signature.match(/^([A-Za-z0-9_]+)\(([^)]*)\)\s*\{$/);
  if (!signatureMatch) {
    throw new Error(`Could not parse method signature: ${signature}`);
  }

  let depth = 0;
  for (let index = braceStart; index < source.length; index++) {
    const char = source[index];
    if (char === '{') {
      depth++;
    } else if (char === '}') {
      depth--;
      if (depth === 0) {
        const body = source.slice(braceStart + 1, index);
        return `function ${signatureMatch[1]}(${signatureMatch[2]}) {${body}}`;
      }
    }
  }

  throw new Error(`Could not find closing brace for: ${signature}`);
}

function createStyleStore() {
  const values = new Map();
  return {
    values,
    setProperty(name, value) {
      values.set(name, value);
    },
    removeProperty(name) {
      values.delete(name);
    },
  };
}

const sourcePath = path.resolve(__dirname, '../js/hexmap.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const updateFullscreenViewportMetricsSource = extractMethodSource(source, 'updateFullscreenViewportMetrics(container = null) {');
const clearFullscreenViewportMetricsSource = extractMethodSource(source, 'clearFullscreenViewportMetrics(container = null) {');
const factory = new Function(`${updateFullscreenViewportMetricsSource}\n${clearFullscreenViewportMetricsSource}\nreturn { updateFullscreenViewportMetrics, clearFullscreenViewportMetrics };`);
const methods = factory();

console.log('\n=== Hexmap fullscreen viewport sizing ===');

{
  const style = createStyleStore();
  const tabs = {
    getBoundingClientRect() {
      return { height: 84.4 };
    },
  };
  const container = {
    style,
    dataset: {},
    querySelector(selector) {
      return selector === '.game-shell__tabs' ? tabs : null;
    },
  };

  global.window = {
    visualViewport: { height: 724.8 },
    innerHeight: 900,
  };
  global.document = {
    documentElement: { clientHeight: 880 },
    getElementById() {
      return container;
    },
  };

  const metrics = methods.updateFullscreenViewportMetrics(container);
  assert(metrics.viewportHeight === 725, 'Uses visualViewport height when available');
  assert(metrics.headerHeight === 84, 'Rounds tab/header height');
  assert(metrics.bodyHeight === 625, 'Subtracts header height and gutter from body height');
  assert(style.values.get('--dc-fullscreen-height') === '725px', 'Stores fullscreen height CSS variable');
  assert(style.values.get('--dc-fullscreen-header-height') === '84px', 'Stores fullscreen header CSS variable');
  assert(style.values.get('--dc-fullscreen-body-height') === '625px', 'Stores fullscreen body CSS variable');
  assert(container.dataset.fullscreenCompact === 'true', 'Marks short viewports as compact');
}

{
  const style = createStyleStore();
  style.setProperty('--dc-fullscreen-height', '725px');
  style.setProperty('--dc-fullscreen-header-height', '84px');
  style.setProperty('--dc-fullscreen-body-height', '625px');
  const container = {
    style,
    dataset: { fullscreenCompact: 'true' },
    querySelector() {
      return null;
    },
  };

  global.window = {
    innerHeight: 1080,
  };
  global.document = {
    documentElement: { clientHeight: 1080 },
    getElementById() {
      return container;
    },
  };

  const metrics = methods.updateFullscreenViewportMetrics(container);
  assert(metrics.viewportHeight === 1080, 'Falls back to window innerHeight');
  assert(metrics.headerHeight === 0, 'Defaults header height to zero when tabs are absent');
  assert(metrics.bodyHeight === 1064, 'Computes body height without tabs');
  assert(container.dataset.fullscreenCompact === 'false', 'Leaves tall viewports in normal mode');

  methods.clearFullscreenViewportMetrics(container);
  assert(!style.values.has('--dc-fullscreen-height'), 'Clears fullscreen height CSS variable');
  assert(!style.values.has('--dc-fullscreen-header-height'), 'Clears fullscreen header CSS variable');
  assert(!style.values.has('--dc-fullscreen-body-height'), 'Clears fullscreen body CSS variable');
  assert(!Object.prototype.hasOwnProperty.call(container.dataset, 'fullscreenCompact'), 'Removes compact dataset flag');
}

console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
  process.exit(1);
}
