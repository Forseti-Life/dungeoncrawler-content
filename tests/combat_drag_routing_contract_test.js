/**
 * @file
 * Contract test: live-combat drag/drop routes to the combat stride path.
 *
 * Regression guard for the campaign 908 defect where dragging an actor during an
 * active encounter silently routed to the non-combat room-move endpoint. The
 * legacy ECS TurnManagementSystem defaults `encounterStatus` to 'idle' and is
 * never fed server state in the v2 shell, so consulting it inside
 * `isLiveCombatEncounterActive()` let that stale client-side default veto the
 * authoritative coordinator snapshot (phase 'encounter', encounterId 99053).
 * The drop then hit `moveEntityWithinRoom` and desynced character placement from
 * the encounter's authoritative initiative order.
 *
 * Run with:
 *   node tests/combat_drag_routing_contract_test.js
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

const shellSource = fs.readFileSync(path.resolve(__dirname, '../js/v2/GameShell.js'), 'utf8');
const phaseManagerSource = fs.readFileSync(path.resolve(__dirname, '../js/game-coordinator/PhaseManager.js'), 'utf8');

console.log('\n=== Live-combat drag routing contracts ===');

// ---------------------------------------------------------------------------
// Source contracts
// ---------------------------------------------------------------------------

assert(
  !/isLiveCombatEncounterActive\(\)[\s\S]{0,900}?turnManagementSystem\?\.getEncounterStatus/.test(shellSource),
  'isLiveCombatEncounterActive no longer consults the stale legacy ECS encounter status'
);

assert(
  /isLiveCombatEncounterActive\(\)[\s\S]{0,900}?snapshot\?\.encounterPresentation\?\.status/.test(shellSource),
  'isLiveCombatEncounterActive prefers the server-provided encounter presentation status from the coordinator snapshot'
);

assert(
  phaseManagerSource.includes("encounterPresentation: this.serverState?.encounter_presentation || null,"),
  'PhaseManager snapshot exposes the server encounter presentation so status is server-derived, not client-defaulted'
);

assert(
  shellSource.includes('const rejectDrop = (reason) => {')
    && shellSource.includes("console.warn('[GameShell] map actor drop rejected'")
    && /rejectDrop\(\s*this\.isLiveCombatEncounterActive\(\) && !this\.isCombatDragActorTurn\(entity\)/.test(shellSource)
    && shellSource.includes('return rejectDrop(validation.reason);')
    && shellSource.includes('return rejectDrop(combatPlan.reason);')
    && shellSource.includes("return rejectDrop('The server rejected that combat movement.');"),
  'handleMapActorDrop surfaces every rejection reason to the user instead of failing silently'
);

assert(
  /room move rejected[\s\S]{0,400}?chat:system-message/.test(shellSource),
  'rejected room moves surface the server error message to the user'
);

// ---------------------------------------------------------------------------
// Behavioral: extract and execute the real routing predicate + drop router.
// ---------------------------------------------------------------------------

function extractMethod(source, signature) {
  const start = source.indexOf(signature);
  if (start < 0) throw new Error(`method not found: ${signature}`);
  // Skip the parameter list first so destructured params aren't mistaken for the body.
  let i = source.indexOf('(', start);
  let parenDepth = 0;
  for (; i < source.length; i++) {
    if (source[i] === '(') parenDepth++;
    else if (source[i] === ')') {
      parenDepth--;
      if (parenDepth === 0) { i++; break; }
    }
  }
  const bodyStart = source.indexOf('{', i);
  let depth = 0;
  for (i = bodyStart; i < source.length; i++) {
    if (source[i] === '{') depth++;
    else if (source[i] === '}') {
      depth--;
      if (depth === 0) return source.slice(bodyStart, i + 1);
    }
  }
  throw new Error(`unbalanced braces for: ${signature}`);
}

const isLiveBody = extractMethod(shellSource, 'isLiveCombatEncounterActive() {');
const handleDropBody = extractMethod(shellSource, 'async handleMapActorDrop({');

// eslint-disable-next-line no-new-func
const isLiveCombatEncounterActive = new Function(`return function () ${isLiveBody};`)();
// eslint-disable-next-line no-new-func
const handleMapActorDrop = new Function(
  `return async function ({ entity = null, sourceQ = 0, sourceR = 0, targetQ = 0, targetR = 0 } = {}) ${handleDropBody};`
)();

function buildShell(overrides = {}) {
  const calls = { combat: [], roomMove: [], systemMessages: [] };
  const shell = {
    // Campaign 908 authoritative snapshot at the time of the bug report.
    gameCoordinator: {
      phaseManager: {
        encounterId: 99053,
        getSnapshot: () => ({
          phase: 'encounter',
          encounterId: 99053,
          encounterPresentation: overrides.encounterPresentation ?? { status: 'active' },
          turn: { entity: 'pc-908-1033' },
        }),
      },
    },
    // Stale legacy default that previously vetoed the authoritative snapshot.
    turnManagementSystem: { getEncounterStatus: () => 'idle' },
    _getStateValue: () => null,
    getEncounterServerState: () => overrides.encounterServerState ?? null,
    bus: { emit: (event, payload) => { if (event === 'chat:system-message') calls.systemMessages.push(payload); } },
    canDragEntityOnMap: () => overrides.canDrag ?? true,
    isCombatDragActorTurn: () => overrides.isActorTurn ?? true,
    resolveMapDragDropValidation: () => overrides.validation ?? { valid: true },
    buildCombatDragMovementPlan: () => overrides.combatPlan ?? {
      valid: true, actionType: 'stride', actionCost: 1, distanceFt: 15,
    },
    getEntityCharacterId: () => 5243,
    resolveActiveRoomId: () => 'undead_crypt_entry_hall',
    performCombatAction: async (opts) => { calls.combat.push(opts); return { success: true }; },
    moveEntityWithinRoom: async (...args) => { calls.roomMove.push(args); return true; },
  };
  shell.isLiveCombatEncounterActive = isLiveCombatEncounterActive.bind(shell);
  shell.handleMapActorDrop = handleMapActorDrop.bind(shell);
  shell.calls = calls;
  return shell;
}

(async () => {
  // --- The exact campaign 908 scenario -------------------------------------
  {
    const shell = buildShell();
    assert(
      shell.isLiveCombatEncounterActive() === true,
      'campaign 908 snapshot (phase encounter, encounter 99053, status active) is recognized as live combat'
    );

    const ok = await shell.handleMapActorDrop({
      entity: { id: 'pc-908-1033' }, sourceQ: -4, sourceR: 0, targetQ: -4, targetR: -4,
    });

    assert(ok === true, 'a legal in-combat drag resolves successfully');
    assert(
      shell.calls.combat.length === 1 && shell.calls.combat[0].actionType === 'stride',
      'in-combat drag routes to the server-authoritative combat stride action'
    );
    assert(
      shell.calls.roomMove.length === 0,
      'in-combat drag never falls through to the non-combat room-move path'
    );
  }

  // --- The stale legacy default must not veto ------------------------------
  {
    const shell = buildShell({ encounterServerState: null });
    // getEncounterServerState() is null (the cache lane is unpopulated), which is
    // exactly the state that previously fell through to the 'idle' ECS default.
    assert(
      shell.isLiveCombatEncounterActive() === true,
      'an unpopulated encounter-state cache does not fall back to the stale idle ECS status'
    );
  }

  // --- Genuine non-combat state still routes to room move -------------------
  {
    const shell = buildShell();
    shell.gameCoordinator.phaseManager.getSnapshot = () => ({
      phase: 'exploration', encounterId: 0, encounterPresentation: null,
    });
    shell.gameCoordinator.phaseManager.encounterId = 0;
    assert(shell.isLiveCombatEncounterActive() === false, 'exploration phase is not live combat');

    await shell.handleMapActorDrop({
      entity: { id: 'pc-908-1033' }, sourceQ: 0, sourceR: 0, targetQ: 1, targetR: 1,
    });
    assert(
      shell.calls.roomMove.length === 1 && shell.calls.combat.length === 0,
      'out-of-combat drag still uses the room-move path'
    );
  }

  // --- A server-declared ended encounter is still respected -----------------
  {
    const shell = buildShell({ encounterPresentation: { status: 'ended' } });
    assert(
      shell.isLiveCombatEncounterActive() === false,
      'a server-declared ended encounter is correctly treated as not live'
    );
  }

  // --- Rejections are surfaced, not silent ---------------------------------
  {
    const shell = buildShell({ canDrag: false, isActorTurn: false });
    const ok = await shell.handleMapActorDrop({
      entity: { id: 'npc_skeleton_guard_alpha' }, sourceQ: 3, sourceR: 2, targetQ: 1, targetR: 1,
    });
    assert(ok === false, 'dragging an actor out of turn is rejected');
    assert(
      shell.calls.systemMessages.length === 1
        && shell.calls.systemMessages[0].kind === 'error'
        && shell.calls.systemMessages[0].text === 'You can only move an actor on its own turn.',
      'out-of-turn drag rejection is surfaced to the user with an explanatory message'
    );
    assert(
      shell.calls.combat.length === 0 && shell.calls.roomMove.length === 0,
      'a rejected drag issues no server movement request at all'
    );
  }

  {
    const shell = buildShell({ validation: { valid: false, reason: 'Drop target is not a valid hex.' } });
    await shell.handleMapActorDrop({
      entity: { id: 'pc-908-1033' }, sourceQ: 0, sourceR: 0, targetQ: 99, targetR: 99,
    });
    assert(
      shell.calls.systemMessages[0]?.text === 'Drop target is not a valid hex.',
      'invalid drop targets surface the validation reason to the user'
    );
  }

  {
    const shell = buildShell({ combatPlan: { valid: false, reason: 'No movement actions remain.' } });
    await shell.handleMapActorDrop({
      entity: { id: 'pc-908-1033' }, sourceQ: 0, sourceR: 0, targetQ: 2, targetR: 2,
    });
    assert(
      shell.calls.systemMessages[0]?.text === 'No movement actions remain.',
      'an illegal combat movement plan surfaces its reason to the user'
    );
  }

  console.log('\n===============================================');
  console.log(`Passed: ${passed}`);
  console.log(`Failed: ${failed}`);
  if (failed > 0) {
    console.error('SOME TESTS FAILED');
    process.exit(1);
  }
  console.log('ALL TESTS PASSED');
})();
