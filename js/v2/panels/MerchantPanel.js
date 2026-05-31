/**
 * @file panels/MerchantPanel.js
 *
 * Merchant catalog, buy/sell, inventory sync.
 * Methods ported verbatim from hexmap.js UIManager.
 */

import { escapeQuestHtml } from '../utils/quest-utils.js';
import { escapeTooltipAttr } from '../utils/dom-utils.js';
import { collectInventoryItems, normalizeInventoryState } from '../utils/inventory-utils.js';

export class MerchantPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.stateManager = null;
    this.dungeonData = null;
    this._inventoryPanel = null;
    // Retry logic
    this.merchantPanelRetryTimer = null;
    this.merchantPanelRetryAttempts = 0;
    // Catalog search state
    this.merchantCatalogSearchRequestToken = 0;
    this.currentMerchantCatalogSearch = null;
    // Panel state
    this.currentMerchantCandidates = [];
    this.currentMerchantContext = null;
    this.currentMerchantFilterText = '';
    this.currentMerchantRef = null;
    this.currentMerchantRoomId = null;
    this.currentMerchantStatus = null;
    this.activeGameShellTab = null;
    this.currentCharacterInventoryContext = null;
    this._cachedOccupants = [];
  }

  init(dungeonData, stateManager, inventoryPanel = null) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    this._inventoryPanel = inventoryPanel;
    const id = (k) => document.getElementById(k);
    const s = (k) => this.container?.querySelector(`[data-merchant="${k}"]`) || null;
    this._el = {
      merchantPanelPortraitWrap: id('merchant-panel-portrait-wrap') || s('portrait-wrap'),
      merchantPanelPortrait:     id('merchant-panel-portrait'),
      merchantPanelName:         id('merchant-panel-name')         || s('name'),
      merchantPanelSummary:      id('merchant-panel-summary')      || s('role'),
      merchantEntitySelect:      id('merchant-entity-select')      || s('select'),
      merchantItemFilter:        id('merchant-item-filter')        || s('filter'),
      merchantBackroomSearch:    id('merchant-backroom-search'),
      merchantPanelStatus:       id('merchant-panel-status')       || s('status'),
      merchantPanelCurrency:     id('merchant-player-currency')    || s('player-currency'),
      merchantPanelGrid:         id('merchant-panel-grid')         || s('grid'),
      merchantPanelEmpty:        id('merchant-panel-empty')        || s('empty'),
      merchantStockList:         id('merchant-stock-list')         || s('stock-grid'),
      merchantSellList:          id('merchant-sell-list')          || s('sell-list'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[MerchantPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._subscribe();
    this.setupMerchantPanelActions();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    if (this.merchantPanelRetryTimer) clearTimeout(this.merchantPanelRetryTimer);
  }

  _subscribe() {
    const tabHandler = (e) => {
      this.activeGameShellTab = e.detail?.tabId || null;
    };
    window.addEventListener('dungeoncrawler:game-shell-tab-changed', tabHandler);
    this._unsubs.push(() => window.removeEventListener('dungeoncrawler:game-shell-tab-changed', tabHandler));

    this._unsubs.push(
      this.bus.on('room:changed', (d) => {
        this._cachedOccupants = [];
        this._buildMerchantEntriesFromOccupants(d?.roomId, []);
      }),
      this.bus.on('room:occupants-changed', (d) => {
        this._cachedOccupants = d?.occupants ?? [];
        const entries = this._buildMerchantEntriesFromOccupants(d?.roomId, this._cachedOccupants);
        if (entries.length > 0) {
          this.loadMerchantPanel();
        }
      }),
      this.bus.on('inventory:changed', (d) => {
        this.currentCharacterInventoryContext = d || null;
      }),
    );
  }

  // syncMerchantContextIntoInventoryPanel: delegate to bound inventoryPanel if available
  _syncInventory(context) {
    if (this._inventoryPanel?.renderInventoryPanel) {
      this._inventoryPanel.renderInventoryPanel(context);
    }
  }

  setupMerchantPanelActions() {
    if (typeof document === 'undefined' || document.body?.dataset.merchantPanelBound === 'true') {
      return;
    }

    document.body.dataset.merchantPanelBound = 'true';
    this.logMerchantPanelTrace('handler-bound', {
      scope: 'document',
      panelSelector: '#game-panel-merchant',
    });
    const changeHandler = (event) => {
      if (event.target?.id !== 'merchant-entity-select') {
        return;
      }
      this.currentMerchantRef = event.target.value || null;
      this.currentMerchantContext = null;
      this.resetMerchantCatalogSearch();
      this.logMerchantPanelTrace('merchant-selected', {
        merchantRef: this.currentMerchantRef,
      });
      this.loadMerchantPanel(true);
    };

    const inputHandler = (event) => {
      if (event.target?.id !== 'merchant-item-filter') {
        return;
      }
      this.currentMerchantFilterText = String(event.target.value || '').trim();
      this.logMerchantPanelTrace('filter-changed', {
        query: this.currentMerchantFilterText || null,
      });
      this.resetMerchantCatalogSearch();
      this.renderMerchantPanel(this.currentMerchantContext);
    };

    const clickHandler = (event) => {
      const panel = event.target.closest('#game-panel-merchant');
      if (!panel) {
        return;
      }
      const backroomButton = event.target.closest('[data-merchant-backroom-search]');
      if (backroomButton) {
        event.preventDefault();
        this.triggerMerchantCatalogSearch();
        return;
      }
      const button = event.target.closest('[data-merchant-action]');
      if (!button) {
        return;
      }
      event.preventDefault();
      this.logMerchantPanelTrace('trade-click', {
        action: button.dataset.merchantAction || null,
        itemId: button.dataset.itemId || null,
        itemInstanceId: button.dataset.itemInstanceId || null,
        merchantRef: this.currentMerchantRef,
      });
      this.dispatchMerchantAction(button);
    };

    document.addEventListener('change', changeHandler);
    document.addEventListener('input', inputHandler);
    document.addEventListener('click', clickHandler);
    this._unsubs.push(() => {
      document.removeEventListener('change', changeHandler);
      document.removeEventListener('input', inputHandler);
      document.removeEventListener('click', clickHandler);
      delete document.body.dataset.merchantPanelBound;
    });
  }

  logMerchantPanelTrace(stage, details = {}) {
    console.log('[MerchantPanel]', {
      stage,
      timestamp: new Date().toISOString(),
      ...details,
    });
  }

  clearMerchantPanelRetry() {
    if (this.merchantPanelRetryTimer) {
      window.clearTimeout(this.merchantPanelRetryTimer);
      this.merchantPanelRetryTimer = null;
    }
    this.merchantPanelRetryAttempts = 0;
  }

  resetMerchantCatalogSearch() {
    this.currentMerchantCatalogSearch = {
      query: '',
      roomId: null,
      merchantRef: null,
      status: 'idle',
      results: [],
      error: '',
    };
  }

  async triggerMerchantCatalogSearch() {
    const context = this.currentMerchantContext;
    const merchant = context?.merchant || null;
    const roomId = this.currentMerchantRoomId || null;
    const merchantRef = this.currentMerchantRef || null;
    const filterText = String(this.currentMerchantFilterText || '').trim().toLowerCase();
    const stock = Array.isArray(context?.stock) ? context.stock : [];
    const filteredStock = filterText
      ? stock.filter((item) => this.buildMerchantItemSearchText(item).includes(filterText))
      : stock;
    const sellableInventory = Array.isArray(context?.player?.sellable_inventory) ? context.player.sellable_inventory : [];
    const filteredSellableInventory = filterText
      ? sellableInventory.filter((item) => this.buildMerchantItemSearchText(item).includes(filterText))
      : sellableInventory;

    if (!merchant || !roomId || !merchantRef || !filterText || filteredStock.length > 0 || filteredSellableInventory.length > 0 || typeof fetch !== 'function') {
      return;
    }

    this.currentMerchantCatalogSearch = {
      query: filterText,
      roomId,
      merchantRef,
      status: 'loading',
      results: [],
      error: '',
    };
    this.renderMerchantPanel(this.currentMerchantContext);
    await this.loadMerchantCatalogSearch(filterText, roomId, merchantRef);
  }

  async loadMerchantCatalogSearch(query, roomId, merchantRef) {
    const hexmap = this.stateManager?.hexmap || null;
    const campaignId = Number(hexmap?.resolveCampaignId?.() || 0);
    if (!campaignId || !roomId || !merchantRef || !query) {
      this.resetMerchantCatalogSearch();
      this.renderMerchantPanel(this.currentMerchantContext);
      return;
    }

    const requestToken = ++this.merchantCatalogSearchRequestToken;
    try {
      const params = new URLSearchParams({ query });
      const response = await fetch(`/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/merchant/${encodeURIComponent(merchantRef)}/search?${params.toString()}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      const result = await response.json().catch(() => ({}));
      if (requestToken !== this.merchantCatalogSearchRequestToken) {
        return;
      }
      if (!response.ok || !result?.success) {
        throw new Error(result?.error || 'Merchant catalog search failed.');
      }

      this.currentMerchantCatalogSearch = {
        query,
        roomId,
        merchantRef,
        status: Array.isArray(result?.items) && result.items.length > 0 ? 'loaded' : 'not_found',
        results: Array.isArray(result?.items) ? result.items : [],
        error: '',
      };
    } catch (error) {
      if (requestToken !== this.merchantCatalogSearchRequestToken) {
        return;
      }
      this.currentMerchantCatalogSearch = {
        query,
        roomId,
        merchantRef,
        status: 'error',
        results: [],
        error: error?.message || 'Merchant catalog search failed.',
      };
    }

    this.renderMerchantPanel(this.currentMerchantContext);
  }

  scheduleMerchantPanelRetry(reason = 'room-pending') {
    if (typeof window === 'undefined') {
      return;
    }
    if (this.merchantPanelRetryTimer || this.activeGameShellTab !== 'merchant') {
      return;
    }
    if (this.merchantPanelRetryAttempts >= 8) {
      this.logMerchantPanelTrace('load-retry-aborted', {
        reason,
        attempts: this.merchantPanelRetryAttempts,
      });
      return;
    }

    this.merchantPanelRetryAttempts += 1;
    const attempt = this.merchantPanelRetryAttempts;
    this.logMerchantPanelTrace('load-retry-scheduled', {
      reason,
      attempt,
    });
    this.merchantPanelRetryTimer = window.setTimeout(() => {
      this.merchantPanelRetryTimer = null;
      this.loadMerchantPanel(true);
    }, 500);
  }

  resolveActiveMerchantCharacterId() {
    const hexmap = this.stateManager?.hexmap || null;
    const candidates = [
      this.currentCharacterInventoryContext?.characterId,
      hexmap?.launchContext?.character_id,
      hexmap?.launchCharacter?.id,
      hexmap?.launchCharacter?.character_id,
    ];

    for (const candidate of candidates) {
      const numeric = Number(candidate || 0);
      if (Number.isFinite(numeric) && numeric > 0) {
        return numeric;
      }
    }

    return null;
  }

  entityLooksMerchant(entity) {
    if (!entity || String(entity?.entity_type || '').trim().toLowerCase() !== 'npc') {
      return false;
    }

    const metadata = entity?.state?.metadata || {};
    const descriptor = [
      metadata.display_name,
      metadata.name,
      metadata.role,
      metadata.occupation,
      metadata.description,
      metadata.content_id,
      metadata.runtime_entity_id,
      entity?.state?.content_id,
      entity?.state?.runtime_entity_id,
      entity?.entity_ref?.content_id,
      entity?.entity_instance_id,
      entity?.instance_id,
      entity?.id,
    ].map((value) => String(value || '').toLowerCase()).join(' ');

    return [
      'merchant',
      'vendor',
      'shop',
      'shopkeeper',
      'barkeep',
      'bartender',
      'keeper',
      'innkeeper',
      'tavern',
      'bar',
      'blacksmith',
      'smith',
      'armorer',
      'apothecary',
      'alchemist',
      'herbalist',
      'trader',
    ].some((keyword) => descriptor.includes(keyword))
      || Boolean(entity?.state?.merchant_enabled || entity?.state?.merchant?.enabled || entity?.state?.merchant_stock);
  }

  // Bus-driven entry: called with the occupants array from room:occupants-changed
  _buildMerchantEntriesFromOccupants(roomId, occupants = []) {
    const resolvedRoomId = roomId ?? null;
    const merchantOccupants = occupants.filter((occupant) => {
      if (resolvedRoomId && String(occupant?.room_id ?? '') !== String(resolvedRoomId)) {
        return false;
      }
      return occupant?.presentation?.is_merchant === true;
    });

    const entries = [];
    const seen = new Set();
    merchantOccupants.forEach((occupant) => {
      const entityId = String(occupant?.occupant_id || '').trim();
      if (!entityId || seen.has(entityId)) return;
      seen.add(entityId);
      entries.push({
        entityId,
        name: String(occupant?.label || entityId).trim(),
        summary: String(occupant?.presentation?.role || 'Merchant').trim(),
        portraitUrl: occupant?.presentation?.portrait_url || '',
      });
    });
    entries.sort((a, b) => a.name.localeCompare(b.name));
    this.currentMerchantCandidates = entries;
    this.renderMerchantPanel(this.currentMerchantContext);
    return entries;
  }

  buildRoomMerchantEntries(roomId = null) {
    return this._buildMerchantEntriesFromOccupants(roomId, this._cachedOccupants ?? []);
  }

  buildMerchantItemSearchText(item = {}) {
    const catalogItem = item?.catalog_item && typeof item.catalog_item === 'object' ? item.catalog_item : {};
    return [
      item?.name,
      item?.item_id,
      item?.type,
      item?.subtype,
      item?.bulk,
      item?.level,
      item?.description,
      item?.source,
      item?.blocked_message,
      catalogItem?.description,
      catalogItem?.type,
      catalogItem?.subtype,
    ]
      .map((value) => String(value || '').trim().toLowerCase())
      .filter(Boolean)
      .join(' ');
  }

  buildMerchantItemMetaHtml(item = {}, options = {}) {
    const {
      quantityLabel = '',
      availabilityLabel = '',
      descriptionOverride = '',
    } = options;
    const catalogItem = item?.catalog_item && typeof item.catalog_item === 'object' ? item.catalog_item : {};
    const summaryParts = [
      item?.type,
      item?.subtype,
      item?.bulk ? `Bulk ${item.bulk}` : '',
      item?.level ? `Lvl ${item.level}` : '',
      quantityLabel,
      availabilityLabel,
      item?.source ? `Source ${item.source}` : '',
    ].filter(Boolean);
    const description = String(descriptionOverride || item?.description || catalogItem?.description || '').trim();
    return [
      summaryParts.length > 0 ? `<div class="merchant-item__meta">${escapeQuestHtml(summaryParts.join(' · '))}</div>` : '',
      description ? `<div class="merchant-item__meta">${escapeQuestHtml(description)}</div>` : '',
    ].filter(Boolean).join('');
  }

  resolveMerchantCategoryLabel(item = {}) {
    const type = String(item?.type || item?.item_type || '').trim();
    const subtype = String(item?.subtype || '').trim();
    const rawLabel = subtype || type || 'miscellaneous';
    return rawLabel
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase());
  }

  groupMerchantItemsByCategory(items = []) {
    const groups = new Map();
    items.forEach((item) => {
      const categoryLabel = this.resolveMerchantCategoryLabel(item);
      if (!groups.has(categoryLabel)) {
        groups.set(categoryLabel, []);
      }
      groups.get(categoryLabel).push(item);
    });

    return Array.from(groups.entries())
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([label, entries]) => ({ label, entries }));
  }

  renderMerchantStockItemHtml(item = {}) {
    return `<article class="merchant-item">
      <div class="merchant-item__copy">
        <div class="merchant-item__name">${escapeQuestHtml(item.name || item.item_id || 'Item')}</div>
        ${this.buildMerchantItemMetaHtml(item, {
          availabilityLabel: Number.isInteger(item.quantity_available) ? `Qty ${item.quantity_available}` : '',
        })}
      </div>
      <div class="merchant-item__actions">
        <span class="merchant-item__price">${escapeQuestHtml(item.price_label || '0 cp')}</span>
        <button type="button" class="btn btn-primary btn-sm" data-merchant-action="buy" data-item-id="${escapeTooltipAttr(item.item_id || '')}">Buy</button>
      </div>
    </article>`;
  }

  renderMerchantSellItemHtml(item = {}) {
    const disabled = item.blocked ? ' disabled aria-disabled="true"' : '';
    const buttonLabel = item.blocked ? 'Blocked' : 'Sell';
    return `<article class="merchant-item">
      <div class="merchant-item__copy">
        <div class="merchant-item__name">${escapeQuestHtml(item.name || item.item_id || 'Item')}</div>
        ${this.buildMerchantItemMetaHtml(item, {
          quantityLabel: item.quantity > 1 ? `Qty ${item.quantity}` : '',
          descriptionOverride: item.blocked ? (item.blocked_message || 'Cannot sell') : '',
        })}
      </div>
      <div class="merchant-item__actions">
        <span class="merchant-item__price">${escapeQuestHtml(item.offer_label || '0 cp')}</span>
        <button type="button" class="btn btn-secondary btn-sm" data-merchant-action="sell" data-item-instance-id="${escapeTooltipAttr(item.item_instance_id || '')}"${disabled}>${escapeQuestHtml(buttonLabel)}</button>
      </div>
    </article>`;
  }

  renderMerchantGroupedListHtml(items = [], emptyText, renderItem) {
    if (!Array.isArray(items) || items.length === 0) {
      return `<div class="merchant-trade-card__empty">${escapeQuestHtml(emptyText)}</div>`;
    }

    return this.groupMerchantItemsByCategory(items).map((group) => {
      return `<details class="merchant-category-group">
        <summary class="merchant-category-group__summary">
          <span class="merchant-category-group__label">${escapeQuestHtml(group.label)}</span>
          <span class="merchant-category-group__count">${escapeQuestHtml(String(group.entries.length))}</span>
        </summary>
        <div class="merchant-category-group__items">
          ${group.entries.map((item) => renderItem.call(this, item)).join('')}
        </div>
      </details>`;
    }).join('');
  }

  setMerchantStatus(message, tone = 'info') {
    this.currentMerchantStatus = {
      tone,
      message: message || '',
    };
    if (this._el.merchantPanelStatus) {
      this._el.merchantPanelStatus.textContent = message || '';
      this._el.merchantPanelStatus.dataset.tone = tone;
    }
  }

  syncMerchantContextIntoInventoryPanel(context) {
    const player = context?.player || {};
    const characterId = Number(player.character_id || 0);
    if (!characterId || !player.inventory || !this.currentCharacterInventoryContext) {
      return;
    }

    if (Number(this.currentCharacterInventoryContext.characterId || 0) !== characterId) {
      return;
    }

    const inventory = normalizeInventoryState(player.inventory || {}, player.currency || this.currentCharacterInventoryContext.currency || {});
    this.currentCharacterInventoryContext = {
      ...this.currentCharacterInventoryContext,
      inventory,
      currency: inventory.currency || player.currency || this.currentCharacterInventoryContext.currency || {},
    };
    this.bus.emit('inventory:changed', this.currentCharacterInventoryContext);
  }

  async loadMerchantPanel(force = false) {
    const hexmap = this.stateManager?.hexmap || null;
    const campaignId = Number(hexmap?.resolveCampaignId?.() || 0);
    const roomId = hexmap?.resolveActiveRoomId?.() || null;
    const merchantEntries = this.buildRoomMerchantEntries(roomId);
    const previousRoomId = this.currentMerchantRoomId;

    if (previousRoomId && roomId && previousRoomId !== roomId) {
      this.currentMerchantContext = null;
      this.resetMerchantCatalogSearch();
    }

    this.currentMerchantRoomId = roomId;
    this.currentMerchantCandidates = merchantEntries;
    if (roomId) {
      this.clearMerchantPanelRetry();
    }
    this.logMerchantPanelTrace('load-start', {
      force,
      campaignId,
      roomId,
      previousRoomId,
      currentMerchantRef: this.currentMerchantRef,
      candidateRefs: merchantEntries.map((entry) => entry.entityId),
      candidateNames: merchantEntries.map((entry) => entry.name),
    });
    this.renderMerchantPanel(this.currentMerchantContext);

    if (!campaignId || !roomId || merchantEntries.length === 0) {
      this.currentMerchantContext = null;
      this.resetMerchantCatalogSearch();
      this.renderMerchantPanel(null);
      this.logMerchantPanelTrace('load-skipped', {
        force,
        campaignId,
        roomId,
        merchantCount: merchantEntries.length,
      });
      if (campaignId && !roomId) {
        this.setMerchantStatus('Loading room merchant context...', 'pending');
        this.scheduleMerchantPanelRetry('room-pending');
        return;
      }
      this.setMerchantStatus(merchantEntries.length === 0 ? 'No merchant is present in this room.' : 'Merchant context is unavailable.', 'info');
      return;
    }

    const currentChoiceValid = merchantEntries.some((entry) => entry.entityId === this.currentMerchantRef);
    if (!currentChoiceValid) {
      this.currentMerchantRef = merchantEntries[0]?.entityId || null;
    }
    if (!this.currentMerchantRef) {
      this.currentMerchantContext = null;
      this.resetMerchantCatalogSearch();
      this.renderMerchantPanel(null);
      return;
    }

    if (!force && this.currentMerchantContext?.merchant?.merchant_ref === this.currentMerchantRef && previousRoomId === roomId) {
      this.renderMerchantPanel(this.currentMerchantContext);
      return;
    }

    const occupant = Array.isArray(this._cachedOccupants)
      ? this._cachedOccupants.find((entry) => String(entry?.occupant_id || entry?.content_id || '') === String(this.currentMerchantRef || '')) || null
      : null;
    const presentation = occupant?.presentation && typeof occupant.presentation === 'object' ? occupant.presentation : {};
    const hasMerchantStock = Object.prototype.hasOwnProperty.call(presentation, 'stock');
    const selectedMerchantEntry = merchantEntries.find((entry) => entry.entityId === this.currentMerchantRef) || merchantEntries[0] || null;
    const characterId = this.resolveActiveMerchantCharacterId();

    if (force || !hasMerchantStock) {
      this.bus.emit('character:inventory-refresh-requested', {
        characterId,
        campaignId,
        currency: this.currentCharacterInventoryContext?.currency || {},
      });
    }

    if (!hasMerchantStock && !this.currentMerchantContext) {
      this.currentMerchantContext = null;
      this.resetMerchantCatalogSearch();
      this.renderMerchantPanel(null);
      this.setMerchantStatus('Loading merchant stock...', 'pending');
      this.scheduleMerchantPanelRetry('stock-pending');
      return;
    }

    const existingContext = this.currentMerchantContext?.merchant?.merchant_ref === this.currentMerchantRef
      ? this.currentMerchantContext
      : null;
    const inventory = normalizeInventoryState(
      this.currentCharacterInventoryContext?.inventory || existingContext?.player?.inventory || {},
      this.currentCharacterInventoryContext?.currency || existingContext?.player?.currency || presentation.player_currency || {}
    );
    const sellableInventory = Array.isArray(existingContext?.player?.sellable_inventory) && existingContext.player.sellable_inventory.length > 0
      ? existingContext.player.sellable_inventory
      : collectInventoryItems(inventory, this.currentCharacterInventoryContext?.equipment || []);

    this.currentMerchantContext = {
      ...existingContext,
      merchant: {
        ...(existingContext?.merchant || {}),
        merchant_ref: this.currentMerchantRef,
        name: selectedMerchantEntry?.name || occupant?.label || existingContext?.merchant?.name || 'Merchant',
        summary: selectedMerchantEntry?.summary || presentation.role || existingContext?.merchant?.summary || '',
        role: presentation.role || existingContext?.merchant?.role || selectedMerchantEntry?.summary || 'Merchant',
        portrait_url: selectedMerchantEntry?.portraitUrl || presentation.portrait_url || existingContext?.merchant?.portrait_url || '',
      },
      stock: hasMerchantStock ? (Array.isArray(presentation.stock) ? presentation.stock : []) : (existingContext?.stock || []),
      player: {
        ...(existingContext?.player || {}),
        character_id: characterId,
        inventory,
        currency: inventory.currency || presentation.player_currency || existingContext?.player?.currency || {},
        currency_label: existingContext?.player?.currency_label || '0 cp',
        sellable_inventory: Array.isArray(sellableInventory) ? sellableInventory : [],
      },
    };

    this.logMerchantPanelTrace('load-success', {
      force,
      merchantRef: this.currentMerchantRef,
      merchantName: this.currentMerchantContext?.merchant?.name || null,
      stockCount: Array.isArray(this.currentMerchantContext?.stock) ? this.currentMerchantContext.stock.length : 0,
      sellableCount: Array.isArray(this.currentMerchantContext?.player?.sellable_inventory) ? this.currentMerchantContext.player.sellable_inventory.length : 0,
      delegatedToShell: true,
    });
    this.renderMerchantPanel(this.currentMerchantContext);
    this.setMerchantStatus(hasMerchantStock ? 'Ready to trade.' : 'Merchant context is syncing...', hasMerchantStock ? 'info' : 'pending');
  }

  renderMerchantPanel(context = null) {
    console.log('[MerchantPanel] renderMerchantPanel', { hasMerchant: !!context?.merchant, stockCount: context?.stock?.length ?? 0 });
    const merchantEntries = Array.isArray(this.currentMerchantCandidates) ? this.currentMerchantCandidates : [];
    const merchant = context?.merchant || null;
    const selectedMerchantEntry = merchantEntries.find((entry) => entry.entityId === this.currentMerchantRef) || merchantEntries[0] || null;
    const player = context?.player || {};
    const stock = Array.isArray(context?.stock) ? context.stock : [];
    const sellableInventory = Array.isArray(player?.sellable_inventory) ? player.sellable_inventory : [];
    const filterText = String(this.currentMerchantFilterText || '').trim().toLowerCase();
    const filteredStock = filterText
      ? stock.filter((item) => this.buildMerchantItemSearchText(item).includes(filterText))
      : stock;
    const filteredSellableInventory = filterText
      ? sellableInventory.filter((item) => this.buildMerchantItemSearchText(item).includes(filterText))
      : sellableInventory;
    const searchState = this.currentMerchantCatalogSearch || {};
    const hasContext = Boolean(context && merchant);
    const fallbackSearchApplies = filterText
      && filteredStock.length === 0
      && filteredSellableInventory.length === 0
      && searchState.query === filterText
      && searchState.roomId === this.currentMerchantRoomId
      && searchState.merchantRef === this.currentMerchantRef;
    const showBackroomButton = hasContext && filterText && filteredStock.length === 0 && filteredSellableInventory.length === 0 && searchState.status !== 'loaded';
    const fallbackStock = fallbackSearchApplies && searchState.status === 'loaded' && Array.isArray(searchState.results)
      ? searchState.results
      : filteredStock;
    let stockEmptyText = filterText ? 'No stock items match the current search.' : 'No stock is listed for this merchant.';
    if (fallbackSearchApplies && searchState.status === 'loading') {
      stockEmptyText = 'No stock items match the current search. Searching the backroom...';
    } else if (fallbackSearchApplies && searchState.status === 'not_found') {
      stockEmptyText = 'No stock items match the current search, and the backroom search came up empty.';
    } else if (fallbackSearchApplies && searchState.status === 'error') {
      stockEmptyText = `No stock items match the current search. ${searchState.error || 'Backroom search failed.'}`;
    }
    this.logMerchantPanelTrace('render', {
      merchantRef: this.currentMerchantRef,
      merchantName: merchant?.name || null,
      merchantCount: merchantEntries.length,
      stockCount: fallbackStock.length,
      sellableCount: filteredSellableInventory.length,
      filterText: filterText || null,
      hasContext: Boolean(context && merchant),
    });

    if (this._el.merchantEntitySelect) {
      const options = merchantEntries.length > 0
        ? merchantEntries.map((entry) => `<option value="${escapeTooltipAttr(entry.entityId)}"${entry.entityId === this.currentMerchantRef ? ' selected' : ''}>${escapeQuestHtml(entry.name)}</option>`)
        : ['<option value="">No merchant available</option>'];
      this._el.merchantEntitySelect.innerHTML = options.join('');
      this._el.merchantEntitySelect.disabled = merchantEntries.length === 0;
    }

    if (this._el.merchantPanelName) {
      this._el.merchantPanelName.textContent = merchant?.name || selectedMerchantEntry?.name || 'No merchant selected';
    }
    if (this._el.merchantPanelSummary) {
      this._el.merchantPanelSummary.textContent = merchant?.summary || merchant?.role || selectedMerchantEntry?.summary || 'Choose a merchant in the active room to browse stock and sell inventory.';
    }
    if (this._el.merchantPanelPortraitWrap && this._el.merchantPanelPortrait) {
      const merchantPortraitUrl = String(merchant?.portrait_url || merchant?.portrait || selectedMerchantEntry?.portraitUrl || '').trim();
      if (merchantPortraitUrl) {
        const merchantName = merchant?.name || selectedMerchantEntry?.name || 'Merchant';
        this._el.merchantPanelPortrait.src = merchantPortraitUrl;
        this._el.merchantPanelPortrait.alt = `${merchantName} portrait`;
        this._el.merchantPanelPortraitWrap.hidden = false;
      } else {
        this._el.merchantPanelPortrait.removeAttribute('src');
        this._el.merchantPanelPortrait.alt = '';
        this._el.merchantPanelPortraitWrap.hidden = true;
      }
    }
    if (this._el.merchantPanelCurrency) {
      this._el.merchantPanelCurrency.textContent = `Party coin: ${player?.currency_label || '0 cp'}`;
    }
    if (this._el.merchantItemFilter) {
      this._el.merchantItemFilter.value = this.currentMerchantFilterText || '';
      this._el.merchantItemFilter.disabled = !hasContext;
    }
    if (this._el.merchantBackroomSearch) {
      this._el.merchantBackroomSearch.hidden = !showBackroomButton;
      this._el.merchantBackroomSearch.disabled = searchState.status === 'loading';
      this._el.merchantBackroomSearch.textContent = searchState.status === 'loading'
        ? 'Searching the backroom...'
        : 'Search the backroom';
    }
    if (this._el.merchantPanelGrid) {
      this._el.merchantPanelGrid.hidden = !hasContext;
    }
    if (this._el.merchantPanelEmpty) {
      this._el.merchantPanelEmpty.hidden = hasContext;
      if (!hasContext) {
        this._el.merchantPanelEmpty.textContent = merchantEntries.length > 0
          ? 'Select a merchant to load trade details.'
          : 'No merchant context is active for this room yet.';
      }
    }

    if (this._el.merchantStockList) {
      this._el.merchantStockList.innerHTML = this.renderMerchantGroupedListHtml(
        fallbackStock,
        stockEmptyText,
        this.renderMerchantStockItemHtml
      );
    }

    if (this._el.merchantSellList) {
      this._el.merchantSellList.innerHTML = this.renderMerchantGroupedListHtml(
        filteredSellableInventory,
        filterText ? 'No sellable items match the current search.' : 'No sellable inventory is available for the active character.',
        this.renderMerchantSellItemHtml
      );
    }

    if (hasContext) {
      console.log('[MerchantPanel] renderMerchantPanel:dom', {
        gridHidden: this._el.merchantPanelGrid?.hidden ?? 'no-el',
        stockHtmlLen: this._el.merchantStockList?.innerHTML?.length ?? 0,
        sellHtmlLen: this._el.merchantSellList?.innerHTML?.length ?? 0,
        panelHidden: this._el.merchantPanelGrid?.closest('#game-panel-merchant')?.hidden ?? 'no-ancestor',
      });
    }
  }

  async dispatchMerchantAction(button) {
    const hexmap = this.stateManager?.hexmap || null;
    const campaignId = Number(hexmap?.resolveCampaignId?.() || 0);
    const roomId = hexmap?.resolveActiveRoomId?.() || null;
    const merchantRef = this.currentMerchantRef;
    if (!campaignId || !roomId || !merchantRef) {
      this.setMerchantStatus('Merchant context is not ready yet.', 'error');
      return;
    }

    const action = button.dataset.merchantAction || '';
    const payload = {
      action,
      character_id: this.resolveActiveMerchantCharacterId(),
      quantity: Number(button.dataset.quantity || 1) || 1,
    };
    if (button.dataset.itemId) {
      payload.item_id = button.dataset.itemId;
    }
    if (button.dataset.itemInstanceId) {
      payload.item_instance_id = button.dataset.itemInstanceId;
    }

    this.logMerchantPanelTrace('trade-submit', {
      action,
      campaignId,
      roomId,
      merchantRef,
      payload,
    });
    this.setMerchantStatus('Submitting trade...', 'pending');

    try {
      const response = await fetch(`/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/merchant/${encodeURIComponent(merchantRef)}/transaction`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      const result = await response.json().catch(() => ({}));
      if (!result || typeof result !== 'object') {
        throw new Error('Merchant trade failed.');
      }

      this.logMerchantPanelTrace('trade-response', {
        action,
        merchantRef,
        ok: response.ok,
        success: Boolean(result.success),
        status: result.status || null,
        message: result.message || result.error || null,
      });
      this.currentMerchantContext = result.context || this.currentMerchantContext;
      this.renderMerchantPanel(this.currentMerchantContext);
      this.syncMerchantContextIntoInventoryPanel(this.currentMerchantContext);
      if (!response.ok || !result.success) {
        this.setMerchantStatus(result.error || result.message || 'Merchant trade failed.', 'error');
        return;
      }
      this.setMerchantStatus(result.message || 'Trade complete.', 'success');
    } catch (error) {
      this.logMerchantPanelTrace('trade-error', {
        action,
        merchantRef,
        error: error?.message || String(error),
      });
      this.setMerchantStatus(error?.message || 'Merchant trade failed.', 'error');
    }
  }

}
