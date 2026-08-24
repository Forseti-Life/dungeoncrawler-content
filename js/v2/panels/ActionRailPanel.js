/**
 * @file panels/ActionRailPanel.js
 *
 * 3-action economy UI — tab-driven action rail categories.
 * Methods ported verbatim from hexmap.js UIManager.
 */

import { getActionRailCost, formatActionRailCost, getActionRailRemainingActions } from '../utils/action-utils.js?v=20260811-v2-turn-sync-ui-1';
import { collectCharacterSkillEntries, buildActionRailEntrySummary } from '../utils/inventory-utils.js';
import { escapeQuestHtml } from '../utils/quest-utils.js';
import { escapeTooltipAttr } from '../utils/dom-utils.js';
import { buildActionRailContext } from '../services/action-rail-context-service.js?v=20260819-v2-action-rail-api-authority-1';
import { buildNavigateActionRailPanel } from '../services/action-rail-navigate-panel-service.js?v=20260723-v2-nav-exit-numbering-4';
import {
  getActionRailDirectRoute,
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
    this.actionRailCategoryPinnedByUser = false;
    this.actionRailDescriptionsCollapsed = true;
    this.actionRailFilters = {};
    this.actionRailAutomationTogglePending = false;
    this.navigateLocationGroups = [];
    this.navigateLocationsCampaignId = null;
    this.navigateLocationsInflight = null;
    this.navigateActiveRoom = null;
    this._lastRoomTransitionId = '';
    this._actionRailRequestSequence = 0;
    this._activeEncounterActionLock = null;
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
      bodySkips: 0,
    };
    this._lastActionRailBodyCategory = '';
    this._lastActionRailTelemetry = null;
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

  isActionRailDebugEnabled() {
    try {
      if (this.stateManager?.get?.('debugActionRail') === true) {
        return true;
      }
    } catch (_) {
      // State manager debug key is optional.
    }
    const hexmapDebugFlag = this.stateManager?.hexmap?.debugFlags?.actionRail;
    if (hexmapDebugFlag === true) {
      return true;
    }
    if (typeof window !== 'undefined' && window?.DC_DEBUG_ACTION_RAIL === true) {
      return true;
    }
    if (typeof window !== 'undefined' && window?.localStorage) {
      return window.localStorage.getItem('dc:debug:action-rail') === '1';
    }
    return false;
  }

  logActionRailDebug(stage, details = {}) {
    if (!this.isActionRailDebugEnabled()) {
      return;
    }
    console.log('[ActionRailPanel]', { stage, ...details });
  }

  logActionRailStateSnapshot(stage, context) {
    const actionContractActions = Array.isArray(context?.actionContract?.actions) ? context.actionContract.actions : [];
    const families = context?.actionContract?.action_option_families && typeof context.actionContract.action_option_families === 'object'
      ? context.actionContract.action_option_families
      : {};
    const familySummary = Object.entries(families).map(([key, family]) => {
      const optionCount = Number(family?.option_count ?? (Array.isArray(family?.options) ? family.options.length : 0));
      return `${key}:${Number.isFinite(optionCount) ? optionCount : 0}`;
    });
    console.info('[ActionRailPanel] state snapshot', {
      stage,
      category: this.activeActionRailCategory,
      actorRef: context?.actorRef || null,
      actorLabel: context?.actorLabel || null,
      encounterActive: Boolean(context?.encounterActive),
      isActorTurn: context?.isActorTurn,
      availableActionCount: Array.isArray(context?.availableActions) ? context.availableActions.length : 0,
      availableActions: Array.isArray(context?.availableActions) ? context.availableActions : [],
      actionContractCount: actionContractActions.length,
      familySummary,
    });
  }

  emitActionRailTelemetry(payload = {}) {
    this._lastActionRailTelemetry = payload;
    try {
      this.bus?.emit?.('action-rail:telemetry', payload);
    } catch (_) {
      // Telemetry bus emission is best-effort.
    }
    this.logActionRailDebug('telemetry', payload);
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('combat:turn-changed', (d) => {
        this.invalidateActionRail(['turn', 'combat', 'header']);
        this.updateActionRailClocks(d);
      }),
      this.bus.on('combat:state-changed', () => this.invalidateActionRail(['combat', 'header'])),
      this.bus.on('game:init', () => this.invalidateActionRail([ACTION_RAIL_DOMAIN_ALL])),
      this.bus.on('game:state-refreshed', () => this.invalidateActionRail([ACTION_RAIL_DOMAIN_ALL])),
      this.bus.on('room:changed', (payload) => this.handleRoomContextChanged(payload, 'room:changed')),
      this.bus.on('room:transitioned', (payload) => this.handleRoomContextChanged(payload, 'room:transitioned')),
      this.bus.on('room:occupants-membership-changed', (payload) => this.handleRoomContextChanged(payload, 'room:occupants-membership-changed')),
      this.bus.on('room:occupants-changed', (payload) => this.handleRoomContextChanged(payload, 'room:occupants-changed')),
      this.bus.on('room:occupants-decoration-changed', (payload) => this.handleRoomContextChanged(payload, 'room:occupants-decoration-changed')),
      this.bus.on('character:updated', () => this.invalidateActionRail(['character', 'header'])),
      this.bus.on('inventory:changed', () => this.invalidateActionRail(['inventory'])),
      this.bus.on('quest:progress-updated', () => this.invalidateActionRail(['quest'])),
      this.bus.on('navigation:capabilities-updated', () => this.invalidateActionRail(['navigation'])),
      this.bus.on('merchant:stock-loaded', () => this.invalidateActionRail(['merchant'])),
      this.bus.on('encounter:action-lock-changed', (payload) => this.handleEncounterActionLockChanged(payload)),
    );
  }

  handleEncounterActionLockChanged(payload = {}) {
    if (payload?.locked) {
      this._activeEncounterActionLock = {
        key: String(payload.key || '').trim(),
        actorRef: String(payload.actorRef || '').trim(),
        type: String(payload.type || '').trim(),
      };
      this.invalidateActionRail(['combat', 'turn']);
      return;
    }

    const unlockKey = String(payload?.key || '').trim();
    if (unlockKey && this._activeEncounterActionLock?.key && unlockKey !== this._activeEncounterActionLock.key) {
      return;
    }
    this._activeEncounterActionLock = null;
    this.invalidateActionRail(['combat', 'turn']);
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

    // Occupant membership changes can alter local actor affordances without
    // requiring navigation capability recalculation.
    if (normalizedSource === 'room:occupants-membership-changed') {
      this.invalidateActionRail(['room', 'header']);
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
    this.logActionRailDebug('setupActionRail', {
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

      this.setActiveActionRailCategory(category, { refresh: true, userInitiated: true });
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
      this.setActiveActionRailCategory(nextCategory, { refresh: true, focus: true, userInitiated: true });
    });

    if (panelBody) {
      this.logActionRailDebug('setupActionRail:bindPanelBody');
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
    const encounterScopedActorRef = String(
      (resolvedContext?.encounterActive && resolvedContext?.hasServerTurn
        ? (resolvedContext?.actionContract?.actor_id || resolvedContext?.phaseSnapshot?.turn?.entity || resolvedContext?.actorRef || '')
        : '')
    ).trim();
    if (encounterScopedActorRef) {
      const actorEntity = this.stateManager?.hexmap?.entityManager?.getEntity?.(encounterScopedActorRef) || null;
      const actorMetadata = actorEntity?.dcStatePayload?.metadata || actorEntity?.dcStatePayload?.state?.metadata || {};
      const actorCharacterId = Number(
        actorEntity?.dcCharacterId
        || actorMetadata?.character_id
        || actorEntity?.dcStatePayload?.character_id
        || actorEntity?.dcStatePayload?.state?.character_id
        || 0
      ) || 0;
      const actorLabel = String(
        actorEntity?.getComponent?.('IdentityComponent')?.name
        || resolvedContext?.actorLabel
        || encounterScopedActorRef
      ).trim();
      return {
        actorRef: encounterScopedActorRef,
        characterId: actorCharacterId || null,
        actorLabel: actorLabel || 'No actor selected',
        actorPortraitUrl: String(
          actorMetadata?.portrait_url
          || actorMetadata?.portrait
          || resolvedContext?.actorPortraitUrl
          || ''
        ).trim(),
        entity: actorEntity,
      };
    }

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

  setActiveActionRailCategory(category, { refresh = true, focus = false, userInitiated = false } = {}) {
    const resolvedCategory = this.resolveActionRailCategory(category);
    this.activeActionRailCategory = resolvedCategory;
    if (userInitiated) {
      this.actionRailCategoryPinnedByUser = true;
    }

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

  resolveDefaultActionRailCategory(context) {
    return 'navigate';
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
    this.logActionRailDebug('refreshActionRail:start', { dirtyDomains });
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
    let shouldRefreshBody = this.shouldRefreshActionRailBody(normalizedDomains, this.activeActionRailCategory);
    if (!shouldRefreshHeader && !shouldRefreshBody) {
      this._actionRailMetrics.skips += 1;
      this.logActionRailDebug('refreshActionRail:skip', { dirtyDomains: normalizedDomains, activeCategory: this.activeActionRailCategory });
      this.emitActionRailTelemetry({
        type: 'refresh',
        dirtyDomains: normalizedDomains,
        activeCategory: this.activeActionRailCategory,
        headerDecision: 'skip',
        bodyDecision: 'skip',
        reason: 'no-dependent-domains',
        elapsedMs: 0,
        metrics: { ...this._actionRailMetrics },
      });
      return;
    }
    const context = this.getActionRailContext();
    this.logActionRailStateSnapshot('refresh-start', context);
    if (!this.actionRailCategoryPinnedByUser) {
      const defaultCategory = this.resolveActionRailCategory(this.resolveDefaultActionRailCategory(context));
      if (defaultCategory !== this.activeActionRailCategory) {
        this.activeActionRailCategory = defaultCategory;
        shouldRefreshBody = true;
      }
    }
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
      this.logActionRailDebug('refreshActionRail:header', { actor: context.actorLabel, status: context.statusLabel, encounter: context.encounterActive });
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
    let bodyDecision = shouldRefreshBody ? 'render' : 'skip';
    let bodyReason = shouldRefreshBody ? 'domain-invalidated' : 'not-dirty';
    if (shouldRefreshBody) {
      const nextCategoryFingerprint = this.buildActionRailCategoryFingerprint(categoryKey, context);
      const previousCategoryFingerprint = String(this._lastCategoryFingerprints[categoryKey] || '');
      const didSwitchCategory = this._lastActionRailBodyCategory !== categoryKey;
      const fingerprintChanged = nextCategoryFingerprint !== previousCategoryFingerprint;
      if (!didSwitchCategory && !fingerprintChanged) {
        bodyDecision = 'skip';
        bodyReason = 'fingerprint-unchanged-skip';
        this._actionRailMetrics.bodySkips += 1;
      } else {
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
        console.info('[ActionRailPanel] rendered panel', {
          category: categoryKey,
          title: panel.title,
          chip: panel.chip,
          htmlLength: String(panel.html || '').length,
          entryCount: (String(panel.html || '').match(/data-action-rail-execute=/g) || []).length,
        });
        this._lastActionRailBodyCategory = categoryKey;
        this._lastCategoryFingerprints[categoryKey] = nextCategoryFingerprint;
        this._actionRailMetrics.bodyRenders += 1;
        bodyDecision = 'render';
        bodyReason = didSwitchCategory ? 'category-switch' : 'fingerprint-changed';
      }
      this.logActionRailDebug('refreshActionRail:body', {
        category: categoryKey,
        decision: bodyDecision,
        reason: bodyReason,
      });
    }
    const refreshElapsedMs = ((typeof performance !== 'undefined' && typeof performance.now === 'function')
      ? performance.now()
      : Date.now()) - refreshStartedAt;
    const telemetryPayload = {
      type: 'refresh',
      dirtyDomains: normalizedDomains,
      activeCategory: categoryKey,
      headerDecision: shouldRefreshHeader ? 'render-or-check' : 'skip',
      bodyDecision,
      reason: bodyReason,
      elapsedMs: Number(refreshElapsedMs.toFixed(2)),
      metrics: { ...this._actionRailMetrics },
    };
    this.logActionRailDebug('refreshActionRail:complete', telemetryPayload);
    this.emitActionRailTelemetry(telemetryPayload);
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
        realClock.textContent = `Realworld Time: ${realWorld.value}`;
      }
      if (realClockMeta) {
        realClockMeta.textContent = realWorld.meta;
      }
    }

    if (campaignClock || campaignClockMeta) {
      const resolvedContext = context || this.getActionRailContext();
      const campaign = this.formatCampaignClock(resolvedContext?.campaignClock || null);
      if (campaignClock) {
        campaignClock.textContent = `Campaign Time: ${campaign.value}`;
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
    if (this.isEncounterActionLockedForContext(context)) {
      return true;
    }
    if (context?.runtimeSync?.readOnlyDesynced) {
      return true;
    }
    if (context?.runtimeSync?.degraded && context?.encounterActive && context?.awaitingHydration) {
      return true;
    }

    if (!context?.encounterActive || !this.shouldEnforceEncounterBudgets(context)) {
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

  isEncounterActionLockedForContext(context) {
    if (!this._activeEncounterActionLock || !context?.encounterActive) {
      return false;
    }
    const lockActorRef = String(this._activeEncounterActionLock.actorRef || '').trim();
    const contextActorRef = String(context?.actorRef || '').trim();
    if (!lockActorRef || !contextActorRef) {
      return true;
    }
    return lockActorRef === contextActorRef;
  }

  shouldEnforceEncounterBudgets(context) {
    return Number(context?.encounterId || 0) > 0;
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
    if (this.isServerActionAvailable(context, 'choose_not_to_act')) {
      return 'choose_not_to_act';
    }
    if (this.isServerActionAvailable(context, 'end_turn')) {
      return 'end_turn';
    }
    return '';
  }

  getServerActionDefinition(context, actionId) {
    const actions = Array.isArray(context?.actionContract?.actions) ? context.actionContract.actions : [];
    return actions.find((entry) => entry?.id === actionId) || null;
  }

  getServerActionOptions(context, actionId) {
    if (this.isEncounterActionContractPending(context)) {
      return null;
    }

    const families = context?.actionContract?.action_option_families;
    if (families && typeof families === 'object') {
      const family = families[actionId];
      if (family && typeof family === 'object' && Array.isArray(family.options)) {
        console.info('[ActionRailPanel] options from family', {
          actionId,
          optionCount: family.options.length,
        });
        return family.options;
      }
    }

    const definition = this.getServerActionDefinition(context, actionId);
    if (definition && Array.isArray(definition.resolved_options)) {
      console.info('[ActionRailPanel] options from resolved_options', {
        actionId,
        optionCount: definition.resolved_options.length,
      });
      return definition.resolved_options;
    }

    console.warn('[ActionRailPanel] no options found for action', {
      actionId,
      availableActions: Array.isArray(context?.availableActions) ? context.availableActions : [],
      hasActionContract: Boolean(context?.actionContract),
    });

    return [];
  }

  isEncounterActionContractPending(context) {
    return Boolean(context?.encounterActive && context?.awaitingHydration);
  }

  buildPendingActionOptionsPanel(title = 'Actions') {
    return {
      title,
      chip: 'Syncing…',
      html: '<div class="action-rail__empty"><p>Waiting for encounter action hydration…</p></div>',
    };
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
    if (context?.runtimeSync?.readOnlyDesynced) {
      return `<div class="action-rail__empty"><p>Runtime sync is desynced. Actions are blocked until authoritative state recovers.</p></div>`;
    }
    if (context?.runtimeSync?.degraded) {
      return `<div class="action-rail__empty"><p>Runtime sync is degraded. Some actions may wait for hydration, but direct actions remain available when your turn data is loaded.</p></div>`;
    }
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
    const controlEntries = [this.renderActionRailEntry({
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
    if (this.isServerActionAvailable(context, 'delay')) {
      controlEntries.push(this.renderActionRailEntry({
        execute: 'delay',
        title: 'Delay',
        summary: buildActionRailEntrySummary(['Turn control']),
        meta: 'Hold your place and re-enter later in the initiative order.',
        disabled: this.isActionRailExecutionDisabled(0, context, !context.actorRef),
        actionLabel: 'Delay',
      }));
    }

    const combatEntries = this.buildContractAtomicActionEntries(context);

    return {
      title: 'Turn controls',
      chip: context.encounterActive ? 'Turn' : 'Room mode',
      html: [
        this.renderActionRailGroup('Core controls', controlEntries.join('')),
        combatEntries.length > 0
          ? this.renderActionRailGroup('Combat actions', combatEntries.join(''))
          : '',
      ].join(''),
    };
  }

  buildContractAtomicActionEntries(context) {
    const allowed = new Set([
      'strike',
      'raise_shield',
      'interact',
      'talk',
      'demoralize',
      'feint',
      'point_out',
      'command_animal',
      'aid_setup',
      'administer_first_aid',
      'treat_poison',
      'battle_medicine',
      'treat_wounds',
    ]);
    const actions = Array.isArray(context?.actionContract?.actions) ? context.actionContract.actions : [];
    const selectedTarget = this.resolveSelectedEntityTargetDataset(context);
    return actions
      .filter((action) => action && action.available !== false)
      .filter((action) => allowed.has(String(action.id || '').trim()))
      .map((action) => {
        const actionId = String(action.id || '').trim();
        const executeKey = this.resolveExecuteKeyForContractAction(actionId);
        const actionCost = getActionRailCost(action.cost, 1);
        const rawTargeting = String(action.targeting || 'contextual').trim().toLowerCase();
        const targeting = rawTargeting === 'contextual'
          ? this.resolveContextualTargetingMode({
            targeting_text: action?.targeting_text,
            description: action?.description,
            desc: action?.summary,
            target: action?.target,
            targets: action?.targets,
            range_text: action?.range_text,
          }, action, rawTargeting)
          : rawTargeting;
        const label = String(action.label || actionId || 'Action').trim();
        const guidance = this.describeTargetingGuidance(targeting);
        const targetRequired = this.isTargetingModeMapPickRequired(targeting);

        let disabled = false;
        const dataset = {
          actionType: actionId,
          actionCost: String(actionCost),
          targeting: String(targeting || 'contextual').trim().toLowerCase(),
          targetRequired: targetRequired ? '1' : '0',
        };
        if (executeKey === 'interact' && selectedTarget?.hasHex) {
          dataset.targetQ = String(selectedTarget.q);
          dataset.targetR = String(selectedTarget.r);
          dataset.targetEntityId = selectedTarget.entityId;
          dataset.targetName = selectedTarget.name;
          if (selectedTarget?.hasTargetRef) {
            dataset.targetRef = selectedTarget.targetRef;
          }
          disabled = this.isActionRailExecutionDisabled(actionCost, context, false);
        } else if (executeKey === 'raise_shield') {
          disabled = this.isActionRailExecutionDisabled(actionCost, context, false);
        } else if (targetRequired) {
          if (selectedTarget?.hasNumericEntityId) {
            dataset.targetId = String(selectedTarget.numericEntityId);
          }
          if (selectedTarget?.hasEntityId) {
            dataset.targetEntityId = selectedTarget.entityId;
          }
          if (selectedTarget?.hasTargetRef) {
            dataset.targetRef = selectedTarget.targetRef;
          }
          if (selectedTarget?.name) {
            dataset.targetName = selectedTarget.name;
          }
          if (executeKey === 'attack') {
            dataset.weaponName = 'weapon';
          }
          disabled = this.isActionRailExecutionDisabled(actionCost, context, false);
        } else {
          disabled = this.isActionRailExecutionDisabled(actionCost, context, false);
        }

        return this.renderActionRailEntry({
          execute: executeKey || actionId || 'noop',
          title: label,
          summary: buildActionRailEntrySummary([
            formatActionRailCost(actionCost),
            targeting ? `Targeting: ${targeting}` : '',
          ]),
          meta: disabled
            ? `${guidance} Select a target on the map to unlock direct execution when supported.`
            : guidance,
          disabled,
          dataset,
          actionLabel: disabled ? 'Use map targeting' : label,
        });
      });
  }

  resolveSelectedEntityTargetDataset(context) {
    const selected = context?.selectedEntity || null;
    if (!selected) {
      return null;
    }
    const actorRef = String(context?.actorRef || '').trim();
    const selectedRef = String(selected?.dcEntityRef || selected?.dcEntityInstanceId || selected?.instanceId || '').trim();
    const selectedId = String(selected?.id || selectedRef || '').trim();
    const targetRef = String(selected?.dcEntityRef || selected?.dcEntityInstanceId || selected?.instanceId || '').trim();
    const numericEntityId = Number(selected?.id);
    if (!selectedId || (selectedRef && actorRef && selectedRef === actorRef)) {
      return null;
    }
    const name = String(selected?.getComponent?.('IdentityComponent')?.name || selected?.name || 'target').trim();
    const pos = selected?.getComponent?.('PositionComponent') || null;
    const q = Number(pos?.q);
    const r = Number(pos?.r);
    return {
      entityId: selectedId,
      numericEntityId: Number.isFinite(numericEntityId) && numericEntityId > 0 ? numericEntityId : null,
      name,
      hasEntityId: selectedId !== '',
      hasNumericEntityId: Number.isFinite(numericEntityId) && numericEntityId > 0,
      targetRef,
      hasTargetRef: targetRef !== '',
      hasHex: Number.isFinite(q) && Number.isFinite(r),
      q: Number.isFinite(q) ? q : null,
      r: Number.isFinite(r) ? r : null,
    };
  }

  resolveSelectedHexTargetDataset(context) {
    const selectedHex = this.stateManager?.get?.('selectedHex')
      || context?.hexmap?.selectedHex
      || null;
    const q = Number(selectedHex?.q);
    const r = Number(selectedHex?.r);
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      return null;
    }
    return {
      hasHex: true,
      q,
      r,
    };
  }

  resolveExecuteKeyForContractAction(actionId) {
    const normalized = String(actionId || '').trim().toLowerCase();
    if (normalized === 'strike') {
      return 'attack';
    }
    if (normalized === 'interact') {
      return 'interact';
    }
    if (normalized === 'talk') {
      return 'talk';
    }
    if (normalized === 'stride') {
      return 'stride';
    }
    if (normalized === 'step') {
      return 'step';
    }
    if (normalized === 'demoralize') {
      return 'demoralize';
    }
    if (normalized === 'raise_shield') {
      return 'raise_shield';
    }
    if (normalized === 'delay') {
      return 'delay';
    }
    if ([
      'feint',
      'point_out',
      'command_animal',
      'aid_setup',
      'administer_first_aid',
      'treat_poison',
      'battle_medicine',
    ].includes(normalized)) {
      return normalized;
    }
    return '';
  }

  describeTargetingGuidance(targeting = '') {
    switch (String(targeting || '').trim().toLowerCase()) {
      case 'hostile_entity':
        return 'Pick a hostile entity on the map first.';
      case 'entity_or_object':
        return 'Pick an adjacent entity or object on the map first.';
      case 'hex':
        return 'Select a destination hex on the map to resolve this action.';
      case 'self':
        return 'This action targets your active actor.';
      case 'none':
        return 'This action does not require a target.';
      default:
        return 'Resolve targeting through the map encounter view.';
    }
  }

  resolveSpellTargetingMode(option = {}, metadata = {}) {
    const rawTargeting = String(option?.targeting || metadata?.targeting || 'contextual').trim().toLowerCase();
    if (rawTargeting === 'contextual') {
      return this.resolveContextualTargetingMode(metadata, option, rawTargeting);
    }
    return rawTargeting;
  }

  isSpellTargetRequired(targeting = '') {
    return this.isTargetingModeMapPickRequired(targeting);
  }

  isTargetingModeMapPickRequired(targeting = '') {
    return [
      'hostile_entity',
      'ally',
      'ally_or_self',
      'self_or_target',
      'entity_or_object',
      'entity_or_room',
      'hex',
      'area_origin',
      'connected_room',
      'room_hazard',
      'room',
    ].includes(String(targeting || '').trim().toLowerCase());
  }

  shouldTreatContextualActionAsTargetable(metadata = {}, option = {}) {
    const targetHints = [
      metadata?.targeting_text,
      metadata?.target,
      metadata?.targets,
      metadata?.range_text,
      metadata?.description,
      metadata?.desc,
      metadata?.benefit,
      metadata?.effect,
      option?.label,
      option?.id,
    ]
      .map((value) => String(value || '').trim().toLowerCase())
      .filter(Boolean)
      .join(' ');

    if (!targetHints) {
      return false;
    }
    if (/\b(no target|no-target|self only|self-only)\b/.test(targetHints)) {
      return false;
    }
    return /\b(target|creature|enemy|foe|ally|object|barrier|door|room|hazard|hex|adjacent|within|range|touch|willing)\b/.test(targetHints);
  }

  resolveContextualTargetingMode(metadata = {}, option = {}, fallback = 'contextual') {
    const targetHints = [
      metadata?.targeting_text,
      metadata?.target,
      metadata?.targets,
      metadata?.range_text,
      metadata?.description,
      metadata?.desc,
      metadata?.benefit,
      metadata?.effect,
      option?.label,
      option?.id,
    ]
      .map((value) => String(value || '').trim().toLowerCase())
      .filter(Boolean)
      .join(' ');

    if (!targetHints) {
      return fallback;
    }
    if (/\b(self only|self-only|targets? you|yourself)\b/.test(targetHints) && !/\bally\b/.test(targetHints)) {
      return 'self';
    }
    if (/\b(ally or self|self or ally|ally\/self)\b/.test(targetHints)) {
      return 'ally_or_self';
    }
    if (/\b(ally|willing creature)\b/.test(targetHints)) {
      return 'ally';
    }
    if (/\b(enemy|foe|hostile|opponent)\b/.test(targetHints)) {
      return 'hostile_entity';
    }
    if (/\b(connected room|next room|adjacent room|adjacent rooms)\b/.test(targetHints)) {
      return 'connected_room';
    }
    if (/\b(room hazard|hazard)\b/.test(targetHints)) {
      return 'room_hazard';
    }
    if (/\b(room)\b/.test(targetHints)) {
      return 'room';
    }
    if (/\b(area origin|burst origin|cone origin|line origin|origin hex)\b/.test(targetHints)) {
      return 'area_origin';
    }
    if (/\b(hex|tile|grid|destination)\b/.test(targetHints)) {
      return 'hex';
    }
    if (/\b(object|barrier|door|lever|switch|device|container)\b/.test(targetHints)) {
      return 'entity_or_object';
    }
    if (/\b(npc|creature|target|entity)\b/.test(targetHints)) {
      return 'entity_or_room';
    }
    return fallback;
  }

  isTargetingModeEntityRequired(targeting = '') {
    return ['hostile_entity', 'ally', 'entity_or_object', 'entity_or_room', 'ally_or_self'].includes(String(targeting || '').trim().toLowerCase());
  }

  syncActionRailPanelState() {
    const panelBody = this._el.actionRailPanelBody;
    if (!panelBody || !this.activeActionRailCategory) {
      return;
    }
    const context = this.getActionRailContext();
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

    this.syncActionRailDisabledWaitOverlays(context, panelBody);
  }

  syncActionRailDisabledWaitOverlays(context, panelBody) {
    panelBody.querySelectorAll('.action-rail__entry').forEach((entry) => {
      if (!(entry instanceof HTMLElement)) {
        return;
      }
      const button = entry.querySelector('.action-rail__entry-action');
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }
      const disabled = button.disabled || button.getAttribute('aria-disabled') === 'true';
      let note = entry.querySelector('.action-rail__entry-disabled-note');
      if (!disabled) {
        button.removeAttribute('title');
        if (note instanceof HTMLElement) {
          note.remove();
        }
        return;
      }
      const waitMessage = this.resolveActionRailDisabledWaitMessage(context, button);
      button.setAttribute('title', waitMessage);
      if (!(note instanceof HTMLElement)) {
        note = document.createElement('p');
        note.className = 'action-rail__entry-disabled-note';
        entry.append(note);
      }
      note.textContent = waitMessage;
    });
  }

  resolveActionRailDisabledWaitMessage(context, button) {
    if (!context?.actorRef) {
      return 'Waiting on actor for character selection.';
    }
    if (context?.runtimeSync?.readOnlyDesynced) {
      return 'Waiting on server for authoritative state resync.';
    }
    if (context?.runtimeSync?.degraded && context?.encounterActive && context?.awaitingHydration) {
      return 'Waiting on server for encounter hydration sync.';
    }
    if (context?.automationState?.active) {
      return 'Waiting on narrator for automation step completion.';
    }
    if (this.isEncounterActionLockedForContext(context)) {
      return 'Waiting on narrator for previous action resolution.';
    }
    const actionType = String(button?.dataset?.actionType || button?.dataset?.actionRailExecute || '').trim();
    if (actionType && !this.isServerActionAvailable(context, actionType)) {
      return `Waiting on server for ${actionType.replace(/_/g, ' ')} availability.`;
    }
    const targetRequired = button?.dataset?.targetRequired === '1';
    const hasTarget = Boolean(String(button?.dataset?.targetId || '').trim())
      || Boolean(String(button?.dataset?.targetEntityId || '').trim())
      || Boolean(String(button?.dataset?.targetRef || '').trim())
      || (String(button?.dataset?.targeting || '').trim().toLowerCase() === 'hex'
        && Number.isFinite(Number(button?.dataset?.targetQ))
        && Number.isFinite(Number(button?.dataset?.targetR)));
    if (targetRequired && !hasTarget) {
      return 'Waiting on actor for map target selection.';
    }
    if (context?.encounterActive && context?.hasServerTurn && context?.isActorTurn === false) {
      return 'Waiting on narrator for your initiative turn.';
    }
    const actionCost = getActionRailCost(button?.dataset?.actionCost, 1);
    const remainingActions = getActionRailRemainingActions(context);
    if (remainingActions !== null && actionCost > remainingActions) {
      return `Waiting on actor for ${actionCost} available action${actionCost === 1 ? '' : 's'}.`;
    }
    return 'Waiting on server for action availability.';
  }

  normalizeActionRailSearchText(value = '') {
    return String(value)
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  buildSearchActionRailPanel(context) {
    const searchAvailable = this.isServerActionAvailable(context, 'search');
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
    const options = this.getServerActionOptions(context, 'cast_spell');
    if (options === null) {
      return this.buildPendingActionOptionsPanel('Spell actions');
    }
    const spellActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'cast_spell');
    const entries = options.map((option) => {
      const metadata = option?.metadata && typeof option.metadata === 'object' ? option.metadata : {};
      const spellId = String(metadata?.spell_id || option?.id || '').trim();
      const spellName = String(metadata?.spell_name || option?.label || spellId || 'Spell').trim();
      const spellLevel = Number(metadata?.spell_level ?? metadata?.level ?? 0);
      const isFocusSpell = Boolean(metadata?.is_focus_spell || metadata?.focus || metadata?.focus_spell);
      const actionCost = getActionRailCost(option?.action_cost ?? metadata?.action_cost ?? metadata?.actions, 2);
      const targeting = this.resolveSpellTargetingMode(option, metadata);
      const targetRequired = this.isSpellTargetRequired(targeting);
      const targetingGuidance = this.describeTargetingGuidance(targeting);
      const normalizedSpellId = String(spellId || '').trim().toLowerCase();
      const normalizedSpellSlug = normalizedSpellId.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
      const explicitRangeFt = Number(
        metadata?.range_ft
        ?? metadata?.rangeFeet
        ?? metadata?.max_distance_ft
        ?? 0
      );
      const rangeText = String(metadata?.range_text || metadata?.range || metadata?.targeting_text || '').trim();
      const inferredRangeFtMatch = rangeText.match(/(\d+)\s*(?:ft|feet|foot)\b/i);
      const inferredRangeFt = inferredRangeFtMatch ? Number(inferredRangeFtMatch[1]) : 0;
      const rangeFt = Number.isFinite(explicitRangeFt) && explicitRangeFt > 0
        ? Math.max(5, Math.trunc(explicitRangeFt))
        : (Number.isFinite(inferredRangeFt) && inferredRangeFt > 0 ? Math.max(5, Math.trunc(inferredRangeFt)) : 0);
      const explicitMinTargets = Number(
        metadata?.min_targets
        ?? metadata?.target_min
        ?? metadata?.minimum_targets
        ?? 0
      );
      const explicitMaxTargets = Number(
        metadata?.max_targets
        ?? metadata?.target_max
        ?? metadata?.maximum_targets
        ?? metadata?.target_count
        ?? 0
      );
      const inferredMagicMissileTargets = normalizedSpellSlug === 'magic_missile'
        ? Math.max(1, Math.min(3, actionCost))
        : 0;
      const maxTargets = Number.isFinite(explicitMaxTargets) && explicitMaxTargets > 0
        ? Math.max(1, Math.trunc(explicitMaxTargets))
        : inferredMagicMissileTargets;
      const minTargets = Number.isFinite(explicitMinTargets) && explicitMinTargets > 0
        ? Math.max(1, Math.trunc(explicitMinTargets))
        : (maxTargets > 1 ? maxTargets : 1);
      const selectionMode = maxTargets > 1 ? 'multi' : 'single';
      const completionPolicy = maxTargets > 1 ? 'max_targets' : 'auto';
      return this.renderActionRailEntry({
        execute: 'cast_spell',
        title: spellName || 'Spell',
        summary: buildActionRailEntrySummary([
          Number.isFinite(spellLevel) && spellLevel === 0 ? 'Cantrip' : (Number.isFinite(spellLevel) ? `Rank ${spellLevel}` : ''),
          isFocusSpell ? 'Focus spell' : '',
          targeting ? `Targeting: ${targeting}` : '',
          formatActionRailCost(actionCost),
        ]),
        meta: buildActionRailEntrySummary([
          String(metadata?.description || metadata?.desc || '').trim(),
          targetRequired ? `${targetingGuidance} Select on map after Use action.` : '',
        ]),
        disabled: this.isActionRailExecutionDisabled(actionCost, context, !spellActionAvailable),
        dataset: {
          spellId,
          spellName,
          spellLevel: String(Number.isFinite(spellLevel) ? spellLevel : 0),
          isFocusSpell: isFocusSpell ? '1' : '0',
          actionCost: String(actionCost),
          targeting,
          targetRequired: targetRequired ? '1' : '0',
          minTargets: String(minTargets),
          maxTargets: String(Math.max(minTargets, maxTargets || 1)),
          selectionMode,
          completionPolicy,
          allowDuplicateTargets: normalizedSpellSlug === 'magic_missile' ? '1' : '0',
          rangeFt: rangeFt > 0 ? String(rangeFt) : '',
        },
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
    const options = this.getServerActionOptions(context, 'consume_item');
    if (options === null) {
      return this.buildPendingActionOptionsPanel('Consumables');
    }
    const consumeActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'consume_item');
    const entries = options.map((option) => {
      const metadata = option?.metadata && typeof option.metadata === 'object' ? option.metadata : {};
      const itemId = String(metadata?.item_id || option?.id || '').trim();
      const itemName = String(metadata?.name || option?.label || itemId || 'Consumable').trim();
      const quantity = Number(metadata?.quantity || 1);
      const actionCost = getActionRailCost(option?.action_cost ?? metadata?.action_cost ?? metadata?.actions, 1);
      const rawTargeting = String(option?.targeting || metadata?.targeting || 'self_or_target').trim().toLowerCase();
      const targeting = rawTargeting === 'contextual'
        ? this.resolveContextualTargetingMode(metadata, option, rawTargeting)
        : rawTargeting;
      const targetRequired = this.isTargetingModeMapPickRequired(targeting);
      const contextualTargetRequired = rawTargeting === 'contextual'
        && this.shouldTreatContextualActionAsTargetable(metadata, option);
      return this.renderActionRailEntry({
        execute: 'consume_item',
        title: itemName || 'Consumable',
        summary: buildActionRailEntrySummary([
          String(metadata?.type || metadata?.category || 'Consumable'),
          quantity > 1 ? `x${quantity}` : '',
          targeting ? `Targeting: ${targeting}` : '',
          formatActionRailCost(actionCost),
        ]),
        meta: String(metadata?.consumable_stats?.effect || metadata?.effect || metadata?.description || metadata?.desc || '').trim(),
        disabled: this.isActionRailExecutionDisabled(actionCost, context, !consumeActionAvailable),
        dataset: {
          itemId: itemId || String(option?.id || ''),
          itemName,
          actionCost: String(actionCost),
          itemPayload: JSON.stringify(metadata || {}),
          targeting,
          targetRequired: (targetRequired || contextualTargetRequired) ? '1' : '0',
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
    const skillOptions = this.getServerActionOptions(context, 'skill');
    if (skillOptions === null) {
      return this.buildPendingActionOptionsPanel('Skill actions');
    }
    const skills = skillOptions
      .map((option) => {
        const metadata = option?.metadata && typeof option.metadata === 'object' ? option.metadata : {};
        const modifier = Number(metadata?.modifier ?? metadata?.bonus ?? 0);
        const rawTargeting = String(option?.targeting || metadata?.targeting || 'contextual').trim().toLowerCase();
        const targeting = rawTargeting === 'contextual'
          ? this.resolveContextualTargetingMode(metadata, option, rawTargeting)
          : rawTargeting;
        const contextualTargetRequired = rawTargeting === 'contextual'
          && this.shouldTreatContextualActionAsTargetable(metadata, option);
        return {
          name: String(metadata?.skill_name || metadata?.name || option?.label || option?.id || 'Skill').trim(),
          modifier: Number.isFinite(modifier) ? modifier : 0,
          proficiency: String(metadata?.proficiency || 'untrained').trim(),
          actionCost: getActionRailCost(option?.action_cost ?? metadata?.action_cost ?? metadata?.actions, 1),
          targeting,
          targetRequired: this.isTargetingModeMapPickRequired(targeting) || contextualTargetRequired,
        };
      })
      .sort((a, b) => Number(b.modifier || 0) - Number(a.modifier || 0));
    const skillActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'skill');
    const entries = skills.map((skill) => {
      const modifier = Number(skill.modifier || 0);
      return this.renderActionRailEntry({
        execute: 'skill',
        title: String(skill.name || 'Skill').replace(/_/g, ' '),
        summary: buildActionRailEntrySummary([
          modifier >= 0 ? `+${modifier}` : `${modifier}`,
          skill.proficiency || 'untrained',
          skill.targeting ? `Targeting: ${skill.targeting}` : '',
          context.encounterActive ? formatActionRailCost(skill.actionCost) : 'Direct log',
        ]),
        meta: context.encounterActive
          ? 'Resolve this skill directly without using chat.'
          : 'Logs the declared skill action directly in the shell.',
        disabled: this.isActionRailExecutionDisabled(skill.actionCost, context, !skillActionAvailable),
        dataset: {
          skillName: String(skill.name || ''),
          skillModifier: String(modifier),
          actionCost: String(skill.actionCost),
          targeting: String(skill.targeting || 'contextual'),
          targetRequired: skill.targetRequired ? '1' : '0',
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
    const options = this.getServerActionOptions(context, 'feat');
    if (options === null) {
      return this.buildPendingActionOptionsPanel('Feat actions');
    }
    const featActionAvailable = !context.encounterActive || this.isServerActionAvailable(context, 'feat');
    const entries = options.map((option) => {
      const metadata = option?.metadata && typeof option.metadata === 'object' ? option.metadata : {};
      const actionCost = getActionRailCost(option?.action_cost ?? metadata?.action_cost ?? metadata?.actions, 1);
      const rawTargeting = String(option?.targeting || metadata?.targeting || 'contextual').trim().toLowerCase();
      const targeting = rawTargeting === 'contextual'
        ? this.resolveContextualTargetingMode(metadata, option, rawTargeting)
        : rawTargeting;
      const contextualTargetRequired = rawTargeting === 'contextual'
        && this.shouldTreatContextualActionAsTargetable(metadata, option);
      const targetRequired = this.isTargetingModeMapPickRequired(targeting) || contextualTargetRequired;
      const dataset = {
        featName: String(metadata?.name || option?.label || 'Feat action'),
        featId: String(metadata?.id || option?.id || ''),
        actionCost: String(actionCost),
        targeting,
        targetRequired: targetRequired ? '1' : '0',
      };
      return this.renderActionRailEntry({
        execute: 'feat',
        title: dataset.featName,
        summary: buildActionRailEntrySummary([
          String(metadata?.type || metadata?.source_feat || 'feat'),
          metadata?.level ? `Lv ${metadata.level}` : '',
          targeting ? `Targeting: ${targeting}` : '',
          formatActionRailCost(actionCost),
        ]),
        meta: String(metadata?.description || metadata?.desc || metadata?.benefit || '').trim(),
        disabled: this.isActionRailExecutionDisabled(dataset.actionCost, context, !featActionAvailable),
        dataset,
      });
    });

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

  renderActionRailGroup(label, entriesHtml = '') {
    return `<section class="action-rail__group"><p class="action-rail__group-label">${escapeQuestHtml(label)}</p>${entriesHtml}</section>`;
  }

  isActionRailTargetPickRequired(actionType, button) {
    const key = String(actionType || '').trim().toLowerCase();
    const targeting = String(button?.dataset?.targeting || '').trim().toLowerCase();
    const targetRequired = button?.dataset?.targetRequired === '1';
    if (targetRequired) {
      return true;
    }
    if (['attack', 'interact', 'talk', 'demoralize', 'stride', 'step'].includes(key)) {
      return true;
    }
    if (key === 'cast_spell' || key === 'spell') {
      if (targeting === '' || targeting === 'contextual') {
        return true;
      }
      return !['self', 'none'].includes(targeting);
    }
    if (['skill', 'feat', 'consume_item', 'consumable'].includes(key)) {
      return targeting !== '' && !['none', 'self'].includes(targeting);
    }
    return false;
  }

  resolveActionRailTargetPrompt(actionType, button) {
    const key = String(actionType || '').trim().toLowerCase();
    const targeting = String(button?.dataset?.targeting || '').trim().toLowerCase();
    if (targeting === 'area_origin') {
      return 'Pick area origin';
    }
    if (targeting === 'connected_room') {
      return 'Pick destination connection';
    }
    if (targeting === 'room_hazard') {
      return 'Pick hazard target';
    }
    if (targeting === 'room') {
      return 'Pick room target';
    }
    if (targeting === 'hex') {
      return 'Pick destination hex';
    }
    if (targeting === 'ally') {
      return 'Pick ally target';
    }
    if (targeting === 'ally_or_self') {
      return 'Pick ally or self target';
    }
    if (targeting === 'self_or_target') {
      return 'Pick self or target';
    }
    if (targeting === 'hostile_entity') {
      return 'Pick hostile target';
    }
    if (key === 'attack' || key === 'demoralize') {
      return 'Pick hostile target';
    }
    if (key === 'interact') {
      return 'Pick object, barrier, or entity target';
    }
    if (key === 'talk') {
      return 'Pick conversation target';
    }
    if (key === 'stride' || key === 'step') {
      return 'Pick destination hex';
    }
    if (key === 'cast_spell' || key === 'spell') {
      if (targeting === 'ally' || targeting === 'ally_or_self') {
        return 'Pick ally target';
      }
      if (targeting === 'entity_or_object') {
        return 'Pick entity or object target';
      }
      if (targeting === 'hex') {
        return 'Pick spell hex target';
      }
      if (targeting === 'self') {
        return 'Pick self target';
      }
      return 'Pick spell target';
    }
    if (key === 'consume_item' || key === 'consumable') {
      if (targeting === 'self_or_target') {
        return 'Pick self or target';
      }
      if (targeting === 'ally' || targeting === 'ally_or_self') {
        return 'Pick consumable target';
      }
      if (targeting === 'entity_or_object') {
        return 'Pick consumable target';
      }
      return 'Pick consumable target';
    }
    if (key === 'skill') {
      if (targeting === 'ally' || targeting === 'ally_or_self') {
        return 'Pick skill target';
      }
      if (targeting === 'entity_or_object' || targeting === 'entity_or_room') {
        return 'Pick skill target';
      }
      if (targeting === 'hex') {
        return 'Pick skill target hex';
      }
      if (targeting === 'room' || targeting === 'room_hazard' || targeting === 'connected_room') {
        return 'Pick skill target';
      }
      return 'Pick skill target';
    }
    if (key === 'feat') {
      if (targeting === 'ally' || targeting === 'ally_or_self') {
        return 'Pick feat target';
      }
      if (targeting === 'entity_or_object' || targeting === 'entity_or_room') {
        return 'Pick feat target';
      }
      if (targeting === 'hex' || targeting === 'area_origin') {
        return 'Pick feat target';
      }
      return 'Pick feat target';
    }
    if (key === 'feint') {
      return 'Pick feint target';
    }
    if (key === 'point_out') {
      return 'Pick target to point out';
    }
    if (key === 'command_animal') {
      return 'Pick companion target';
    }
    if (key === 'aid_setup' || key === 'administer_first_aid' || key === 'battle_medicine' || key === 'treat_poison' || key === 'treat_wounds') {
      return 'Pick ally target';
    }
    return 'Pick target';
  }

  handleActionRailPanelAction(button) {
    const actionType = String(button.dataset.actionRailExecute || '').trim();
    if (!actionType) {
      return;
    }
    const context = this.getActionRailContext();
    if (context?.runtimeSync?.readOnlyDesynced) {
      this.bus.emit('chat:system-message', {
        text: 'Action blocked: runtime sync is desynced and currently read-only.',
        speaker: 'System',
        kind: 'error',
        view: 'room',
        channel: 'room',
        source: 'action-rail-sync-guard',
        authority: 'authoritative',
      });
      return;
    }
    if (context?.runtimeSync?.degraded && context?.encounterActive && context?.awaitingHydration) {
      this.bus.emit('chat:system-message', {
        text: 'Action temporarily blocked: encounter action hydration is still syncing.',
        speaker: 'System',
        kind: 'warning',
        view: 'room',
        channel: 'room',
        source: 'action-rail-sync-guard',
        authority: 'authoritative',
      });
      return;
    }

    this.logActionRailDebug('action-clicked', {
      actionType,
      title: String(button.dataset.actionLabel || button.textContent || '').trim(),
      roomId: String(button.dataset.roomId || '').trim(),
      mapId: String(button.dataset.mapId || '').trim(),
      dungeonLevelId: String(button.dataset.dungeonLevelId || '').trim(),
    });

    const directRoute = getActionRailDirectRoute(actionType, button);
    if (directRoute?.event) {
      this.logActionRailDebug('action-direct-route', {
        actionType,
        event: directRoute.event,
      });
      this.bus.emit(directRoute.event, directRoute.payload || {});
      return;
    }

    if (isActionRailSelectableAction(actionType)) {
      if (this.isActionRailTargetPickRequired(actionType, button)) {
        const actorRef = String(
          context?.actionContract?.actor_id
          || context?.phaseSnapshot?.turn?.entity
          || context?.actorRef
          || ''
        ).trim();
        if (actorRef) {
          button.dataset.actorRef = actorRef;
        }
        this.bus.emit('user:target-pick-requested', {
          actionKey: actionType,
          button,
          promptLabel: this.resolveActionRailTargetPrompt(actionType, button),
        });
        return;
      }
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
    this.logActionRailDebug('beginActionRailRequest:start', { requestId, execute: button.dataset.actionRailExecute, roomId: button.dataset.roomId });
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
