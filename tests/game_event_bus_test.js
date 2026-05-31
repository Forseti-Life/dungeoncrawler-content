/**
 * @file
 * Unit tests for GameEventBus.
 *
 * Run with:
 *   node tests/game_event_bus_test.js
 *
 * Covers: subscribe, emit, unsubscribe, handler isolation (error in one
 * handler doesn't prevent others), destroy clears all listeners.
 */

let passed = 0;
let failed = 0;

function assert(condition, msg) {
  if (condition) {
    passed++;
    console.log(`  ✓ ${msg}`);
  } else {
    failed++;
    console.error(`  ✗ ${msg}`);
  }
}

// Load GameEventBus from source (ES module → CJS via Function wrapper).
const fs = require('fs');
const path = require('path');

const srcPath = path.resolve(__dirname, '../js/v2/GameEventBus.js');
let src = fs.readFileSync(srcPath, 'utf8');
src = src.replace(/^export\s+/gm, '');
const GameEventBus = new Function(src + '\nreturn GameEventBus;')();

// ---------------------------------------------------------------------------
// Suite: subscribe and emit
// ---------------------------------------------------------------------------
console.log('\n=== subscribe and emit ===');
{
  const bus = new GameEventBus();

  let received = null;
  bus.on('test:event', (payload) => { received = payload; });
  bus.emit('test:event', { value: 42 });
  assert(received?.value === 42, 'handler receives emitted payload');

  bus.destroy();
}

// ---------------------------------------------------------------------------
// Suite: unsubscribe via returned function
// ---------------------------------------------------------------------------
console.log('\n=== unsubscribe ===');
{
  const bus = new GameEventBus();
  let callCount = 0;

  const unsub = bus.on('test:unsub', () => { callCount++; });
  bus.emit('test:unsub', null);
  unsub();
  bus.emit('test:unsub', null);

  assert(callCount === 1, 'handler not called after unsubscribe');
  bus.destroy();
}

// ---------------------------------------------------------------------------
// Suite: multiple subscribers on the same event
// ---------------------------------------------------------------------------
console.log('\n=== multiple subscribers ===');
{
  const bus = new GameEventBus();
  const results = [];

  bus.on('multi', (v) => results.push('a' + v));
  bus.on('multi', (v) => results.push('b' + v));
  bus.emit('multi', '!');

  assert(results.length === 2, 'both handlers called');
  assert(results.includes('a!') && results.includes('b!'), 'both handlers receive payload');
  bus.destroy();
}

// ---------------------------------------------------------------------------
// Suite: error in one handler does not prevent others
// ---------------------------------------------------------------------------
console.log('\n=== handler error isolation ===');
{
  const bus = new GameEventBus();
  let secondCalled = false;

  bus.on('err:event', () => { throw new Error('intentional test error'); });
  bus.on('err:event', () => { secondCalled = true; });
  bus.emit('err:event', null);

  assert(secondCalled, 'second handler still called after first throws');
  bus.destroy();
}

// ---------------------------------------------------------------------------
// Suite: emit on event with no subscribers is a no-op
// ---------------------------------------------------------------------------
console.log('\n=== emit with no subscribers ===');
{
  const bus = new GameEventBus();
  let threw = false;
  try {
    bus.emit('ghost:event', { data: 1 });
  } catch (_) {
    threw = true;
  }
  assert(!threw, 'no error when emitting to event with no subscribers');
  bus.destroy();
}

// ---------------------------------------------------------------------------
// Suite: destroy clears all listeners
// ---------------------------------------------------------------------------
console.log('\n=== destroy clears listeners ===');
{
  const bus = new GameEventBus();
  let callCount = 0;

  bus.on('persist:event', () => { callCount++; });
  bus.destroy();
  // After destroy, emit should not reach any handler
  try {
    bus.emit('persist:event', null);
  } catch (_) { /* ignore */ }

  assert(callCount === 0, 'no handlers called after destroy');
}

// ---------------------------------------------------------------------------
// Suite: off() removes specific handler, leaves others intact
// ---------------------------------------------------------------------------
console.log('\n=== off() selective removal ===');
{
  const bus = new GameEventBus();
  let aCount = 0;
  let bCount = 0;

  const handlerA = () => { aCount++; };
  const handlerB = () => { bCount++; };

  bus.on('selective', handlerA);
  bus.on('selective', handlerB);
  bus.off('selective', handlerA);
  bus.emit('selective', null);

  assert(aCount === 0, 'removed handler A not called');
  assert(bCount === 1, 'remaining handler B still called');
  bus.destroy();
}

// ---------------------------------------------------------------------------
// Results
// ---------------------------------------------------------------------------
console.log('\n===================================');
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  console.error('SOME TESTS FAILED');
  process.exit(1);
} else {
  console.log('ALL TESTS PASSED');
}
