/**
 * @file
 * Lightweight unit tests for ChatSessionApi.
 *
 * Run with:
 *   node tests/chat_session_api_test.js
 *
 * These tests verify the ChatSessionApi class builds correct URLs
 * and handles responses properly by mocking globalThis.fetch.
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

// Minimal fetch mock — intercepts globalThis.fetch.
const fetchCalls = [];
function resetFetch() { fetchCalls.length = 0; }

function mockFetch(responseData, status = 200) {
  globalThis.fetch = async (url, opts = {}) => {
    fetchCalls.push({ url: String(url), opts });
    return {
      ok: status >= 200 && status < 300,
      status,
      headers: { get: () => 'application/json' },
      json: async () => responseData,
      text: async () => JSON.stringify(responseData),
    };
  };
}

// Install mock fetch immediately so eval'd code picks it up.
mockFetch({ success: true, data: {} });

// We can't natively import ES modules in older Node without flags, so we
// inline a minimal version by reading the source and evaluating it.
const fs = require('fs');
const path = require('path');

// Read ChatSessionApi.js source and convert to CommonJS.
const srcPath = path.resolve(__dirname, '../js/ChatSessionApi.js');
let src = fs.readFileSync(srcPath, 'utf8');

// Strip ES module syntax and inject into a module scope.
src = src.replace(/^export /gm, '');
src = src.replace(/^import .*/gm, '');
src = src.replace(/^default /gm, '// default ');

// Wrap in a function that returns the class.
const factory = new Function(src + '\nreturn ChatSessionApi;');
const ChatSessionApi = factory();

// ============================================================
console.log('\n=== ChatSessionApi URL construction ===');

resetFetch();
mockFetch({ success: true, data: { sessions: [], root_session_id: null } });
const api = new ChatSessionApi(42);

assert(api.baseUrl === '/api/campaign/42', 'Base URL set correctly');

(async () => {
  // --- listSessions ---
  resetFetch();
  mockFetch({ success: true, data: { sessions: [], root_session_id: null } });
  await api.listSessions();
  assert(fetchCalls[0].url === '/api/campaign/42/sessions', 'listSessions URL');

  resetFetch();
  mockFetch({ success: true, data: { sessions: [], root_session_id: null } });
  await api.listSessions({ type: 'room' });
  assert(fetchCalls[0].url.includes('type=room'), 'listSessions with type filter');

  // --- getSessionMessages ---
  resetFetch();
  mockFetch({ success: true, data: { messages: [] } });
  await api.getSessionMessages(99, { order: 'asc', limit: 20 });
  assert(fetchCalls[0].url.includes('/sessions/99/messages'), 'getSessionMessages URL');
  assert(fetchCalls[0].url.includes('order=asc'), 'getSessionMessages order param');
  assert(fetchCalls[0].url.includes('limit=20'), 'getSessionMessages limit param');

  // --- postSessionMessage ---
  resetFetch();
  mockFetch({ success: true, data: { message_id: 1, session_id: 99 } });
  await api.postSessionMessage(99, { speaker: 'Torgar', message: 'Hello!' });
  assert(fetchCalls[0].url === '/api/campaign/42/sessions/99/messages', 'postSessionMessage URL');
  const postBody = JSON.parse(fetchCalls[0].opts.body);
  assert(postBody.speaker === 'Torgar', 'postSessionMessage speaker in body');
  assert(postBody.message === 'Hello!', 'postSessionMessage message in body');

  // --- getCharacterNarrative ---
  console.log('\n=== Character narrative ===');
  resetFetch();
  mockFetch({ success: true, data: { character_id: 85, messages: [] } });
  await api.getCharacterNarrative(85, { dungeonId: '10', roomId: 'room_a1' });
  assert(fetchCalls[0].url.includes('/narrative/85'), 'getCharacterNarrative URL');
  assert(fetchCalls[0].url.includes('dungeon_id=10'), 'narrative dungeon_id param');
  assert(fetchCalls[0].url.includes('room_id=room_a1'), 'narrative room_id param');

  // --- getPartyChat ---
  console.log('\n=== Party chat ===');
  resetFetch();
  mockFetch({ success: true, data: { session_id: 5, messages: [] } });
  await api.getPartyChat({ limit: 30 });
  assert(fetchCalls[0].url.includes('/party-chat'), 'getPartyChat URL');
  assert(fetchCalls[0].url.includes('limit=30'), 'getPartyChat limit param');

  // --- postPartyChat ---
  resetFetch();
  mockFetch({ success: true, data: { message_id: 2, session_id: 5 } });
  await api.postPartyChat('Torgar', 'Let\'s huddle!', '85');
  const partyBody = JSON.parse(fetchCalls[0].opts.body);
  assert(partyBody.speaker === 'Torgar', 'postPartyChat speaker');
  assert(partyBody.message === "Let's huddle!", 'postPartyChat message');
  assert(partyBody.speaker_ref === '85', 'postPartyChat speaker_ref');

  // --- getGmPrivate ---
  console.log('\n=== GM Private ===');
  resetFetch();
  mockFetch({ success: true, data: { session_id: 7, messages: [] } });
  await api.getGmPrivate(85);
  assert(fetchCalls[0].url.includes('/gm-private/85'), 'getGmPrivate URL');

  // --- postGmPrivate ---
  resetFetch();
  mockFetch({ success: true, data: { message_id: 3, character_id: 85 } });
  await api.postGmPrivate(85, 'Torgar', 'I secretly pickpocket.', { skill: 'thievery' });
  const gmBody = JSON.parse(fetchCalls[0].opts.body);
  assert(gmBody.speaker === 'Torgar', 'postGmPrivate speaker');
  assert(gmBody.message === 'I secretly pickpocket.', 'postGmPrivate message');
  assert(gmBody.metadata?.skill === 'thievery', 'postGmPrivate metadata');

  // --- getSystemLog ---
  console.log('\n=== System log ===');
  resetFetch();
  mockFetch({ success: true, data: { session_id: 8, messages: [] } });
  await api.getSystemLog({ limit: 200 });
  assert(fetchCalls[0].url.includes('/system-log'), 'getSystemLog URL');
  assert(fetchCalls[0].url.includes('limit=200'), 'getSystemLog limit param');

  // --- flushNarration ---
  console.log('\n=== Flush narration ===');
  resetFetch();
  mockFetch({ success: true, data: { flushed: true, scene_beats: [], characters_narrated: 0 } });
  await api.flushNarration(10, 'room_a1', [{ character_id: 85, name: 'Torgar' }]);
  assert(fetchCalls[0].url === '/api/campaign/42/narration/flush', 'flushNarration URL');
  const flushBody = JSON.parse(fetchCalls[0].opts.body);
  assert(flushBody.dungeon_id === 10, 'flushNarration dungeon_id');
  assert(flushBody.room_id === 'room_a1', 'flushNarration room_id');
  assert(Array.isArray(flushBody.present_characters), 'flushNarration present_characters array');

  // --- Error handling ---
  console.log('\n=== Error handling ===');
  resetFetch();
  mockFetch(null, 404);
  const notFound = await api.getPartyChat();
  assert(notFound === null, 'GET 404 returns null');

  resetFetch();
  mockFetch({ success: false, error: 'Bad' }, 400);
  try {
    await api.postPartyChat('X', 'Y');
    assert(false, 'POST 400 should throw');
  } catch (e) {
    assert(e.message.includes('400'), 'POST 400 throws with status code');
  }

  // --- Report ---
  console.log('\n===================================');
  console.log(`Passed: ${passed}`);
  console.log(`Failed: ${failed}`);
  console.log('===================================');
  if (failed === 0) {
    console.log('ALL TESTS PASSED');
  } else {
    console.log('SOME TESTS FAILED');
    process.exitCode = 1;
  }
})();
