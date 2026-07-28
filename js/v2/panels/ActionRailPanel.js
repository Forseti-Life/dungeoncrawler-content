/**
 * @file panels/ActionRailPanel.js
 *
 * 3-action economy UI — tab-driven action rail categories.
 * Methods ported verbatim from hexmap.js UIManager.
 */

import { getActionRailCost, formatActionRailCost, getActionRailRemainingActions } from '../utils/action-utils.js';
import { normalizeSpellcastingData, collectSpellRankGroups, normalizeDisplayedSpellSlots } from '../utils/spell-utils.js';
import { extractConsumableItems, collectCharacterSkillEntries, buildActionRailEntrySummary } from '../utils/inventory-utils.js';
import { escapeQuestHtml } from '../utils/quest-utils.js';
import { escapeTooltipAttr, flattenTooltipBuckets, slugifyTooltipKey } from '../utils/dom-utils.js';
import { buildActionRailContext } from '../services/action-rail-context-service.js';
import { buildNavigateActionRailPanel } from '../services/action-rail-navigate-panel-service.js?v=20260723-v2-nav-exit-numbering-4';
import {
  getActionRailDirectRoute,
  getServerActionIdForExecute,
  isActionRailSelectableAction,
  resolveActionRailCategory as resolveContractActionRailCategory,
} from '../contracts/action-rail-contract.js';

const ACTION_RAIL_DOMAIN_ALL = 'all';
const ACTION_RAIL_CATEGORY_DEPENDENCIES = {
  navigate: ['navigation', 'room'],
  turn: ['turn', 'combat'],
  search: ['turn', 'combat', 'room'],
  rest: ['character', 'inventory', 'room'],
  spells: ['character', 'inventory', 'turn', 'combat'],
  consumables: ['inventory', 'turn', 'combat'],
  skills: ['character', 'turn', 'combat'],
  feats: ['character', 'inventory', 'turn', 'combat'],
};
const ACTION_RAIL_HEADER_DEPENDENCIES = ['header', 'turn', 'combat', 'automation', 'clock', 'character', 'room'];

function resolveSkillEntry(state, skillName) {
  const target = String(skillName || '').trim().toLowerCase();
  return collectCharacterSkillEntries(state).find((entry) => String(entry?.name || '').trim().toLowerCase() === target) || null;
}

function resolveSkillRank(proficiency) {
  switch (String(proficiency || '').trim().toLowerCase()) {
    case 'trained': return 1;
    case 'expert': return 2;
    case 'master': return 3;
    case 'legendary': return 4;
    default: return 0;
  }
}

function collectInventoryCandidates(state) {
  const inventory = state?.inventory || {};
  const equipment = state?.equipment || {};
  return [
    ...(Array.isArray(inventory.items) ? inventory.items : []),
    ...(Array.isArray(inventory.equipped) ? inventory.equipped : []),
    ...(Array.isArray(inventory.carried) ? inventory.carried : []),
    ...(Array.isArray(equipment.held) ? equipment.held : []),
    ...(Array.isArray(equipment.worn) ? equipment.worn : []),
  ];
}

function hasInventoryMatch(state, matcher) {
  return collectInventoryCandidates(state).some((item) => {
    const itemId = String(item?.item_id || item?.id || '').trim().toLowerCase();
    const itemName = String(item?.name || '').trim().toLowerCase();
    return matcher(itemId, itemName, item);
  });
}

function resolveFocusPoints(state, field) {
  const resources = state?.resources || {};
  const focus = resources.focusPoints || state?.focusPoints || {};
  const value = Number(focus?.[field]);
  return Number.isFinite(value) ? value : 0;
}

function resolveCharacterLevel(state) {
  const value = Number(state?.basicInfo?.level ?? state?.level ?? 1);
  return Number.isFinite(value) && value > 0 ? value : 1;
}

function resolveConstitutionModifier(state) {
  const candidates = [
    state?.abilityScores?.constitution?.modifier,
    state?.abilities?.constitution?.modifier,
    state?.attributes?.constitution?.modifier,
    state?.stats?.constitutionModifier,
  ];
  const value = Number(candidates.find((candidate) => Number.isFinite(Number(candidate))) ?? 0);
  return Number.isFinite(value) ? value : 0;
}

export class ActionRailPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.stateManager = null;
    this.dungeonData = null;
    this._actionRailRealtimeTimer = null;
    // Timer for real-world clock updates (separate naming to match usage)
    this.actionRailRealClockTimer = null;
    // UI state
    this.activeActionRailCategory = 'navigate';
    this.actionRailDescriptionsCollapsed = true;
    this.actionRailFilters = {};
    this.actionRailAutomationTogglePending = false;
    this.navigateLocationGroups = [];
    this.navigateLocationsCampaignId = null;
    this.navigateLocationsInflight = null;
    this.navigateActiveRoom = null;
    this._lastRoomTransitionId = '';
    this._actionRailRequestSequence = 0;
    this._actionRailRefreshRaf = null;
    this._actionRailRefreshTimer = null;
    this._actionRailDirtyDomains = new Set([ACTION_RAIL_DOMAIN_ALL]);
    this._lastHeaderFingerprint = '';
    this._lastCategoryFingerprints = {};
    this._actionRailMetrics = {
      flushes: 0,
      skips: 0,
      headerRenders: 0,
      bodyRenders: 0,
    };
    this._domListeners = [];
  }

  buildRestActionRailPanel(context) {
    const medicine = resolveSkillEntry(context.state, 'medicine');
    const crafting = resolveSkillEntry(context.state, 'crafting');
    const focusCurrent = resolveFocusPoints(context.state, 'current');
    const focusMax = resolveFocusPoints(context.state, 'max');
    const hasHealersTools = hasInventoryMatch(
      context.state,
      (itemId, itemName) => itemId === 'healers_tools' || itemName.includes("healer")
    );
    const restEntries = [
      {
        key: 'treat_wounds',
        title: 'Treat Wounds',
        summary: buildActionRailEntrySummary([
          '10 minutes',
          medicine ? `${Number(medicine.modifier || 0) >= 0 ? '+' : ''}${Number(medicine.modifier || 0)} Medicine` : 'Medicine required',
          hasHealersTools ? "Healer's tools ready" : "Needs healer's tools",
        ]),
        meta: 'Treat your own wounds during a safe room pause. Requires Medicine training.',
        dataset: {
          targetId: String(context.actorRef || ''),
        },
      },
      {
        key: 'refocus',
        title: 'Refocus',
        summary: buildActionRailEntrySummary([
          '10 minutes',
          focusMax > 0 ? `${focusCurrent}/${focusMax} Focus Points` : 'No Focus Points',
        ]),
        meta: 'Recover 1 Focus Point by meditating, praying, or centering yourself.',
        dataset: {},
      },
      {
        key: 'repair',
        title: 'Repair',
        summary: buildActionRailEntrySummary([
          '10 minutes',
          crafting ? `${Number(crafting.modifier || 0) >= 0 ? '+' : ''}${Number(crafting.modifier || 0)} Crafting` : 'Crafting check',
          'Repairs held shield or gear',
        ]),
        meta: 'Patch up damaged equipment before pushing deeper into the dungeon.',
        dataset: {},
      },
      {
        key: 'daily_preparations',
        title: 'Daily Preparations',
        summary: buildActionRailEntrySummary([
          '8 hours',
          `Level ${resolveCharacterLevel(context.state)}`,
          'Restore daily resources',
        ]),
        meta: 'Take a full overnight rest: restore spell slots, focus, and recover level-based HP.',
        dataset: {},
      },
    ];

    const entries = restEntries
      .filter((entry) => this.isServerActionAvailable(context, entry.key))
      .map((entry) => this.renderActionRailEntry({
        execute: entry.key,
        title: entry.title,
        summary: entry.summary,
        meta: entry.meta,
        disabled: this.isActionRailExecutionDisabled(0, context, !this.isServerActionAvailable(context, entry.key)),
        dataset: entry.dataset,
        actionLabel: entry.title,
      }));

    return {
      title: 'Rest actions',
      chip: entries.length ? `${entries.length} safe-room actions` : 'Unavailable',
      html: entries.length
        ? entries.join('')
        : `<div class="action-rail__empty"><p>Rest actions unlock only in rooms flagged as safe for rest.</p></div>`,
    };
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => (typeof document !== 'undefined' ? document.getElementById(k) : null);
    this._el = {
      actionRail:                 id('hexmap-action-rail'),
      actionRailActorCard:        id('action-rail-actor-card'),
      actionRailActorName:        id('action-rail-actor-name'),
      actionRailActorImage:       id('action-rail-actor-image'),
      actionRailActorInitial:     id('action-rail-actor-initial'),
      actionRailStatus:           id('action-rail-status'),
      actionRailAutomationToggle: id('action-rail-automate-toggle'),
      actionRailAutomationMeta:   id('action-rail-automation-meta'),
      actionRailRealClock:        id('action-rail-real-clock'),
      actionRailRealClockMeta:    id('action-rail-real-clock-meta'),
      actionRailCampaignClock:    id('action-rail-campaign-clock'),
      actionRailCampaignClockMeta: id('action-rail-campaign-clock-meta'),
      actionRailCategories:       id('action-rail-categories'),
      actionRailPanelTitle:       id('action-rail-panel-title'),
      actionRailPanelChip:        id('action-rail-panel-chip'),
      actionRailPanelBody:        id('action-rail-panel-body'),
      actionInstruction:          id('action-instruction'),
    };
    this._subscribe();
    this.setupActionRail();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this.teardownActionRailDomListeners();
    if (this._actionRailRefreshRaf !== null && typeof window !== 'undefined' && typeof window.cancelAnimationFrame === 'function') {
      window.cancelAnimationFrame(this._actionRailRefreshRaf);
      this._actionRailRefreshRaf = null;
    }
    if (this._actionRailRefreshTimer !== null) {
      clearTimeout(this._actionRailRefreshTimer);
      this._actionRailRefreshTimer = null;
    }
    if (this._actionRailRealtimeTimer) clearInterval(this._actionRailRealtimeTimer);
    if (this.actionRailRealClockTimer) clearInterval(this.actionRailRealClockTimer);
  }

  invalidateActionRail(domains = [ACTION_RAIL_DOMAIN_ALL]) {
    const nextDomains = Array.isArray(domains) ? domains : [domains];
    if (!nextDomains.length) {
      this._actionRailDirtyDomains.add(ACTION_RAIL_DOMAIN_ALL);
    } else {
      nextDomains.forEach((domain) => {
        const normalized = String(domain || '').trim().toLowerCase();
        if (normalized) {
          this._actionRailDirtyDomains.add(normalized);
        }
      });
    }
    this.scheduleActionRailFlush();
  }

  queueActionRailRefresh() {
    this.invalidateActionRail([ACTION_RAIL_DOMAIN_ALL]);
  }

  scheduleActionRailFlush() {
    if (this._actionRailRefreshRaf !== null || this._actionRailRefreshTimer !== null) {
      return;
    }
    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      this._actionRailRefreshRaf = window.requestAnimationFrame(() => {
        this._actionRailRefreshRaf = null;
        this.flushActionRailRefresh();
      });
      return;
    }
    this._actionRailRefreshTimer = setTimeout(() => {
      this._actionRailRefreshTimer = null;
      this.flushActionRailRefresh();
    }, 0);
  }

  flushActionRailRefresh() {
    const dirtyDomains = this._actionRailDirtyDomains.size
      ? Array.from(this._actionRailDirtyDomains)
      : [ACTION_RAIL_DOMAIN_ALL];
    this._actionRailDirtyDomains.clear();
    this._actionRailMetrics.flushes += 1;
    this.refreshActionRail(dirtyDomains);
  }

  getActionRailMetrics() {
    return { ...this._actionRailMetrics };
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('combat:turn-changed', (d) => {
        this.invalidateActionRail(['turn', 'combat', 'header']);
        this.updateActionRailClocks(d);
      }),
      this.bus.on('combat:state-changed', () => this.invalidateActionRail(['combat', 'header'])),
      this.bus.on('game:init', () => this.invalidateActionRail([ACTION_RAIL_DOMAIN_ALL])),
      this.bus.on('room:changed', (payload) => this.handleRoomContextChanged(payload, 'room:changed')),
      this.bus.on('room:transitioned', (payload) => this.handleRoomContextChanged(payload, 'room:transitioned')),
      this.bus.on('room:occupants-changed', (payload) => this.handleRoomContextChanged(payload, 'room:occupants-changed')),
      this.bus.on('room:occupants-decoration-changed', (payload) => this.handleRoomContextChanged(payload, 'room:occupants-decoration-changed')),
      this.bus.on('character:updated', () => this.invalidateActionRail(['character', 'header'])),
      this.bus.on('inventory:changed', () => this.invalidateActionRail(['inventory'])),
      this.bus.on('quest:progress-updated', () => this.invalidateActionRail(['quest'])),
      this.bus.on('navigation:capabilities-updated', () => this.invalidateActionRail(['navigation'])),
      this.bus.on('merchant:stock-loaded', () => this.invalidateActionRail(['merchant'])),
    );
  }

  handleRoomContextChanged(payload = {}, sourceEvent = '') {
    const transitionId = String(payload?.transition?.id || '').trim();
    if (transitionId && transitionId === this._lastRoomTransitionId) {
      return;
    }
    const normalizedSource = String(sourceEvent || '').trim().toLowerCase();
    if (transitionId && (normalizedSource === 'room:changed' || normalizedSource === 'room:transitioned')) {
      this._lastRoomTransitionId = transitionId;
      this.navigateActiveRoom = null;
      this.navigateLocationsCampaignId = null;
      this.invalidateActionRail(['room', 'navigation', 'header']);
      return;
    }

    // Room-level authoritative changes should invalidate navigate and header.
    if (normalizedSource === 'room:changed' || normalizedSource === 'room:transitioned') {
      this.navigateActiveRoom = null;
      this.navigateLocationsCampaignId = null;
      this.invalidateActionRail(['room', 'navigation', 'header']);
      return;
    }

    // Occupant fan-out (merchant enrichment, decoration, transient overlays)
    // must not force navigation recomputation.
    this.invalidateActionRail(['occupants-decoration']);
  }

  setupActionRail() {
    const categories = this._el.actionRailCategories;
    const panelBody = this._el.actionRailPanelBody;
    const automationToggle = this._el.actionRailAutomationToggle;
    const actorCard = this._el.actionRailActorCard;
    console.log('[ActionRailPanel] setupActionRail', {
      hasCategories: !!categories,
      hasPanelBody: !!panelBody,
      hasAutomationToggle: !!automationToggle,
      hasActorCard: !!actorCard,
    });
    this.updateActionRailClocks();
    if (!this.actionRailRealClockTimer) {
      this.actionRailRealClockTimer = setInterval(() => {
        this.updateActionRailClocks();
      }, 1000);
    }
    if (!categories) {
      console.warn('[ActionRailPanel] setupActionRail: categories element missing — panelBody click listener NOT bound');
      this.refreshActionRail();
      return;
    }
    this.teardownActionRailDomListeners();

    if (automationToggle) {
      this.bindActionRailDomListener(automationToggle, 'click', () => {
        this.handleActionRailAutomationToggle();
      });
    }
    if (actorCard) {
      this.bindActionRailDomListener(actorCard, 'click', () => {
        this.handleActionRailActorCardActivate();
      });
      this.bindActionRailDomListener(actorCard, 'keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
          return;
        }
        event.preventDefault();
        this.handleActionRailActorCardActivate();
      });
    }

    this.bindActionRailDomListener(categories, 'click', (event) => {
      const button = event.target instanceof HTMLElement
        ? event.target.closest('[data-action-rail-category]')
        : null;
      if (!(button instanceof HTMLButtonElement) || button.disabled) {
        return;
      }

      const category = button.dataset.actionRailCategory || '';
      if (!category) {
        return;
      }

      this.setActiveActionRailCategory(category, { refresh: true });
    });
    this.bindActionRailDomListener(categories, 'keydown', (event) => {
      const target = event.target instanceof HTMLElement
        ? event.target.closest('[data-action-rail-category]')
        : null;
      if (!(target instanceof HTMLButtonElement)) {
        return;
      }

      const buttons = Array.from(categories.querySelectorAll('[data-action-rail-category]'))
        .filter((button) => button instanceof HTMLButtonElement);
      const index = buttons.indexOf(target);
      if (index < 0 || buttons.length === 0) {
        return;
      }

      let nextIndex = index;
      if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        nextIndex = (index + 1) % buttons.length;
      } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        nextIndex = (index - 1 + buttons.length) % buttons.length;
      } else if (event.key === 'Home') {
        nextIndex = 0;
      } else if (event.key === 'End') {
        nextIndex = buttons.length - 1;
      } else {
        return;
      }

      event.preventDefault();
      const nextButton = buttons[nextIndex];
      const nextCategory = nextButton?.dataset?.actionRailCategory || '';
      if (!nextCategory) {
        return;
      }
      this.setActiveActionRailCategory(nextCategory, { refresh: true, focus: true });
    });

    if (panelBody) {
      console.log('[ActionRailPanel] setupActionRail: binding panelBody click listener');
      this.bindActionRailDomListener(panelBody, 'click', (event) => {
        const toggle = event.target instanceof HTMLElement
          ? event.target.closest('[data-action-rail-toggle-descriptions]')
          : null;
        if (toggle instanceof HTMLButtonElement) {
          this.actionRailDescriptionsCollapsed = !this.actionRailDescriptionsCollapsed;
          this.syncActionRailPanelState();
          return;
        }
        const button = event.target instanceof HTMLElement
          ? event.target.closest('[data-action-rail-execute]')
          : null;
        if (!(button instanceof HTMLButtonElement)) {
          return;
        }
        if (button.disabled) {
          console.warn('[ActionRailPanel] panelBody click: button is disabled, ignoring', {
            execute: button.dataset.actionRailExecute,
            roomId: button.dataset.roomId,
            disabled: button.disabled,
            ariaBusy: button.getAttribute('aria-busy'),
          });
          return;
        }
        this.handleActionRailPanelAction(button);
      });
      this.bindActionRailDomListener(panelBody, 'input', (event) => {
        const input = event.target instanceof HTMLElement
          ? event.target.closest('[data-action-rail-filter]')
          : null;
        if (!(input instanceof HTMLInputElement)) {
          return;
        }
        const category = input.dataset.actionRailFilterCategory || this.activeActionRailCategory || '';
        this.actionRailFilters[category] = input.value || '';
        this.syncActionRailPanelState();
      });
    }

    this.refreshActionRail();
  }

  bindActionRailDomListener(target, type, handler, options = undefined) {
    if (!target || typeof target.addEventListener !== 'function') {
      return;
    }
    target.addEventListener(type, handler, options);
    this._domListeners.push(() => {
      target.removeEventListener(type, handler, options);
    });
  }

  teardownActionRailDomListeners() {
    this._domListeners.forEach((unbind) => unbind());
    this._domListeners = [];
  }

  resolveActionRailActorCardTarget(context = null) {
    const resolvedContext = context || this.getActionRailContext();
    const selectedEntity = this.stateManager?.get?.('selectedEntity') || null;
    const selectedRef = String(
      selectedEntity?.dcEntityRef
      || selectedEntity?.dcEntityInstanceId
      || selectedEntity?.instanceId
      || selectedEntity?.id
      || ''
    ).trim();
    const selectedCharacterId = Number(
      selectedEntity?.dcCharacterId
      || selectedEntity?.dcStatePayload?.metadata?.character_id
      || selectedEntity?.dcStatePayload?.character_id
      || selectedEntity?.dcStatePayload?.state?.character_id
      || 0
    ) || 0;

    if (selectedEntity && (selectedRef || selectedCharacterId > 0)) {
      const identity = selectedEntity.getComponent?.('IdentityComponent');
      const render = selectedEntity.getComponent?.('RenderComponent');
      const metadata = selectedEntity?.dcStatePayload?.metadata || selectedEntity?.dcStatePayload?.state?.metadata || {};
      const portraitUrl = String(
        metadata?.portrait_url
        || metadata?.portrait
        || (render?.spriteKey ? this.stateManager?.hexmap?.spriteService?.getCachedUrl?.(render.spriteKey) : '')
        || ''
      ).trim();
      return {
        actorRef: selectedRef,
        characterId: selectedCharacterId || null,
        actorLabel: String(identity?.name || selectedEntity?.dcStatePayload?.label || resolvedContext?.actorLabel || 'Selected actor').trim(),
        actorPortraitUrl: portraitUrl,
        entity: selectedEntity,
      };
    }

    return {
      actorRef: String(resolvedContext?.actorRef || '').trim(),
      characterId: Number(resolvedContext?.characterId || 0) || null,
      actorLabel: String(resolvedContext?.actorLabel || 'No actor selected').trim(),
      actorPortraitUrl: String(resolvedContext?.actorPortraitUrl || '').trim(),
      entity: null,
    };
  }

  handleActionRailActorCardActivate() {
    const context = this.getActionRailContext();
    const target = this.resolveActionRailActorCardTarget(context);
    const actorRef = String(target.actorRef || '').trim();
    const canOpen = Boolean(target.characterId || actorRef);
    if (!canOpen) {
      return;
    }

    const hexmap = this.stateManager?.hexmap || null;
    if (target.entity && typeof hexmap?.selectEntity === 'function') {
      hexmap.selectEntity(target.entity);
    } else if (actorRef && hexmap?.entityManager?.getEntity && typeof hexmap?.selectEntity === 'function') {
      const actorEntity = hexmap.entityManager.getEntity(actorRef);
      if (actorEntity) {
        hexmap.selectEntity(actorEntity);
      }
    }

    if (target.characterId) {
      this.bus.emit('character:sheet-requested', { characterId: target.characterId });
    }

    if (typeof hexmap?.activateGameShellTab === 'function') {
      hexmap.activateGameShellTab('party');
      return;
    }

    const shell = typeof document !== 'undefined'
      ? document.querySelector('[data-game-shell]')
      : null;
    if (shell instanceof HTMLElement) {
      shell.dispatchEvent(new CustomEvent('dungeoncrawler:activate-tab', {
        detail: { tabId: 'party' },
      }));
    }
  }

  resolveActionRailCategory(category = '') {
    return resolveContractActionRailCategory(category, 'navigate');
  }

  setActiveActionRailCategory(category, { refresh = true, focus = false } = {}) {
    const resolvedCategory = this.resolveActionRailCategory(category);
    this.activeActionRailCategory = resolvedCategory;

    if (focus) {
      const categories = this._el.actionRailCategories;
      const nextButton = categories?.querySelector?.(`[data-action-rail-category="${resolvedCategory}"]`);
      if (nextButton instanceof HTMLButtonElement) {
        nextButton.focus();
      }
    }

    if (refresh) {
      this.invalidateActionRail([ACTION_RAIL_DOMAIN_ALL]);
    }
  }

  shouldRefreshActionRailHeader(dirtyDomains = []) {
    if (!Array.isArray(dirtyDomains) || dirtyDomains.length === 0) {
      return true;
    }
    if (dirtyDomains.includes(ACTION_RAIL_DOMAIN_ALL)) {
      return true;
    }
    return dirtyDomains.some((domain) => ACTION_RAIL_HEADER_DEPENDENCIES.includes(String(domain || '').trim().toLowerCase()));
  }

  getActiveCategoryDomains(category = '') {
    const resolvedCategory = this.resolveActionRailCategory(category || this.activeActionRailCategory);
    return ACTION_RAIL_CATEGORY_DEPENDENCIES[resolvedCategory] || [ACTION_RAIL_DOMAIN_ALL];
  }

  shouldRefreshActionRailBody(dirtyDomains = [], activeCategory = '') {
    if (!Array.isArray(dirtyDomains) || dirtyDomains.length === 0) {
      return true;
    }
    if (dirtyDomains.includes(ACTION_RAIL_DOMAIN_ALL)) {
      return true;
    }
    const categoryDomains = this.getActiveCategoryDomains(activeCategory);
    return dirtyDomains.some((domain) => categoryDomains.includes(String(domain || '').trim().toLowerCase()));
  }

  buildActionRailHeaderFingerprint(context = null, actorCardTarget = null) {
    const resolvedContext = context || this.getActionRailContext();
    const target = actorCardTarget || this.resolveActionRailActorCardTarget(resolvedContext);
    const automation = resolvedContext?.automationState || {};
    const clock = resolvedContext?.campaignClock || {};
    return JSON.stringify({
      actorRef: String(target?.actorRef || '').trim(),
      characterId: Number(target?.characterId || 0) || 0,
      actorLabel: String(target?.actorLabel || resolvedContext?.actorLabel || '').trim(),
      actorPortrait: String(target?.actorPortraitUrl || resolvedContext?.actorPortraitUrl || '').trim(),
      statusLabel: String(resolvedContext?.statusLabel || '').trim(),
      automationActive: Boolean(automation?.active),
      automationInflight: Boolean(automation?.inflight || this.actionRailAutomationTogglePending),
      automationStatusLabel: String(automation?.statusLabel || '').trim(),
      canAutomate: Boolean(resolvedContext?.canAutomate),
      encounterActive: Boolean(resolvedContext?.encounterActive),
      turnEntity: String(resolvedContext?.phaseSnapshot?.turn?.entity || '').trim(),
      actionsRemaining: Number(resolvedContext?.phaseSnapshot?.turn?.actions_remaining ?? -1),
      clockDatetime: String(clock?.datetime || '').trim(),
      clockTimezone: String(clock?.timezone || '').trim(),
    });
  }

  buildActionRailCategoryFingerprint(category = '', context = null) {
    const resolvedCategory = this.resolveActionRailCategory(category || this.activeActionRailCategory);
    const resolvedContext = context || this.getActionRailContext();
    const roomId = String(resolvedContext?.runtimeContext?.roomId || resolvedContext?.hexmap?.resolveActiveRoomId?.() || '').trim();
    const actionContractActions = Array.isArray(resolvedContext?.actionContract?.actions)
      ? resolvedContext.actionContract.actions.map((action) => ({
        id: String(action?.id || ''),
        available: action?.available !== false,
      }))
      : [];
    const common = {
      category: resolvedCategory,
      actorRef: String(resolvedContext?.actorRef || '').trim(),
      characterId: Number(resolvedContext?.characterId || 0) || 0,
      roomId,
      encounterActive: Boolean(resolvedContext?.encounterActive),
      isActorTurn: Boolean(resolvedContext?.isActorTurn),
      availableActions: Array.isArray(resolvedContext?.availableActions) ? resolvedContext.availableActions : [],
      actionContractActions,
    };

    switch (resolvedCategory) {
      case 'navigate': {
        const capabilities = Array.isArray(resolvedContext?.hexmap?.resolveNavigationCapabilities?.(roomId))
          ? resolvedContext.hexmap.resolveNavigationCapabilities(roomId)
          : [];
        return JSON.stringify({
          ...common,
          exits: capabilities.map((capability) => ({
            target: String(capability?.target_room_id || ''),
            name: String(capability?.target_room_name || ''),
            connection: String(capability?.connection_id || ''),
            available: capability?.available !== false,
            blocked: String(capability?.blocked_reason || ''),
            quest: capability?.quest_reference === true,
          })),
        });
      }
      case 'consumables':
        return JSON.stringify({
          ...common,
          inventory: resolvedContext?.state?.inventory || {},
          equipment: resolvedContext?.state?.equipment || {},
        });
      case 'spells':
        return JSON.stringify({
          ...common,
          spells: resolvedContext?.state?.spells || {},
          spellSlots: resolvedContext?.state?.resources?.spellSlots || {},
          focusPoints: resolvedContext?.state?.resources?.focusPoints || {},
        });
      case 'skills':
      case 'feats':
      case 'rest':
        return JSON.stringify({
          ...common,
          state: resolvedContext?.state || {},
        });
      default:
        return JSON.stringify(common);
    }
  }

  refreshActionRail(dirtyDomains = [ACTION_RAIL_DOMAIN_ALL]) {
    const refreshStartedAt = (typeof performance !== 'undefined' && typeof performance.now === 'function')
      ? performance.now()
      : Date.now();
    console.log('[ActionRailPanel] refreshActionRail', { dirtyDomains });
    const categories = this._el.actionRailCategories;
    const panelTitle = this._el.actionRailPanelTitle;
    const panelChip = this._el.actionRailPanelChip;
    const panelBody = this._el.actionRailPanelBody;
    const actorCard = this._el.actionRailActorCard;
    const actorName = this._el.actionRailActorName;
    const actorImage = this._el.actionRailActorImage;
    const actorInitial = this._el.actionRailActorInitial;
    const status = this._el.actionRailStatus;
    const automationToggle = this._el.actionRailAutomationToggle;
    const automationMeta = this._el.actionRailAutomationMeta;
    const hexmap = this.stateManager?.hexmap || null;

    if (!categories || !panelBody || !actorName || !status) {
      console.warn('[ActionRailPanel] refreshActionRail: missing el', {
        categories: !!categories, panelBody: !!panelBody, actorName: !!actorName, status: !!status,
      });
      return;
    }

    const normalizedDomains = Array.isArray(dirtyDomains)
      ? Array.from(new Set(dirtyDomains.map((domain) => String(domain || '').trim().toLowerCase()).filter(Boolean)))
      : [ACTION_RAIL_DOMAIN_ALL];
    const shouldRefreshHeader = this.shouldRefreshActionRailHeader(normalizedDomains);
    const shouldRefreshBody = this.shouldRefreshActionRailBody(normalizedDomains, this.activeActionRailCategory);
    if (!shouldRefreshHeader && !shouldRefreshBody) {
      this._actionRailMetrics.skips += 1;
      console.log('[ActionRailPanel] refreshActionRail:skip', { dirtyDomains: normalizedDomains, activeCategory: this.activeActionRailCategory });
      return;
    }
    const context = this.getActionRailContext();
    const actorCardTarget = this.resolveActionRailActorCardTarget(context);
    const maybeWakeAutomation = () => {
      if (context.automationState?.active) {
        hexmap?.queuePlayerAutomationStep?.('action-rail-refresh');
      }
    };
    const nextHeaderFingerprint = this.buildActionRailHeaderFingerprint(context, actorCardTarget);
    if (shouldRefreshHeader || nextHeaderFingerprint !== this._lastHeaderFingerprint) {
      actorName.textContent = actorCardTarget.actorLabel || context.actorLabel;
      if (actorCard) {
        const actorRef = String(actorCardTarget.actorRef || '').trim();
        const canOpen = Boolean(actorCardTarget.characterId || actorRef);
        actorCard.setAttribute('aria-disabled', canOpen ? 'false' : 'true');
        actorCard.classList.toggle('action-rail__actor-card--disabled', !canOpen);
        actorCard.setAttribute('aria-label', canOpen ? `${actorCardTarget.actorLabel || context.actorLabel}: open character sheet` : (actorCardTarget.actorLabel || context.actorLabel));
        if (actorRef) {
          actorCard.dataset.entityId = actorRef;
        } else {
          delete actorCard.dataset.entityId;
        }
      }
      if (actorImage && actorInitial) {
        const portraitUrl = String(actorCardTarget.actorPortraitUrl || context.actorPortraitUrl || '').trim();
        if (portraitUrl) {
          actorImage.src = portraitUrl;
          actorImage.alt = actorCardTarget.actorLabel || context.actorLabel;
          actorImage.hidden = false;
          actorInitial.hidden = true;
        } else {
          actorImage.hidden = true;
          actorImage.removeAttribute('src');
          actorImage.alt = '';
          actorInitial.hidden = false;
          const actorInitialLabel = actorCardTarget.actorLabel || context.actorLabel;
          actorInitial.textContent = actorInitialLabel.charAt(0).toUpperCase() || '?';
        }
      }
      status.textContent = context.statusLabel;
      if (automationToggle) {
        const automationActive = Boolean(context.automationState?.active);
        const automationBusy = Boolean(context.automationState?.inflight || this.actionRailAutomationTogglePending);
        const canToggle = automationActive || context.canAutomate;
        const toggleDisabled = !canToggle || (!automationActive && automationBusy);
        automationToggle.disabled = toggleDisabled;
        automationToggle.setAttribute('aria-disabled', toggleDisabled ? 'true' : 'false');
        automationToggle.setAttribute('aria-pressed', automationActive ? 'true' : 'false');
        automationToggle.textContent = automationActive ? 'Stop automation' : (automationBusy ? 'Thinking…' : 'Suggest next move');
        automationToggle.classList.toggle('action-rail__automation-toggle--active', automationActive);
      }
      if (automationMeta) {
        automationMeta.textContent = context.automationState?.statusLabel
          || 'Draft and send the next in-character room chat line.';
      }
      this.updateActionRailClocks(context);
      console.log('[ActionRailPanel] refreshActionRail:header', { actor: context.actorLabel, status: context.statusLabel, encounter: context.encounterActive });
      this._lastHeaderFingerprint = nextHeaderFingerprint;
      this._actionRailMetrics.headerRenders += 1;
    }

    categories.querySelectorAll('[data-action-rail-category]').forEach((button) => {
      const nextButton = /** @type {HTMLButtonElement} */ (button);
      const category = nextButton.dataset.actionRailCategory || '';
      const isActive = Boolean(category) && this.activeActionRailCategory === category;
      nextButton.disabled = false;
      nextButton.setAttribute('aria-disabled', 'false');
      nextButton.setAttribute('role', 'tab');
      nextButton.setAttribute('aria-selected', isActive ? 'true' : 'false');
      nextButton.tabIndex = isActive ? 0 : -1;
      nextButton.classList.toggle('action-rail__category--active', isActive);
    });

    this.activeActionRailCategory = this.resolveActionRailCategory(this.activeActionRailCategory);
    const categoryKey = this.activeActionRailCategory;
    if (shouldRefreshBody) {
      const nextCategoryFingerprint = this.buildActionRailCategoryFingerprint(categoryKey, context);
      const previousCategoryFingerprint = String(this._lastCategoryFingerprints[categoryKey] || '');
      const panel = this.buildActionRailPanel(categoryKey, context);
      if (panelTitle) {
        panelTitle.textContent = panel.title;
      }
      if (panelChip) {
        panelChip.textContent = panel.chip;
      }
      panelBody.innerHTML = panel.html;
      if (context.automationState?.active) {
        panelBody.querySelectorAll('[data-action-rail-execute]').forEach((entry) => {
          if (entry instanceof HTMLButtonElement) {
            entry.disabled = true;
            entry.setAttribute('aria-disabled', 'true');
          }
        });
      }
      this.syncActionRailPanelState();
      this._lastCategoryFingerprints[categoryKey] = nextCategoryFingerprint;
      this._actionRailMetrics.bodyRenders += 1;
      console.log('[ActionRailPanel] refreshActionRail:body', {
        category: categoryKey,
        reason: nextCategoryFingerprint !== previousCategoryFingerprint ? 'domain-invalidated+fingerprint-changed' : 'domain-invalidated',
      });
    }
    const refreshElapsedMs = ((typeof performance !== 'undefined' && typeof performance.now === 'function')
      ? performance.now()
      : Date.now()) - refreshStartedAt;
    console.log('[ActionRailPanel] refreshActionRail:complete', {
      dirtyDomains: normalizedDomains,
      activeCategory: categoryKey,
      header: shouldRefreshHeader,
      body: shouldRefreshBody,
      elapsedMs: Number(refreshElapsedMs.toFixed(2)),
      metrics: { ...this._actionRailMetrics },
    });
    maybeWakeAutomation();
  }

  getActionRailContext() {
    return buildActionRailContext(this.stateManager);
  }

  formatRealWorldClock(now = new Date()) {
    const localLabel = new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'medium',
    }).format(now);
    const timezoneLabel = new Intl.DateTimeFormat(undefined, {
      timeZoneName: 'short',
    }).formatToParts(now).find((part) => part.type === 'timeZoneName')?.value || 'Local time';

    return {
      value: localLabel,
      meta: timezoneLabel,
    };
  }

  formatCampaignClock(clock) {
    if (!clock || typeof clock !== 'object') {
      return {
        value: 'Unavailable',
        meta: 'Advances when actions consume time',
      };
    }

    const timezone = typeof clock.timezone === 'string' && clock.timezone.trim() !== ''
      ? clock.timezone.trim()
      : 'UTC';
    const datetime = typeof clock.datetime === 'string' ? clock.datetime : '';
    const parsedDate = datetime ? new Date(datetime) : null;
    const hasValidDate = parsedDate instanceof Date && !Number.isNaN(parsedDate.getTime());
    const fallbackValue = [clock.date, clock.time, timezone].filter(Boolean).join(' ');
    const formattedValue = hasValidDate
      ? new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'medium',
        timeZone: timezone,
      }).format(parsedDate)
      : (fallbackValue || 'Unavailable');
    const metaParts = [clock.weekday, clock.season, timezone].filter(Boolean);

    return {
      value: formattedValue,
      meta: metaParts.join(' • ') || 'Campaign time',
    };
  }

  updateActionRailClocks(context = null) {
    const realClock = this._el.actionRailRealClock;
    const realClockMeta = this._el.actionRailRealClockMeta;
    const campaignClock = this._el.actionRailCampaignClock;
    const campaignClockMeta = this._el.actionRailCampaignClockMeta;

    if (realClock || realClockMeta) {
      const realWorld = this.formatRealWorldClock();
      if (realClock) {
        realClock.textContent = realWorld.value;
      }
      if (realClockMeta) {
        realClockMeta.textContent = realWorld.meta;
      }
    }

    if (campaignClock || campaignClockMeta) {
      const resolvedContext = context || this.getActionRailContext();
      const campaign = this.formatCampaignClock(resolvedContext?.campaignClock || null);
      if (campaignClock) {
        campaignClock.textContent = campaign.value;
      }
      if (campaignClockMeta) {
        const activeCount = Array.isArray(resolvedContext?.timedActivities)
          ? resolvedContext.timedActivities.filter((activity) => activity?.status === 'active').length
          : 0;
        campaignClockMeta.textContent = activeCount > 0
          ? `${campaign.meta} • ${activeCount} active timed activit${activeCount === 1 ? 'y' : 'ies'}`
          : campaign.meta;
      }
    }
  }

  isActionRailExecutionDisabled(actionCost, context, disabled = false) {
    if (disabled) {
      return true;
    }

    if (!context?.encounterActive) {
      return false;
    }

    if (context.hasServerTurn && context.isActorTurn === false) {
      return true;
    }

    const remainingActions = getActionRailRemainingActions(context);
    if (remainingActions === null) {
      return false;
    }

    return getActionRailCost(actionCost, 1) > remainingActions;
  }

  isServerActionAvailable(context, actionId) {
    const id = String(actionId || '').trim();
    if (!id) {
      return false;
    }
    if (Array.isArray(context?.availableActions) && context.availableActions.includes(id)) {
      return true;
    }
    const actions = Array.isArray(context?.actionContract?.actions) ? context.actionContract.actions : [];
    const action = actions.find((entry) => entry?.id === id);
    return action ? action.available !== false : false;
  }

  resolveTurnActionKey(context) {
    if (this.isServerActionAvailable(context, getServerActionIdForExecute('choose_not_to_act'))) {
      return 'choose_not_to_act';
    }
    if (this.isServerActionAvailable(context, getServerActionIdForExecute('end_turn'))) {
      return 'end_turn';
    }
    return '';
  }

  getServerActionDefinition(context, actionId) {
    const actions = Array.isArray(context?.actionContract?.actions) ? context.actionContract.actions : [];
    return actions.find((entry) => entry?.id === actionId) || null;
  }

  async handleActionRailAutomationToggle() {
    if (this.actionRailAutomationTogglePending) {
      return;
    }

    const hexmap = this.stateManager?.hexmap || null;
    if (!hexmap) {
      return;
    }

    const automationState = hexmap.getPlayerAutomationState?.() || {};
    if (automationState.active) {
      hexmap.stopPlayerAutomation?.('manual');
      this.refreshActionRail();
      return;
    }

    this.actionRailAutomationTogglePending = true;
    this.refreshActionRail();
    try {
      await hexmap.startPlayerAutomation?.();
    } finally {
      this.actionRailAutomationTogglePending = false;
      this.refreshActionRail();
    }
  }

  renderActionRailEmptyState(context) {
    if (!context.characterId) {
      return `<div class="action-rail__empty"><p>Select or load a character to enable action tabs.</p></div>`;
    }

    return `<div class="action-rail__empty"><p>Choose a tab to open player actions for ${escapeQuestHtml(context.actorLabel)}.</p></div>`;
  }

  buildActionRailPanel(category, context) {
    const resolvedCategory = this.resolveActionRailCategory(category);
    const builders = {
      turn: () => this.buildTurnActionRailPanel(context),
      navigate: () => buildNavigateActionRailPanel(this, context),
      search: () => this.buildSearchActionRailPanel(context),
      rest: () => this.buildRestActionRailPanel(context),
      spells: () => this.buildSpellActionRailPanel(context),
      consumables: () => this.buildConsumableActionRailPanel(context),
      skills: () => this.buildSkillActionRailPanel(context),
      feats: () => this.buildFeatActionRailPanel(context),
    };
    const builder = builders[resolvedCategory];
    if (!builder) {
      return {
        title: 'Quick actions',
        chip: 'Direct',
        html: this.renderActionRailEmptyState(context),
      };
    }
    return builder();
  }

  buildTurnActionRailPanel(context) {
    const turnActionKey = this.resolveTurnActionKey(context);
    const hasTurnAction = turnActionKey !== '';
    const actionLabel = turnActionKey === 'choose_not_to_act' ? 'Choose not to act' : 'End turn';
    const disabled = this.isActionRailExecutionDisabled(0, context, !context.actorRef || !hasTurnAction);
    const statusSummary = context.encounterActive ? 'Encounter turn control' : 'No active encounter turn';
    const entries = [this.renderActionRailEntry({
      execute: turnActionKey || 'end_turn',
      title: actionLabel,
      summary: buildActionRailEntrySummary([statusSummary]),
      meta: hasTurnAction
        ? 'Advance to the next actor in initiative order.'
        : 'Turn controls unlock when the server marks a turn action as available.',
      disabled,
      dataset: {
        actionType: turnActionKey || 'end_turn',
      },
      actionLabel,
    })];

    return {
      title: 'Turn actions',
      chip: hasTurnAction ? 'Ready' : 'Unavailable',
      html: entries.join(''),
    };
  }

  syncActionRailPanelState() {
    const panelBody = this._el.actionRailPanelBody;
    if (!panelBody || !this.activeActionRailCategory) {
      return;
    }
    const category = this.resolveActionRailCategory(this.activeActionRailCategory);
    const entries = Array.from(panelBody.querySelectorAll('.action-rail__entry'));
    const groups = Array.from(panelBody.querySelectorAll('.action-rail__group'));
    const standaloneEntries = entries.filter((entry) => !entry.closest('.action-rail__group'));
    const activeFilter = this.normalizeActionRailSearchText(this.actionRailFilters[category] || '');
    let toolbar = panelBody.querySelector('[data-action-rail-toolbar]');
    if (!(toolbar instanceof HTMLElement)) {
      toolbar = document.createElement('div');
      toolbar.dataset.actionRailToolbar = 'true';
      toolbar.className = 'action-rail__toolbar';
      toolbar.innerHTML = `
        <label class="action-rail__filter">
          <span class="action-rail__filter-label">Filter options</span>
          <input
            type="search"
            class="action-rail__filter-input"
            data-action-rail-filter="true"
            data-action-rail-filter-category="${escapeTooltipAttr(category)}"
            placeholder="Filter actions, targets, or locations"
            autocomplete="off"
          />
        </label>
        <button
          type="button"
          class="action-rail__toggle-descriptions"
          data-action-rail-toggle-descriptions="true"
          aria-pressed="false"
        >Hide descriptions</button>
      `;
      panelBody.prepend(toolbar);
    }

    const filterInput = toolbar.querySelector('[data-action-rail-filter]');
    if (filterInput instanceof HTMLInputElement && filterInput.value !== (this.actionRailFilters[category] || '')) {
      filterInput.value = this.actionRailFilters[category] || '';
    }

    const toggleButton = toolbar.querySelector('[data-action-rail-toggle-descriptions]');
    if (toggleButton instanceof HTMLButtonElement) {
      toggleButton.setAttribute('aria-pressed', this.actionRailDescriptionsCollapsed ? 'true' : 'false');
      toggleButton.textContent = this.actionRailDescriptionsCollapsed ? 'Show descriptions' : 'Hide descriptions';
    }

    panelBody.classList.toggle('action-rail__panel-body--descriptions-collapsed', this.actionRailDescriptionsCollapsed);

    let visibleEntries = 0;
    groups.forEach((group) => {
      const label = this.normalizeActionRailSearchText(group.querySelector('.action-rail__group-label')?.textContent || '');
      let groupVisibleEntries = 0;
      group.querySelectorAll('.action-rail__entry').forEach((entry) => {
        if (!(entry instanceof HTMLElement)) {
          return;
        }
        const haystack = entry.dataset.actionRailSearch || this.normalizeActionRailSearchText(entry.textContent || '');
        const matches = !activeFilter || haystack.includes(activeFilter) || label.includes(activeFilter);
        entry.hidden = !matches;
        if (matches) {
          groupVisibleEntries += 1;
          visibleEntries += 1;
        }
      });
      group.hidden = groupVisibleEntries === 0;
    });

    standaloneEntries.forEach((entry) => {
      if (!(entry instanceof HTMLElement)) {
        return;
      }
      const haystack = entry.dataset.actionRailSearch || this.normalizeActionRailSearchText(entry.textContent || '');
      const matches = !activeFilter || haystack.includes(activeFilter);
      entry.hidden = !matches;
      if (matches) {
        visibleEntries += 1;
      }
    });

    let emptyState = panelBody.querySelector('[data-action-rail-filter-empty]');
    if (!(emptyState instanceof HTMLElement)) {
      emptyState = document.createElement('div');
      emptyState.dataset.actionRailFilterEmpty = 'true';
      emptyState.className = 'action-rail__empty action-rail__empty--filtered';
      emptyState.innerHTML = '<p>No actions match the current filter.</p>';
      panelBody.append(emptyState);
    }
    emptyState.hidden = !(activeFilter && entries.length > 0 && visibleEntries === 0);
  }

  normalizeActionRailSearchText(value = '') {
    return String(value)
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  buildSearchActionRailPanel(context) {
    const searchAvailable = this.isServerActionAvailable(context, getServerActionIdForExecute('search'));
    const hasActor = Boolean(context.actorRef);
    const disabled = !hasActor || !searchAvailable;
    const entries = [this.renderActionRailEntry({
      execute: 'search',
      title: 'Search the room',
      summary: buildActionRailEntrySummary([
        'Perception',
        context.encounterActive ? formatActionRailCost(1) : '10 minutes',
      ]),
      meta: 'Run a room-level Perception check and ask the narrator to reveal any newly unlocked sensory details, clues, hazards, or hidden objects.',
      disabled,
      actionLabel: 'Search',
    })];
    return {
      title: 'Search',
      chip: 'Perception',
      html: entries.join(''),
    };
  }

  buildSpellActionRailPanel(context) {
    const spells = normalizeSpellcastingData(context.state?.spells || {}, context.state || {});
    const rankGroups = collectSpellRankGroups(spells);
    const runtimeSlots = normalizeDisplayedSpellSlots(context.state?.resources?.spellSlots, spells.slots);
    const entries = [];
    const spellActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('spell'));

    rankGroups.forEach(({ rank, label, spells: rankSpells }) => {
      rankSpells.forEach((spell) => {
        const spellId = typeof spell === 'string' ? spell : (spell.spell_id || spell.id || '');
        const spellName = typeof spell === 'string'
          ? spell.replace(/_/g, ' ')
          : (spell.spell_name || spell.name || spellId || 'Spell');
        const slotState = rank > 0 ? runtimeSlots[String(rank)] || null : null;
        const isFocusSpell = Boolean(spell?.is_focus_spell || spell?.focus || spell?.focus_spell);
        const remaining = isFocusSpell
          ? Number(context.state?.resources?.focusPoints?.current ?? 0)
          : Number(slotState?.current ?? 0);
        const disabled = rank > 0 && !isFocusSpell ? remaining <= 0 : false;
        const actionCost = getActionRailCost(spell?.action_cost ?? spell?.actions ?? spell?.cast_actions, 2);
        entries.push(this.renderActionRailEntry({
          execute: 'spell',
          title: spellName,
          summary: buildActionRailEntrySummary([
            rank === 0 ? 'Cantrip' : label,
            spell?.tradition ? `${String(spell.tradition).replace(/^./, (char) => char.toUpperCase())}` : '',
            isFocusSpell ? `Focus ${remaining}` : (slotState ? `Slots ${slotState.current}/${slotState.max}` : ''),
            formatActionRailCost(actionCost),
          ]),
          meta: typeof spell === 'object' ? (spell.description || spell.desc || '') : '',
          disabled: this.isActionRailExecutionDisabled(actionCost, context, disabled || !spellActionAvailable),
          dataset: {
            spellId,
            spellName,
            spellLevel: String(rank),
            isFocusSpell: isFocusSpell ? '1' : '0',
            actionCost: String(actionCost),
          },
        }));
      });
    });

    return {
      title: 'Spell actions',
      chip: `${entries.length} loaded`,
      html: entries.length
        ? entries.join('')
        : `<div class="action-rail__empty"><p>No spell actions are available for this character.</p></div>`,
    };
  }

  buildConsumableActionRailPanel(context) {
    const items = extractConsumableItems(context.state?.inventory || {}, context.state?.equipment || []);
    const consumeActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('consumable'));
    const entries = items.map((item) => {
      const itemId = item.id || item.item_id || item.name || '';
      const quantity = Number(item.quantity || 1);
      const actionCost = getActionRailCost(item.action_cost ?? item.actions, 1);
      return this.renderActionRailEntry({
        execute: 'consumable',
        title: item.name || itemId || 'Consumable',
        summary: buildActionRailEntrySummary([
          item.type || item.category || 'Consumable',
          quantity > 1 ? `x${quantity}` : '',
          formatActionRailCost(actionCost),
        ]),
        meta: item.consumable_stats?.effect || item.effect || item.description || item.desc || '',
        disabled: this.isActionRailExecutionDisabled(actionCost, context, !consumeActionAvailable),
        dataset: {
          itemId: String(itemId),
          actionCost: String(actionCost),
        },
      });
    });

    return {
      title: 'Consumables',
      chip: `${entries.length} ready`,
      html: entries.length
        ? entries.join('')
        : `<div class="action-rail__empty"><p>No consumables are currently available.</p></div>`,
    };
  }

  buildSkillActionRailPanel(context) {
    const skills = collectCharacterSkillEntries(context.state)
      .sort((a, b) => Number(b.modifier || 0) - Number(a.modifier || 0));
    const skillActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('skill'));
    const entries = skills.map((skill) => {
      const modifier = Number(skill.modifier || 0);
      return this.renderActionRailEntry({
        execute: 'skill',
        title: String(skill.name || 'Skill').replace(/_/g, ' '),
        summary: buildActionRailEntrySummary([
          modifier >= 0 ? `+${modifier}` : `${modifier}`,
          skill.proficiency || 'untrained',
          context.encounterActive ? formatActionRailCost(1) : 'Direct log',
        ]),
        meta: context.encounterActive
          ? 'Resolve this skill directly without using chat.'
          : 'Logs the declared skill action directly in the shell.',
        disabled: this.isActionRailExecutionDisabled(1, context, !skillActionAvailable),
        dataset: {
          skillName: String(skill.name || ''),
          skillModifier: String(modifier),
        },
      });
    });

    return {
      title: 'Skill actions',
      chip: `${entries.length} skills`,
      html: entries.length
        ? entries.join('')
        : `<div class="action-rail__empty"><p>No skill actions are available yet.</p></div>`,
    };
  }

  buildFeatActionRailPanel(context) {
    const features = context.state?.features || {};
    const featActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, getServerActionIdForExecute('feat'));
    const featActions = flattenTooltipBuckets(context.state?.actions?.availableActions?.feat || features?.featEffects?.available_actions || {});
    const fallbackFeats = [
      ...(Array.isArray(features.ancestryFeatures) ? features.ancestryFeatures : []),
      ...(Array.isArray(features.classFeatures) ? features.classFeatures : []),
      ...(Array.isArray(features.feats) ? features.feats : []),
    ];

    const actionEntries = featActions.length > 0
      ? featActions.map((action) => ({
        title: action.name || 'Feat action',
        summary: buildActionRailEntrySummary([
          action.source_feat || '',
          formatActionRailCost(getActionRailCost(action.action_cost, 1)),
          action.uses_remaining != null && action.uses_max != null ? `${action.uses_remaining}/${action.uses_max} uses` : '',
        ]),
        meta: action.description || '',
        dataset: {
          featName: action.name || 'Feat action',
          featId: action.id || action.source_feat || '',
          actionCost: String(getActionRailCost(action.action_cost, 1)),
        },
      }))
      : fallbackFeats.map((feat) => ({
        title: feat.name || String(feat || 'Feat'),
        summary: buildActionRailEntrySummary([
          feat.type || 'feat',
          feat.level ? `Lv ${feat.level}` : '',
          context.encounterActive ? formatActionRailCost(1) : 'Direct log',
        ]),
        meta: feat.description || feat.desc || feat.benefit || '',
        dataset: {
          featName: feat.name || String(feat || 'Feat'),
          featId: feat.id || slugifyTooltipKey(feat.name || String(feat || 'feat')),
          actionCost: '1',
        },
      }));

    const entries = actionEntries.map((entry) => this.renderActionRailEntry({
      execute: 'feat',
      title: entry.title,
      summary: entry.summary,
      meta: entry.meta,
      disabled: this.isActionRailExecutionDisabled(entry.dataset.actionCost, context, !featActionAvailable),
      dataset: entry.dataset,
    }));

    return {
      title: 'Feat actions',
      chip: `${entries.length} available`,
      html: entries.length
        ? entries.join('')
        : `<div class="action-rail__empty"><p>No direct feat actions are currently available.</p></div>`,
    };
  }

  renderActionRailEntry({ execute, title, summary = '', meta = '', disabled = false, dataset = {}, actionLabel = 'Use action' }) {
    const searchText = this.normalizeActionRailSearchText([title, summary, meta, actionLabel].filter(Boolean).join(' '));
    const encodedDataset = Object.entries(dataset)
      .map(([key, value]) => ` data-${key.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`)}="${escapeTooltipAttr(value)}"`)
      .join('');
    return `<article class="action-rail__entry" data-action-rail-search="${escapeTooltipAttr(searchText)}">
      <div class="action-rail__entry-top">
        <div>
          <p class="action-rail__entry-title">${escapeQuestHtml(title)}</p>
          ${summary ? `<p class="action-rail__entry-summary">${escapeQuestHtml(summary)}</p>` : ''}
        </div>
      </div>
      ${meta ? `<p class="action-rail__entry-meta">${escapeQuestHtml(meta)}</p>` : ''}
      <button type="button" class="btn btn-action action-rail__entry-action" data-action-rail-execute="${escapeTooltipAttr(execute)}"${encodedDataset}${disabled ? ' disabled aria-disabled="true"' : ''}>${escapeQuestHtml(actionLabel)}</button>
    </article>`;
  }

  handleActionRailPanelAction(button) {
    const actionType = String(button.dataset.actionRailExecute || '').trim();
    if (!actionType) {
      return;
    }

    console.info('[ActionRailPanel] Clicked action rail button', {
      actionType,
      title: String(button.dataset.actionLabel || button.textContent || '').trim(),
      roomId: String(button.dataset.roomId || '').trim(),
      mapId: String(button.dataset.mapId || '').trim(),
      dungeonLevelId: String(button.dataset.dungeonLevelId || '').trim(),
    });

    const directRoute = getActionRailDirectRoute(actionType, button);
    if (directRoute?.event) {
      console.info('[ActionRailPanel] Dispatching direct route', {
        actionType,
        event: directRoute.event,
      });
      this.bus.emit(directRoute.event, directRoute.payload || {});
      return;
    }

    if (isActionRailSelectableAction(actionType)) {
      this.bus.emit('user:action-selected', { actionKey: actionType, button });
      return;
    }

    console.warn('[ActionRailPanel] Unsupported panel action:', actionType);
  }

  beginActionRailRequest(button) {
    if (!(button instanceof HTMLButtonElement)) {
      console.error('[ActionRailPanel] beginActionRailRequest: button is not HTMLButtonElement', { type: typeof button, tag: button?.tagName });
      return false;
    }
    if (button.dataset.actionRailPending === '1') {
      console.warn('[ActionRailPanel] beginActionRailRequest: request already pending for button', { execute: button.dataset.actionRailExecute });
      return false;
    }
    const requestId = `action-rail-${Date.now()}-${++this._actionRailRequestSequence}`;
    console.log('[ActionRailPanel] beginActionRailRequest: starting', { requestId, execute: button.dataset.actionRailExecute, roomId: button.dataset.roomId });
    button.dataset.actionRailPending = '1';
    button.dataset.backendRequestId = requestId;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    this.bus.emit('game:backend-request-start', {
      requestId,
      label: this.buildActionRailRequestLabel(button),
      source: 'action-rail',
    });
    return true;
  }

  endActionRailRequest(button) {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }
    const requestId = button.dataset.backendRequestId || '';
    delete button.dataset.actionRailPending;
    delete button.dataset.backendRequestId;
    button.disabled = false;
    button.removeAttribute('aria-busy');
    if (requestId) {
      this.bus.emit('game:backend-request-end', { requestId, source: 'action-rail' });
    }
  }

  buildActionRailRequestLabel(button) {
    const label = String(
      button.dataset.actionLabel
      || button.dataset.actionRailExecute
      || button.dataset.actionRailDirect
      || button.textContent
      || 'action'
    ).trim();
    return `Waiting for ${label || 'action'} response...`;
  }

}
