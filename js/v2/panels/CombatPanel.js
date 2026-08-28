/**
 * @file panels/CombatPanel.js
 *
 * Initiative tracker, current turn indicator, round counter, combat controls.
 * Methods ported verbatim from hexmap.js UIManager.
 */

function resolveBooleanFlag(value) {
  if (typeof value === 'boolean') {
    return value;
  }
  if (typeof value === 'number') {
    return value > 0;
  }
  const normalized = String(value ?? '').trim().toLowerCase();
  if (normalized === '') {
    return false;
  }
  if (['1', 'true', 'yes', 'y'].includes(normalized)) {
    return true;
  }
  if (['0', 'false', 'no', 'n', 'null', 'undefined'].includes(normalized)) {
    return false;
  }
  return Boolean(value);
}

export class CombatPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.dungeonData = null;
    this.stateManager = null;
    this.currentRoundNumber = 0;
    this.lastEncounterStatus = 'idle';
    this._lastAuthoritativeSnapshotKey = '';
    this._pendingStatusRequests = new Map();
    this._manualEncounterStatusLabel = '';
    this._authoritativeGameEventsHandler = (event) => this.handleAuthoritativeGameEvents(event);
  }

  init(dungeonData = {}, stateManager = {}) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => (typeof document !== 'undefined' ? document.getElementById(k) : null);
    const s = (k) => this.container?.querySelector(`[data-combat="${k}"]`) || null;
    this._el = {
      // Map both by ID (original) and data-combat attribute (v2 template)
      currentTurn:      id('current-turn')      || s('turn-name'),
      currentRound:     id('current-round')     || s('round-counter'),
      initiativeList:   id('initiative-list')   || s('initiative-list'),
      mapInitiativeList: id('map-initiative-list') || s('map-initiative-list'),
      initiativeTracker: id('initiative-tracker') || s('tracker-wrap'),
      mapInitiativeTracker: id('map-initiative-tracker') || s('map-tracker-wrap'),
      mapRoundDisplay:  id('map-round-display') || s('map-round-display'),
      mapEncounterState: id('map-encounter-state') || s('map-encounter-state'),
      mapTurnCounter:   id('map-turn-counter') || s('map-turn-counter'),
      mapCurrentTurnName: id('map-current-turn-name') || s('map-current-turn-name'),
      mapNextTurnName:  id('map-next-turn-name') || s('map-next-turn-name'),
      turnOwner:        id('turn-owner')        || s('turn-label'),
      turnActionSummary: id('turn-action-summary') || s('turn-actions'),
      turnMoveSummary:  id('turn-move-summary') || s('turn-movement'),
      turnReaction:     id('turn-reaction')     || s('turn-reaction'),
      startCombatBtn:   id('start-combat')      || s('start-btn'),
      endCombatBtn:     id('end-combat')        || s('end-combat-btn'),
      turnHud:          id('turn-hud'),
      turnActionChips:  id('turn-action-chips'),
      actionInstruction: id('action-instruction'),
    };
    this._ensureMapInitiativeOverlay();
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[CombatPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    if (this._el.mapInitiativeTracker) {
      this._el.mapInitiativeTracker.style.display = 'block';
    }
    this._bindDom();
    this._bindMapInitiativeHandlers();
    this._subscribe();
    if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
      window.addEventListener('dungeoncrawler:game-events', this._authoritativeGameEventsHandler);
    }
    this.updateInitiativeTracker([]);
    this.renderEncounterSnapshot();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (typeof window !== 'undefined' && typeof window.removeEventListener === 'function') {
      window.removeEventListener('dungeoncrawler:game-events', this._authoritativeGameEventsHandler);
    }
  }

  _bindDom() {
    const { startCombatBtn, endCombatBtn } = this._el;
    if (startCombatBtn) {
      const onStart = () => this.bus.emit('user:combat-start');
      startCombatBtn.addEventListener('click', onStart);
      this._unsubs.push(() => {
        if (typeof startCombatBtn.removeEventListener === 'function') {
          startCombatBtn.removeEventListener('click', onStart);
        }
      });
    }
    if (endCombatBtn) {
      const onEnd = () => this.bus.emit('user:combat-end');
      endCombatBtn.addEventListener('click', onEnd);
      this._unsubs.push(() => {
        if (typeof endCombatBtn.removeEventListener === 'function') {
          endCombatBtn.removeEventListener('click', onEnd);
        }
      });
    }
  }

  _bindMapInitiativeHandlers() {
    const list = this._el.mapInitiativeList;
    if (!list) {
      return;
    }

    const onClickHandler = (e) => {
      const card = e.target.closest('.rail-card[data-entity-id]');
      if (!card) {
        return;
      }
      this._selectInitiativeEntity(card.dataset.entityId);
    };

    const onKeydownHandler = (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') {
        return;
      }
      const card = e.target.closest('.rail-card[data-entity-id]');
      if (!card) {
        return;
      }
      this._selectInitiativeEntity(card.dataset.entityId);
    };

    list.addEventListener('click', onClickHandler);
    list.addEventListener('keydown', onKeydownHandler);
    this._unsubs.push(() => {
      if (typeof list.removeEventListener === 'function') {
        list.removeEventListener('click', onClickHandler);
        list.removeEventListener('keydown', onKeydownHandler);
      }
    });
  }

  _selectInitiativeEntity(entityId) {
    if (!entityId) {
      return;
    }
    const hexmap = this.stateManager?.hexmap;
    const entity = hexmap?.entityManager?.getEntity?.(entityId) || null;
    if (entity) {
      hexmap.selectEntity?.(entity);
    }
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('combat:turn-changed', () => this.renderEncounterSnapshot()),
      this.bus.on('combat:round-changed', () => this.renderEncounterSnapshot()),
      this.bus.on('combat:state-changed', () => this.renderEncounterSnapshot()),
      this.bus.on('combat:order-changed', () => this.renderEncounterSnapshot()),
      this.bus.on('game:state-refreshed',  (d) => this.renderEncounterSnapshot({ phaseSnapshot: d?.phaseSnapshot || null })),
      this.bus.on('game:backend-request-start', (d) => this.handleBackendStatusRequestStart(d)),
      this.bus.on('game:backend-request-end', (d) => this.handleBackendStatusRequestEnd(d)),
    );
  }

  formatEncounterStatusLabel(statusRaw = '', combatState = 'inactive') {
    const normalized = String(statusRaw || '').trim().toLowerCase();
    if (normalized === 'active') {
      return 'Active encounter';
    }
    if (normalized === 'setup' || normalized === 'rolling_initiative') {
      return 'Rolling initiative';
    }
    if (normalized === 'paused') {
      return 'Encounter paused';
    }
    if (normalized === 'ended') {
      return 'Encounter ended';
    }
    if (combatState === 'ended') {
      return 'Encounter ended';
    }
    return 'No active combat';
  }

  formatTurnCounter(currentIndex, totalTurns) {
    const index = Number(currentIndex);
    const total = Number(totalTurns);
    if (!Number.isFinite(index) || index < 0 || !Number.isFinite(total) || total <= 0) {
      return 'Turn -';
    }
    return `Turn ${index + 1}/${total}`;
  }

  resolveEncounterEntityByRef(entityRef = '') {
    const ref = String(entityRef || '').trim();
    const hexmap = this.stateManager?.hexmap || null;
    if (!ref || !hexmap?.entityManager?.getEntitiesWith) {
      return null;
    }
    const entities = hexmap.entityManager.getEntitiesWith('PositionComponent') || [];
    return entities.find((entity) => [
      entity?.dcEntityRef,
      entity?.dcEntityInstanceId,
      entity?.instanceId,
      entity?.id,
    ].map((value) => String(value || '').trim()).filter(Boolean).includes(ref)) || null;
  }

  resolveCombatStateFromSnapshot(phaseSnapshot = {}) {
    const phase = String(phaseSnapshot?.phase || '').trim().toLowerCase();
    const encounterId = Number(phaseSnapshot?.encounterId || 0);
    if (phase !== 'encounter' || !Number.isFinite(encounterId) || encounterId <= 0) {
      return 'inactive';
    }
    return 'in_progress';
  }

  buildInitiativeOrderFromSnapshot(phaseSnapshot = {}) {
    const initiativeOrder = Array.isArray(phaseSnapshot?.initiativeOrder) ? phaseSnapshot.initiativeOrder : [];
    const turnEntityRef = String(
      phaseSnapshot?.actionContract?.actor_id
      || phaseSnapshot?.turn?.entity
      || ''
    ).trim();
    const turnIndex = Number(phaseSnapshot?.turn?.index);
    return initiativeOrder.map((entry, index) => {
      const entryEntityRef = String(
        entry?.entity_id
        || entry?.entityId
        || entry?.entity_ref
        || entry?.participant_ref
        || entry?.id
        || ''
      ).trim();
      const entity = this.resolveEncounterEntityByRef(entryEntityRef);
      const isCurrent = (
        (turnEntityRef && entryEntityRef && turnEntityRef === entryEntityRef)
        || (Number.isFinite(turnIndex) && turnIndex === index)
      );
      return {
        ...entry,
        entity,
        entityId: entryEntityRef || entity?.id || '',
        name: String(entry?.name || entity?.getComponent?.('IdentityComponent')?.name || entryEntityRef || 'Unknown combatant').trim(),
        initiative: Number.isFinite(Number(entry?.initiative)) ? Number(entry.initiative) : null,
        isCurrent,
        actions_remaining: isCurrent ? Number(phaseSnapshot?.turn?.actions_remaining) : Number(entry?.actions_remaining),
        reaction_available: isCurrent
          ? Boolean(phaseSnapshot?.turn?.reaction_available)
          : (typeof entry?.reaction_available === 'boolean' ? entry.reaction_available : null),
      };
    });
  }

  renderEncounterSnapshot(options = {}) {
    const phaseSnapshot = options?.phaseSnapshot && typeof options.phaseSnapshot === 'object'
      ? options.phaseSnapshot
      : (this.stateManager?.hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || null);
    if (phaseSnapshot && typeof phaseSnapshot === 'object') {
      const encounterId = Number(phaseSnapshot?.encounterId || 0);
      const turnEntityRef = String(
        phaseSnapshot?.actionContract?.actor_id
        || phaseSnapshot?.turn?.entity
        || ''
      ).trim();
      const turnIndex = Number(phaseSnapshot?.turn?.index);
      const roundNumber = Number(phaseSnapshot?.round || 0);
      const order = this.buildInitiativeOrderFromSnapshot(phaseSnapshot);
      const signature = JSON.stringify({
        encounterId,
        turnEntityRef,
        turnIndex: Number.isFinite(turnIndex) ? turnIndex : -1,
        roundNumber: Number.isFinite(roundNumber) ? roundNumber : 0,
        orderLength: order.length,
      });
      if (signature === this._lastAuthoritativeSnapshotKey) {
        return true;
      }
      this._lastAuthoritativeSnapshotKey = signature;

      const combatState = this.resolveCombatStateFromSnapshot(phaseSnapshot);
      const statusRaw = combatState === 'in_progress'
        ? (turnEntityRef ? 'active' : 'setup')
        : 'idle';
      this.updateCombatControls({
        state: combatState,
        statusRaw,
        roundNumber: Number.isFinite(roundNumber) ? roundNumber : 0,
        turnIndex: Number.isFinite(turnIndex) ? turnIndex : -1,
        totalTurns: order.length,
      });
      this.updateRound(Number.isFinite(roundNumber) ? roundNumber : 0);
      this.updateInitiativeTracker(order);

      const currentEntity = this.resolveEncounterEntityByRef(turnEntityRef);
      if (currentEntity) {
        const identity = currentEntity.getComponent?.('IdentityComponent') || null;
        const combat = currentEntity.getComponent?.('CombatComponent') || null;
        const actions = currentEntity.getComponent?.('ActionsComponent') || null;
        const movement = currentEntity.getComponent?.('MovementComponent') || null;
        const launchPlayer = this.stateManager?.hexmap?.findLaunchPlayerEntity?.() || null;
        this.updateCurrentTurn({
          entity: currentEntity,
          name: identity?.name || currentEntity?.name || currentEntity?.actorName || 'Unknown combatant',
          actions,
          movement,
          hasReaction: typeof phaseSnapshot?.turn?.reaction_available === 'boolean'
            ? Boolean(phaseSnapshot.turn.reaction_available)
            : (typeof actions?.hasReactionAvailable === 'function'
              ? actions.hasReactionAvailable()
              : Boolean(actions?.hasReaction)),
          team: combat?.team || null,
          isPlayersTurn: Boolean(currentEntity && launchPlayer && currentEntity.id === launchPlayer.id),
        });
      }
      return;
    }

    const turnManagement = this.stateManager?.hexmap?.turnManagementSystem || null;
    if (!turnManagement) {
      return false;
    }

    this.updateCombatControls({
      state: turnManagement.combatState,
      statusRaw: turnManagement.getEncounterStatus?.() || 'idle',
      roundNumber: turnManagement.currentRound || 0,
      turnIndex: turnManagement.currentTurnIndex,
      totalTurns: Array.isArray(turnManagement.initiativeOrder) ? turnManagement.initiativeOrder.length : 0,
    });
    this.updateRound(turnManagement.currentRound || 0);
    this.updateInitiativeTracker(turnManagement.getInitiativeOrder?.() || []);

    const currentEntity = turnManagement.getCurrentTurnEntity?.() || null;
    if (!currentEntity) {
      return;
    }
    const identity = currentEntity.getComponent?.('IdentityComponent') || null;
    const combat = currentEntity.getComponent?.('CombatComponent') || null;
    const actions = currentEntity.getComponent?.('ActionsComponent') || null;
    const movement = currentEntity.getComponent?.('MovementComponent') || null;
    const launchPlayer = this.stateManager?.hexmap?.findLaunchPlayerEntity?.() || null;
    this.updateCurrentTurn({
      entity: currentEntity,
      name: identity?.name || currentEntity?.name || currentEntity?.actorName || 'Unknown combatant',
      actions,
      movement,
      hasReaction: typeof actions?.hasReactionAvailable === 'function'
        ? actions.hasReactionAvailable()
        : Boolean(actions?.hasReaction),
      team: combat?.team || null,
      isPlayersTurn: Boolean(currentEntity && launchPlayer && currentEntity.id === launchPlayer.id),
    });
    return true;
  }

  updateCombatControls(payload = {}) {
    const combatState = payload?.state;
    const statusRaw = String(payload?.statusRaw || '').trim().toLowerCase();
    const isInactive = (combatState === 'inactive' || combatState === 'ended');
    this.lastEncounterStatus = statusRaw || (isInactive ? 'idle' : this.lastEncounterStatus);

    if (this._el.startCombatBtn) {
      this._el.startCombatBtn.style.display = isInactive ? 'inline-block' : 'none';
    }
    if (this._el.endCombatBtn) {
      this._el.endCombatBtn.style.display = isInactive ? 'none' : 'inline-block';
    }
    if (this._el.initiativeTracker) {
      this._el.initiativeTracker.style.display = isInactive ? 'none' : 'block';
    }
    if (this._el.turnHud) {
      this._el.turnHud.classList.toggle('hud-inactive', isInactive);
    }
    if (this._el.turnOwner) {
      const baseLabel = this.formatEncounterStatusLabel(statusRaw, combatState);
      this._el.turnOwner.textContent = this._resolveEncounterStatusLabel(baseLabel, isInactive, statusRaw);
    }
    if (this._el.mapEncounterState) {
      const baseLabel = this.formatEncounterStatusLabel(statusRaw, combatState);
      this._el.mapEncounterState.textContent = this._resolveEncounterStatusLabel(baseLabel, isInactive, statusRaw);
    }
    if (this._el.mapTurnCounter) {
      this._el.mapTurnCounter.textContent = this.formatTurnCounter(payload?.turnIndex, payload?.totalTurns);
    }
    if (isInactive) {
      this.updateRound(0);
      this.updateInitiativeTracker([]);
      if (this._el.currentTurn) {
        this._el.currentTurn.textContent = 'No active turn';
      }
      if (this._el.turnActionSummary) {
        this._el.turnActionSummary.textContent = 'Actions: -';
      }
      if (this._el.turnMoveSummary) {
        this._el.turnMoveSummary.textContent = 'Movement: -';
      }
      if (this._el.turnReaction) {
        this._el.turnReaction.textContent = 'Reaction: ready';
        this._el.turnReaction.classList.remove('pill-positive');
        this._el.turnReaction.classList.add('pill-muted');
      }
      if (this._el.actionInstruction) {
        this._el.actionInstruction.hidden = true;
        this._el.actionInstruction.textContent = '';
      }
    }
    if (!isInactive && statusRaw === 'active') {
      this._manualEncounterStatusLabel = '';
    }

  }

  handleBackendStatusRequestStart(data = {}) {
    const requestId = String(data?.requestId || '').trim();
    if (!requestId) {
      return;
    }
    const source = String(data?.source || '').trim().toLowerCase();
    const label = this.resolveBackendStatusLabel(source, data?.label);
    this._pendingStatusRequests.set(requestId, {
      source,
      label,
      startedAt: Date.now(),
    });
    this.applyPendingEncounterStatusLabel();
  }

  handleBackendStatusRequestEnd(data = {}) {
    const requestId = String(data?.requestId || '').trim();
    if (!requestId) {
      return;
    }
    this._pendingStatusRequests.delete(requestId);
    this.applyPendingEncounterStatusLabel();
  }

  resolveBackendStatusLabel(source = '', fallbackLabel = '') {
    const normalized = String(source || '').trim().toLowerCase();
    if (normalized === 'runtime-state') {
      return 'Hydrating runtime state...';
    }
    if (normalized === 'chat-history') {
      return 'Hydrating room transcript...';
    }
    if (normalized === 'encounter-bootstrap') {
      return 'Initializing encounter system...';
    }
    if (normalized === 'room-view') {
      return 'Loading room view...';
    }
    return String(fallbackLabel || '').trim() || 'Loading...';
  }

  applyPendingEncounterStatusLabel() {
    if (!this._pendingStatusRequests.size) {
      this._manualEncounterStatusLabel = '';
      this.renderEncounterSnapshot();
      return;
    }
    const next = Array.from(this._pendingStatusRequests.values())
      .sort((left, right) => Number(left?.startedAt || 0) - Number(right?.startedAt || 0))[0];
    this._manualEncounterStatusLabel = String(next?.label || '').trim();
    this.renderEncounterSnapshot();
  }

  _resolveEncounterStatusLabel(baseLabel = '', isInactive = false, statusRaw = '') {
    const status = String(statusRaw || '').trim().toLowerCase();
    const canOverride = isInactive || status === 'setup' || status === 'rolling_initiative' || status === '';
    if (canOverride && this._manualEncounterStatusLabel) {
      return this._manualEncounterStatusLabel;
    }
    return baseLabel;
  }

  handleAuthoritativeGameEvents(customEvent = {}) {
    const events = Array.isArray(customEvent?.detail?.events) ? customEvent.detail.events : [];
    if (!events.length) {
      return;
    }
    let sawEncounterStarted = false;
    let sawRoundOrTurnStart = false;
    let latestRound = null;
    for (const gameEvent of events) {
      const type = String(gameEvent?.type || '').trim().toLowerCase();
      if (type === 'encounter_started') {
        sawEncounterStarted = true;
      }
      if (type === 'round_start' || type === 'turn_start') {
        sawRoundOrTurnStart = true;
        const parsedRound = Number(gameEvent?.data?.round);
        if (Number.isFinite(parsedRound) && parsedRound > 0) {
          latestRound = parsedRound;
        }
      }
    }

    if (sawEncounterStarted) {
      this._manualEncounterStatusLabel = 'Combat initiated. Rolling initiative...';
    }
    if (sawRoundOrTurnStart) {
      this._manualEncounterStatusLabel = 'Active encounter';
      if (Number.isFinite(latestRound) && latestRound > 0) {
        this.updateRound(latestRound);
      }
    }
    if (sawEncounterStarted || sawRoundOrTurnStart) {
      this.renderEncounterSnapshot();
    }
  }

  updateCurrentTurn(payload = {}) {
    const entity = payload?.entity || null;
    const identity = entity?.getComponent?.('IdentityComponent') || null;
    const combat = entity?.getComponent?.('CombatComponent') || null;
    const actions = payload?.actions || entity?.getComponent?.('ActionsComponent') || null;
    const movement = payload?.movement || entity?.getComponent?.('MovementComponent') || null;
    const team = payload?.team || combat?.team || null;
    const isPlayersTurn = typeof payload?.isPlayersTurn === 'boolean'
      ? payload.isPlayersTurn
      : team === 'player';
    const hasReaction = typeof payload?.hasReaction === 'boolean'
      ? payload.hasReaction
      : (typeof actions?.hasReactionAvailable === 'function'
        ? actions.hasReactionAvailable()
        : Boolean(actions?.hasReaction));
    const name = String(
      payload?.name
      || identity?.name
      || entity?.name
      || entity?.actorName
      || 'Unknown combatant'
    ).trim();

    if (this._el.currentTurn) {
      const turnLabel = isPlayersTurn ? 'Your turn' : (team ? `${team} turn` : 'Turn');
      const reactionBadge = hasReaction ? '<span class="pill pill-positive">Reaction ready</span>' : '<span class="pill pill-muted">Reaction spent</span>';
      this._el.currentTurn.innerHTML = `
        <div class="turn-name">${name}</div>
        <div class="turn-sub">
          <span class="pill pill-strong">${turnLabel}</span>
          ${reactionBadge}
        </div>`;
    }

    if (this._el.turnOwner) {
      const encounterLabel = this.lastEncounterStatus === 'paused'
        ? 'Encounter paused'
        : (isPlayersTurn ? 'Your turn' : (team ? `${team} turn` : 'Awaiting combat'));
      this._el.turnOwner.textContent = encounterLabel;
    }

    const maxActions = actions ? actions.maxActions + (actions.actionBonus || 0) : null;
    if (this._el.turnActionSummary) {
      const remaining = actions ? `${actions.actionsRemaining}/${maxActions} actions` : 'Actions: -';
      this._el.turnActionSummary.textContent = remaining;
    }

    if (this._el.turnMoveSummary) {
      const moveText = movement && Number.isFinite(movement.movementRemaining)
        ? `${movement.movementRemaining} ft left`
        : 'Movement: -';
      this._el.turnMoveSummary.textContent = moveText;
    }

    if (this._el.turnReaction) {
      this._el.turnReaction.textContent = hasReaction ? 'Reaction ready' : 'Reaction spent';
      this._el.turnReaction.classList.toggle('pill-positive', !!hasReaction);
      this._el.turnReaction.classList.toggle('pill-muted', !hasReaction);
    }

    if (this._el.turnActionChips) {
      const canAct = actions ? actions.actionsRemaining > 0 : false;
      const moveLeft = movement ? movement.movementRemaining > 0 : false;
      this._el.turnActionChips.innerHTML = `
        <span class="chip ${moveLeft ? 'chip-live' : 'chip-dim'}">Movement</span>
        <span class="chip ${canAct ? 'chip-live' : 'chip-dim'}">Strike</span>
        <span class="chip ${canAct ? 'chip-live' : 'chip-dim'}">Search</span>
        <span class="chip chip-live">Talk</span>
        <span class="chip chip-end">End Turn</span>`;
    }

    if (this._el.actionInstruction) {
      if (!isPlayersTurn) {
        this._el.actionInstruction.hidden = false;
        this._el.actionInstruction.textContent = 'Watching enemy turn...';
      } else if (actions && actions.actionsRemaining > 0) {
        this._el.actionInstruction.hidden = false;
        this._el.actionInstruction.textContent = 'Select a hostile target to strike or click a blue hex to navigate.';
      } else if (movement && movement.movementRemaining > 0) {
        this._el.actionInstruction.hidden = false;
        this._el.actionInstruction.textContent = 'Move to a blue hex, then end turn.';
      } else {
        this._el.actionInstruction.hidden = false;
        this._el.actionInstruction.textContent = 'No actions left — end your turn.';
      }
    }
  }

  updateInitiativeTracker(initiativeOrder) {
    const standardLists = [this._el.initiativeList].filter(Boolean);
    const mapList = this._el.mapInitiativeList || null;
    if (!standardLists.length && !mapList) return;

    let standardHtml = '';
    let mapHtml = '';
    const order = Array.isArray(initiativeOrder) ? initiativeOrder : [];
    let currentName = 'Unavailable';
    let nextName = 'Unavailable';
    const currentIndex = order.findIndex((entry) => resolveBooleanFlag(entry?.isCurrent ?? entry?.is_current));
    if (currentIndex >= 0) {
      const current = order[currentIndex];
      const next = order[(currentIndex + 1) % order.length] || null;
      currentName = String(current?.name || current?.entity?.getComponent?.('IdentityComponent')?.name || 'Unavailable').trim() || 'Unavailable';
      nextName = String(next?.name || next?.entity?.getComponent?.('IdentityComponent')?.name || 'Unavailable').trim() || 'Unavailable';
    } else if (order.length > 0) {
      currentName = String(order[0]?.name || order[0]?.entity?.getComponent?.('IdentityComponent')?.name || 'Unavailable').trim() || 'Unavailable';
      nextName = String(order[1]?.name || order[1]?.entity?.getComponent?.('IdentityComponent')?.name || currentName).trim() || currentName;
    }

    order.forEach((data) => {
      const entity = data?.entity || null;
      const combat = entity?.getComponent?.('CombatComponent') || null;
      const stats = entity?.getComponent?.('StatsComponent') || null;
      const actions = entity?.getComponent?.('ActionsComponent') || null;
      const role = String(data?.role || '').trim().toLowerCase();
      const team = String(
        data?.team
        || combat?.team
        || (role === 'player' ? 'player' : 'neutral')
      ).trim().toLowerCase() || 'neutral';
      const teamLabels = { player: 'Player', enemy: 'Enemy', ally: 'Ally', neutral: 'NPC' };
      const teamLabel = teamLabels[team] || team;

      // HP bar — exact values only for player team (AC-004 visibility rule)
      let hpHtml = '';
      const cardHp = data?.hp && typeof data.hp === 'object' ? data.hp : null;
      const currentHp = Number.isFinite(Number(cardHp?.current))
        ? Number(cardHp.current)
        : (stats ? Number(stats.currentHp) : NaN);
      const maxHp = Number.isFinite(Number(cardHp?.max))
        ? Number(cardHp.max)
        : (stats ? Number(stats.maxHp) : NaN);
      if (Number.isFinite(maxHp) && maxHp > 0) {
        const pct = Math.max(0, Math.min(100, Math.round((currentHp / maxHp) * 100)));
        let hpStateClass = 'hp-bar--healthy';
        if (pct <= 0) hpStateClass = 'hp-bar--defeated';
        else if (pct <= 25) hpStateClass = 'hp-bar--critical';
        else if (pct <= 50) hpStateClass = 'hp-bar--bloodied';
        const hpVisibility = String(cardHp?.visibility || '').trim().toLowerCase();
        const showExactHp = hpVisibility
          ? hpVisibility === 'full'
          : team === 'player';
        const hpLabel = showExactHp ? `${currentHp}/${maxHp}` : '';
        hpHtml = `<div class="rail-card__hp-wrap" title="${hpLabel || 'HP status'}">
            <div class="rail-card__hp-track"><div class="rail-card__hp-bar ${hpStateClass}" style="width:${pct}%"></div></div>
            ${hpLabel ? `<span class="rail-card__hp-label">${hpLabel}</span>` : ''}
          </div>`;
      }

      // Action pips — only shown on active combatant (AC-001 compact status cues)
      let actionsHtml = '';
      const isCurrent = resolveBooleanFlag(data?.isCurrent ?? data?.is_current);
      const actionsRemaining = Number.isFinite(Number(data?.actionsRemaining))
        ? Number(data.actionsRemaining)
        : (Number.isFinite(Number(data?.actions_remaining)) ? Number(data.actions_remaining) : Number(actions?.actionsRemaining));
      const hasReaction = typeof data?.reactionAvailable === 'boolean'
        ? data.reactionAvailable
        : (typeof data?.reaction_available === 'boolean'
          ? data.reaction_available
          : (typeof actions?.hasReactionAvailable === 'function'
            ? actions.hasReactionAvailable()
            : Boolean(actions?.hasReaction)));
      if (isCurrent && Number.isFinite(actionsRemaining)) {
        const maxA = actions?.maxActions || Math.max(3, actionsRemaining);
        let pips = '';
        for (let i = 0; i < maxA; i++) {
          const spent = i >= actionsRemaining;
          pips += `<span class="rail-card__pip ${spent ? 'pip--spent' : 'pip--ready'}" title="${spent ? 'Action spent' : 'Action ready'}"></span>`;
        }
        const rxClass = hasReaction ? 'pip--reaction-ready' : 'pip--reaction-spent';
        pips += `<span class="rail-card__pip rail-card__pip--reaction ${rxClass}" title="${hasReaction ? 'Reaction ready' : 'Reaction spent'}">R</span>`;
        actionsHtml = `<div class="rail-card__actions">${pips}</div>`;
      }

      const isDefeated = resolveBooleanFlag(data?.isDefeated ?? data?.is_defeated);
      const displayName = String(
        data?.name
        || entity?.getComponent?.('IdentityComponent')?.name
        || entity?.name
        || entity?.actorName
        || 'Unknown combatant'
      ).trim();
      const entityId = data?.entityId || data?.entity_id || entity?.id || displayName;
      const activeClass = isCurrent ? 'rail-card--active' : '';
      const defeatedClass = isDefeated ? 'rail-card--defeated' : '';
      standardHtml += `<div class="initiative-item rail-card ${activeClass} ${defeatedClass}" data-entity-id="${entityId}" role="button" tabindex="0" aria-label="${displayName}${isCurrent ? ' — active turn' : ''}">
          <div class="rail-card__header">
            <span class="rail-card__init">${data?.initiative ?? '-'}</span>
            <span class="rail-card__name">${displayName}</span>
            <span class="rail-card__team-badge rail-card__team--${team}">${teamLabel}</span>
          </div>
          ${hpHtml}
          ${actionsHtml}
        </div>`;
      mapHtml += `<div class="initiative-item initiative-item--compact rail-card ${activeClass} ${defeatedClass}" data-entity-id="${entityId}" role="button" tabindex="0" aria-label="${displayName}${isCurrent ? ' — active turn' : ''}">
          <div class="rail-card__header">
            <span class="rail-card__name">${displayName}</span>
          </div>
        </div>`;
    });
    if (standardHtml === '') {
      standardHtml = `<div class="initiative-item rail-card">
        <div class="rail-card__header">
          <span class="rail-card__name">No active encounter</span>
        </div>
      </div>`;
    }
    if (mapHtml === '') {
      mapHtml = `<div class="initiative-item initiative-item--compact rail-card">
        <div class="rail-card__header">
          <span class="rail-card__name">No active encounter</span>
        </div>
      </div>`;
    }

    standardLists.forEach((listEl) => {
      listEl.innerHTML = standardHtml;
    });
    if (mapList) {
      mapList.innerHTML = mapHtml;
    }
    if (this._el.mapCurrentTurnName) {
      this._el.mapCurrentTurnName.textContent = currentName;
    }
    if (this._el.mapNextTurnName) {
      this._el.mapNextTurnName.textContent = nextName;
    }
    if (this._el.mapTurnCounter) {
      this._el.mapTurnCounter.textContent = this.formatTurnCounter(currentIndex, order.length);
    }
  }

  updateRound(roundNumber) {
    this.currentRoundNumber = Number.isFinite(Number(roundNumber)) ? Number(roundNumber) : 0;
    if (this._el.currentRound) {
      this._el.currentRound.textContent = `Round ${this.currentRoundNumber}`;
    }
    if (this._el.mapRoundDisplay) {
      this._el.mapRoundDisplay.textContent = `Round ${this.currentRoundNumber}`;
    }
  }

  _ensureMapInitiativeOverlay() {
    if (this._el.mapInitiativeTracker && this._el.mapInitiativeList) {
      return;
    }

    const host = document.getElementById('hexmap-canvas-container');
    if (!host) {
      return;
    }

    let tracker = document.getElementById('map-initiative-tracker');
    let list = document.getElementById('map-initiative-list');
    if (!tracker) {
      tracker = document.createElement('div');
      tracker.id = 'map-initiative-tracker';
      tracker.className = 'map-initiative-tracker';
      tracker.setAttribute('data-combat', 'map-tracker-wrap');
      tracker.style.cssText = [
        'position:absolute',
        'top:16px',
        'right:16px',
        'z-index:100000',
        'display:block',
      ].join(';');
      tracker.innerHTML = '<div class="tracker-header tracker-header--map"><div class="tracker-header__title-wrap"><span class="round-display" id="map-round-display" data-combat="map-round-display">Round 0</span></div><div class="map-initiative-summary"><div class="map-initiative-summary__row"><span class="map-initiative-summary__label">State</span><span class="map-initiative-summary__value" id="map-encounter-state" data-combat="map-encounter-state">No active combat</span></div><div class="map-initiative-summary__row"><span class="map-initiative-summary__label">Turn</span><span class="map-initiative-summary__value" id="map-turn-counter" data-combat="map-turn-counter">Turn -</span></div><div class="map-initiative-summary__row"><span class="map-initiative-summary__label">Current</span><span class="map-initiative-summary__value" id="map-current-turn-name" data-combat="map-current-turn-name">Unavailable</span></div><div class="map-initiative-summary__row"><span class="map-initiative-summary__label">Next</span><span class="map-initiative-summary__value" id="map-next-turn-name" data-combat="map-next-turn-name">Unavailable</span></div></div></div><div class="map-initiative-status"><div data-status="unavail-banner" class="server-unavail-banner" hidden><span>Server unavailable — reconnecting…</span></div><div data-status="backend-wait" class="backend-wait-banner" role="status" aria-live="polite" hidden><span class="backend-wait-banner__spinner" aria-hidden="true"></span><span data-backend-wait-label>Waiting for backend response...</span></div></div><div class="initiative-list" id="map-initiative-list" data-combat="map-initiative-list"></div><div class="map-initiative-chat"><div class="map-initiative-chat__header" id="map-action-log-heading">Action log</div><div class="hexmap-chat__log map-initiative-chat__log" id="map-initiative-chat-log" data-chat="map-initiative-log" aria-live="polite"><div class="chat-line chat-line--system">Narrative and action updates appear here.</div></div></div>';
      host.appendChild(tracker);
    }

    if (!list) {
      list = tracker.querySelector('#map-initiative-list');
    }

    this._el.mapInitiativeTracker = tracker || this._el.mapInitiativeTracker;
    this._el.mapInitiativeList = list || this._el.mapInitiativeList;
    this._el.mapRoundDisplay = tracker?.querySelector?.('#map-round-display') || this._el.mapRoundDisplay;
    this._el.mapEncounterState = tracker?.querySelector?.('#map-encounter-state') || this._el.mapEncounterState;
    this._el.mapTurnCounter = tracker?.querySelector?.('#map-turn-counter') || this._el.mapTurnCounter;
    this._el.mapCurrentTurnName = tracker?.querySelector?.('#map-current-turn-name') || this._el.mapCurrentTurnName;
    this._el.mapNextTurnName = tracker?.querySelector?.('#map-next-turn-name') || this._el.mapNextTurnName;
  }

}
