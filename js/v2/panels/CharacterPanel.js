/**
 * @file panels/CharacterPanel.js
 *
 * Character sheet summary and selected-entity info display.
 *
 * PURE UI — no game logic. All character state is server-authoritative,
 * pushed via bus events.
 *
 * DOM bindings (via [data-char="key"]):
 *   name         — launch character name
 *   class        — class name
 *   level        — level number
 *   portrait     — portrait <img>
 *   hp           — HP display (e.g. "18 / 24")
 *   ac           — AC value
 *   sheet-link   — <a> to full character sheet (href set from data)
 *   entity-info  — container shown when an entity is selected
 *   entity-name  — selected entity name
 *   entity-stats — selected entity stats (HP, AC, conditions)
 *
 * Character shape (from server, on game:init):
 *   { name, class_name, level, portrait_url, sheet_url?,
 *     hp_current, hp_max, ac }
 *
 * Entity shape (from entity:selected):
 *   { name, team, hp_current?, hp_max?, ac?, conditions?: string[] }
 *
 * Subscribes to bus events:
 *   game:init         — { character }   render launch-character summary
 *   entity:selected   — { entity }      show entity info panel
 *   entity:deselected — {}              hide entity info panel
 *
 * Fires no bus events (display only).
 */

export class CharacterPanel {
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
    const s = (key) => this.container.querySelector(`[data-char="${key}"]`);
    this._el = {
      name:        s('name'),
      cls:         s('class'),
      level:       s('level'),
      portrait:    s('portrait'),
      hp:          s('hp'),
      ac:          s('ac'),
      sheetLink:   s('sheet-link'),
      entityInfo:  s('entity-info'),
      entityName:  s('entity-name'),
      entityStats: s('entity-stats'),
    };
    this._hideEntityInfo();
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:init',         (data) => this._onInit(data)),
      this.bus.on('entity:selected',   (data) => this._onEntitySelected(data)),
      this.bus.on('entity:deselected', ()     => this._hideEntityInfo()),
    );
  }

  _onInit({ character } = {}) {
    if (!character) return;
    const { name, cls, level, portrait, hp, ac, sheetLink } = this._el;
    if (name)    name.textContent    = String(character.name ?? '');
    if (cls)     cls.textContent     = String(character.class_name ?? character.class ?? '');
    if (level)   level.textContent   = String(character.level ?? '');
    if (portrait && character.portrait_url) {
      portrait.src = character.portrait_url;
      portrait.alt = String(character.name ?? '');
    }
    if (hp) {
      hp.textContent = (character.hp_current != null && character.hp_max != null)
        ? `${character.hp_current} / ${character.hp_max}`
        : '';
    }
    if (ac)        ac.textContent  = String(character.ac ?? character.armor_class ?? '');
    if (sheetLink && character.sheet_url) sheetLink.href = character.sheet_url;
  }

  _onEntitySelected({ entity } = {}) {
    if (!entity) { this._hideEntityInfo(); return; }
    const { entityInfo, entityName, entityStats } = this._el;
    if (entityInfo) entityInfo.hidden = false;
    if (entityName) entityName.textContent = String(entity.name ?? 'Unknown');
    if (entityStats) {
      const parts = [];
      if (entity.hp_current != null && entity.hp_max != null) {
        parts.push(`HP: ${entity.hp_current}/${entity.hp_max}`);
      }
      if (entity.ac != null) parts.push(`AC: ${entity.ac}`);
      const conditions = (entity.conditions ?? []).map(_esc).join(', ');
      if (conditions) parts.push(`Conditions: ${conditions}`);
      entityStats.textContent = parts.join(' | ');
    }
  }

  _hideEntityInfo() {
    const { entityInfo, entityName, entityStats } = this._el;
    if (entityInfo)  entityInfo.hidden  = true;
    if (entityName)  entityName.textContent  = '';
    if (entityStats) entityStats.textContent = '';
  }
}

// ---------------------------------------------------------------------------
// Utility
// ---------------------------------------------------------------------------

function _esc(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
