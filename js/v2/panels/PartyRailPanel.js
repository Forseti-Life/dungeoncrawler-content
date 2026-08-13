/**
 * @file panels/PartyRailPanel.js
 *
 * Party member quick-select rail shown during both exploration and combat.
 * During exploration: renders PC cards from the occupants list.
 * During combat: the legacy initiative tracker in #initiative-list takes over.
 */

export class PartyRailPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.dungeonData = null;
    this.stateManager = null;
    this._lastOccupantsTransitionId = '';
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const s = (k) => this.container?.querySelector(`[data-party="${k}"]`) || null;
    const id = (k) => document.getElementById(k);
    this._el = {
      rail:           s('rail'),
      empty:          s('empty'),
      initiativeList: id('initiative-list'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[PartyRailPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._subscribe();
    this._bindInitiativeHandlers();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:init', ({ occupants } = {}) => this.renderPartyRail()),
      this.bus.on('room:occupants-membership-changed', (payload = {}) => this.handleRoomOccupantsChanged(payload)),
      // Legacy compatibility event during bus migration.
      this.bus.on('room:occupants-changed', (payload = {}) => this.handleRoomOccupantsChanged(payload)),
    );
  }

  handleRoomOccupantsChanged(payload = {}) {
    const transitionId = String(payload?.transition?.id || '').trim();
    if (transitionId && transitionId === this._lastOccupantsTransitionId) {
      return;
    }
    if (transitionId) {
      this._lastOccupantsTransitionId = transitionId;
    }
    this.renderPartyRail();
  }

  /** Render exploration-mode party cards into the party rail. */
  renderPartyRail() {
    const rail = this._el.rail;
    const emptyEl = this._el.empty;
    if (!rail) return;

    const hexmap = this.stateManager?.hexmap;
    const allOccupants = typeof hexmap?.getVisualOccupants === 'function'
      ? hexmap.getVisualOccupants()
      : [];

    const partyMembers = allOccupants.filter((o) => {
      const type = String(o?.occupant_type || '').toLowerCase();
      return o?.is_party === true || type === 'player_character' || type === 'player';
    });

    rail.innerHTML = '';
    partyMembers.forEach((member) => {
      rail.appendChild(this._buildPartyCard(member));
    });

    const hasMembers = partyMembers.length > 0;
    rail.hidden = !hasMembers;
    if (emptyEl) emptyEl.hidden = hasMembers;

    console.log('[PartyRailPanel] renderPartyRail', { count: partyMembers.length });
  }

  /** Build a compact party member card element. */
  _buildPartyCard(occupant) {
    const name = String(occupant?.label || 'Unknown').trim();
    const entityId = String(occupant?.occupant_id || '').trim();
    const portraitUrl = occupant?.presentation?.portrait_url || null;

    const card = document.createElement('div');
    card.className = 'party-rail__card';
    if (entityId) card.dataset.entityId = entityId;
    card.setAttribute('role', 'button');
    card.setAttribute('tabindex', '0');
    card.setAttribute('aria-label', name);

    const avatar = document.createElement('div');
    avatar.className = 'party-rail__card-avatar';
    if (portraitUrl) {
      const img = document.createElement('img');
      img.src = portraitUrl;
      img.alt = name;
      img.className = 'party-rail__card-avatar-img';
      img.loading = 'lazy';
      avatar.appendChild(img);
    } else {
      avatar.textContent = name.charAt(0).toUpperCase();
    }

    const label = document.createElement('span');
    label.className = 'party-rail__card-name';
    label.textContent = name;

    card.appendChild(avatar);
    card.appendChild(label);

    card.addEventListener('click', () => this._onCardClick(entityId));
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') this._onCardClick(entityId);
    });

    return card;
  }

  _onCardClick(entityId) {
    if (!entityId) return;
    const hexmap = this.stateManager?.hexmap;
    const entity = hexmap?.entityManager?.getEntity(entityId);
    if (entity) hexmap.selectEntity(entity);
  }

  /** Wire delegated handlers on #initiative-list for combat card selection. */
  _bindInitiativeHandlers() {
    const list = this._el.initiativeList;
    if (!list) return;

    const onClickHandler = (e) => {
      const card = e.target.closest('.rail-card[data-entity-id]');
      if (!card) return;
      this._onCardClick(card.dataset.entityId);
    };

    const onKeydownHandler = (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const card = e.target.closest('.rail-card[data-entity-id]');
      if (card) this._onCardClick(card.dataset.entityId);
    };

    list.addEventListener('click', onClickHandler);
    list.addEventListener('keydown', onKeydownHandler);
    this._unsubs.push(() => {
      list.removeEventListener('click', onClickHandler);
      list.removeEventListener('keydown', onKeydownHandler);
    });
  }

}
