/**
 * @file panels/CharacterPanel.js
 *
 * Character sheet, entity info, and launch character display.
 * Methods ported verbatim from hexmap.js UIManager.
 */

import { collectCharacterSkillEntries, normalizeInventoryState } from '../utils/inventory-utils.js';
import { normalizeSpellcastingData, collectSpellRankGroups, normalizeDisplayedSpellSlots, formatSpellRankLabel } from '../utils/spell-utils.js';
import { escapeQuestHtml } from '../utils/quest-utils.js';
import { escapeTooltipAttr, flattenTooltipBuckets, formatTooltipActionCost, slugifyTooltipKey, tooltipSourceMatches, uniqueTooltipStrings } from '../utils/dom-utils.js';
import { normalizeAuthoritativeStateActorRef, shouldRequestAuthoritativeStateForActorRef as shouldRequestAuthoritativeStateForActorRefShared } from '../utils/authoritative-state-utils.js';

export class CharacterPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.stateManager = null;
    this.dungeonData = null;
    this.currentCharacterInventoryContext = null;
    this.currentCharacterContext = null;
    this.primaryCharacterContext = null;
    this.primaryLaunchCharacter = null;
    this.activeGameShellSurface = 'party';
    this._tabChangedHandler = null;
    this._characterPanelContainer = null;
    this._partyPanelHost = null;
    this._convertToLibraryInFlight = false;
    this._suppressActorSelectorChange = false;
    this._lastRoomSyncTransitionId = '';
    this._relationshipsMatrixRequestToken = 0;
    this._relationshipsMatrixRemoteDisabledUntil = 0;
    this.activeActorFilter = 'party';
    this.activeActorSort = 'alpha';
    this._actorFilterButtons = [];
  }

  buildConditionTooltipModel(condition, projectedTooltips = {}) {
    const raw = (condition && typeof condition === 'object') ? condition : { name: String(condition || 'Condition') };
    const rawCode = String(raw.condition_type || raw.id || raw.name || 'condition');
    const code = rawCode.trim().toLowerCase().replace(/[\s-]+/g, '_');
    const projected = projectedTooltips && typeof projectedTooltips === 'object'
      ? projectedTooltips[code]
      : null;
    if (raw.tooltip && typeof raw.tooltip === 'object') {
      return {
        name: String(raw.tooltip.name || raw.name || raw.condition_type || raw.id || 'Condition').trim() || 'Condition',
        type: String(raw.tooltip.type || 'condition'),
        desc: String(raw.tooltip.desc || 'Active condition or effect.'),
        stats: Array.isArray(raw.tooltip.stats) ? raw.tooltip.stats : [],
        effects: Array.isArray(raw.tooltip.effects) ? raw.tooltip.effects : [],
        notes: Array.isArray(raw.tooltip.notes) ? raw.tooltip.notes : [],
      };
    }
    if (projected && typeof projected === 'object') {
      return {
        name: String(projected.name || raw.name || raw.condition_type || raw.id || 'Condition').trim() || 'Condition',
        type: String(projected.type || 'condition'),
        desc: String(projected.desc || 'Active condition or effect.'),
        stats: Array.isArray(projected.stats) ? projected.stats : [],
        effects: Array.isArray(projected.effects) ? projected.effects : [],
        notes: Array.isArray(projected.notes) ? projected.notes : [],
      };
    }
    const name = String(raw.name || raw.condition_type || raw.id || 'Condition').trim() || 'Condition';
    const stats = [];
    const effects = [];
    const notes = [];
    const desc = String(raw.description || '').trim();

    if (raw.duration) {
      notes.push(`Duration: ${String(raw.duration).replace(/_/g, ' ')}`);
    }
    if (raw.source) {
      notes.push(`Source: ${raw.source}`);
    }
    if (raw.value !== undefined && raw.value !== null && raw.value !== '') {
      effects.push(`Value: ${raw.value}`);
    }

    return {
      name,
      type: 'condition',
      desc: desc || 'Active condition or effect.',
      stats,
      effects,
      notes,
    };
  }

  renderConditionTooltipEntry(condition, projectedTooltips = {}) {
    const tooltip = this.buildConditionTooltipModel(condition, projectedTooltips);
    const nameHtml = escapeTooltipAttr(tooltip.name);
    return `<li class="condition-entry" data-tooltip-enabled="true" data-tooltip-name="${nameHtml}" data-tooltip-type="${escapeTooltipAttr(tooltip.type)}" data-tooltip-desc="${escapeTooltipAttr(tooltip.desc)}" data-tooltip-stats="${escapeTooltipAttr(JSON.stringify(tooltip.stats))}" data-tooltip-effects="${escapeTooltipAttr(JSON.stringify(tooltip.effects))}" data-tooltip-notes="${escapeTooltipAttr(JSON.stringify(tooltip.notes))}">${nameHtml}</li>`;
  }

  init(dungeonData, stateManager) {
    this.dungeonData = dungeonData || {};
    this.stateManager = stateManager || {};
    const id = (k) => document.getElementById(k);
    const s = (k) => this.container?.querySelector(`[data-char="${k}"]`) || null;
    const abilityBindingIds = {
      characterStr: 'char-str',
      characterStrMod: 'char-str-mod',
      characterDex: 'char-dex',
      characterDexMod: 'char-dex-mod',
      characterCon: 'char-con',
      characterConMod: 'char-con-mod',
      characterInt: 'char-int',
      characterIntMod: 'char-int-mod',
      characterWis: 'char-wis',
      characterWisMod: 'char-wis-mod',
      characterCha: 'char-cha',
      characterChaMod: 'char-cha-mod',
    };
    const abilityElements = Object.fromEntries(
      Object.entries(abilityBindingIds).map(([key, domId]) => [key, id(domId)])
    );

    this._el = {
      // Entity info sub-panel (inline NPC/creature details)
      entityInfoPanel:         id('char-entity-info'),
      entityName:              s('entity-name'),
      entityType:              id('entity-type'),
      entityImageWrap:         id('entity-image-wrap'),
      entityImage:             id('entity-image'),
      entitySummary:           id('entity-summary'),
      entityDescription:       id('entity-description'),
      entityKnownDetails:      id('entity-known-details'),
      entityTeam:              id('entity-team'),
      entityHp:                id('entity-hp'),
      entityAc:                id('entity-ac'),
      entityActions:           id('entity-actions'),
      entityMovement:          id('entity-movement'),
      // Character sheet header
      characterPortraitWrap:   id('char-portrait-wrap'),
      characterPortrait:       id('char-portrait'),
      characterName:           id('char-name'),
      characterType:           id('char-type'),
      characterSubtitle:       id('char-subtitle'),
      characterPersonalityWrap: id('char-personality-wrap'),
      characterPersonality:    id('char-personality'),
      characterBackstoryWrap:  id('char-backstory-wrap'),
      characterBackstory:      id('char-backstory'),
      characterAncestry:       id('char-ancestry'),
      characterLevel:          id('char-level'),
      characterConditions:     id('char-conditions'),
      characterFullSheetLink:  id('char-full-sheet-link'),
      partyActorSelectWrap:    id('party-actor-select-wrap'),
      partyActorFilters:       id('party-actor-filters'),
      partyActorSort:          id('party-actor-sort'),
      partyActorSelect:        id('party-actor-select'),
      partyFullSheetLink:      id('party-full-sheet-link'),
      partySheetName:          id('party-sheet-name'),
      partySheetSubtitle:      id('party-sheet-subtitle'),
      partySheetEmbedWrap:     id('party-sheet-embed-wrap'),
      partySheetEmbed:         id('party-sheet-embed'),
      partySheetEmpty:         id('party-sheet-empty'),
      actorRelationshipsMatrix: id('actor-relationships-matrix'),
      characterConvertLibraryButton: id('char-convert-library-button'),
      characterSheetEmbedWrap: id('char-sheet-embed-wrap'),
      characterSheetEmbed:     id('char-sheet-embed'),
      characterSheetLegacy:    id('char-sheet-legacy'),
      // Stats
      characterHp:             id('char-hp'),
      characterAc:             id('char-ac'),
      characterHero:           id('char-hero'),
      characterSpeed:          id('char-speed'),
      characterPerception:     id('char-perception'),
      characterXp:             id('char-xp'),
      ...abilityElements,
      characterFort:           id('char-fort'),
      characterRef:            id('char-ref'),
      characterWill:           id('char-will'),
      characterSkills:         id('char-skills'),
      characterGp:             id('char-gp'),
      characterSp:             id('char-sp'),
      characterCp:             id('char-cp'),
      // Spells/features sidebar panel
      characterSpellsSection:  id('char-spells-section'),
      characterSpellMeta:      id('char-spell-meta'),
      characterSpells:         id('char-spells'),
      characterFeatures:       id('char-features'),
    };
    const nullKeys = Object.entries(this._el).filter(([,v]) => !v).map(([k]) => k);
    console.log('[CharacterPanel] init', { container: !!this.container, nullEl: nullKeys.length, nullKeys: nullKeys.join(',') || 'none' });
    this._subscribe();
    this._bindGameShellTabChanges();
    this.ensureRelationshipsSidebarSurface();
    this.setupCharacterSheetSections();
    this._initSidebarTabs();
    this._bindCharacterActions();
    this.refreshActorSelector();
  }

  ensureRelationshipsSidebarSurface() {
    const sidebar = this.container?.closest('.game-layout__sidebar');
    if (!sidebar) {
      return;
    }

    const tabStrip = sidebar.querySelector('#sidebar-tabs') || sidebar.querySelector('.sidebar-tabs');
    if (tabStrip && !tabStrip.querySelector('[data-sidebar-tab="relationships"]')) {
      const tab = document.createElement('button');
      tab.type = 'button';
      tab.className = 'sidebar-tab';
      tab.dataset.sidebarTab = 'relationships';
      tab.textContent = 'Relationships';
      const characterTab = tabStrip.querySelector('[data-sidebar-tab="character"]');
      if (characterTab?.nextSibling) {
        tabStrip.insertBefore(tab, characterTab.nextSibling);
      } else {
        tabStrip.appendChild(tab);
      }
    }

    if (!sidebar.querySelector('#sidebar-panel-relationships')) {
      const panel = document.createElement('div');
      panel.id = 'sidebar-panel-relationships';
      panel.className = 'sidebar-panel';
      panel.style.display = 'none';
      panel.innerHTML = `
        <div class="character-sheet">
          <div class="character-sheet__section">
            <div class="section-header section-header--static">
              <h4>Actor Disposition Calculations</h4>
            </div>
            <div class="section-body section-body--static">
              <p class="muted small">Selected actor disposition formula and weighted components toward other room actors.</p>
              <div id="actor-relationships-matrix" class="relationships-matrix-wrap">
                <div class="relationships-calculation-summary" style="padding:8px 4px;color:#94a3b8;font-size:12px;">
                  Select a campaign room to load actor relationship calculations.
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
      const spellsPanel = sidebar.querySelector('#sidebar-panel-spells-feats');
      if (spellsPanel?.parentNode) {
        spellsPanel.parentNode.insertBefore(panel, spellsPanel);
      } else {
        sidebar.appendChild(panel);
      }
    }

    if (!this._el.actorRelationshipsMatrix) {
      this._el.actorRelationshipsMatrix = document.getElementById('actor-relationships-matrix');
    }

    const actorSwitch = this._el.partyActorSelectWrap;
    if (actorSwitch && !actorSwitch.querySelector('[data-char-action="open-relationships-tab"]')) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'character-sheet__actor-filter';
      button.dataset.charAction = 'open-relationships-tab';
      button.textContent = 'Relationships';
      const filterGroup = this._el.partyActorFilters || actorSwitch;
      filterGroup.appendChild(button);
    }
  }

  /**
   * Wire sidebar sub-tab buttons (Character / Relationships / Spells & Feats / Inventory / Quests).
   * Buttons use [data-sidebar-tab] and panels use .sidebar-panel + matching IDs.
   */
  _initSidebarTabs() {
    const sidebar = this.container?.closest('.game-layout__sidebar');
    if (!sidebar) {
      console.warn('[CharacterPanel] _initSidebarTabs: no .game-layout__sidebar ancestor found');
      return;
    }
    const tabs   = Array.from(sidebar.querySelectorAll('[data-sidebar-tab]'));
    const panels = Array.from(sidebar.querySelectorAll('.sidebar-panel'));
    const initialPanelStyles = Object.fromEntries(panels.map((p) => [p.id, p.style.display || '(none)']));
    console.log('[CharacterPanel] _initSidebarTabs', { tabCount: tabs.length, panelCount: panels.length, initialStyles: initialPanelStyles });

    tabs.forEach((tab) => {
      const handler = () => {
        const targetId = `sidebar-panel-${tab.dataset.sidebarTab}`;
        tabs.forEach((t)   => t.classList.toggle('sidebar-tab--active',   t === tab));
        panels.forEach((p) => {
          const active = p.id === targetId;
          p.classList.toggle('sidebar-panel--active', active);
          // Clear any inline display style so CSS class controls visibility.
          // Twig template sets style="display:none;" on non-default panels.
          p.style.display = '';
        });
        if (tab.dataset.sidebarTab === 'inventory' && this.currentCharacterInventoryContext) {
          this.bus.emit('character:inventory-refresh-requested', this.currentCharacterInventoryContext);
        }
        if (tab.dataset.sidebarTab === 'relationships') {
          this.renderRelationshipsMatrix();
        }
        if (tab.dataset.sidebarTab === 'quests') {
          this.bus.emit('quest:refresh-requested', this.buildQuestRefreshContext('character-sidebar-tab'));
        }
        this.bus.emit('character:runtime-state-refresh-requested', {
          reason: `character-sidebar-tab:${String(tab.dataset.sidebarTab || '').trim().toLowerCase() || 'unknown'}`,
          context: {
            ...(this.currentCharacterContext || {}),
            characterId: Number(
              this.currentCharacterContext?.runtimeCharacterId
              || this.currentCharacterContext?.sheetCharacterId
              || this.currentCharacterInventoryContext?.characterId
              || 0
            ) || null,
            campaignId: Number(
              this.currentCharacterContext?.campaignId
              || this.currentCharacterInventoryContext?.campaignId
              || this.stateManager?.hexmap?.resolveCampaignId?.()
              || 0
            ) || null,
          },
        });
        console.log('[CharacterPanel] sidebar tab clicked', { target: targetId, panelVisible: !!document.getElementById(targetId) && !document.getElementById(targetId).classList.contains('dc-is-hidden') });
      };
      tab.addEventListener('click', handler);
      this._unsubs.push(() => tab.removeEventListener('click', handler));
    });
  }

  _bindCharacterActions() {
    const convertButton = this._el.characterConvertLibraryButton;
    if (convertButton) {
      const handler = () => {
        this.convertCurrentCharacterToLibrary();
      };
      convertButton.addEventListener('click', handler);
      this._unsubs.push(() => convertButton.removeEventListener('click', handler));
    }

    const partyActorSelect = this._el.partyActorSelect;
    if (partyActorSelect) {
      const partyActorSelectHandler = () => {
        if (this._suppressActorSelectorChange) {
          return;
        }
        this.focusActorFromSelector(partyActorSelect.value, { activateCharacterTab: false });
        this.bus.emit('character:runtime-state-refresh-requested', {
          reason: 'party-actor-selector-change',
          context: {
            ...(this.currentCharacterContext || {}),
            characterId: Number(
              this.currentCharacterContext?.runtimeCharacterId
              || this.currentCharacterContext?.sheetCharacterId
              || this.currentCharacterInventoryContext?.characterId
              || 0
            ) || null,
            campaignId: Number(
              this.currentCharacterContext?.campaignId
              || this.currentCharacterInventoryContext?.campaignId
              || this.stateManager?.hexmap?.resolveCampaignId?.()
              || 0
            ) || null,
          },
        });
        this.bus.emit('quest:refresh-requested', this.buildQuestRefreshContext('party-actor-selector-change'));
      };
      partyActorSelect.addEventListener('change', partyActorSelectHandler);
      this._unsubs.push(() => partyActorSelect.removeEventListener('change', partyActorSelectHandler));
    }

    const actorFilterButtons = Array.from(this._el.partyActorFilters?.querySelectorAll?.('[data-actor-filter]') || []);
    this._actorFilterButtons = actorFilterButtons;
    actorFilterButtons.forEach((button) => {
      const handler = () => {
        const requestedFilter = String(button?.dataset?.actorFilter || '').trim().toLowerCase();
        if (!requestedFilter) {
          return;
        }
        this.activeActorFilter = this.normalizeActorFilter(requestedFilter);
        this.refreshActorSelector();
      };
      button.addEventListener('click', handler);
      this._unsubs.push(() => button.removeEventListener('click', handler));
    });

    const partyActorSort = this._el.partyActorSort;
    if (partyActorSort) {
      const handler = () => {
        this.activeActorSort = this.normalizeActorSortMode(partyActorSort.value);
        this.refreshActorSelector();
      };
      partyActorSort.addEventListener('change', handler);
      this._unsubs.push(() => partyActorSort.removeEventListener('change', handler));
    }

    const openRelationshipsButton = this._el.partyActorSelectWrap?.querySelector?.('[data-char-action="open-relationships-tab"]') || null;
    if (openRelationshipsButton) {
      const handler = () => {
        this._activateSidebarTab('relationships');
        this.renderRelationshipsMatrix();
      };
      openRelationshipsButton.addEventListener('click', handler);
      this._unsubs.push(() => openRelationshipsButton.removeEventListener('click', handler));
    }
  }

  destroy() {
    if (this._tabChangedHandler) {
      window.removeEventListener('dungeoncrawler:game-shell-tab-changed', this._tabChangedHandler);
      this._tabChangedHandler = null;
    }
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('entity:selected',   (d) => {
        this.showEntityInfo(d?.entity);
        this.refreshActorSelector();
      }),
      this.bus.on('entity:deselected', () => {
        this.hideEntityInfo();
        this.refreshActorSelector();
        this.syncSheetLinksForSelectedEntity();
      }),
      this.bus.on('game:init',         (d) => {
        if (d?.launchCharacter) {
          this.consumeLaunchCharacterUpdate(d.launchCharacter);
        }
        this.refreshActorSelector();
      }),
      this.bus.on('character:updated', (d) => {
        const launchCharacter = d?.launchCharacter
          || this.stateManager?.hexmap?.launchCharacter
          || this.stateManager?.hexmap?.characterData
          || null;
        if (launchCharacter) {
          this.consumeLaunchCharacterUpdate(launchCharacter);
        }
        this.refreshActorSelector();
        this.syncSheetLinksForSelectedEntity();
      }),
      this.bus.on('character:sheet-requested', (d) => {
        if (d?.characterId) this.showEmbeddedCharacterSheet(d.characterId);
      }),
      this.bus.on('room:changed', (payload) => this.handleRoomContextChanged(payload)),
      this.bus.on('room:actor-roster-changed', (payload) => this.handleRoomContextChanged(payload)),
      this.bus.on('room:occupants-membership-changed', (payload) => this.handleRoomContextChanged(payload)),
      this.bus.on('game:state-refreshed', () => this.rehydrateSelectedEntityFromEncounterCache()),
      // Legacy compatibility event during bus migration.
      this.bus.on('room:occupants-changed', (payload) => this.handleRoomContextChanged(payload)),
    );
  }

  rehydrateSelectedEntityFromEncounterCache() {
    const selectedRef = String(this._el.partyActorSelect?.value || '').trim();
    const selectedOption = this._el.partyActorSelect?.selectedOptions?.[0] || null;
    const selectedActorKind = String(selectedOption?.dataset?.actorKind || '').trim().toLowerCase();
    if (!selectedRef || selectedRef === '__primary__' || selectedActorKind === 'primary') {
      return;
    }

    const selectedEntity = this.stateManager?.hexmap?._getStateValue?.('selectedEntity')
      || this.resolveEntityByRef(selectedRef)
      || null;
    if (!selectedEntity) {
      return;
    }
    this.refreshEncounterStateAndRerenderEntity(
      selectedEntity,
      selectedActorKind === 'actor' ? 'actor' : 'follower',
      { preferredActorRef: selectedRef }
    );
  }

  handleRoomContextChanged(payload = {}) {
    const transitionId = String(payload?.transition?.id || '').trim();
    if (transitionId && transitionId === this._lastRoomSyncTransitionId) {
      return;
    }
    if (transitionId) {
      this._lastRoomSyncTransitionId = transitionId;
    }
    this.refreshActorSelector();
    if (this.isPartySurfaceActive()) {
      this.applyPartyFollowerSelectionToCharacterSheet();
    }
    if (this.isRelationshipsTabActive()) {
      this.renderRelationshipsMatrix();
    }
  }

  _bindGameShellTabChanges() {
    this._characterPanelContainer = document.getElementById('game-panel-party');
    this._partyPanelHost = document.getElementById('party-character-panel-host');
    this._tabChangedHandler = (event) => {
      const tabId = String(event?.detail?.tabId || '').trim().toLowerCase();
      if (tabId !== 'party' && tabId !== 'character') {
        return;
      }
      this.activeGameShellSurface = tabId === 'character' ? 'party' : tabId;
      this.syncCharacterSurfaceForActiveTab();
    };
    window.addEventListener('dungeoncrawler:game-shell-tab-changed', this._tabChangedHandler);
    const shell = this.container?.closest?.('[data-game-shell]') || document.querySelector('[data-game-shell]');
    const currentTabId = String(shell?.dataset?.gameShellActive || '').trim().toLowerCase();
    if (currentTabId === 'party' || currentTabId === 'character') {
      this.activeGameShellSurface = currentTabId === 'character' ? 'party' : currentTabId;
      this.syncCharacterSurfaceForActiveTab();
    } else {
      this.syncPartySelectorVisibility();
    }
  }

  isPartySurfaceActive() {
    return this.activeGameShellSurface === 'party';
  }

  syncCharacterSurfaceForActiveTab() {
    this.attachCharacterPanelToActiveSurface();
    this.refreshActorSelector();
    this.applyPartyFollowerSelectionToCharacterSheet();
  }

  attachCharacterPanelToActiveSurface() {
    const characterContainer = this._characterPanelContainer || document.getElementById('game-panel-party');
    const partyHost = this._partyPanelHost || document.getElementById('party-character-panel-host');
    const characterSurface = characterContainer?.querySelector('.game-shell__character-panel')
      || partyHost?.querySelector('.game-shell__character-panel')
      || null;
    if (!characterSurface) {
      return;
    }
    if (this.isPartySurfaceActive()) {
      if (partyHost && characterSurface.parentElement !== partyHost) {
        partyHost.appendChild(characterSurface);
      }
      return;
    }
    if (characterContainer && characterSurface.parentElement !== characterContainer) {
      characterContainer.appendChild(characterSurface);
    }
  }

  restorePrimaryCharacterSheet() {
    const launchCharacter = this.primaryLaunchCharacter
      || this.stateManager?.hexmap?.launchCharacter
      || this.stateManager?.hexmap?.characterData
      || null;
    if (!launchCharacter) {
      return;
    }
    this.showLaunchCharacter(launchCharacter, { storeAsPrimary: false });
  }

  hideEntityInfo() {
    if (this._el.entityInfoPanel) {
      this._el.entityInfoPanel.classList.add('dc-is-hidden');
      this._el.entityInfoPanel.style.display = 'none';
      this._el.entityInfoPanel.setAttribute('aria-hidden', 'true');
    }
    this.syncSheetLinksForSelectedEntity();
  }

  setupCharacterSheetSections() {
    const sectionHeaders = document.querySelectorAll('.character-sheet__section .section-header');
    sectionHeaders.forEach((header) => {
      if (header.classList.contains('section-header--static')) return;
      const handler = () => {
        const section = header.closest('.character-sheet__section');
        const sectionName = header.dataset.section;
        const body = section.querySelector(`.section-body[data-section="${sectionName}"]`);
        const toggle = header.querySelector('.section-toggle');
        if (!body || !toggle) return;
        const isCollapsed = section.classList.contains('collapsed');
        if (isCollapsed) {
          section.classList.remove('collapsed');
          body.style.display = '';
          toggle.textContent = '▾';
        } else {
          section.classList.add('collapsed');
          body.style.display = 'none';
          toggle.textContent = '▸';
        }
      };
      header.addEventListener('click', handler);
      this._unsubs.push(() => header.removeEventListener('click', handler));
    });
  }

  /**
   * Activate a sidebar sub-tab by ID (e.g. 'character', 'inventory').
   * Directly toggles CSS classes and clears inline display styles — does NOT
   * call .click() so localStorage is not touched on programmatic activation.
   */
  _activateSidebarTab(tabId) {
    const sidebar = this.container?.closest('.game-layout__sidebar');
    if (!sidebar) return;
    sidebar.querySelectorAll('[data-sidebar-tab]').forEach((t) => {
      t.classList.toggle('sidebar-tab--active', t.dataset.sidebarTab === tabId);
    });
    sidebar.querySelectorAll('.sidebar-panel').forEach((p) => {
      const active = p.id === `sidebar-panel-${tabId}`;
      p.classList.toggle('sidebar-panel--active', active);
      // Clear inline style so CSS class controls visibility.
      p.style.display = '';
    });
    if (tabId === 'inventory' && this.currentCharacterInventoryContext) {
      this.bus.emit('character:inventory-refresh-requested', this.currentCharacterInventoryContext);
    }
    if (tabId === 'relationships') {
      this.renderRelationshipsMatrix();
    }
    if (tabId === 'quests') {
      this.bus.emit('quest:refresh-requested', this.buildQuestRefreshContext('character-sidebar-programmatic'));
    }
  }

  buildQuestRefreshContext(source = '') {
    const contextCharacterId = Number(this.currentCharacterContext?.sheetCharacterId || 0) || 0;
    const campaignId = Number(this.currentCharacterContext?.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || 0) || 0;
    return {
      source,
      actorScope: this.isPartySurfaceActive() ? 'party' : 'character',
      characterId: contextCharacterId > 0 ? contextCharacterId : null,
      campaignId: campaignId > 0 ? campaignId : null,
    };
  }

  _activateCharacterTab() {
    if (typeof document === 'undefined') {
      return;
    }
    const shell = this.container?.closest?.('[data-game-shell]') || document.querySelector('[data-game-shell]');
    if (!(shell instanceof HTMLElement)) {
      return;
    }
    shell.dispatchEvent(new CustomEvent('dungeoncrawler:activate-tab', {
      detail: { tabId: 'party' },
    }));
  }

  resolveEntityRef(entity = null) {
    return String(
      entity?.dcEntityRef
      || entity?.dcEntityInstanceId
      || entity?.instanceId
      || entity?.id
      || ''
    ).trim();
  }

  resolveEntityByRef(actorRef = '') {
    const normalizedRef = String(actorRef || '').trim();
    if (!normalizedRef) {
      return null;
    }

    const hexmap = this.stateManager?.hexmap || null;
    const direct = hexmap?.entityManager?.getEntity?.(normalizedRef) || null;
    if (direct) {
      return direct;
    }

    const entities = hexmap?.entityManager?.getEntitiesWith?.('PositionComponent') || [];
    return entities.find((entity) => this.entityMatchesActorRef(entity, normalizedRef)) || null;
  }

  entityMatchesActorRef(entity = null, actorRef = '') {
    const normalizedRef = String(actorRef || '').trim();
    if (!entity || !normalizedRef) {
      return false;
    }
    const runtimeRef = this.resolveEntityRef(entity);
    const metadata = this.resolveEntityMetadata(entity);
    const characterId = this.resolveEntityCharacterId(entity);
    const campaignCharacterId = Number(
      metadata?.campaign_character_id
      || entity?.dcStatePayload?.campaign_character_id
      || entity?.dcEntityPayload?.campaign_character_id
      || 0
    ) || 0;
    const contentId = String(
      entity?.dcContentId
      || entity?.dcStatePayload?.content_id
      || entity?.dcStatePayload?.entity_ref?.content_id
      || entity?.dcEntityPayload?.content_id
      || entity?.dcEntityPayload?.entity_ref?.content_id
      || ''
    ).trim();
    const candidateActorRefs = new Set([
      runtimeRef,
      runtimeRef ? `runtime:${runtimeRef}` : '',
      campaignCharacterId > 0 ? `campaign-character:${campaignCharacterId}` : '',
      characterId > 0 ? `character:${characterId}` : '',
      contentId ? `content:${contentId}` : '',
    ].filter(Boolean));
    return candidateActorRefs.has(normalizedRef);
  }

  resolveEntityCharacterId(entity = null) {
    return Number(
      entity?.dcCharacterId
      || entity?.dcStatePayload?.metadata?.character_id
      || entity?.dcStatePayload?.character_id
      || entity?.dcStatePayload?.state?.character_id
      || entity?.dcEntityPayload?.character_id
      || entity?.dcEntityPayload?.state?.character_id
      || 0
    ) || 0;
  }

  resolveEntityLabel(entity = null) {
    return String(
      entity?.getComponent?.('IdentityComponent')?.name
      || entity?.dcStatePayload?.metadata?.name
      || entity?.dcStatePayload?.label
      || entity?.dcEntityPayload?.label
      || this.resolveEntityRef(entity)
      || 'Unknown actor'
    ).trim();
  }

  resolveEntityMetadata(entity = null) {
    const metadata = entity?.dcStatePayload?.metadata
      || entity?.dcStatePayload?.state?.metadata
      || entity?.dcEntityPayload?.metadata
      || entity?.dcEntityPayload?.state?.metadata
      || null;
    return metadata && typeof metadata === 'object' ? metadata : {};
  }

  normalizeActorFilter(rawFilter = '') {
    const normalized = String(rawFilter || '').trim().toLowerCase();
    if (['all', 'party', 'allied', 'hostile', 'neutral', 'hazard'].includes(normalized)) {
      return normalized;
    }
    return 'party';
  }

  normalizeActorSortMode(rawMode = '') {
    const normalized = String(rawMode || '').trim().toLowerCase();
    if (normalized === 'initiative') {
      return 'initiative';
    }
    return 'alpha';
  }

  normalizeActorSide(rawSide = '') {
    const normalized = String(rawSide || '').trim().toLowerCase();
    if (['all', 'party', 'allied', 'hostile', 'neutral', 'hazard'].includes(normalized)) {
      return normalized;
    }
    if (normalized === 'ally') {
      return 'allied';
    }
    if (normalized === 'enemy' || normalized === 'hostile') {
      return 'hostile';
    }
    if (normalized === 'player') {
      return 'party';
    }
    return 'neutral';
  }

  resolveActorSideForEntity(entity = null) {
    if (!entity) {
      return 'neutral';
    }

    const metadata = this.resolveEntityMetadata(entity);
    const identityType = String(entity?.getComponent?.('IdentityComponent')?.entityType || '').trim().toLowerCase();
    if (identityType === 'hazard' || identityType === 'trap' || identityType === 'obstacle') {
      return 'hazard';
    }

    const currentCharacterId = this.resolveCurrentCharacterId();
    if (this.isFollowerEntityForCurrentCharacter(entity, currentCharacterId)) {
      return 'party';
    }

    const teamFromCombat = String(entity?.getComponent?.('CombatComponent')?.team || '').trim().toLowerCase();
    const teamFromMetadata = String(
      metadata?.team
      || entity?.dcEntityPayload?.team
      || entity?.dcEntityPayload?.presentation?.badge
      || entity?.dcStatePayload?.team
      || ''
    ).trim().toLowerCase();
    const normalizedTeam = this.normalizeActorSide(teamFromCombat || teamFromMetadata);
    if (normalizedTeam !== 'neutral') {
      return normalizedTeam;
    }

    if (identityType === 'player_character' || identityType === 'player' || identityType === 'pc') {
      return 'party';
    }

    return 'neutral';
  }

  isEntityInActiveRoom(entity = null) {
    if (!entity) {
      return false;
    }
    const activeRoomId = String(
      this.stateManager?.hexmap?.resolveActiveRoomId?.()
      || this.stateManager?.hexmap?.activeRoomId
      || ''
    ).trim();
    if (!activeRoomId) {
      return true;
    }
    const positionRoomId = String(entity?.getComponent?.('PositionComponent')?.roomId || '').trim();
    const placementRoomId = String(entity?.placement?.room_id || '').trim();
    const payloadRoomId = String(entity?.dcEntityPayload?.room_id || entity?.dcEntityPayload?.placement?.room_id || '').trim();
    const roomId = positionRoomId || placementRoomId || payloadRoomId;
    return roomId === activeRoomId;
  }

  formatActorSideLabel(side = '') {
    const normalized = this.normalizeActorSide(side);
    if (normalized === 'party') {
      return 'Party';
    }
    if (normalized === 'allied') {
      return 'Allied';
    }
    if (normalized === 'hostile') {
      return 'Hostile';
    }
    if (normalized === 'hazard') {
      return 'Hazard';
    }
    return 'Neutral';
  }

  syncActorFilterButtons(optionSet = []) {
    const counts = {
      all: 0,
      party: 0,
      allied: 0,
      hostile: 0,
      neutral: 0,
      hazard: 0,
    };
    optionSet.forEach((option) => {
      const side = this.normalizeActorSide(option?.actorSide || 'neutral');
      counts.all++;
      if (Object.prototype.hasOwnProperty.call(counts, side)) {
        counts[side]++;
      }
    });
    this._actorFilterButtons.forEach((button) => {
      const filter = this.normalizeActorFilter(button?.dataset?.actorFilter || 'party');
      const isActive = filter === this.activeActorFilter;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      const label = String(button.dataset.actorFilterLabel || button.textContent || '').trim();
      const count = counts[filter] ?? 0;
      button.textContent = `${label} (${count})`;
    });
  }

  getAvailableActorSortModes() {
    const sortModes = this.stateManager?.hexmap?.mapVisualState?.actor_roster?.sort_modes;
    if (!Array.isArray(sortModes) || sortModes.length === 0) {
      return ['alpha'];
    }
    const normalizedModes = Array.from(new Set(
      sortModes.map((mode) => this.normalizeActorSortMode(mode))
    ));
    return normalizedModes.length > 0 ? normalizedModes : ['alpha'];
  }

  syncActorSortControl() {
    const select = this._el.partyActorSort;
    if (!select) {
      return;
    }

    const availableModes = this.getAvailableActorSortModes();
    if (!availableModes.includes(this.activeActorSort)) {
      this.activeActorSort = availableModes[0] || 'alpha';
    }

    const modeLabels = {
      alpha: 'Alphabetical',
      initiative: 'Initiative',
    };
    select.innerHTML = '';
    availableModes.forEach((mode) => {
      const option = document.createElement('option');
      option.value = mode;
      option.textContent = modeLabels[mode] || mode;
      select.appendChild(option);
    });
    select.value = this.activeActorSort;
    select.disabled = availableModes.length <= 1;
  }

  buildActorOptionsFromCanonicalRoster() {
    const hexmap = this.stateManager?.hexmap || null;
    const rosterEntries = Array.isArray(hexmap?.getActiveRoomActorRoster?.())
      ? hexmap.getActiveRoomActorRoster()
      : [];
    return rosterEntries.map((entry) => {
      const runtimeRef = String(entry?.entity_ref || entry?.runtime_instance_id || entry?.actor_id || '').trim();
      if (!runtimeRef) {
        return null;
      }
      const actorSide = this.normalizeActorSide(entry?.side || 'neutral');
      const actorKind = String(entry?.actor_kind || '').trim().toLowerCase() === 'follower'
        ? 'follower'
        : 'actor';
      const followerKind = String(
        entry?.sheet_ref?.sheet_type === 'follower'
          ? (entry?.sheet_ref?.route_params?.follower_kind || '')
          : ''
      ).trim().toLowerCase();
      const ownerCharacterId = Number(entry?.sheet_ref?.route_params?.character_id || 0) || 0;
      const followerCharacterId = Number(
        entry?.sheet_ref?.sheet_type === 'character'
          ? (entry?.sheet_ref?.route_params?.character_id || 0)
          : 0
      ) || 0;
      const displayName = String(entry?.display_name || runtimeRef).trim() || runtimeRef;
      const kindLabel = this.formatActorSideLabel(actorSide);
      const sheetHref = this.buildSheetHrefFromSheetRef(entry?.sheet_ref || null);
      const combat = entry?.combat && typeof entry.combat === 'object' ? entry.combat : {};
      const sortInitiative = Number.isFinite(Number(combat?.initiative)) ? Number(combat.initiative) : null;
      return {
        value: runtimeRef,
        label: `${displayName} (${kindLabel})`,
        actorKind,
        actorSide,
        followerKind,
        ownerCharacterId,
        followerCharacterId,
        sheetHref,
        sortInitiative,
        sortIsParticipant: Boolean(combat?.is_participant) || sortInitiative !== null,
        sortIsCurrent: Boolean(combat?.is_current),
      };
    }).filter(Boolean);
  }

  sortActorOptions(optionSet = []) {
    const sortMode = this.normalizeActorSortMode(this.activeActorSort);
    return [...optionSet].sort((a, b) => {
      if (sortMode === 'initiative') {
        const aParticipant = Boolean(a?.sortIsParticipant);
        const bParticipant = Boolean(b?.sortIsParticipant);
        if (aParticipant !== bParticipant) {
          return aParticipant ? -1 : 1;
        }
        if (aParticipant && bParticipant) {
          const aCurrent = Boolean(a?.sortIsCurrent);
          const bCurrent = Boolean(b?.sortIsCurrent);
          if (aCurrent !== bCurrent) {
            return aCurrent ? -1 : 1;
          }
          const aInitiative = Number.isFinite(Number(a?.sortInitiative)) ? Number(a.sortInitiative) : Number.NEGATIVE_INFINITY;
          const bInitiative = Number.isFinite(Number(b?.sortInitiative)) ? Number(b.sortInitiative) : Number.NEGATIVE_INFINITY;
          if (aInitiative !== bInitiative) {
            return bInitiative - aInitiative;
          }
        }
      } else {
        if (a?.value === '__primary__' && b?.value !== '__primary__') {
          return -1;
        }
        if (b?.value === '__primary__' && a?.value !== '__primary__') {
          return 1;
        }
      }
      return String(a?.label || '').localeCompare(String(b?.label || ''), undefined, { sensitivity: 'base' });
    });
  }

  buildSheetHrefFromSheetRef(sheetRef = null) {
    if (!sheetRef || typeof sheetRef !== 'object') {
      return '';
    }
    const routeName = String(sheetRef.route_name || '').trim();
    const routeParams = sheetRef.route_params && typeof sheetRef.route_params === 'object'
      ? sheetRef.route_params
      : {};
    const query = sheetRef.query && typeof sheetRef.query === 'object' ? { ...sheetRef.query } : {};

    if (routeName === 'dungeoncrawler_content.character_view') {
      const characterId = Number(routeParams.character_id || 0) || 0;
      return this.buildCharacterSheetHref(characterId);
    }
    if (routeName === 'dungeoncrawler_content.character_follower_view') {
      const ownerCharacterId = Number(routeParams.character_id || 0) || 0;
      const followerKind = String(routeParams.follower_kind || '').trim().toLowerCase();
      return this.buildCharacterSheetHref(ownerCharacterId, followerKind);
    }

    const campaignId = Number(query.campaign_id || this.currentCharacterContext?.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || 0) || 0;
    const actorId = String(routeParams.actor_id || sheetRef.sheet_id || '').trim();
    if (routeName === 'dungeoncrawler_content.actor_view' && actorId) {
      return campaignId > 0
        ? `/actors/${encodeURIComponent(actorId)}?campaign_id=${campaignId}`
        : `/actors/${encodeURIComponent(actorId)}`;
    }

    return '';
  }

  resolveEntityFollowerKind(entity = null) {
    const entityRef = this.resolveEntityRef(entity).toLowerCase();
    if (entityRef.startsWith('familiar-')) {
      return 'familiar';
    }
    if (entityRef.startsWith('companion-')) {
      return 'companion';
    }
    if (entityRef.startsWith('follower-')) {
      return 'follower';
    }
    const metadata = this.resolveEntityMetadata(entity);
    const explicitKind = String(metadata?.follower_kind || metadata?.bond_contract?.follower_kind || '').trim().toLowerCase();
    if (['familiar', 'companion', 'follower'].includes(explicitKind)) {
      return explicitKind;
    }
    const roleKind = String(
      metadata?.role
      || metadata?.bond_contract?.role
      || entity?.dcStatePayload?.metadata?.role
      || entity?.dcStatePayload?.metadata?.occupation
      || entity?.dcStatePayload?.role
      || entity?.dcStatePayload?.state?.role
      || entity?.dcStatePayload?.content_id
      || entity?.dcEntityPayload?.content_id
      || entity?.dcEntityPayload?.role
      || entity?.dcEntityPayload?.state?.role
      || ''
    ).trim().toLowerCase();
    if (roleKind.includes('familiar')) {
      return 'familiar';
    }
    if (roleKind.includes('companion')) {
      return 'companion';
    }
    if (roleKind.includes('follower')) {
      return 'follower';
    }
    return '';
  }

  resolveEntityOwnerCharacterId(entity = null) {
    const metadata = this.resolveEntityMetadata(entity);
    const explicitOwnerId = Number(metadata?.owner_character_id || metadata?.bond_contract?.owner_character_id || 0) || 0;
    if (explicitOwnerId > 0) {
      return explicitOwnerId;
    }
    const fallbackCharacterId = this.resolveEntityCharacterId(entity);
    if (fallbackCharacterId > 0 && this.resolveEntityFollowerKind(entity) !== '') {
      return fallbackCharacterId;
    }
    const entityRef = this.resolveEntityRef(entity);
    const refMatch = entityRef.match(/^(?:familiar|follower|companion)-(\d+)$/i);
    if (refMatch) {
      return Number(refMatch[1] || 0) || 0;
    }
    return 0;
  }

  resolveCurrentCharacterId() {
    const contextCharacterId = Number(
      this.primaryCharacterContext?.sheetCharacterId
      || this.currentCharacterContext?.sheetCharacterId
      || 0
    ) || 0;
    if (contextCharacterId > 0) {
      return contextCharacterId;
    }
    const primaryState = this.primaryLaunchCharacter?.data || this.primaryLaunchCharacter || null;
    const primaryCharacterId = Number(
      primaryState?.sheet_character_id
      || primaryState?.character_id
      || this.primaryLaunchCharacter?.sheet_character_id
      || this.primaryLaunchCharacter?.character_id
      || this.primaryLaunchCharacter?.characterId
      || this.primaryLaunchCharacter?.id
      || 0
    ) || 0;
    if (primaryCharacterId > 0) {
      return primaryCharacterId;
    }
    const launchPlayerEntity = this.stateManager?.hexmap?.findLaunchPlayerEntity?.() || null;
    return this.resolveEntityCharacterId(launchPlayerEntity);
  }

  extractFollowerRosterFromLaunchCharacter(launchCharacter = null) {
    if (!launchCharacter || typeof launchCharacter !== 'object') {
      return [];
    }
    const state = launchCharacter.data || launchCharacter;
    const roster = state?.followers ?? launchCharacter?.followers ?? [];
    return Array.isArray(roster)
      ? roster.filter((entry) => entry && typeof entry === 'object')
      : [];
  }

  resolvePrimaryFollowerRoster() {
    const candidates = [
      this.primaryLaunchCharacter,
      this.stateManager?.hexmap?.launchCharacter,
      this.stateManager?.hexmap?.characterData,
    ];
    for (const candidate of candidates) {
      const roster = this.extractFollowerRosterFromLaunchCharacter(candidate);
      if (roster.length > 0) {
        return roster;
      }
    }
    return [];
  }

  hydrateLaunchCharacterWithPrimaryFollowerRoster(launchCharacter = null) {
    if (!launchCharacter || typeof launchCharacter !== 'object') {
      return launchCharacter;
    }
    const incomingRoster = this.extractFollowerRosterFromLaunchCharacter(launchCharacter);
    if (incomingRoster.length > 0) {
      return launchCharacter;
    }
    const existingPrimaryRoster = this.resolvePrimaryFollowerRoster();
    if (existingPrimaryRoster.length === 0) {
      return launchCharacter;
    }
    const incomingState = launchCharacter.data && typeof launchCharacter.data === 'object'
      ? launchCharacter.data
      : null;
    return incomingState
      ? { ...launchCharacter, data: { ...incomingState, followers: existingPrimaryRoster } }
      : { ...launchCharacter, followers: existingPrimaryRoster };
  }

  consumeLaunchCharacterUpdate(launchCharacter = null) {
    if (!launchCharacter || typeof launchCharacter !== 'object') {
      return;
    }
    const hydratedLaunchCharacter = this.hydrateLaunchCharacterWithPrimaryFollowerRoster(launchCharacter);
    this.primaryLaunchCharacter = hydratedLaunchCharacter;
    if (this._el.partyActorSelect) {
      this.applyPartyFollowerSelectionToCharacterSheet();
      return;
    }
    this.showLaunchCharacter(hydratedLaunchCharacter);
  }

  resolveFollowerRosterEntryByRef(actorRef = '') {
    const normalizedRef = String(actorRef || '').trim();
    if (!normalizedRef) {
      return null;
    }
    return this.resolvePrimaryFollowerRoster().find((entry) => String(
      entry?.runtime_entity_id
      || entry?.instance_id
      || entry?.entity_instance_id
      || ''
    ).trim() === normalizedRef) || null;
  }

  resolveFollowerEntityFromRosterEntry(followerEntry = null) {
    if (!followerEntry || typeof followerEntry !== 'object') {
      return null;
    }

    const runtimeRef = String(
      followerEntry?.runtime_entity_id
      || followerEntry?.instance_id
      || followerEntry?.entity_instance_id
      || ''
    ).trim();
    if (runtimeRef) {
      const direct = this.resolveEntityByRef(runtimeRef);
      if (direct) {
        return direct;
      }
    }

    const followerCharacterId = Number(followerEntry?.follower_character_id || 0) || 0;
    if (followerCharacterId <= 0) {
      return null;
    }
    const entities = this.stateManager?.hexmap?.entityManager?.getEntitiesWith?.('PositionComponent') || [];
    return entities.find((entity) => {
      const metadata = this.resolveEntityMetadata(entity);
      const candidateIds = [
        this.resolveEntityCharacterId(entity),
        Number(metadata?.character_id || 0) || 0,
        Number(metadata?.campaign_character_id || 0) || 0,
        Number(metadata?.follower_character_id || 0) || 0,
      ].filter((value) => value > 0);
      return candidateIds.includes(followerCharacterId);
    }) || null;
  }

  resolveOccupantMetadata(occupant = null) {
    const metadata = occupant?.metadata
      || occupant?.state?.metadata
      || occupant?.state_payload?.metadata
      || null;
    return metadata && typeof metadata === 'object' ? metadata : {};
  }

  resolveOccupantFollowerKind(occupant = null) {
    const metadata = this.resolveOccupantMetadata(occupant);
    const explicitKind = String(
      metadata?.follower_kind
      || metadata?.bond_contract?.follower_kind
      || occupant?.follower_kind
      || ''
    ).trim().toLowerCase();
    if (explicitKind) {
      return explicitKind;
    }
    const roleKind = String(
      metadata?.role
      || metadata?.bond_contract?.role
      || occupant?.presentation?.role
      || occupant?.role
      || occupant?.state?.role
      || occupant?.content_id
      || ''
    ).trim().toLowerCase();
    if (roleKind.includes('familiar')) {
      return 'familiar';
    }
    if (roleKind.includes('companion')) {
      return 'companion';
    }
    if (roleKind.includes('follower')) {
      return 'follower';
    }
    const occupantRef = String(occupant?.occupant_id || '').trim().toLowerCase();
    if (occupantRef.startsWith('familiar-')) {
      return 'familiar';
    }
    if (occupantRef.startsWith('companion-')) {
      return 'companion';
    }
    if (occupantRef.startsWith('follower-')) {
      return 'follower';
    }
    return '';
  }

  resolveOccupantOwnerCharacterId(occupant = null) {
    const metadata = this.resolveOccupantMetadata(occupant);
    const explicitOwnerId = Number(
      metadata?.owner_character_id
      || metadata?.bond_contract?.owner_character_id
      || occupant?.owner_character_id
      || occupant?.state?.owner_character_id
      || 0
    ) || 0;
    if (explicitOwnerId > 0) {
      return explicitOwnerId;
    }
    const occupantRef = String(occupant?.occupant_id || '').trim();
    if (occupantRef) {
      const linkedEntity = this.resolveEntityByRef(occupantRef);
      const linkedEntityOwnerId = this.resolveEntityOwnerCharacterId(linkedEntity);
      if (linkedEntityOwnerId > 0) {
        return linkedEntityOwnerId;
      }
    }
    const fallbackCharacterId = Number(occupant?.character_id || occupant?.state?.character_id || 0) || 0;
    if (fallbackCharacterId > 0 && this.resolveOccupantFollowerKind(occupant) !== '') {
      return fallbackCharacterId;
    }
    const refMatch = occupantRef.match(/^(?:familiar|follower|companion)-(\d+)$/i);
    if (refMatch) {
      return Number(refMatch[1] || 0) || 0;
    }
    return 0;
  }

  isFollowerOccupantForCurrentCharacter(occupant = null, currentCharacterId = 0) {
    if ((Number(currentCharacterId || 0) || 0) <= 0 || !occupant) {
      return false;
    }
    const ownerCharacterId = this.resolveOccupantOwnerCharacterId(occupant);
    if (ownerCharacterId !== currentCharacterId) {
      return false;
    }
    const followerKind = this.resolveOccupantFollowerKind(occupant);
    return followerKind !== '';
  }

  isFollowerEntityForCurrentCharacter(entity = null, currentCharacterId = 0) {
    if ((Number(currentCharacterId || 0) || 0) <= 0 || !entity) {
      return false;
    }
    const ownerCharacterId = this.resolveEntityOwnerCharacterId(entity);
    if (ownerCharacterId !== currentCharacterId) {
      return false;
    }
    const followerKind = this.resolveEntityFollowerKind(entity);
    return followerKind !== '';
  }

  buildCharacterSheetHref(characterId, followerKind = '') {
    const normalizedCharacterId = Number(characterId || 0) || 0;
    if (normalizedCharacterId <= 0) {
      return '';
    }

    const normalizedFollowerKind = String(followerKind || '').trim().toLowerCase();
    const basePath = normalizedFollowerKind
      ? `/characters/${normalizedCharacterId}/followers/${encodeURIComponent(normalizedFollowerKind)}`
      : `/characters/${normalizedCharacterId}`;
    const campaignId = Number(this.currentCharacterContext?.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || 0) || 0;
    if (campaignId > 0) {
      return `${basePath}?campaign_id=${campaignId}`;
    }
    return basePath;
  }

  resolveSheetHrefForEntity(entity = null) {
    const selectedFollowerKind = this.resolveEntityFollowerKind(entity);
    const selectedFollowerOwnerId = this.resolveEntityOwnerCharacterId(entity);
    const selectedCharacterId = this.resolveEntityCharacterId(entity);
    const fallbackCharacterId = Number(this.currentCharacterContext?.sheetCharacterId || 0) || 0;
    const resolvedCharacterId = selectedFollowerOwnerId || selectedCharacterId || fallbackCharacterId;
    return this.buildCharacterSheetHref(resolvedCharacterId, selectedFollowerKind);
  }

  resolveCurrentCharacterSheetHref() {
    const sheetCharacterId = Number(this.currentCharacterContext?.sheetCharacterId || 0) || 0;
    return this.buildCharacterSheetHref(sheetCharacterId);
  }

  syncSheetLinksForSelectedEntity(entity = null) {
    this.syncCharacterSheetLinkForSelectedEntity(entity);
    this.syncPartySheetLinkForSelectedEntity(entity);
  }

  syncCharacterSheetLinkForSelectedEntity(entity = null) {
    const link = this._el.characterFullSheetLink;
    if (!link) {
      return;
    }

    let href = this.resolveCurrentCharacterSheetHref();
    const selectedOption = this._el.partyActorSelect?.selectedOptions?.[0] || null;
    const selectedActorKind = String(selectedOption?.dataset?.actorKind || '').trim().toLowerCase();
    if (selectedActorKind === 'follower') {
      const selectedEntity = entity || this.resolveSelectedFollowerEntityFromSelector();
      if (selectedEntity) {
        href = this.resolveSheetHrefForEntity(selectedEntity);
      } else {
        const selectedFollowerKind = String(selectedOption?.dataset?.followerKind || '').trim().toLowerCase();
        const selectedOwnerCharacterId = Number(selectedOption?.dataset?.ownerCharacterId || 0) || 0;
        href = this.buildCharacterSheetHref(selectedOwnerCharacterId, selectedFollowerKind);
      }
    }

    if (href) {
      link.href = href;
      link.style.display = '';
    } else {
      link.style.display = 'none';
    }
  }

  syncPartySheetLinkForSelectedEntity(entity = null) {
    const link = this._el.partyFullSheetLink;
    if (!link) {
      return;
    }
    const partySelect = this._el.partyActorSelect;
    const partySelectionRef = String(partySelect?.value || '').trim();
    const selectedOption = partySelect?.selectedOptions?.[0] || null;
    const selectedActorKind = String(selectedOption?.dataset?.actorKind || '').trim().toLowerCase();
    const selectedActorSide = this.normalizeActorSide(String(selectedOption?.dataset?.actorSide || '').trim());
    const selectedFollowerKindFromOption = String(selectedOption?.dataset?.followerKind || '').trim().toLowerCase();
    const selectedOwnerCharacterIdFromOption = Number(selectedOption?.dataset?.ownerCharacterId || 0) || 0;
    const selectedEntity = entity || this.resolveEntityByRef(partySelectionRef) || null;
    const preferredSheetHref = String(selectedOption?.dataset?.sheetHref || '').trim();
    const href = preferredSheetHref
      || (selectedEntity
        ? this.resolveSheetHrefForEntity(selectedEntity)
        : this.buildCharacterSheetHref(selectedOwnerCharacterIdFromOption, selectedFollowerKindFromOption));
    const selectedLabel = this.resolveEntityLabel(selectedEntity);
    const selectedFollowerKind = selectedEntity
      ? this.resolveEntityFollowerKind(selectedEntity)
      : selectedFollowerKindFromOption;
    const selectedFollowerKindLabel = selectedFollowerKind ? selectedFollowerKind.toUpperCase() : '';

    if (this._el.partySheetName) {
      const fallbackLabel = String(selectedOption?.textContent || '').replace(/\s+\([^)]+\)\s*$/, '').trim();
      this._el.partySheetName.textContent = href ? (selectedLabel || fallbackLabel || 'Follower') : 'Select a follower';
    }
    if (this._el.partySheetSubtitle) {
      if (selectedActorKind === 'actor') {
        this._el.partySheetSubtitle.textContent = `${this.formatActorSideLabel(selectedActorSide)} actor sheet`;
      } else if (selectedFollowerKindLabel) {
        this._el.partySheetSubtitle.textContent = `${selectedFollowerKindLabel} follower`;
      } else {
        this._el.partySheetSubtitle.textContent = 'Character sheet';
      }
    }
    if (href) {
      link.href = href;
      link.style.display = '';
      if (this._el.partySheetEmbed && this._el.partySheetEmbedWrap) {
        if (this._el.partySheetEmbed.getAttribute('src') !== href) {
          this._el.partySheetEmbed.setAttribute('src', href);
        }
        this._el.partySheetEmbedWrap.style.display = '';
      }
      if (this._el.partySheetEmpty) {
        this._el.partySheetEmpty.style.display = 'none';
      }
    } else {
      link.style.display = 'none';
      if (this._el.partySheetEmbed) {
        this._el.partySheetEmbed.removeAttribute('src');
      }
      if (this._el.partySheetEmbedWrap) {
        this._el.partySheetEmbedWrap.style.display = 'none';
      }
      if (this._el.partySheetEmpty) {
        this._el.partySheetEmpty.style.display = '';
      }
    }
  }

  refreshActorSelector() {
    const hexmap = this.stateManager?.hexmap || null;
    if (!hexmap) {
      return;
    }
    const selectorTargets = [
      { wrap: this._el.partyActorSelectWrap, select: this._el.partyActorSelect },
    ].filter((target) => target.wrap && target.select);
    if (selectorTargets.length === 0) {
      return;
    }

    const canonicalRosterOptions = this.buildActorOptionsFromCanonicalRoster();
    const allOptions = [];
    const seen = new Set();
    const primaryLaunchCharacter = this.primaryLaunchCharacter
      || this.stateManager?.hexmap?.launchCharacter
      || this.stateManager?.hexmap?.characterData
      || null;
    const primaryState = primaryLaunchCharacter?.data || primaryLaunchCharacter || {};
    const primaryCharacterId = Number(primaryState?.sheet_character_id || primaryState?.character_id || primaryState?.characterId || 0) || 0;
    const launchPlayerEntity = this.stateManager?.hexmap?.findLaunchPlayerEntity?.() || null;
    const launchPlayerRef = this.resolveEntityRef(launchPlayerEntity);
    const launchPlayerMetadata = this.resolveEntityMetadata(launchPlayerEntity);
    const primaryCampaignCharacterId = Number(
      launchPlayerMetadata?.campaign_character_id
      || launchPlayerEntity?.dcStatePayload?.campaign_character_id
      || launchPlayerEntity?.dcEntityPayload?.campaign_character_id
      || 0
    ) || 0;
    const primaryRefCandidates = new Set([
      launchPlayerRef,
      launchPlayerRef ? `runtime:${launchPlayerRef}` : '',
      primaryCampaignCharacterId > 0 ? `campaign-character:${primaryCampaignCharacterId}` : '',
      primaryCharacterId > 0 ? `character:${primaryCharacterId}` : '',
    ].filter(Boolean));
    const isPrimaryRosterDuplicate = (option = {}) => {
      const actorKind = String(option?.actorKind || '').trim().toLowerCase();
      if (actorKind && actorKind !== 'actor') {
        return false;
      }
      const actorSide = this.normalizeActorSide(option?.actorSide || 'neutral');
      if (actorSide !== 'party') {
        return false;
      }
      const optionValue = String(option?.value || '').trim();
      const optionOwnerCharacterId = Number(option?.ownerCharacterId || 0) || 0;
      const optionFollowerCharacterId = Number(option?.followerCharacterId || 0) || 0;
      if (primaryCharacterId > 0 && (optionOwnerCharacterId === primaryCharacterId || optionFollowerCharacterId === primaryCharacterId)) {
        return true;
      }
      if (optionValue && primaryRefCandidates.has(optionValue)) {
        return true;
      }
      return false;
    };
    const primaryLabel = String(
      primaryState?.basicInfo?.name
      || primaryState?.name
      || this.resolveEntityLabel(launchPlayerEntity)
      || 'Main character'
    ).trim() || 'Main character';
    allOptions.push({
      value: '__primary__',
      label: `${primaryLabel} (PC)`,
      actorKind: 'primary',
      actorSide: 'party',
      ownerCharacterId: primaryCharacterId,
      followerKind: '',
      followerCharacterId: primaryCharacterId,
      sortInitiative: Number.isFinite(Number(
        launchPlayerEntity?.getComponent?.('CombatComponent')?.getInitiative?.()
        ?? launchPlayerMetadata?.initiative
      )) ? Number(
        launchPlayerEntity?.getComponent?.('CombatComponent')?.getInitiative?.()
        ?? launchPlayerMetadata?.initiative
      ) : null,
      sortIsParticipant: Number.isFinite(Number(
        launchPlayerEntity?.getComponent?.('CombatComponent')?.getInitiative?.()
        ?? launchPlayerMetadata?.initiative
      )),
      sortIsCurrent: Boolean(launchPlayerMetadata?.is_current_turn),
    });
    seen.add('__primary__');

    canonicalRosterOptions.forEach((option) => {
      if (!option?.value || seen.has(option.value) || isPrimaryRosterDuplicate(option)) {
        return;
      }
      allOptions.push(option);
      seen.add(option.value);
    });

    const [primaryOption, ...restOptions] = allOptions;
    const primaryIncluded = this.activeActorFilter === 'all' || this.activeActorFilter === 'party';
    const filteredOptions = restOptions.filter((option) => {
      const side = this.normalizeActorSide(option?.actorSide || 'neutral');
      if (this.activeActorFilter === 'all') {
        return true;
      }
      return side === this.activeActorFilter;
    });
    const resolvedOptions = this.sortActorOptions([
      ...(primaryIncluded && primaryOption ? [primaryOption] : []),
      ...filteredOptions,
    ]);
    this.syncActorFilterButtons(allOptions);
    this.syncActorSortControl();

    if (resolvedOptions.length === 0) {
      this._suppressActorSelectorChange = true;
      selectorTargets.forEach(({ wrap, select }) => {
        select.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'No characters available';
        select.appendChild(placeholder);
        select.disabled = true;
        wrap.style.display = '';
      });
      this._suppressActorSelectorChange = false;
      this.syncSheetLinksForSelectedEntity();
      this.syncPartySelectorVisibility();
      return;
    }

    const selectedValue = String(this._el.partyActorSelect?.value || '').trim();
    const preferredValue = [selectedValue, resolvedOptions[0]?.value]
      .find((candidate) => candidate && resolvedOptions.some((option) => option.value === candidate)) || '';

    this._suppressActorSelectorChange = true;
    selectorTargets.forEach(({ wrap, select }) => {
      select.innerHTML = '';
      resolvedOptions.forEach((option) => {
        const el = document.createElement('option');
        el.value = option.value;
        el.textContent = option.label;
        if (option.actorKind) {
          el.dataset.actorKind = String(option.actorKind);
        }
        if (option.actorSide) {
          el.dataset.actorSide = String(option.actorSide);
        }
        if (option.ownerCharacterId > 0) {
          el.dataset.ownerCharacterId = String(option.ownerCharacterId);
        }
        if (option.followerKind) {
          el.dataset.followerKind = String(option.followerKind);
        }
        if (option.followerCharacterId > 0) {
          el.dataset.followerCharacterId = String(option.followerCharacterId);
        }
        if (option.sheetHref) {
          el.dataset.sheetHref = String(option.sheetHref);
        }
        select.appendChild(el);
      });
      select.disabled = false;
      select.value = preferredValue;
      wrap.style.display = '';
    });
    this._suppressActorSelectorChange = false;
    this.syncSheetLinksForSelectedEntity();
    this.syncPartySelectorVisibility();
    if (this.isRelationshipsTabActive()) {
      this.renderRelationshipsMatrix();
    }
  }

  syncPartySelectorVisibility() {
    if (!this._el.partyActorSelectWrap) {
      return;
    }
    this._el.partyActorSelectWrap.style.display = '';
  }

  isRelationshipsTabActive() {
    const partyPanel = this.container?.closest('#game-panel-party');
    if (!partyPanel || partyPanel.hidden || !partyPanel.classList.contains('game-shell__panel--active')) {
      return false;
    }
    const activeTab = partyPanel.querySelector('[data-sidebar-tab].sidebar-tab--active');
    return String(activeTab?.dataset?.sidebarTab || '').trim().toLowerCase() === 'relationships';
  }

  resolveRelationshipMatrixActors() {
    const actors = [];
    const seen = new Set();
    const canonicalRosterOptions = this.buildActorOptionsFromCanonicalRoster();
    canonicalRosterOptions.forEach((option) => {
      const actorRef = String(option?.value || '').trim();
      if (!actorRef || seen.has(actorRef)) {
        return;
      }
      seen.add(actorRef);
      const displayName = String(option?.label || actorRef).replace(/\s+\([^)]+\)\s*$/, '').trim() || actorRef;
      const linkedEntity = this.resolveEntityByRef(actorRef);
      const resolvedSide = linkedEntity ? this.resolveActorSideForEntity(linkedEntity) : this.normalizeActorSide(option?.actorSide || 'neutral');
      actors.push({
        actorRef,
        displayName,
        actorSide: resolvedSide,
      });
    });

    const launchPlayerEntity = this.stateManager?.hexmap?.findLaunchPlayerEntity?.() || null;
    const launchPlayerRef = this.resolveEntityRef(launchPlayerEntity);
    if (launchPlayerRef && !seen.has(launchPlayerRef)) {
      actors.unshift({
        actorRef: launchPlayerRef,
        displayName: this.resolveEntityLabel(launchPlayerEntity),
        actorSide: 'party',
      });
    }

    return actors;
  }

  canUseServerRelationshipActorRef(actorRef = '') {
    const normalized = String(actorRef || '').trim().toLowerCase();
    if (!normalized) {
      return false;
    }
    return /^pc-\d+-\d+$/.test(normalized)
      || /^npc[_-][a-z0-9_-]+$/.test(normalized);
  }

  resolveSelectedRelationshipSourceRef(actors = [], preferredSourceRef = '') {
    const actorRefs = new Set((Array.isArray(actors) ? actors : []).map((actor) => String(actor?.actorRef || '').trim()).filter(Boolean));
    const preferred = String(preferredSourceRef || '').trim();
    if (preferred && actorRefs.has(preferred)) {
      return preferred;
    }

    const selectedValue = String(this._el.partyActorSelect?.value || '').trim();
    if (selectedValue === '__primary__') {
      const launchPlayerRef = this.resolveEntityRef(this.stateManager?.hexmap?.findLaunchPlayerEntity?.() || null);
      if (launchPlayerRef && actorRefs.has(launchPlayerRef)) {
        return launchPlayerRef;
      }
    } else if (selectedValue && actorRefs.has(selectedValue)) {
      return selectedValue;
    }

    return String(actors?.[0]?.actorRef || '').trim();
  }

  formatRelationshipAttitudeLabel(attitude = '') {
    const normalized = String(attitude || '').trim().toLowerCase();
    if (!normalized) {
      return '—';
    }
    return normalized.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
  }

  renderRelationshipsMatrixTable(actors = [], matrix = {}, calculations = {}, selectedSourceRef = '') {
    const container = this._el.actorRelationshipsMatrix;
    if (!container) {
      return;
    }
    const scrollStyle = 'overflow:auto;border:1px solid rgba(148,163,184,0.35);border-radius:10px;background:rgba(15,23,42,0.35);';
    const tableStyle = 'width:100%;min-width:640px;border-collapse:collapse;table-layout:fixed;';
    const thStyle = 'border:1px solid rgba(148,163,184,0.35);padding:8px 10px;font-size:12px;text-align:center;background:rgba(148,163,184,0.15);font-weight:700;white-space:nowrap;';
    const rowHeaderStyle = 'border:1px solid rgba(148,163,184,0.35);padding:8px 10px;font-size:12px;text-align:left;background:rgba(148,163,184,0.15);font-weight:700;white-space:nowrap;';
    const tdBaseStyle = 'border:1px solid rgba(148,163,184,0.35);padding:8px 10px;font-size:12px;text-align:center;vertical-align:middle;background:rgba(15,23,42,0.45);';
    const badgeBaseStyle = 'display:inline-flex;align-items:center;justify-content:center;min-width:88px;padding:3px 8px;border-radius:9999px;border:1px solid rgba(148,163,184,0.45);font-size:11px;font-weight:700;color:#e2e8f0;';
    const badgeStyleByAttitude = {
      hostile: `${badgeBaseStyle}background:rgba(239,68,68,0.24);border-color:rgba(239,68,68,0.6);`,
      unfriendly: `${badgeBaseStyle}background:rgba(245,158,11,0.22);border-color:rgba(245,158,11,0.6);`,
      indifferent: `${badgeBaseStyle}background:rgba(148,163,184,0.2);border-color:rgba(148,163,184,0.6);`,
      friendly: `${badgeBaseStyle}background:rgba(16,185,129,0.2);border-color:rgba(16,185,129,0.55);`,
      helpful: `${badgeBaseStyle}background:rgba(16,185,129,0.28);border-color:rgba(16,185,129,0.7);`,
      unknown: `${badgeBaseStyle}background:rgba(71,85,105,0.2);border-color:rgba(148,163,184,0.45);`,
    };
    const stanceStyleByValue = {
      aggressive_engage: `${badgeBaseStyle}background:rgba(239,68,68,0.24);border-color:rgba(239,68,68,0.6);`,
      finish_weakest: `${badgeBaseStyle}background:rgba(239,68,68,0.32);border-color:rgba(239,68,68,0.72);`,
      threaten: `${badgeBaseStyle}background:rgba(245,158,11,0.24);border-color:rgba(245,158,11,0.62);`,
      warn: `${badgeBaseStyle}background:rgba(250,204,21,0.2);border-color:rgba(250,204,21,0.6);`,
      engage_dialogue: `${badgeBaseStyle}background:rgba(16,185,129,0.24);border-color:rgba(16,185,129,0.62);`,
      deescalate: `${badgeBaseStyle}background:rgba(34,197,94,0.2);border-color:rgba(34,197,94,0.6);`,
      observe: `${badgeBaseStyle}background:rgba(148,163,184,0.2);border-color:rgba(148,163,184,0.6);`,
      self_preserve: `${badgeBaseStyle}background:rgba(59,130,246,0.2);border-color:rgba(96,165,250,0.62);`,
      flee: `${badgeBaseStyle}background:rgba(79,70,229,0.24);border-color:rgba(129,140,248,0.62);`,
      pass: `${badgeBaseStyle}background:rgba(100,116,139,0.2);border-color:rgba(148,163,184,0.5);`,
      unknown: `${badgeBaseStyle}background:rgba(71,85,105,0.2);border-color:rgba(148,163,184,0.45);`,
    };
    const selectedSource = this.resolveSelectedRelationshipSourceRef(actors, selectedSourceRef);
    const selectedSourceActor = actors.find((actor) => actor.actorRef === selectedSource) || null;
    const selectedSourceLabel = String(selectedSourceActor?.displayName || selectedSource || '').trim() || 'Selected actor';
    const calculationRows = actors
      .filter(({ actorRef }) => actorRef !== selectedSource)
      .map(({ actorRef: targetRef, displayName: targetDisplay }) => {
        const calculation = (calculations?.[selectedSource]?.[targetRef] && typeof calculations[selectedSource][targetRef] === 'object')
          ? calculations[selectedSource][targetRef]
          : {};
        const fallbackAttitude = this.normalizeAttitudeValue(String(matrix?.[selectedSource]?.[targetRef] || ''));
        const finalAttitude = this.normalizeAttitudeValue(String(calculation?.final_attitude || fallbackAttitude));
        const sourceDefault = this.normalizeAttitudeValue(String(calculation?.source_default_attitude || ''));
        const edgeAttitude = this.normalizeAttitudeValue(String(calculation?.edge_attitude || ''));
        const rule = String(calculation?.rule || '').trim();
        const formula = String(calculation?.formula || '').trim()
          || 'final_score = clamp((w_default*source_default_score) + (w_edge*edge_score_or_0) + (w_inst*institution_score), -100, 100)';
        const equation = String(calculation?.equation || '').trim()
          || `formula(${formula})`;
        const sourceDefaultScore = Number.isFinite(Number(calculation?.source_default_score))
          ? Number(calculation.source_default_score)
          : this.resolveAttitudeScore(sourceDefault);
        const edgeScoreHasValue = calculation?.edge_score !== null
          && calculation?.edge_score !== undefined
          && Number.isFinite(Number(calculation.edge_score));
        const edgeScore = edgeScoreHasValue
          ? Number(calculation.edge_score)
          : (edgeAttitude ? this.resolveAttitudeScore(edgeAttitude) : null);
        const finalScore = Number.isFinite(Number(calculation?.final_score))
          ? Number(calculation.final_score)
          : this.resolveAttitudeScore(finalAttitude);
        const stance = this.normalizeStanceValue(String(calculation?.stance || this.deriveFallbackStanceFromDisposition(finalScore, finalAttitude)));
        const stanceLabel = this.formatRelationshipAttitudeLabel(stance);
        const stanceConfidence = Number.isFinite(Number(calculation?.stance_confidence))
          ? Number(calculation.stance_confidence)
          : 0;
        const stanceMode = String(calculation?.stance_mode || 'combat_entry').trim() || 'combat_entry';
        const stanceReason = String(calculation?.stance_reason || '').trim()
          || (stance === 'threaten'
            ? 'Hostile disposition signal exists against target.'
            : 'No hostile trigger crossed for this target.');
        const stanceFormula = String(calculation?.stance_formula || '').trim()
          || `stance = if hostile_signal(${finalScore}) then threaten else observe`;
        const stanceTargetRef = String(calculation?.stance_target_actor_ref || targetRef).trim() || targetRef;
        const stancePolicyFlags = (calculation?.stance_policy_flags && typeof calculation.stance_policy_flags === 'object')
          ? calculation.stance_policy_flags
          : {};
        const stancePolicyLine = Object.keys(stancePolicyFlags).length > 0
          ? Object.entries(stancePolicyFlags).map(([key, value]) => `${key}=${value ? 'yes' : 'no'}`).join(', ')
          : 'policy_flags unavailable';
        const institutionScore = Number.isFinite(Number(calculation?.institution_score))
          ? Number(calculation.institution_score)
          : 0;
        const institutionWeightedScore = Number.isFinite(Number(calculation?.institution_weighted_score))
          ? Number(calculation.institution_weighted_score)
          : 0;
        const weights = (calculation?.weights && typeof calculation.weights === 'object')
          ? calculation.weights
          : {};
        const wDefault = Number.isFinite(Number(weights?.default))
          ? Number(weights.default)
          : (Number.isFinite(Number(weights?.baseline)) ? Number(weights.baseline) : 0);
        const wEdge = Number.isFinite(Number(weights?.edge))
          ? Number(weights.edge)
          : (Number.isFinite(Number(weights?.relationship)) ? Number(weights.relationship) : 0);
        const wInstitution = Number.isFinite(Number(weights?.institution)) ? Number(weights.institution) : 0;
        const institutionBreakdownRaw = (calculation?.institution_breakdown && typeof calculation.institution_breakdown === 'object')
          ? calculation.institution_breakdown
          : null;
        const actorSentimentBreakdown = Array.isArray(institutionBreakdownRaw?.actor_sentiment)
          ? institutionBreakdownRaw.actor_sentiment
          : [];
        const institutionMatrixBreakdown = Array.isArray(institutionBreakdownRaw?.institution_matrix)
          ? institutionBreakdownRaw.institution_matrix
          : [];
        const legacyBreakdown = Array.isArray(institutionBreakdownRaw)
          ? institutionBreakdownRaw
          : [];
        const institutionBreakdown = legacyBreakdown.length > 0
          ? legacyBreakdown
          : [...actorSentimentBreakdown, ...institutionMatrixBreakdown];
        const hasInstitutionMembershipData = actorSentimentBreakdown.length > 0 || institutionMatrixBreakdown.length > 0;
        const institutionSummaryLine = hasInstitutionMembershipData
          ? `institution memberships: sentiment=${actorSentimentBreakdown.length}, matrix_edges=${institutionMatrixBreakdown.length}, net=${institutionScore}`
          : '';
        const institutionBreakdownMarkup = institutionBreakdown.length > 0
          ? institutionBreakdown.slice(0, 8).map((entry) => {
            const isMatrixEdge = String(entry?.source || '').trim() === 'institution_matrix_edge'
              || (entry?.source_subject_id && entry?.target_subject_id);
            const weightedComponent = Number.isFinite(Number(entry?.weighted_component)) ? Number(entry.weighted_component) : 0;
            const rawScore = Number.isFinite(Number(entry?.raw_score)) ? Number(entry.raw_score) : 0;
            if (isMatrixEdge) {
              const sourceSubject = String(entry?.source_subject_id || 'source').trim();
              const targetSubject = String(entry?.target_subject_id || 'target').trim();
              const sourceWeight = Number.isFinite(Number(entry?.source_weight)) ? Number(entry.source_weight) : 0;
              const targetWeight = Number.isFinite(Number(entry?.target_weight)) ? Number(entry.target_weight) : 0;
              const confidenceWeight = Number.isFinite(Number(entry?.matrix_confidence_weight)) ? Number(entry.matrix_confidence_weight) : 0;
              return `<div style="color:#94a3b8;">matrix(${escapeQuestHtml(sourceSubject)} → ${escapeQuestHtml(targetSubject)}): ${rawScore} × ${sourceWeight.toFixed(2)} × ${targetWeight.toFixed(2)} × ${confidenceWeight.toFixed(2)} = ${weightedComponent}</div>`;
            }
            const instName = String(entry?.institution_name || entry?.institution_subject_id || 'Institution').trim();
            const domain = String(entry?.sentiment_domain || entry?.domain || 'unknown').trim();
            const domainWeight = Number.isFinite(Number(entry?.domain_weight)) ? Number(entry.domain_weight) : 0;
            const knowledgeWeight = Number.isFinite(Number(entry?.knowledge_weight)) ? Number(entry.knowledge_weight) : 0;
            return `<div style="color:#94a3b8;">sentiment(${escapeQuestHtml(instName)}|${escapeQuestHtml(domain)}): ${rawScore} × ${domainWeight.toFixed(2)} × ${knowledgeWeight.toFixed(2)} = ${weightedComponent}</div>`;
          }).join('')
          : '<div style="color:#94a3b8;">No institutional membership/sentiment adjustments found.</div>';
        const finalLabel = this.formatRelationshipAttitudeLabel(finalAttitude);
        const detailLabel = rule === 'relationship_edge_override'
          ? 'Edge override'
          : (rule === 'source_default' ? 'Source default' : (rule === 'inferred_from_sides' ? 'Side inference' : (rule === 'weighted_edge_plus_institutions' ? 'Weighted formula (edge + institutions)' : (rule === 'weighted_default_plus_institutions' ? 'Weighted formula (default + institutions)' : 'Computed'))));
        const resolverSnapshot = (calculation?.resolver_snapshot && typeof calculation.resolver_snapshot === 'object')
          ? calculation.resolver_snapshot
          : {};
        const resolverComponents = (resolverSnapshot?.components && typeof resolverSnapshot.components === 'object')
          ? resolverSnapshot.components
          : {};
        const resolverWeights = (resolverSnapshot?.weights && typeof resolverSnapshot.weights === 'object')
          ? resolverSnapshot.weights
          : {};
        const weightedItems = [
          {
            key: 'actor_baseline_score',
            label: 'actor_baseline_score',
            raw: Number.isFinite(Number(resolverComponents?.actor_baseline_score))
              ? Number(resolverComponents.actor_baseline_score)
              : sourceDefaultScore,
            weight: Number.isFinite(Number(resolverWeights?.baseline))
              ? Number(resolverWeights.baseline)
              : wDefault,
            source: 'ActorDispositionService current_score',
          },
          {
            key: 'relationship_score',
            label: 'relationship_score',
            raw: Number.isFinite(Number(resolverComponents?.relationship_score))
              ? Number(resolverComponents.relationship_score)
              : (edgeScore ?? 0),
            weight: Number.isFinite(Number(resolverWeights?.relationship))
              ? Number(resolverWeights.relationship)
              : wEdge,
            source: `RelationshipAttitudeService edge_score (${calculation?.edge_score_source || 'none'})`,
          },
          {
            key: 'situational_score',
            label: 'situational_score',
            raw: Number.isFinite(Number(resolverComponents?.situational_score))
              ? Number(resolverComponents.situational_score)
              : 0,
            weight: Number.isFinite(Number(resolverWeights?.situational))
              ? Number(resolverWeights.situational)
              : 0,
            source: 'DispositionSceneContextService',
          },
          {
            key: 'institution_score',
            label: 'institution_score',
            raw: Number.isFinite(Number(resolverComponents?.institution_score))
              ? Number(resolverComponents.institution_score)
              : institutionScore,
            weight: Number.isFinite(Number(resolverWeights?.institution))
              ? Number(resolverWeights.institution)
              : wInstitution,
            source: 'InstitutionDispositionScoreAssemblerService',
          },
          {
            key: 'recent_harm_score',
            label: 'recent_harm_score',
            raw: Number.isFinite(Number(resolverComponents?.recent_harm_score))
              ? Number(resolverComponents.recent_harm_score)
              : 0,
            weight: Number.isFinite(Number(resolverWeights?.recent_harm))
              ? Number(resolverWeights.recent_harm)
              : 0,
            source: 'DispositionSceneContextService',
          },
          {
            key: 'recent_help_score',
            label: 'recent_help_score',
            raw: Number.isFinite(Number(resolverComponents?.recent_help_score))
              ? Number(resolverComponents.recent_help_score)
              : 0,
            weight: Number.isFinite(Number(resolverWeights?.recent_help))
              ? Number(resolverWeights.recent_help)
              : 0,
            source: 'DispositionSceneContextService',
          },
          {
            key: 'coercion_score',
            label: 'coercion_score',
            raw: Number.isFinite(Number(resolverComponents?.coercion_score))
              ? Number(resolverComponents.coercion_score)
              : 0,
            weight: Number.isFinite(Number(resolverWeights?.coercion))
              ? Number(resolverWeights.coercion)
              : 0,
            source: 'DispositionSceneContextService',
          },
          {
            key: 'recent_impulse_score',
            label: 'recent_impulse_score',
            raw: Number.isFinite(Number(resolverComponents?.recent_impulse_score))
              ? Number(resolverComponents.recent_impulse_score)
              : 0,
            weight: Number.isFinite(Number(resolverWeights?.recent_impulse))
              ? Number(resolverWeights.recent_impulse)
              : 0,
            source: 'DispositionSceneContextService',
          },
        ];
        const weightedSum = weightedItems.reduce((sum, item) => sum + (Number(item.raw || 0) * Number(item.weight || 0)), 0);
        const dispositionRowsMarkup = weightedItems.map((item) => {
          const contribution = Number(item.raw || 0) * Number(item.weight || 0);
          return `
            <tr>
              <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);color:#e2e8f0;">${escapeQuestHtml(item.label)}</td>
              <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#cbd5e1;">${Number(item.raw || 0)}</td>
              <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#cbd5e1;">${Number(item.weight || 0).toFixed(2)}</td>
              <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#cbd5e1;">${contribution.toFixed(2)}</td>
              <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);color:#94a3b8;">${escapeQuestHtml(item.source)}</td>
            </tr>
          `;
        }).join('');
        const dispositionMetaRowsMarkup = [
          ['source_default_attitude', sourceDefault || 'unknown', 'ActorDispositionService current_attitude'],
          ['edge_attitude', edgeAttitude || 'none', 'dc_campaign_relationships.attitude'],
          ['relationship_type', String(calculation?.relationship_type || 'none'), 'dc_campaign_relationships.relationship_type'],
          ['relationship_status', String(calculation?.relationship_status || 'none'), 'dc_campaign_relationships.status'],
        ].map(([variable, value, source]) => `
          <tr>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);color:#e2e8f0;">${escapeQuestHtml(variable)}</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#cbd5e1;">${escapeQuestHtml(String(value))}</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#64748b;">n/a</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#64748b;">n/a</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);color:#94a3b8;">${escapeQuestHtml(source)}</td>
          </tr>
        `).join('');
        const stanceBasis = (calculation?.stance_basis && typeof calculation.stance_basis === 'object')
          ? calculation.stance_basis
          : {};
        const stanceProfile = (stanceBasis?.profile && typeof stanceBasis.profile === 'object') ? stanceBasis.profile : {};
        const stanceResolved = (stanceBasis?.resolved_disposition && typeof stanceBasis.resolved_disposition === 'object') ? stanceBasis.resolved_disposition : {};
        const stanceAggression = (stanceBasis?.aggression && typeof stanceBasis.aggression === 'object') ? stanceBasis.aggression : {};
        const stanceSurvival = (stanceBasis?.survival && typeof stanceBasis.survival === 'object') ? stanceBasis.survival : {};
        const stanceNarrative = (stanceBasis?.narrative && typeof stanceBasis.narrative === 'object') ? stanceBasis.narrative : {};
        const stanceRows = [
          ['profile.attitude', String(stanceProfile?.attitude || sourceDefault || 'unknown'), 'ActorDispositionService current_attitude'],
          ['profile.score', String(stanceProfile?.score ?? sourceDefaultScore), 'ActorDispositionService current_score'],
          ['profile.score_source', String(stanceProfile?.score_source || 'unknown'), 'ActorDispositionService resolveScoreSource()'],
          ['resolved.primary_target_score', String(stanceResolved?.primary_target_score ?? finalScore), 'DispositionResolverService effective_disposition_score'],
          ['aggression.threat_score', String(stanceAggression?.threat_score ?? 0), 'ActorStanceResolverService context/basis'],
          ['survival.hp_ratio', String(stanceSurvival?.hp_ratio ?? 1), 'ActorStanceResolverService context/basis'],
          ['survival.danger_level', String(stanceSurvival?.danger_level || 'none'), 'ActorStanceResolverService context/basis'],
          ['narrative.direct_addressed', String(stanceNarrative?.direct_addressed ?? false), 'ActorStanceResolverService context/basis'],
          ['policy_flags', stancePolicyLine, 'ActorStanceResolverService derivePolicyFlags()'],
          ['stance_total', `${stance || 'unknown'} (${stanceMode})`, 'ActorStanceResolverService evaluateStance()'],
        ];
        const stanceRowsMarkup = stanceRows.map(([variable, value, source]) => `
          <tr>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);color:#e2e8f0;">${escapeQuestHtml(String(variable))}</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#cbd5e1;">${escapeQuestHtml(String(value))}</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#64748b;">n/a</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);text-align:right;color:#64748b;">n/a</td>
            <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.25);color:#94a3b8;">${escapeQuestHtml(String(source))}</td>
          </tr>
        `).join('');
        const panelStyle = 'margin:4px 0;padding:6px 8px;border:1px solid rgba(148,163,184,0.25);border-radius:8px;background:rgba(15,23,42,0.35);';
        const summaryStyle = 'cursor:pointer;font-weight:700;color:#cbd5e1;list-style:none;';
        return `
          <tr>
            <th scope="row" rowspan="2" style="${rowHeaderStyle}vertical-align:top;">${escapeQuestHtml(targetDisplay)}</th>
            <td style="${tdBaseStyle}text-align:left;">
              <div style="font-weight:700;color:#cbd5e1;margin-bottom:4px;">Disposition</div>
              <div><span style="${badgeStyleByAttitude[finalAttitude] || badgeStyleByAttitude.unknown}">${escapeQuestHtml(finalLabel)} (score ${finalScore})</span></div>
              <details class="relationships-breakdown" data-breakdown-kind="disposition" style="${panelStyle}">
                <summary style="${summaryStyle}">Show disposition breakdown</summary>
                <div style="margin-top:6px;">
                  <div style="font-weight:700;color:#cbd5e1;">${escapeQuestHtml(detailLabel)}</div>
                  <div style="color:#cbd5e1;">${escapeQuestHtml(formula)}</div>
                  <div style="color:#94a3b8;">${escapeQuestHtml(equation)}</div>
                  <div style="overflow:auto;margin-top:6px;">
                    <table style="width:100%;border-collapse:collapse;font-size:11px;">
                      <thead>
                        <tr>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:left;color:#cbd5e1;">Variable</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#cbd5e1;">Raw</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#cbd5e1;">Weight</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#cbd5e1;">Contribution</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:left;color:#cbd5e1;">Source</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${dispositionRowsMarkup}
                        ${dispositionMetaRowsMarkup}
                        <tr>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);font-weight:700;color:#e2e8f0;">weighted_total</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#64748b;">n/a</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#64748b;">n/a</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;font-weight:700;color:#e2e8f0;">${weightedSum.toFixed(2)}</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);color:#94a3b8;">DispositionResolverService weighted sum</td>
                        </tr>
                        <tr>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);font-weight:700;color:#e2e8f0;">final_score</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;font-weight:700;color:#e2e8f0;">${finalScore}</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#64748b;">n/a</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#64748b;">n/a</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);color:#94a3b8;">DispositionResolverService effective_disposition_score</td>
                        </tr>
                        <tr>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);font-weight:700;color:#e2e8f0;">final_attitude</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;font-weight:700;color:#e2e8f0;">${escapeQuestHtml(finalAttitude)}</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#64748b;">n/a</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#64748b;">n/a</td>
                          <td style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);color:#94a3b8;">DispositionAuthorityContract scoreToAttitude()</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  ${institutionSummaryLine ? `<div style="color:#94a3b8;">${escapeQuestHtml(institutionSummaryLine)}</div>` : ''}
                  <div style="margin-top:4px;">${institutionBreakdownMarkup}</div>
                </div>
              </details>
            </td>
          </tr>
          <tr>
            <td style="${tdBaseStyle}text-align:left;">
              <div style="font-weight:700;color:#cbd5e1;margin-bottom:4px;">Stance</div>
              <div><span style="${stanceStyleByValue[stance] || stanceStyleByValue.unknown}">${escapeQuestHtml(stanceLabel)}${stanceConfidence > 0 ? ` (${stanceConfidence}%)` : ''}</span></div>
              <details class="relationships-breakdown" data-breakdown-kind="stance" style="${panelStyle}">
                <summary style="${summaryStyle}">Show stance breakdown</summary>
                <div style="margin-top:6px;">
                  <div style="color:#cbd5e1;">${escapeQuestHtml(stanceFormula)}</div>
                  <div style="color:#cbd5e1;">${escapeQuestHtml(`stance=${stance || 'unknown'} | mode=${stanceMode} | target=${stanceTargetRef}`)}</div>
                  <div style="color:#94a3b8;">${escapeQuestHtml(stanceReason)}</div>
                  <div style="overflow:auto;margin-top:6px;">
                    <table style="width:100%;border-collapse:collapse;font-size:11px;">
                      <thead>
                        <tr>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:left;color:#cbd5e1;">Variable</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#cbd5e1;">Value</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#cbd5e1;">Weight</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:right;color:#cbd5e1;">Contribution</th>
                          <th style="padding:4px 6px;border:1px solid rgba(148,163,184,0.35);text-align:left;color:#cbd5e1;">Source</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${stanceRowsMarkup}
                      </tbody>
                    </table>
                  </div>
                </div>
              </details>
            </td>
          </tr>
        `;
      })
      .join('');
    const calculationSummaryMarkup = selectedSource
      ? `
        <div class="relationships-calculation-summary" style="margin-top:10px;">
          <div style="font-size:12px;font-weight:700;color:#cbd5e1;margin-bottom:6px;">Disposition calculation: ${escapeQuestHtml(selectedSourceLabel)} → other room actors</div>
          <div style="display:flex;gap:8px;align-items:center;margin:0 0 8px 0;">
            <button type="button" data-relationships-breakdown-toggle="expand" style="padding:5px 8px;border-radius:6px;border:1px solid rgba(148,163,184,0.45);background:rgba(30,41,59,0.6);color:#e2e8f0;font-size:11px;font-weight:600;cursor:pointer;">Expand all breakdowns</button>
            <button type="button" data-relationships-breakdown-toggle="collapse" style="padding:5px 8px;border-radius:6px;border:1px solid rgba(148,163,184,0.45);background:rgba(30,41,59,0.6);color:#e2e8f0;font-size:11px;font-weight:600;cursor:pointer;">Collapse all breakdowns</button>
          </div>
          <div class="relationships-matrix-scroll" style="${scrollStyle}">
            <table class="relationships-matrix-table" style="${tableStyle}">
              <thead>
                <tr>
                  <th scope="col" style="${thStyle}">Target Actor</th>
                  <th scope="col" style="${thStyle}">Calculation</th>
                </tr>
              </thead>
              <tbody>
                ${calculationRows || `<tr><td colspan="2" style="${tdBaseStyle}text-align:left;color:#94a3b8;">No other visible actors available for calculation.</td></tr>`}
              </tbody>
            </table>
          </div>
        </div>
      `
      : '<div class="relationships-calculation-summary" style="padding:8px 4px;color:#94a3b8;font-size:12px;">No selected actor available for relationship calculation.</div>';

    container.innerHTML = calculationSummaryMarkup;
    const breakdownToggles = Array.from(container.querySelectorAll('[data-relationships-breakdown-toggle]'));
    if (breakdownToggles.length > 0) {
      breakdownToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
          const mode = String(toggle.getAttribute('data-relationships-breakdown-toggle') || '').trim().toLowerCase();
          const shouldOpen = mode === 'expand';
          container.querySelectorAll('details.relationships-breakdown').forEach((node) => {
            node.open = shouldOpen;
          });
        });
      });
    }
  }

  renderRelationshipsMatrixStatusTable(message = '') {
    const container = this._el.actorRelationshipsMatrix;
    if (!container) {
      return;
    }
    const text = String(message || '').trim() || 'Relationship matrix unavailable.';
    container.innerHTML = `<div class="relationships-calculation-summary" style="padding:8px 4px;color:#94a3b8;font-size:12px;">${escapeQuestHtml(text)}</div>`;
  }

  normalizeAttitudeValue(value = '') {
    const normalized = String(value || '').trim().toLowerCase();
    return ['helpful', 'friendly', 'indifferent', 'unfriendly', 'hostile'].includes(normalized)
      ? normalized
      : '';
  }

  resolveAttitudeScore(attitude = '') {
    switch (this.normalizeAttitudeValue(attitude)) {
      case 'helpful':
        return 100;
      case 'friendly':
        return 50;
      case 'unfriendly':
        return -50;
      case 'hostile':
        return -100;
      default:
        return 0;
    }
  }

  normalizeStanceValue(value = '') {
    const normalized = String(value || '').trim().toLowerCase();
    return [
      'engage_dialogue',
      'observe',
      'warn',
      'threaten',
      'aggressive_engage',
      'finish_weakest',
      'self_preserve',
      'flee',
      'pass',
      'deescalate',
    ].includes(normalized)
      ? normalized
      : '';
  }

  deriveFallbackStanceFromDisposition(score = 0, attitude = '') {
    const numericScore = Number.isFinite(Number(score)) ? Number(score) : this.resolveAttitudeScore(attitude);
    if (numericScore <= -70 || this.normalizeAttitudeValue(attitude) === 'hostile') {
      return 'threaten';
    }
    if (numericScore >= 50) {
      return 'engage_dialogue';
    }
    return 'observe';
  }

  resolveDefaultAttitudeFromSide(actorSide = 'neutral') {
    const side = this.normalizeActorSide(actorSide);
    if (side === 'hostile' || side === 'hazard') {
      return 'hostile';
    }
    if (side === 'party' || side === 'allied') {
      return 'friendly';
    }
    return 'indifferent';
  }

  resolveActorDefaultAttitude(entity = null) {
    if (!entity || typeof entity !== 'object') {
      return '';
    }
    const metadata = this.resolveEntityMetadata(entity);
    const statePayload = (entity?.dcStatePayload && typeof entity.dcStatePayload === 'object') ? entity.dcStatePayload : {};
    const entityPayload = (entity?.dcEntityPayload && typeof entity.dcEntityPayload === 'object') ? entity.dcEntityPayload : {};
    const descriptorAttitude = statePayload?.descriptors?.attitude
      || statePayload?.state?.descriptors?.attitude
      || entityPayload?.descriptors?.attitude
      || entityPayload?.state?.descriptors?.attitude
      || metadata?.attitude
      || statePayload?.attitude
      || entityPayload?.attitude
      || '';
    return this.normalizeAttitudeValue(descriptorAttitude);
  }

  resolveActorRelationshipAttitudes(entity = null) {
    if (!entity || typeof entity !== 'object') {
      return new Map();
    }
    const metadata = this.resolveEntityMetadata(entity);
    const statePayload = (entity?.dcStatePayload && typeof entity.dcStatePayload === 'object') ? entity.dcStatePayload : {};
    const entityPayload = (entity?.dcEntityPayload && typeof entity.dcEntityPayload === 'object') ? entity.dcEntityPayload : {};
    const source = statePayload?.relationship_attitudes
      || statePayload?.state?.relationship_attitudes
      || entityPayload?.relationship_attitudes
      || entityPayload?.state?.relationship_attitudes
      || metadata?.relationship_attitudes
      || [];
    const result = new Map();

    if (Array.isArray(source)) {
      source.forEach((entry) => {
        if (!entry || typeof entry !== 'object') {
          return;
        }
        const targetRef = String(
          entry?.target_entity_ref
          || entry?.target_ref
          || entry?.target_id
          || entry?.entity_ref
          || ''
        ).trim();
        const attitude = this.normalizeAttitudeValue(entry?.attitude || entry?.value || '');
        if (targetRef && attitude) {
          result.set(targetRef, attitude);
        }
      });
      return result;
    }

    if (source && typeof source === 'object') {
      Object.entries(source).forEach(([targetRef, rawAttitude]) => {
        const normalizedTarget = String(targetRef || '').trim();
        const attitude = this.normalizeAttitudeValue(rawAttitude);
        if (normalizedTarget && attitude) {
          result.set(normalizedTarget, attitude);
        }
      });
    }
    return result;
  }

  inferAttitudeFromSides(sourceSide = 'neutral', targetSide = 'neutral') {
    const from = this.normalizeActorSide(sourceSide);
    const to = this.normalizeActorSide(targetSide);
    if (from === 'party' && to === 'party') {
      return 'friendly';
    }
    if (from === 'allied' && to === 'allied') {
      return 'friendly';
    }
    if (from === 'hostile' && ['party', 'allied'].includes(to)) {
      return 'hostile';
    }
    if (['party', 'allied'].includes(from) && to === 'hostile') {
      return 'hostile';
    }
    if (from === 'allied' && to === 'party') {
      return 'friendly';
    }
    if (from === 'party' && to === 'allied') {
      return 'friendly';
    }
    return 'indifferent';
  }

  buildLocalRelationshipsMatrix(actors = []) {
    const matrix = {};
    actors.forEach((source) => {
      matrix[source.actorRef] = {};
      const sourceEntity = this.resolveEntityByRef(source.actorRef);
      const sourceDefault = this.resolveActorDefaultAttitude(sourceEntity) || this.resolveDefaultAttitudeFromSide(source.actorSide);
      const edges = this.resolveActorRelationshipAttitudes(sourceEntity);
      actors.forEach((target) => {
        if (source.actorRef === target.actorRef) {
          matrix[source.actorRef][target.actorRef] = '';
          return;
        }
        const direct = this.normalizeAttitudeValue(edges.get(target.actorRef) || '');
        if (direct) {
          matrix[source.actorRef][target.actorRef] = direct;
          return;
        }
        if (sourceDefault) {
          matrix[source.actorRef][target.actorRef] = sourceDefault;
          return;
        }
        const inferred = this.inferAttitudeFromSides(source.actorSide, target.actorSide);
        matrix[source.actorRef][target.actorRef] = inferred;
      });
    });
    return matrix;
  }

  buildLocalRelationshipCalculations(actors = [], matrix = {}) {
    const calculations = {};
    actors.forEach((source) => {
      calculations[source.actorRef] = {};
      const sourceEntity = this.resolveEntityByRef(source.actorRef);
      const sourceDefault = this.resolveActorDefaultAttitude(sourceEntity) || this.resolveDefaultAttitudeFromSide(source.actorSide);
      const edges = this.resolveActorRelationshipAttitudes(sourceEntity);
      actors.forEach((target) => {
        if (source.actorRef === target.actorRef) {
          calculations[source.actorRef][target.actorRef] = {
            rule: 'self',
            formula: 'self',
            weights: { default: 0.8, edge: 0.0, institution: 0.2 },
            source_default_attitude: sourceDefault,
            source_default_score: this.resolveAttitudeScore(sourceDefault),
            edge_attitude: '',
            edge_score: null,
            edge_score_source: 'none',
            institution_score: 0,
            institution_weighted_score: 0,
            institution_breakdown: [],
            final_attitude: '',
            final_score: 0,
            stance: '',
            stance_confidence: 0,
            stance_reason: '',
            stance_mode: 'combat_entry',
            stance_target_actor_ref: source.actorRef,
            stance_policy_flags: {},
            stance_basis: {},
            stance_formula: 'self',
            equation: 'self',
          };
          return;
        }
        const edgeAttitude = this.normalizeAttitudeValue(edges.get(target.actorRef) || '');
        const fallbackAttitude = this.normalizeAttitudeValue(String(matrix?.[source.actorRef]?.[target.actorRef] || ''));
        const inferred = this.inferAttitudeFromSides(source.actorSide, target.actorSide);
        const finalAttitude = edgeAttitude || sourceDefault || fallbackAttitude || inferred;
        const rule = edgeAttitude
          ? 'relationship_edge_override'
          : (sourceDefault ? 'source_default' : 'inferred_from_sides');
        const weights = edgeAttitude
          ? { default: 0.35, edge: 0.45, institution: 0.20 }
          : { default: 0.80, edge: 0.00, institution: 0.20 };
        const sourceDefaultScore = this.resolveAttitudeScore(sourceDefault);
        const edgeScore = edgeAttitude ? this.resolveAttitudeScore(edgeAttitude) : null;
        const institutionScore = 0;
        const institutionWeightedScore = Math.round(institutionScore * Number(weights.institution || 0));
        const finalScore = Math.max(
          -100,
          Math.min(
            100,
            Math.round(
              (Number(weights.default || 0) * sourceDefaultScore)
              + (Number(weights.edge || 0) * (edgeScore ?? 0))
              + institutionWeightedScore
            )
          )
        );
        const equation = edgeAttitude
          ? `clamp((${Number(weights.default || 0).toFixed(2)}*${sourceDefaultScore}) + (${Number(weights.edge || 0).toFixed(2)}*${edgeScore ?? 0}) + (${institutionWeightedScore}), -100, 100) = ${finalScore}`
          : (sourceDefault
            ? `clamp((${Number(weights.default || 0).toFixed(2)}*${sourceDefaultScore}) + (${Number(weights.edge || 0).toFixed(2)}*0) + (${institutionWeightedScore}), -100, 100) = ${finalScore}`
            : `inferred(${source.actorSide}→${target.actorSide} => ${inferred})`);
        calculations[source.actorRef][target.actorRef] = {
          rule,
          formula: 'final_score = clamp((w_default*source_default_score) + (w_edge*edge_score_or_0) + (w_inst*institution_score), -100, 100)',
          weights,
          source_default_attitude: sourceDefault,
          source_default_score: sourceDefaultScore,
          edge_attitude: edgeAttitude,
          edge_score: edgeScore,
          edge_score_source: edgeAttitude ? 'attitude_bucket' : 'none',
          institution_score: institutionScore,
          institution_weighted_score: institutionWeightedScore,
          institution_breakdown: [],
          final_attitude: finalAttitude,
          final_score: finalScore,
          stance: this.deriveFallbackStanceFromDisposition(finalScore, finalAttitude),
          stance_confidence: 0,
          stance_reason: edgeAttitude
            ? 'Local fallback stance derived from direct relationship edge hostility.'
            : 'Local fallback stance derived from disposition score when server stance payload is unavailable.',
          stance_mode: 'combat_entry',
          stance_target_actor_ref: target.actorRef,
          stance_policy_flags: {},
          stance_basis: {
            fallback: true,
            source_default_score: sourceDefaultScore,
            edge_score: edgeScore,
            final_score: finalScore,
          },
          stance_formula: `stance = if hostile_signal(${finalScore}) then threaten else observe`,
          equation,
        };
      });
    });
    return calculations;
  }

  async renderRelationshipsMatrix() {
    const container = this._el.actorRelationshipsMatrix;
    if (!container) {
      return;
    }
    if (!this.isRelationshipsTabActive()) {
      return;
    }
    const campaignId = Number(this.currentCharacterContext?.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || 0) || 0;
    if (campaignId <= 0) {
      this.renderRelationshipsMatrixStatusTable('Relationship matrix is available in campaign mode.');
      return;
    }

    const actors = this.resolveRelationshipMatrixActors();
    if (actors.length <= 1) {
      this.renderRelationshipsMatrixStatusTable('Need at least two room actors to build a relationship matrix.');
      return;
    }

    const requestToken = ++this._relationshipsMatrixRequestToken;
    if (Date.now() < this._relationshipsMatrixRemoteDisabledUntil) {
      const localMatrix = this.buildLocalRelationshipsMatrix(actors);
      const localCalculations = this.buildLocalRelationshipCalculations(actors, localMatrix);
      this.renderRelationshipsMatrixTable(actors, localMatrix, localCalculations, this.resolveSelectedRelationshipSourceRef(actors));
      return;
    }

    try {
      const params = new URLSearchParams();
      const selectedSourceRef = this.resolveSelectedRelationshipSourceRef(actors);
      if (selectedSourceRef) {
        params.set('selected_actor_ref', selectedSourceRef);
      }
      const serverActors = actors.filter(({ actorRef }) => this.canUseServerRelationshipActorRef(actorRef));
      if (serverActors.length <= 1) {
        const localMatrix = this.buildLocalRelationshipsMatrix(actors);
        const localCalculations = this.buildLocalRelationshipCalculations(actors, localMatrix);
        this.renderRelationshipsMatrixTable(actors, localMatrix, localCalculations, selectedSourceRef);
        return;
      }
      const requestKey = `${campaignId}|${selectedSourceRef}|${serverActors.map(({ actorRef }) => String(actorRef || '').trim()).filter(Boolean).join(',')}`;
      if (this._relationshipsMatrixPendingKey === requestKey) {
        return;
      }
      const lastCompletedAt = Number(this._relationshipsMatrixLastCompletedAt || 0);
      if (this._relationshipsMatrixLastCompletedKey === requestKey && (Date.now() - lastCompletedAt) < 2000) {
        return;
      }
      this._relationshipsMatrixPendingKey = requestKey;
      this.renderRelationshipsMatrixStatusTable('Loading relationship matrix…');
      params.set('actor_refs', serverActors.map(({ actorRef }) => String(actorRef || '').trim()).filter(Boolean).join(','));
      try {
        const response = await fetch(`/api/campaign/${encodeURIComponent(campaignId)}/relationships/matrix?${params.toString()}`, {
          method: 'GET',
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));
        if (requestToken !== this._relationshipsMatrixRequestToken) {
          return;
        }
        if (!response.ok || !payload?.success || typeof payload?.matrix !== 'object') {
          if (response.status === 404) {
            const localMatrix = this.buildLocalRelationshipsMatrix(actors);
            const localCalculations = this.buildLocalRelationshipCalculations(actors, localMatrix);
            this.renderRelationshipsMatrixTable(actors, localMatrix, localCalculations, selectedSourceRef);
            return;
          }
          if (response.status >= 500) {
            this._relationshipsMatrixRemoteDisabledUntil = Date.now() + 120000;
          }
          throw new Error(payload?.error || `Unable to load relationship matrix (${response.status})`);
        }
        const payloadCalculations = payload?.calculations && typeof payload.calculations === 'object'
          ? payload.calculations
          : {};
        const localMatrix = this.buildLocalRelationshipsMatrix(actors);
        const localCalculations = this.buildLocalRelationshipCalculations(actors, localMatrix);
        Object.entries(payload?.matrix || {}).forEach(([sourceRef, row]) => {
          if (!sourceRef || typeof row !== 'object' || row === null) {
            return;
          }
          localMatrix[sourceRef] = { ...(localMatrix[sourceRef] || {}), ...row };
        });
        Object.entries(payloadCalculations).forEach(([sourceRef, row]) => {
          if (!sourceRef || typeof row !== 'object' || row === null) {
            return;
          }
          localCalculations[sourceRef] = { ...(localCalculations[sourceRef] || {}), ...row };
        });
        const payloadSelectedSourceRef = this.resolveSelectedRelationshipSourceRef(actors, String(payload?.selected_actor_ref || selectedSourceRef));
        this.renderRelationshipsMatrixTable(actors, localMatrix, localCalculations, payloadSelectedSourceRef);
        this._relationshipsMatrixLastCompletedKey = requestKey;
        this._relationshipsMatrixLastCompletedAt = Date.now();
      } finally {
        if (this._relationshipsMatrixPendingKey === requestKey) {
          this._relationshipsMatrixPendingKey = '';
        }
      }
    } catch (error) {
      console.error('[CharacterPanel] renderRelationshipsMatrix failed', error);
      this._relationshipsMatrixRemoteDisabledUntil = Date.now() + 120000;
      if (requestToken !== this._relationshipsMatrixRequestToken) {
        return;
      }
      const localMatrix = this.buildLocalRelationshipsMatrix(actors);
      const localCalculations = this.buildLocalRelationshipCalculations(actors, localMatrix);
      this.renderRelationshipsMatrixTable(actors, localMatrix, localCalculations, this.resolveSelectedRelationshipSourceRef(actors));
    }
  }

  focusActorFromSelector(actorRef = '', options = {}) {
    const { activateCharacterTab = true } = options;
    const normalizedRef = String(actorRef || '').trim();
    if (!normalizedRef) {
      return;
    }
    const selectedOption = this._el.partyActorSelect?.selectedOptions?.[0] || null;
    const selectedActorKind = String(selectedOption?.dataset?.actorKind || '').trim().toLowerCase();
    if (selectedActorKind === 'primary' || normalizedRef === '__primary__') {
      this.restorePrimaryCharacterSheet();
      this.syncSheetLinksForSelectedEntity();
      this.togglePartyEmptyState(false);
      if (activateCharacterTab) {
        this._activateCharacterTab();
      }
      return;
    }

    const hexmap = this.stateManager?.hexmap || null;
    const entity = this.resolveEntityByRef(normalizedRef);
    if (entity && typeof hexmap?.selectEntity === 'function') {
      hexmap.selectEntity(entity);
    }
    if (selectedActorKind === 'actor') {
      if (entity) {
        this.showActorCharacterFromEntity(entity, { preferredActorRef: normalizedRef });
      }
      this.syncSheetLinksForSelectedEntity(entity || null);
      this.togglePartyEmptyState(false);
      if (activateCharacterTab) {
        this._activateCharacterTab();
      }
      return;
    }
    if (entity) {
      this.showFollowerCharacterFromEntity(entity);
      this.syncSheetLinksForSelectedEntity(entity);
      this.togglePartyEmptyState(false);
      if (activateCharacterTab) {
        this._activateCharacterTab();
      }
      return;
    }
    const followerRosterEntry = this.resolveFollowerRosterEntryByRef(normalizedRef);
    const fallbackPayload = this.buildFollowerLaunchCharacterPayloadFromRosterEntry(followerRosterEntry);
    if (fallbackPayload) {
      this.showLaunchCharacter(fallbackPayload, { storeAsPrimary: false });
      this.syncSheetLinksForSelectedEntity();
      this.togglePartyEmptyState(false);
      if (activateCharacterTab) {
        this._activateCharacterTab();
      }
      return;
    }
    if (!entity || !hexmap) {
      this.togglePartyEmptyState(true);
      return;
    }
  }

  applyPartyFollowerSelectionToCharacterSheet() {
    const selectedRef = String(this._el.partyActorSelect?.value || '').trim();
    const selectedOption = this._el.partyActorSelect?.selectedOptions?.[0] || null;
    const selectedActorKind = String(selectedOption?.dataset?.actorKind || '').trim().toLowerCase();
    if (selectedActorKind === 'primary' || selectedRef === '__primary__' || selectedRef === '') {
      this.restorePrimaryCharacterSheet();
      this.syncSheetLinksForSelectedEntity();
      this.togglePartyEmptyState(false);
      return;
    }
    const selectedEntity = this.resolveSelectedFollowerEntityFromSelector();
    if (selectedActorKind === 'actor') {
      const actorEntity = this.resolveEntityByRef(selectedRef);
      if (actorEntity) {
        this.showActorCharacterFromEntity(actorEntity, { preferredActorRef: selectedRef });
      }
      this.syncSheetLinksForSelectedEntity(actorEntity);
      this.togglePartyEmptyState(false);
      return;
    }
    if (selectedEntity) {
      this.showFollowerCharacterFromEntity(selectedEntity);
      this.syncSheetLinksForSelectedEntity(selectedEntity);
      this.togglePartyEmptyState(false);
      return;
    }
    const followerRosterEntry = this.resolveFollowerRosterEntryByRef(selectedRef);
    const fallbackPayload = this.buildFollowerLaunchCharacterPayloadFromRosterEntry(followerRosterEntry);
    if (fallbackPayload) {
      this.showLaunchCharacter(fallbackPayload, { storeAsPrimary: false });
      this.syncSheetLinksForSelectedEntity();
      this.togglePartyEmptyState(false);
      return;
    }
    this.togglePartyEmptyState(true);
  }

  resolveSelectedFollowerEntityFromSelector() {
    const selectedRef = String(this._el.partyActorSelect?.value || '').trim();
    if (!selectedRef) {
      return null;
    }
    const followerRosterEntry = this.resolveFollowerRosterEntryByRef(selectedRef);
    if (!followerRosterEntry) {
      return null;
    }
    const selectedEntity = this.resolveFollowerEntityFromRosterEntry(followerRosterEntry);
    if (!selectedEntity) {
      return null;
    }
    return selectedEntity;
  }

  togglePartyEmptyState(showEmpty = false) {
    const empty = document.getElementById('party-sheet-empty');
    const host = this._partyPanelHost || document.getElementById('party-character-panel-host');
    if (empty) {
      empty.style.display = showEmpty ? '' : 'none';
    }
    if (host) {
      host.style.display = showEmpty ? 'none' : '';
    }
  }

  showFollowerCharacterFromEntity(entity) {
    this.refreshEncounterStateAndRerenderEntity(entity, 'follower');
  }

  showActorCharacterFromEntity(entity, options = {}) {
    this.refreshEncounterStateAndRerenderEntity(entity, 'actor', options);
  }

  async refreshEncounterStateAndRerenderEntity(entity, actorKind = 'actor', options = {}) {
    if (!entity) {
      return;
    }
    const preferredActorRef = String(options?.preferredActorRef || '').trim();
    const targetRef = String(this.resolveEntityRef(entity) || preferredActorRef || '').trim();
    const characterId = Number(this.resolveEntityCharacterId(entity) || 0) || 0;
    const campaignId = Number(
      this.currentCharacterContext?.campaignId
      || this.stateManager?.hexmap?.resolveCampaignId?.()
      || 0
    ) || 0;
    if (characterId <= 0 || campaignId <= 0 || !targetRef) {
      const fallbackPayload = actorKind === 'follower'
        ? this.buildFollowerLaunchCharacterPayload(entity)
        : this.buildActorLaunchCharacterPayload(entity, { preferredActorRef: targetRef });
      if (fallbackPayload) {
        this.showLaunchCharacter(fallbackPayload, { storeAsPrimary: false });
      }
      return;
    }
    const requestRoomId = String(this.stateManager?.hexmap?.resolveActiveRoomId?.() || '').trim();
    const requestToken = `${targetRef}|${characterId}|${Date.now()}`;
    this._encounterStateRefreshToken = requestToken;

    try {
      const query = new URLSearchParams();
      query.set('campaignId', String(campaignId));
      query.set('instanceId', targetRef);
      const response = await fetch(`/api/character/${encodeURIComponent(characterId)}/state?${query.toString()}`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload?.success || !payload?.data) {
        throw new Error(payload?.error || `Character state refresh failed (${response.status})`);
      }
      if (this._encounterStateRefreshToken !== requestToken) {
        return;
      }
      const activeRoomId = String(this.stateManager?.hexmap?.resolveActiveRoomId?.() || '').trim();
      if (requestRoomId && activeRoomId && requestRoomId !== activeRoomId) {
        return;
      }
      const selectedEntity = this.stateManager?.hexmap?._getStateValue?.('selectedEntity') || entity;
      if (targetRef && !this.entityMatchesActorRef(selectedEntity, targetRef)) {
        return;
      }
      const refreshedPayload = payload.data;
      this.showLaunchCharacter(refreshedPayload, { storeAsPrimary: false });
    } catch (error) {
      console.warn('[CharacterPanel] character-state runtime refresh failed', {
        actorRef: targetRef || null,
        characterId,
        message: String(error?.message || error || ''),
      });
      return;
    }
  }

  shouldRequestAuthoritativeStateForActorRef(actorRef = '', options = {}) {
    return shouldRequestAuthoritativeStateForActorRefShared(actorRef, options);
  }

  isEncounterPhaseActive() {
    const snapshot = this.stateManager?.hexmap?.gameCoordinator?.phaseManager?.getSnapshot?.() || null;
    const phase = String(snapshot?.phase || '').trim().toLowerCase();
    const encounterId = Number(snapshot?.encounterId || 0);
    return phase === 'encounter' && Number.isFinite(encounterId) && encounterId > 0;
  }

  resolveEncounterParticipantForEntity(entity, options = {}) {
    if (!entity || typeof entity !== 'object') {
      return null;
    }

    const encounterState = this.stateManager?.hexmap?.getEncounterServerState?.() || null;
    const participants = Array.isArray(encounterState?.participants) ? encounterState.participants : [];
    if (participants.length === 0) {
      return null;
    }

    const preferredActorRef = String(options?.preferredActorRef || '').trim();
    const metadata = this.resolveEntityMetadata(entity);
    const contentId = String(
      entity?.dcContentId
      || entity?.dcStatePayload?.content_id
      || entity?.dcStatePayload?.entity_ref?.content_id
      || entity?.dcEntityPayload?.content_id
      || entity?.dcEntityPayload?.entity_ref?.content_id
      || metadata?.content_id
      || metadata?.npc_template_id
      || ''
    ).trim();
    const canonicalize = (value) => String(value ?? '')
      .trim()
      .toLowerCase()
      .replace(/^runtime:/, '')
      .replace(/^content:/, '')
      .replace(/^character:/, '')
      .replace(/^campaign-character:/, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
    const candidates = [
      this.resolveEntityRef(entity),
      preferredActorRef,
      entity?.dcEntityRef,
      entity?.dcEntityInstanceId,
      entity?.dcCharacterId,
      entity?.dcStatePayload?.metadata?.character_id,
      entity?.dcStatePayload?.character_id,
      entity?.dcEntityPayload?.character_id,
      contentId,
      contentId ? `npc_${contentId}` : '',
      contentId ? `npc-${contentId}` : '',
      this.resolveEntityLabel(entity),
      metadata?.display_name,
      metadata?.name,
      entity?.id,
    ]
      .map((value) => String(value ?? '').trim())
      .filter(Boolean);
    if (candidates.length === 0) {
      return null;
    }
    const uniqueCandidates = [...new Set(candidates)];
    const canonicalCandidates = new Set(uniqueCandidates.map((value) => canonicalize(value)).filter(Boolean));

    for (const participant of participants) {
      if (!participant || typeof participant !== 'object') {
        continue;
      }
      const participantCandidates = [
        participant?.entity_ref,
        participant?.entity_id,
        participant?.id,
        participant?.name,
      ]
        .map((value) => String(value ?? '').trim())
        .filter(Boolean);
      const entityRefRaw = String(participant?.entity_ref ?? '').trim();
      if (entityRefRaw.startsWith('{')) {
        try {
          const decoded = JSON.parse(entityRefRaw);
          const decodedContentId = String(decoded?.content_id ?? '').trim();
          participantCandidates.push(
            String(decoded?.entity_id ?? '').trim(),
            String(decoded?.instance_id ?? '').trim(),
            String(decoded?.entity_instance_id ?? '').trim(),
            String(decoded?.character_id ?? '').trim(),
            decodedContentId,
            decodedContentId ? `npc_${decodedContentId}` : '',
            decodedContentId ? `npc-${decodedContentId}` : '',
            String(decoded?.id ?? '').trim()
          );
        } catch (_error) {
          // Ignore malformed participant entity_ref payloads.
        }
      }
      const participantCanonical = new Set(participantCandidates.map((value) => canonicalize(value)).filter(Boolean));
      const directMatch = uniqueCandidates.some((candidate) => participantCandidates.includes(candidate));
      const canonicalMatch = Array.from(canonicalCandidates).some((candidate) => participantCanonical.has(candidate));
      if (directMatch || canonicalMatch) {
        return participant;
      }
    }

    return null;
  }

  resolveEncounterHpForEntity(entity, options = {}) {
    const participant = this.resolveEncounterParticipantForEntity(entity, options);
    if (!participant) {
      return null;
    }
    const current = Number(participant?.hp);
    if (!Number.isFinite(current)) {
      return null;
    }
    const max = Number(participant?.max_hp);
    return {
      current: current,
      max: Number.isFinite(max) && max > 0 ? max : current,
    };
  }

  resolveEncounterConditionsForEntity(entity, options = {}) {
    const participant = this.resolveEncounterParticipantForEntity(entity, options);
    if (!participant || typeof participant !== 'object') {
      return [];
    }

    const rawConditions = (
      (Array.isArray(participant.conditions) ? participant.conditions : null)
      || (Array.isArray(participant.active_conditions) ? participant.active_conditions : null)
      || (Array.isArray(participant.condition_states) ? participant.condition_states : null)
      || []
    );
    if (!Array.isArray(rawConditions) || rawConditions.length === 0) {
      return [];
    }

    return rawConditions
      .map((condition) => {
        if (!condition || typeof condition !== 'object') {
          const label = String(condition || '').trim();
          if (!label) {
            return null;
          }
          return {
            condition_type: label.toLowerCase().replace(/\s+/g, '_'),
            name: label,
          };
        }

        const rawType = String(
          condition.condition_type
          || condition.type
          || condition.id
          || condition.name
          || ''
        ).trim();
        if (!rawType) {
          return null;
        }

        return {
          condition_type: rawType.toLowerCase().replace(/\s+/g, '_'),
          name: String(condition.name || rawType).trim() || rawType,
          value: Number.isFinite(Number(condition.value)) ? Number(condition.value) : null,
          source: String(condition.source || '').trim() || null,
        };
      })
      .filter(Boolean);
  }

  buildActorLaunchCharacterPayload(entity, options = {}) {
    if (!entity) {
      return null;
    }
    const preferredActorRef = String(options?.preferredActorRef || '').trim();

    const identity = entity.getComponent?.('IdentityComponent') || null;
    const statsComponent = entity.getComponent?.('StatsComponent') || null;
    const movement = entity.getComponent?.('MovementComponent') || null;
    const combat = entity.getComponent?.('CombatComponent') || null;
    const metadata = this.resolveEntityMetadata(entity);
    const statePayload = (entity?.dcStatePayload && typeof entity.dcStatePayload === 'object') ? entity.dcStatePayload : {};
    const entityPayload = (entity?.dcEntityPayload && typeof entity.dcEntityPayload === 'object') ? entity.dcEntityPayload : {};
    const nestedStatePayload = (statePayload?.state && typeof statePayload.state === 'object') ? statePayload.state : {};
    const nestedEntityStatePayload = (entityPayload?.state && typeof entityPayload.state === 'object') ? entityPayload.state : {};
    const actorCharacterData = (
      (metadata?.character_data && typeof metadata.character_data === 'object') ? metadata.character_data
        : ((statePayload?.character_data && typeof statePayload.character_data === 'object') ? statePayload.character_data
          : ((nestedStatePayload?.character_data && typeof nestedStatePayload.character_data === 'object') ? nestedStatePayload.character_data
            : ((entityPayload?.character_data && typeof entityPayload.character_data === 'object') ? entityPayload.character_data
              : ((nestedEntityStatePayload?.character_data && typeof nestedEntityStatePayload.character_data === 'object') ? nestedEntityStatePayload.character_data : {}))))
    ) || {};
    const actorBasicInfo = (
      (metadata?.basic_info && typeof metadata.basic_info === 'object') ? metadata.basic_info
        : ((metadata?.basicInfo && typeof metadata.basicInfo === 'object') ? metadata.basicInfo
          : ((actorCharacterData?.basicInfo && typeof actorCharacterData.basicInfo === 'object') ? actorCharacterData.basicInfo
            : ((actorCharacterData?.basic_info && typeof actorCharacterData.basic_info === 'object') ? actorCharacterData.basic_info : {})))
    ) || {};
    const actorCalculatedStats = (
      (actorCharacterData?.calculated_stats && typeof actorCharacterData.calculated_stats === 'object') ? actorCharacterData.calculated_stats
        : ((metadata?.calculated_stats && typeof metadata.calculated_stats === 'object') ? metadata.calculated_stats : {})
    ) || {};
    const identityName = this.resolveEntityLabel(entity);
    const displayName = String(metadata.display_name || metadata.name || identityName || 'Actor').trim();
    if (!displayName) {
      return null;
    }

    const actorType = String(
      entity?.dcStatePayload?.entity_type
      || entity?.dcEntityPayload?.entity_type
      || metadata.entity_type
      || metadata.role
      || identity?.entityType
      || 'actor'
    ).trim().toLowerCase();
    const classId = String(metadata.class_id || actorType || 'actor').trim();
    const resolvedClass = String(
      metadata.class_id
      || metadata.class
      || actorCharacterData.class
      || actorBasicInfo.class
      || actorType
      || 'actor'
    ).trim();
    const ancestry = String(
      metadata.species
      || metadata.ancestry
      || metadata.follower_species
      || actorCharacterData.ancestry
      || actorBasicInfo.ancestry
      || actorType
      || 'Actor'
    ).trim();
    const level = Number(
      metadata.level
      || actorCharacterData.level
      || actorBasicInfo.level
      || metadata.stats?.level
      || actorCalculatedStats.level
      || 1
    ) || 1;
    const metadataStats = (metadata && typeof metadata.stats === 'object' && metadata.stats !== null) ? metadata.stats : {};
    let hpCurrent = Number(
      statsComponent?.currentHp
      ?? statsComponent?.current_hp
      ?? metadata.hp_current
      ?? actorCharacterData.hp_current
      ?? actorCharacterData?.hp?.current
      ?? metadataStats?.currentHp
      ?? metadataStats?.current_hp
      ?? actorCalculatedStats.current_hp
      ?? 0
    ) || 0;
    let hpMax = Number(
      statsComponent?.maxHp
      ?? statsComponent?.max_hp
      ?? metadata.hp_max
      ?? actorCharacterData.hp_max
      ?? actorCharacterData?.hp?.max
      ?? metadataStats?.maxHp
      ?? metadataStats?.max_hp
      ?? actorCalculatedStats.max_hp
      ?? hpCurrent
    ) || hpCurrent;
    const encounterHp = this.resolveEncounterHpForEntity(entity, { preferredActorRef });
    if (encounterHp) {
      hpCurrent = encounterHp.current;
      hpMax = encounterHp.max;
    }
    const armorClass = Number(
      statsComponent?.ac
      ?? metadata.armor_class
      ?? actorCharacterData.ac
      ?? metadataStats?.ac
      ?? actorCalculatedStats.ac
      ?? 0
    ) || 0;
    const speed = Number(
      movement?.speed
      ?? metadata.movement_speed
      ?? statsComponent?.speed
      ?? metadataStats?.speed
      ?? 25
    ) || 25;
    const perception = Number(
      statsComponent?.perception
      ?? metadata.perception
      ?? actorCharacterData.perception
      ?? metadataStats?.perception
      ?? actorCalculatedStats.perception
      ?? 0
    ) || 0;
    const campaignId = Number(this.currentCharacterContext?.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || 0) || 0;
    const runtimeCharacterId = Number(
      metadata.character_id
      || metadata.campaign_character_id
      || statePayload.character_id
      || statePayload.campaign_character_id
      || nestedStatePayload.character_id
      || nestedStatePayload.campaign_character_id
      || entityPayload.character_id
      || entityPayload.campaign_character_id
      || nestedEntityStatePayload.character_id
      || nestedEntityStatePayload.campaign_character_id
      || 0
    ) || 0;
    const portraitUrl = String(metadata.portrait_url || metadata.portrait || '').trim();
    const resourcesPayload = (metadata && typeof metadata.resources === 'object' && metadata.resources !== null) ? metadata.resources : {};
    const rawEquipment = (
      (Array.isArray(metadata.equipment) ? metadata.equipment : null)
      || (Array.isArray(statePayload.equipment) ? statePayload.equipment : null)
      || (Array.isArray(nestedStatePayload.equipment) ? nestedStatePayload.equipment : null)
      || (Array.isArray(entityPayload.equipment) ? entityPayload.equipment : null)
      || (Array.isArray(nestedEntityStatePayload.equipment) ? nestedEntityStatePayload.equipment : null)
      || []
    );
    const normalizedEquipment = rawEquipment
      .map((item, index) => {
        if (typeof item === 'string') {
          const label = item.trim();
          if (!label) {
            return null;
          }
          const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || `item-${index + 1}`;
          return {
            item_id: slug,
            name: label,
            quantity: 1,
            equipped: true,
            worn: false,
          };
        }
        if (!item || typeof item !== 'object') {
          return null;
        }
        return {
          item_id: String(item.item_id || item.id || item.name || `item-${index + 1}`).trim() || `item-${index + 1}`,
          name: String(item.name || item.item_id || item.id || `Item ${index + 1}`).trim() || `Item ${index + 1}`,
          quantity: Number(item.quantity || 1) || 1,
          equipped: item.equipped !== false,
          worn: item.worn === true,
        };
      })
      .filter(Boolean);
    const inferEquipmentHint = (item = {}) => {
      const explicitEquipSlot = String(item.equip_slot || item?.inventory_metadata?.equip_slot || '').trim().toLowerCase();
      const explicitWornSlot = String(item.worn_slot || item?.inventory_metadata?.worn_slot || '').trim().toLowerCase();
      if (explicitEquipSlot || explicitWornSlot) {
        return { equipSlot: explicitEquipSlot, wornSlot: explicitWornSlot };
      }
      const fingerprint = String(`${item.item_id || ''} ${item.name || ''}`).trim().toLowerCase();
      if (fingerprint.includes('shield')) {
        return { equipSlot: 'shield', wornSlot: '' };
      }
      if (fingerprint.includes('helmet') || fingerprint.includes('helm') || fingerprint.includes('hat') || fingerprint.includes('hood')) {
        return { equipSlot: '', wornSlot: 'head' };
      }
      if (
        fingerprint.includes('armor')
        || fingerprint.includes('mail')
        || fingerprint.includes('breastplate')
        || fingerprint.includes('leather')
        || fingerprint.includes('chain')
        || fingerprint.includes('plate')
      ) {
        return { equipSlot: 'armor', wornSlot: '' };
      }
      if (
        fingerprint.includes('sword')
        || fingerprint.includes('axe')
        || fingerprint.includes('bow')
        || fingerprint.includes('spear')
        || fingerprint.includes('hammer')
        || fingerprint.includes('mace')
        || fingerprint.includes('dagger')
        || fingerprint.includes('staff')
      ) {
        return { equipSlot: 'held', wornSlot: '' };
      }
      return { equipSlot: '', wornSlot: '' };
    };
    const deriveInventoryFromEquipment = (equipmentItems = [], baseInventory = null) => {
      const inventoryBase = (baseInventory && typeof baseInventory === 'object') ? { ...baseInventory } : {};
      const carriedItems = Array.isArray(inventoryBase.carried) ? [...inventoryBase.carried] : [];
      const equippedItems = Array.isArray(inventoryBase.equipped) ? [...inventoryBase.equipped] : [];
      const stashedItems = Array.isArray(inventoryBase.stashed) ? [...inventoryBase.stashed] : [];
      const wornSeed = (inventoryBase.worn && typeof inventoryBase.worn === 'object') ? { ...inventoryBase.worn } : {};
      const hasExplicitWorn = Object.keys(wornSeed).length > 0;
      const hasExplicitEquipped = equippedItems.length > 0;
      const candidatePool = equipmentItems.length > 0
        ? equipmentItems
        : carriedItems.filter((item) => item && typeof item === 'object' && (item.equipped === true || item.worn === true));
      if (!hasExplicitWorn && !hasExplicitEquipped && candidatePool.length > 0) {
        const derivedWorn = {
          weapons: [],
          accessories: [],
          armor: null,
          shield: null,
        };
        const movedItemKeys = new Set();
        candidatePool.forEach((item) => {
          if (!item || typeof item !== 'object' || item.equipped === false) {
            return;
          }
          const { equipSlot, wornSlot } = inferEquipmentHint(item);
          const slotItem = {
            ...item,
            equipped: true,
            inventory_metadata: {
              ...(item.inventory_metadata && typeof item.inventory_metadata === 'object' ? item.inventory_metadata : {}),
              ...(equipSlot ? { equip_slot: equipSlot } : {}),
              ...(wornSlot ? { worn_slot: wornSlot } : {}),
            },
          };
          if (equipSlot === 'shield') {
            if (!derivedWorn.shield) {
              derivedWorn.shield = slotItem;
            } else {
              derivedWorn.accessories.push(slotItem);
            }
          } else if (equipSlot === 'armor') {
            if (!derivedWorn.armor) {
              derivedWorn.armor = slotItem;
            } else {
              derivedWorn.accessories.push(slotItem);
            }
          } else if (equipSlot === 'held') {
            derivedWorn.weapons.push(slotItem);
          } else {
            derivedWorn.accessories.push(slotItem);
          }
          movedItemKeys.add(String(item.item_instance_id || `${item.item_id || item.id || item.name || 'item'}:${item.quantity || 1}`));
        });
        const normalizedCarried = carriedItems.filter((item) => {
          if (!item || typeof item !== 'object') {
            return false;
          }
          const itemKey = String(item.item_instance_id || `${item.item_id || item.id || item.name || 'item'}:${item.quantity || 1}`);
          return !movedItemKeys.has(itemKey);
        });
        return {
          ...inventoryBase,
          worn: derivedWorn,
          carried: normalizedCarried,
          equipped: hasExplicitEquipped ? equippedItems : [],
          stashed: stashedItems,
        };
      }
      return {
        ...inventoryBase,
        worn: wornSeed,
        carried: carriedItems,
        equipped: equippedItems,
        stashed: stashedItems,
      };
    };
    const rawInventorySeed = (
      resourcesPayload.inventory
      || metadata.inventory
      || statePayload.inventory
      || nestedStatePayload.inventory
      || entityPayload.inventory
      || nestedEntityStatePayload.inventory
      || null
    );
    const derivedInventorySeed = deriveInventoryFromEquipment(
      normalizedEquipment,
      rawInventorySeed && typeof rawInventorySeed === 'object' ? rawInventorySeed : null
    );
    const inventorySeed = normalizeInventoryState(
      Object.keys(derivedInventorySeed || {}).length > 0
        ? derivedInventorySeed
        : (normalizedEquipment.length > 0 ? deriveInventoryFromEquipment(normalizedEquipment, { carried: normalizedEquipment }) : {}),
      resourcesPayload.currency || metadata.currency || {}
    );
    const abilitiesPayload = (
      (metadata && typeof metadata.abilities === 'object' && metadata.abilities !== null) ? metadata.abilities
        : ((actorCharacterData && typeof actorCharacterData.abilities === 'object' && actorCharacterData.abilities !== null) ? actorCharacterData.abilities
          : ((actorCalculatedStats && typeof actorCalculatedStats.abilities === 'object' && actorCalculatedStats.abilities !== null) ? actorCalculatedStats.abilities
            : {}))
    );
    const ability = (key, fallback = 10) => Number(
      abilitiesPayload[key]
      ?? abilitiesPayload[String(key).toLowerCase()]
      ?? abilitiesPayload[String(key).slice(0, 3).toLowerCase()]
      ?? fallback
    ) || fallback;
    const skillsPayload = (
      (metadata && typeof metadata.skills === 'object' && metadata.skills !== null) ? metadata.skills
        : ((actorCharacterData && typeof actorCharacterData.skills === 'object' && actorCharacterData.skills !== null) ? actorCharacterData.skills : {})
    );
    const skills = Array.isArray(skillsPayload)
      ? skillsPayload
      : Object.entries(skillsPayload).map(([name, value]) => ({
        name,
        modifier: Number(value?.modifier ?? value?.bonus ?? value ?? 0) || 0,
        proficiency: String(value?.proficiency ?? value?.rank ?? ''),
      }));
    const savesPayload = (
      (metadata && typeof metadata.saves === 'object' && metadata.saves !== null) ? metadata.saves
        : ((actorCharacterData && typeof actorCharacterData.saves === 'object' && actorCharacterData.saves !== null) ? actorCharacterData.saves : {})
    );
    const combatInitiative = Number(combat?.getInitiative?.() ?? metadata.initiative ?? 0) || 0;
    const fortitude = Number(
      savesPayload.fortitude?.base
      ?? savesPayload.fortitude
      ?? metadataStats?.fortitude
      ?? 0
    ) || 0;
    const reflex = Number(
      savesPayload.reflex?.base
      ?? savesPayload.reflex
      ?? metadataStats?.reflex
      ?? 0
    ) || 0;
    const will = Number(
      savesPayload.will?.base
      ?? savesPayload.will
      ?? metadataStats?.will
      ?? 0
    ) || 0;
    const spellsPayload = (
      (metadata && typeof metadata.spellcasting === 'object' && metadata.spellcasting !== null) ? metadata.spellcasting
        : ((actorCharacterData && typeof actorCharacterData.spellcasting === 'object' && actorCharacterData.spellcasting !== null) ? actorCharacterData.spellcasting
          : ((actorCharacterData && typeof actorCharacterData.spells === 'object' && actorCharacterData.spells !== null) ? actorCharacterData.spells : {}))
    );
    const encounterConditions = this.resolveEncounterConditionsForEntity(entity, { preferredActorRef });
    const actorFeaturesPayload = (
      (actorCharacterData && typeof actorCharacterData.features === 'object' && actorCharacterData.features !== null) ? actorCharacterData.features
        : {}
    );

    return {
      id: runtimeCharacterId || null,
      character_id: runtimeCharacterId || null,
      sheet_character_id: runtimeCharacterId || null,
      campaignId,
      is_runtime_actor: true,
      actor_kind: actorType,
      portrait: portraitUrl,
      data: {
        id: runtimeCharacterId || null,
        characterId: runtimeCharacterId || null,
        character_id: runtimeCharacterId || null,
        sheet_character_id: runtimeCharacterId || null,
        is_runtime_actor: true,
        actor_kind: actorType,
        name: displayName,
        ancestry,
        class: resolvedClass || classId,
        level,
        speed,
        hp_current: hpCurrent,
        hp_max: hpMax,
        armor_class: armorClass,
        perception,
        portrait_url: portraitUrl,
        basicInfo: {
          name: displayName,
          ancestry,
          class: resolvedClass || classId,
          level,
        },
        inventory: inventorySeed,
        resources: {
          hitPoints: { current: hpCurrent, max: hpMax },
          heroPoints: {
            current: Number(resourcesPayload.heroPoints?.current ?? resourcesPayload.hero_points ?? 0) || 0,
            max: Number(resourcesPayload.heroPoints?.max ?? resourcesPayload.hero_points_max ?? 0) || 0,
          },
          inventory: inventorySeed,
        },
        defenses: {
          armorClass,
          perception,
          fortitude,
          reflex,
          will,
        },
        saves: {
          fortitude,
          reflex,
          will,
        },
        abilities: {
          strength: ability('strength'),
          dexterity: ability('dexterity'),
          constitution: ability('constitution'),
          intelligence: ability('intelligence'),
          wisdom: ability('wisdom'),
          charisma: ability('charisma'),
        },
        skills,
        spells: spellsPayload,
        features: {
          classFeatures: Array.isArray(actorFeaturesPayload.classFeatures) ? actorFeaturesPayload.classFeatures : [],
          feats: Array.isArray(actorFeaturesPayload.feats)
            ? actorFeaturesPayload.feats
            : (Array.isArray(actorCharacterData.feats) ? actorCharacterData.feats : []),
        },
        conditions: encounterConditions.length > 0
          ? encounterConditions
          : (Array.isArray(metadata.conditions)
            ? metadata.conditions
            : (Array.isArray(actorCharacterData.conditions) ? actorCharacterData.conditions : [])),
        combat: {
          initiative: combatInitiative,
          team: String(combat?.team || metadata.team || '').trim(),
        },
        personality: {
          personality: String(metadata.psychology_profile?.personality || actorCharacterData.personality || '').trim(),
          backstory: String(metadata.description || actorCharacterData.backstory || '').trim(),
        },
        equipment: normalizedEquipment,
      },
    };
  }

  buildFollowerLaunchCharacterPayload(entity) {
    if (!entity) {
      return null;
    }
    const metadata = this.resolveEntityMetadata(entity);
    const displayName = String(metadata.display_name || metadata.name || this.resolveEntityLabel(entity) || 'Follower').trim();
    if (!displayName) {
      return null;
    }

    const followerKind = String(
      metadata.follower_kind
      || this.resolveEntityFollowerKind(entity)
      || metadata.role
      || 'follower'
    ).trim().toLowerCase();
    const classId = String(metadata.class_id || followerKind || 'follower').trim();
    const ancestry = String(
      metadata.familiar_species_name
      || metadata.follower_species
      || metadata.species
      || metadata.ancestry
      || 'Follower'
    ).trim();
    const level = Number(metadata.level || metadata.stats?.level || 1) || 1;
    const stats = (metadata && typeof metadata.stats === 'object' && metadata.stats !== null) ? metadata.stats : {};
    let hpCurrent = Number(stats.currentHp ?? stats.current_hp ?? metadata.hp_current ?? 0) || 0;
    let hpMax = Number(stats.maxHp ?? stats.max_hp ?? metadata.hp_max ?? hpCurrent) || hpCurrent;
    const encounterHp = this.resolveEncounterHpForEntity(entity);
    if (encounterHp) {
      hpCurrent = encounterHp.current;
      hpMax = encounterHp.max;
    }
    const armorClass = Number(stats.ac ?? metadata.armor_class ?? 0) || 0;
    const speed = Number(metadata.movement_speed ?? stats.speed ?? 25) || 25;
    const perception = Number(stats.perception ?? metadata.perception ?? 0) || 0;
    const ownerCharacterId = Number(metadata.owner_character_id || 0) || 0;
    let followerCharacterId = Number(
      metadata.follower_character_id
      || metadata.campaign_character_id
      || metadata.character_id
      || 0
    ) || 0;
    if (ownerCharacterId > 0 && followerCharacterId === ownerCharacterId) {
      followerCharacterId = 0;
    }
    const sourceCharacterId = Number(
      metadata.follower_source_character_id
      || metadata.source_character_id
      || 0
    ) || 0;
    const campaignId = Number(this.currentCharacterContext?.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || 0) || 0;
    const followerPortraitUrl = String(metadata.portrait_url || metadata.portrait || '').trim();
    const resourcesPayload = (metadata && typeof metadata.resources === 'object' && metadata.resources !== null) ? metadata.resources : {};
    const inventorySeed = normalizeInventoryState(
      resourcesPayload.inventory || metadata.inventory || {},
      resourcesPayload.currency || metadata.currency || {}
    );

    const abilitiesPayload = (metadata && typeof metadata.abilities === 'object' && metadata.abilities !== null) ? metadata.abilities : {};
    const ability = (key, fallback = 10) => Number(
      abilitiesPayload[key]
      ?? abilitiesPayload[String(key).toLowerCase()]
      ?? fallback
    ) || fallback;

    const skillsPayload = (metadata && typeof metadata.skills === 'object' && metadata.skills !== null) ? metadata.skills : {};
    const skills = Array.isArray(skillsPayload)
      ? skillsPayload
      : Object.entries(skillsPayload).map(([name, value]) => ({
        name,
        modifier: Number(value?.modifier ?? value?.bonus ?? value ?? 0) || 0,
        proficiency: String(value?.proficiency ?? value?.rank ?? ''),
      }));

    const savesPayload = (metadata && typeof metadata.saves === 'object' && metadata.saves !== null) ? metadata.saves : {};
    const spellsPayload = (metadata && typeof metadata.spellcasting === 'object' && metadata.spellcasting !== null) ? metadata.spellcasting : {};
    const encounterConditions = this.resolveEncounterConditionsForEntity(entity);
    const classFeatureOptions = Array.isArray(metadata.class_feature_options)
      ? metadata.class_feature_options
      : (Array.isArray(metadata.familiar_class_feature_options) ? metadata.familiar_class_feature_options : []);
    const familiarAbilityDetails = Array.isArray(metadata.familiar_ability_details) ? metadata.familiar_ability_details : [];
    const features = {
      classFeatures: classFeatureOptions.map((option) => ({
        id: option.id || option.option_id || '',
        name: option.name || option.option_id || 'Class feature',
        description: option.description || '',
        type: 'class_feature',
      })),
      feats: familiarAbilityDetails.map((feat) => ({
        id: feat.class_feature_option_id || feat.id || '',
        name: feat.name || feat.id || 'Familiar ability',
        description: feat.description || '',
        type: 'feat',
      })),
    };

    return {
      id: followerCharacterId || null,
      character_id: followerCharacterId || null,
      sheet_character_id: followerCharacterId || null,
      owner_character_id: ownerCharacterId || null,
      source_character_id: sourceCharacterId || null,
      campaignId,
      is_follower_actor: true,
      follower_kind: followerKind,
      portrait: followerPortraitUrl,
      data: {
        id: followerCharacterId || null,
        characterId: followerCharacterId || null,
        character_id: followerCharacterId || null,
        sheet_character_id: followerCharacterId || null,
        owner_character_id: ownerCharacterId || null,
        source_character_id: sourceCharacterId || null,
        is_follower_actor: true,
        follower_kind: followerKind,
        name: displayName,
        ancestry,
        class: classId,
        level,
        speed,
        hp_current: hpCurrent,
        hp_max: hpMax,
        armor_class: armorClass,
        perception,
        portrait_url: followerPortraitUrl,
        basicInfo: {
          name: displayName,
          ancestry,
          class: classId,
          level,
        },
        inventory: inventorySeed,
        resources: {
          hitPoints: { current: hpCurrent, max: hpMax },
          heroPoints: {
            current: Number(resourcesPayload.heroPoints?.current ?? resourcesPayload.hero_points ?? 0) || 0,
            max: Number(resourcesPayload.heroPoints?.max ?? resourcesPayload.hero_points_max ?? 0) || 0,
          },
          inventory: inventorySeed,
        },
        defenses: {
          armorClass,
          perception,
          fortitude: Number(savesPayload.fortitude?.base ?? savesPayload.fortitude ?? 0) || 0,
          reflex: Number(savesPayload.reflex?.base ?? savesPayload.reflex ?? 0) || 0,
          will: Number(savesPayload.will?.base ?? savesPayload.will ?? 0) || 0,
        },
        saves: {
          fortitude: Number(savesPayload.fortitude?.base ?? savesPayload.fortitude ?? 0) || 0,
          reflex: Number(savesPayload.reflex?.base ?? savesPayload.reflex ?? 0) || 0,
          will: Number(savesPayload.will?.base ?? savesPayload.will ?? 0) || 0,
        },
        abilities: {
          strength: ability('strength'),
          dexterity: ability('dexterity'),
          constitution: ability('constitution'),
          intelligence: ability('intelligence'),
          wisdom: ability('wisdom'),
          charisma: ability('charisma'),
        },
        skills,
        spells: spellsPayload,
        features,
        conditions: encounterConditions.length > 0
          ? encounterConditions
          : (Array.isArray(metadata.conditions) ? metadata.conditions : []),
        personality: {
          personality: String(metadata.psychology_profile?.personality || '').trim(),
          backstory: String(metadata.description || '').trim(),
        },
        equipment: Array.isArray(metadata.equipment) ? metadata.equipment : [],
      },
    };
  }

  buildFollowerLaunchCharacterPayloadFromRosterEntry(followerEntry = null) {
    if (!followerEntry || typeof followerEntry !== 'object') {
      return null;
    }
    const ownerCharacterId = Number(followerEntry?.owner_character_id || 0) || 0;
    const followerCharacterId = Number(followerEntry?.follower_character_id || 0) || 0;
    if (ownerCharacterId <= 0 || followerCharacterId <= 0) {
      return null;
    }
    const followerKind = String(followerEntry?.follower_kind || followerEntry?.role || 'follower').trim().toLowerCase() || 'follower';
    const displayName = String(followerEntry?.display_name || 'Follower').trim() || 'Follower';
    const portraitUrl = String(followerEntry?.portrait_url || '').trim();
    const sourceCharacterId = Number(followerEntry?.follower_source_character_id || 0) || 0;
    const campaignId = Number(this.currentCharacterContext?.campaignId || this.stateManager?.hexmap?.resolveCampaignId?.() || 0) || 0;

    return {
      id: followerCharacterId,
      character_id: followerCharacterId,
      sheet_character_id: followerCharacterId,
      owner_character_id: ownerCharacterId,
      source_character_id: sourceCharacterId || null,
      campaignId,
      is_follower_actor: true,
      follower_kind: followerKind,
      portrait: portraitUrl,
      data: {
        id: followerCharacterId,
        characterId: followerCharacterId,
        character_id: followerCharacterId,
        sheet_character_id: followerCharacterId,
        owner_character_id: ownerCharacterId,
        source_character_id: sourceCharacterId || null,
        is_follower_actor: true,
        follower_kind: followerKind,
        name: displayName,
        portrait_url: portraitUrl,
        basicInfo: {
          name: displayName,
          level: 1,
        },
        resources: {
          hitPoints: { current: 0, max: 0 },
          heroPoints: { current: 0, max: 0 },
          inventory: { items: [], currency: { gp: 0, sp: 0, cp: 0 } },
        },
        defenses: {
          armorClass: 0,
          perception: 0,
        },
        abilities: {
          strength: 10,
          dexterity: 10,
          constitution: 10,
          intelligence: 10,
          wisdom: 10,
          charisma: 10,
        },
        skills: [],
        saves: {
          fortitude: 0,
          reflex: 0,
          will: 0,
        },
        features: {},
        conditions: [],
        inventory: { items: [], currency: { gp: 0, sp: 0, cp: 0 } },
      },
    };
  }

  showEmbeddedCharacterSheet(characterId) {
    if (!characterId) {
      return;
    }
    console.log('[CharacterPanel] showEmbeddedCharacterSheet', { characterId });
    const activeSidebarTab = this.container
      ?.closest('.game-layout__sidebar')
      ?.querySelector('[data-sidebar-tab].sidebar-tab--active')
      ?.dataset?.sidebarTab ?? null;
    if (!activeSidebarTab) {
      this._activateSidebarTab('character');
    }
    if (this._el.characterSheetEmbedWrap) {
      this._el.characterSheetEmbedWrap.style.display = 'none';
    }
    if (this._el.characterSheetEmbed) {
      this._el.characterSheetEmbed.removeAttribute('src');
    }
    if (this._el.characterSheetLegacy) {
      this._el.characterSheetLegacy.style.display = '';
    }
    const charSubPanel = document.getElementById('sidebar-panel-character');
    console.log('[CharacterPanel] showEmbeddedCharacterSheet:done', {
      legacyShown: !!this._el.characterSheetLegacy,
      embedHidden: !!this._el.characterSheetEmbedWrap,
      legacyStyle: this._el.characterSheetLegacy?.style?.display ?? 'no-el',
      gamePanelHidden: document.getElementById('game-panel-party')?.hidden ?? 'no-el',
      charSubPanelDisplay: charSubPanel?.style?.display ?? 'no-el',
      charSubPanelActive: charSubPanel?.classList?.contains('sidebar-panel--active') ?? false,
    });
  }

  showEntityInfo(entity) {
    console.log('[CharacterPanel] showEntityInfo', { id: entity?.id, type: entity?.type });
    if (!this._el.entityInfoPanel) return;

    this._el.entityInfoPanel.classList.remove('dc-is-hidden');
    this._el.entityInfoPanel.style.display = 'block';
    this._el.entityInfoPanel.setAttribute('aria-hidden', 'false');

    const hexmap = this.stateManager?.hexmap || null;
    const identity = entity.getComponent('IdentityComponent');
    const stats = entity.getComponent('StatsComponent');
    const combat = entity.getComponent('CombatComponent');
    const actions = entity.getComponent('ActionsComponent');
    const movement = entity.getComponent('MovementComponent');
    const render = entity.getComponent('RenderComponent');
    const metadata = entity?.dcStatePayload?.metadata || {};
    const contentId = entity?.dcContentId || entity?.entity_ref?.content_id || null;
    const objectDefinition = hexmap?.getObjectDefinition?.(contentId) || null;
    const spriteId = metadata.sprite_id || objectDefinition?.visual?.sprite_id || render?.spriteKey || null;
    const imageUrl = metadata.portrait_url || metadata.portrait || (spriteId ? hexmap?.spriteService?.getCachedUrl?.(spriteId) : null) || null;
    const displayType = objectDefinition?.category || identity?.entityType || '-';
    const teamLabel = combat?.team || metadata.team || '-';
    const description = metadata.description || objectDefinition?.description || metadata.item_description || '';
    const movementValue = Number.isFinite(movement?.movementRemaining)
      ? `${movement.movementRemaining} ft`
      : (Number.isFinite(movement?.speed) ? `${movement.speed} ft` : (Number.isFinite(metadata?.movement_speed) ? `${metadata.movement_speed} ft` : '-'));
    const knownSummary = [
      metadata.role,
      metadata.item_name,
      objectDefinition?.label && objectDefinition?.label !== identity?.name ? objectDefinition.label : null,
      displayType && displayType !== identity?.entityType ? displayType : null,
    ].filter(Boolean)[0] || 'Known details';
    const knownDetails = [];

    if (teamLabel && teamLabel !== '-') {
      knownDetails.push(`Team: ${teamLabel}`);
    }
    if (objectDefinition?.category) {
      knownDetails.push(`Category: ${objectDefinition.category}`);
    }
    if (metadata.role) {
      knownDetails.push(`Role: ${metadata.role}`);
    }
    if (metadata.collectible === true) {
      knownDetails.push('Collectible');
    }
    if (typeof metadata.movable === 'boolean') {
      knownDetails.push(metadata.movable ? 'Movable' : 'Fixed in place');
    }
    if (typeof metadata.passable === 'boolean') {
      knownDetails.push(metadata.passable ? 'Passable' : 'Blocks movement');
    }
    if (Array.isArray(objectDefinition?.traits) && objectDefinition.traits.length) {
      knownDetails.push(`Traits: ${objectDefinition.traits.join(', ')}`);
    }

    if (this._el.entityName) {
      this._el.entityName.textContent = identity?.name || 'Unknown';
    }
    if (this._el.entityType) {
      this._el.entityType.textContent = displayType;
    }
    if (this._el.entityImageWrap && this._el.entityImage) {
      if (imageUrl) {
        this._el.entityImage.src = imageUrl;
        this._el.entityImage.alt = `${identity?.name || 'Entity'} portrait`;
        this._el.entityImageWrap.classList.remove('dc-is-hidden');
      } else {
        this._el.entityImage.removeAttribute('src');
        this._el.entityImage.alt = '';
        this._el.entityImageWrap.classList.add('dc-is-hidden');
      }
    }
    if (this._el.entitySummary) {
      this._el.entitySummary.textContent = knownSummary;
    }
    if (this._el.entityDescription) {
      this._el.entityDescription.textContent = description || 'No additional details are known yet.';
    }
    if (this._el.entityKnownDetails) {
      if (knownDetails.length) {
        this._el.entityKnownDetails.innerHTML = knownDetails
          .map((detail) => `<li>${detail}</li>`)
          .join('');
      } else {
        this._el.entityKnownDetails.innerHTML = '<li>No additional details are known yet.</li>';
      }
    }
    if (this._el.entityTeam) {
      this._el.entityTeam.textContent = teamLabel;
    }
    if (this._el.entityHp) {
      this._el.entityHp.textContent = stats ? `${stats.currentHp}/${stats.maxHp}` : '-';
    }
    if (this._el.entityAc) {
      this._el.entityAc.textContent = stats?.ac || '-';
    }
    if (this._el.entityActions) {
      this._el.entityActions.textContent = actions ? actions.getActionDisplay?.() || `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions` : '-';
    }
    if (this._el.entityMovement) {
      this._el.entityMovement.textContent = movementValue;
    }
    this.syncSheetLinksForSelectedEntity(entity);

    // NOTE: Character sheet (character* elements) is only populated by
    // showLaunchCharacter() with the PC's full data.  Do NOT overwrite it
    // here — this method fires for every selected entity including NPCs.
  }

  showLaunchCharacter(launchCharacter, options = {}) {
    if (!launchCharacter || typeof launchCharacter !== 'object') {
      return;
    }
    const storeAsPrimary = options.storeAsPrimary !== false;
    if (storeAsPrimary) {
      launchCharacter = this.hydrateLaunchCharacterWithPrimaryFollowerRoster(launchCharacter);
      this.primaryLaunchCharacter = launchCharacter;
    }

    console.log('[CharacterPanel] showLaunchCharacter', { id: launchCharacter?.id, instance: launchCharacter?.instance_id });

    // Support both legacy format and new API state format
    const state = launchCharacter.data || launchCharacter;
    const basicInfo = state.basicInfo || {};
    const abilities = state.abilities || {};
    const resources = state.resources || {};
    const defenses = state.defenses || {};
    const conditions = state.conditions || [];
    const skills = collectCharacterSkillEntries(launchCharacter);
    const features = state.features || {};
    const feats = state.feats || []; // Direct feats array from legacy format
    const equipment = state.equipment || [];
    const fallbackCurrency = state.currency || launchCharacter.currency || {
      gp: state.gold || launchCharacter.gold || 0,
      sp: 0,
      cp: 0,
    };
    const inventory = normalizeInventoryState(
      state.inventory || resources.inventory || launchCharacter.inventory || {},
      fallbackCurrency
    );
    const spells = normalizeSpellcastingData(state.spells || launchCharacter.spells || {}, state, launchCharacter);
    const saves = state.saves || defenses.savingThrows || {};
    const featEffects = features.featEffects || {};
    const featActions = flattenTooltipBuckets(state.actions?.availableActions?.feat || featEffects.available_actions || {});
    const featAugments = flattenTooltipBuckets(spells.featAugments || featEffects.spell_augments || {});
    const featSelections = Array.isArray(features.featSelectionGrants) ? features.featSelectionGrants : [];
    const featNotes = Array.isArray(featEffects.notes) ? featEffects.notes : [];
    const featTodoReview = Array.isArray(features.featTodoReview) ? features.featTodoReview : [];
    const featTraining = features.featTraining || {};
    const featConditionalModifiers = features.featConditionalModifiers || {};
    const featRestResources = flattenTooltipBuckets(resources.featResources || {});
  
    // Normalize ability scores (support both short 'str' and long 'strength' keys)
    const normalizeAbilities = (abs) => ({
      strength: abs.strength || abs.str || 10,
      dexterity: abs.dexterity || abs.dex || 10,
      constitution: abs.constitution || abs.con || 10,
      intelligence: abs.intelligence || abs.int || 10,
      wisdom: abs.wisdom || abs.wis || 10,
      charisma: abs.charisma || abs.cha || 10,
    });
    const normalizedAbilities = normalizeAbilities(abilities);
    const firstNonEmptyText = (...values) => {
      for (const value of values) {
        if (typeof value === 'string' && value.trim()) {
          return value.trim();
        }
        if (Array.isArray(value)) {
          const nested = firstNonEmptyText(...value);
          if (nested) {
            return nested;
          }
        }
      }
      return '';
    };

    // Basic info
    const name = basicInfo.name || state.name || launchCharacter.name || 'Selected character';
    const ancestry = basicInfo.ancestry || state.ancestry || launchCharacter.ancestry || '';
    const heritage = state.heritage || launchCharacter.heritage || '';
    const characterClass = basicInfo.class || state.class || launchCharacter.class || '';
    const background = state.background || launchCharacter.background || '';
    const personalityInfo = (state.personality && typeof state.personality === 'object') ? state.personality : {};
    const launchPersonality = (launchCharacter.personality && typeof launchCharacter.personality === 'object') ? launchCharacter.personality : {};
    const personalityText = firstNonEmptyText(
      basicInfo.personality,
      personalityInfo.personality,
      Array.isArray(personalityInfo.traits) ? personalityInfo.traits[0] : '',
      launchPersonality.personality,
      Array.isArray(launchPersonality.traits) ? launchPersonality.traits[0] : '',
      state.personality,
      launchCharacter.personality
    );
    const backstoryText = firstNonEmptyText(
      basicInfo.backstory,
      personalityInfo.backstory,
      launchPersonality.backstory,
      state.backstory,
      launchCharacter.backstory
    );
    const level = Number(basicInfo.level || state.level || launchCharacter.level || 0);
    const speed = Number(state.speed || launchCharacter.speed || 25);
    const characterId = state.characterId || state.id || launchCharacter.characterId || launchCharacter.id || null;
    const sheetCharacterId = state.sheet_character_id || state.character_id || state.characterId || launchCharacter.sheet_character_id || launchCharacter.character_id || launchCharacter.characterId || characterId || null;
  
    // Resources
    const hpCurrent = Number(resources.hitPoints?.current ?? state.hp_current ?? launchCharacter.hp_current ?? 0);
    const hpMax = Number(resources.hitPoints?.max ?? state.hp_max ?? launchCharacter.hp_max ?? 0);
    const heroCurrent = Number(resources.heroPoints?.current ?? state.hero_points ?? launchCharacter.hero_points ?? 1);
    const heroMax = Number(resources.heroPoints?.max ?? 3);
    const armorClass = Number(defenses.armorClass?.base ?? defenses.armorClass ?? state.armor_class ?? launchCharacter.armor_class ?? 0);
    const xp = Number(basicInfo.experiencePoints ?? state.experience_points ?? 0);
  
    // Perception
    const perception = Number(defenses.perception?.base ?? state.perception ?? launchCharacter.perception ?? 0);
    const currency = inventory.currency || fallbackCurrency;

    // Calculate ability modifiers
    const calcMod = (score) => {
      const mod = Math.floor((score - 10) / 2);
      return mod >= 0 ? `+${mod}` : `${mod}`;
    };
    const formatMod = (val) => val >= 0 ? `+${val}` : `${val}`;

    // Portrait
    const portraitUrl = firstNonEmptyText(
      state.portrait_url,
      state.portrait?.url,
      state.portrait,
      basicInfo.portrait_url,
      basicInfo.portrait?.url,
      basicInfo.portrait,
      launchCharacter.portrait_url,
      launchCharacter.portrait?.url,
      launchCharacter.portrait
    );
    if (this._el.characterPortrait && this._el.characterPortraitWrap) {
      if (portraitUrl) {
        this._el.characterPortrait.src = portraitUrl;
        this._el.characterPortrait.alt = `${name} portrait`;
        this._el.characterPortraitWrap.style.display = '';
      } else {
        this._el.characterPortrait.removeAttribute('src');
        this._el.characterPortrait.alt = '';
        this._el.characterPortraitWrap.style.display = 'none';
      }
    }

    // Update basic info
    if (this._el.characterName) this._el.characterName.textContent = name;
    if (this._el.characterType) {
      const subtitleParts = [ancestry, characterClass].filter(Boolean);
      this._el.characterType.textContent = subtitleParts.length ? subtitleParts.join(' ') : 'Type —';
    }
    // Subtitle line: heritage, background
    if (this._el.characterSubtitle) {
      const subtitleDetails = [];
      if (heritage) subtitleDetails.push(heritage.charAt(0).toUpperCase() + heritage.slice(1));
      if (background) subtitleDetails.push(`Background: ${background}`);
      if (subtitleDetails.length) {
        this._el.characterSubtitle.textContent = subtitleDetails.join(' · ');
        this._el.characterSubtitle.style.display = '';
      }
      else {
        this._el.characterSubtitle.textContent = '';
        this._el.characterSubtitle.style.display = 'none';
      }
    }
    if (this._el.characterPersonality && this._el.characterPersonalityWrap) {
      this._el.characterPersonality.textContent = personalityText;
      this._el.characterPersonalityWrap.style.display = personalityText ? '' : 'none';
    }
    if (this._el.characterBackstory && this._el.characterBackstoryWrap) {
      this._el.characterBackstory.textContent = backstoryText;
      this._el.characterBackstoryWrap.style.display = backstoryText ? '' : 'none';
    }
    // "View Full Sheet" link
    if (this._el.characterFullSheetLink && sheetCharacterId) {
      this._el.characterFullSheetLink.href = this.buildCharacterSheetHref(sheetCharacterId);
      this._el.characterFullSheetLink.style.display = '';
    }
    this.showEmbeddedCharacterSheet(sheetCharacterId);
    if (this._el.characterAncestry) this._el.characterAncestry.textContent = ancestry || '—';
    if (this._el.characterLevel) this._el.characterLevel.textContent = level > 0 ? `Lvl ${level}` : 'Lvl —';

    // Update core stats
    if (this._el.characterHp) {
      this._el.characterHp.textContent = Number.isFinite(hpCurrent) && Number.isFinite(hpMax) ? `${hpCurrent}/${hpMax}` : '-';
    }
    if (this._el.characterAc) {
      this._el.characterAc.textContent = armorClass > 0 ? `${armorClass}` : '-';
    }
    if (this._el.characterHero) {
      this._el.characterHero.textContent = `${heroCurrent}/${heroMax}`;
    }
    if (this._el.characterSpeed) {
      this._el.characterSpeed.textContent = `${speed} ft`;
    }
    if (this._el.characterPerception) {
      this._el.characterPerception.textContent = formatMod(perception);
    }
    if (this._el.characterXp) {
      this._el.characterXp.textContent = xp;
    }

    // Update ability scores
    const abilityPairs = [
      ['Str', normalizedAbilities.strength],
      ['Dex', normalizedAbilities.dexterity],
      ['Con', normalizedAbilities.constitution],
      ['Int', normalizedAbilities.intelligence],
      ['Wis', normalizedAbilities.wisdom],
      ['Cha', normalizedAbilities.charisma]
    ];

    abilityPairs.forEach(([name, score]) => {
      const valueEl = this._el[`character${name}`];
      const modEl = this._el[`character${name}Mod`];
      if (valueEl) valueEl.textContent = score;
      if (modEl) modEl.textContent = calcMod(score);
    });

    // Update saving throws (prefer pre-computed saves from server)
    if (this._el.characterFort) {
      const fort = saves.fortitude?.base ?? saves.fortitude ?? defenses.fortitude?.base ?? defenses.fortitude ?? 0;
      this._el.characterFort.textContent = formatMod(fort);
    }
    if (this._el.characterRef) {
      const ref = saves.reflex?.base ?? saves.reflex ?? defenses.reflex?.base ?? defenses.reflex ?? 0;
      this._el.characterRef.textContent = formatMod(ref);
    }
    if (this._el.characterWill) {
      const will = saves.will?.base ?? saves.will ?? defenses.will?.base ?? defenses.will ?? 0;
      this._el.characterWill.textContent = formatMod(will);
    }

    // Update skills
    if (this._el.characterSkills) {
      if (Array.isArray(skills) && skills.length > 0) {
        this._el.characterSkills.innerHTML = skills
          .map(skill => {
            const name = skill.name || skill;
            const bonus = skill.modifier !== undefined ? (skill.modifier >= 0 ? `+${skill.modifier}` : skill.modifier) : '';
            const prof = skill.proficiency ? `<span class="skill-prof">${skill.proficiency}</span>` : '';
            return `<li><span>${name}</span>${prof}<span>${bonus}</span></li>`;
          })
          .join('');
      } else {
        this._el.characterSkills.innerHTML = '<li class="skills-empty">No skills</li>';
      }
    }

    // Update conditions
    if (this._el.characterConditions) {
      const conditionTooltips = state?.effectiveState?.sources?.condition_tooltips
        || state?.effectiveState?.sources?.conditionTooltips
        || {};
      if (Array.isArray(conditions) && conditions.length > 0) {
        this._el.characterConditions.innerHTML = conditions
          .map((condition) => this.renderConditionTooltipEntry(condition, conditionTooltips))
          .join('');
      } else {
        this._el.characterConditions.innerHTML = '<li class="conditions-empty">No conditions</li>';
      }
    }

    // Update currency
    if (this._el.characterGp) this._el.characterGp.textContent = currency.gp || 0;
    if (this._el.characterSp) this._el.characterSp.textContent = currency.sp || 0;
    if (this._el.characterCp) this._el.characterCp.textContent = currency.cp || 0;

    const activeCampaignId = Number(
      this.stateManager?.hexmap?.resolveCampaignId?.()
      || this.stateManager?.hexmap?.launchContext?.campaign_id
      || state.campaignId
      || launchCharacter.campaignId
      || 0
    ) || null;

    this.currentCharacterInventoryContext = {
      characterId,
      campaignId: activeCampaignId,
      inventory,
      equipment,
      currency,
      abilities: normalizedAbilities,
      isFollowerActor: Boolean(state.is_follower_actor || launchCharacter.is_follower_actor),
      followerKind: String(state.follower_kind || launchCharacter.follower_kind || '').trim().toLowerCase() || null,
    };
    this.currentCharacterContext = {
      runtimeCharacterId: Number(launchCharacter.id || launchCharacter.characterId || characterId || 0) || null,
      sheetCharacterId: Number(sheetCharacterId || 0) || null,
      sourceCharacterId: Number(state.source_character_id || launchCharacter.source_character_id || 0) || null,
      linkedCharacterId: Number(state.character_id || launchCharacter.character_id || 0) || null,
      campaignId: activeCampaignId,
    };
    if (storeAsPrimary) {
      this.primaryCharacterContext = { ...this.currentCharacterContext };
    }
    this.refreshActorSelector();
    this.syncSheetLinksForSelectedEntity();
    this.updateLibraryConversionAction();
    this.bus.emit('inventory:changed', this.currentCharacterInventoryContext);
    // Runtime actor payloads can arrive without item_instance_ids; force a canonical
    // inventory refresh so mutation controls bind to real instance rows.
    this.bus.emit('character:inventory-refresh-requested', this.currentCharacterInventoryContext);

    // Update features & feats (with type badges)
    if (this._el.characterFeatures) {
      const ancestryFeatures = features.ancestryFeatures || [];
      const classFeatures = features.classFeatures || [];
      // Use nested features.feats if available, fall back to the top-level
      // feats array from the legacy PHP payload.
      const featList = features.feats || feats || [];
      const allFeatures = [...ancestryFeatures, ...classFeatures, ...featList];
    
      if (allFeatures.length > 0) {
        this._el.characterFeatures.innerHTML = allFeatures
          .map(feat => {
            const featName = feat.name || feat;
            const featNameHtml = escapeTooltipAttr(featName);
            const featDescription = feat.description || feat.desc || feat.benefit || '';
            const featTraits = Array.isArray(feat.traits) ? feat.traits.join(', ') : (feat.traits || '');
            const featStats = [];
            const featEffectsList = [];
            const featMods = [];
            const featNotesList = [];
            const featId = feat.id || slugifyTooltipKey(featName);
            const featTypeLabel = feat.type ? String(feat.type) : 'feature';
            if (feat.type) featStats.push({ label: 'Type', value: feat.type });
            if (feat.level) featStats.push({ label: 'Level', value: `Lv ${feat.level}` });

            featActions
              .filter(action => tooltipSourceMatches(action?.id, featId))
              .forEach(action => {
                const actionLabel = formatTooltipActionCost(action.action_cost);
                featEffectsList.push(`${action.name || featName}${actionLabel ? ` (${actionLabel})` : ''}: ${action.description || 'No description.'}`);
                if (action.uses_remaining != null || action.uses_max != null) {
                  featMods.push({
                    stat: action.name || 'Uses',
                    val: `${action.uses_remaining ?? 0}/${action.uses_max ?? action.uses_remaining ?? 0} remaining`,
                  });
                }
              });

            featAugments
              .filter(augment => tooltipSourceMatches(augment?.id, featId))
              .forEach(augment => {
                featEffectsList.push(`${augment.name || 'Spell Augment'}: ${augment.description || 'Augments spellcasting.'}`);
                if (augment.range_bonus_feet != null) {
                  featMods.push({ stat: 'Range', val: `+${augment.range_bonus_feet} ft` });
                }
                if (augment.area_multiplier != null) {
                  featMods.push({ stat: 'Area', val: `x${augment.area_multiplier}` });
                }
                if (augment.spell_level != null) {
                  featMods.push({ stat: 'Spell Level', val: augment.spell_level });
                }
                if (augment.casting) {
                  featMods.push({ stat: 'Casting', val: String(augment.casting).replace(/_/g, ' ') });
                }
                if (augment.tradition) {
                  featMods.push({ stat: 'Tradition', val: augment.tradition });
                }
              });

            featSelections
              .filter(selection => selection?.source_feat === featId)
              .forEach(selection => {
                featEffectsList.push(selection.description || `${selection.count || 1} pending selections required.`);
                featMods.push({
                  stat: 'Selection',
                  val: `${selection.count || 1} ${selection.status || 'pending'}`.replace(/_/g, ' '),
                });
              });

            featRestResources
              .filter(resource => tooltipSourceMatches(resource?.id, featId))
              .forEach(resource => {
                featMods.push({
                  stat: resource.name || 'Uses',
                  val: `${resource.remaining ?? 0}/${resource.max ?? 0} (${String(resource.reset_on || 'rest').replace(/_/g, ' ')})`,
                });
              });

            if (Array.isArray(featTraining.skills) && featTraining.skills.length > 0 && featTypeLabel === 'skill') {
              featEffectsList.push(`Training grants: ${featTraining.skills.join(', ')}.`);
            }
            if (Array.isArray(featTraining.lore) && featTraining.lore.length > 0 && featTypeLabel === 'skill') {
              featEffectsList.push(`Lore grants: ${featTraining.lore.join(', ')}.`);
            }
            if (Array.isArray(featTraining.weapons) && featTraining.weapons.length > 0 && featTypeLabel !== 'skill') {
              featTraining.weapons.forEach(weaponGrant => {
                const examples = Array.isArray(weaponGrant.examples) && weaponGrant.examples.length > 0
                  ? ` (${weaponGrant.examples.join(', ')})`
                  : '';
                featEffectsList.push(`Weapon training: ${weaponGrant.group || 'weapon group'} ${weaponGrant.proficiency || ''}${examples}.`.trim());
              });
            }
            if (Array.isArray(featTraining.proficiencies) && featTraining.proficiencies.length > 0) {
              featTraining.proficiencies.forEach(prof => {
                featMods.push({
                  stat: prof.category || 'Proficiency',
                  val: `${prof.target || 'target'}: ${prof.rank || 'trained'}`,
                });
              });
            }

            Object.entries(featConditionalModifiers || {}).forEach(([category, entries]) => {
              if (!Array.isArray(entries)) {
                return;
              }
              entries
                .filter(entry => tooltipSourceMatches(entry?.id || entry?.source_feat, featId))
                .forEach(entry => {
                  featMods.push({
                    stat: category.replace(/_/g, ' '),
                    val: entry.description || entry.value || entry.modifier || entry.outcome || 'conditional effect',
                  });
                });
            });

            featNotes
              .filter(note => {
                const normalized = String(note ?? '').toLowerCase();
                return normalized.includes(String(featName).toLowerCase()) || normalized.includes(featId);
              })
              .forEach(note => featNotesList.push(note));

            featTodoReview.forEach(entry => {
              if (typeof entry === 'string' && entry.includes(featId)) {
                featNotesList.push(`Review pending: ${entry}`);
              } else if (entry && typeof entry === 'object' && tooltipSourceMatches(entry.id || entry.source_feat, featId)) {
                featNotesList.push(`Review pending: ${entry.reason || entry.description || featId}`);
              }
            });

            if (feat.benefit && feat.benefit !== featDescription) {
              featEffectsList.push(`Benefit: ${feat.benefit}`);
            }
            const featTypeKey = typeof feat.type === 'string'
              ? feat.type.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
              : '';
            const featType = feat.type ? `<span class="feat-type feat-type--${featTypeKey}">${escapeTooltipAttr(feat.type)}</span>` : '';
            const featLevel = feat.level ? `<span class="feat-level">Lv ${escapeTooltipAttr(feat.level)}</span>` : '';
            return `<li class="feat-entry feat-entry--detail" data-tooltip-enabled="true" data-tooltip-name="${featNameHtml}" data-tooltip-type="${escapeTooltipAttr(featTypeLabel)} feat" data-tooltip-desc="${escapeTooltipAttr(featDescription)}" data-tooltip-traits="${escapeTooltipAttr(featTraits)}" data-tooltip-stats="${escapeTooltipAttr(JSON.stringify(featStats))}" data-tooltip-effects="${escapeTooltipAttr(JSON.stringify(uniqueTooltipStrings(featEffectsList)))}" data-tooltip-mods="${escapeTooltipAttr(JSON.stringify(featMods))}" data-tooltip-notes="${escapeTooltipAttr(JSON.stringify(uniqueTooltipStrings(featNotesList)))}">${featType}${featNameHtml}${featLevel}</li>`;
          })
          .join('');
        window.dcAttachTooltips?.(this._el.characterFeatures);
      } else {
        this._el.characterFeatures.innerHTML = '<li class="features-empty">No features</li>';
      }
    }

    // Update spellcasting section
    if (this._el.characterSpellsSection && this._el.characterSpells) {
      const rankGroups = collectSpellRankGroups(spells);
      const hasSpells = rankGroups.length > 0 || Boolean(spells.tradition || spells.casting_ability);
      this._el.characterSpellsSection.style.display = hasSpells ? '' : 'none';
      if (hasSpells) {
        const runtimeSlots = normalizeDisplayedSpellSlots(resources?.spellSlots, spells.slots);
        // Spell meta info
        if (this._el.characterSpellMeta) {
          const metaParts = [];
          if (spells.tradition) metaParts.push(`Tradition: ${spells.tradition}`);
          if (spells.casting_ability) metaParts.push(`Ability: ${spells.casting_ability.toUpperCase()}`);
          const slotParts = Object.entries(runtimeSlots)
            .sort(([a], [b]) => Number(a) - Number(b))
            .map(([k, slot]) => {
              const label = formatSpellRankLabel(Number(k));
              const current = Number(slot?.current ?? slot?.max ?? 0);
              const max = Number(slot?.max ?? current);
              return `${label}: ${current}/${max}`;
            });
          if (spells.slots?.cantrips) {
            slotParts.unshift(`cantrips: ${spells.slots.cantrips}`);
          }
          if (slotParts.length === 0 && spells.slots) {
            Object.entries(spells.slots).forEach(([k, v]) => {
              if (k !== 'cantrips') {
                slotParts.push(`${k}: ${v}`);
              }
            });
          }
          if (slotParts.length > 0) {
            metaParts.push(`Slots: ${slotParts.join(', ')}`);
          }
          this._el.characterSpellMeta.innerHTML = metaParts.map(p => `<span class="spell-meta-item">${p}</span>`).join('');
        }
        // Spell list
        const spellEntries = [];
        rankGroups.forEach(({ rank, label, spells: rankSpells }) => {
          const slotState = rank > 0 ? runtimeSlots[String(rank)] || null : null;
          const headerLabel = rank > 0 && slotState
            ? `${label} - Slots ${slotState.current}/${slotState.max}`
            : label;
          spellEntries.push(`<li class="spell-rank-header">${escapeQuestHtml(headerLabel)}</li>`);
          rankSpells.forEach(s => {
            const spellId = typeof s === 'string' ? s : (s.id || s.spell_id || '');
            const spellName = typeof s === 'string' ? s.replace(/_/g, ' ') : (s.name || s);
            const spellNameHtml = escapeTooltipAttr(spellName);
            const spellDescription = typeof s === 'object' ? (s.description || s.desc || '') : '';
            const spellTraits = typeof s === 'object'
              ? (Array.isArray(s.traits) ? s.traits.join(', ') : (s.traits || ''))
              : '';
            const spellStats = rank === 0
              ? [
                { label: 'Rank', value: 'Cantrip' },
                { label: 'Cast Rank', value: Math.max(1, Math.ceil(level / 2)) },
                ...(spells.tradition ? [{ label: 'Tradition', value: spells.tradition }] : []),
                ...(spells.casting_ability ? [{ label: 'Ability', value: spells.casting_ability.toUpperCase() }] : []),
              ]
              : [
                { label: 'Rank', value: label },
                ...(slotState ? [{ label: 'Slots', value: `${slotState.current}/${slotState.max}` }] : []),
                ...(spells.tradition ? [{ label: 'Tradition', value: spells.tradition }] : []),
                ...(spells.casting_ability ? [{ label: 'Ability', value: spells.casting_ability.toUpperCase() }] : []),
              ];
            const spellType = rank === 0 ? 'cantrip spell' : 'spell';
            spellEntries.push(`<li class="spell-entry spell-entry--detail" data-tooltip-enabled="true" data-tooltip-resolver="spell" data-item-id="${escapeTooltipAttr(spellId)}" data-tooltip-name="${spellNameHtml}" data-tooltip-type="${spellType}" data-tooltip-desc="${escapeTooltipAttr(spellDescription)}" data-tooltip-traits="${escapeTooltipAttr(spellTraits)}" data-tooltip-stats="${escapeTooltipAttr(JSON.stringify(spellStats))}">${spellNameHtml}</li>`);
          });
        });
        const rawFeatActionBuckets = state.actions?.availableActions?.feat || featEffects.available_actions || {};
        const spellActionBuckets = Array.isArray(rawFeatActionBuckets)
          ? [['actions', rawFeatActionBuckets]]
          : Object.entries(rawFeatActionBuckets || {});
        const spellActionLabels = {
          actions: 'Spell Actions',
          at_will: 'At-Will Spell Actions',
          per_short_rest: 'Short-Rest Spell Actions',
          per_long_rest: 'Long-Rest Spell Actions',
          spellshape: 'Spellshape Actions',
          metamagic: 'Metamagic Actions',
        };
        spellActionBuckets.forEach(([bucketKey, actions]) => {
          const actionList = Array.isArray(actions) ? actions.filter(Boolean) : [];
          if (actionList.length === 0) {
            return;
          }
          spellEntries.push(`<li class="spell-rank-header spell-rank-header--actions">${escapeQuestHtml(spellActionLabels[bucketKey] || String(bucketKey).replace(/_/g, ' '))}</li>`);
          actionList.forEach(action => {
            const actionName = action?.name || 'Spell Action';
            const actionNameHtml = escapeTooltipAttr(actionName);
            const actionCost = formatTooltipActionCost(action?.action_cost);
            const actionDescription = action?.description || 'Spellcasting action.';
            const actionStats = [
              ...(actionCost ? [{ label: 'Cost', value: actionCost }] : []),
              ...(action?.uses_remaining != null || action?.uses_max != null
                ? [{ label: 'Uses', value: `${action.uses_remaining ?? 0}/${action.uses_max ?? action.uses_remaining ?? 0}` }]
                : []),
            ];
            const actionLabel = actionCost ? `${actionNameHtml} <span class="spell-action-cost">${escapeQuestHtml(actionCost)}</span>` : actionNameHtml;
            spellEntries.push(`<li class="spell-entry spell-entry--detail spell-entry--action" data-tooltip-enabled="true" data-tooltip-name="${actionNameHtml}" data-tooltip-type="spell action" data-tooltip-desc="${escapeTooltipAttr(actionDescription)}" data-tooltip-stats="${escapeTooltipAttr(JSON.stringify(actionStats))}">${actionLabel}</li>`);
          });
        });
        this._el.characterSpells.innerHTML = spellEntries.length > 0
          ? spellEntries.join('')
          : '<li class="spells-empty">No spells</li>';
        window.dcAttachTooltips?.(this._el.characterSpells);
      } else {
        this._el.characterSpellsSection.style.display = 'none';
      }
    }

    console.log('[CharacterPanel] showLaunchCharacter:done', {
      name: this._el.characterName?.textContent,
      hp: this._el.characterHp?.textContent,
      ac: this._el.characterAc?.textContent,
      level: this._el.characterLevel?.textContent,
      legacyStyle: this._el.characterSheetLegacy?.style?.display ?? 'no-el',
      gamePanelHidden: document.getElementById('game-panel-party')?.hidden ?? 'no-el',
    });
  }

  updateLibraryConversionAction() {
    const button = this._el.characterConvertLibraryButton;
    if (!button) {
      return;
    }

    const context = this.currentCharacterContext || {};
    const campaignId = Number(context.campaignId || 0) || 0;
    const runtimeCharacterId = Number(context.runtimeCharacterId || context.sheetCharacterId || 0) || 0;
    const sourceCharacterId = Number(context.sourceCharacterId || 0) || 0;
    const linkedCharacterId = Number(context.linkedCharacterId || 0) || 0;
    const alreadyLinked = sourceCharacterId > 0 || (linkedCharacterId > 0 && linkedCharacterId !== runtimeCharacterId);
    const canConvert = campaignId > 0 && runtimeCharacterId > 0 && !alreadyLinked && !this._convertToLibraryInFlight;

    button.style.display = canConvert ? '' : 'none';
    button.disabled = !canConvert;
  }

  async convertCurrentCharacterToLibrary() {
    if (this._convertToLibraryInFlight) {
      return;
    }

    const context = this.currentCharacterContext || {};
    const campaignId = Number(context.campaignId || 0) || 0;
    const runtimeCharacterId = Number(context.runtimeCharacterId || context.sheetCharacterId || 0) || 0;
    if (campaignId <= 0 || runtimeCharacterId <= 0) {
      return;
    }

    this._convertToLibraryInFlight = true;
    this.updateLibraryConversionAction();

    try {
      const response = await fetch(`/api/character/${encodeURIComponent(runtimeCharacterId)}/convert-library`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ campaignId }),
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result?.success || !result?.data) {
        throw new Error(result?.error || `Library conversion failed (${response.status}).`);
      }

      const converted = result.data || {};
      const libraryCharacterId = Number(converted.library_character_id || 0) || null;
      const refreshedRuntimeId = Number(converted.runtime_character_id || runtimeCharacterId) || runtimeCharacterId;
      this.currentCharacterContext = {
        ...context,
        runtimeCharacterId: refreshedRuntimeId,
        sourceCharacterId: libraryCharacterId,
        linkedCharacterId: libraryCharacterId,
      };

      this.bus?.emit?.('chat:system-message', {
        speaker: 'System',
        kind: 'success',
        text: 'Character saved to the permanent library.',
      });

      const hexmap = this.stateManager?.hexmap;
      await hexmap?.loadCharacterFromApi?.(refreshedRuntimeId);
    } catch (error) {
      console.error('[CharacterPanel] convertCurrentCharacterToLibrary failed', error);
      this.bus?.emit?.('chat:system-message', {
        speaker: 'System',
        kind: 'error',
        text: error?.message || 'Unable to save this character to the library.',
      });
    } finally {
      this._convertToLibraryInFlight = false;
      this.updateLibraryConversionAction();
    }
  }

}
