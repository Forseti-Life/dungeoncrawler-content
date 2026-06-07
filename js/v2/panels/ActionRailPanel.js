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
    this.actionRailDescriptionsCollapsed = false;
    this.actionRailFilters = {};
    this.actionRailAutomationTogglePending = false;
    this.navigateLocationGroups = [];
    this.navigateLocationsCampaignId = null;
    this.navigateLocationsInflight = null;
    this._actionRailRequestSequence = 0;
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
      actionRailActorName:        id('action-rail-actor-name'),
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
    if (this._actionRailRealtimeTimer) clearInterval(this._actionRailRealtimeTimer);
    if (this.actionRailRealClockTimer) clearInterval(this.actionRailRealClockTimer);
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('combat:turn-changed', (d) => {
        this.refreshActionRail();
        this.updateActionRailClocks(d);
      }),
      this.bus.on('combat:state-changed', () => this.refreshActionRail()),
      this.bus.on('game:init', () => this.refreshActionRail()),
      this.bus.on('room:changed', () => this.refreshActionRail()),
      this.bus.on('room:occupants-changed', () => this.refreshActionRail()),
      this.bus.on('character:updated', () => this.refreshActionRail()),
      this.bus.on('inventory:changed', () => this.refreshActionRail()),
      this.bus.on('quest:progress-updated', () => this.refreshActionRail()),
    );
  }

  setupActionRail() {
    const categories = this._el.actionRailCategories;
    const panelBody = this._el.actionRailPanelBody;
    const automationToggle = this._el.actionRailAutomationToggle;
    this.updateActionRailClocks();
    if (!this.actionRailRealClockTimer) {
      this.actionRailRealClockTimer = setInterval(() => {
        this.updateActionRailClocks();
      }, 1000);
    }
    if (!categories || categories.dataset.bound === 'true') {
      this.refreshActionRail();
      return;
    }

    if (automationToggle && automationToggle.dataset.bound !== 'true') {
      automationToggle.dataset.bound = 'true';
      automationToggle.addEventListener('click', () => {
        this.handleActionRailAutomationToggle();
      });
    }

    categories.dataset.bound = 'true';
    categories.addEventListener('click', (event) => {
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

      this.activeActionRailCategory = category;
      this.refreshActionRail();
    });
    categories.addEventListener('keydown', (event) => {
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
      nextButton.focus();
      this.activeActionRailCategory = nextCategory;
      this.refreshActionRail();
    });

    if (panelBody) {
      panelBody.addEventListener('click', (event) => {
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
        if (!(button instanceof HTMLButtonElement) || button.disabled) {
          return;
        }
        this.handleActionRailPanelAction(button);
      });
      panelBody.addEventListener('input', (event) => {
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

  refreshActionRail() {
    console.log('[ActionRailPanel] refreshActionRail');
    const categories = this._el.actionRailCategories;
    const panelTitle = this._el.actionRailPanelTitle;
    const panelChip = this._el.actionRailPanelChip;
    const panelBody = this._el.actionRailPanelBody;
    const actorName = this._el.actionRailActorName;
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

    const context = this.getActionRailContext();
    const maybeWakeAutomation = () => {
      if (context.automationState?.active) {
        hexmap?.queuePlayerAutomationStep?.('action-rail-refresh');
      }
    };
    actorName.textContent = context.actorLabel;
    status.textContent = context.statusLabel;
    console.log('[ActionRailPanel] refreshActionRail:render', { actor: context.actorLabel, status: context.statusLabel, encounter: context.encounterActive });
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

    if (!this.activeActionRailCategory) {
      this.activeActionRailCategory = 'navigate';
    }

    const panel = this.buildActionRailPanel(this.activeActionRailCategory, context);
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
    maybeWakeAutomation();
  }

  getActionRailContext() {
    const hexmap = this.stateManager?.hexmap || null;
    const selected = this.stateManager?.get?.('selectedEntity') || null;
    const phaseSnapshot = hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || {};
    const current = hexmap?.turnManagementSystem?.getCurrentTurnEntity?.() || null;
    const encounterActive = phaseSnapshot?.phase === 'encounter';
    const hasServerTurn = Boolean(phaseSnapshot?.turn?.entity);
    const launchPlayer = hexmap?.findLaunchPlayerEntity?.() || null;
    const actor = launchPlayer || (!hasServerTurn ? (selected || current || null) : null);
    const state = hexmap?.launchCharacter || hexmap?.characterData || {};
    const basicInfo = state?.basicInfo || {};
    const actorName = basicInfo.name || state?.name || actor?.getComponent?.('IdentityComponent')?.name || 'No actor selected';
    const runtimeContext = hexmap?.resolveLaunchCharacterRuntimeContext?.() || {};
    const automationProfile = hexmap?.buildPlayerAutomationProfile?.() || {};
    const automationState = hexmap?.getPlayerAutomationState?.() || {};
    const actions = actor?.getComponent?.('ActionsComponent') || null;
    const movement = actor?.getComponent?.('MovementComponent') || null;
    const serverActionsRemaining = Number(phaseSnapshot?.turn?.actions_remaining);
    const actionText = Number.isFinite(serverActionsRemaining)
      ? `${serverActionsRemaining}/3 actions`
      : (actions ? `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions` : null);
    const movementText = movement && Number.isFinite(movement.movementRemaining)
      ? `${movement.movementRemaining} ft move`
      : null;
    const currentTurnLabel = current?.getComponent?.('IdentityComponent')?.name || actorName;
    const actorRef = actor?.dcEntityRef || actor?.dcEntityInstanceId || runtimeContext?.instanceId || null;
    const serverTurnEntity = String(phaseSnapshot?.turn?.entity || '').trim();
    const isActorTurn = !hasServerTurn
      || !serverTurnEntity
      || !actorRef
      || serverTurnEntity === actorRef
      || (!current || !actor || current.id === actor.id);
    const characterId = Number(
      state?.characterId
      || state?.id
      || runtimeContext?.characterId
      || hexmap?.launchContext?.character_id
      || 0
    ) || 0;
    const baseStatus = buildActionRailEntrySummary([
      encounterActive ? 'Encounter active' : 'Encounter unavailable',
      hasServerTurn ? (isActorTurn ? 'Active turn' : `${currentTurnLabel}'s turn`) : '',
      actionText,
      movementText,
    ]) || 'Select your character to unlock direct actions.';

    return {
      hexmap,
      state,
      actor,
      actorRef,
      actorLabel: actorName,
      characterId,
      runtimeContext,
      phaseSnapshot,
      campaignClock: phaseSnapshot?.campaignClock || null,
      timedActivities: Array.isArray(phaseSnapshot?.timedActivities) ? phaseSnapshot.timedActivities : [],
      encounterActive,
      hasServerTurn,
      isActorTurn,
      selectedEntity: selected,
      availableActions: Array.isArray(phaseSnapshot?.availableActions) ? phaseSnapshot.availableActions : [],
      actionContract: phaseSnapshot?.actionContract || null,
      automationState,
      canAutomate: Boolean(
        runtimeContext?.campaignId
        && Number(automationProfile?.character_id || 0) > 0
        && String(runtimeContext?.roomId || hexmap?.resolveActiveRoomId?.() || '').trim() !== ''
      ),
      actions,
      movement,
      statusLabel: buildActionRailEntrySummary([
        baseStatus,
        automationState?.inflight ? 'Running next autonomous step' : '',
        automationState?.lastError ? 'Automation failed' : '',
      ]) || baseStatus,
    };
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
    const builders = {
      turn: () => this.buildTurnActionRailPanel(context),
      navigate: () => this.buildNavigateActionRailPanel(context),
      search: () => this.buildSearchActionRailPanel(context),
      rest: () => this.buildRestActionRailPanel(context),
      spells: () => this.buildSpellActionRailPanel(context),
      consumables: () => this.buildConsumableActionRailPanel(context),
      skills: () => this.buildSkillActionRailPanel(context),
      feats: () => this.buildFeatActionRailPanel(context),
    };
    const builder = builders[category];
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
    const category = this.activeActionRailCategory;
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

  buildNavigateActionRailPanel(context) {
    const campaignId = Number(context.runtimeContext?.campaignId || context.hexmap?.resolveCampaignId?.() || 0);
    this.ensureNavigateLocationGroups(campaignId);

    const exitGroups = this.collectNavigateExitGroups(context);
    const visitedGroups = this.collectVisitedNavigateLocationGroups(context, campaignId, exitGroups);
    const groups = [...exitGroups, ...visitedGroups];

    if (!groups.length) {
      return {
        title: 'Navigate',
        chip: this.navigateLocationsInflight ? 'Loading' : 'No routes',
        html: `<div class="action-rail__empty"><p>${this.navigateLocationsInflight ? 'Loading previously visited dungeons and rooms...' : 'No room exits or accessible known destinations are available from the current campaign state yet.'}</p></div>`,
      };
    }

    const entryCount = groups.reduce((total, group) => total + group.locations.length, 0);
    const html = groups.map((group) => {
      const entries = group.locations.map((location) => this.renderActionRailEntry({
        execute: 'navigate',
        title: location.roomName,
        summary: buildActionRailEntrySummary([
          location.statusLabel || group.dungeonName || group.title,
          location.lastVisitedLabel,
        ]),
        meta: location.meta,
        disabled: location.disabled === true || location.navigable === false,
        dataset: {
          roomId: location.roomId,
          roomName: location.roomName,
          connectionId: location.connectionId,
          originQ: location.originQ,
          originR: location.originR,
          mapId: location.mapId || group.mapId || '',
          dungeonLevelId: location.dungeonLevelId || group.dungeonLevelId || '',
        },
        actionLabel: location.navigable === false ? 'Unavailable' : 'Travel here',
      })).join('');
      return `<section class="action-rail__group"><p class="action-rail__group-label">${escapeQuestHtml(group.title || group.dungeonName || 'Destinations')}</p>${entries}</section>`;
    }).join('');

    return {
      title: 'Navigate',
      chip: `${entryCount} destination${entryCount === 1 ? '' : 's'}`,
      html,
    };
  }

  collectNavigateExitGroups(context) {
    const hexmap = context.hexmap;
    const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
    const rooms = visualRooms && typeof visualRooms === 'object' ? visualRooms : {};
    const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
    const capabilitiesRaw = typeof hexmap?.resolveNavigationCapabilities === 'function'
      ? hexmap.resolveNavigationCapabilities(activeRoomId)
      : [];
    const capabilities = Array.isArray(capabilitiesRaw) ? capabilitiesRaw : [];
    const history = Array.isArray(hexmap?.dungeonData?.location_history) ? hexmap.dungeonData.location_history : [];
    const latestHistoryByRoomId = new Map();
    history.forEach((entry) => {
      const roomId = String(entry?.room_id || '').trim();
      if (roomId) {
        latestHistoryByRoomId.set(roomId, entry);
      }
    });
    const currentMapId = String(hexmap?.dungeonData?.map_id || hexmap?.launchContext?.map_id || this.stateManager?.get?.('mapId') || '').trim();
    const currentDungeonLevelId = String(hexmap?.dungeonData?.level_id || hexmap?.launchContext?.dungeon_level_id || '').trim();

    const exits = capabilities
      .map((capability) => {
        if (!capability?.available) {
          return null;
        }
        const targetRoomId = String(capability?.target_room_id || '').trim();
        if (!targetRoomId || targetRoomId === activeRoomId) {
          return null;
        }
        const room = rooms[targetRoomId] || null;
        const historyEntry = latestHistoryByRoomId.get(targetRoomId) || null;
        return {
          roomId: targetRoomId,
          roomName: String(room?.name || historyEntry?.room_name || targetRoomId),
          statusLabel: 'Exit',
          lastVisitedLabel: historyEntry?.timestamp ? `Seen ${historyEntry.timestamp}` : 'Linked from current room',
          meta: [
            room?.description || room?.short_description || '',
            capability?.type ? `Connection: ${String(capability.type).replace(/_/g, ' ')}` : '',
          ].filter(Boolean).join(' '),
          navigable: true,
          connectionId: String(capability?.connection_id || ''),
          originQ: capability?.origin_hex?.q ?? '',
          originR: capability?.origin_hex?.r ?? '',
          mapId: currentMapId,
          dungeonLevelId: currentDungeonLevelId,
        };
      })
      .filter(Boolean)
      .sort((a, b) => a.roomName.localeCompare(b.roomName));

    if (!exits.length) {
      return [];
    }

    return [{
      key: 'room-exits',
      title: 'Room exits',
      dungeonName: 'Room exits',
      mapId: currentMapId,
      dungeonLevelId: currentDungeonLevelId,
      locations: exits,
    }];
  }

  collectVisitedNavigateLocationGroups(context, campaignId, exitGroups = []) {
    if (!campaignId || this.navigateLocationsCampaignId !== campaignId || !Array.isArray(this.navigateLocationGroups)) {
      return [];
    }

    const hexmap = context.hexmap;
    const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
    const currentMapId = String(hexmap?.dungeonData?.map_id || hexmap?.launchContext?.map_id || this.stateManager?.get?.('mapId') || '').trim();
    const directRouteKeys = new Set();

    exitGroups.forEach((group) => {
      (Array.isArray(group.locations) ? group.locations : []).forEach((location) => {
        const roomId = String(location?.roomId || '').trim();
        if (roomId) {
          const routeMapId = String(location?.mapId || currentMapId || '').trim();
          directRouteKeys.add(`${routeMapId}:${roomId}`);
        }
      });
    });

    return this.navigateLocationGroups
      .map((group) => {
        const mapId = String(group?.mapId || group?.dungeonId || '').trim();
        const dungeonLevelId = String(group?.dungeonLevelId || '').trim();
        const locations = (Array.isArray(group?.locations) ? group.locations : [])
          .map((location) => ({
            ...location,
            roomId: String(location?.roomId || '').trim(),
            roomName: String(location?.roomName || location?.roomId || 'Room'),
            mapId,
            dungeonLevelId,
            statusLabel: 'Visited',
            lastVisitedLabel: String(location?.lastVisitedLabel || 'Visited by party'),
            meta: String(location?.meta || ''),
          }))
          .filter((location) => {
            if (!location.roomId) {
              return false;
            }
            const sameMap = Boolean(mapId && currentMapId && mapId === currentMapId);
            if (sameMap) {
              return false;
            }
            return !directRouteKeys.has(`${mapId || currentMapId}:${location.roomId}`);
          });

        return {
          ...group,
          title: `Known destinations — ${String(group?.dungeonName || group?.title || group?.dungeonId || 'Visited destinations')}`,
          dungeonName: String(group?.dungeonName || group?.title || group?.dungeonId || 'Visited destinations'),
          mapId,
          dungeonLevelId,
          locations,
        };
      })
      .filter((group) => Array.isArray(group.locations) && group.locations.length > 0);
  }

  ensureNavigateLocationGroups(campaignId) {
    if (!campaignId || (this.navigateLocationsCampaignId === campaignId && Array.isArray(this.navigateLocationGroups) && this.navigateLocationGroups.length)) {
      return;
    }
    if (this.navigateLocationsInflight) {
      return;
    }

    this.navigateLocationsInflight = fetch(`/api/campaign/${campaignId}/visited-locations`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.error || 'Unable to load visited locations.');
        }

        this.navigateLocationsCampaignId = campaignId;
        this.navigateLocationGroups = (Array.isArray(data.dungeons) ? data.dungeons : [])
          .map((group) => ({
            dungeonId: String(group?.dungeon_id || ''),
            dungeonName: String(group?.dungeon_name || group?.dungeon_id || 'Dungeon'),
            mapId: String(group?.map_id || group?.dungeon_id || ''),
            dungeonLevelId: String(group?.dungeon_level_id || ''),
            locations: Array.isArray(group?.locations)
              ? group.locations.map((location) => ({
                roomId: String(location?.room_id || ''),
                roomName: String(location?.room_name || location?.room_id || 'Room'),
                meta: String(location?.description || ''),
                lastVisitedLabel: Number(location?.last_visited || 0) > 0
                  ? `Visited ${new Date(Number(location.last_visited) * 1000).toLocaleString()}`
                  : 'Visited by party',
              })).filter((location) => location.roomId)
              : [],
          }))
          .filter((group) => group.locations.length > 0);
      })
      .catch((error) => {
        console.warn('Failed to load campaign visited locations:', error);
      })
      .finally(() => {
        this.navigateLocationsInflight = null;
        if (this.activeActionRailCategory === 'navigate') {
          this.refreshActionRail();
        }
      });
  }

  buildSearchActionRailPanel(context) {
    const searchAvailable = this.isServerActionAvailable(context, 'search');
    const hasActor = Boolean(context.actorRef);
    const disabled = context.encounterActive
      ? this.isActionRailExecutionDisabled(1, context, !searchAvailable)
      : !hasActor;
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
          disabled: this.isActionRailExecutionDisabled(actionCost, context, disabled),
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
        disabled: this.isActionRailExecutionDisabled(actionCost, context),
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
        disabled: this.isActionRailExecutionDisabled(1, context),
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
      disabled: this.isActionRailExecutionDisabled(entry.dataset.actionCost, context),
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
    const actionType = button.dataset.actionRailExecute || '';
    if (!actionType) {
      return;
    }

    if (actionType === 'navigate') {
      this.bus.emit('user:navigate', { button });
      return;
    }

    if (actionType === 'end_turn' || actionType === 'choose_not_to_act') {
      this.bus.emit('user:end-turn', { button, actionType });
      return;
    }

    this.bus.emit('user:action-selected', { actionKey: actionType, button });
  }

  beginActionRailRequest(button) {
    if (!(button instanceof HTMLButtonElement)) {
      return false;
    }
    if (button.dataset.actionRailPending === '1') {
      return false;
    }
    const requestId = `action-rail-${Date.now()}-${++this._actionRailRequestSequence}`;
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
