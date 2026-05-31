/**
 * @file panels/CombatPanel.js
 *
 * Renders initiative tracker, current turn indicator, round counter,
 * and combat state controls (start/end combat).
 *
 * Subscribes to bus events:
 *   combat:turn-changed    — { entity, turnIndex, totalTurns }
 *   combat:round-changed   — { roundNumber }
 *   combat:state-changed   — { state } ('active'|'inactive'|'ended')
 *   combat:damage-dealt    — { target, amount, remaining }
 *
 * Fires bus events:
 *   user:combat-start  — player requests combat start
 *   user:combat-end    — player requests combat end
 *   user:end-turn      — player ends their turn
 *
 * DOM selectors (all optional — panel degrades gracefully if elements missing):
 *   [data-combat="turn-name"]          Active entity name
 *   [data-combat="turn-label"]         "Your turn" / "Enemy turn" etc.
 *   [data-combat="turn-actions"]       Actions remaining summary
 *   [data-combat="turn-movement"]      Movement remaining summary
 *   [data-combat="turn-reaction"]      Reaction state badge
 *   [data-combat="round-counter"]      Round N label
 *   [data-combat="initiative-list"]    Initiative tracker container
 *   [data-combat="start-btn"]          Start combat button
 *   [data-combat="end-turn-btn"]       End turn button
 *   [data-combat="end-combat-btn"]     End combat button
 *   [data-combat="tracker-wrap"]       Wrapper hidden when inactive
 */

export class CombatPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
  }

  init() {
    this._bindElements();
    this._bindButtons();

    this._unsubs.push(
      this.bus.on('combat:turn-changed', (data) => this._onTurnChanged(data)),
      this.bus.on('combat:round-changed', ({ roundNumber } = {}) => this._onRoundChanged(roundNumber)),
      this.bus.on('combat:state-changed', ({ state } = {}) => this._onStateChanged(state)),
      this.bus.on('combat:damage-dealt', (data) => this._onDamageDealt(data)),
    );

    this._onStateChanged('inactive');
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Private — DOM binding
  // ---------------------------------------------------------------------------

  _bindElements() {
    const sel = (attr) => this.container?.querySelector(`[data-combat="${attr}"]`) ?? null;
    this._el = {
      turnName:       sel('turn-name'),
      turnLabel:      sel('turn-label'),
      turnActions:    sel('turn-actions'),
      turnMovement:   sel('turn-movement'),
      turnReaction:   sel('turn-reaction'),
      roundCounter:   sel('round-counter'),
      initiativeList: sel('initiative-list'),
      startBtn:       sel('start-btn'),
      endTurnBtn:     sel('end-turn-btn'),
      endCombatBtn:   sel('end-combat-btn'),
      trackerWrap:    sel('tracker-wrap'),
    };
  }

  _bindButtons() {
    const { startBtn, endTurnBtn, endCombatBtn } = this._el;
    if (startBtn)     startBtn.addEventListener('click',    () => this.bus.emit('user:combat-start'));
    if (endTurnBtn)   endTurnBtn.addEventListener('click',  () => this.bus.emit('user:end-turn'));
    if (endCombatBtn) endCombatBtn.addEventListener('click',() => this.bus.emit('user:combat-end'));
  }

  // ---------------------------------------------------------------------------
  // Private — event handlers
  // ---------------------------------------------------------------------------

  _onTurnChanged({ entity, turnIndex, totalTurns, initiativeOrder } = {}) {
    if (!entity) return;

    const identity  = entity.getComponent?.('IdentityComponent');
    const actions   = entity.getComponent?.('ActionsComponent');
    const movement  = entity.getComponent?.('MovementComponent');
    const combat    = entity.getComponent?.('CombatComponent');
    const name      = identity?.name ?? `Entity ${entity.id}`;
    const isPlayer  = combat?.isPlayerTeam?.() || combat?.team === 'player';

    const { turnName, turnLabel, turnActions, turnMovement, turnReaction } = this._el;

    if (turnName)  turnName.textContent  = name;
    if (turnLabel) turnLabel.textContent = isPlayer ? 'Your turn' : (combat?.team ? `${combat.team} turn` : 'Turn');

    if (turnActions) {
      const max = actions ? (actions.maxActions || 0) + (actions.actionBonus || 0) : null;
      turnActions.textContent = max !== null ? `${actions.actionsRemaining}/${max} actions` : 'Actions: —';
    }

    if (turnMovement) {
      const mv = movement?.movementRemaining;
      turnMovement.textContent = Number.isFinite(mv) ? `${mv} ft left` : 'Movement: —';
    }

    if (turnReaction) {
      const hasReaction = actions?.hasReaction ?? actions?.hasReactionAvailable?.() ?? false;
      turnReaction.textContent = hasReaction ? 'Reaction ready' : 'Reaction spent';
      turnReaction.classList.toggle('pill-positive', !!hasReaction);
      turnReaction.classList.toggle('pill-muted',    !hasReaction);
    }

    if (initiativeOrder) {
      this._renderInitiativeList(initiativeOrder);
    }
  }

  _onRoundChanged(roundNumber) {
    if (this._el.roundCounter) {
      this._el.roundCounter.textContent = `Round ${roundNumber ?? 1}`;
    }
  }

  _onStateChanged(state) {
    const inactive = (state === 'inactive' || state === 'ended');
    const { startBtn, endTurnBtn, endCombatBtn, trackerWrap } = this._el;

    if (startBtn)     startBtn.style.display     = inactive ? '' : 'none';
    if (endTurnBtn)   endTurnBtn.style.display    = inactive ? 'none' : '';
    if (endCombatBtn) endCombatBtn.style.display  = inactive ? 'none' : '';
    if (trackerWrap)  trackerWrap.style.display   = inactive ? 'none' : '';

    if (inactive && this._el.turnName)   this._el.turnName.textContent   = '—';
    if (inactive && this._el.turnLabel)  this._el.turnLabel.textContent  = 'No active combat';
    if (inactive && this._el.turnActions)  this._el.turnActions.textContent  = '';
    if (inactive && this._el.turnMovement) this._el.turnMovement.textContent = '';
  }

  /**
   * Flash a damage indicator on the correct initiative card.
   * @private
   */
  _onDamageDealt({ target } = {}) {
    if (!target?.id || !this._el.initiativeList) return;
    const card = this._el.initiativeList.querySelector(`[data-entity-id="${target.id}"]`);
    if (!card) return;
    card.classList.add('rail-card--damaged');
    setTimeout(() => card.classList.remove('rail-card--damaged'), 600);
  }

  // ---------------------------------------------------------------------------
  // Private — initiative list renderer
  // ---------------------------------------------------------------------------

  /**
   * Render rich participant cards in the initiative tracker.
   * HP is shown exactly for player-team; as a state bar for enemies.
   * Action pips are shown only on the active card.
   *
   * @param {Array<{entity, entityId, name, initiative, isCurrent, isDefeated}>} order
   * @private
   */
  _renderInitiativeList(order) {
    const list = this._el.initiativeList;
    if (!list) return;

    list.innerHTML = order.map((data) => {
      const combat  = data.entity?.getComponent?.('CombatComponent');
      const stats   = data.entity?.getComponent?.('StatsComponent');
      const actions = data.entity?.getComponent?.('ActionsComponent');
      const team    = combat?.team ?? 'neutral';
      const teamLabel = { player: 'Player', enemy: 'Enemy', ally: 'Ally', neutral: 'NPC' }[team] ?? team;

      const hpHtml = this._buildHpHtml(stats, team);
      const pipsHtml = data.isCurrent ? this._buildPipsHtml(actions) : '';
      const activeClass   = data.isCurrent  ? 'rail-card--active'   : '';
      const defeatedClass = data.isDefeated ? 'rail-card--defeated' : '';

      return `<div class="initiative-item rail-card ${activeClass} ${defeatedClass}"
                   data-entity-id="${data.entityId ?? ''}"
                   role="button" tabindex="0"
                   aria-label="${data.name}${data.isCurrent ? ' — active turn' : ''}">
        <div class="rail-card__header">
          <span class="rail-card__init">${data.initiative ?? '?'}</span>
          <span class="rail-card__name">${data.name ?? 'Unknown'}</span>
          <span class="rail-card__team-badge rail-card__team--${team}">${teamLabel}</span>
        </div>
        ${hpHtml}
        ${pipsHtml}
      </div>`;
    }).join('');

    // Wire click → entity:selected
    list.querySelectorAll('[data-entity-id]').forEach((card) => {
      card.addEventListener('click', () => {
        const entityId = card.dataset.entityId;
        if (entityId) this.bus.emit('user:select-entity', { entityId });
      });
    });
  }

  /** @private */
  _buildHpHtml(stats, team) {
    if (!stats || !stats.maxHp) return '';
    const pct = Math.max(0, Math.min(100, Math.round((stats.currentHp / stats.maxHp) * 100)));
    let barClass = 'hp-bar--healthy';
    if (pct <= 0)  barClass = 'hp-bar--defeated';
    else if (pct <= 25) barClass = 'hp-bar--critical';
    else if (pct <= 50) barClass = 'hp-bar--bloodied';
    const label = team === 'player' ? `${stats.currentHp}/${stats.maxHp}` : '';
    return `<div class="rail-card__hp-wrap">
      <div class="rail-card__hp-track">
        <div class="rail-card__hp-bar ${barClass}" style="width:${pct}%"></div>
      </div>
      ${label ? `<span class="rail-card__hp-label">${label}</span>` : ''}
    </div>`;
  }

  /** @private */
  _buildPipsHtml(actions) {
    if (!actions) return '';
    const max = actions.maxActions ?? 3;
    let pips = '';
    for (let i = 0; i < max; i++) {
      const spent = i >= (actions.actionsRemaining ?? 0);
      pips += `<span class="rail-card__pip ${spent ? 'pip--spent' : 'pip--ready'}" title="${spent ? 'Action spent' : 'Action ready'}"></span>`;
    }
    const rxClass = (actions.hasReaction ?? actions.hasReactionAvailable?.()) ? 'pip--reaction-ready' : 'pip--reaction-spent';
    pips += `<span class="rail-card__pip rail-card__pip--reaction ${rxClass}" title="Reaction">R</span>`;
    return `<div class="rail-card__actions">${pips}</div>`;
  }
}
