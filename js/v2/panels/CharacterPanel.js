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

export class CharacterPanel {
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
    this._el = {};
    this.stateManager = null;
    this.dungeonData = null;
    this.currentCharacterInventoryContext = null;
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
    this.setupCharacterSheetSections();
    this._initSidebarTabs();
  }

  /**
   * Wire sidebar sub-tab buttons (Character / Spells & Feats / Inventory / Quests).
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
        if (tab.dataset.sidebarTab === 'quests') {
          this.bus.emit('quest:refresh-requested', { source: 'character-sidebar-tab' });
        }
        console.log('[CharacterPanel] sidebar tab clicked', { target: targetId, panelVisible: !!document.getElementById(targetId) && !document.getElementById(targetId).classList.contains('dc-is-hidden') });
      };
      tab.addEventListener('click', handler);
      this._unsubs.push(() => tab.removeEventListener('click', handler));
    });
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
  }

  _subscribe() {
    this._unsubs.push(
      this.bus.on('entity:selected',   (d) => this.showEntityInfo(d?.entity)),
      this.bus.on('entity:deselected', () => this.hideEntityInfo()),
      this.bus.on('game:init',         (d) => {
        if (d?.launchCharacter) this.showLaunchCharacter(d.launchCharacter);
      }),
      this.bus.on('character:updated', (d) => {
        const launchCharacter = d?.launchCharacter
          || this.stateManager?.hexmap?.launchCharacter
          || this.stateManager?.hexmap?.characterData
          || null;
        if (launchCharacter) this.showLaunchCharacter(launchCharacter);
      }),
      this.bus.on('character:sheet-requested', (d) => {
        if (d?.characterId) this.showEmbeddedCharacterSheet(d.characterId);
      }),
    );
  }

  hideEntityInfo() {
    if (this._el.entityInfoPanel) {
      this._el.entityInfoPanel.classList.add('dc-is-hidden');
      this._el.entityInfoPanel.style.display = 'none';
      this._el.entityInfoPanel.setAttribute('aria-hidden', 'true');
    }
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
    if (tabId === 'quests') {
      this.bus.emit('quest:refresh-requested', { source: 'character-sidebar-programmatic' });
    }
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
      gamePanelHidden: document.getElementById('game-panel-character')?.hidden ?? 'no-el',
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

    // NOTE: Character sheet (character* elements) is only populated by
    // showLaunchCharacter() with the PC's full data.  Do NOT overwrite it
    // here — this method fires for every selected entity including NPCs.
  }

  showLaunchCharacter(launchCharacter) {
    if (!launchCharacter || typeof launchCharacter !== 'object') {
      return;
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
    const portraitUrl = state.portrait_url || state.portrait || launchCharacter.portrait_url || launchCharacter.portrait || null;
    if (this._el.characterPortrait && this._el.characterPortraitWrap) {
      if (portraitUrl) {
        this._el.characterPortrait.src = portraitUrl;
        this._el.characterPortrait.alt = `${name} portrait`;
        this._el.characterPortraitWrap.style.display = '';
      } else {
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
      this._el.characterFullSheetLink.href = `/characters/${sheetCharacterId}`;
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
      if (Array.isArray(conditions) && conditions.length > 0) {
        const conditionNames = conditions.map(c => typeof c === 'string' ? c : (c.name || 'Unknown'));
        this._el.characterConditions.innerHTML = conditionNames
          .map(name => `<li>${name}</li>`)
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
    };
    this.bus.emit('inventory:changed', this.currentCharacterInventoryContext);

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

    this.bus.emit('character:updated');
    console.log('[CharacterPanel] showLaunchCharacter:done', {
      name: this._el.characterName?.textContent,
      hp: this._el.characterHp?.textContent,
      ac: this._el.characterAc?.textContent,
      level: this._el.characterLevel?.textContent,
      legacyStyle: this._el.characterSheetLegacy?.style?.display ?? 'no-el',
      gamePanelHidden: document.getElementById('game-panel-character')?.hidden ?? 'no-el',
    });
  }

}
