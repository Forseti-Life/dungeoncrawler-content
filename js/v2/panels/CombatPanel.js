/**
 * @file panels/CombatPanel.js
 *
 * Initiative tracker, current turn indicator, round counter, combat controls.
 * Methods ported verbatim from hexmap.js UIManager.
 */

import { CombatState } from '../../ecs/systems/TurnManagementSystem.js';

export class CombatPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
  }

  init() {
    const id = (k) => document.getElementById(k);
    const s = (k) => this.container?.querySelector(`[data-combat="${k}"]`) || null;
    this._el = {
      // Map both by ID (original) and data-combat attribute (v2 template)
      currentTurn:      id('current-turn')      || s('turn-name'),
      currentRound:     id('current-round')     || s('round-counter'),
      initiativeList:   id('initiative-list')   || s('initiative-list'),
      initiativeTracker: id('initiative-tracker') || s('tracker-wrap'),
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
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[CombatPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._bindDom();
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _bindDom() {
    const { startCombatBtn, endCombatBtn } = this._el;
    if (startCombatBtn) {
      const onStart = () => this.bus.emit('user:combat-start');
      startCombatBtn.addEventListener('click', onStart);
      this._unsubs.push(() => startCombatBtn.removeEventListener('click', onStart));
    }
    if (endCombatBtn) {
      const onEnd = () => this.bus.emit('user:combat-end');
      endCombatBtn.addEventListener('click', onEnd);
      this._unsubs.push(() => endCombatBtn.removeEventListener('click', onEnd));
    }
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('combat:turn-changed', (d) => {
        this.updateCurrentTurn(d || {});
      }),
      this.bus.on('combat:round-changed',  (d) => this.updateRound(d?.roundNumber)),
      this.bus.on('combat:state-changed',  (d) => this.updateCombatControls(d?.state)),
      this.bus.on('combat:order-changed',  (d) => this.updateInitiativeTracker(d?.order)),
    );
  }

  updateCombatControls(combatState) {
    const isInactive = (combatState === CombatState.INACTIVE || combatState === CombatState.ENDED);

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
      this._el.turnOwner.textContent = isInactive ? 'No active combat' : 'Active encounter';
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
      this._el.turnOwner.textContent = isPlayersTurn ? 'Your turn' : (team ? `${team} turn` : 'Awaiting combat');
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
        <span class="chip ${moveLeft ? 'chip-live' : 'chip-dim'}">Navigate</span>
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
        this._el.actionInstruction.textContent = 'Navigate to a blue hex, then end turn.';
      } else {
        this._el.actionInstruction.hidden = false;
        this._el.actionInstruction.textContent = 'No actions left — end your turn.';
      }
    }
  }

  updateInitiativeTracker(initiativeOrder) {
    if (!this._el.initiativeList) return;

    let html = '';
    initiativeOrder.forEach((data) => {
      const entity = data?.entity || null;
      const combat = entity?.getComponent?.('CombatComponent') || null;
      const stats = entity?.getComponent?.('StatsComponent') || null;
      const actions = entity?.getComponent?.('ActionsComponent') || null;
      const role = String(data?.role || '').trim().toLowerCase();
      const team = combat?.team || (role === 'player' ? 'player' : 'neutral');
      const teamLabels = { player: 'Player', enemy: 'Enemy', ally: 'Ally', neutral: 'NPC' };
      const teamLabel = teamLabels[team] || team;

      // HP bar — exact values only for player team (AC-004 visibility rule)
      let hpHtml = '';
      if (stats && stats.maxHp > 0) {
        const pct = Math.max(0, Math.min(100, Math.round((stats.currentHp / stats.maxHp) * 100)));
        let hpStateClass = 'hp-bar--healthy';
        if (pct <= 0) hpStateClass = 'hp-bar--defeated';
        else if (pct <= 25) hpStateClass = 'hp-bar--critical';
        else if (pct <= 50) hpStateClass = 'hp-bar--bloodied';
        const hpLabel = team === 'player' ? `${stats.currentHp}/${stats.maxHp}` : '';
        hpHtml = `<div class="rail-card__hp-wrap" title="${hpLabel || 'HP status'}">
            <div class="rail-card__hp-track"><div class="rail-card__hp-bar ${hpStateClass}" style="width:${pct}%"></div></div>
            ${hpLabel ? `<span class="rail-card__hp-label">${hpLabel}</span>` : ''}
          </div>`;
      }

      // Action pips — only shown on active combatant (AC-001 compact status cues)
      let actionsHtml = '';
      if (data.isCurrent && actions) {
        const maxA = actions.maxActions || 3;
        let pips = '';
        for (let i = 0; i < maxA; i++) {
          const spent = i >= actions.actionsRemaining;
          pips += `<span class="rail-card__pip ${spent ? 'pip--spent' : 'pip--ready'}" title="${spent ? 'Action spent' : 'Action ready'}"></span>`;
        }
        const rxClass = actions.hasReaction ? 'pip--reaction-ready' : 'pip--reaction-spent';
        pips += `<span class="rail-card__pip rail-card__pip--reaction ${rxClass}" title="${actions.hasReaction ? 'Reaction ready' : 'Reaction spent'}">R</span>`;
        actionsHtml = `<div class="rail-card__actions">${pips}</div>`;
      }

      const isCurrent = Boolean(data?.isCurrent);
      const isDefeated = Boolean(data?.isDefeated);
      const displayName = String(
        data?.name
        || entity?.getComponent?.('IdentityComponent')?.name
        || entity?.name
        || entity?.actorName
        || 'Unknown combatant'
      ).trim();
      const entityId = data?.entityId || entity?.id || displayName;
      const activeClass = isCurrent ? 'rail-card--active' : '';
      const defeatedClass = isDefeated ? 'rail-card--defeated' : '';
      html += `<div class="initiative-item rail-card ${activeClass} ${defeatedClass}" data-entity-id="${entityId}" role="button" tabindex="0" aria-label="${displayName}${isCurrent ? ' — active turn' : ''}">
          <div class="rail-card__header">
            <span class="rail-card__init">${data?.initiative ?? '-'}</span>
            <span class="rail-card__name">${displayName}</span>
            <span class="rail-card__team-badge rail-card__team--${team}">${teamLabel}</span>
          </div>
          ${hpHtml}
          ${actionsHtml}
        </div>`;
    });
    this._el.initiativeList.innerHTML = html;
  }

  updateRound(roundNumber) {
    if (this._el.currentRound) {
      this._el.currentRound.textContent = `Round ${roundNumber}`;
    }
  }

}
