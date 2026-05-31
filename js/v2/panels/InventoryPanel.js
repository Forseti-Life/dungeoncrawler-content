/**
 * @file panels/InventoryPanel.js
 *
 * Renders the character's inventory with item action buttons.
 *
 * PURE UI — no game logic. All inventory state is server-authoritative,
 * pushed via bus events.
 *
 * DOM bindings (via [data-inv="key"]):
 *   item-list  — scrollable list of item cards
 *   empty      — shown when inventory is empty
 *   currency   — player currency display (e.g. "50 gp, 10 sp")
 *
 * Inventory shape (from server):
 *   { items: [{ item_id, name, item_type, quantity, equipped, description? }],
 *     currency: { gp, sp, cp } }
 *
 * Item actions rendered as [data-inv-action] buttons:
 *   use, drop, equip  (server decides legality)
 *
 * Subscribes to bus events:
 *   game:init                — { inventory }   initial render
 *   entity:inventory-changed — { inventory }   re-render after change
 *
 * Fires bus events:
 *   user:inventory-action  — { action: 'use'|'drop'|'equip', itemId }
 */

export class InventoryPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    /** @type {object[]} current item list */
    this._items = [];
    /** @type {object} bound DOM elements */
    this._el = {};
  }

  init() {
    const s = (key) => this.container.querySelector(`[data-inv="${key}"]`);
    this._el = { itemList: s('item-list'), empty: s('empty'), currency: s('currency') };
    this._bindEvents();
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this._items = [];
  }

  // ---------------------------------------------------------------------------
  // DOM events
  // ---------------------------------------------------------------------------

  _bindEvents() {
    const { itemList } = this._el;
    if (!itemList) return;
    itemList.addEventListener('click', (e) => {
      const btn = e.target?.closest?.('[data-inv-action]');
      if (!btn) return;
      const action = btn.dataset.invAction;
      const itemId = btn.dataset.itemId;
      if (action && itemId) {
        this.bus.emit('user:inventory-action', { action, itemId });
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:init',                (data) => this._onInventory(data?.inventory)),
      this.bus.on('entity:inventory-changed', (data) => this._onInventory(data?.inventory)),
    );
  }

  _onInventory(inventory) {
    if (!inventory) return;
    this._items = inventory.items ?? [];
    this._renderItems();
    this._renderCurrency(inventory.currency ?? {});
  }

  // ---------------------------------------------------------------------------
  // Rendering
  // ---------------------------------------------------------------------------

  _renderItems() {
    const { itemList, empty } = this._el;
    if (empty) empty.hidden = this._items.length > 0;
    if (!itemList) return;
    itemList.innerHTML = this._items.map((item) => this._itemCardHtml(item)).join('');
  }

  _itemCardHtml(item) {
    const type      = _esc(item.item_type ?? 'misc');
    const name      = _esc(item.name ?? 'Unknown Item');
    const qty       = item.quantity > 1 ? `<span class="inv-item__qty">×${item.quantity}</span>` : '';
    const equipped  = item.equipped ? ' inv-item--equipped' : '';
    const desc      = item.description ? `<p class="inv-item__desc">${_esc(item.description)}</p>` : '';
    const actions   = this._itemActionsHtml(item);
    return `<div class="inv-item inv-item--${type}${equipped}" data-item-id="${_esc(item.item_id)}">
  <span class="inv-item__name">${name}</span>${qty}
  ${desc}
  <div class="inv-item__actions">${actions}</div>
</div>`;
  }

  _itemActionsHtml(item) {
    const id = _esc(item.item_id);
    const btns = [];
    if (item.item_type === 'consumable') {
      btns.push(`<button class="inv-btn" data-inv-action="use" data-item-id="${id}">Use</button>`);
    }
    if (!item.equipped && (item.item_type === 'weapon' || item.item_type === 'armor' || item.item_type === 'equipment')) {
      btns.push(`<button class="inv-btn" data-inv-action="equip" data-item-id="${id}">Equip</button>`);
    }
    btns.push(`<button class="inv-btn inv-btn--drop" data-inv-action="drop" data-item-id="${id}">Drop</button>`);
    return btns.join('');
  }

  _renderCurrency({ gp = 0, sp = 0, cp = 0 } = {}) {
    const { currency } = this._el;
    if (!currency) return;
    const parts = [];
    if (gp) parts.push(`${gp} gp`);
    if (sp) parts.push(`${sp} sp`);
    if (cp) parts.push(`${cp} cp`);
    currency.textContent = parts.join(', ') || '—';
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
