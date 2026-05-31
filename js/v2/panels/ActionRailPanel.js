/**
 * @file panels/ActionRailPanel.js
 *
 * Renders the 3-action economy rail for the active player's turn.
 *
 * Sub-panels rendered on demand (via [data-action-rail-category] buttons):
 *   attack      — weapon strike; lists hostile targets
 *   spells      — spell selection by rank, cast on target
 *   skills      — skill check actions
 *   interact    — object/door/NPC interactions
 *   navigate    — room connection navigation
 *   consumables — use consumable items
 *
 * Subscribes to bus events:
 *   combat:turn-changed      — rebuild rail for new active entity; { entity, initiativeOrder }
 *   combat:state-changed     — { state } show/hide rail
 *   entity:selected          — { entity } update context for selected entity
 *   room:changed             — { room, connections } refresh navigate panel
 *   room:entities-changed    — { entities } refresh attack target list
 *
 * Fires bus events:
 *   user:action-selected     — { actionKey, cost, context }
 *   user:attack              — { attacker, targetEntityId, weaponId }
 *   user:cast-spell          — { spellId, rank, targetEntityId }
 *   user:interact            — { targetEntityId, interactionType }
 *   user:navigate-to-room    — { roomId, connectionId }
 *
 * DOM selectors (all optional — graceful degradation):
 *   [data-action-rail="actor-name"]    Active entity label
 *   [data-action-rail="status"]        Actions/movement summary
 *   [data-action-rail="categories"]    Category button container (delegated clicks)
 *   [data-action-rail="panel-title"]   Active sub-panel title
 *   [data-action-rail="panel-body"]    Active sub-panel content
 */

export class ActionRailPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;

    /** @type {object|null} Active ECS entity (player-team, current or selected) */
    this._actor = null;
    /** @type {Array} Room entities for target selection */
    this._roomEntities = [];
    /** @type {Array} Room connections for navigation */
    this._connections = [];
    /** @type {string|null} Active category tab */
    this._activeCategory = null;
    /** @type {string} Filter text for current sub-panel */
    this._filterText = '';
    /** @type {boolean} Whether combat is active */
    this._combatActive = false;

    this._el = {};
    this._unsubs = [];
  }

  init() {
    this._bindElements();
    this._bindCategoryClicks();
    this._bindPanelBodyClicks();

    this._unsubs.push(
      this.bus.on('combat:turn-changed', ({ entity } = {}) => {
        if (entity) this._setActor(entity);
        this._render();
      }),
      this.bus.on('combat:state-changed', ({ state } = {}) => {
        this._combatActive = (state === 'active');
        this._render();
      }),
      this.bus.on('entity:selected', ({ entity } = {}) => {
        if (entity) this._setActorIfPlayer(entity);
        this._renderActorHeader();
        if (this._activeCategory) this._renderSubPanel();
      }),
      this.bus.on('room:changed', ({ connections } = {}) => {
        this._connections = Array.isArray(connections) ? connections : [];
        if (this._activeCategory === 'navigate') this._renderSubPanel();
      }),
      this.bus.on('room:entities-changed', ({ entities } = {}) => {
        this._roomEntities = Array.isArray(entities) ? entities : [];
        if (this._activeCategory === 'attack') this._renderSubPanel();
      }),
    );

    this._render();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Private — DOM binding
  // ---------------------------------------------------------------------------

  _bindElements() {
    const s = (attr) => this.container?.querySelector(`[data-action-rail="${attr}"]`) ?? null;
    this._el = {
      actorName:  s('actor-name'),
      status:     s('status'),
      categories: s('categories'),
      panelTitle: s('panel-title'),
      panelBody:  s('panel-body'),
    };
  }

  _bindCategoryClicks() {
    const cats = this._el.categories;
    if (!cats) return;
    cats.addEventListener('click', (e) => {
      const btn = e.target?.closest?.('[data-action-rail-category]');
      if (!btn || btn.disabled) return;
      const cat = btn.dataset.actionRailCategory;
      this._activeCategory = (this._activeCategory === cat) ? null : cat;
      this._filterText = '';
      this._renderCategoryButtons();
      this._renderSubPanel();
    });
  }

  _bindPanelBodyClicks() {
    const body = this._el.panelBody;
    if (!body) return;
    body.addEventListener('click', (e) => {
      const btn = e.target?.closest?.('[data-rail-execute]');
      if (!btn || btn.disabled) return;
      this._handleExecute(btn);
    });
    body.addEventListener('input', (e) => {
      if (e.target?.dataset?.railFilter) {
        this._filterText = e.target.value ?? '';
        this._applyFilter();
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Private — actor management
  // ---------------------------------------------------------------------------

  _setActor(entity) {
    this._actor = entity;
  }

  _setActorIfPlayer(entity) {
    const combat = entity?.getComponent?.('CombatComponent');
    const isPlayer = combat?.isPlayerTeam?.() || combat?.team === 'player';
    if (isPlayer) this._actor = entity;
  }

  // ---------------------------------------------------------------------------
  // Private — rendering
  // ---------------------------------------------------------------------------

  _render() {
    this._renderActorHeader();
    this._renderCategoryButtons();
    this._renderSubPanel();
  }

  _renderActorHeader() {
    const actor = this._actor;
    const identity = actor?.getComponent?.('IdentityComponent');
    const actions  = actor?.getComponent?.('ActionsComponent');
    const movement = actor?.getComponent?.('MovementComponent');

    const name = identity?.name ?? (actor ? `Entity ${actor.id}` : 'No actor');
    if (this._el.actorName) this._el.actorName.textContent = name;

    if (this._el.status) {
      const parts = [];
      if (actions) parts.push(`${actions.actionsRemaining ?? '?'}/${actions.maxActions ?? '?'} actions`);
      if (movement && Number.isFinite(movement.movementRemaining)) parts.push(`${movement.movementRemaining} ft move`);
      this._el.status.textContent = parts.join(' • ') || (this._combatActive ? 'No actor loaded' : 'Exploration ready');
    }
  }

  _renderCategoryButtons() {
    const cats = this._el.categories;
    if (!cats) return;
    cats.querySelectorAll('[data-action-rail-category]').forEach((btn) => {
      const cat = btn.dataset.actionRailCategory;
      btn.classList.toggle('action-rail__category--active', cat === this._activeCategory);
      const canAct = this._canAct();
      btn.disabled = !canAct && !['navigate', 'skills', 'interact'].includes(cat);
      btn.setAttribute('aria-disabled', btn.disabled ? 'true' : 'false');
    });
  }

  _renderSubPanel() {
    const body = this._el.panelBody;
    if (!body) return;

    if (!this._activeCategory) {
      this._setPanelTitle('Quick actions');
      body.innerHTML = this._renderEmptyState();
      return;
    }

    const panel = this._buildPanel(this._activeCategory);
    this._setPanelTitle(panel.title);
    body.innerHTML = panel.html;
    this._applyFilter();
  }

  _setPanelTitle(title) {
    if (this._el.panelTitle) this._el.panelTitle.textContent = title;
  }

  _canAct() {
    if (!this._actor) return false;
    const actions = this._actor.getComponent?.('ActionsComponent');
    return (actions?.actionsRemaining ?? 0) > 0;
  }

  // ---------------------------------------------------------------------------
  // Private — sub-panel builders
  // ---------------------------------------------------------------------------

  _buildPanel(category) {
    const builders = {
      attack:      () => this._buildAttackPanel(),
      spells:      () => this._buildSpellsPanel(),
      skills:      () => this._buildSkillsPanel(),
      interact:    () => this._buildInteractPanel(),
      navigate:    () => this._buildNavigatePanel(),
      consumables: () => this._buildConsumablesPanel(),
    };
    return builders[category]?.() ?? { title: category, html: this._renderEmptyState() };
  }

  _buildAttackPanel() {
    const actor = this._actor;
    if (!actor) {
      return { title: 'Attack', html: '<div class="action-rail__empty"><p>No active actor.</p></div>' };
    }

    const hostile = this._roomEntities.filter((e) => {
      if (e.id === actor.id) return false;
      const combat = e.getComponent?.('CombatComponent');
      const stats  = e.getComponent?.('StatsComponent');
      const alive  = stats?.isAlive?.() ?? ((stats?.currentHp ?? 1) > 0);
      return combat && alive && combat.team === 'enemy';
    });

    if (!hostile.length) {
      return {
        title: 'Attack',
        html: '<div class="action-rail__empty"><p>No hostile targets in range.</p></div>',
      };
    }

    const html = hostile.map((target) => {
      const id  = target?.getComponent?.('IdentityComponent');
      const st  = target?.getComponent?.('StatsComponent');
      const pct = st && st.maxHp ? Math.round((st.currentHp / st.maxHp) * 100) : 0;
      const name = id?.name ?? `Entity ${target.id}`;
      return this._renderEntry({
        execute: 'attack', title: name,
        summary: `Strike • 1 action • HP ${pct}%`,
        dataset: { targetEntityId: String(target.id) },
        disabled: !this._canAct(),
        actionLabel: 'Strike',
      });
    }).join('');

    return { title: `Attack (${hostile.length} target${hostile.length > 1 ? 's' : ''})`, html };
  }

  _buildSpellsPanel() {
    const actor = this._actor;
    const state = actor?.dcStatePayload?.state ?? actor?.dcStatePayload ?? {};
    const spells = state?.spells ?? state?.known_spells ?? [];

    if (!Array.isArray(spells) || !spells.length) {
      return { title: 'Spells', html: '<div class="action-rail__empty"><p>No spells available.</p></div>' };
    }

    const html = spells.slice(0, 20).map((spell) => {
      const name  = spell?.name ?? spell?.spell_name ?? 'Unknown spell';
      const rank  = spell?.rank ?? spell?.level ?? 1;
      const cost  = spell?.action_cost ?? 2;
      return this._renderEntry({
        execute: 'cast-spell', title: name,
        summary: `Rank ${rank} • ${cost} action${cost !== 1 ? 's' : ''}`,
        dataset: { spellId: String(spell?.id ?? spell?.spell_id ?? ''), rank: String(rank) },
        disabled: (this._actor?.getComponent?.('ActionsComponent')?.actionsRemaining ?? 0) < cost,
        actionLabel: 'Cast',
      });
    }).join('');

    return { title: `Spells (${spells.length})`, html };
  }

  _buildSkillsPanel() {
    const SKILLS = [
      { id: 'acrobatics',   label: 'Acrobatics',   desc: 'Balance, tumble, squeeze through tight spaces' },
      { id: 'athletics',    label: 'Athletics',    desc: 'Climb, swim, jump, shove' },
      { id: 'deception',    label: 'Deception',    desc: 'Lie, feint, impersonate' },
      { id: 'diplomacy',    label: 'Diplomacy',    desc: 'Negotiate, perform, request' },
      { id: 'intimidation', label: 'Intimidation', desc: 'Coerce, demoralise' },
      { id: 'medicine',     label: 'Medicine',     desc: 'Administer first aid, treat wounds' },
      { id: 'nature',       label: 'Nature',       desc: 'Command an animal, identify creatures' },
      { id: 'perception',   label: 'Perception',   desc: 'Seek, sense motive' },
      { id: 'stealth',      label: 'Stealth',      desc: 'Hide, sneak, conceal object' },
      { id: 'thievery',     label: 'Thievery',     desc: 'Pick lock, disable device, steal' },
    ];

    const html = SKILLS.map((sk) => this._renderEntry({
      execute: 'skill', title: sk.label, summary: sk.desc,
      dataset: { skillId: sk.id },
      disabled: false,
      actionLabel: 'Roll',
    })).join('');

    return { title: 'Skill actions', html };
  }

  _buildInteractPanel() {
    const targets = this._roomEntities.filter((e) => {
      const type = e.getComponent?.('IdentityComponent')?.entityType ?? '';
      return ['obstacle', 'door', 'npc', 'item', 'object'].includes(type);
    });

    if (!targets.length) {
      return { title: 'Interact', html: '<div class="action-rail__empty"><p>Nothing to interact with nearby.</p></div>' };
    }

    const html = targets.map((target) => {
      const id   = target?.getComponent?.('IdentityComponent');
      const name = id?.name ?? `Object ${target.id}`;
      const type = id?.entityType ?? 'object';
      return this._renderEntry({
        execute: 'interact', title: name,
        summary: `${type} • free interaction`,
        dataset: { targetEntityId: String(target.id), interactionType: type },
        disabled: false,
        actionLabel: 'Interact',
      });
    }).join('');

    return { title: `Interact (${targets.length})`, html };
  }

  _buildNavigatePanel() {
    if (!this._connections.length) {
      return { title: 'Navigate', html: '<div class="action-rail__empty"><p>No known routes from this room.</p></div>' };
    }

    const html = this._connections.map((conn) => {
      const name = conn?.room_name ?? conn?.roomName ?? conn?.destination ?? `Room ${conn?.room_id ?? conn?.roomId ?? '?'}`;
      const roomId = String(conn?.room_id ?? conn?.roomId ?? '');
      const connId = String(conn?.connection_id ?? conn?.connectionId ?? conn?.id ?? '');
      return this._renderEntry({
        execute: 'navigate', title: name,
        summary: conn?.direction ? `Direction: ${conn.direction}` : 'Travel to room',
        dataset: { roomId, connectionId: connId },
        disabled: false,
        actionLabel: 'Travel',
      });
    }).join('');

    return { title: `Navigate (${this._connections.length})`, html };
  }

  _buildConsumablesPanel() {
    const actor = this._actor;
    const state = actor?.dcStatePayload?.state ?? actor?.dcStatePayload ?? {};
    const inventory = state?.inventory ?? {};
    const items = Object.values(inventory).flat?.() ?? [];
    const consumables = items.filter((item) => item?.item_type === 'consumable' || item?.type === 'consumable');

    if (!consumables.length) {
      return { title: 'Consumables', html: '<div class="action-rail__empty"><p>No consumable items in inventory.</p></div>' };
    }

    const html = consumables.map((item) => {
      const name = item?.item_name ?? item?.name ?? 'Item';
      const qty  = item?.quantity ?? 1;
      return this._renderEntry({
        execute: 'use-consumable', title: name,
        summary: `Qty: ${qty} • 1 action`,
        dataset: { itemId: String(item?.id ?? item?.item_id ?? '') },
        disabled: !this._canAct(),
        actionLabel: 'Use',
      });
    }).join('');

    return { title: `Consumables (${consumables.length})`, html };
  }

  // ---------------------------------------------------------------------------
  // Private — HTML helpers
  // ---------------------------------------------------------------------------

  /** @private */
  _renderEntry({ execute, title, summary, dataset = {}, disabled = false, actionLabel = 'Execute' }) {
    const dataAttrs = Object.entries(dataset)
      .map(([k, v]) => `data-${k}="${this._esc(v)}"`)
      .join(' ');
    const searchText = `${title} ${summary}`.toLowerCase();
    return `<div class="action-rail__entry" data-rail-search="${this._esc(searchText)}">
      <div class="action-rail__entry-main">
        <span class="action-rail__entry-title">${this._esc(title)}</span>
        <span class="action-rail__entry-summary">${this._esc(summary)}</span>
      </div>
      <button type="button" class="action-rail__execute btn-primary btn-sm"
              data-rail-execute="${this._esc(execute)}" ${dataAttrs}
              ${disabled ? 'disabled aria-disabled="true"' : ''}>
        ${this._esc(actionLabel)}
      </button>
    </div>`;
  }

  /** @private */
  _renderEmptyState() {
    const label = this._combatActive ? 'Select an action category above.' : 'Click a category above to prepare your action.';
    return `<div class="action-rail__empty"><p>${label}</p></div>`;
  }

  /** @private */
  _esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ---------------------------------------------------------------------------
  // Private — filter
  // ---------------------------------------------------------------------------

  _applyFilter() {
    const body = this._el.panelBody;
    if (!body) return;
    const q = this._filterText.toLowerCase().trim();
    body.querySelectorAll('.action-rail__entry').forEach((entry) => {
      const hay = entry.dataset.railSearch ?? entry.textContent.toLowerCase();
      entry.hidden = !!(q && !hay.includes(q));
    });
  }

  // ---------------------------------------------------------------------------
  // Private — action execution
  // ---------------------------------------------------------------------------

  _handleExecute(btn) {
    const execute = btn.dataset.railExecute;
    const actor   = this._actor;

    switch (execute) {
      case 'attack':
        this.bus.emit('user:attack', {
          attacker:       actor,
          targetEntityId: btn.dataset.targetEntityId,
        });
        break;

      case 'cast-spell':
        this.bus.emit('user:cast-spell', {
          spellId:        btn.dataset.spellId,
          rank:           Number(btn.dataset.rank ?? 1),
          targetEntityId: btn.dataset.targetEntityId ?? null,
        });
        break;

      case 'interact':
        this.bus.emit('user:interact', {
          targetEntityId:  btn.dataset.targetEntityId,
          interactionType: btn.dataset.interactionType,
        });
        break;

      case 'navigate':
        this.bus.emit('user:navigate-to-room', {
          roomId:       btn.dataset.roomId,
          connectionId: btn.dataset.connectionId,
        });
        break;

      case 'skill':
        this.bus.emit('user:action-selected', {
          actionKey: btn.dataset.skillId,
          cost: 1,
          context: { actor },
        });
        break;

      case 'use-consumable':
        this.bus.emit('user:action-selected', {
          actionKey: 'use-consumable',
          cost: 1,
          context: { actor, itemId: btn.dataset.itemId },
        });
        break;

      default:
        this.bus.emit('user:action-selected', { actionKey: execute, cost: 1, context: { actor } });
    }
  }
}
