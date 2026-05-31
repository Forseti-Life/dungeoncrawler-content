/**
 * @file panels/PartyRailPanel.js
 *
 * Party member quick-select rail.
 * Methods ported verbatim from hexmap.js UIManager.
 */

export class PartyRailPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.dungeonData = null;
    this.stateManager = null;
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const s = (k) => this.container?.querySelector(`[data-party="${k}"]`) || null;
    this._el = {
      rail:  s('rail'),
      empty: s('empty'),
    };
    this._subscribe();
    this.setupPartyRailHandlers();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:init', () => this.setupPartyRailHandlers()),
      this.bus.on('room:occupants-changed', () => this.setupPartyRailHandlers()),
    );
  }

  setupPartyRailHandlers() {
    const list = this._el.initiativeList;
    if (!list) return;

    list.addEventListener('click', (e) => {
      const card = e.target.closest('.rail-card[data-entity-id]');
      if (!card) return;
      const entityId = card.dataset.entityId;
      const hexmap = this.stateManager?.hexmap;
      if (!hexmap || !entityId) return;
      const entity = hexmap.entityManager?.getEntity(entityId);
      if (entity) hexmap.selectEntity(entity);
    });

    list.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const card = e.target.closest('.rail-card[data-entity-id]');
      if (card) card.click();
    });
  }

}
