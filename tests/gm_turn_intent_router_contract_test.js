/**
 * @file
 * Contract checks for generateGmReply turn-intent router extraction.
 *
 * Run with:
 *   node tests/gm_turn_intent_router_contract_test.js
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

const routerSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/GmSubsystem/TurnIntentRouter.php'),
  'utf8'
);
const roomChatSource = fs.readFileSync(
  path.resolve(__dirname, '../src/Service/RoomChatService.php'),
  'utf8'
);
const servicesSource = fs.readFileSync(
  path.resolve(__dirname, '../dungeoncrawler_content.services.yml'),
  'utf8'
);

console.log('\n=== GM turn-intent router contract ===');

assert(
  routerSource.includes('class TurnIntentRouter'),
  'turn-intent router service class exists'
);
assert(
  routerSource.includes('DETERMINISTIC_INTENT_ROUTE_MAP')
    && routerSource.includes('NARRATIVE_INTENT_ROUTE_MAP'),
  'router defines deterministic and narrative route maps'
);
assert(
  routerSource.includes('fallback_to_llm'),
  'router exposes explicit fallback_to_llm resolution outcome'
);
assert(
  roomChatSource.includes('protected TurnIntentRouter $turnIntentRouter;')
    && roomChatSource.includes('$this->turnIntentRouter = $turn_intent_router ?? new TurnIntentRouter();'),
  'RoomChatService wires the router dependency with deterministic fallback construction'
);
assert(
  roomChatSource.includes('$route_decision = $this->turnIntentRouter->routeFromIntent($turn_intent, $is_room_entry);'),
  'generateGmReply uses router output for route decision metadata'
);
assert(
  roomChatSource.includes("'route_family' => $route_decision['route_family'] ?? 'llm_fallback'")
    && roomChatSource.includes("'resolution_outcome' => $route_decision['resolution_outcome'] ?? 'fallback_to_llm'"),
  'generateGmReply emits route-family and resolution-outcome debug metadata'
);
assert(
  servicesSource.includes('dungeoncrawler_content.gm_turn_intent_router:')
    && servicesSource.includes("- '@?dungeoncrawler_content.gm_turn_intent_router'"),
  'service container registers and injects the GM turn-intent router'
);

console.log(`\nPassed: ${passed}`);
console.log(`Failed: ${failed}`);
if (failed > 0) {
  process.exit(1);
}

