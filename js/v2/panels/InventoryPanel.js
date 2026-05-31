/**
 * @file panels/InventoryPanel.js
 *
 * Character inventory display, item actions, slot assignment.
 * Methods ported verbatim from hexmap.js UIManager.
 * Rendering helpers imported from inventory-utils.js.
 */

import {
  normalizeInventoryState,
  collectInventoryItems,
  formatInventoryItemList,
  renderInventoryPanelList,
  renderInventorySlotGrid,
} from '../utils/inventory-utils.js';

export class InventoryPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.currentCharacterInventoryContext = null;
    this.stateManager = null;
    this.dungeonData = null;
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => document.getElementById(k);
    this._el = {
      inventoryPanel:       id('inventory-panel'),
      inventoryList:        id('inventory-list'),
      inventoryStatus:      id('inventory-status'),
      inventorySlotGrid:    id('inventory-slot-grid'),
    };
    this._subscribe();
    this.setupInventoryPanelActions();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('inventory:changed', (d) => {
        this.currentCharacterInventoryContext = d;
        this.renderInventoryPanel(d);
      }),
      this.bus.on('game:init', (d) => {
        if (d?.inventoryContext) {
          this.currentCharacterInventoryContext = d.inventoryContext;
          this.renderInventoryPanel(d.inventoryContext);
        }
      }),
    );
  }

  setupInventoryPanelActions() {
    if (typeof document === 'undefined' || document.body?.dataset.inventoryActionBound === 'true') {
      return;
    }

    document.body.dataset.inventoryActionBound = 'true';
    this.logInventoryActionTrace('handler-bound', {
      scope: 'document',
      panelSelector: '#sidebar-panel-inventory',
    });
    document.addEventListener('click', (event) => {
      const panel = event.target.closest('#sidebar-panel-inventory');
      if (!panel) {
        return;
      }

      const button = event.target.closest('[data-inventory-action]');
      const row = event.target.closest('.inv-item');
      this.logInventoryActionTrace('raw-click', {
        targetTag: event.target?.tagName || null,
        targetText: String(event.target?.textContent || '').trim().slice(0, 80) || null,
        hasActionButton: Boolean(button),
        action: button?.dataset?.inventoryAction || null,
        itemInstanceId: button?.dataset?.itemInstanceId || row?.dataset?.itemInstanceId || null,
        itemId: button?.dataset?.itemId || row?.dataset?.itemId || null,
        slotKey: button?.dataset?.slotKey || null,
        panelVisible: panel.offsetParent !== null,
      });
      if (button) {
        event.preventDefault();
        this.logInventoryActionTrace('capture-dispatch', {
          action: button.dataset?.inventoryAction || null,
          itemInstanceId: button.dataset?.itemInstanceId || row?.dataset?.itemInstanceId || null,
          itemId: button.dataset?.itemId || row?.dataset?.itemId || null,
          slotKey: button.dataset?.slotKey || null,
        });
        this.dispatchInventoryAction(button);
      }
    }, true);
  }

  dispatchInventoryAction(button) {
    this.handleInventoryAction(button).catch((error) => {
      console.error('Inventory action failed', error);
      this.setInventoryActionStatus(error?.message || 'Inventory action failed.', 'error', { persist: true });
    });
  }

  resolveInventoryActionContext(button) {
    const context = this.currentCharacterInventoryContext;
    const action = String(button?.dataset?.inventoryAction || '').trim();
    const row = button?.closest?.('.inv-item') || null;
    const slotSelect = row?.querySelector?.('[data-slot-select]') || null;
    const slotKey = String(button?.dataset?.slotKey || '').trim();
    const slotLabel = String(button?.dataset?.slotLabel || '').trim();
    const slotIndexRaw = button?.dataset?.slotIndex;
    const slotIndex = slotIndexRaw !== undefined && slotIndexRaw !== '' && Number.isFinite(Number(slotIndexRaw))
      ? Number(slotIndexRaw)
      : null;
    const itemName = String(
      button?.dataset?.itemName
      || row?.querySelector('.inv-item__name')?.textContent
      || row?.dataset?.itemName
      || 'Item'
    ).trim();
    const itemId = String(button?.dataset?.itemId || row?.dataset?.itemId || '').trim();
    let itemInstanceId = String(button?.dataset?.itemInstanceId || row?.dataset?.itemInstanceId || '').trim();

    if (!itemInstanceId && context?.inventory) {
      const inventory = normalizeInventoryState(context.inventory || {}, context.currency || {});
      const candidates = [];
      const pushCandidate = (item, location, candidateSlotKey = null, candidateSlotIndex = null) => {
        if (!item || typeof item !== 'object') {
          return;
        }
        candidates.push({
          itemInstanceId: String(item.item_instance_id || '').trim(),
          itemId: String(item.item_id || item.id || '').trim(),
          itemName: String(item.name || '').trim(),
          location,
          slotKey: candidateSlotKey,
          slotIndex: Number.isInteger(candidateSlotIndex) ? candidateSlotIndex : null,
        });
      };

      const worn = inventory.worn && typeof inventory.worn === 'object' ? inventory.worn : {};
      const slotState = inventory.slotState && typeof inventory.slotState === 'object' ? inventory.slotState : {};
      (Array.isArray(worn.weapons) ? worn.weapons : []).forEach((item) => pushCandidate(item, 'worn'));
      (Array.isArray(worn.accessories) ? worn.accessories : []).forEach((item) => pushCandidate(item, 'worn'));
      if (worn.armor) {
        pushCandidate(worn.armor, 'worn', 'armor', null);
      }
      if (worn.shield) {
        pushCandidate(worn.shield, 'worn', 'shield', null);
      }
      (Array.isArray(inventory.carried) ? inventory.carried : []).forEach((item) => pushCandidate(item, 'carried'));
      (Array.isArray(inventory.equipped) ? inventory.equipped : []).forEach((item) => pushCandidate(item, 'equipped'));
      (Array.isArray(inventory.stashed) ? inventory.stashed : []).forEach((item) => pushCandidate(item, 'stashed'));

      Object.entries(slotState).forEach(([candidateSlotKey, slotValue]) => {
        if (Array.isArray(slotValue)) {
          slotValue.forEach((entry, index) => {
            pushCandidate(entry, 'worn', candidateSlotKey, index);
          });
          return;
        }
        pushCandidate(slotValue, 'worn', candidateSlotKey, null);
      });

      const desiredLocation = action === 'unequip' ? 'worn' : '';
      const exactMatch = candidates.find((candidate) => (
        candidate.itemInstanceId !== ''
        && (!desiredLocation || candidate.location === desiredLocation)
        && (!itemId || candidate.itemId === itemId)
        && (!itemName || candidate.itemName === itemName)
        && (!slotKey || candidate.slotKey === slotKey)
        && (slotIndex === null || candidate.slotIndex === slotIndex)
      ));
      const fallbackMatch = candidates.find((candidate) => (
        candidate.itemInstanceId !== ''
        && (!desiredLocation || candidate.location === desiredLocation)
        && (!itemId || candidate.itemId === itemId)
      ));
      itemInstanceId = exactMatch?.itemInstanceId || fallbackMatch?.itemInstanceId || '';
    }

    return {
      action,
      row,
      slotSelect,
      itemName,
      itemId,
      itemInstanceId,
      slotKey: slotKey || null,
      slotLabel: slotLabel || null,
      slotIndex,
    };
  }

  resolveInventoryAssignSelection(actionContext) {
    const { slotKey, slotIndex, slotLabel, slotSelect } = actionContext;
    let selectedSlotKey = slotKey;
    let selectedSlotIndex = slotIndex;

    if (!selectedSlotKey) {
      const selectedValue = String(
        slotSelect?.value
        || slotSelect?.selectedOptions?.[0]?.value
        || slotSelect?.options?.[0]?.value
        || ''
      );
      const [resolvedSlotKey, resolvedSlotIndex] = selectedValue.split('::');
      selectedSlotKey = resolvedSlotKey;
      selectedSlotIndex = resolvedSlotIndex !== undefined && resolvedSlotIndex !== '' ? Number(resolvedSlotIndex) : null;
    }

    if (!selectedSlotKey) {
      throw new Error('Choose a slot first.');
    }

    const selectedSlotLabel = String(
      slotLabel
      || slotSelect?.selectedOptions?.[0]?.textContent
      || slotSelect?.options?.[slotSelect?.selectedIndex ?? 0]?.textContent
      || selectedSlotKey
    ).trim();

    return {
      selectedSlotKey,
      selectedSlotIndex,
      selectedSlotLabel,
    };
  }

  setInventoryActionStatus(message = '', tone = 'info', options = {}) {
    const status = this._el.inventoryActionStatus;
    if (!status) {
      return;
    }

    if (this.inventoryActionStatusTimer) {
      window.clearTimeout(this.inventoryActionStatusTimer);
      this.inventoryActionStatusTimer = null;
    }

    const nextMessage = String(message || '').trim();
    status.hidden = nextMessage === '';
    status.textContent = nextMessage;
    status.classList.toggle('inventory-panel__status--pending', tone === 'pending' && nextMessage !== '');
    status.classList.toggle('inventory-panel__status--success', tone === 'success' && nextMessage !== '');
    status.classList.toggle('inventory-panel__status--error', tone === 'error' && nextMessage !== '');

    if (nextMessage !== '' && !options.persist && tone !== 'pending') {
      this.inventoryActionStatusTimer = window.setTimeout(() => {
        this.setInventoryActionStatus('', 'info', { persist: true });
      }, 2400);
    }
  }

  logInventoryActionTrace(stage, details = {}, level = 'info') {
    const payload = {
      stage: String(stage || '').trim() || 'unknown',
      timestamp: new Date().toISOString(),
      ...(details && typeof details === 'object' ? details : {}),
    };

    const globalScope = typeof globalThis !== 'undefined'
      ? globalThis
      : (typeof window !== 'undefined' ? window : null);
    if (globalScope) {
      if (!Array.isArray(globalScope.dcInventoryActionLog)) {
        globalScope.dcInventoryActionLog = [];
      }
      globalScope.dcInventoryActionLog.push(payload);
    }

    const consoleMethod = level === 'error'
      ? 'error'
      : (level === 'warn' ? 'warn' : 'info');
    if (typeof console !== 'undefined' && typeof console[consoleMethod] === 'function') {
      console[consoleMethod]('[InventoryAction]', payload);
    }

    return payload;
  }

  renderInventoryPanel(context) {
    const inventory = normalizeInventoryState(context?.inventory || {}, context?.currency || {});
    const items = collectInventoryItems(inventory, context?.equipment || []);
    const summaryHtml = formatInventoryItemList(items);
    const feedback = context?.inventoryActionFeedback || null;
    const panelHtml = renderInventoryPanelList(items, inventory, feedback);
    const currency = inventory.currency || context?.currency || {};
    const abilities = context?.abilities || {};
    const totalBulk = inventory.totalBulk ?? estimateInventoryBulk(items);
    const bulkMax = Math.max(5, Number(this._el.inventoryBulkMax?.textContent || 0), Number(abilities.strength || abilities.str || 0));

    if (this._el.characterInventory) {
      this._el.characterInventory.innerHTML = summaryHtml || '<li class="inventory-empty">No items</li>';
    }
    if (this._el.inventoryPp) this._el.inventoryPp.textContent = currency.pp || 0;
    if (this._el.inventoryGp) this._el.inventoryGp.textContent = currency.gp || 0;
    if (this._el.inventoryEp) this._el.inventoryEp.textContent = currency.ep || 0;
    if (this._el.inventorySp) this._el.inventorySp.textContent = currency.sp || 0;
    if (this._el.inventoryCp) this._el.inventoryCp.textContent = currency.cp || 0;
    if (this._el.inventoryBulkCurrent) this._el.inventoryBulkCurrent.textContent = formatBulkValue(totalBulk);
    if (this._el.inventoryBulkMax) this._el.inventoryBulkMax.textContent = formatBulkValue(bulkMax);
    if (this._el.inventorySlotGrid) {
      this._el.inventorySlotGrid.innerHTML = renderInventorySlotGrid(inventory, feedback);
    }
    if (this._el.inventoryItemList) {
      this._el.inventoryItemList.innerHTML = panelHtml;
    }
    this.setInventoryActionStatus(feedback?.message || '', feedback?.tone || 'info', { persist: true });
    this.logInventoryActionTrace('panel-render', {
      characterId: context?.characterId || null,
      campaignId: context?.campaignId || null,
      itemCount: items.length,
      carriedCount: Array.isArray(inventory.carried) ? inventory.carried.length : 0,
      wornCount: collectWornInventoryItems(inventory.worn).length,
      assignButtonCount: this._el.inventoryItemList?.querySelectorAll?.('[data-inventory-action="assign"]')?.length || 0,
      unequipButtonCount: (this._el.inventoryItemList?.querySelectorAll?.('[data-inventory-action="unequip"]')?.length || 0)
        + (this._el.inventorySlotGrid?.querySelectorAll?.('[data-inventory-action="unequip"]')?.length || 0),
    });

    if (typeof window !== 'undefined') {
      window.dcInventoryPanelManaged = true;
      window.dcRefreshInventoryPanel = () => {
        if (!this.currentCharacterInventoryContext) {
          return false;
        }
        this.renderInventoryPanel(this.currentCharacterInventoryContext);
        return true;
      };
      if (typeof window.dcAttachTooltips === 'function' && this._el.inventoryItemList) {
        window.dcAttachTooltips(this._el.inventoryItemList);
      }
      if (typeof window.dcApplyInventoryFilter === 'function') {
        window.dcApplyInventoryFilter();
      }
    }
  }

  async handleInventoryAction(button) {
    const context = this.currentCharacterInventoryContext;
    if (!context?.characterId) {
      throw new Error('No character is selected.');
    }

    const actionContext = this.resolveInventoryActionContext(button);
    const {
      row,
      itemInstanceId,
      action,
      itemName,
      slotSelect,
      slotKey,
      slotLabel,
      slotIndex,
    } = actionContext;
    if (!itemInstanceId || !action) {
      throw new Error('Missing inventory item context.');
    }

    const payload = {
      location: action === 'unequip' ? 'carried' : 'worn',
    };
    let selectedSlotLabel = '';
    if (context.campaignId) {
      payload.campaignId = context.campaignId;
    }
    if (action === 'assign') {
      const resolvedAssignTarget = this.resolveInventoryAssignSelection(actionContext);
      selectedSlotLabel = resolvedAssignTarget.selectedSlotLabel;
      payload.equippedSlotKey = resolvedAssignTarget.selectedSlotKey;
      if (resolvedAssignTarget.selectedSlotIndex !== undefined && resolvedAssignTarget.selectedSlotIndex !== null && resolvedAssignTarget.selectedSlotIndex !== '') {
        payload.equippedSlotIndex = Number(resolvedAssignTarget.selectedSlotIndex);
      }
    }

    this.logInventoryActionTrace('click', {
      action,
      characterId: context.characterId,
      campaignId: context.campaignId || null,
      itemInstanceId,
      itemId: actionContext.itemId || null,
      itemName,
      slotKey: action === 'assign' ? (payload.equippedSlotKey || null) : slotKey,
      slotIndex: action === 'assign'
        ? (Number.isInteger(payload.equippedSlotIndex) ? payload.equippedSlotIndex : null)
        : slotIndex,
      source: row ? 'inventory-list' : 'inventory-slot',
    });

    this.currentCharacterInventoryContext = {
      ...context,
      inventoryActionFeedback: {
        tone: 'pending',
        action,
        itemInstanceId,
        slotKey: action === 'assign' ? (payload.equippedSlotKey || null) : slotKey,
        slotIndex: action === 'assign'
          ? (Number.isInteger(payload.equippedSlotIndex) ? payload.equippedSlotIndex : null)
          : slotIndex,
        message: action === 'assign'
          ? `${itemName} -> ${selectedSlotLabel || 'selected slot'}`
          : `${itemName} -> carried inventory`,
      },
    };
    this.renderInventoryPanel(this.currentCharacterInventoryContext);

    try {
      this.logInventoryActionTrace('request', {
        action,
        characterId: context.characterId,
        campaignId: context.campaignId || null,
        itemInstanceId,
        itemId: actionContext.itemId || null,
        itemName,
        payload: { ...payload },
      });
      const response = await fetch(`/api/inventory/character/${encodeURIComponent(context.characterId)}/item/${encodeURIComponent(itemInstanceId)}/location`, {
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

      if (!response.ok || !result?.success) {
        throw new Error(result?.error || result?.message || 'Inventory update failed.');
      }

      const nextInventory = normalizeInventoryState(result.inventory || {}, context.currency || {});
      this.logInventoryActionTrace('success', {
        action,
        characterId: context.characterId,
        campaignId: context.campaignId || null,
        itemInstanceId,
        itemId: actionContext.itemId || null,
        itemName,
        responseMessage: result?.message || null,
        location: payload.location,
        slotKey: action === 'assign' ? (payload.equippedSlotKey || null) : slotKey,
        slotIndex: action === 'assign' && Number.isInteger(payload.equippedSlotIndex) ? payload.equippedSlotIndex : slotIndex,
      });
      this.currentCharacterInventoryContext = {
        ...this.currentCharacterInventoryContext,
        inventory: nextInventory,
        currency: nextInventory.currency || context.currency || {},
        inventoryActionFeedback: {
          tone: 'success',
          action,
          itemInstanceId,
          slotKey: action === 'assign' ? (payload.equippedSlotKey || null) : slotKey,
          slotIndex: action === 'assign' && Number.isInteger(payload.equippedSlotIndex) ? payload.equippedSlotIndex : slotIndex,
          message: action === 'assign'
            ? `${itemName} assigned to ${selectedSlotLabel || 'selected slot'}.`
            : `${itemName} moved back to carried inventory.`,
        },
      };
      this.renderInventoryPanel(this.currentCharacterInventoryContext);
    } catch (error) {
      this.logInventoryActionTrace('failure', {
        action,
        characterId: context.characterId,
        campaignId: context.campaignId || null,
        itemInstanceId,
        itemId: actionContext.itemId || null,
        itemName,
        location: payload.location,
        slotKey: action === 'assign' ? (payload.equippedSlotKey || null) : slotKey,
        slotIndex: action === 'assign'
          ? (Number.isInteger(payload.equippedSlotIndex) ? payload.equippedSlotIndex : null)
          : slotIndex,
        error: error?.message || 'Inventory update failed.',
      }, 'error');
      this.currentCharacterInventoryContext = {
        ...context,
        inventoryActionFeedback: {
          tone: 'error',
          action,
          itemInstanceId,
          slotKey: action === 'assign' ? (payload.equippedSlotKey || null) : slotKey,
          slotIndex: action === 'assign'
            ? (Number.isInteger(payload.equippedSlotIndex) ? payload.equippedSlotIndex : null)
            : slotIndex,
          message: error?.message || 'Inventory update failed.',
        },
      };
      this.renderInventoryPanel(this.currentCharacterInventoryContext);
      throw error;
    }
  }

}
