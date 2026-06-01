/**
 * @file panels/ActionRailPanel.js
 *
 * 3-action economy UI — attack, spell, skill, search, navigate, consumable sub-panels.
 * Methods ported verbatim from hexmap.js UIManager.
 */

import { getActionRailCost, formatActionRailCost, getActionRailRemainingActions } from '../utils/action-utils.js';
import { normalizeSpellcastingData, collectSpellRankGroups, normalizeDisplayedSpellSlots } from '../utils/spell-utils.js';
import { extractReadyWeapons, extractConsumableItems, collectCharacterSkillEntries, buildActionRailEntrySummary } from '../utils/inventory-utils.js';
import { escapeQuestHtml } from '../utils/quest-utils.js';
import { escapeTooltipAttr, flattenTooltipBuckets, slugifyTooltipKey } from '../utils/dom-utils.js';

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
    this.activeActionRailCategory = null;
    this.actionRailDescriptionsCollapsed = false;
    this.actionRailFilters = {};
    this.actionRailAutomationTogglePending = false;
    this.navigateLocationGroups = [];
    this.navigateLocationsCampaignId = null;
    this.navigateLocationsInflight = null;
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => document.getElementById(k);
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
      endTurnBtn:                 id('end-turn'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[ActionRailPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
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
      this.actionRailRealClockTimer = window.setInterval(() => {
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
        ? event.target.closest('[data-action-rail-category], [data-action-rail-direct]')
        : null;
      if (!(button instanceof HTMLButtonElement) || button.disabled) {
        return;
      }

      const directAction = button.dataset.actionRailDirect || '';
      const category = button.dataset.actionRailCategory || '';
      if (directAction) {
        this.activeActionRailCategory = null;
        this.handleActionRailDirectAction(directAction, button);
        this.refreshActionRail();
        return;
      }

      if (category) {
        this.activeActionRailCategory = this.activeActionRailCategory === category ? null : category;
        this.refreshActionRail();
      }
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

    categories.querySelectorAll('[data-action-rail-category], [data-action-rail-direct]').forEach((button) => {
      const nextButton = /** @type {HTMLButtonElement} */ (button);
      const category = nextButton.dataset.actionRailCategory || '';
      const directAction = nextButton.dataset.actionRailDirect || '';
      const disabled = this.isActionRailButtonDisabled(category || directAction, context);
      nextButton.disabled = disabled;
      nextButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      nextButton.classList.toggle('action-rail__category--active', Boolean(category) && this.activeActionRailCategory === category);
    });

    if (!this.activeActionRailCategory) {
      if (panelTitle) {
        panelTitle.textContent = 'Quick actions';
      }
      if (panelChip) {
        panelChip.textContent = context.encounterActive ? 'Encounter' : 'Direct';
      }
      panelBody.innerHTML = this.renderActionRailEmptyState(context);
      maybeWakeAutomation();
      return;
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
    const current = hexmap?.turnManagementSystem?.getCurrentTurnEntity?.() || null;
    const encounterActive = Boolean(hexmap?.stateManager?.get?.('encounterId'));
    const launchPlayer = hexmap?.findLaunchPlayerEntity?.() || null;
    const actor = encounterActive
      ? (selected || current || launchPlayer || null)
      : (launchPlayer || selected || current || null);
    const state = hexmap?.launchCharacter || hexmap?.characterData || {};
    const basicInfo = state?.basicInfo || {};
    const actorName = basicInfo.name || state?.name || actor?.getComponent?.('IdentityComponent')?.name || 'No actor selected';
    const runtimeContext = hexmap?.resolveLaunchCharacterRuntimeContext?.() || {};
    const automationProfile = hexmap?.buildPlayerAutomationProfile?.() || {};
    const phaseSnapshot = hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || {};
    const automationState = hexmap?.getPlayerAutomationState?.() || {};
    const actions = actor?.getComponent?.('ActionsComponent') || null;
    const movement = actor?.getComponent?.('MovementComponent') || null;
    const actionText = actions ? `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions` : null;
    const movementText = movement && Number.isFinite(movement.movementRemaining)
      ? `${movement.movementRemaining} ft move`
      : null;
    const currentTurnLabel = current?.getComponent?.('IdentityComponent')?.name || actorName;
    const isActorTurn = !encounterActive || !current || !actor || current.id === actor.id;
    const actorRef = actor?.dcEntityRef || actor?.dcEntityInstanceId || runtimeContext?.instanceId || null;
    const characterId = Number(
      state?.characterId
      || state?.id
      || runtimeContext?.characterId
      || hexmap?.launchContext?.character_id
      || 0
    ) || 0;
    const baseStatus = buildActionRailEntrySummary([
      encounterActive ? 'Encounter active' : 'Exploration ready',
      encounterActive ? (isActorTurn ? 'Active turn' : `${currentTurnLabel}'s turn`) : '',
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
      isActorTurn,
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
        timeStyle: 'short',
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

  isActionRailButtonDisabled(actionKey, context) {
    if (context.automationState?.active) {
      return true;
    }

    if (!context.characterId) {
      return true;
    }

    if (actionKey === 'end-turn') {
      return !context.encounterActive;
    }

    if (actionKey === 'search') {
      if (!context.actorRef || context.isActorTurn === false) {
        return true;
      }
      if (context.encounterActive) {
        const remainingActions = getActionRailRemainingActions(context);
        return remainingActions !== null && remainingActions < 1;
      }
      return false;
    }

    if (actionKey === 'attack') {
      return !context.actor;
    }

    return false;
  }

  isActionRailExecutionDisabled(actionCost, context, disabled = false) {
    if (disabled) {
      return true;
    }

    if (!context?.encounterActive) {
      return false;
    }

    if (context.isActorTurn === false) {
      return true;
    }

    const remainingActions = getActionRailRemainingActions(context);
    if (remainingActions === null) {
      return false;
    }

    return getActionRailCost(actionCost, 1) > remainingActions;
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
      return `<div class="action-rail__empty"><p>Select or load a character to enable direct action buttons.</p></div>`;
    }

    return `<div class="action-rail__empty"><p>Choose Attack, Navigate, Search, Spells, Consumables, Skills, or Feats to open direct action buttons for ${escapeQuestHtml(context.actorLabel)}.</p></div>`;
  }

  buildActionRailPanel(category, context) {
    const builders = {
      attack: () => this.buildAttackActionRailPanel(context),
      navigate: () => this.buildNavigateActionRailPanel(context),
      search: () => this.buildSearchActionRailPanel(context),
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

  buildAttackActionRailPanel(context) {
    const weapons = extractReadyWeapons(context.state?.inventory || {}, context.state?.equipment || []);
    const availableTargets = this.collectAttackTargets(context);

    if (!weapons.length) {
      return {
        title: 'Attack options',
        chip: 'No weapons',
        html: `<div class="action-rail__empty"><p>No ready weapons are available for ${escapeQuestHtml(context.actorLabel)}.</p></div>`,
      };
    }

    if (!context.actor) {
      return {
        title: 'Attack options',
        chip: 'No attacker',
        html: `<div class="action-rail__empty"><p>Select or load a character to enable attacks.</p></div>`,
      };
    }

    if (!availableTargets.length) {
      return {
        title: 'Attack options',
        chip: 'No targets',
        html: `<div class="action-rail__empty"><p>${context.encounterActive ? 'No hostile targets are currently available for attack.' : 'No other room occupants are currently available to target.'}</p></div>`,
      };
    }

    const sections = weapons.map((weapon) => {
      const entries = availableTargets.map(({ target, distance, teamLabel = '' }) => {
        const targetName = target?.getComponent?.('IdentityComponent')?.name || 'Target';
        const summaryParts = [
          weapon.sourceLabel,
          weapon.damage || '',
          teamLabel,
          Number.isFinite(distance) ? `${distance} hex` : '',
          context.encounterActive ? formatActionRailCost(1) : 'Starts combat',
        ];
        return this.renderActionRailEntry({
          execute: 'attack',
          title: targetName,
          summary: buildActionRailEntrySummary(summaryParts),
          meta: buildActionRailEntrySummary([
            weapon.traits || '',
            weapon.description || '',
          ]),
          disabled: this.isActionRailExecutionDisabled(1, context),
          dataset: {
            targetId: String(target?.id || ''),
            targetName,
            weaponId: String(weapon.id || ''),
            weaponName: weapon.name,
          },
          actionLabel: context.encounterActive ? 'Strike target' : 'Start combat',
        });
      }).join('');

      return `<section class="action-rail__group"><p class="action-rail__group-label">${escapeQuestHtml(weapon.name || 'Weapon')}</p>${entries}</section>`;
    }).join('');

    return {
      title: 'Attack options',
      chip: `${weapons.length} weapon${weapons.length === 1 ? '' : 's'} / ${availableTargets.length} target${availableTargets.length === 1 ? '' : 's'}`,
      html: sections,
    };
  }

  collectAttackTargets(context) {
    const actor = context.actor;
    const hexmap = context.hexmap;
    if (!actor || !hexmap?.entityManager) {
      return [];
    }

    if (context.encounterActive) {
      const hostileTargets = Array.isArray(hexmap.getHostileTargets?.(actor))
        ? hexmap.getHostileTargets(actor)
        : [];
      return hostileTargets.filter(({ target }) => {
        const check = hexmap?.combatSystem?.canAttack?.(actor, target);
        return check ? check.canAttack !== false : true;
      }).map(({ target, distance }) => ({
        target,
        distance,
        teamLabel: this.describeCombatantTeam(target),
      }));
    }

    const actorPos = actor.getComponent?.('PositionComponent');
    const candidates = hexmap.entityManager.getEntitiesWith('CombatComponent', 'StatsComponent', 'PositionComponent', 'IdentityComponent') || [];
    return candidates
      .filter((candidate) => {
        if (!candidate || candidate.id === actor.id) {
          return false;
        }
        const identity = candidate.getComponent?.('IdentityComponent');
        const stats = candidate.getComponent?.('StatsComponent');
        const pos = candidate.getComponent?.('PositionComponent');
        if (!identity?.isCreature?.() || !stats?.isAlive?.() || !pos || !actorPos) {
          return false;
        }
        return hexmap.hasLineOfSight(actorPos.q, actorPos.r, pos.q, pos.r);
      })
      .map((target) => {
        const pos = target.getComponent?.('PositionComponent');
        const distance = actorPos && pos ? hexmap.movementSystem?.hexDistance?.(actorPos.q, actorPos.r, pos.q, pos.r) : null;
        return {
          target,
          distance,
          teamLabel: this.describeCombatantTeam(target),
        };
      })
      .sort((left, right) => {
        const leftName = left.target?.getComponent?.('IdentityComponent')?.name || '';
        const rightName = right.target?.getComponent?.('IdentityComponent')?.name || '';
        return leftName.localeCompare(rightName);
      });
  }

  buildNavigateActionRailPanel(context) {
    const campaignId = Number(context.runtimeContext?.campaignId || context.hexmap?.resolveCampaignId?.() || 0);
    this.ensureNavigateLocationGroups(campaignId);

    const routeGroups = this.collectNavigateLocationGroups(context);
    const visitedGroups = this.collectVisitedNavigateLocationGroups(context, campaignId);
    const groups = [...routeGroups, ...visitedGroups];

    if (!groups.length) {
      return {
        title: 'Navigate',
        chip: this.navigateLocationsInflight ? 'Loading' : 'No routes',
        html: `<div class="action-rail__empty"><p>${this.navigateLocationsInflight ? 'Loading previously visited dungeons and rooms...' : 'No known routes or visited destinations are available from the current campaign state yet.'}</p></div>`,
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

  collectNavigateLocationGroups(context) {
    const hexmap = context.hexmap;
    const dungeonData = hexmap?.dungeonData || {};
    const visualRooms = typeof hexmap?.getVisualRooms === 'function' ? hexmap.getVisualRooms() : {};
    const rooms = visualRooms && typeof visualRooms === 'object' ? visualRooms : {};
    const activeRoomId = hexmap?.resolveActiveRoomId?.() || null;
    const visitOrder = new Map();
    const history = Array.isArray(dungeonData?.location_history) ? dungeonData.location_history : [];
    const capabilitiesRaw = typeof hexmap?.resolveNavigationCapabilities === 'function'
      ? hexmap.resolveNavigationCapabilities(activeRoomId)
      : [];
    const capabilities = Array.isArray(capabilitiesRaw) ? capabilitiesRaw : [];
    const capabilityByRoomId = new Map();

    capabilities.forEach((capability) => {
      const targetRoomId = String(capability?.target_room_id || '').trim();
      if (!targetRoomId) {
        return;
      }

      const existing = capabilityByRoomId.get(targetRoomId);
      if (!existing || (!existing.available && capability?.available)) {
        capabilityByRoomId.set(targetRoomId, capability);
      }
    });

    history.forEach((entry, index) => {
      const roomId = String(entry?.room_id || '').trim();
      if (roomId) {
        visitOrder.set(roomId, index);
      }
    });

    Object.entries(rooms).forEach(([roomId, room]) => {
      if (room?.state?.explored) {
        visitOrder.set(String(roomId), Math.max(visitOrder.get(String(roomId)) ?? -1, history.length));
      }
    });

    const sections = [
      { key: 'explored-navigable', title: 'Explored navigable', locations: [] },
      { key: 'explored-blocked', title: 'Explored not navigable', locations: [] },
      { key: 'unexplored-navigable', title: 'Unexplored navigable', locations: [] },
      { key: 'unexplored-blocked', title: 'Unexplored not navigable', locations: [] },
    ];

    const destinations = Object.entries(rooms)
      .map(([roomId, room]) => {
        const normalizedRoomId = String(roomId || '').trim();
        if (!normalizedRoomId || normalizedRoomId === activeRoomId) {
          return null;
        }

        const capability = capabilityByRoomId.get(normalizedRoomId) || null;
        const lastHistoryEntry = [...history].reverse().find((entry) => String(entry?.room_id || '') === normalizedRoomId) || null;
        const explored = visitOrder.has(normalizedRoomId);
        const navigable = Boolean(capability?.available);
        const blockedReason = String(capability?.blocked_reason || '').trim();
        const roomName = String(room?.name || lastHistoryEntry?.room_name || normalizedRoomId);
        const statusLabel = [
          explored ? 'Explored' : 'Unexplored',
          navigable ? 'Navigable' : 'Not navigable',
        ].join(' · ');
        const lastVisitedLabel = explored
          ? (lastHistoryEntry?.timestamp ? `Seen ${lastHistoryEntry.timestamp}` : 'Visited by party')
          : 'Not yet explored';
        const metaParts = [
          room?.description || room?.short_description || '',
          !navigable && blockedReason === 'blocked' ? 'Route is currently blocked.' : '',
          !navigable && blockedReason === 'undiscovered' ? 'Route has not been discovered yet.' : '',
          !navigable && !blockedReason && !capability ? 'No direct route from the current room.' : '',
        ].filter(Boolean);

        return {
          roomId: normalizedRoomId,
          roomName,
          statusLabel,
          lastVisitedLabel,
          meta: metaParts.join(' '),
          order: Number(visitOrder.get(normalizedRoomId) ?? -1),
          explored,
          navigable,
          connectionId: String(capability?.connection_id || ''),
          originQ: capability?.origin_hex?.q ?? '',
          originR: capability?.origin_hex?.r ?? '',
        };
      })
      .filter(Boolean)
      .sort((a, b) => {
        if (b.order !== a.order) {
          return b.order - a.order;
        }
        return a.roomName.localeCompare(b.roomName);
      });

    destinations.forEach((destination) => {
      const key = `${destination.explored ? 'explored' : 'unexplored'}-${destination.navigable ? 'navigable' : 'blocked'}`;
      const section = sections.find((candidate) => candidate.key === key);
      if (section) {
        section.locations.push(destination);
      }
    });

    if (!destinations.length) {
      return [];
    }

    return sections.filter((section) => section.locations.length > 0);
  }

  collectVisitedNavigateLocationGroups(context, campaignId) {
    if (!campaignId || this.navigateLocationsCampaignId !== campaignId || !Array.isArray(this.navigateLocationGroups)) {
      return [];
    }

    const hexmap = context.hexmap;
    const activeRoomId = String(hexmap?.resolveActiveRoomId?.() || '').trim();
    const currentMapId = String(hexmap?.dungeonData?.map_id || hexmap?.launchContext?.map_id || this.stateManager?.get?.('mapId') || '').trim();
    const directRouteKeys = new Set();

    this.collectNavigateLocationGroups(context).forEach((group) => {
      (Array.isArray(group.locations) ? group.locations : []).forEach((location) => {
        const roomId = String(location?.roomId || '').trim();
        if (roomId) {
          directRouteKeys.add(`${currentMapId}:${roomId}`);
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
            const sameMap = !mapId || !currentMapId || mapId === currentMapId;
            if (sameMap && location.roomId === activeRoomId) {
              return false;
            }
            return !directRouteKeys.has(`${mapId || currentMapId}:${location.roomId}`);
          });

        return {
          ...group,
          title: String(group?.dungeonName || group?.title || group?.dungeonId || 'Visited destinations'),
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
    const disabled = context.encounterActive
      ? this.isActionRailExecutionDisabled(1, context)
      : !context.actorRef;
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

  handleActionRailDirectAction(actionKey, button = null) {
    const context = this.getActionRailContext();
    const hexmap = context.hexmap;
    if (!hexmap) {
      return;
    }
    if (actionKey === 'end-turn') {
      hexmap.endTurn?.();
      return;
    }
    if (actionKey === 'search') {
      this.bus.emit('user:action-selected', { actionKey, button });
      return;
    }

    const guidance = {
    };
    this.bus.emit('chat:system-message', { text: guidance[actionKey] || 'That action is not available right now.', speaker: 'System', kind: 'system' });
  }

  handleActionRailPanelAction(button) {
    const actionType = button.dataset.actionRailExecute || '';
    // Emit user:action-selected — EncounterSystem / NavigationSystem / PlayerAutomation handle it
    if (actionType) {
      this.bus.emit('user:action-selected', { actionKey: actionType, button });
    }
  }

  beginActionRailRequest(button) {
    if (!(button instanceof HTMLButtonElement)) {
      return false;
    }
    if (button.dataset.actionRailPending === '1') {
      return false;
    }
    button.dataset.actionRailPending = '1';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    return true;
  }

  endActionRailRequest(button) {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }
    delete button.dataset.actionRailPending;
    button.disabled = false;
    button.removeAttribute('aria-busy');
  }

  updateActionMode(mode, { canAct = false, canInteract = false, moveLeft = 0, isPlayersTurn = false } = {}) {
    const { actionMoveBtn, actionAttackBtn, actionInteractBtn, actionInstruction } = this._el;

    const setActive = (btn, active) => {
      if (!btn) return;
      btn.classList.toggle('btn-active', !!active);
    };

    setActive(actionMoveBtn, mode === 'move');
    setActive(actionAttackBtn, mode === 'attack');
    setActive(actionInteractBtn, mode === 'search');

    if (actionMoveBtn) {
      actionMoveBtn.title = isPlayersTurn
        ? (moveLeft > 0 ? `${moveLeft} ft remaining` : 'No movement left')
        : 'Not your turn';
    }
    if (actionAttackBtn) {
      actionAttackBtn.title = isPlayersTurn
        ? (canAct ? 'Click an enemy to attack' : 'No actions remaining')
        : 'Not your turn';
    }
    if (actionInteractBtn) {
      actionInteractBtn.title = isPlayersTurn
        ? (canInteract ? 'Search the room for clues, hidden objects, hazards, and sensory details' : 'No search actions available')
        : 'Not your turn';
    }

    if (actionInstruction) {
      if (!isPlayersTurn) {
        actionInstruction.hidden = false;
        actionInstruction.textContent = 'Watching enemy turn...';
      } else if (mode === 'move') {
        actionInstruction.hidden = false;
        actionInstruction.textContent = moveLeft > 0 ? `Click a blue hex to navigate (${moveLeft} ft left).` : 'No movement left; switch to attack or end turn.';
      } else if (mode === 'search') {
        actionInstruction.hidden = false;
        actionInstruction.textContent = canInteract ? 'Search runs a room-level Perception check for new details.' : 'No search actions remaining; attack, move, or end turn.';
      } else {
        actionInstruction.hidden = false;
        actionInstruction.textContent = canAct ? 'Select a hostile target to attack.' : 'No actions remaining; move or end turn.';
      }
    }
  }

  describeCombatantTeam(entity) {
    const combat = entity?.getComponent?.('CombatComponent');
    const rawTeam = String(combat?.team || '').trim();
    if (!rawTeam) {
      return '';
    }
    return rawTeam.charAt(0).toUpperCase() + rawTeam.slice(1);
  }

}
