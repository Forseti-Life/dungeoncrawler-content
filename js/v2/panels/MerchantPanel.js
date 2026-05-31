/**
 * @file panels/MerchantPanel.js
 *
 * Renders the merchant shop for NPCs where presentation.is_merchant === true.
 *
 * Data source: canonical occupant API — is_merchant flag set server-side
 * by MapVisualStateProjector (keyword detection + explicit flags).
 * No client-side merchant heuristics.
 *
 * Occupant shape: { occupant_id, label, presentation: { is_merchant, portrait_url, role,
 *   stock: [{ item_id, item_name, price, quantity, description, item_type }],
 *   player_currency: { gp, sp, cp } } }
 *
 * Subscribes to bus events:
 *   room:occupants-changed   — { occupants: Array } re-evaluate merchant list
 *   user:merchant-selected   — { occupantId } show that merchant's stock
 *
 * Fires bus events:
 *   user:purchase-requested  — { merchantId, itemId, itemName, price }
 *
 * DOM selectors (all optional, graceful degradation):
 *   [data-merchant="select"]          <select> for merchant choice (if >1)
 *   [data-merchant="name"]            Active merchant name
 *   [data-merchant="portrait"]        <img> merchant portrait
 *   [data-merchant="portrait-wrap"]   Portrait container (shows/hides placeholder)
 *   [data-merchant="role"]            Merchant role label
 *   [data-merchant="player-currency"] Player gold display
 *   [data-merchant="filter"]          <input> for item text search
 *   [data-merchant="stock-grid"]      Stock item card container
 *   [data-merchant="empty"]           Shown when stock empty / no merchant
 *   [data-merchant="status"]          Loading/error status label
 *
 * @see PortraitPanel — sibling panel, same data source
 */

export class MerchantPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._merchants = [];        // merchant occupants for current room
    this._activeMerchantId = null;
    this._filterText = '';
    this._unsubs = [];
    this._el = {};
  }

  init() {
    this._bindElements();
    this._bindEvents();

    this._unsubs.push(
      this.bus.on('room:occupants-changed', ({ occupants } = {}) => {
        this._onOccupantsChanged(Array.isArray(occupants) ? occupants : []);
      }),
      this.bus.on('user:merchant-selected', ({ occupantId } = {}) => {
        this._selectMerchant(occupantId ?? null);
      }),
    );

    this._renderEmpty('Enter a room to meet merchants.');
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  // ---------------------------------------------------------------------------
  // Private — DOM binding
  // ---------------------------------------------------------------------------

  _bindElements() {
    const s = (attr) => this.container?.querySelector(`[data-merchant="${attr}"]`) ?? null;
    this._el = {
      select:         s('select'),
      name:           s('name'),
      portrait:       s('portrait'),
      portraitWrap:   s('portrait-wrap'),
      role:           s('role'),
      currency:       s('player-currency'),
      filter:         s('filter'),
      stockGrid:      s('stock-grid'),
      empty:          s('empty'),
      status:         s('status'),
    };
  }

  _bindEvents() {
    const { select, filter, stockGrid } = this._el;

    if (select) {
      select.addEventListener('change', () => {
        this._selectMerchant(select.value || null);
      });
    }

    if (filter) {
      filter.addEventListener('input', () => {
        this._filterText = filter.value ?? '';
        this._applyFilter();
      });
    }

    if (stockGrid) {
      stockGrid.addEventListener('click', (e) => {
        const btn = e.target?.closest?.('[data-merchant-buy]');
        if (!btn || btn.disabled) return;
        this.bus.emit('user:purchase-requested', {
          merchantId: this._activeMerchantId,
          itemId:     btn.dataset.itemId,
          itemName:   btn.dataset.itemName,
          price:      Number(btn.dataset.price ?? 0),
        });
      });
    }
  }

  // ---------------------------------------------------------------------------
  // Private — state management
  // ---------------------------------------------------------------------------

  _onOccupantsChanged(occupants) {
    this._merchants = occupants.filter((occ) => occ?.presentation?.is_merchant);

    if (!this._merchants.length) {
      this._activeMerchantId = null;
      this._renderEmpty('No merchants are present in this room.');
      return;
    }

    // Auto-select first or reconfirm existing selection
    const ids = this._merchants.map((m) => m.occupant_id);
    if (!this._activeMerchantId || !ids.includes(this._activeMerchantId)) {
      this._activeMerchantId = this._merchants[0].occupant_id;
    }

    this._populateSelect();
    this._renderMerchant();
  }

  _selectMerchant(occupantId) {
    if (!occupantId) return;
    const found = this._merchants.find((m) => m.occupant_id === occupantId);
    if (!found) return;
    this._activeMerchantId = occupantId;
    if (this._el.select) this._el.select.value = occupantId;
    this._filterText = '';
    if (this._el.filter) this._el.filter.value = '';
    this._renderMerchant();
  }

  _populateSelect() {
    const sel = this._el.select;
    if (!sel) return;
    sel.innerHTML = this._merchants.map((m) =>
      `<option value="${this._esc(m.occupant_id)}">${this._esc(m.label ?? m.occupant_id)}</option>`
    ).join('');
    sel.value = this._activeMerchantId ?? '';
    sel.style.display = this._merchants.length > 1 ? '' : 'none';
  }

  // ---------------------------------------------------------------------------
  // Private — rendering
  // ---------------------------------------------------------------------------

  _renderMerchant() {
    const merchant = this._merchants.find((m) => m.occupant_id === this._activeMerchantId);
    if (!merchant) {
      this._renderEmpty('Merchant not found.');
      return;
    }

    const pres = merchant.presentation ?? {};
    const name = String(merchant.label ?? 'Merchant').trim();
    const role = String(pres.role ?? '').trim();
    const portraitUrl = pres.portrait_url ?? null;
    const currency = pres.player_currency ?? {};
    const stock = Array.isArray(pres.stock) ? pres.stock : [];

    if (this._el.name)   this._el.name.textContent   = name;
    if (this._el.role)   this._el.role.textContent   = role || 'Merchant';

    if (this._el.portrait) {
      if (portraitUrl) {
        this._el.portrait.src = portraitUrl;
        this._el.portrait.alt = `${name} portrait`;
        this._el.portrait.hidden = false;
      } else {
        this._el.portrait.hidden = true;
      }
    }

    if (this._el.currency) {
      const gp = Number(currency.gp ?? 0);
      const sp = Number(currency.sp ?? 0);
      const cp = Number(currency.cp ?? 0);
      const parts = [];
      if (gp) parts.push(`${gp} gp`);
      if (sp) parts.push(`${sp} sp`);
      if (cp) parts.push(`${cp} cp`);
      this._el.currency.textContent = parts.join(', ') || '—';
    }

    this._renderStock(stock);
  }

  /**
   * @param {Array} stock
   * @private
   */
  _renderStock(stock) {
    if (this._el.empty)     this._el.empty.hidden     = stock.length > 0;
    if (this._el.stockGrid) {
      this._el.stockGrid.innerHTML = stock.map((item) => this._itemCardHtml(item)).join('');
      this._el.stockGrid.hidden = stock.length === 0;
    }
    if (this._el.status) this._el.status.textContent = '';
    this._applyFilter();
  }

  _renderEmpty(message) {
    if (this._el.empty) {
      this._el.empty.hidden = false;
      this._el.empty.textContent = message;
    }
    if (this._el.stockGrid) {
      this._el.stockGrid.innerHTML = '';
      this._el.stockGrid.hidden = true;
    }
    if (this._el.name)    this._el.name.textContent    = '';
    if (this._el.role)    this._el.role.textContent    = '';
    if (this._el.status)  this._el.status.textContent  = '';
    if (this._el.select)  this._el.select.style.display = 'none';
  }

  _applyFilter() {
    if (!this._el.stockGrid) return;
    const q = this._filterText.toLowerCase().trim();
    this._el.stockGrid.querySelectorAll('.merchant-item-card').forEach((card) => {
      const hay = (card.dataset.searchText ?? card.textContent).toLowerCase();
      card.hidden = !!(q && !hay.includes(q));
    });
  }

  /**
   * @param {{ item_id, item_name, price, quantity, description, item_type }} item
   * @returns {string} HTML
   * @private
   */
  _itemCardHtml(item) {
    const name  = String(item?.item_name ?? item?.name ?? 'Item').trim();
    const price = Number(item?.price ?? 0);
    const qty   = item?.quantity != null ? Number(item.quantity) : null;
    const desc  = String(item?.description ?? '').trim();
    const type  = String(item?.item_type ?? item?.type ?? '').trim();
    const searchText = `${name} ${desc} ${type}`.toLowerCase();
    const priceLabel = price ? `${price} gp` : 'Free';
    const qtyLabel   = qty != null ? `Qty: ${qty}` : '';

    return `<div class="merchant-item-card" data-search-text="${this._esc(searchText)}">
      <div class="merchant-item-card__info">
        <span class="merchant-item-card__name">${this._esc(name)}</span>
        ${type ? `<span class="merchant-item-card__type">${this._esc(type)}</span>` : ''}
        ${desc ? `<p class="merchant-item-card__desc">${this._esc(desc)}</p>` : ''}
        <span class="merchant-item-card__meta">${this._esc([priceLabel, qtyLabel].filter(Boolean).join(' • '))}</span>
      </div>
      <button type="button" class="btn-primary btn-sm merchant-item-card__buy"
              data-merchant-buy="true"
              data-item-id="${this._esc(String(item?.item_id ?? ''))}"
              data-item-name="${this._esc(name)}"
              data-price="${price}"
              ${qty === 0 ? 'disabled aria-disabled="true"' : ''}>
        Buy${price ? ` (${price} gp)` : ''}
      </button>
    </div>`;
  }

  /** @private */
  _esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
}
