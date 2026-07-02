/**
 * @file
 * Hex map rendering with PixiJS + ECS architecture.
 */

// Import ECS modules
import { EntityManager, PositionComponent, RenderComponent, IdentityComponent, EntityType, RenderSystem, MovementComponent, StatsComponent, MovementSystem, MovementMode, ActionsComponent, ActionType, ActionCost, CombatComponent, Team, TurnManagementSystem, CombatState, CombatSystem, AttackResult } from './ecs/index.js';
import combatApi from './hexmap-api.js';
import { HexmapStateSync } from './HexmapStateSync.js';
import { GameCoordinator } from './game-coordinator/GameCoordinator.js';
import { ChatSessionApi } from './ChatSessionApi.js';
import { SpriteService } from './SpriteService.js';

// Ensure Drupal and once are available
/* global Drupal, once, PIXI */

(function (Drupal, once) {
  'use strict';

  function resolveQuestTitle(quest) {
    if (!quest || typeof quest !== 'object') {
      return 'Unknown Quest';
    }
    return quest.title || quest.quest_name || quest.name || quest.quest_key || quest.quest_id || quest.id || 'Unknown Quest';
  }

  function escapeQuestHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function extractQuestPhases(quest) {
    if (!quest || typeof quest !== 'object') {
      return [];
    }
    if (Array.isArray(quest.generated_objectives) && quest.generated_objectives.length > 0) {
      return quest.generated_objectives;
    }
    if (Array.isArray(quest.objective_states) && quest.objective_states.some(phase => phase && Array.isArray(phase.objectives))) {
      return quest.objective_states;
    }
    return [];
  }

  function buildObjectiveStateIndex(quest) {
    const index = {};
    if (!quest || !Array.isArray(quest.objective_states)) {
      return index;
    }

    for (const entry of quest.objective_states) {
      if (!entry || typeof entry !== 'object') {
        continue;
      }

      if (Array.isArray(entry.objectives)) {
        for (const objective of entry.objectives) {
          const objectiveId = objective?.objective_id;
          if (!objectiveId) {
            continue;
          }
          index[objectiveId] = {
            current: Number(objective.current || 0),
            target: Number(objective.target_count || 1),
            description: objective.description || objectiveId,
            completed: Boolean(objective.completed),
          };
        }
        continue;
      }

      const objectiveId = entry.objective_id;
      if (!objectiveId) {
        continue;
      }
      index[objectiveId] = {
        current: Number(entry.current || 0),
        target: Number(entry.target || entry.target_count || 1),
        description: entry.description || objectiveId,
        completed: Boolean(entry.completed),
      };
    }

    return index;
  }

  function mergeObjectiveProgress(baseObjective, objectiveIndex) {
    const objectiveId = baseObjective?.objective_id;
    const merged = {
      objective_id: objectiveId,
      type: baseObjective?.type || '',
      description: baseObjective?.description || objectiveId || '',
      target_count: Number(baseObjective?.target_count || 1),
      current: Number(baseObjective?.current || 0),
      completed: Boolean(baseObjective?.completed),
    };

    if (objectiveId && objectiveIndex[objectiveId]) {
      const state = objectiveIndex[objectiveId];
      merged.current = Math.max(merged.current, Number(state.current || 0));
      merged.target_count = Number(merged.target_count || state.target || 1);
      if (!baseObjective?.description) {
        merged.description = state.description || merged.description;
      }
      merged.completed = merged.completed || Boolean(state.completed) || merged.current >= merged.target_count;
    } else {
      merged.completed = merged.completed || merged.current >= merged.target_count;
    }

    return merged;
  }

  /**
   * UIManager — [THIN-CLIENT: shell UI + chat adapter]
   *
   * Responsibilities (presentation only):
   *   - DOM interactions and UI updates for the hexmap shell
   *   - Chat adapter: rendering messages, managing channels, submitting chat
   *
   * NOT responsible for:
   *   - Gameplay rules or state mutations (GameCoordinator owns that)
   *   - Server state polling (HexmapStateSync owns that)
   *   - API calls for combat actions (hexmap-api.js owns that)
   */
  class UIManager {
    constructor(stateManager = null) {
      this.stateManager = stateManager;
      this.elements = {};
      this.embeddedCharacterSheetUrl = null;
      this.lastServerMessageAt = 0;
      this.serverMessageCooldownMs = 3000;
      // Channel state
      this.activeChannel = 'room';
      this.channels = { room: { key: 'room', label: 'Room', type: 'room', active: true } };
      // Session view state
      this.activeSessionView = 'room'; // room | narrative | party | gm-private | system-log
      /** @type {ChatSessionApi|null} */
      this.chatSessionApi = null;
      this.setupActionFooterToggle();
      this.setupFullscreenToggle();
      this.cacheElements();
      this.setupPartyRailHandlers();
      this.setupChatLog();
      this.setupChannelTabs();
      this.setupSessionViewTabs();
    }

    /**
     * Ensure the action footer exists in the DOM even if the template is missing it.
     */
    ensureActionFooter() {
        return;
    }

    setupActionFooterToggle() {
      const footer = document.getElementById('action-footer');
      if (!footer) {
        return;
      }

      const toggle = footer.querySelector('#action-footer-toggle');
      if (toggle && toggle.dataset.bound !== 'true') {
        toggle.dataset.bound = 'true';
        toggle.addEventListener('click', () => {
          const collapsed = footer.classList.toggle('collapsed');
          toggle.textContent = collapsed ? 'Actions ▸' : 'Actions ▾';
        });
      }

      const sections = footer.querySelectorAll('.action-section');
      sections.forEach((section) => {
        const header = section.querySelector('.action-section__header');
        const body = section.querySelector('.action-section__body');
        if (!header || !body || header.dataset.bound === 'true') {
          return;
        }
        header.dataset.bound = 'true';
        header.addEventListener('click', () => {
          const collapsed = section.classList.toggle('collapsed');
          const chevron = section.querySelector('.action-section__chevron');
          if (chevron) {
            chevron.textContent = collapsed ? '▸' : '▾';
          }
        });
      });

      this.applyInitialSectionState(footer, sections);
    }

    applyInitialSectionState(footer, sections) {
      if (!footer || footer.dataset.initialStateApplied === 'true') {
        return;
      }

      const isMobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
      if (isMobile && sections && sections.length) {
        sections.forEach((section) => {
          section.classList.add('collapsed');
          const chevron = section.querySelector('.action-section__chevron');
          if (chevron) {
            chevron.textContent = '▸';
          }
        });
      }

      footer.dataset.initialStateApplied = 'true';
    }

    setupFullscreenToggle() {
      const btn = document.getElementById('fullscreen-toggle');
      if (!btn || btn.dataset.bound === 'true') {
        return;
      }

      btn.dataset.bound = 'true';
      btn.addEventListener('click', () => {
        const container = document.getElementById('hexmap-container');
        if (!container) {
          return;
        }

        const isFullscreen = document.fullscreenElement !== null;

        if (isFullscreen) {
          // Exit fullscreen
          document.exitFullscreen().catch(() => {});
          btn.textContent = '⛶';
          container.classList.remove('fullscreen');
        } else {
          // Enter fullscreen
          container.requestFullscreen().catch(() => {});
          btn.textContent = '⛌';
          container.classList.add('fullscreen');
        }
      });

      // Listen for fullscreen change events (e.g., user presses Esc)
      document.addEventListener('fullscreenchange', () => {
        const btn = document.getElementById('fullscreen-toggle');
        const isFullscreen = document.fullscreenElement !== null;
        if (btn) {
          btn.textContent = isFullscreen ? '⛌' : '⛶';
          const container = document.getElementById('hexmap-container');
          if (container) {
            container.classList.toggle('fullscreen', isFullscreen);
          }
        }
      });
    }

    /**
     * Cache frequently accessed DOM elements.
     */
    cacheElements() {
      this.elements = {
        hoveredHex: document.getElementById('hovered-hex'),
        hoveredObject: document.getElementById('hovered-object'),
        selectedHex: document.getElementById('selected-hex'),
        currentRound: document.getElementById('current-round'),
        initiativeList: document.getElementById('initiative-list'),
        combatControls: document.getElementById('combat-controls'),
        startCombatBtn: document.getElementById('start-combat'),
        endCombatBtn: document.getElementById('end-combat'),
        initiativeTracker: document.getElementById('initiative-tracker'),
        entityInfoPanel: document.getElementById('entity-info-panel'),
        entityName: document.getElementById('entity-name'),
        entityType: document.getElementById('entity-type'),
        entityImageWrap: document.getElementById('entity-image-wrap'),
        entityImage: document.getElementById('entity-image'),
        entitySummary: document.getElementById('entity-summary'),
        entityDescription: document.getElementById('entity-description'),
        entityKnownDetails: document.getElementById('entity-known-details'),
        entityTeam: document.getElementById('entity-team'),
        entityHp: document.getElementById('entity-hp'),
        entityAc: document.getElementById('entity-ac'),
        entityActions: document.getElementById('entity-actions'),
        entityMovement: document.getElementById('entity-movement'),
        zoomLevel: document.getElementById('zoom-level'),
        hexDetailRoom: document.getElementById('hex-detail-room'),
        hexDetailTerrain: document.getElementById('hex-detail-terrain'),
        hexDetailElevation: document.getElementById('hex-detail-elevation'),
        hexDetailLighting: document.getElementById('hex-detail-lighting'),
        hexDetailPassability: document.getElementById('hex-detail-passability'),
        hexDetailObjects: document.getElementById('hex-detail-objects'),
        hexDetailEntities: document.getElementById('hex-detail-entities'),
        hexDetailConnection: document.getElementById('hex-detail-connection'),
        selectedHexContentsSummary: document.getElementById('selected-hex-contents-summary'),
        selectedHexContentsEmpty: document.getElementById('selected-hex-contents-empty'),
        selectedHexContentsList: document.getElementById('selected-hex-contents-list'),

        // Turn clarity HUD
        turnHud: document.getElementById('turn-hud'),
        turnOwner: document.getElementById('turn-owner'),
        turnActionSummary: document.getElementById('turn-action-summary'),
        turnMoveSummary: document.getElementById('turn-move-summary'),
        turnReaction: document.getElementById('turn-reaction'),
        turnActionChips: document.getElementById('turn-action-chips'),
        actionInstruction: document.getElementById('action-instruction'),
        actionMenu: document.getElementById('action-menu'),
        actionMoveBtn: document.getElementById('action-move'),
        actionAttackBtn: document.getElementById('action-attack'),
        actionInteractBtn: document.getElementById('action-interact'),
        actionTalkBtn: document.getElementById('action-talk'),
        endTurnBtn: document.getElementById('end-turn'),

        // Character sheet panel
        characterSheetEmbedWrap: document.getElementById('char-sheet-embed-wrap'),
        characterSheetEmbed: document.getElementById('char-sheet-embed'),
        characterSheetLegacy: document.getElementById('char-sheet-legacy'),
        characterPortraitWrap: document.getElementById('char-portrait-wrap'),
        characterPortrait: document.getElementById('char-portrait'),
        characterName: document.getElementById('char-name'),
        characterType: document.getElementById('char-type'),
        characterSubtitle: document.getElementById('char-subtitle'),
        characterFullSheetLink: document.getElementById('char-full-sheet-link'),
        characterAncestry: document.getElementById('char-ancestry'),
        characterLevel: document.getElementById('char-level'),
        characterHp: document.getElementById('char-hp'),
        characterAc: document.getElementById('char-ac'),
        characterHero: document.getElementById('char-hero'),
        characterSpeed: document.getElementById('char-speed'),
        characterPerception: document.getElementById('char-perception'),
        characterXp: document.getElementById('char-xp'),
        characterStr: document.getElementById('char-str'),
        characterStrMod: document.getElementById('char-str-mod'),
        characterDex: document.getElementById('char-dex'),
        characterDexMod: document.getElementById('char-dex-mod'),
        characterCon: document.getElementById('char-con'),
        characterConMod: document.getElementById('char-con-mod'),
        characterInt: document.getElementById('char-int'),
        characterIntMod: document.getElementById('char-int-mod'),
        characterWis: document.getElementById('char-wis'),
        characterWisMod: document.getElementById('char-wis-mod'),
        characterCha: document.getElementById('char-cha'),
        characterChaMod: document.getElementById('char-cha-mod'),
        characterFort: document.getElementById('char-fort'),
        characterRef: document.getElementById('char-ref'),
        characterWill: document.getElementById('char-will'),
        characterSkills: document.getElementById('char-skills'),
        characterConditions: document.getElementById('char-conditions'),
        characterGp: document.getElementById('char-gp'),
        characterSp: document.getElementById('char-sp'),
        characterCp: document.getElementById('char-cp'),
        characterInventory: document.getElementById('char-inventory'),
        characterFeatures: document.getElementById('char-features'),
        characterSpellsSection: document.getElementById('char-spells-section'),
        characterSpellMeta: document.getElementById('char-spell-meta'),
        characterSpells: document.getElementById('char-spells'),

        // Dialog log & chat
        chatLog: document.getElementById('chat-log'),
        chatForm: document.getElementById('chat-form'),
        chatInput: document.getElementById('chat-input'),
        chatSend: document.getElementById('chat-send'),
        chatChannelTabs: document.getElementById('chat-channel-tabs'),
        chatChannelIndicator: document.getElementById('chat-channel-indicator'),
        chatChannelLabel: document.getElementById('chat-channel-label'),
        chatSessionTabs: document.getElementById('chat-session-tabs'),
        chatPanelTitle: document.getElementById('chat-panel-title'),

        // Quest journal panel
        questJournal: document.getElementById('quest-journal'),
        questList: document.getElementById('quest-list'),
        questCount: document.getElementById('quest-count'),
        questConfirmationCount: document.getElementById('quest-confirmation-count'),
        questConfirmationList: document.getElementById('quest-confirmation-list')
      };

      this.setupCharacterSheetSections();
    }

    /**
     * Bind delegated click/keyboard handlers on the initiative list for party-rail card selection.
     * Called once from constructor; works with dynamically replaced card HTML.
     */
    setupPartyRailHandlers() {
      const list = this.elements.initiativeList;
      if (!list) return;

      list.addEventListener('click', (e) => {
        const card = e.target.closest('.rail-card[data-entity-id]');
        if (!card) return;
        const entityId = card.dataset.entityId;
        const hexmap = this.stateManager?.hexmap;
        if (!hexmap || !entityId) return;
        const entity = hexmap.entityManager?.getEntity(entityId);
        if (entity) hexmap.selectEntity(entity);
      });

      list.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('.rail-card[data-entity-id]');
        if (card) card.click();
      });
    }

    /**
     * Setup collapsible character sheet sections.
     */
    setupCharacterSheetSections() {
      const sectionHeaders = document.querySelectorAll('.character-sheet__section .section-header');
      sectionHeaders.forEach(header => {
        if (header.dataset.bound === 'true') return;
        header.dataset.bound = 'true';

        header.addEventListener('click', () => {
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
        });
      });
    }

    /**
     * Update hovered hex display.
     */
    updateHoveredHex(q, r) {
      if (this.elements.hoveredHex) {
        this.elements.hoveredHex.textContent = q !== null ? `(${q}, ${r})` : 'None';
      }
    }

    /**
     * Update hovered object label display.
     */
    updateHoveredObject(label) {
      if (this.elements.hoveredObject) {
        this.elements.hoveredObject.textContent = label || 'None';
      }
    }

    /**
     * Update selected hex display.
     */
    updateSelectedHex(q, r) {
      if (this.elements.selectedHex) {
        this.elements.selectedHex.textContent = `(${q}, ${r})`;
      }
    }

    /**
     * Update current turn display.
     */
    updateCurrentTurn(name, actions, movement, hasReaction, team = null, isPlayersTurn = false) {
      if (this.elements.currentTurn) {
        const turnLabel = isPlayersTurn ? 'Your turn' : (team ? `${team} turn` : 'Turn');
        const reactionBadge = hasReaction ? '<span class="pill pill-positive">Reaction ready</span>' : '<span class="pill pill-muted">Reaction spent</span>';
        this.elements.currentTurn.innerHTML = `
          <div class="turn-name">${name}</div>
          <div class="turn-sub">
            <span class="pill pill-strong">${turnLabel}</span>
            ${reactionBadge}
          </div>`;
      }

      if (this.elements.turnOwner) {
        this.elements.turnOwner.textContent = isPlayersTurn ? 'Your turn' : (team ? `${team} turn` : 'Awaiting combat');
      }

      const maxActions = actions ? actions.maxActions + (actions.actionBonus || 0) : null;
      if (this.elements.turnActionSummary) {
        const remaining = actions ? `${actions.actionsRemaining}/${maxActions} actions` : 'Actions: -';
        this.elements.turnActionSummary.textContent = remaining;
      }

      if (this.elements.turnMoveSummary) {
        const moveText = movement && Number.isFinite(movement.movementRemaining)
          ? `${movement.movementRemaining} ft left`
          : 'Movement: -';
        this.elements.turnMoveSummary.textContent = moveText;
      }

      if (this.elements.turnReaction) {
        this.elements.turnReaction.textContent = hasReaction ? 'Reaction ready' : 'Reaction spent';
        this.elements.turnReaction.classList.toggle('pill-positive', !!hasReaction);
        this.elements.turnReaction.classList.toggle('pill-muted', !hasReaction);
      }

      if (this.elements.turnActionChips) {
        const canAct = actions ? actions.actionsRemaining > 0 : false;
        const moveLeft = movement ? movement.movementRemaining > 0 : false;
        this.elements.turnActionChips.innerHTML = `
          <span class="chip ${moveLeft ? 'chip-live' : 'chip-dim'}">Move</span>
          <span class="chip ${canAct ? 'chip-live' : 'chip-dim'}">Strike</span>
          <span class="chip ${canAct ? 'chip-live' : 'chip-dim'}">Interact</span>
          <span class="chip chip-live">Talk</span>
          <span class="chip chip-end">End Turn</span>`;
      }

      if (this.elements.actionInstruction) {
        if (!isPlayersTurn) {
          this.elements.actionInstruction.textContent = 'Watching enemy turn...';
        } else if (actions && actions.actionsRemaining > 0) {
          this.elements.actionInstruction.textContent = 'Select a hostile target to attack or click a blue hex to move.';
        } else if (movement && movement.movementRemaining > 0) {
          this.elements.actionInstruction.textContent = 'Move to a blue hex, then end turn.';
        } else {
          this.elements.actionInstruction.textContent = 'No actions left — end your turn.';
        }
      }

      this.renderActionButtons(actions, movement, isPlayersTurn);
    }

    /**
     * Update action mode buttons and instruction text.
     */
    updateActionMode(mode, { canAct = false, canInteract = false, moveLeft = 0, isPlayersTurn = false } = {}) {
      const { actionMoveBtn, actionAttackBtn, actionInteractBtn, actionInstruction } = this.elements;

      const setActive = (btn, active) => {
        if (!btn) return;
        btn.classList.toggle('btn-active', !!active);
      };

      setActive(actionMoveBtn, mode === 'move');
      setActive(actionAttackBtn, mode === 'attack');
      setActive(actionInteractBtn, mode === 'interact');

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
          ? (canInteract ? 'Interact with nearby objects, doors, and room transitions' : 'No interaction actions available')
          : 'Not your turn';
      }

      if (actionInstruction) {
        if (!isPlayersTurn) {
          actionInstruction.textContent = 'Watching enemy turn...';
        } else if (mode === 'move') {
          actionInstruction.textContent = moveLeft > 0 ? `Click a blue hex to move (${moveLeft} ft left).` : 'No movement left; switch to attack or end turn.';
        } else if (mode === 'interact') {
          actionInstruction.textContent = canInteract ? 'Click an adjacent item, NPC, door, or obstacle to interact.' : 'No interaction actions remaining; attack, move, or end turn.';
        } else {
          actionInstruction.textContent = canAct ? 'Select a hostile target to attack.' : 'No actions remaining; move or end turn.';
        }
      }
    }

    renderActionButtons(actions, movement, isPlayersTurn) {
      const { actionMoveBtn, actionAttackBtn, actionInteractBtn, actionTalkBtn, endTurnBtn } = this.elements;
      const maxActions = actions ? actions.maxActions + (actions.actionBonus || 0) : null;
      const actionsRemaining = actions ? actions.actionsRemaining : 0;
      const canAct = !!(isPlayersTurn && actions && actions.canAct !== false && actionsRemaining > 0);
      const canMove = !!(isPlayersTurn && movement && Number.isFinite(movement.movementRemaining) && movement.movementRemaining > 0);
      const canInteract = canAct;

      const applyDisabledState = (button, disabled) => {
        if (!button) {
          return;
        }
        button.classList.toggle('btn-disabled', !!disabled);
        button.disabled = !!disabled;
        button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      };

      if (actionMoveBtn) {
        const moveLabel = movement && Number.isFinite(movement.movementRemaining)
          ? `Move (${movement.movementRemaining} ft)`
          : 'Move';
        actionMoveBtn.textContent = moveLabel;
        applyDisabledState(actionMoveBtn, !canMove);
      }

      if (actionAttackBtn) {
        const attackLabel = maxActions !== null
          ? `Attack (${actionsRemaining}/${maxActions})`
          : 'Attack';
        actionAttackBtn.textContent = attackLabel;
        applyDisabledState(actionAttackBtn, !canAct);
      }

      if (actionInteractBtn) {
        actionInteractBtn.textContent = maxActions !== null
          ? `Interact (${actionsRemaining}/${maxActions})`
          : 'Interact';
        applyDisabledState(actionInteractBtn, !canInteract);
      }

      if (actionTalkBtn) {
        actionTalkBtn.textContent = 'Talk (Free)';
        applyDisabledState(actionTalkBtn, !isPlayersTurn);
      }

      if (endTurnBtn) {
        applyDisabledState(endTurnBtn, !isPlayersTurn);
      }
    }

    /**
     * Update round display.
     */
    updateRound(roundNumber) {
      if (this.elements.currentRound) {
        this.elements.currentRound.textContent = `Round ${roundNumber}`;
      }
    }

    /**
     * Update initiative tracker with rich party-rail participant cards.
     * Each card shows: initiative badge, name, team badge, HP bar, and (for the
     * active combatant) action-pip state.  Enemy HP is shown as a coloured state
     * bar only; player-team HP is shown with exact values.
     * Clicking a card selects the corresponding entity on the board.
     */
    updateInitiativeTracker(initiativeOrder) {
      if (!this.elements.initiativeList) return;

      let html = '';
      initiativeOrder.forEach((data) => {
        const combat = data.entity?.getComponent('CombatComponent');
        const stats = data.entity?.getComponent('StatsComponent');
        const actions = data.entity?.getComponent('ActionsComponent');
        const team = combat?.team || 'neutral';
        const teamLabels = { player: 'Player', enemy: 'Enemy', ally: 'Ally', neutral: 'NPC' };
        const teamLabel = teamLabels[team] || team;

        // HP bar — exact values only for player team (AC-004 visibility rule)
        let hpHtml = '';
        if (stats && stats.maxHp > 0) {
          const pct = Math.max(0, Math.min(100, Math.round((stats.currentHp / stats.maxHp) * 100)));
          let hpStateClass = 'hp-bar--healthy';
          if (pct <= 0) hpStateClass = 'hp-bar--defeated';
          else if (pct <= 25) hpStateClass = 'hp-bar--critical';
          else if (pct <= 50) hpStateClass = 'hp-bar--bloodied';
          const hpLabel = team === 'player' ? `${stats.currentHp}/${stats.maxHp}` : '';
          hpHtml = `<div class="rail-card__hp-wrap" title="${hpLabel || 'HP status'}">
              <div class="rail-card__hp-track"><div class="rail-card__hp-bar ${hpStateClass}" style="width:${pct}%"></div></div>
              ${hpLabel ? `<span class="rail-card__hp-label">${hpLabel}</span>` : ''}
            </div>`;
        }

        // Action pips — only shown on active combatant (AC-001 compact status cues)
        let actionsHtml = '';
        if (data.isCurrent && actions) {
          const maxA = actions.maxActions || 3;
          let pips = '';
          for (let i = 0; i < maxA; i++) {
            const spent = i >= actions.actionsRemaining;
            pips += `<span class="rail-card__pip ${spent ? 'pip--spent' : 'pip--ready'}" title="${spent ? 'Action spent' : 'Action ready'}"></span>`;
          }
          const rxClass = actions.hasReaction ? 'pip--reaction-ready' : 'pip--reaction-spent';
          pips += `<span class="rail-card__pip rail-card__pip--reaction ${rxClass}" title="${actions.hasReaction ? 'Reaction ready' : 'Reaction spent'}">R</span>`;
          actionsHtml = `<div class="rail-card__actions">${pips}</div>`;
        }

        const activeClass = data.isCurrent ? 'rail-card--active' : '';
        const defeatedClass = data.isDefeated ? 'rail-card--defeated' : '';
        html += `<div class="initiative-item rail-card ${activeClass} ${defeatedClass}" data-entity-id="${data.entityId}" role="button" tabindex="0" aria-label="${data.name}${data.isCurrent ? ' — active turn' : ''}">
            <div class="rail-card__header">
              <span class="rail-card__init">${data.initiative}</span>
              <span class="rail-card__name">${data.name}</span>
              <span class="rail-card__team-badge rail-card__team--${team}">${teamLabel}</span>
            </div>
            ${hpHtml}
            ${actionsHtml}
          </div>`;
      });
      this.elements.initiativeList.innerHTML = html;
    }

    /**
     * Update combat controls visibility.
     */
    updateCombatControls(combatState) {
      const isInactive = (combatState === CombatState.INACTIVE || combatState === CombatState.ENDED);

      if (this.elements.startCombatBtn) {
        this.elements.startCombatBtn.style.display = isInactive ? 'inline-block' : 'none';
      }
      if (this.elements.endTurnBtn) {
        this.elements.endTurnBtn.style.display = isInactive ? 'none' : 'inline-block';
      }
      if (this.elements.endCombatBtn) {
        this.elements.endCombatBtn.style.display = isInactive ? 'none' : 'inline-block';
      }
      if (this.elements.initiativeTracker) {
        this.elements.initiativeTracker.style.display = isInactive ? 'none' : 'block';
      }

      if (this.elements.turnHud) {
        this.elements.turnHud.classList.toggle('hud-inactive', isInactive);
      }
      if (this.elements.turnOwner) {
        this.elements.turnOwner.textContent = isInactive ? 'No active combat' : 'Active encounter';
      }
    }

    /**
     * Show entity info panel.
     */
    showEntityInfo(entity) {
      if (!this.elements.entityInfoPanel) return;

      this.elements.entityInfoPanel.classList.remove('dc-is-hidden');
      this.elements.entityInfoPanel.style.display = 'block';
      this.elements.entityInfoPanel.setAttribute('aria-hidden', 'false');

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

      if (this.elements.entityName) {
        this.elements.entityName.textContent = identity?.name || 'Unknown';
      }
      if (this.elements.entityType) {
        this.elements.entityType.textContent = displayType;
      }
      if (this.elements.entityImageWrap && this.elements.entityImage) {
        if (imageUrl) {
          this.elements.entityImage.src = imageUrl;
          this.elements.entityImage.alt = `${identity?.name || 'Entity'} portrait`;
          this.elements.entityImageWrap.classList.remove('dc-is-hidden');
        } else {
          this.elements.entityImage.removeAttribute('src');
          this.elements.entityImage.alt = '';
          this.elements.entityImageWrap.classList.add('dc-is-hidden');
        }
      }
      if (this.elements.entitySummary) {
        this.elements.entitySummary.textContent = knownSummary;
      }
      if (this.elements.entityDescription) {
        this.elements.entityDescription.textContent = description || 'No additional details are known yet.';
      }
      if (this.elements.entityKnownDetails) {
        if (knownDetails.length) {
          this.elements.entityKnownDetails.innerHTML = knownDetails
            .map((detail) => `<li>${detail}</li>`)
            .join('');
        } else {
          this.elements.entityKnownDetails.innerHTML = '<li>No additional details are known yet.</li>';
        }
      }
      if (this.elements.entityTeam) {
        this.elements.entityTeam.textContent = teamLabel;
      }
      if (this.elements.entityHp) {
        this.elements.entityHp.textContent = stats ? `${stats.currentHp}/${stats.maxHp}` : '-';
      }
      if (this.elements.entityAc) {
        this.elements.entityAc.textContent = stats?.ac || '-';
      }
      if (this.elements.entityActions) {
        this.elements.entityActions.textContent = actions ? actions.getActionDisplay?.() || `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions` : '-';
      }
      if (this.elements.entityMovement) {
        this.elements.entityMovement.textContent = movementValue;
      }

      // NOTE: Character sheet (character* elements) is only populated by
      // showLaunchCharacter() with the PC's full data.  Do NOT overwrite it
      // here — this method fires for every selected entity including NPCs.
    }

    /**
     * Display character sheet from either legacy launchCharacter or full API state.
     */
    showLaunchCharacter(launchCharacter) {
      if (!launchCharacter || typeof launchCharacter !== 'object') {
        return;
      }

      console.log('showLaunchCharacter received:', launchCharacter);

      // Support both legacy format and new API state format
      const state = launchCharacter.data || launchCharacter;
      const basicInfo = state.basicInfo || {};
      const abilities = state.abilities || {};
      const resources = state.resources || {};
      const defenses = state.defenses || {};
      const conditions = state.conditions || [];
      const skills = state.skills || [];
      const features = state.features || {};
      const feats = state.feats || []; // Direct feats array from legacy format
      const equipment = state.equipment || [];
      const inventory = state.inventory || resources.inventory || {};
      const spells = state.spells || {};
      const saves = state.saves || defenses.savingThrows || {};
      
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

      // Basic info
      const name = basicInfo.name || state.name || launchCharacter.name || 'Selected character';
      const ancestry = basicInfo.ancestry || state.ancestry || launchCharacter.ancestry || '';
      const heritage = state.heritage || launchCharacter.heritage || '';
      const characterClass = basicInfo.class || state.class || launchCharacter.class || '';
      const background = state.background || launchCharacter.background || '';
      const level = Number(basicInfo.level || state.level || launchCharacter.level || 0);
      const speed = Number(state.speed || launchCharacter.speed || 25);
      const characterId = state.id || launchCharacter.id || null;
      const sheetCharacterId = state.sheet_character_id || state.character_id || launchCharacter.sheet_character_id || launchCharacter.character_id || characterId || null;
      
      // Resources
      const hpCurrent = Number(resources.hitPoints?.current ?? state.hp_current ?? launchCharacter.hp_current ?? 0);
      const hpMax = Number(resources.hitPoints?.max ?? state.hp_max ?? launchCharacter.hp_max ?? 0);
      const heroCurrent = Number(resources.heroPoints?.current ?? state.hero_points ?? launchCharacter.hero_points ?? 1);
      const heroMax = Number(resources.heroPoints?.max ?? 3);
      const armorClass = Number(defenses.armorClass ?? state.armor_class ?? launchCharacter.armor_class ?? 0);
      const xp = Number(basicInfo.experiencePoints ?? state.experience_points ?? 0);
      
      // Perception
      const perception = Number(state.perception ?? launchCharacter.perception ?? 0);
      
      // Currency (support both API format and legacy format)
      const currency = inventory.currency || state.currency || { 
        gp: state.gold || 0, 
        sp: 0, 
        cp: 0 
      };

      // Calculate ability modifiers
      const calcMod = (score) => {
        const mod = Math.floor((score - 10) / 2);
        return mod >= 0 ? `+${mod}` : `${mod}`;
      };
      const formatMod = (val) => val >= 0 ? `+${val}` : `${val}`;

      // Portrait
      const portraitUrl = state.portrait_url || state.portrait || launchCharacter.portrait_url || launchCharacter.portrait || null;
      if (this.elements.characterPortrait && this.elements.characterPortraitWrap) {
        if (portraitUrl) {
          this.elements.characterPortrait.src = portraitUrl;
          this.elements.characterPortrait.alt = `${name} portrait`;
          this.elements.characterPortraitWrap.style.display = '';
        } else {
          this.elements.characterPortraitWrap.style.display = 'none';
        }
      }

      // Update basic info
      if (this.elements.characterName) this.elements.characterName.textContent = name;
      if (this.elements.characterType) {
        const subtitleParts = [ancestry, characterClass].filter(Boolean);
        this.elements.characterType.textContent = subtitleParts.length ? subtitleParts.join(' ') : 'Type —';
      }
      // Subtitle line: heritage, background
      if (this.elements.characterSubtitle) {
        const subtitleDetails = [];
        if (heritage) subtitleDetails.push(heritage.charAt(0).toUpperCase() + heritage.slice(1));
        if (background) subtitleDetails.push(`Background: ${background}`);
        if (subtitleDetails.length) {
          this.elements.characterSubtitle.textContent = subtitleDetails.join(' · ');
          this.elements.characterSubtitle.style.display = '';
        }
      }
      // "View Full Sheet" link
      if (this.elements.characterFullSheetLink && sheetCharacterId) {
        this.elements.characterFullSheetLink.href = `/characters/${sheetCharacterId}`;
        this.elements.characterFullSheetLink.style.display = '';
      }
      this.showEmbeddedCharacterSheet(sheetCharacterId);
      if (this.elements.characterAncestry) this.elements.characterAncestry.textContent = ancestry || '—';
      if (this.elements.characterLevel) this.elements.characterLevel.textContent = level > 0 ? `Lvl ${level}` : 'Lvl —';

      // Update core stats
      if (this.elements.characterHp) {
        this.elements.characterHp.textContent = Number.isFinite(hpCurrent) && Number.isFinite(hpMax) ? `${hpCurrent}/${hpMax}` : '-';
      }
      if (this.elements.characterAc) {
        this.elements.characterAc.textContent = armorClass > 0 ? `${armorClass}` : '-';
      }
      if (this.elements.characterHero) {
        this.elements.characterHero.textContent = `${heroCurrent}/${heroMax}`;
      }
      if (this.elements.characterSpeed) {
        this.elements.characterSpeed.textContent = `${speed} ft`;
      }
      if (this.elements.characterPerception) {
        this.elements.characterPerception.textContent = formatMod(perception);
      }
      if (this.elements.characterXp) {
        this.elements.characterXp.textContent = xp;
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
        const valueEl = this.elements[`character${name}`];
        const modEl = this.elements[`character${name}Mod`];
        if (valueEl) valueEl.textContent = score;
        if (modEl) modEl.textContent = calcMod(score);
      });

      // Update saving throws (prefer pre-computed saves from server)
      if (this.elements.characterFort) {
        const fort = saves.fortitude ?? defenses.fortitude ?? 0;
        this.elements.characterFort.textContent = formatMod(fort);
      }
      if (this.elements.characterRef) {
        const ref = saves.reflex ?? defenses.reflex ?? 0;
        this.elements.characterRef.textContent = formatMod(ref);
      }
      if (this.elements.characterWill) {
        const will = saves.will ?? defenses.will ?? 0;
        this.elements.characterWill.textContent = formatMod(will);
      }

      // Update skills
      if (this.elements.characterSkills) {
        if (Array.isArray(skills) && skills.length > 0) {
          this.elements.characterSkills.innerHTML = skills
            .map(skill => {
              const name = skill.name || skill;
              const bonus = skill.modifier !== undefined ? (skill.modifier >= 0 ? `+${skill.modifier}` : skill.modifier) : '';
              const prof = skill.proficiency ? `<span class="skill-prof">${skill.proficiency}</span>` : '';
              return `<li><span>${name}</span>${prof}<span>${bonus}</span></li>`;
            })
            .join('');
        } else {
          this.elements.characterSkills.innerHTML = '<li class="skills-empty">No skills</li>';
        }
      }

      // Update conditions
      if (this.elements.characterConditions) {
        if (Array.isArray(conditions) && conditions.length > 0) {
          const conditionNames = conditions.map(c => typeof c === 'string' ? c : (c.name || 'Unknown'));
          this.elements.characterConditions.innerHTML = conditionNames
            .map(name => `<li>${name}</li>`)
            .join('');
        } else {
          this.elements.characterConditions.innerHTML = '<li class="conditions-empty">No conditions</li>';
        }
      }

      // Update currency
      if (this.elements.characterGp) this.elements.characterGp.textContent = currency.gp || 0;
      if (this.elements.characterSp) this.elements.characterSp.textContent = currency.sp || 0;
      if (this.elements.characterCp) this.elements.characterCp.textContent = currency.cp || 0;

      // Update inventory (support both equipment array and inventory.carried)
      if (this.elements.characterInventory) {
        const carried = inventory.carried || equipment || [];
        const worn = inventory.worn || {};
        const weapons = worn.weapons || [];
        const allItems = [...weapons, ...carried];
        
        if (allItems.length > 0) {
          this.elements.characterInventory.innerHTML = allItems
            .map(item => {
              const itemName = item.name || item;
              const itemId = item.item_id || item.id || '';
              const qty = item.quantity || 1;
              const qtyLabel = qty > 1 ? ` ×${qty}` : '';
              const equipped = item.equipped ? ' <span class="item-tag equipped">E</span>' : '';
              const type = item.type || item.category || '';
              const bulk = item.bulk != null ? item.bulk : '';
              const desc = item.description || '';
              return `<li data-item-id="${itemId}" data-type="${type}" data-qty="${qty}" data-bulk="${bulk}" data-desc="${desc.replace(/"/g, '&quot;')}">${itemName}${qtyLabel}${equipped}</li>`;
            })
            .join('');
        } else {
          this.elements.characterInventory.innerHTML = '<li class="inventory-empty">No items</li>';
        }
      }

      // Update features & feats (with type badges)
      if (this.elements.characterFeatures) {
        const ancestryFeatures = features.ancestryFeatures || [];
        const classFeatures = features.classFeatures || [];
        // Use nested features.feats if available, fall back to the top-level
        // feats array from the legacy PHP payload.
        const featList = features.feats || feats || [];
        const allFeatures = [...ancestryFeatures, ...classFeatures, ...featList];
        
        if (allFeatures.length > 0) {
          this.elements.characterFeatures.innerHTML = allFeatures
            .map(feat => {
              const featName = feat.name || feat;
              const featType = feat.type ? `<span class="feat-type feat-type--${feat.type}">${feat.type}</span>` : '';
              const featLevel = feat.level ? `<span class="feat-level">Lv ${feat.level}</span>` : '';
              return `<li>${featType}${featName}${featLevel}</li>`;
            })
            .join('');
        } else {
          this.elements.characterFeatures.innerHTML = '<li class="features-empty">No features</li>';
        }
      }

      // Update spellcasting section
      if (this.elements.characterSpellsSection && this.elements.characterSpells) {
        if (spells && spells.tradition) {
          this.elements.characterSpellsSection.style.display = '';
          // Spell meta info
          if (this.elements.characterSpellMeta) {
            const metaParts = [`Tradition: ${spells.tradition}`];
            if (spells.casting_ability) metaParts.push(`Ability: ${spells.casting_ability.toUpperCase()}`);
            if (spells.slots) {
              const slotParts = Object.entries(spells.slots).map(([k, v]) => `${k}: ${v}`);
              metaParts.push(`Slots: ${slotParts.join(', ')}`);
            }
            this.elements.characterSpellMeta.innerHTML = metaParts.map(p => `<span class="spell-meta-item">${p}</span>`).join('');
          }
          // Spell list
          const spellEntries = [];
          const cantrips = spells.cantrips || [];
          if (cantrips.length > 0) {
            spellEntries.push(`<li class="spell-rank-header">Cantrips</li>`);
            cantrips.forEach(s => {
              const spellName = typeof s === 'string' ? s.replace(/_/g, ' ') : (s.name || s);
              spellEntries.push(`<li class="spell-entry">${spellName}</li>`);
            });
          }
          const firstLevel = spells.first_level || [];
          if (firstLevel.length > 0) {
            spellEntries.push(`<li class="spell-rank-header">1st Level</li>`);
            firstLevel.forEach(s => {
              const spellName = typeof s === 'string' ? s.replace(/_/g, ' ') : (s.name || s);
              spellEntries.push(`<li class="spell-entry">${spellName}</li>`);
            });
          }
          this.elements.characterSpells.innerHTML = spellEntries.length > 0
            ? spellEntries.join('')
            : '<li class="spells-empty">No spells</li>';
        } else {
          this.elements.characterSpellsSection.style.display = 'none';
        }
      }
    }

    /**
     * Display the canonical /characters/{id} sheet inline in the hexmap panel.
     */
    showEmbeddedCharacterSheet(characterId) {
      if (!characterId || !this.elements.characterSheetEmbed) {
        return;
      }

      const query = new URLSearchParams(window.location.search);
      const campaignId = Number(query.get('campaign_id') || 0);
      let sheetUrl = `/characters/${characterId}/embed`;
      if (campaignId > 0) {
        sheetUrl += `?campaign_id=${campaignId}`;
      }

      if (this.embeddedCharacterSheetUrl !== sheetUrl) {
        this.elements.characterSheetEmbed.src = sheetUrl;
        this.embeddedCharacterSheetUrl = sheetUrl;
      }

      if (this.elements.characterSheetEmbedWrap) {
        this.elements.characterSheetEmbedWrap.style.display = '';
      }
      if (this.elements.characterSheetLegacy) {
        this.elements.characterSheetLegacy.style.display = 'none';
      }
    }

    /**
     * Hide entity info panel.
     */
    hideEntityInfo() {
      if (this.elements.entityInfoPanel) {
        this.elements.entityInfoPanel.classList.add('dc-is-hidden');
        this.elements.entityInfoPanel.style.display = 'none';
        this.elements.entityInfoPanel.setAttribute('aria-hidden', 'true');
      }
    }

    /**
     * Update zoom level display.
     */
    updateZoomLevel(scale) {
      if (this.elements.zoomLevel) {
        const zoomPercent = Math.round(scale * 100);
        this.elements.zoomLevel.textContent = `${zoomPercent}%`;
      }
    }

    /**
     * Update hovered hex detail panel.
     * @param {Object|null} details - Detail payload for the hovered hex.
     */
    updateHexDetails(details) {
      const fallback = {
        room: 'None',
        terrain: 'Unknown',
        elevation: '-',
        lighting: 'Unknown',
        passability: 'Unknown',
        objects: 'None',
        entities: 'None',
        connection: 'None'
      };

      const payload = details ? {
        room: details.roomName || fallback.room,
        terrain: details.terrain || fallback.terrain,
        elevation: Number.isFinite(details.elevationFt) ? `${details.elevationFt} ft` : fallback.elevation,
        lighting: details.lighting || fallback.lighting,
        passability: details.passability || fallback.passability,
        objects: Array.isArray(details.objects) && details.objects.length ? details.objects.join(', ') : fallback.objects,
        entities: Array.isArray(details.entities) && details.entities.length ? details.entities.join(', ') : fallback.entities,
        connection: details.connection || fallback.connection
      } : fallback;

      const map = {
        hexDetailRoom: payload.room,
        hexDetailTerrain: payload.terrain,
        hexDetailElevation: payload.elevation,
        hexDetailLighting: payload.lighting,
        hexDetailPassability: payload.passability,
        hexDetailObjects: payload.objects,
        hexDetailEntities: payload.entities,
        hexDetailConnection: payload.connection
      };

      Object.entries(map).forEach(([key, value]) => {
        if (this.elements[key]) {
          this.elements[key].textContent = value;
        }
      });
    }

    /**
     * Render an exact list of everything occupying the selected hex.
     * @param {Array<Object>} occupants - Occupant view models
     * @param {number|null} q - Selected q
     * @param {number|null} r - Selected r
     * @param {(entityId:number, mode:string)=>void} onChoose - Click callback
     */
    updateSelectedHexContents(occupants, q, r, onChoose) {
      const summary = this.elements.selectedHexContentsSummary;
      const empty = this.elements.selectedHexContentsEmpty;
      const list = this.elements.selectedHexContentsList;
      if (!summary || !empty || !list) {
        return;
      }

      const hasCoords = Number.isFinite(q) && Number.isFinite(r);
      summary.textContent = hasCoords
        ? `Hex (${q}, ${r}) contains ${occupants.length} entr${occupants.length === 1 ? 'y' : 'ies'}.`
        : 'Click a hex to inspect everything on it.';

      list.innerHTML = '';

      if (!occupants.length) {
        empty.style.display = '';
        return;
      }

      empty.style.display = 'none';

      occupants.forEach((occupant) => {
        const row = document.createElement('div');
        row.className = 'hex-contents-item';
        if (occupant.isSelected) {
          row.classList.add('is-selected');
        }

        const meta = document.createElement('div');
        meta.className = 'hex-contents-item__meta';

        const name = document.createElement('div');
        name.className = 'hex-contents-item__name';
        name.textContent = occupant.name;

        const detail = document.createElement('div');
        detail.className = 'hex-contents-item__detail';
        detail.textContent = `${occupant.typeLabel}${occupant.teamLabel ? ` • ${occupant.teamLabel}` : ''}`;

        meta.appendChild(name);
        meta.appendChild(detail);

        const actions = document.createElement('div');
        actions.className = 'hex-contents-item__actions';

        const inspectBtn = document.createElement('button');
        inspectBtn.type = 'button';
        inspectBtn.className = 'hex-contents-item__button hex-contents-item__button--secondary';
        inspectBtn.textContent = 'Inspect';
        inspectBtn.addEventListener('click', () => onChoose(occupant.entityId, 'inspect'));
        actions.appendChild(inspectBtn);

        if (occupant.canSelect) {
          const selectBtn = document.createElement('button');
          selectBtn.type = 'button';
          selectBtn.className = 'hex-contents-item__button';
          selectBtn.textContent = occupant.isSelected ? 'Selected' : 'Select';
          selectBtn.addEventListener('click', () => onChoose(occupant.entityId, 'select'));
          actions.appendChild(selectBtn);
        }

        row.appendChild(meta);
        row.appendChild(actions);
        list.appendChild(row);
      });
    }

    /**
     * Initialize the dialog log and chat input.
     */
    setupChatLog() {
      const form = this.elements.chatForm;
      const input = this.elements.chatInput;
      const log = this.elements.chatLog;

      if (!form || !input || !log || form.dataset.bound === 'true') {
        return;
      }

      form.dataset.bound = 'true';
      let isSubmitting = false;

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        // Prevent double submission
        if (isSubmitting) {
          return;
        }

        const message = input.value.trim();
        if (!message) {
          return;
        }

        // Validate message length (matches backend limit)
        if (message.length > 2000) {
          this.appendChatLine('System', 'Message too long (max 2000 characters)', 'system');
          return;
        }

        // Get context from state manager
        const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
        const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;
        const characterData = this.stateManager.hexmap?.characterData || {};
        const characterName = characterData.name || 'You';
        const characterId = characterData.id || null;

        if (!campaignId) {
          this.appendChatLine('System', 'Unable to send message: No active campaign', 'system');
          return;
        }

        // Set loading state
        isSubmitting = true;
        const sendButton = this.elements.chatSend;
        const originalText = sendButton?.textContent;
        if (sendButton) {
          sendButton.disabled = true;
          sendButton.textContent = 'Sending...';
        }

        // Clear input immediately for better UX
        input.value = '';

        // Route to the correct handler based on active session view.
        if (this.activeSessionView !== 'room') {
          try {
            await this.postSessionViewMessage(characterName, message, characterId);
          } catch (error) {
            console.error('Failed to send session message:', error);
            this.appendChatLine('System', `Failed to send: ${error.message}`, 'system');
            input.value = message;
          } finally {
            isSubmitting = false;
            if (sendButton) {
              sendButton.disabled = false;
              sendButton.textContent = originalText || 'Send';
            }
          }
          return;
        }

        // Send to legacy room chat server
        if (!roomId) {
          isSubmitting = false;
          if (sendButton) { sendButton.disabled = false; sendButton.textContent = originalText || 'Send'; }
          input.value = message;
          this.appendChatLine('System', 'Unable to send message: No active room', 'system');
          return;
        }
        try {
          await this.postChatMessage(campaignId, roomId, characterName, message, characterId);
          // Message will appear when server confirms (or from loadChatHistory)
        } catch (error) {
          // Handle permission errors silently (user doesn't have access)
          if (error.message.includes('403')) {
            console.warn('Chat message send denied (permission)');
            // Don't show error in chat, don't restore message, just silently fail
          } else {
            console.error('Failed to send chat message:', error);
            this.appendChatLine('System', `Failed to send message: ${error.message}`, 'system');
            // Restore message to input so user can retry (non-permission errors only)
            input.value = message;
          }
        } finally {
          // Reset loading state
          isSubmitting = false;
          if (sendButton) {
            sendButton.disabled = false;
            sendButton.textContent = originalText || 'Send';
          }
        }
      });

      // Chat history will be loaded when room becomes active
      // (via state subscription or explicit call from room change handler)
    }

    // ===================================================================
    // Channel management
    // ===================================================================

    /**
     * Set up channel tab click handlers.
     */
    setupChannelTabs() {
      const tabContainer = this.elements.chatChannelTabs;
      if (!tabContainer) return;

      tabContainer.addEventListener('click', (e) => {
        const tab = e.target.closest('.chat-channel-tab');
        if (!tab) return;

        const channelKey = tab.dataset.channel;
        if (!channelKey || channelKey === this.activeChannel) return;

        this.switchChannel(channelKey);
      });
    }

    /**
     * Switch to a different chat channel and reload messages.
     */
    switchChannel(channelKey) {
      this.activeChannel = channelKey;

      // Update tab active state.
      const tabContainer = this.elements.chatChannelTabs;
      if (tabContainer) {
        tabContainer.querySelectorAll('.chat-channel-tab').forEach(tab => {
          tab.classList.toggle('chat-channel-tab--active', tab.dataset.channel === channelKey);
        });
      }

      // Determine channel type and label for indicators.
      const channel = this.channels[channelKey];
      let channelType = 'room';
      let indicatorIcon = '\u{1F4E2}';
      let indicatorText = 'Room \u2014 Everyone can hear';

      if (channel && channelKey !== 'room') {
        const targetName = channel.target_name || channel.label || 'NPC';
        const ability = channel.source_ability || 'whisper';
        if (channelKey.startsWith('spell:')) {
          channelType = 'spell';
          indicatorIcon = '\u2728';
          indicatorText = `${channel.label || ability} \u2014 Magical link with ${targetName}`;
        } else {
          channelType = 'whisper';
          indicatorIcon = '\u{1F5E3}';
          indicatorText = `Whisper \u2014 Private with ${targetName}`;
        }
      }

      // Update channel indicator banner.
      const indicator = this.elements.chatChannelIndicator;
      if (indicator) {
        indicator.dataset.channelType = channelType;
        const iconEl = indicator.querySelector('.channel-indicator__icon');
        if (iconEl) iconEl.textContent = indicatorIcon;
      }
      const label = this.elements.chatChannelLabel;
      if (label) label.textContent = indicatorText;

      // Color-code the chat log border.
      const log = this.elements.chatLog;
      if (log) log.dataset.channelType = channelType;

      // Update input placeholder.
      const input = this.elements.chatInput;
      if (input) {
        if (channelKey === 'room') {
          input.placeholder = 'Say something to the room...';
        } else {
          const targetName = channel?.target_name || channel?.label || 'NPC';
          input.placeholder = `${channel?.source_ability || 'Whisper'} to ${targetName}...`;
        }
      }

      // Reload chat history for this channel.
      this.loadChatHistory();
    }

    /**
     * Load channels for the current room and render tabs.
     */
    async loadChannels() {
      const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
      const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;
      const characterData = this.stateManager.hexmap?.characterData || {};
      const characterId = characterData.id || null;

      if (!campaignId || !roomId) return;

      try {
        const url = `/api/campaign/${campaignId}/room/${roomId}/channels${characterId ? '?character_id=' + characterId : ''}`;
        const response = await fetch(url);
        if (!response.ok) return;

        const result = await response.json();
        if (!result.success || !result.data) return;

        this.channels = result.data.channels || { room: { key: 'room', label: 'Room', type: 'room', active: true } };
        this.renderChannelTabs();
      } catch (err) {
        console.error('Failed to load channels:', err);
      }
    }

    /**
     * Render channel tabs from the current channels state.
     */
    renderChannelTabs() {
      const container = this.elements.chatChannelTabs;
      if (!container) return;

      container.innerHTML = '';

      for (const [key, ch] of Object.entries(this.channels)) {
        if (!(ch.active ?? true)) continue;

        const tab = document.createElement('button');
        tab.className = 'chat-channel-tab';
        if (key === this.activeChannel) {
          tab.classList.add('chat-channel-tab--active');
        }
        tab.dataset.channel = key;
        tab.title = ch.description || ch.label || key;
        tab.textContent = ch.label || key;

        // Add close button for non-room channels.
        if (key !== 'room') {
          const close = document.createElement('span');
          close.className = 'chat-channel-tab__close';
          close.textContent = '\u00D7';
          close.title = 'Close channel';
          close.addEventListener('click', (e) => {
            e.stopPropagation();
            this.closeChannel(key);
          });
          tab.appendChild(close);
        }

        container.appendChild(tab);
      }
    }

    /**
     * Open a new channel (e.g. from clicking "Talk" on an NPC entity).
     */
    async openChannel(targetEntity, targetName, sourceAbility = 'whisper') {
      const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
      const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;
      const characterData = this.stateManager.hexmap?.characterData || {};
      const characterId = characterData.id || null;

      if (!campaignId || !roomId) return;

      const channelKey = sourceAbility === 'whisper'
        ? `whisper:${targetEntity}`
        : `spell:${sourceAbility}:${targetEntity}`;

      try {
        const response = await fetch(`/api/campaign/${campaignId}/room/${roomId}/channels`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({
            channel_key: channelKey,
            opened_by: String(characterId),
            target_entity: targetEntity,
            target_name: targetName,
            source_ability: sourceAbility,
          }),
        });

        const result = await response.json();
        if (result.success && result.data?.channel) {
          // Add to local channels and render.
          this.channels[channelKey] = result.data.channel;
          this.renderChannelTabs();
          // Switch to the new channel.
          this.switchChannel(channelKey);
        } else {
          this.appendChatLine('System', result.data?.error || result.error || 'Unable to open channel.', 'system');
        }
      } catch (err) {
        console.error('Failed to open channel:', err);
        this.appendChatLine('System', 'Failed to open channel.', 'system');
      }
    }

    /**
     * Close a channel.
     */
    async closeChannel(channelKey) {
      if (channelKey === 'room') return;

      const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
      const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;
      if (!campaignId || !roomId) return;

      try {
        await fetch(`/api/campaign/${campaignId}/room/${roomId}/channels/${encodeURIComponent(channelKey)}`, {
          method: 'DELETE',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        // Remove from local state and switch to room if we were on it.
        delete this.channels[channelKey];
        this.renderChannelTabs();
        if (this.activeChannel === channelKey) {
          this.switchChannel('room');
        }
      } catch (err) {
        console.error('Failed to close channel:', err);
      }
    }

    async loadChatHistory() {
      const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
      const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;
      const characterData = this.stateManager.hexmap?.characterData || {};
      const characterId = characterData.id || null;

      if (!campaignId || !roomId) {
        return;
      }

      try {
        let url = `/api/campaign/${campaignId}/room/${roomId}/chat?channel=${encodeURIComponent(this.activeChannel)}`;
        if (characterId) {
          url += `&character_id=${characterId}`;
        }
        const response = await fetch(url);
        
        // Handle 403 (permission denied) gracefully  
        if (response.status === 403) {
          console.warn('Chat access denied for campaign:', campaignId);
          return; // Silently skip loading - user doesn't have access
        }
        
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        if (result.success && result.data?.messages) {
          // Clear existing messages
          const log = this.elements.chatLog;
          if (log) {
            log.innerHTML = '';
          }

          // Render all messages
          result.data.messages.forEach(msg => {
            this.appendChatLine(msg.speaker, msg.message, msg.type);
          });

          // When no chat history exists, show the room description as the opening narration.
          if (result.data.messages.length === 0) {
            const roomData = this.stateManager.hexmap?.getActiveRoomData?.() || null;
            if (roomData?.name) {
              const terrain = roomData.terrain?.type ? roomData.terrain.type.replace(/_/g, ' ') : '';
              const lighting = roomData.lighting && roomData.lighting !== 'normal' ? ` | Lighting: ${roomData.lighting}` : '';
              const size = roomData.size_category && roomData.size_category !== 'medium' ? ` | ${roomData.size_category}` : '';
              const subtitle = [terrain, lighting, size].filter(Boolean).join('').replace(/^\s*\|\s*/, '');
              const meta = subtitle ? ` (${subtitle})` : '';
              this.appendChatLine('System', `📍 ${roomData.name}${meta}`, 'system');
            }
            if (roomData?.description) {
              this.appendChatLine('System', roomData.description, 'system');
            } else {
              this.appendChatLine('System', 'Welcome to the room. Start a conversation!', 'system');
            }
          }
        }
      } catch (error) {
        console.error('Failed to load chat history:', error);
        // Don't show error message in chat, just log to console
        // The chat interface will still work for new messages
      }
    }

    async postChatMessage(campaignId, roomId, speaker, message, characterId = null) {
      const response = await fetch(`/api/campaign/${campaignId}/room/${roomId}/chat`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          speaker,
          message,
          type: 'player',
          character_id: characterId,
          channel: this.activeChannel,
        }),
      });

      if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        throw new Error(result.error || `HTTP ${response.status}`);
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Unknown error');
      }

      // Show the player message immediately
      this.appendChatLine(speaker, message, 'player');

      // If the server returned a GM response, append it directly
      if (result.data?.gm_response) {
        const gm = result.data.gm_response;
        this.appendChatLine(gm.speaker || 'Game Master', gm.message, gm.type || 'npc');
      } else {
        // Fallback: reload full chat history
        await this.loadChatHistory();
      }

      // If any NPCs interjected, render their messages after the GM response.
      if (result.data?.npc_interjections?.length) {
        for (const npcMsg of result.data.npc_interjections) {
          this.appendChatLine(npcMsg.speaker, npcMsg.message, 'npc');
        }
      }

      // Handle navigation: if the GM triggered a location change, inject the
      // new room/entities/connections into the live dungeon data and switch.
      if (result.data?.navigation?.target_room_id) {
        this.handleNavigationResult(result.data.navigation);
      }

      return result;
    }

    /**
     * Handle a navigate_to_location result from the chat API.
     *
     * [THIN-CLIENT: server-authoritative] — reconciles server-returned room payload
     * into the local dungeonData presentation cache. The room, entities, and connections
     * all originate from the server response (nav.room, nav.entities, nav.connections).
     * This is NOT client-originated state creation.
     *
     * Injects the new room, entities, and connections into the live dungeonData,
     * moves the player entity to the entry hex, and switches the active room.
     *
     * @param {object} nav - Navigation payload from the server:
     *   { target_room_id, destination, room, entities, connections, entry_hex }
     */
    handleNavigationResult(nav) {
      const hexmap = this.stateManager?.hexmap;
      if (!hexmap || !hexmap.dungeonData) {
        console.error('[Navigation] hexmap or dungeonData not available');
        return;
      }

      const targetRoomId = nav.target_room_id;
      const newRoom = nav.room;
      const newEntities = nav.entities || [];
      const newConnections = nav.connections || [];
      const entryHex = nav.entry_hex || { q: 0, r: 0 };

      console.log('[Navigation] Transitioning to:', targetRoomId, nav.destination);

      // 1. Inject the new room into dungeonData.rooms (keyed by room_id).
      if (newRoom && targetRoomId) {
        hexmap.dungeonData.rooms[targetRoomId] = newRoom;
      }

      // 2. Append new entities to dungeonData.entities.
      if (!Array.isArray(hexmap.dungeonData.entities)) {
        hexmap.dungeonData.entities = [];
      }
      for (const entity of newEntities) {
        // Avoid duplicates by instance_id.
        const existingIdx = hexmap.dungeonData.entities.findIndex(
          (e) => (e.instance_id || e.entity_instance_id) === (entity.instance_id || entity.entity_instance_id)
        );
        if (existingIdx === -1) {
          hexmap.dungeonData.entities.push(entity);
        }
      }

      // 3. Append new connections to dungeonData.connections.
      if (!Array.isArray(hexmap.dungeonData.connections)) {
        hexmap.dungeonData.connections = [];
      }
      for (const conn of newConnections) {
        // Avoid duplicate connections.
        const connId = conn.connection_id || `${conn.from_room}_${conn.to_room}`;
        const exists = hexmap.dungeonData.connections.some(
          (c) => (c.connection_id || `${c.from_room}_${c.to_room}`) === connId
        );
        if (!exists) {
          hexmap.dungeonData.connections.push(conn);
        }
      }

      // 4. Move the selected player entity to the new room entry hex.
      const selectedEntity = hexmap.stateManager?.get('selectedEntity');
      if (selectedEntity && Array.isArray(hexmap.dungeonData.entities)) {
        const entityRef = selectedEntity.dcEntityRef;
        for (const de of hexmap.dungeonData.entities) {
          const deRef = de.instance_id || de.entity_instance_id;
          if (deRef === entityRef || (selectedEntity.dcCharacterId && de?.state?.metadata?.character_id == selectedEntity.dcCharacterId)) {
            de.placement = {
              room_id: targetRoomId,
              hex: { q: Number(entryHex.q), r: Number(entryHex.r) },
            };
            break;
          }
        }

        // Also move ally NPCs to adjacent hexes.
        const allyNpcs = hexmap.dungeonData.entities.filter(
          (e) => e.entity_type === 'npc' && e?.state?.metadata?.team === 'ally'
        );
        const offsets = [{ q: 1, r: 0 }, { q: -1, r: 0 }, { q: 0, r: 1 }, { q: 0, r: -1 }, { q: 1, r: -1 }, { q: -1, r: 1 }];
        allyNpcs.forEach((npc, i) => {
          const offset = offsets[i % offsets.length];
          npc.placement = {
            room_id: targetRoomId,
            hex: { q: Number(entryHex.q) + offset.q, r: Number(entryHex.r) + offset.r },
          };
        });

        // Persist entity position to server.
        const campaignId = hexmap.resolveCampaignId();
        if (campaignId && entityRef) {
          fetch(`/api/campaign/${campaignId}/entity/${entityRef}/move`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ room_id: targetRoomId, q: Number(entryHex.q), r: Number(entryHex.r) }),
          }).catch((err) => console.warn('[Navigation] Entity move persist failed:', err));
        }

        // Deselect before room switch.
        hexmap.deselectEntity();
      }

      // 5. Show travel notification in chat.
      this.appendChatLine('System', `🗺️ Traveling to ${nav.destination || newRoom?.name || targetRoomId}...`, 'system');

      // 6. Switch to the new room (triggers full re-render, chat reload, banner).
      hexmap.setActiveRoom(targetRoomId);

      // 7. Re-select the player entity in the new room.
      const newPlayerEntity = hexmap.findLaunchPlayerEntity();
      if (newPlayerEntity) {
        hexmap.selectEntity(newPlayerEntity);
        if (hexmap.launchCharacter) {
          hexmap.uiManager?.showLaunchCharacter?.(hexmap.launchCharacter);
        }
      }

      console.log('[Navigation] Room switch complete:', targetRoomId);
    }

    appendChatLine(speaker, message, type = 'npc') {
      const log = this.elements.chatLog;
      if (!log) {
        return;
      }

      const line = document.createElement('div');
      line.className = `chat-line chat-line--${type}`;

      if (speaker) {
        const name = document.createElement('span');
        name.className = 'chat-line__speaker';
        name.textContent = `${speaker}:`;
        line.appendChild(name);
      }

      const text = document.createElement('span');
      text.textContent = message;
      line.appendChild(text);

      log.appendChild(line);
      log.scrollTop = log.scrollHeight;
    }

    showServerUnavailable(message = 'Unable to connect to server. Please try again.') {
      const now = Date.now();
      if ((now - this.lastServerMessageAt) < this.serverMessageCooldownMs) {
        return;
      }

      this.lastServerMessageAt = now;

      if (this.elements.actionInstruction) {
        this.elements.actionInstruction.textContent = message;
      }

      this.appendChatLine('System', message, 'system');
    }

    // ===================================================================
    // Session view management (multi-tab chat system)
    // ===================================================================

    /**
     * Lazily initialize the ChatSessionApi when campaign is known.
     * @returns {ChatSessionApi|null}
     */
    ensureChatSessionApi() {
      const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
      if (!campaignId) return null;

      if (!this.chatSessionApi || this.chatSessionApi.campaignId !== campaignId) {
        this.chatSessionApi = new ChatSessionApi(campaignId);
      }
      return this.chatSessionApi;
    }

    /**
     * Set up click handlers for session view tabs.
     */
    setupSessionViewTabs() {
      const container = this.elements.chatSessionTabs;
      if (!container) return;

      container.addEventListener('click', (e) => {
        const tab = e.target.closest('.session-view-tab');
        if (!tab) return;

        const view = tab.dataset.view;
        if (!view || view === this.activeSessionView) return;

        this.switchSessionView(view);
      });
    }

    /**
     * Switch to a different session view.
     * @param {string} view — room | narrative | party | gm-private | system-log
     */
    switchSessionView(view) {
      this.activeSessionView = view;

      // Update tab active states.
      const container = this.elements.chatSessionTabs;
      if (container) {
        container.querySelectorAll('.session-view-tab').forEach(tab => {
          tab.classList.toggle('session-view-tab--active', tab.dataset.view === view);
        });
      }

      // Show/hide channel sub-tabs and indicator (only for room view).
      const channelTabs = this.elements.chatChannelTabs;
      const channelIndicator = this.elements.chatChannelIndicator;
      if (channelTabs) channelTabs.style.display = view === 'room' ? '' : 'none';
      if (channelIndicator) channelIndicator.style.display = view === 'room' ? '' : 'none';

      // Update panel title.
      const titles = {
        'room': 'Room Dialogue',
        'narrative': 'My Story',
        'party': 'Party Chat',
        'gm-private': 'GM Secret',
        'system-log': 'Dice Log',
      };
      if (this.elements.chatPanelTitle) {
        this.elements.chatPanelTitle.textContent = titles[view] || 'Chat';
      }

      // Update log border color.
      const log = this.elements.chatLog;
      if (log) {
        // Clear channel-type data attr for non-room views.
        if (view === 'room') {
          const channel = this.channels[this.activeChannel];
          const channelType = this.activeChannel === 'room' ? 'room'
            : this.activeChannel.startsWith('spell:') ? 'spell' : 'whisper';
          log.dataset.channelType = channelType;
          delete log.dataset.viewType;
        } else {
          delete log.dataset.channelType;
          log.dataset.viewType = view;
        }
      }

      // Update input placeholder and read-only state.
      const input = this.elements.chatInput;
      const sendBtn = this.elements.chatSend;
      const isReadOnly = view === 'system-log';

      if (input) {
        input.disabled = isReadOnly;
        const placeholders = {
          'room': 'Say something to the room...',
          'narrative': 'Your story unfolds here (read-only)...',
          'party': 'Whisper to your party...',
          'gm-private': 'Send a secret action to the GM...',
          'system-log': 'Dice rolls appear here automatically...',
        };
        input.placeholder = placeholders[view] || '';
      }
      if (sendBtn) sendBtn.disabled = isReadOnly;

      // Load messages for the selected view.
      this.loadSessionViewMessages(view);
    }

    /**
     * Load messages for the given session view.
     * @param {string} view
     */
    async loadSessionViewMessages(view) {
      // Room view uses existing legacy loading.
      if (view === 'room') {
        this.loadChatHistory();
        return;
      }

      const api = this.ensureChatSessionApi();
      if (!api) return;

      const characterData = this.stateManager.hexmap?.characterData || {};
      const characterId = characterData.id || null;

      const log = this.elements.chatLog;
      if (log) log.innerHTML = '';

      try {
        let data = null;

        switch (view) {
          case 'narrative': {
            if (!characterId) {
              this.appendChatLine('System', 'No character selected.', 'system');
              return;
            }
            const dungeonId = this.stateManager.hexmap?.dungeonData?.id || null;
            const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;
            data = await api.getCharacterNarrative(characterId, {
              dungeonId: dungeonId || undefined,
              roomId: roomId || undefined,
              limit: 50,
            });
            break;
          }

          case 'party':
            data = await api.getPartyChat({ limit: 50 });
            break;

          case 'gm-private': {
            if (!characterId) {
              this.appendChatLine('System', 'No character selected.', 'system');
              return;
            }
            data = await api.getGmPrivate(characterId, { limit: 50 });
            break;
          }

          case 'system-log':
            data = await api.getSystemLog({ limit: 100 });
            break;
        }

        if (data && data.messages && data.messages.length > 0) {
          data.messages.forEach(msg => {
            const lineType = this.resolveSessionLineType(msg, view);
            this.appendChatLine(msg.speaker, msg.message, lineType);
          });
        } else {
          const emptyMessages = {
            'narrative': 'Your story in this room has not yet begun...',
            'party': 'No party chatter yet. Say something!',
            'gm-private': 'No secret actions. Type to send one to the GM.',
            'system-log': 'No dice rolls yet.',
          };
          this.appendChatLine('System', emptyMessages[view] || 'No messages.', 'system');
        }
      } catch (err) {
        console.error(`Failed to load ${view} messages:`, err);
      }
    }

    /**
     * Determine the CSS line type for a session message.
     * @param {object} msg — formatted message from API
     * @param {string} view — active session view
     * @returns {string} — CSS class suffix
     */
    resolveSessionLineType(msg, view) {
      if (msg.speaker_type === 'system') return 'system';
      if (msg.speaker_type === 'gm') return 'gm';
      if (msg.message_type === 'mechanical' || msg.message_type === 'dice_roll') return 'mechanical';
      if (view === 'gm-private') return msg.speaker_type === 'player' ? 'secret' : 'gm';
      if (view === 'narrative') return msg.speaker_type === 'player' ? 'player' : 'narrative';
      if (msg.speaker_type === 'player') return 'player';
      return 'npc';
    }

    /**
     * Post a message from the active session view (non-room views).
     * Called from setupChatLog form handler when activeSessionView !== 'room'.
     *
     * @param {string} speaker
     * @param {string} message
     * @param {string|null} characterId
     */
    async postSessionViewMessage(speaker, message, characterId) {
      const api = this.ensureChatSessionApi();
      if (!api) return;

      try {
        switch (this.activeSessionView) {
          case 'party':
            this.appendChatLine(speaker, message, 'player');
            await api.postPartyChat(speaker, message, String(characterId || ''));
            break;

          case 'gm-private':
            if (!characterId) {
              this.appendChatLine('System', 'No character selected.', 'system');
              return;
            }
            this.appendChatLine(speaker, message, 'secret');
            await api.postGmPrivate(characterId, speaker, message);
            break;

          case 'narrative':
            // Narrative is read-only from the player's perspective.
            // The backend generates perception-filtered narration automatically.
            this.appendChatLine('System', 'Your story is narrated by the GM.', 'system');
            return;

          case 'system-log':
            // System log is read-only.
            return;
        }
      } catch (err) {
        console.error(`Failed to post to ${this.activeSessionView}:`, err);
        this.appendChatLine('System', `Failed to send: ${err.message}`, 'system');
      }
    }

    /**
     * Render the quest journal panel with active quest objectives.
     * @param {Array} activeQuests - Array of active quest objects.
     */
    renderQuestJournal(activeQuests) {
      const list = this.elements.questList;
      const count = this.elements.questCount;
      if (!list) return;

      if (!Array.isArray(activeQuests) || activeQuests.length === 0) {
        list.innerHTML = '<li class="quest-empty">No active quests</li>';
        if (count) count.textContent = '0';
        return;
      }

      if (count) count.textContent = String(activeQuests.length);

      list.innerHTML = activeQuests.map(quest => {
        const title = resolveQuestTitle(quest);
        const phases = extractQuestPhases(quest);
        const objectiveIndex = buildObjectiveStateIndex(quest);
        const rawStatus = String(quest.status || '').trim().toLowerCase();
        const status = rawStatus ? rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1) : 'Active';

        // Build objective list HTML for the first incomplete phase.
        let objectiveHtml = '';
        for (const phase of phases) {
          const objectives = phase.objectives || [];
          objectiveHtml = objectives.map(obj => {
            const merged = mergeObjectiveProgress(obj, objectiveIndex);
            const current = merged.current;
            const target = merged.target_count || 1;
            const completed = merged.completed;
            const icon = completed ? '✅' : '⬜';
            const desc = merged.description || merged.objective_id;
            const progress = merged.type === 'collect' ? ` (${current}/${target})` : '';
            return `<li class="quest-objective ${completed ? 'quest-objective--done' : ''}">${icon} ${desc}${progress}</li>`;
          }).join('');

          // Show only the first phase that has incomplete objectives.
          const allDone = objectives.every(o => mergeObjectiveProgress(o, objectiveIndex).completed);
          if (!allDone) break;
        }

        if (!objectiveHtml) {
          objectiveHtml = '<li class="quest-objective">✅ All objectives complete</li>';
        }

        return `<li class="quest-entry">
          <strong class="quest-title">📜 ${title}</strong>
          <div class="quest-status">Status: ${status}</div>
          <ul class="quest-objectives">${objectiveHtml}</ul>
        </li>`;
      }).join('');
    }

    /**
     * Show a toast notification for quest events.
     * @param {string} message
     * @param {string} type - 'success' | 'info' | 'warning'
     */
    showQuestToast(message, type = 'info') {
      this.appendChatLine('Quest', message, 'system');
    }
  }

  /**
   * StateManager - Centralized state management.
   * Provides a single source of truth for application state.
   */
  class StateManager {
    constructor() {
      this.state = {
        // Selection state
        selectedEntity: null,
        selectedHex: null,
        hoveredHex: null,
        
        // Movement state
        movementRange: null,
        movementRangeOverlay: null,
        
        // Combat state
        combatActive: false,
        serverCombatMode: false,
        attackTarget: null,
        
        // Drag state
        draggedObject: null,
        
        // Flags
        assetsLoaded: false,
        showCoordinates: false,
        showGrid: true
      };
      
      this.listeners = {};
    }

    /**
     * Get state value.
     */
    get(key) {
      return this.state[key];
    }

    /**
     * Set state value and notify listeners.
     */
    set(key, value) {
      const oldValue = this.state[key];
      this.state[key] = value;
      
      // Notify listeners
      if (this.listeners[key]) {
        this.listeners[key].forEach(callback => callback(value, oldValue));
      }
    }

    /**
     * Subscribe to state changes.
     */
    subscribe(key, callback) {
      if (!this.listeners[key]) {
        this.listeners[key] = [];
      }
      this.listeners[key].push(callback);
      
      // Return unsubscribe function
      return () => {
        this.listeners[key] = this.listeners[key].filter(cb => cb !== callback);
      };
    }

    /**
     * Reset all state to defaults.
     */
    reset() {
      this.state = {
        selectedEntity: null,
        selectedHex: null,
        hoveredHex: null,
        movementRange: null,
        movementRangeOverlay: null,
        combatActive: false,
        serverCombatMode: false,
        attackTarget: null,
        draggedObject: null,
        assetsLoaded: false,
        showCoordinates: false,
        showGrid: true,
        showFog: true,
        fogOverlay: null,
        visibleHexes: null
      };
    }
  }

  /**
   * Hex map behavior.
   */
  Drupal.behaviors.hexMap = {
    // Configuration
    config: {
      hexSize: 30,
      gridWidth: 20,
      gridHeight: 20,
      minZoom: 0.5,
      maxZoom: 3.0,
      defaultVisionRange: 8,
      defaultWidth: 800,
      defaultHeight: 600,
      backgroundColor: 0x1a1a2e,
      serverStateSyncIntervalMs: 3000,
    },
    
    // PixiJS containers
    app: null,
    hexContainer: null,
    gridContainer: null,
    objectContainer: null,
    uiContainer: null,
    interactionContainer: null,
    hudContainer: null,
    _roomBanner: null,
    _orientationReferenceOverlay: null,
    _spreadHoverAnchorKey: null,
    _spreadExpandedHexKey: null,
    _spreadClearTimer: null,
    
    // Managers
    uiManager: null,
    stateManager: null,
    
    // ECS architecture
    entityManager: null,
    renderSystem: null,
    movementSystem: null,
    turnManagementSystem: null,
    combatSystem: null,

    // Launch context from campaign/tavern flow.
    launchContext: {},

    // Current user id from drupalSettings.user.uid (0 for anonymous).
    currentUserId: 0,

    // Launch character summary from campaign flow for initial sheet hydration.
    launchCharacter: {},

    // Dungeon payload for room-aware rendering and transitions.
    dungeonData: {},
    activeRoomId: null,

    // Cache of sprite_id => URL from server-side generated images.
    spriteService: null,

    // Procedural tile textures for terrain rendering.
    tileTextures: null,
    
    // Cleanup tracking
    eventListeners: [],
    stageListeners: [],
    tickerCallbacks: [],
    stateSubscriptions: [],

    // [THIN-CLIENT: state adapter] — managed by HexmapStateSync
    stateSync: null,

    attach: function (context, settings) {
      const container = once('hexmap-init', '#hexmap-canvas-container', context);
      
      if (container.length === 0) {
        return;
      }

      // Initialize managers
      this.uiManager = new UIManager();
      this.stateManager = new StateManager();
      this.spriteService = new SpriteService();
      this.uiManager.stateManager = this.stateManager; // Give UIManager access to state manager
      this.stateManager.hexmap = this; // Allow state manager to reference hexmap methods
      this.setupStateSubscriptions();
      
      // Load dungeon data from drupalSettings (populated by HexMapController.php)
      // Data flow: dc_campaign_dungeons.dungeon_data (JSON column) -> HexMapController::normalizeDungeonPayload() -> drupalSettings
      // Schema: dungeon_level.schema.json + hexmap.schema.json + entity_instance.schema.json
      this.launchContext = settings?.dungeoncrawlerContent?.hexmapLaunchContext || {};
      this.dungeonData = settings?.dungeoncrawlerContent?.hexmapDungeonData || {};
      this.launchCharacter = settings?.dungeoncrawlerContent?.hexmapLaunchCharacter || {};
      this.characterData = this.launchCharacter;

      console.log('HexMap Init - Launch Context:', this.launchContext);
      console.log('HexMap Init - Launch Character:', this.launchCharacter);
      console.log('HexMap Init - Has Dungeon Data:', Object.keys(this.dungeonData).length > 0);
      this.activeRoomId = this.dungeonData?.active_room_id || null;
      this.currentUserId = Number(settings?.user?.uid || 0);

      this.initPixiApp(container[0]);
      this.initECS(); // Initialize ECS architecture
      this.generateHexGrid();
      this.setupControls();
      this.setupInteraction();
      this.applyDungeonData();
      this.applyLaunchContext();
      this.initQuestData();

      // [THIN-CLIENT: state adapter] Initialize and start server-state polling.
      this.stateSync = new HexmapStateSync(this);
      this.stateSync.start();

      // Initialize Game Coordinator (Phase 2: server-authoritative game loop).
      const campaignId = this.resolveCampaignId?.() || Number(this.launchContext?.campaign_id || 0);
      if (campaignId > 0 && this.canUseServerCombatApi?.()) {
        this.gameCoordinator = new GameCoordinator(campaignId, this);
        this.gameCoordinator.init().catch(err => {
          console.warn('GameCoordinator init failed; falling back to legacy mode:', err.message);
          this.gameCoordinator = null;
        });
      } else {
        this.gameCoordinator = null;
      }

      try {
        const launchEntitySelected = this.applyLaunchCharacterSelection();
        if (!launchEntitySelected) {
          this.applyLaunchCharacterSummary();
        }
      } catch (error) {
        console.error('Launch character hydration failed; falling back to summary.', error);
        this.applyLaunchCharacterSummary();
      }
      
      // Start game loop and track for cleanup
      const updateCallback = (delta) => this.update(delta);
      this.app.ticker.add(updateCallback);
      this.tickerCallbacks.push(updateCallback);
    },
    
    /**
     * Detach behavior and cleanup resources.
     */
    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      
      console.log('Cleaning up hexmap resources...');
      
      // Remove ticker callbacks
      this.tickerCallbacks.forEach(callback => {
        if (this.app && this.app.ticker) {
          this.app.ticker.remove(callback);
        }
      });
      this.tickerCallbacks = [];
      
      // Remove event listeners
      this.eventListeners.forEach(({ element, event, handler }) => {
        element.removeEventListener(event, handler);
      });
      this.eventListeners = [];

      // Remove stage listeners
      this.stageListeners.forEach(({ event, handler }) => {
        if (this.app && this.app.stage) {
          this.app.stage.off(event, handler);
        }
      });
      this.stageListeners = [];

      // Unsubscribe state listeners
      this.stateSubscriptions.forEach(unsubscribe => unsubscribe());
      this.stateSubscriptions = [];

      this.stateSync?.stop();
      this.stateSync = null;

      // Cleanup Game Coordinator.
      if (this.gameCoordinator) {
        this.gameCoordinator.destroy();
        this.gameCoordinator = null;
      }
      
      // Cleanup ECS systems
      if (this.entityManager) {
        this.entityManager.removeAllEntities();
        this.entityManager = null;
      }
      
      // Cleanup PixiJS
      const movementRangeOverlay = this.stateManager ? this.stateManager.get('movementRangeOverlay') : null;
      if (movementRangeOverlay) {
        movementRangeOverlay.destroy();
        this.stateManager.set('movementRangeOverlay', null);
      }

      const fogOverlay = this.stateManager ? this.stateManager.get('fogOverlay') : null;
      if (fogOverlay) {
        fogOverlay.destroy();
        this.stateManager.set('fogOverlay', null);
      }
      
      if (this.app) {
        this.app.destroy(true, { children: true, texture: false, baseTexture: false });
        this.app = null;
      }
      
      // Reset state
      if (this.stateManager) {
        this.stateManager.reset();
      }

      this.launchContext = {};
      this.dungeonData = {};
      this.launchCharacter = {};
      this.activeRoomId = null;
      
      console.log('Hexmap cleanup complete');
    },

    /**
     * Initialize ECS architecture.
     */
    initECS: function () {
      // Store self reference for callbacks
      const self = this;
      
      // Create entity manager
      this.entityManager = new EntityManager();
      
      // Create render system
      this.renderSystem = new RenderSystem(
        this.entityManager,
        this.app,
        {
          hex: this.hexContainer,
          object: this.objectContainer,
          ui: this.uiContainer
        }
      );
      this.renderSystem.setHexSize(this.config.hexSize);
      this.entityManager.addSystem(this.renderSystem);
      
      // Create movement system
      this.movementSystem = new MovementSystem(this.entityManager);
      this.entityManager.addSystem(this.movementSystem);
      
      // Create combat system
      this.combatSystem = new CombatSystem(this.entityManager);
      this.entityManager.addSystem(this.combatSystem);
      
      // Set up combat callbacks
      this.combatSystem.onAttack(function(attackData) {
        self.onAttackPerformed(attackData);
      });
      this.combatSystem.onDamage(function(damageData) {
        self.onDamageDealt(damageData);
      });
      
      // Create turn management system
      this.turnManagementSystem = new TurnManagementSystem(this.entityManager);
      this.entityManager.addSystem(this.turnManagementSystem);
      
      // Set up turn management callbacks
      this.turnManagementSystem.onTurnChange(function(entity, turnIndex, totalTurns) {
        self.onTurnChange(entity, turnIndex, totalTurns);
      });
      this.turnManagementSystem.onRoundChange(function(roundNumber) {
        self.onRoundChange(roundNumber);
      });
      this.turnManagementSystem.onCombatStateChange(function(combatState) {
        self.onCombatStateChange(combatState);
      });
      
      console.log('ECS initialized');
    },
    
    /**
     * Game loop update.
     * @param {number} delta - Time delta from PixiJS ticker
     */
    update: function (delta) {
      // Update all ECS systems
      if (this.entityManager) {
        this.entityManager.update(delta * 16.67); // Convert to milliseconds
      }
    },

    /**
     * Setup state subscriptions for reactive UI updates.
     */
    setupStateSubscriptions: function () {
      this.stateSubscriptions.push(
        this.stateManager.subscribe('showGrid', (value) => {
          if (this.gridContainer) {
            this.gridContainer.visible = value;
          }
        })
      );

      this.stateSubscriptions.push(
        this.stateManager.subscribe('showFog', () => {
          this.refreshFogOfWar();
        })
      );
    },

    /**
     * Set world layer position for all render containers.
     * @param {number} x - X coordinate
     * @param {number} y - Y coordinate
     */
    setWorldPosition: function (x, y) {
      this.backgroundContainer.x = x;
      this.backgroundContainer.y = y;
      this.hexContainer.x = x;
      this.hexContainer.y = y;
      this.gridContainer.x = x;
      this.gridContainer.y = y;
      this.propsContainer.x = x;
      this.propsContainer.y = y;
      this.objectContainer.x = x;
      this.objectContainer.y = y;
      this.fxContainer.x = x;
      this.fxContainer.y = y;
      this.uiContainer.x = x;
      this.uiContainer.y = y;
      if (this.interactionContainer) {
        this.interactionContainer.x = x;
        this.interactionContainer.y = y;
      }
    },

    /**
     * Set world layer scale for all render containers.
     * @param {number} scale - Uniform scale value
     */
    setWorldScale: function (scale) {
      this.backgroundContainer.scale.set(scale);
      this.hexContainer.scale.set(scale);
      this.gridContainer.scale.set(scale);
      this.propsContainer.scale.set(scale);
      this.objectContainer.scale.set(scale);
      this.fxContainer.scale.set(scale);
      this.uiContainer.scale.set(scale);
      if (this.interactionContainer) {
        this.interactionContainer.scale.set(scale);
      }
    },

    /**
     * Clear all ECS entities and related overlays/state.
     */
    clearEntities: function () {
      if (!this.entityManager) {
        return;
      }

      this.clearSpreadInteractionTargets();
      this._spreadHoverAnchorKey = null;

      this.deselectEntity();

      // End any existing combat before wiping entities so turn order resets cleanly.
      if (this.turnManagementSystem) {
        this.turnManagementSystem.endCombat();
      }

      this.entityManager.removeAllEntities();
      this.uiManager.hideEntityInfo();
      this.uiManager.updateCurrentTurn('-', null, null, false, null, false);
      this.uiManager.updateInitiativeTracker([]);
      console.log('Cleared all ECS entities');
    },
    
    /**
     * Turn change callback.
     * @param {Entity} entity - Entity whose turn it is
     * @param {number} turnIndex - Current turn index
     * @param {number} totalTurns - Total turns in round
     */
    onTurnChange: function (entity, turnIndex, totalTurns) {
      const identity = entity.getComponent('IdentityComponent');
      const actions = entity.getComponent('ActionsComponent');
      const movement = entity.getComponent('MovementComponent');
      const combat = entity.getComponent('CombatComponent');
      const name = identity ? identity.name : `Entity ${entity.id}`;
      const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
      
      console.log(`Turn change: ${name} (${turnIndex + 1}/${totalTurns})`);
      
      // Update UI via UIManager
      this.uiManager.updateCurrentTurn(name, actions, movement, actions?.hasReactionAvailable(), combat?.team, isPlayersTurn);
      this.uiManager.updateInitiativeTracker(this.turnManagementSystem.getInitiativeOrder());
      const moveLeft = movement ? movement.movementRemaining : 0;
      const canAct = actions ? actions.actionsRemaining > 0 : false;
      const actionMode = this.stateManager.get('actionMode') || 'attack';
      this.uiManager.updateActionMode(actionMode, { canAct, canInteract: canAct, moveLeft, isPlayersTurn });
      
      // Auto-select entity on their turn (if player controlled). NPCs are resolved server-side.
      if (combat && combat.isPlayerTeam()) {
        this.selectEntity(entity);
      }
      this.syncTokenBadgeState();
    },
    
    /**
     * Round change callback.
     * @param {number} roundNumber - New round number
     */
    onRoundChange: function (roundNumber) {
      console.log(`Round ${roundNumber} started`);
      this.uiManager.updateRound(roundNumber);
      this.stateManager.set('actionMode', 'attack');
    },
    
    /**
     * Combat state change callback.
     * @param {string} combatState - New combat state
     */
    onCombatStateChange: function (combatState) {
      console.log(`Combat state: ${combatState}`);
      this.stateManager.set('combatActive', combatState === CombatState.IN_PROGRESS || combatState === CombatState.ROLLING_INITIATIVE);
      
      // Update UI
      this.uiManager.updateCombatControls(combatState);
    },

    /**
     * Initialize PixiJS application.
     */
    initPixiApp: function (container) {
      // Create PixiJS application
      this.app = new PIXI.Application({
        width: container.clientWidth || this.config.defaultWidth,
        height: container.clientHeight || this.config.defaultHeight,
        backgroundColor: this.config.backgroundColor,
        antialias: true,
        resolution: window.devicePixelRatio || 1,
        autoDensity: true,
      });

      container.appendChild(this.app.view);

      // Scene Layer Contract — deterministic render stack.
      // World-space layers (z 5–45) move with pan/zoom via setWorldPosition/setWorldScale.
      // Screen-space layers (z 50+) remain fixed during pan/zoom.
      //
      // Layer       | zIndex | Space       | Purpose
      // ------------|--------|-------------|------------------------------------------
      // background  |      5 | world       | Room background art, atmosphere assets
      // hex         |     10 | world       | Terrain base hexes
      // grid        |     20 | world       | Grid lines, coordinates, measurement overlays
      // props       |     25 | world       | Static scene props (furniture, obstacles)
      // object      |     30 | world       | Tokens (characters, NPCs, enemies)
      // fx          |     35 | world       | Fog, lighting, move/attack/reveal effects
      // ui          |     40 | world       | Overlays, templates, world-space UI elements
      // interaction |     45 | world       | Hit areas, pointer capture
      // hud         |     50 | screen      | HUD, initiative rail, action buttons

      this.backgroundContainer = new PIXI.Container();
      this.hexContainer = new PIXI.Container();
      this.gridContainer = new PIXI.Container();
      this.propsContainer = new PIXI.Container();
      this.objectContainer = new PIXI.Container();
      this.fxContainer = new PIXI.Container();
      this.uiContainer = new PIXI.Container();
      this.interactionContainer = new PIXI.Container();

      this.app.stage.sortableChildren = true;
      this.backgroundContainer.zIndex = 5;
      this.hexContainer.zIndex = 10;
      this.gridContainer.zIndex = 20;
      this.propsContainer.zIndex = 25;
      this.objectContainer.zIndex = 30;
      this.fxContainer.zIndex = 35;
      this.uiContainer.zIndex = 40;
      this.interactionContainer.zIndex = 45;

      this.app.stage.addChild(this.backgroundContainer);
      this.app.stage.addChild(this.hexContainer);
      this.app.stage.addChild(this.gridContainer);
      this.app.stage.addChild(this.propsContainer);
      this.app.stage.addChild(this.objectContainer);
      this.app.stage.addChild(this.fxContainer);
      this.app.stage.addChild(this.uiContainer);
      this.app.stage.addChild(this.interactionContainer);

      // Center world-space layers
      const cx = this.app.screen.width / 2;
      const cy = this.app.screen.height / 2;
      this.backgroundContainer.x = cx;
      this.backgroundContainer.y = cy;
      this.hexContainer.x = cx;
      this.hexContainer.y = cy;
      this.gridContainer.x = cx;
      this.gridContainer.y = cy;
      this.propsContainer.x = cx;
      this.propsContainer.y = cy;
      this.objectContainer.x = cx;
      this.objectContainer.y = cy;
      this.fxContainer.x = cx;
      this.fxContainer.y = cy;
      this.uiContainer.x = cx;
      this.uiContainer.y = cy;
      this.interactionContainer.x = cx;
      this.interactionContainer.y = cy;

      this.interactionContainer.eventMode = 'passive';
      this.interactionContainer.interactiveChildren = true;

      // Enable interactivity on stage
      this.app.stage.interactive = true;
      this.app.stage.hitArea = this.app.screen;

      // Screen-space HUD — not affected by pan/zoom
      this.hudContainer = new PIXI.Container();
      this.hudContainer.zIndex = 50;
      this.app.stage.addChild(this.hudContainer);

      console.log('PixiJS initialized');
    },

    /**
     * Draw compass rose in the bottom-right corner of the canvas (screen-space).
     */
    drawCompassRose: function () {
      if (!this.hudContainer || !this.app) return;

      const g = new PIXI.Graphics();
      const cx = this.app.screen.width - 50;
      const cy = this.app.screen.height - 55;
      const r = 22;
      const flatEdgeOffset = Math.sin(Math.PI / 3) * r;
      const edgeDirections = [
        { key: 'N', angle: -Math.PI / 2, color: 0xe53e3e },
        { key: 'NE', angle: -Math.PI / 6, color: 0xa0aec0 },
        { key: 'SE', angle: Math.PI / 6, color: 0xa0aec0 },
        { key: 'S', angle: Math.PI / 2, color: 0xa0aec0 },
        { key: 'SW', angle: (5 * Math.PI) / 6, color: 0xa0aec0 },
        { key: 'NW', angle: -(5 * Math.PI) / 6, color: 0xa0aec0 },
      ];

      // Background disc
      g.beginFill(0x1a202c, 0.7);
      g.drawCircle(cx, cy, r + 6);
      g.endFill();
      g.lineStyle(1, 0x4a5568, 0.8);
      g.drawCircle(cx, cy, r + 6);

      // Hex orientation guide (matches map hex orientation: flat-top)
      g.lineStyle(1, 0x64748b, 0.45);
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = cx + r * Math.cos(angle);
        const y = cy + r * Math.sin(angle);
        if (i === 0) {
          g.moveTo(x, y);
        } else {
          g.lineTo(x, y);
        }
      }
      g.closePath();

      // Direction arrows for edge-centered orientation vectors.
      edgeDirections.forEach(({ angle, color }) => {
        const tipX = cx + Math.cos(angle) * (r + 2);
        const tipY = cy + Math.sin(angle) * (r + 2);
        const baseCx = cx + Math.cos(angle) * (r - 6);
        const baseCy = cy + Math.sin(angle) * (r - 6);
        const perpX = -Math.sin(angle) * 4;
        const perpY = Math.cos(angle) * 4;

        g.beginFill(color);
        g.drawPolygon([
          tipX, tipY,
          baseCx + perpX, baseCy + perpY,
          baseCx - perpX, baseCy - perpY,
        ]);
        g.endFill();
      });

      this.hudContainer.addChild(g);

      // Cardinal labels
      const labelStyle = { fontFamily: 'Arial', fontSize: 11, fill: 0xe2e8f0, fontWeight: 'bold' };
      const labels = edgeDirections.map(({ key, angle }) => ({
        text: key,
        x: cx + Math.cos(angle) * (r + 11),
        y: cy + Math.sin(angle) * (r + 11) - 6,
      }));
      labels.forEach(({ text, x, y }) => {
        const label = new PIXI.Text(text, labelStyle);
        label.x = x;
        label.y = y;
        this.hudContainer.addChild(label);
      });
    },

    /**
     * Show room name banner as a fixed HUD overlay at the top of the canvas.
     * @param {string} roomName - Room name
     * @param {string} [subtitle] - Optional subtitle (terrain, lighting, etc.)
     */
    showRoomBanner: function (roomName, subtitle) {
      if (!this.hudContainer || !this.app) return;

      // Remove previous banner if any
      if (this._roomBanner) {
        this.hudContainer.removeChild(this._roomBanner);
        this._roomBanner.destroy({ children: true });
        this._roomBanner = null;
      }

      const container = new PIXI.Container();
      const screenW = this.app.screen.width;

      // Background bar
      const bg = new PIXI.Graphics();
      bg.beginFill(0x1a202c, 0.85);
      bg.drawRoundedRect(10, 8, screenW - 20, subtitle ? 46 : 32, 6);
      bg.endFill();
      container.addChild(bg);

      // Room name
      const title = new PIXI.Text(roomName, {
        fontFamily: 'Arial',
        fontSize: 16,
        fontWeight: 'bold',
        fill: 0xf7fafc,
      });
      title.x = 20;
      title.y = 12;
      container.addChild(title);

      // Subtitle (terrain / lighting)
      if (subtitle) {
        const sub = new PIXI.Text(subtitle, {
          fontFamily: 'Arial',
          fontSize: 11,
          fill: 0xa0aec0,
        });
        sub.x = 20;
        sub.y = 32;
        container.addChild(sub);
      }

      this.hudContainer.addChild(container);
      this._roomBanner = container;
    },

    /**
     * Generate hexagonal grid.
     */
    generateHexGrid: function () {
      // Clear existing hexes
      this.hexContainer.removeChildren();
      this.gridContainer.removeChildren();
      this.clearSpreadInteractionTargets();
      this._spreadHoverAnchorKey = null;

      // Reset transient UI state tied to previous hex graphics
      this.stateManager.set('hoveredHex', null);
      this.uiManager.updateHoveredHex(null, null);
      this.uiManager.updateHoveredObject('None');
      if (this.uiManager.elements.selectedHex) {
        this.uiManager.elements.selectedHex.textContent = 'None';
      }
      this.stateManager.set('selectedHex', null);
      this.hideMovementRange();

      const hexSize = this.config.hexSize;
      const width = this.config.gridWidth;
      const height = this.config.gridHeight;

      // Calculate hex dimensions
      const hexWidth = hexSize * 2;
      const hexHeight = Math.sqrt(3) * hexSize;

      // Generate grid (flat-top orientation)
      for (let q = -Math.floor(width / 2); q < Math.ceil(width / 2); q++) {
        for (let r = -Math.floor(height / 2); r < Math.ceil(height / 2); r++) {
          this.createHex(q, r, hexSize);
        }
      }

      // Reapply room/obstacle styling for the rebuilt grid
      this.paintActiveRoom();

      // If an entity remains selected, refresh its movement range overlay with the new grid sizing
      const selectedEntity = this.stateManager.get('selectedEntity');
      if (selectedEntity) {
        this.showMovementRange(selectedEntity);
      }

      this.refreshFogOfWar();
      this.renderOrientationReferenceHex();

      console.log(`Generated ${width}x${height} hex grid`);
    },

    /**
     * Create a single hex.
     */
    createHex: function (q, r, size) {
      const hex = new PIXI.Graphics();
      const pos = this.axialToPixel(q, r, size);

      // Draw hex shape
      hex.beginFill(0x2d3748);
      hex.lineStyle(1, 0x4a5568, 1);
      
      // Draw hexagon (flat-top)
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = size * Math.cos(angle);
        const y = size * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      // Position hex
      hex.x = pos.x;
      hex.y = pos.y;

      // Store hex data
      hex.hexData = { q, r };

      // Make interactive
      hex.interactive = true;
      hex.buttonMode = true;

      // Event handlers
      hex.on('pointerover', () => this.onHexHover(hex));
      hex.on('pointerout', () => this.onHexOut(hex));
      hex.on('pointerdown', (event) => this.onHexClick(hex, event));

      this.hexContainer.addChild(hex);

      // Add coordinates text if enabled
      if (this.stateManager.get('showCoordinates')) {
        this.addHexCoordinates(hex, q, r, pos);
      }
    },

    /**
     * Add coordinate text to hex.
     */
    addHexCoordinates: function (hex, q, r, pos) {
      const text = new PIXI.Text(`${q},${r}`, {
        fontFamily: 'Arial',
        fontSize: 10,
        fill: 0x718096,
        align: 'center',
      });
      
      text.anchor.set(0.5);
      text.x = pos.x;
      text.y = pos.y;
      text.visible = Boolean(this.stateManager.get('showCoordinates'));
      
      this.gridContainer.addChild(text);
      hex.hexCoordText = text;
    },

    /**
     * Show or hide entity labels for entities located at a specific hex.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @param {boolean} visible - Visibility toggle
     */
    setEntityLabelsForHex: function (q, r, visible) {
      if (!this.entityManager) {
        return;
      }

      // Keep in-world hover labels disabled to avoid screen-filling text.
      // Exact hover/click feedback is provided through the HUD panels and the
      // spread click targets instead of rendering every name over the map.
      this.hideAllEntityLabels();
      const shouldShowLabels = false;

      const entities = this.entityManager.getEntitiesWith('PositionComponent', 'RenderComponent');
      entities.forEach((entity) => {
        const position = entity.getComponent('PositionComponent');
        const render = entity.getComponent('RenderComponent');
        if (!position || !render) {
          return;
        }

        if (position.q === q && position.r === r) {
          render._hoverLabelVisible = shouldShowLabels;
        }
      });
    },

    /**
     * Spread co-located entities apart while hovering a crowded hex.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @param {boolean} active - Whether spread should be active
     */
    setEntitySpreadForHex: function (q, r, active) {
      if (!this.entityManager) {
        return;
      }

      const entities = this.getLiveEntitiesAtHex(q, r);
      if (!entities.length) {
        return;
      }

      const currentHexSize = Number(this.config?.hexSize || 0);
      const spreadRadius = currentHexSize > 0 ? currentHexSize * 1.0 : 30;

      if (!active || entities.length <= 1) {
        entities.forEach((entity) => {
          const render = entity.getComponent('RenderComponent');
          if (!render) {
            return;
          }
          render._spreadOffsetX = 0;
          render._spreadOffsetY = 0;
        });
        if (this._spreadExpandedHexKey === `${q}:${r}`) {
          this._spreadExpandedHexKey = null;
        }
        return;
      }

      entities.forEach((entity, index) => {
        const render = entity.getComponent('RenderComponent');
        if (!render) {
          return;
        }

        const angle = ((Math.PI * 2) / entities.length) * index - (Math.PI / 2);
        render._spreadOffsetX = Math.cos(angle) * spreadRadius;
        render._spreadOffsetY = Math.sin(angle) * spreadRadius;
      });

      this._spreadExpandedHexKey = `${q}:${r}`;
      this.refreshSpreadInteractionTargets(q, r);
    },

    /**
     * Remove temporary click targets for spread entities.
     */
    clearSpreadInteractionTargets: function () {
      if (!this.interactionContainer) {
        return;
      }

      this.interactionContainer.removeChildren().forEach((child) => {
        child.destroy({ children: true });
      });
    },

    /**
     * Build temporary click targets for entities visually spread from a crowded hex.
     * @param {number} q
     * @param {number} r
     */
    refreshSpreadInteractionTargets: function (q, r) {
      this.clearSpreadInteractionTargets();

      if (!this.interactionContainer) {
        return;
      }

      const entities = this.getLiveEntitiesAtHex(q, r);
      if (entities.length <= 1) {
        return;
      }

      const hexKey = `${q}:${r}`;
      entities.forEach((entity) => {
        const render = entity.getComponent('RenderComponent');
        const center = this.getRenderedEntityCenter(entity);
        if (!render || !center) {
          return;
        }

        const spriteWidth = Number(render?.sprite?.width || 0);
        const spriteHeight = Number(render?.sprite?.height || 0);
        const hitRadius = Math.max(
          this.config.hexSize * 0.42,
          spriteWidth * 0.4,
          spriteHeight * 0.4
        );

        const target = new PIXI.Graphics();
        target.beginFill(0xffffff, 0.001);
        target.drawCircle(0, 0, hitRadius);
        target.endFill();
        target.x = center.x;
        target.y = center.y;
        target.zIndex = 9500 + (render.zIndex || 0);
        target.eventMode = 'static';
        target.interactive = true;
        target.cursor = 'pointer';

        target.on('pointerover', () => {
          this._spreadHoverAnchorKey = hexKey;
          this.setEntityLabelsForHex(q, r, true);
          const identity = entity.getComponent('IdentityComponent');
          this.uiManager?.updateHoveredObject(identity?.name || this.getObjectLabelAtHex(q, r));
        });

        target.on('pointerout', () => {
          if (this._spreadHoverAnchorKey === hexKey) {
            this._spreadHoverAnchorKey = null;
          }
          this.scheduleSpreadHoverClear(q, r);
        });

        target.on('pointertap', (event) => {
          if (typeof event?.stopPropagation === 'function') {
            event.stopPropagation();
          }
          this.handleEntityClick(entity, q, r, event);
        });

        this.interactionContainer.addChild(target);
      });
    },

    /**
     * Defer clearing spread state so pointer travel from the hex to a spread item
     * does not immediately collapse the hover layout.
     * @param {number} q
     * @param {number} r
     */
    scheduleSpreadHoverClear: function (q, r) {
      if (this._spreadClearTimer) {
        clearTimeout(this._spreadClearTimer);
      }

      const hexKey = `${q}:${r}`;
      this._spreadClearTimer = setTimeout(() => {
        this._spreadClearTimer = null;

        if (this._spreadHoverAnchorKey === hexKey) {
          return;
        }

        const hoveredHex = this.stateManager.get('hoveredHex');
        if (hoveredHex?.hexData?.q === q && hoveredHex?.hexData?.r === r) {
          return;
        }

        this.clearCrowdedHexHoverState(q, r);
      }, 120);
    },

    /**
     * Collapse temporary crowded-hex hover state and restore idle UI.
     * @param {?number} q
     * @param {?number} r
     */
    clearCrowdedHexHoverState: function (q = null, r = null) {
      if (this._spreadClearTimer) {
        clearTimeout(this._spreadClearTimer);
        this._spreadClearTimer = null;
      }

      const targets = [];
      const addTarget = (targetQ, targetR) => {
        if (!Number.isFinite(targetQ) || !Number.isFinite(targetR)) {
          return;
        }
        const key = `${targetQ}:${targetR}`;
        if (targets.some((target) => target.key === key)) {
          return;
        }
        targets.push({ key, q: targetQ, r: targetR });
      };

      addTarget(q, r);

      const hoveredHex = this.stateManager.get('hoveredHex');
      if (hoveredHex?.hexData) {
        addTarget(hoveredHex.hexData.q, hoveredHex.hexData.r);
      }

      targets.forEach((target) => {
        this.setEntityLabelsForHex(target.q, target.r, false);
        this.setEntitySpreadForHex(target.q, target.r, false);

        const renderedHex = this.findHexByCoords(target.q, target.r);
        if (renderedHex && this.stateManager.get('selectedHex') !== renderedHex) {
          this.resetHexAppearance(renderedHex);
        }
        if (renderedHex?.hexCoordText) {
          renderedHex.hexCoordText.visible = Boolean(this.stateManager.get('showCoordinates'));
        }
      });

      this.clearSpreadInteractionTargets();
      this._spreadHoverAnchorKey = null;
      this.stateManager.set('hoveredHex', null);
      this.uiManager.updateHoveredHex(null, null);
      this.uiManager.updateHoveredObject('None');
      this.uiManager.updateHexDetails(null);
    },

    /**
     * Clear all temporary hover spread offsets.
     */
    clearAllEntitySpreadOffsets: function () {
      if (!this.entityManager) {
        return;
      }

      const entities = this.entityManager.getEntitiesWith('RenderComponent');
      entities.forEach((entity) => {
        const render = entity.getComponent('RenderComponent');
        if (!render) {
          return;
        }
        render._spreadOffsetX = 0;
        render._spreadOffsetY = 0;
      });
    },

    /**
     * Hide all entity labels until hover reveals specific hex labels.
     */
    hideAllEntityLabels: function () {
      if (!this.entityManager) {
        return;
      }

      const entities = this.entityManager.getEntitiesWith('RenderComponent');
      entities.forEach((entity) => {
        const render = entity.getComponent('RenderComponent');
        if (!render) {
          return;
        }
        render._hoverLabelVisible = false;
      });
    },

    /**
     * Convert axial coordinates (q, r) to pixel position.
     */
    axialToPixel: function (q, r, size) {
      const x = size * (3 / 2 * q);
      const y = size * (Math.sqrt(3) / 2 * q + Math.sqrt(3) * r);
      return { x, y };
    },

    /**
     * Convert pixel position to axial coordinates.
     */
    pixelToAxial: function (x, y, size) {
      const q = (2 / 3 * x) / size;
      const r = (-1 / 3 * x + Math.sqrt(3) / 3 * y) / size;
      return this.roundAxial(q, r);
    },

    /**
     * Round fractional axial coordinates to nearest hex.
     */
    roundAxial: function (q, r) {
      const s = -q - r;
      
      let rq = Math.round(q);
      let rr = Math.round(r);
      let rs = Math.round(s);
      
      const qDiff = Math.abs(rq - q);
      const rDiff = Math.abs(rr - r);
      const sDiff = Math.abs(rs - s);
      
      if (qDiff > rDiff && qDiff > sDiff) {
        rq = -rr - rs;
      } else if (rDiff > sDiff) {
        rr = -rq - rs;
      }
      
      return { q: rq, r: rr };
    },

    /**
     * Hex hover event.
     */
    onHexHover: function (hex) {
      if (this._spreadClearTimer) {
        clearTimeout(this._spreadClearTimer);
        this._spreadClearTimer = null;
      }

      const incomingHexKey = hex?.hexData ? `${hex.hexData.q}:${hex.hexData.r}` : null;
      if (this._spreadExpandedHexKey && this._spreadExpandedHexKey !== incomingHexKey) {
        const [prevQ, prevR] = this._spreadExpandedHexKey.split(':').map(Number);
        if (Number.isFinite(prevQ) && Number.isFinite(prevR)) {
          this.setEntityLabelsForHex(prevQ, prevR, false);
          this.setEntitySpreadForHex(prevQ, prevR, false);
        }
        this.clearSpreadInteractionTargets();
      }

      const previousHover = this.stateManager.get('hoveredHex');
      if (previousHover?.hexCoordText) {
        previousHover.hexCoordText.visible = false;
      }
      if (previousHover?.hexData) {
        this.setEntityLabelsForHex(previousHover.hexData.q, previousHover.hexData.r, false);
        this.setEntitySpreadForHex(previousHover.hexData.q, previousHover.hexData.r, false);
        this.clearSpreadInteractionTargets();
      }

      // Highlight hex
      hex.clear();
      hex.beginFill(0x4a5568);
      hex.lineStyle(2, 0xfbbf24, 1);
      
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.config.hexSize * Math.cos(angle);
        const y = this.config.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      this.stateManager.set('hoveredHex', hex);
      if (hex.hexCoordText) {
        hex.hexCoordText.visible = true;
      }
      
      // Update UI
      const { q, r } = hex.hexData;
      this._spreadHoverAnchorKey = `${q}:${r}`;
      this.setEntityLabelsForHex(q, r, true);
      this.setEntitySpreadForHex(q, r, true);
      this.uiManager.updateHoveredHex(q, r);
      this.uiManager.updateHoveredObject(this.getObjectLabelAtHex(q, r));
      this.uiManager.updateHexDetails(this.getHexDetail(q, r));
    },

    /**
      this._spreadExpandedHexKey = null;
     * Hex out event.
     */
    onHexOut: function (hex) {
      // Reset hex appearance (unless it's selected)
      if (this.stateManager.get('selectedHex') !== hex) {
        this.resetHexAppearance(hex);
      }

      if (hex?.hexCoordText) {
        const showCoordinates = Boolean(this.stateManager.get('showCoordinates'));
        hex.hexCoordText.visible = showCoordinates;
      }
      if (hex?.hexData) {
        const hexKey = `${hex.hexData.q}:${hex.hexData.r}`;
        if (this._spreadHoverAnchorKey === hexKey) {
          this._spreadHoverAnchorKey = null;
        }
        this.scheduleSpreadHoverClear(hex.hexData.q, hex.hexData.r);
      }

      this.stateManager.set('hoveredHex', null);
    },

    /**
     * Handle an exact entity click, including items and obstacles.
     * @param {Entity} entity
     * @param {number} q
     * @param {number} r
     * @param {PIXI.FederatedPointerEvent|null} pointerEvent
     * @returns {boolean}
     */
    handleEntityClick: function (entity, q, r, pointerEvent = null) {
      if (!entity) {
        return false;
      }

      const selectedEntity = this.stateManager.get('selectedEntity');
      const actionMode = this.stateManager.get('actionMode') || 'attack';

      if (selectedEntity && actionMode === 'interact' && entity.id !== selectedEntity.id) {
        if (this.performInteractAtHex(selectedEntity, q, r, entity)) {
          this.refreshSelectedHexContents(q, r);
          return true;
        }
      }

      if (selectedEntity && entity.id !== selectedEntity.id) {
        const attackerCombat = selectedEntity.getComponent('CombatComponent');
        const targetCombat = entity.getComponent('CombatComponent');

        if (actionMode === 'attack' && attackerCombat && targetCombat && attackerCombat.isHostileTo(targetCombat)) {
          const canAttackCheck = this.combatSystem.canAttack(selectedEntity, entity);
          console.info('Click attack check', { actorId: selectedEntity.id, targetId: entity.id, mode: actionMode, check: canAttackCheck });
          if (!canAttackCheck.canAttack) {
            console.warn('Cannot attack target', canAttackCheck.reason);
            return true;
          }

          this.performAttack(selectedEntity, entity);
          return true;
        }
      }

      if (entity.hasComponent('MovementComponent')) {
        this.selectEntity(entity);
        this.refreshSelectedHexContents(q, r);
        return true;
      }

      this.uiManager.showEntityInfo(entity);
      this.clearCrowdedHexHoverState(q, r);
      if (this.uiManager.elements.actionInstruction) {
        const identity = entity.getComponent('IdentityComponent');
        this.uiManager.elements.actionInstruction.textContent = `Inspecting ${identity?.name || 'entity'} in hex (${q}, ${r}).`;
      }
      this.refreshSelectedHexContents(q, r);
      return true;
    },

    /**
     * Hex click event.
     */
    onHexClick: function (hex, pointerEvent = null) {
      const { q, r } = hex.hexData;

      this.refreshSelectedHexContents(q, r);

      // Game Coordinator intercept — routes clicks to the active phase handler.
      // Falls through to legacy logic if the coordinator doesn't consume the click.
      if (this.gameCoordinator?.isActive() && this.gameCoordinator.handleHexClick(q, r)) {
        return;
      }
      
      // Mode 1: Room transition if hex is a passable room connection endpoint.
      if (this.tryTransitionAtHex(q, r)) {
        return;
      }
      
      // Mode 2: Check if clicking on an entity
      const entitiesAtPos = this.getLiveEntitiesAtHex(q, r);
      const pickedEntity = this.pickEntityAtHexFromPointer(q, r, pointerEvent, entitiesAtPos);
      const selectedEntity = this.stateManager.get('selectedEntity');
      const entitiesToCheck = pickedEntity
        ? [pickedEntity, ...entitiesAtPos.filter((entity) => entity.id !== pickedEntity.id)]
        : entitiesAtPos;

      for (const entity of entitiesToCheck) {
        const pos = entity.getComponent('PositionComponent');
        if (pos.q === q && pos.r === r) {
          if (this.handleEntityClick(entity, q, r, pointerEvent)) {
            return;
          }
        }
      }
      
      // Mode 3: Move selected entity
      const actionMode = this.stateManager.get('actionMode') || 'attack';
      let movementRange = this.stateManager.get('movementRange');

      // If we lost the cached range (e.g., after switching modes), rebuild so clicks still work.
      if (selectedEntity && actionMode === 'move' && (!movementRange || movementRange.size === 0)) {
        this.showMovementRange(selectedEntity);
        movementRange = this.stateManager.get('movementRange');
      }

      if (selectedEntity && movementRange) {
        const hexKey = `${q}_${r}`;
        if (movementRange.has(hexKey)) {
          if (actionMode !== 'move') {
            // Require explicit move mode to avoid accidental moves while targeting.
            this.uiManager.updateActionMode('attack', { canAct: true, canInteract: true, moveLeft: 0, isPlayersTurn: true });
            return;
          }
          // Try to move entity
          const success = this.movementSystem.moveEntity(selectedEntity, q, r);
          if (success) {
            console.log(`Moved entity to (${q}, ${r})`);
            // Refresh movement range after move
            this.showMovementRange(selectedEntity);
            const actions = selectedEntity.getComponent('ActionsComponent');
            const movementComp = selectedEntity.getComponent('MovementComponent');
            const combat = selectedEntity.getComponent('CombatComponent');
            const identity = selectedEntity.getComponent('IdentityComponent');
            const name = identity ? identity.name : `Entity ${selectedEntity.id}`;
            const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
            if (actions && movementComp) {
              this.uiManager.updateCurrentTurn(name, actions, movementComp, actions.hasReactionAvailable(), combat?.team, isPlayersTurn);
            }
            this.refreshFogOfWar();
          }
          return;
        }
      }

      if (selectedEntity && actionMode === 'interact') {
        const interacted = this.performInteractAtHex(selectedEntity, q, r);
        if (interacted) {
          return;
        }
      }
      
      // Mode 4: Default hex selection
      // Deselect previous hex
      const previousSelectedHex = this.stateManager.get('selectedHex');
      if (previousSelectedHex) {
        this.onHexOut(previousSelectedHex);
      }

      this.setSelectedHex(hex);
      this.refreshSelectedHexContents(q, r);
      
      console.log('Selected hex:', q, r);
    },

    /**
     * Return all live ECS entities on the given hex, sorted top-down by render order.
     * @param {number} q
     * @param {number} r
     * @returns {Array<Entity>}
     */
    getLiveEntitiesAtHex: function (q, r) {
      if (!this.entityManager) {
        return [];
      }

      const entities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
      return entities
        .filter((entity) => {
          const pos = entity.getComponent('PositionComponent');
          return pos?.q === q && pos?.r === r;
        })
        .sort((a, b) => {
          const ar = a.getComponent('RenderComponent');
          const br = b.getComponent('RenderComponent');
          return (br?.zIndex || 0) - (ar?.zIndex || 0);
        });
    },

    /**
     * Resolve an entity's rendered center position, including hover spread.
     * @param {Entity} entity
     * @returns {{x:number, y:number}|null}
     */
    getRenderedEntityCenter: function (entity) {
      if (!entity) {
        return null;
      }

      const position = entity.getComponent('PositionComponent');
      const render = entity.getComponent('RenderComponent');
      if (!position || !render) {
        return null;
      }

      const base = this.axialToPixel(position.q, position.r, this.config.hexSize);
      const offsetX = Number.isFinite(render._spreadOffsetX) ? render._spreadOffsetX : 0;
      const offsetY = Number.isFinite(render._spreadOffsetY) ? render._spreadOffsetY : 0;

      return {
        x: base.x + offsetX,
        y: base.y + offsetY,
      };
    },

    /**
     * Pick the exact rendered entity under the pointer within a crowded hex.
     * @param {number} q
     * @param {number} r
     * @param {PIXI.FederatedPointerEvent|null} pointerEvent
     * @param {Array<Entity>} entitiesAtPos
     * @returns {Entity|null}
     */
    pickEntityAtHexFromPointer: function (q, r, pointerEvent, entitiesAtPos = []) {
      if (!Array.isArray(entitiesAtPos) || !entitiesAtPos.length) {
        return null;
      }

      if (!pointerEvent?.data || !this.objectContainer) {
        return entitiesAtPos[0] || null;
      }

      const localPoint = pointerEvent.data.getLocalPosition(this.objectContainer);
      let bestMatch = null;

      entitiesAtPos.forEach((entity) => {
        const render = entity.getComponent('RenderComponent');
        const center = this.getRenderedEntityCenter(entity);
        if (!render || !center) {
          return;
        }

        const dx = localPoint.x - center.x;
        const dy = localPoint.y - center.y;
        const distance = Math.sqrt((dx * dx) + (dy * dy));
        const spriteWidth = Number(render?.sprite?.width || 0);
        const spriteHeight = Number(render?.sprite?.height || 0);
        const hitRadius = Math.max(
          this.config.hexSize * 0.28,
          spriteWidth * 0.35,
          spriteHeight * 0.35
        );

        if (distance > hitRadius) {
          return;
        }

        if (!bestMatch || distance < bestMatch.distance) {
          bestMatch = { entity, distance };
        }
      });

      return bestMatch?.entity || entitiesAtPos[0] || null;
    },

    /**
     * Build view models for the Selected Hex Contents panel.
     * @param {number} q
     * @param {number} r
     * @returns {Array<Object>}
     */
    getHexOccupantEntries: function (q, r) {
      const selectedEntity = this.stateManager.get('selectedEntity');
      return this.getLiveEntitiesAtHex(q, r).map((entity) => {
        const identity = entity.getComponent('IdentityComponent');
        const combat = entity.getComponent('CombatComponent');
        return {
          entityId: entity.id,
          name: identity?.name || `Entity ${entity.id}`,
          typeLabel: identity?.entityType || 'entity',
          teamLabel: combat?.team || '',
          canSelect: entity.hasComponent('MovementComponent'),
          isSelected: selectedEntity?.id === entity.id,
        };
      });
    },

    /**
     * Refresh the Selected Hex Contents panel for an exact hex.
     * @param {number} q
     * @param {number} r
     */
    refreshSelectedHexContents: function (q, r) {
      if (!this.uiManager) {
        return;
      }

      this.uiManager.updateSelectedHex(q, r);
      const occupants = this.getHexOccupantEntries(q, r);
      this.uiManager.updateSelectedHexContents(occupants, q, r, (entityId, mode) => {
        const entity = this.entityManager?.getEntity(entityId);
        if (!entity) {
          return;
        }

        if (mode === 'select' && entity.hasComponent('MovementComponent')) {
          this.selectEntity(entity);
          return;
        }

        this.uiManager.showEntityInfo(entity);
        if (this.uiManager.elements.actionInstruction) {
          const identity = entity.getComponent('IdentityComponent');
          this.uiManager.elements.actionInstruction.textContent = `Inspecting ${identity?.name || 'entity'} in hex (${q}, ${r}).`;
        }
      });

      if (occupants.length > 1 && this.uiManager.elements.actionInstruction) {
        this.uiManager.elements.actionInstruction.textContent = `Multiple entities occupy hex (${q}, ${r}). Use Selected Hex Contents to inspect or select the exact one you want.`;
      }
    },
    
    /**
     * Create a game entity using ECS architecture.
     * @param {number} q - Hex Q coordinate
     * @param {number} r - Hex R coordinate
     * @param {string} entityType - Entity type from EntityType enum
     * @param {string} name - Entity name
     * @param {string} spriteKey - Optional sprite key
     * @returns {Entity} Created entity
     */
    createEntityObject: function (q, r, entityType, name, spriteKey = null, options = {}) {
      // Create new entity
      const entity = this.entityManager.createEntity();
      
      //Add core components
      entity.addComponent('PositionComponent', new PositionComponent(q, r));
      entity.addComponent('IdentityComponent', new IdentityComponent(name, entityType));
      const renderComp = new RenderComponent(spriteKey);
      const explicitScale = Number(options.scale);
      renderComp.orientation = String(options.orientation || 'n').toLowerCase();
      renderComp.scale = Number.isFinite(explicitScale)
        ? explicitScale
        : (entityType === EntityType.ITEM ? 0.4 : 1.0);
      renderComp.zIndex = this.getEntityRenderZIndex(entityType, q, r);
      if (options.objectCategory) {
        renderComp.objectCategory = options.objectCategory;
      }
      if (options.objectColor) {
        renderComp.objectColor = options.objectColor;
      }
      entity.addComponent('RenderComponent', renderComp);
      
      // Add components based on entity type
      if (entityType === EntityType.CREATURE || entityType === EntityType.PLAYER_CHARACTER || entityType === EntityType.NPC) {
        const statsConfig = options.stats || {};
        const movementSpeed = options.movementSpeed ?? statsConfig.speed ?? 30;
        const actionsPerTurn = options.actionsPerTurn ?? 3;

        // Add stats
        const stats = new StatsComponent({ 
          speed: movementSpeed,
          maxHp: statsConfig.maxHp ?? 20,
          currentHp: statsConfig.currentHp ?? statsConfig.maxHp ?? 20,
          ac: statsConfig.ac ?? 10,
          perception: statsConfig.perception ?? 0
        });
        entity.addComponent('StatsComponent', stats);
        
        // Add movement
        const movement = new MovementComponent(movementSpeed);
        entity.addComponent('MovementComponent', movement);
        
        // Add actions (3-action economy)
        const actions = new ActionsComponent(actionsPerTurn);
        entity.addComponent('ActionsComponent', actions);
        
        // Add combat
        const team = this.resolveTeamPreference(options.team, entityType);
        const combat = new CombatComponent({ 
          team: team,
          initiativeBonus: statsConfig.initiative_bonus ?? options.initiativeBonus ?? 0,
          attackBonus: statsConfig.attack_bonus ?? 0
        });
        entity.addComponent('CombatComponent', combat);
      } else if (entityType === EntityType.ITEM || entityType === EntityType.OBSTACLE) {
        // Items/furniture should be targetable but not join initiative.
        const statsConfig = options.stats || {};
        const stats = new StatsComponent({
          speed: 0,
          maxHp: statsConfig.maxHp ?? 10,
          currentHp: statsConfig.currentHp ?? statsConfig.maxHp ?? 10,
          ac: statsConfig.ac ?? 10,
          perception: statsConfig.perception ?? 0
        });
        entity.addComponent('StatsComponent', stats);
      }
      
      console.log(`Created entity "${name}" (${entityType}) at (${q}, ${r})`);
      return entity;
    },

    /**
     * Compute render ordering so multiple entities can share one hex.
     * Higher values render later/on top.
     *
     * @param {string} entityType - Entity type from EntityType enum
     * @param {number} q - Hex q coordinate
     * @param {number} r - Hex r coordinate
     * @returns {number}
     */
    getEntityRenderZIndex: function (entityType, q, r) {
      const depthBias = ((Number.isFinite(r) ? r : 0) * 100) + (Number.isFinite(q) ? q : 0);
      switch (entityType) {
        case EntityType.OBSTACLE:
          return 1000 + depthBias;
        case EntityType.ITEM:
          return 2000 + depthBias;
        case EntityType.CREATURE:
        case EntityType.NPC:
          return 3000 + depthBias;
        case EntityType.PLAYER_CHARACTER:
          return 4000 + depthBias;
        default:
          return 2500 + depthBias;
      }
    },

    /**
     * Resolve team preference to CombatComponent team value.
     */
    resolveTeamPreference: function (teamPreference, entityType) {
      const normalized = teamPreference ? String(teamPreference).toLowerCase() : null;
      if (normalized === 'player') {
        return Team.PLAYER;
      }
      if (normalized === 'ally') {
        return Team.ALLY;
      }
      if (normalized === 'neutral') {
        return Team.NEUTRAL;
      }
      if (normalized === 'enemy') {
        return Team.ENEMY;
      }

      return entityType === EntityType.PLAYER_CHARACTER ? Team.PLAYER : Team.ENEMY;
    },
    
    /**
     * Select an entity for movement.
     * @param {Entity} entity - Entity to select
     */
    selectEntity: function (entity) {
      // Deselect previous entity
      const previousEntity = this.stateManager.get('selectedEntity');
      if (previousEntity) {
        this.deselectEntity();
      }
      
      this.stateManager.set('selectedEntity', entity);
      // Default to attack mode when a new player entity is selected
      this.stateManager.set('actionMode', 'attack');
      
      // Check if entity can move
      const movement = entity.getComponent('MovementComponent');
      if (!movement) {
        console.warn('Entity has no MovementComponent — selection continues without movement range');
      } else {
        // Calculate and show movement range
        this.showMovementRange(entity);
      }
      
      // Update UI
      const identity = entity.getComponent('IdentityComponent');
      const name = identity ? identity.name : `Entity ${entity.id}`;
      console.log(`Selected entity: ${name}`);
      
      // Show entity info panel via UIManager
      this.uiManager.showEntityInfo(entity);

      const actions = entity.getComponent('ActionsComponent');
      const combat = entity.getComponent('CombatComponent');
      const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
      this.uiManager.updateActionMode('attack', {
        canAct: actions ? actions.actionsRemaining > 0 : false,
        canInteract: actions ? actions.actionsRemaining > 0 : false,
        moveLeft: movement ? movement.movementRemaining : 0,
        isPlayersTurn
      });
      this.refreshFogOfWar(entity);
      this.syncTokenBadgeState();
    },
    
    /**
     * Deselect currently selected entity.
     */
    deselectEntity: function () {
      const selectedEntity = this.stateManager.get('selectedEntity');
      if (!selectedEntity) {
        return;
      }
      
      // Remove tint from sprite (no-op now — tint is never set, but keep for safety)
      const render = selectedEntity.getComponent('RenderComponent');
      if (render && render.sprite) {
        render.sprite.tint = 0xffffff; // Reset to white
      }
      
      this.stateManager.set('selectedEntity', null);
      this.hideMovementRange();
      this.hideAttackTargets();
      this.hideFogOfWar();
      
      // Hide entity info panel
      this.uiManager.hideEntityInfo();
      
      this.syncTokenBadgeState();
      
      console.log('Entity deselected');
    },
    
    /**
     * Sync per-token badge state flags onto all RenderComponents.
     * Sets _isSelected, _isCurrentTurn, _conditions, and _isInteractable on each entity's
     * RenderComponent so RenderSystem can draw rings/badges without coupling to stateManager.
     *
     * Interactable types: item, npc, obstacle.
     */
    syncTokenBadgeState: function () {
      const selectedEntity = this.stateManager.get('selectedEntity');
      const selectedId = selectedEntity ? selectedEntity.id : null;
      const currentTurnEntity = this.turnManagementSystem
        ? this.turnManagementSystem.getCurrentTurnEntity()
        : null;
      const currentTurnId = currentTurnEntity ? currentTurnEntity.id : null;

      const interactableTypes = new Set(['item', 'npc', 'obstacle']);

      const entities = this.entityManager.getAllEntities();
      for (const entity of entities) {
        const render = entity.getComponent('RenderComponent');
        if (!render) {
          continue;
        }

        render._isSelected = entity.id === selectedId;
        render._isCurrentTurn = entity.id === currentTurnId;

        // Conditions: default empty; will be populated from server hydration in future
        if (!Array.isArray(render._conditions)) {
          render._conditions = [];
        }

        const identity = entity.getComponent('IdentityComponent');
        render._isInteractable = identity
          ? interactableTypes.has(identity.type)
          : false;
      }
    },

    /**
     * Show movement range overlay for entity.
     * @param {Entity} entity - Entity to show range for
     */
    showMovementRange: function (entity) {
      // Clear existing overlay
      this.hideMovementRange();
      
      // Calculate movement range
      const movementRange = this.movementSystem.calculateMovementRange(entity);
      this.stateManager.set('movementRange', movementRange);

      const movement = entity.getComponent('MovementComponent');
      console.info('Range: movement range calculated', {
        entityId: entity.id,
        movementRemaining: movement?.movementRemaining,
        reachable: movementRange.size
      });
      
      // Create overlay graphics
      const movementRangeOverlay = new PIXI.Graphics();
      
      // Draw reachable hexes
      movementRange.forEach(hexKey => {
        const [q, r] = hexKey.split('_').map(Number);
        const pos = this.axialToPixel(q, r, this.config.hexSize);
        
        movementRangeOverlay.beginFill(0x3b82f6, 0.2); // Blue with transparency
        movementRangeOverlay.lineStyle(2, 0x60a5fa, 0.5);
        
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = pos.x + this.config.hexSize * Math.cos(angle);
          const y = pos.y + this.config.hexSize * Math.sin(angle);
          
          if (i === 0) {
            movementRangeOverlay.moveTo(x, y);
          } else {
            movementRangeOverlay.lineTo(x, y);
          }
        }
        movementRangeOverlay.closePath();
        movementRangeOverlay.endFill();
      });
      
      // Ensure the overlay never intercepts clicks.
      movementRangeOverlay.interactive = false;
      movementRangeOverlay.eventMode = 'none';
      movementRangeOverlay.zIndex = 9000;

      this.uiContainer.addChild(movementRangeOverlay);
      this.stateManager.set('movementRangeOverlay', movementRangeOverlay);
    },
    
    /**
     * Hide movement range overlay.
     */
    hideMovementRange: function () {
      const movementRangeOverlay = this.stateManager.get('movementRangeOverlay');
      if (movementRangeOverlay) {
        this.uiContainer.removeChild(movementRangeOverlay);
        movementRangeOverlay.destroy();
        this.stateManager.set('movementRangeOverlay', null);
      }
      this.stateManager.set('movementRange', null);
    },

    /**
     * Show hostile targets for attack mode.
     * @param {Entity} actor
     */
    showAttackTargets: function (actor) {
      this.hideAttackTargets();
      const targets = this.getHostileTargets(actor);
      const overlay = new PIXI.Container();
      overlay.zIndex = 9001;

      targets.forEach(({ target }) => {
        const posComp = target.getComponent('PositionComponent');
        if (!posComp) return;
        const pos = this.axialToPixel(posComp.q, posComp.r, this.config.hexSize);
        const radius = this.config.hexSize * 0.9;

        const ring = new PIXI.Graphics();
        ring.beginFill(0xef4444, 0.15);
        ring.lineStyle(2, 0xf97316, 0.9);
        ring.drawCircle(0, 0, radius * 0.6);
        ring.endFill();
        ring.x = pos.x;
        ring.y = pos.y;
        ring.targetId = target.id;
        ring.eventMode = 'static';
        ring.interactive = true;
        ring.cursor = 'pointer';

        ring.on('pointertap', () => {
          const currentActionMode = this.stateManager.get('actionMode');
          const attacker = this.stateManager.get('selectedEntity') || actor;
          if (currentActionMode !== 'attack') {
            console.warn('Tap ignored: not in attack mode');
            return;
          }
          const targetEntity = this.entityManager.getEntity(ring.targetId);
          const canAttackCheck = targetEntity ? this.combatSystem.canAttack(attacker, targetEntity) : { canAttack: false, reason: 'Target missing' };
          console.info('Overlay attack tap', { attackerId: attacker?.id, targetId: ring.targetId, check: canAttackCheck });
          if (!canAttackCheck.canAttack) {
            return;
          }
          this.performAttack(attacker, targetEntity);
        });

        overlay.addChild(ring);
      });

      // Allow pointer events only on target rings so movement clicks pass through elsewhere.
      overlay.eventMode = 'passive';

      this.uiContainer.addChild(overlay);
      this.stateManager.set('attackTargetsOverlay', overlay);

      console.info('Range: attack targets highlighted', {
        actorId: actor.id,
        targets: targets.map(({ target }) => target.id)
      });
    },

    /**
     * Hide attack target overlay.
     */
    hideAttackTargets: function () {
      const overlay = this.stateManager.get('attackTargetsOverlay');
      if (overlay) {
        this.uiContainer.removeChild(overlay);
        overlay.destroy({ children: true });
        this.stateManager.set('attackTargetsOverlay', null);
      }
    },

    /**
     * Refresh fog-of-war overlay for the currently selected or active player actor.
     * @param {Entity|null} actorOverride - Optional explicit actor
     */
    refreshFogOfWar: function (actorOverride = null) {
      const showFog = this.stateManager.get('showFog');
      if (!showFog) {
        this.hideFogOfWar();
        return;
      }

      const selected = actorOverride || this.stateManager.get('selectedEntity');
      const selectedCombat = selected?.getComponent?.('CombatComponent');
      const selectedIsPlayer = selectedCombat?.isPlayerTeam ? selectedCombat.isPlayerTeam() : (selectedCombat?.team === Team.PLAYER || selectedCombat?.team === 'player');

      let actor = selectedIsPlayer ? selected : null;
      if (!actor && this.turnManagementSystem?.getCurrentTurnEntity) {
        const current = this.turnManagementSystem.getCurrentTurnEntity();
        const currentCombat = current?.getComponent?.('CombatComponent');
        const currentIsPlayer = currentCombat?.isPlayerTeam ? currentCombat.isPlayerTeam() : (currentCombat?.team === Team.PLAYER || currentCombat?.team === 'player');
        if (currentIsPlayer) {
          actor = current;
        }
      }

      if (!actor) {
        this.hideFogOfWar();
        return;
      }

      this.renderFogOfWarForEntity(actor);
    },

    /**
     * Hide and destroy fog overlay.
     */
    hideFogOfWar: function () {
      const fogOverlay = this.stateManager.get('fogOverlay');
      if (fogOverlay) {
        this.uiContainer.removeChild(fogOverlay);
        fogOverlay.destroy();
        this.stateManager.set('fogOverlay', null);
      }
      this.stateManager.set('visibleHexes', null);
    },

    /**
     * Return default/derived vision radius for an actor.
     * @param {Entity} actor
     * @returns {number}
     */
    getVisionRangeForEntity: function (actor) {
      const stats = actor?.getComponent?.('StatsComponent');
      const perception = Number(stats?.perception ?? 0);
      const derived = this.config.defaultVisionRange + Math.max(-2, Math.min(2, Math.floor(perception / 4)));
      return Math.max(4, Math.min(12, derived));
    },

    /**
     * Render fog overlay by darkening non-visible hexes.
     * @param {Entity} actor
     */
    renderFogOfWarForEntity: function (actor) {
      this.hideFogOfWar();

      const visibleHexes = this.getVisibleHexSet(actor);
      this.stateManager.set('visibleHexes', visibleHexes);

      const fogOverlay = new PIXI.Graphics();
      fogOverlay.zIndex = 8500;
      fogOverlay.interactive = false;
      fogOverlay.eventMode = 'none';

      this.hexContainer.children.forEach((hex) => {
        const data = hex?.hexData;
        if (!data) {
          return;
        }

        const key = `${data.q}_${data.r}`;
        if (visibleHexes.has(key)) {
          return;
        }

        const pos = this.axialToPixel(data.q, data.r, this.config.hexSize);
        fogOverlay.beginFill(0x020617, 0.72);
        fogOverlay.lineStyle(0, 0x000000, 0);
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = pos.x + this.config.hexSize * Math.cos(angle);
          const y = pos.y + this.config.hexSize * Math.sin(angle);
          if (i === 0) {
            fogOverlay.moveTo(x, y);
          } else {
            fogOverlay.lineTo(x, y);
          }
        }
        fogOverlay.closePath();
        fogOverlay.endFill();
      });

      this.uiContainer.addChild(fogOverlay);
      this.stateManager.set('fogOverlay', fogOverlay);
    },

    /**
     * Compute visible hex set based on range + line of sight.
     * @param {Entity} actor
     * @returns {Set<string>}
     */
    getVisibleHexSet: function (actor) {
      const visible = new Set();
      const actorPos = actor?.getComponent?.('PositionComponent');
      if (!actorPos) {
        return visible;
      }

      const range = this.getVisionRangeForEntity(actor);
      this.hexContainer.children.forEach((hex) => {
        const data = hex?.hexData;
        if (!data) {
          return;
        }

        const distance = this.movementSystem?.hexDistance
          ? this.movementSystem.hexDistance(actorPos.q, actorPos.r, data.q, data.r)
          : Math.max(Math.abs(actorPos.q - data.q), Math.abs(actorPos.r - data.r), Math.abs((actorPos.q + actorPos.r) - (data.q + data.r)));
        if (distance > range) {
          return;
        }

        if (this.hasLineOfSight(actorPos.q, actorPos.r, data.q, data.r)) {
          visible.add(`${data.q}_${data.r}`);
        }
      });

      visible.add(`${actorPos.q}_${actorPos.r}`);
      return visible;
    },

    /**
     * Determine line of sight using axial interpolation and obstacle checks.
     * @param {number} fromQ
     * @param {number} fromR
     * @param {number} toQ
     * @param {number} toR
     * @returns {boolean}
     */
    hasLineOfSight: function (fromQ, fromR, toQ, toR) {
      if (fromQ === toQ && fromR === toR) {
        return true;
      }

      const line = this.getAxialLine(fromQ, fromR, toQ, toR);
      for (let i = 1; i < line.length - 1; i++) {
        const { q, r } = line[i];
        const obstacle = this.getObstacleMobilityAtHex(q, r);
        if (obstacle && !obstacle.passable) {
          return false;
        }
      }

      return true;
    },

    /**
     * Build axial line coordinates from origin to target.
     * @param {number} fromQ
     * @param {number} fromR
     * @param {number} toQ
     * @param {number} toR
     * @returns {Array<{q:number,r:number}>}
     */
    getAxialLine: function (fromQ, fromR, toQ, toR) {
      const toCube = (q, r) => ({ x: q, z: r, y: -q - r });
      const fromCube = toCube(fromQ, fromR);
      const targetCube = toCube(toQ, toR);
      const distance = this.movementSystem?.hexDistance
        ? this.movementSystem.hexDistance(fromQ, fromR, toQ, toR)
        : Math.max(Math.abs(fromQ - toQ), Math.abs(fromR - toR), Math.abs((fromQ + fromR) - (toQ + toR)));

      const points = [];
      for (let step = 0; step <= distance; step++) {
        const t = distance === 0 ? 0 : step / distance;
        const x = fromCube.x + (targetCube.x - fromCube.x) * t;
        const y = fromCube.y + (targetCube.y - fromCube.y) * t;
        const z = fromCube.z + (targetCube.z - fromCube.z) * t;

        let rx = Math.round(x);
        let ry = Math.round(y);
        let rz = Math.round(z);
        const dx = Math.abs(rx - x);
        const dy = Math.abs(ry - y);
        const dz = Math.abs(rz - z);

        if (dx > dy && dx > dz) {
          rx = -ry - rz;
        } else if (dy > dz) {
          ry = -rx - rz;
        } else {
          rz = -rx - ry;
        }

        points.push({ q: rx, r: rz });
      }

      return points;
    },

    /**
     * Return 6 neighboring axial coordinates for a hex.
     * @param {number} q
     * @param {number} r
     * @returns {Array<{q:number,r:number}>}
     */
    getAdjacentHexes: function (q, r) {
      return [
        { q: q + 1, r },
        { q: q + 1, r: r - 1 },
        { q, r: r - 1 },
        { q: q - 1, r },
        { q: q - 1, r: r + 1 },
        { q, r: r + 1 }
      ];
    },

    /**
     * Find payload-backed obstacle record at hex.
     * @param {number} q
     * @param {number} r
     * @returns {Object|null}
     */
    findObstaclePayloadAtHex: function (q, r) {
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      return entities.find((entity) => {
        if (entity?.entity_type !== 'obstacle') {
          return false;
        }
        const placement = entity?.placement;
        return placement && placement.room_id === this.activeRoomId && Number(placement?.hex?.q) === q && Number(placement?.hex?.r) === r;
      }) || null;
    },

    /**
     * Find ECS obstacle entity at hex.
     * @param {number} q
     * @param {number} r
     * @returns {Entity|null}
     */
    findObstacleEntityAtHex: function (q, r) {
      if (!this.entityManager) {
        return null;
      }

      const entities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
      for (const entity of entities) {
        const pos = entity.getComponent('PositionComponent');
        const identity = entity.getComponent('IdentityComponent');
        if (pos?.q === q && pos?.r === r && identity?.entityType === EntityType.OBSTACLE) {
          return entity;
        }
      }

      return null;
    },

    /**
     * Find room connection touching this hex in the active room.
     * @param {number} q
     * @param {number} r
     * @returns {Object|null}
     */
    findConnectionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      return connections.find((connection) => {
        const fromMatch = connection?.from_room === this.activeRoomId &&
          Number(connection?.from_hex?.q) === q &&
          Number(connection?.from_hex?.r) === r;
        const toMatch = connection?.to_room === this.activeRoomId &&
          Number(connection?.to_hex?.q) === q &&
          Number(connection?.to_hex?.r) === r;
        return fromMatch || toMatch;
      }) || null;
    },

    /**
     * Send a generic non-attack action to server combat API and hydrate state.
     * @param {Object} payload
     * @returns {Promise<boolean>}
     */
    performCombatAction: async function (payload = {}) {
      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('Combat action skipped; no active encounter id.', payload);
        return null;
      }

      try {
        // Always use mapId from stateManager (captured from startCombat response).
        const mapId = this.stateManager.get('mapId');
        const serverState = await combatApi.performAction({
          encounterId,
          ...(mapId ? { mapId } : {}),
          ...payload
        });

        if (!serverState) {
          console.error('Combat action returned no state; keeping current client view.');
          this.notifyServerUnavailable();
          return null;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }

        return serverState;
      } catch (err) {
        console.error('Combat action via API failed; client will not fall back.', err);
        this.notifyServerUnavailable();
        return null;
      }
    },

    /**
     * Apply backend-authoritative world delta returned by combat action API.
     *
     * [THIN-CLIENT: server-authoritative] — reconciles server-originated world state
     * changes (open_passage, open_door, move_object) into the local dungeonData cache.
     * All mutations originate from the server; this method is presentation cache sync only.
     *
     * @param {Object|null} worldDelta
     */
    applyWorldDelta: function (worldDelta) {
      if (!worldDelta || typeof worldDelta !== 'object') {
        return;
      }

      const type = String(worldDelta.type || '');
      const roomId = String(worldDelta.room_id || this.activeRoomId || '');
      const targetHex = worldDelta.target_hex || {};
      const destinationHex = worldDelta.destination_hex || {};

      if (type === 'open_passage') {
        const connectionId = worldDelta.connection_id;
        const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
        connections.forEach((connection) => {
          if (connectionId && connection.connection_id !== connectionId) {
            return;
          }

          const fromMatch = connection?.from_room === roomId
            && Number(connection?.from_hex?.q) === Number(targetHex.q)
            && Number(connection?.from_hex?.r) === Number(targetHex.r);
          const toMatch = connection?.to_room === roomId
            && Number(connection?.to_hex?.q) === Number(targetHex.q)
            && Number(connection?.to_hex?.r) === Number(targetHex.r);

          if (!connectionId && !fromMatch && !toMatch) {
            return;
          }

          connection.is_passable = true;
          connection.is_discovered = true;
        });
      }

      if (type === 'open_door') {
        const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
        entities.forEach((entity) => {
          if (entity?.entity_type !== 'obstacle') {
            return;
          }

          const placement = entity?.placement;
          if (!placement || placement.room_id !== roomId) {
            return;
          }

          if (Number(placement?.hex?.q) !== Number(targetHex.q) || Number(placement?.hex?.r) !== Number(targetHex.r)) {
            return;
          }

          entity.state = entity.state || {};
          entity.state.metadata = entity.state.metadata || {};
          entity.state.metadata.passable = true;
        });
      }

      if (type === 'move_object') {
        const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
        entities.forEach((entity) => {
          if (entity?.entity_type !== 'obstacle') {
            return;
          }

          const placement = entity?.placement;
          if (!placement || placement.room_id !== roomId) {
            return;
          }

          if (Number(placement?.hex?.q) !== Number(targetHex.q) || Number(placement?.hex?.r) !== Number(targetHex.r)) {
            return;
          }

          placement.hex.q = Number(destinationHex.q);
          placement.hex.r = Number(destinationHex.r);
        });

        // Move matching ECS obstacle sprite/entity as well.
        const ecsObstacle = this.findObstacleEntityAtHex(Number(targetHex.q), Number(targetHex.r));
        if (ecsObstacle) {
          const pos = ecsObstacle.getComponent('PositionComponent');
          if (pos) {
            pos.q = Number(destinationHex.q);
            pos.r = Number(destinationHex.r);
          }
        }
      }

      this.paintActiveRoom();
      this.refreshFogOfWar();
    },

    /**
     * Perform interact action at an adjacent hex (doors, movable obstacles, blocked connections, quest items, NPCs).
     * @param {Entity} actor
     * @param {number} targetQ
     * @param {number} targetR
     * @param {Entity} [targetEntity] - Optional ECS entity at target hex.
     * @returns {boolean}
     */
    performInteractAtHex: function (actor, targetQ, targetR, targetEntity) {
      const actorPos = actor?.getComponent?.('PositionComponent');
      const actorActions = actor?.getComponent?.('ActionsComponent');
      if (!actorPos || !actorActions) {
        return false;
      }

      const combatActive = this.stateManager.get('combatActive');
      if (combatActive && this.turnManagementSystem && !this.turnManagementSystem.isEntityTurn(actor)) {
        return false;
      }

      const distance = this.movementSystem?.hexDistance
        ? this.movementSystem.hexDistance(actorPos.q, actorPos.r, targetQ, targetR)
        : Math.max(Math.abs(actorPos.q - targetQ), Math.abs(actorPos.r - targetR), Math.abs((actorPos.q + actorPos.r) - (targetQ + targetR)));
      if (distance > 1) {
        return false;
      }

      // --- Quest item collection ---
      if (targetEntity) {
        const targetState = targetEntity.dcStatePayload || {};
        const metadata = targetState.metadata || {};
        const identity = targetEntity.getComponent?.('IdentityComponent');
        const entityType = identity?.entityType;

        // Collect quest items.
        if (metadata.collectible && metadata.quest_id && metadata.objective_id) {
          this.collectQuestItem(actor, targetEntity, metadata);
          return true;
        }

        // Interact with NPC for quest turn-in.
        if (entityType === EntityType.NPC || entityType === 'npc') {
          const npcRef = targetEntity.dcEntityRef;
          if (npcRef && this.questData) {
            const turnInResult = this.tryQuestTurnIn(actor, npcRef, targetEntity);
            if (turnInResult) {
              return true;
            }
          }
          // Even if no quest turn-in, still allow NPC interaction (talk prompt).
          const npcName = identity?.name || 'NPC';
          const npcEntityRef = npcRef || npcName.toLowerCase().replace(/\s+/g, '_');
          // Open a whisper channel to this NPC.
          if (this.uiManager && this.uiManager.openChannel) {
            this.uiManager.openChannel(npcEntityRef, npcName, 'whisper');
          } else {
            this.uiManager.appendChatLine(npcName, 'Greetings, adventurer! What can I do for you?', 'npc');
          }
          return true;
        }
      }

      const connection = this.findConnectionAtHex(targetQ, targetR);
      if (connection && connection.is_passable === false) {
        this.performCombatAction({
          actorId: actor.id,
          actionType: 'interact',
          interactionType: 'open_passage',
          actionCost: 1,
          targetId: connection.connection_id,
          targetHex: { q: targetQ, r: targetR }
        }).then((serverState) => {
          if (!serverState) {
            return;
          }

          this.applyWorldDelta(serverState.world_delta || null);
          console.info('Interaction: opened room connection', { connectionId: connection.connection_id, q: targetQ, r: targetR });
        });
        return true;
      }

      const obstacleProfile = this.getObstacleMobilityAtHex(targetQ, targetR);
      if (!obstacleProfile) {
        return false;
      }

      if (obstacleProfile.movable) {
        const pushDeltaQ = targetQ - actorPos.q;
        const pushDeltaR = targetR - actorPos.r;
        const preferredDestination = { q: targetQ + pushDeltaQ, r: targetR + pushDeltaR };
        const candidates = [preferredDestination, ...this.getAdjacentHexes(targetQ, targetR)];

        const destination = candidates.find((candidate) => {
          if (!this.isHexInActiveRoom(candidate.q, candidate.r)) {
            return false;
          }
          if (this.getObstacleMobilityAtHex(candidate.q, candidate.r)) {
            return false;
          }

          const occupied = this.entityManager?.getEntitiesWith('PositionComponent', 'IdentityComponent').some((entity) => {
            const pos = entity.getComponent('PositionComponent');
            return pos?.q === candidate.q && pos?.r === candidate.r;
          });

          return !occupied;
        });

        if (!destination) {
          return false;
        }

        this.performCombatAction({
          actorId: actor.id,
          actionType: 'interact',
          interactionType: 'move_object',
          actionCost: 1,
          targetId: this.getObjectIdAtHex(targetQ, targetR) || null,
          targetHex: { q: targetQ, r: targetR },
          destinationHex: destination
        }).then((serverState) => {
          if (!serverState) {
            return;
          }

          this.applyWorldDelta(serverState.world_delta || null);

          console.info('Interaction: moved obstacle', {
            from: { q: targetQ, r: targetR },
            to: destination
          });
        });
        return true;
      }

      if (!obstacleProfile.passable) {
        const label = (this.getObjectLabelAtHex(targetQ, targetR) || '').toLowerCase();
        const isDoorLike = /(door|gate|hatch|portal)/.test(label);

        if (isDoorLike) {
          this.performCombatAction({
            actorId: actor.id,
            actionType: 'interact',
            interactionType: 'open_door',
            actionCost: 1,
            targetId: this.getObjectIdAtHex(targetQ, targetR) || null,
            targetHex: { q: targetQ, r: targetR },
            label
          }).then((serverState) => {
            if (!serverState) {
              return;
            }

            this.applyWorldDelta(serverState.world_delta || null);

            console.info('Interaction: opened door-like obstacle', { q: targetQ, r: targetR, label });
          });
          return true;
        }
      }

      return false;
    },
    
    /**
     * Start combat encounter.
     */
    serializeCombatantsForApi: function () {
      if (!this.entityManager) {
        return [];
      }

      const entities = this.entityManager.getEntitiesWith('IdentityComponent', 'CombatComponent');
      return entities.map((entity) => {
        const identity = entity.getComponent('IdentityComponent');
        const combat = entity.getComponent('CombatComponent');
        const stats = entity.getComponent('StatsComponent');
        const position = entity.getComponent('PositionComponent');

        return {
          entityId: entity.id,
          entityRef: entity.dcEntityRef || entity.instanceId || null,
          characterId: entity.dcCharacterId || null,
          name: identity?.name || `Entity ${entity.id}`,
          team: combat?.team,
          initiative: combat?.getInitiative ? combat.getInitiative() : null,
          initiative_bonus: combat?.initiativeBonus,
          perception: stats?.perception,
          ac: stats?.ac,
          hp: stats?.currentHp,
          max_hp: stats?.maxHp,
          position: position ? { q: position.q, r: position.r } : null,
        };
      });
    },

    /**
     * Resolve campaign id from launch context.
     * @returns {number|null}
     */
    resolveCampaignId: function () {
      const launchCampaignId = Number(this.launchContext?.campaign_id || 0);
      return Number.isFinite(launchCampaignId) && launchCampaignId > 0 ? launchCampaignId : null;
    },

    /**
     * Resolve active room id for combat API payloads.
     * @returns {string|null}
     */
    resolveActiveRoomId: function () {
      return this.activeRoomId || this.stateManager.get('activeRoomId') || this.launchContext?.room_id || null;
    },

    /**
     * Determine whether server combat APIs should be used for this user/session.
     * @returns {boolean}
     */
    canUseServerCombatApi: function () {
      const uid = Number(this.currentUserId || 0);
      return Number.isFinite(uid) && uid > 0;
    },

    notifyServerUnavailable: function () {
      const message = 'Unable to connect to server. Please try again.';
      if (this.uiManager && typeof this.uiManager.showServerUnavailable === 'function') {
        this.uiManager.showServerUnavailable(message);
      }
    },

    startCombat: async function (options = {}) {
      console.log('Starting combat (server authoritative)...');

      if (!this.canUseServerCombatApi()) {
        console.info('Combat start skipped; authenticated user is required for server combat APIs.');
        return;
      }

      const encounterId = this.stateManager.get('encounterId');
      if (encounterId) {
        console.info('Combat start skipped; encounter already active.', { encounterId });
        return;
      }

      const campaignId = this.resolveCampaignId();
      if (!campaignId) {
        console.info('Combat start skipped; campaign context is required for server combat APIs.');
        return;
      }

      const payload = {
        campaignId,
        roomId: this.resolveActiveRoomId(),
        entities: this.serializeCombatantsForApi(),
        ...options
      };

      try {
        const serverState = await combatApi.startCombat(payload);
        if (!serverState) {
          console.error('Combat start returned no state; aborting client start.');
          this.notifyServerUnavailable();
          return;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (serverState.map_id) {
          this.stateManager.set('mapId', serverState.map_id);
          console.log('Captured map_id from startCombat:', serverState.map_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          if (this.combatSystem && typeof this.combatSystem.setServerResultRequirement === 'function') {
            this.combatSystem.setServerResultRequirement(true);
          }
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }
      } catch (err) {
        console.error('Combat start via API failed.', err);
        this.notifyServerUnavailable();
      }
    },
    
    /**
     * End current turn.
     */
    endTurn: async function () {
      console.log('Ending turn (server authoritative)...');

      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('End turn skipped; no active encounter id.');
        return;
      }

      const currentTurn = this.turnManagementSystem?.getCurrentTurn?.();
      const payload = {
        encounterId,
        participantId: currentTurn?.entityId
      };

      try {
        const serverState = await combatApi.endTurn(payload);
        if (!serverState) {
          console.error('End turn returned no state; keeping current client view.');
          this.notifyServerUnavailable();
          return;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }
      } catch (err) {
        console.error('Turn end via API failed.', err);
        this.notifyServerUnavailable();
      }
    },
    
    /**
     * End combat encounter.
     */
    endCombat: async function () {
      console.log('Ending combat (server authoritative)...');

      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('End combat skipped; no active encounter id.');
        return;
      }

      const payload = {
        encounterId
      };

      try {
        await combatApi.endCombat(payload);
      } catch (err) {
        console.error('Combat end via API failed.', err);
        this.notifyServerUnavailable();
        return;
      }

      this.turnManagementSystem.endCombat();
      this.stateManager.set('encounterId', null);
      this.stateManager.set('serverCombatMode', false);
      if (this.combatSystem && typeof this.combatSystem.setServerResultRequirement === 'function') {
        this.combatSystem.setServerResultRequirement(false);
      }
      this.deselectEntity();
    },

    /**
     * Free-action talk interface hook for AI conversation integration.
     * @param {Entity} speaker - Speaking entity
     * @param {string} message - Utterance content
     */
    performTalk: async function (speaker, message) {
      if (!speaker || !message) {
        return;
      }

      const actionAccepted = await this.performCombatAction({
        actorId: speaker.id,
        actionType: 'talk',
        actionCost: 0,
        message
      });

      if (!actionAccepted) {
        return;
      }

      const identity = speaker.getComponent('IdentityComponent');
      const combat = speaker.getComponent('CombatComponent');

      // Emit an event for downstream ai_conversation listeners.
      window.dispatchEvent(new CustomEvent('dungeoncrawler:talk', {
        detail: {
          entityId: speaker.id,
          name: identity?.name || `Entity ${speaker.id}`,
          team: combat?.team || null,
          roomId: this.activeRoomId || null,
          message: message
        }
      }));
    },

    /**
     * Perform attack action.
     * @param {Entity} attacker - Attacking entity
     * @param {Entity} target - Target entity
     */
    performAttack: async function (attacker, target) {
      const combatActive = this.stateManager.get('combatActive');
      if (combatActive && this.turnManagementSystem) {
        if (!this.turnManagementSystem.isEntityTurn(attacker)) {
          console.warn('Not your turn!', { attackerId: attacker?.id, currentTurn: this.turnManagementSystem?.getCurrentTurnEntity?.()?.id });
          return;
        }
      }

      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('Attack skipped; no active encounter id.');
        return;
      }

      const payload = {
        encounterId,
        ...(this.stateManager.get('mapId') ? { mapId: this.stateManager.get('mapId') } : {}),
        attackerId: attacker?.id,
        targetId: target?.id,
        action: 'attack'
      };

      try {
        const serverState = await combatApi.performAttack(payload);
        if (!serverState) {
          console.error('Attack returned no state; keeping current client view.');
          this.notifyServerUnavailable();
          return;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }

        const actionResult = serverState.action_result || {};
        const projectedResult = {
          result: actionResult.result || actionResult.outcome || (actionResult.hit ? AttackResult.HIT : AttackResult.MISS),
          attackRoll: actionResult.attack_roll,
          attackTotal: actionResult.attack_total,
          damage: Number(actionResult.damage || 0),
          applyDamage: false
        };

        if (this.combatSystem && attacker && target) {
          this.combatSystem.makeAttack(attacker, target, projectedResult);
        }
      } catch (err) {
        console.error('Attack via API failed; client will not fall back.', err);
        this.notifyServerUnavailable();
      }
    },

    /**
     * Get all hostile, alive targets for an entity, sorted by distance.
     * @param {Entity} actor
     * @returns {Array<{target: Entity, distance: number}>}
     */
    getHostileTargets: function (actor) {
      const actorCombat = actor.getComponent('CombatComponent');
      const actorPos = actor.getComponent('PositionComponent');
      if (!actorCombat || !actorPos) {
        return [];
      }

      const candidates = this.entityManager.getEntitiesWith('CombatComponent', 'StatsComponent', 'PositionComponent');
      const hostileTargets = [];

      candidates.forEach((candidate) => {
        if (candidate.id === actor.id) {
          return;
        }

        const targetCombat = candidate.getComponent('CombatComponent');
        const targetStats = candidate.getComponent('StatsComponent');
        const targetPos = candidate.getComponent('PositionComponent');

        if (!targetCombat || !targetPos || !targetStats?.isAlive()) {
          return;
        }

        if (!actorCombat.isHostileTo(targetCombat)) {
          return;
        }

        const distance = this.movementSystem.hexDistance(actorPos.q, actorPos.r, targetPos.q, targetPos.r);
        if (!this.hasLineOfSight(actorPos.q, actorPos.r, targetPos.q, targetPos.r)) {
          return;
        }
        hostileTargets.push({ target: candidate, distance });
      });

      hostileTargets.sort((a, b) => a.distance - b.distance);
      return hostileTargets;
    },

    
    /**
     * Callback when attack is performed.
     * @param {Object} attackData - Attack data
     */
    onAttackPerformed: function (attackData) {
      const attackerName = attackData.attacker.getComponent('IdentityComponent')?.name || 'Attacker';
      const targetName = attackData.target.getComponent('IdentityComponent')?.name || 'Target';
      
      let message = `${attackerName} attacks ${targetName}: `;
      
      if (attackData.result === AttackResult.CRITICAL_HIT) {
        message += `💥 CRITICAL HIT! `;
      } else if (attackData.result === AttackResult.HIT) {
        message += `✓ Hit! `;
      } else if (attackData.result === AttackResult.MISS) {
        message += `✗ Miss! `;
      } else if (attackData.result === AttackResult.CRITICAL_MISS) {
        message += `❌ Critical Miss! `;
      }
      
      if (attackData.damage > 0) {
        message += `${attackData.damage} damage`;
      }
      
      console.log(message);
      
      // Could add floating damage numbers or attack animations here
    },
    
    /**
     * Callback when damage is dealt.
     * @param {Object} damageData - Damage data
     */
    onDamageDealt: function (damageData) {
      const targetName = damageData.target.getComponent('IdentityComponent')?.name || 'Target';
      
      console.log(`${targetName}: ${damageData.remainingHp}/${damageData.maxHp} HP`);
      
      if (damageData.defeated) {
        console.log(`${targetName} has been defeated!`);
        
        // Update sprite to show defeated state (could add death animation)
        const render = damageData.target.getComponent('RenderComponent');
        if (render && render.sprite) {
          render.sprite.alpha = 0.5; // Make semi-transparent
        }
      }
    },

    /**
     * Select the current turn entity (player) after a server hydration so buttons work.
     */
    syncSelectedToCurrentTurn: function () {
      const current = this.turnManagementSystem?.getCurrentTurnEntity?.();
      if (current) {
        const combat = current.getComponent('CombatComponent');
        if (combat && combat.isPlayerTeam && combat.isPlayerTeam()) {
          this.selectEntity(current);
          return;
        }
      }
      // If no player current, clear selection.
      this.deselectEntity();
    },

    /**
     * Load game assets.
     */
    loadAssets: async function (assetList) {
      if (this.stateManager && this.stateManager.get('assetsLoaded')) return;
      
      console.log('Loading assets...');
      
      try {
          this.syncSelectedToCurrentTurn();
        for (const asset of assetList) {
          await PIXI.Assets.load(asset);
        }
        if (this.stateManager) {
          this.stateManager.set('assetsLoaded', true);
        }
        console.log('Assets loaded successfully');
      } catch (error) {
        console.error('Error loading assets:', error);
      }
    },

    /**
     * Setup control handlers.
     */
    setupControls: function () {
      const self = this;

      // Helper to track event listeners for cleanup
      const addTrackedListener = (element, event, handler) => {
        if (element) {
          element.addEventListener(event, handler);
          self.eventListeners.push({ element, event, handler });
        }
      };

      // Grid size selector
      const gridSizeSelect = document.getElementById('grid-size');
      addTrackedListener(gridSizeSelect, 'change', function (e) {
        const size = e.target.value;
        switch (size) {
          case 'small':
            self.config.gridWidth = 10;
            self.config.gridHeight = 10;
            break;
          case 'medium':
            self.config.gridWidth = 20;
            self.config.gridHeight = 20;
            break;
          case 'large':
            self.config.gridWidth = 40;
            self.config.gridHeight = 40;
            break;
        }
        self.generateHexGrid();
      });

      // Hex size slider
      const hexSizeSlider = document.getElementById('hex-size');
      addTrackedListener(hexSizeSlider, 'input', function (e) {
        self.config.hexSize = parseInt(e.target.value);
        const hexSizeValue = document.getElementById('hex-size-value');
        if (hexSizeValue) {
          hexSizeValue.textContent = self.config.hexSize + 'px';
        }
        self.generateHexGrid();
      });

      // Toggle coordinates
      const toggleCoords = document.getElementById('toggle-coordinates');
      addTrackedListener(toggleCoords, 'click', function () {
        const current = self.stateManager.get('showCoordinates');
        self.stateManager.set('showCoordinates', !current);
        self.generateHexGrid();
      });

      // Toggle grid lines
      const toggleGrid = document.getElementById('toggle-grid');
      addTrackedListener(toggleGrid, 'click', function () {
        const current = self.stateManager.get('showGrid');
        const newValue = !current;
        self.stateManager.set('showGrid', newValue);
      });

      // Toggle fog of war
      const toggleFog = document.getElementById('toggle-fog');
      addTrackedListener(toggleFog, 'click', function () {
        const current = self.stateManager.get('showFog');
        const next = !current;
        self.stateManager.set('showFog', next);
        this.textContent = next ? 'Hide Fog of War' : 'Show Fog of War';
      });
      if (toggleFog) {
        toggleFog.textContent = self.stateManager.get('showFog') ? 'Hide Fog of War' : 'Show Fog of War';
      }

      // Reset view
      const resetView = document.getElementById('reset-view');
      addTrackedListener(resetView, 'click', function () {
        self.setWorldScale(1);
        self.setWorldPosition(self.app.screen.width / 2, self.app.screen.height / 2);
        self.uiManager.updateZoomLevel(1);
      });

      // Deselect entity button
      const deselectBtn = document.getElementById('deselect-entity');
      addTrackedListener(deselectBtn, 'click', function () {
        self.deselectEntity();
      });
      
      // Combat controls
      const startCombatBtn = document.getElementById('start-combat');
      addTrackedListener(startCombatBtn, 'click', function () {
        self.startCombat();
      });
      
      const actionMoveBtn = document.getElementById('action-move');
      addTrackedListener(actionMoveBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          console.warn('No actor available to move');
          return;
        }

        self.stateManager.set('actionMode', 'move');
        const actions = actor.getComponent('ActionsComponent');
        const movement = actor.getComponent('MovementComponent');
        const combat = actor.getComponent('CombatComponent');
        const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');

        if (actor !== selected) {
          self.stateManager.set('selectedEntity', actor);
          self.uiManager.showEntityInfo(actor);
        }

        console.info('UI: Move button clicked', {
          actorId: actor.id,
          actionsRemaining: actions?.actionsRemaining,
          movementRemaining: movement?.movementRemaining
        });

        self.hideAttackTargets?.();
        self.showMovementRange(actor);
        self.uiManager.updateActionMode('move', {
          canAct: actions ? actions.actionsRemaining > 0 : false,
          canInteract: actions ? actions.actionsRemaining > 0 : false,
          moveLeft: movement ? movement.movementRemaining : 0,
          isPlayersTurn
        });
      });

      const actionAttackBtn = document.getElementById('action-attack');
      addTrackedListener(actionAttackBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          console.warn('No actor available to attack');
          return;
        }

        self.stateManager.set('actionMode', 'attack');
        const actions = actor.getComponent('ActionsComponent');
        const movement = actor.getComponent('MovementComponent');
        const combat = actor.getComponent('CombatComponent');
        const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');

        console.info('UI: Attack button clicked', {
          actorId: actor.id,
          actionsRemaining: actions?.actionsRemaining,
          movementRemaining: movement?.movementRemaining
        });

        self.hideMovementRange();
        self.showAttackTargets?.(actor);
        self.uiManager.updateActionMode('attack', {
          canAct: actions ? actions.actionsRemaining > 0 : false,
          canInteract: actions ? actions.actionsRemaining > 0 : false,
          moveLeft: movement ? movement.movementRemaining : 0,
          isPlayersTurn
        });
      });

      const actionInteractBtn = document.getElementById('action-interact');
      addTrackedListener(actionInteractBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          return;
        }

        self.stateManager.set('actionMode', 'interact');
        const actions = actor.getComponent('ActionsComponent');
        const movement = actor.getComponent('MovementComponent');
        const combat = actor.getComponent('CombatComponent');
        const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');

        if (actor !== selected) {
          self.stateManager.set('selectedEntity', actor);
          self.uiManager.showEntityInfo(actor);
        }

        self.hideMovementRange();
        self.hideAttackTargets();
        self.uiManager.updateActionMode('interact', {
          canAct: actions ? actions.actionsRemaining > 0 : false,
          canInteract: actions ? actions.actionsRemaining > 0 : false,
          moveLeft: movement ? movement.movementRemaining : 0,
          isPlayersTurn
        });
      });

      const actionTalkBtn = document.getElementById('action-talk');
      addTrackedListener(actionTalkBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          return;
        }

        const message = window.prompt('What does your character say?', '');
        if (!message || !message.trim()) {
          return;
        }

        self.performTalk(actor, message.trim());
      });

      const endTurnBtn = document.getElementById('end-turn');
      addTrackedListener(endTurnBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        self.endTurn();
      });
      
      const endCombatBtn = document.getElementById('end-combat');
      addTrackedListener(endCombatBtn, 'click', function () {
        self.endCombat();
      });
    },

    /**
     * Setup pan and zoom interaction.
     */
    setupInteraction: function () {
      const self = this;
      let isDragging = false;
      let dragStart = { x: 0, y: 0 };

      const addTrackedStageListener = (event, handler) => {
        this.app.stage.on(event, handler);
        this.stageListeners.push({ event, handler });
      };

      // Pan functionality
      addTrackedStageListener('pointerdown', function (e) {
        isDragging = true;
        dragStart = { x: e.data.global.x, y: e.data.global.y };
      });

      addTrackedStageListener('pointerup', function () {
        isDragging = false;
      });

      addTrackedStageListener('pointerupoutside', function () {
        isDragging = false;
      });

      addTrackedStageListener('pointermove', function (e) {
        if (isDragging) {
          const dx = e.data.global.x - dragStart.x;
          const dy = e.data.global.y - dragStart.y;

          const nextX = self.hexContainer.x + dx;
          const nextY = self.hexContainer.y + dy;
          self.setWorldPosition(nextX, nextY);
          
          dragStart = { x: e.data.global.x, y: e.data.global.y };
        }
      });

      // Zoom functionality
      const wheelHandler = function (e) {
        e.preventDefault();
        
        const delta = e.deltaY < 0 ? 1.1 : 0.9;
        const newScale = self.hexContainer.scale.x * delta;
        
        // Limit zoom using config values
        if (newScale > self.config.minZoom && newScale < self.config.maxZoom) {
          self.setWorldScale(newScale);
          
          self.uiManager.updateZoomLevel(newScale);
        }
      };
      
      this.app.view.addEventListener('wheel', wheelHandler);
      this.eventListeners.push({ element: this.app.view, event: 'wheel', handler: wheelHandler });

      const leaveHandler = function () {
        isDragging = false;
        self.clearCrowdedHexHoverState();
      };

      this.app.view.addEventListener('mouseleave', leaveHandler);
      this.eventListeners.push({ element: this.app.view, event: 'mouseleave', handler: leaveHandler });
    },

    /**
     * Find a rendered hex by axial coordinates.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {PIXI.Graphics|null}
     */
    findHexByCoords: function (q, r) {
      const matchingHex = this.hexContainer.children.find((child) => {
        if (!child.hexData) {
          return false;
        }
        return child.hexData.q === q && child.hexData.r === r;
      });

      return matchingHex || null;
    },

    /**
     * Draw a hex with provided style.
     * @param {PIXI.Graphics} hex - Hex graphic
     * @param {number} fillColor - Fill color
     * @param {number} lineWidth - Border width
     * @param {number} lineColor - Border color
     * @param {number} alpha - Fill alpha
     */
    drawHexStyle: function (hex, fillColor, lineWidth, lineColor, alpha = 1) {
      hex.clear();
      hex.beginFill(fillColor, alpha);
      hex.lineStyle(lineWidth, lineColor, 1);

      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.config.hexSize * Math.cos(angle);
        const y = this.config.hexSize * Math.sin(angle);

        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();
    },

    /**
     * Draw a hex with a repeating texture fill.
     * @param {PIXI.Graphics} hex - Hex graphic
     * @param {PIXI.Texture} texture - Texture to fill with (should be repeat-wrapped)
     * @param {number} lineWidth - Border width
     * @param {number} lineColor - Border color
     * @param {number} alpha - Fill alpha
     */
    drawHexTexture: function (hex, texture, lineWidth, lineColor, alpha = 1) {
      if (!texture) {
        this.drawHexStyle(hex, 0x2d4b36, lineWidth, lineColor, alpha);
        return;
      }

      hex.clear();

      // Scale the pattern relative to the current hex size.
      const matrix = new PIXI.Matrix();
      const scale = Math.max(0.5, Math.min(2.0, this.config.hexSize / 32));
      matrix.scale(scale, scale);

      hex.beginTextureFill({ texture, matrix, alpha });
      hex.lineStyle(lineWidth, lineColor, 1);

      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.config.hexSize * Math.cos(angle);
        const y = this.config.hexSize * Math.sin(angle);

        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();
    },

    /**
     * Return a cached procedural texture for a terrain key.
     * @param {string} key - 'floor' | 'wall' | 'wooden_floor' | 'stone_floor'
     * @returns {PIXI.Texture|null}
     */
    getTileTexture: function (key) {
      if (typeof PIXI === 'undefined') {
        return null;
      }

      if (!this.tileTextures || typeof this.tileTextures !== 'object') {
        this.tileTextures = {};
      }

      if (this.tileTextures[key]) {
        return this.tileTextures[key];
      }

      const canvas = this.generateTileCanvas(key, 64);
      if (!canvas) {
        return null;
      }

      const texture = PIXI.Texture.from(canvas);
      if (texture?.baseTexture && PIXI.WRAP_MODES) {
        texture.baseTexture.wrapMode = PIXI.WRAP_MODES.REPEAT;
      }

      this.tileTextures[key] = texture;
      return texture;
    },

    /**
     * Procedurally generate a small repeating canvas tile.
     * Uses existing palette values already present in hex styling.
     * @param {string} key - 'floor' | 'wall'
     * @param {number} size - Canvas size
     * @returns {HTMLCanvasElement|null}
     */
    generateTileCanvas: function (key, size = 64) {
      if (typeof document === 'undefined') {
        return null;
      }

      const canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        return null;
      }

      const fill = (hex, alpha = 1) => {
        const value = Number(hex) >>> 0;
        const r = (value >> 16) & 0xff;
        const g = (value >> 8) & 0xff;
        const b = value & 0xff;
        ctx.fillStyle = `rgba(${r}, ${g}, ${b}, ${alpha})`;
      };

      // Deterministic pseudo-random generator (xorshift32) for stable patterns.
      let seed = 0;
      const seedString = `${key}:${size}`;
      for (let i = 0; i < seedString.length; i++) {
        seed = ((seed << 5) - seed + seedString.charCodeAt(i)) | 0;
      }
      const rand = () => {
        seed ^= seed << 13;
        seed ^= seed >> 17;
        seed ^= seed << 5;
        return ((seed >>> 0) % 10000) / 10000;
      };

      if (key === 'floor') {
        // Base: active room floor palette.
        fill(0x2d4b36, 1);
        ctx.fillRect(0, 0, size, size);

        // Subtle plank lines.
        fill(0x4d7a5b, 0.22);
        for (let x = 0; x < size; x += 8) {
          ctx.fillRect(x + 1, 0, 1, size);
        }

        // Light noise speckles.
        fill(0x4d7a5b, 0.18);
        for (let i = 0; i < 120; i++) {
          const x = Math.floor(rand() * size);
          const y = Math.floor(rand() * size);
          ctx.fillRect(x, y, 1, 1);
        }

        return canvas;
      }

      if (key === 'wooden_floor') {
        // Warm tavern wood planks.
        fill(0x5c3d1e, 1);
        ctx.fillRect(0, 0, size, size);

        // Wood grain planks.
        fill(0x7a5325, 0.35);
        for (let x = 0; x < size; x += 10) {
          ctx.fillRect(x, 0, 1, size);
        }

        // Grain streaks.
        fill(0x8b6914, 0.18);
        for (let i = 0; i < 80; i++) {
          const x = Math.floor(rand() * size);
          const y = Math.floor(rand() * size);
          ctx.fillRect(x, y, Math.floor(rand() * 6) + 2, 1);
        }

        // Knots.
        fill(0x3e2007, 0.4);
        for (let i = 0; i < 4; i++) {
          const cx = Math.floor(rand() * size);
          const cy = Math.floor(rand() * size);
          ctx.beginPath();
          ctx.arc(cx, cy, 2, 0, Math.PI * 2);
          ctx.fill();
        }

        return canvas;
      }

      if (key === 'stone_floor') {
        // Dungeon stone tile.
        fill(0x3a3a3a, 1);
        ctx.fillRect(0, 0, size, size);

        // Stone tile grid.
        fill(0x555555, 0.4);
        const tileSize = 16;
        for (let y = 0; y < size; y += tileSize) {
          for (let x = 0; x < size; x += tileSize) {
            const w = tileSize - 2 - Math.floor(rand() * 2);
            const h = tileSize - 2 - Math.floor(rand() * 2);
            ctx.fillRect(x + 1, y + 1, w, h);
          }
        }

        // Cracks.
        fill(0x2a2a2a, 0.3);
        for (let i = 0; i < 6; i++) {
          const x = Math.floor(rand() * size);
          const y = Math.floor(rand() * size);
          ctx.fillRect(x, y, 1, Math.floor(rand() * 8) + 2);
        }

        return canvas;
      }

      if (key === 'wall') {
        // Base: outside/neutral palette already used.
        fill(0x2d3748, 1);
        ctx.fillRect(0, 0, size, size);

        // Stone blocks.
        fill(0x4a5568, 0.42);
        const blockH = 10;
        const blockW = 14;
        for (let y = 0; y < size + blockH; y += blockH) {
          const offset = (Math.floor(y / blockH) % 2) * Math.floor(blockW / 2);
          for (let x = -offset; x < size + blockW; x += blockW) {
            const w = blockW - 2 - Math.floor(rand() * 3);
            const h = blockH - 2 - Math.floor(rand() * 3);
            ctx.fillRect(x + 1, y + 1, w, h);
          }
        }

        // Mortar lines.
        fill(0x4a5568, 0.25);
        for (let i = 0; i < 10; i++) {
          const y = Math.floor(rand() * size);
          ctx.fillRect(0, y, size, 1);
        }

        return canvas;
      }

      return null;
    },

    /**
     * Resolve a terrain texture key for visuals / UI labels.
     * Uses room-level terrain data when available for scene-appropriate tiles.
     * @param {{movable: boolean, passable: boolean, isWall?: boolean}|null} obstacleProfile
     * @param {boolean} inActiveRoom
     * @returns {'floor'|'wooden_floor'|'stone_floor'|'wall'|'outside'}
     */
    resolveTerrainKey: function (obstacleProfile, inActiveRoom) {
      if (obstacleProfile?.isWall && !obstacleProfile.passable && !obstacleProfile.movable) {
        return 'wall';
      }
      if (!inActiveRoom) {
        return 'outside';
      }
      // Use room terrain type to pick a scene-appropriate floor texture.
      const room = this.getActiveRoomData();
      const terrainType = room?.terrain?.type || '';
      if (terrainType.includes('wood') || terrainType.includes('tavern') || terrainType.includes('plank')) {
        return 'wooden_floor';
      }
      if (terrainType.includes('stone') || terrainType.includes('dungeon') || terrainType.includes('cave')) {
        return 'stone_floor';
      }
      return 'floor';
    },

    /**
     * Check whether a hex coordinate belongs to the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {boolean}
     */
    isHexInActiveRoom: function (q, r) {
      const room = this.getActiveRoomData();
      if (!room || !Array.isArray(room.hexes)) {
        return false;
      }
      return room.hexes.some((roomHex) => roomHex.q === q && roomHex.r === r);
    },

    /**
     * Reset hex appearance based on active room membership.
     * @param {PIXI.Graphics} hex - Hex graphic
     */
    resetHexAppearance: function (hex) {
      if (!hex?.hexData) {
        return;
      }

      const { q, r } = hex.hexData;
      const obstacleProfile = this.getObstacleMobilityAtHex(q, r);

      if (obstacleProfile) {
        if (obstacleProfile.isWall && !obstacleProfile.passable && !obstacleProfile.movable) {
          // Fixed impassable obstacle: treat as a wall tile.
          this.drawHexTexture(hex, this.getTileTexture('wall'), 2, 0x8b3a3a, 0.95);
          return;
        }

        if (!obstacleProfile.passable && obstacleProfile.movable) {
          const inActiveRoom = this.isHexInActiveRoom(q, r);
          const terrainKey = this.resolveTerrainKey(obstacleProfile, inActiveRoom);
          const terrainTexture = this.getTileTexture(terrainKey);
          if (terrainTexture) {
            this.drawHexTexture(hex, terrainTexture, 2, 0xb7791f, 1);
          } else {
            this.drawHexStyle(hex, 0x7a5325, 2, 0xb7791f, 0.95);
          }
          return;
        }

        if (obstacleProfile.passable && obstacleProfile.movable) {
          this.drawHexStyle(hex, 0x2d5170, 2, 0x4299e1, 0.95);
          return;
        }

        const inActiveRoom = this.isHexInActiveRoom(q, r);
        const terrainKey = this.resolveTerrainKey(obstacleProfile, inActiveRoom);
        const terrainTexture = this.getTileTexture(terrainKey);
        const borderColors = {
          'wooden_floor': { fill: 0x5c3d1e, line: 0x7a5325 },
          'stone_floor': { fill: 0x3a3a3a, line: 0x555555 },
          'floor': { fill: 0x2d4b36, line: 0x4d7a5b },
          'outside': { fill: 0x2d3748, line: 0x4a5568 },
          'wall': { fill: 0x6b2f2f, line: 0x8b3a3a },
        };
        const colors = borderColors[terrainKey] || borderColors['floor'];
        if (terrainTexture) {
          this.drawHexTexture(hex, terrainTexture, 2, colors.line, 1);
        } else {
          this.drawHexStyle(hex, colors.fill, 2, colors.line, 1);
        }
        return;
      }

      if (this.isHexInActiveRoom(q, r)) {
        const terrainKey = this.resolveTerrainKey(null, true);
        const terrainTexture = this.getTileTexture(terrainKey);
        const borderColors = {
          'wooden_floor': { fill: 0x5c3d1e, line: 0x7a5325 },
          'stone_floor': { fill: 0x3a3a3a, line: 0x555555 },
          'floor': { fill: 0x2d4b36, line: 0x4d7a5b },
        };
        const colors = borderColors[terrainKey] || borderColors['floor'];
        if (terrainTexture) {
          this.drawHexTexture(hex, terrainTexture, 2, colors.line, 1);
        } else {
          this.drawHexStyle(hex, colors.fill, 2, colors.line, 1);
        }
      } else {
        this.drawHexStyle(hex, 0x2d3748, 1, 0x4a5568, 1);
      }
    },

    /**
     * Apply selected-hex visuals and state.
     * @param {PIXI.Graphics} hex - Hex graphic
     */
    setSelectedHex: function (hex) {
      if (!hex?.hexData) {
        return;
      }

      this.stateManager.set('selectedHex', hex);
      this.drawHexStyle(hex, 0x3b82f6, 3, 0x60a5fa, 1);

      const { q, r } = hex.hexData;
      this.uiManager.updateSelectedHex(q, r);
    },

    /**
     * Get currently active room payload.
     * @returns {Object|null}
     */
    getActiveRoomData: function () {
      if (!this.dungeonData || !this.activeRoomId || !this.dungeonData.rooms) {
        return null;
      }
      return this.dungeonData.rooms[this.activeRoomId] || null;
    },

    /**
     * Color room footprint for active room.
     */
    paintActiveRoom: function () {
      this.hexContainer.children.forEach((hex) => {
        if (!hex?.hexData) {
          return;
        }
        this.resetHexAppearance(hex);
      });
    },

    /**
     * Render active-room entities from dungeon payload.
     */
    renderActiveRoomEntities: function () {
      if (!this.entityManager) {
        return;
      }

      this.clearEntities();
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];

      entities.forEach((entity) => {
        const placement = entity?.placement;
        if (!placement || placement.room_id !== this.activeRoomId || !placement.hex) {
          return;
        }

        const q = Number(placement.hex.q);
        const r = Number(placement.hex.r);
        if (!Number.isFinite(q) || !Number.isFinite(r)) {
          return;
        }

        const rawType = entity?.entity_type ? String(entity.entity_type).toLowerCase() : '';
        let entityType = EntityType.OBSTACLE;
        if (rawType === 'creature') {
          entityType = EntityType.CREATURE;
        } else if (rawType === 'player_character' || rawType === 'player') {
          entityType = EntityType.PLAYER_CHARACTER;
        } else if (rawType === 'npc') {
          entityType = EntityType.NPC;
        } else if (rawType === 'item') {
          entityType = EntityType.ITEM;
        }

        const metadata = entity?.state?.metadata || {};
        const contentId = entity?.entity_ref?.content_id;
        const objectDefinition = this.getObjectDefinition(contentId);
        const portraitUrl = metadata.portrait_url || metadata.portrait || null;
        const launchCharacterId = Number(this.launchContext?.character_id || 0);
        const entityCharacterId = Number(metadata.character_id || entity?.character_id || 0);
        const entityName = metadata.display_name || metadata.name || entity?.display_name ||
          objectDefinition?.label || (contentId ? String(contentId).replace(/[_-]+/g, ' ') : String(entity.entity_type || 'entity'));

        const options = {
          team: metadata.team,
          stats: metadata.stats || {},
          movementSpeed: metadata.movement_speed,
          actionsPerTurn: metadata.actions_per_turn,
          initiativeBonus: metadata.initiative_bonus,
          orientation: placement.orientation || metadata.orientation || 'n',
          objectCategory: objectDefinition?.category || null,
          objectColor: objectDefinition?.visual?.color || null
        };

        if (entityType === EntityType.PLAYER_CHARACTER && launchCharacterId > 0 && entityCharacterId === launchCharacterId) {
          options.orientation = 'n';
          if (entity?.placement && typeof entity.placement === 'object') {
            entity.placement.orientation = 'n';
          }
          if (entity?.state && typeof entity.state === 'object') {
            entity.state.metadata = entity.state.metadata || {};
            entity.state.metadata.orientation = 'n';
          }
        }

        // Standardize portrait layering via object_definitions + sprite cache,
        // matching the same pipeline used by tavern door/object sprites.
        if (portraitUrl && contentId) {
          if (!this.dungeonData.object_definitions || typeof this.dungeonData.object_definitions !== 'object') {
            this.dungeonData.object_definitions = {};
          }

          const portraitSpriteId = `portrait_${String(contentId)}`;
          const existingDef = this.dungeonData.object_definitions[contentId] || {};
          const existingVisual = existingDef.visual && typeof existingDef.visual === 'object' ? existingDef.visual : {};

          this.dungeonData.object_definitions[contentId] = {
            ...existingDef,
            object_id: existingDef.object_id || contentId,
            label: existingDef.label || entityName,
            category: existingDef.category || 'creature',
            visual: {
              ...existingVisual,
              sprite_id: portraitSpriteId
            }
          };

          this.spriteService.preloadUrl(portraitSpriteId, portraitUrl);
          options.objectCategory = options.objectCategory || 'creature';
        }

        const created = this.createEntityObject(q, r, entityType, entityName, null, options);
        if (created) {
          created.dcEntityRef = entity?.instance_id || entity?.entity_ref?.content_id || null;
          created.dcContentId = contentId || null;
          created.dcCharacterId = Number(metadata.character_id || entity?.character_id || 0) || null;
          created.dcStatePayload = entity?.state || null;
        }
      });

      // Auto-enter initiative only once per campaign-backed encounter context.
      const shouldAutoStart = this.turnManagementSystem &&
        this.canUseServerCombatApi() &&
        this.resolveCampaignId() &&
        !this.stateManager.get('encounterId') &&
        !this.stateManager.get('combatActive');

      if (shouldAutoStart) {
        this.startCombat({ force: true });
      }

      // Resolve generated sprite images for all object definitions in view.
      this.spriteService.resolveAndApply(
        this.entityManager,
        this.renderSystem,
        this.dungeonData,
        this.activeRoomId,
        this.resolveCampaignId()
      );
    },

    /**
     * Resolve object definition by content ID.
     * @param {string} contentId - Object content ID
     * @returns {Object|null}
     */
    getObjectDefinition: function (contentId) {
      if (!contentId) {
        return null;
      }

      const definitions = this.dungeonData?.object_definitions;
      if (!definitions || typeof definitions !== 'object') {
        return null;
      }

      return definitions[contentId] || null;
    },

    /**
     * Get obstacle mobility profile at hex in active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {{movable: boolean, passable: boolean, stackable: boolean, isWall: boolean}|null}
     */
    getObstacleMobilityAtHex: function (q, r) {
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const obstacle = entities.find((entity) => {
        if (entity?.entity_type !== 'obstacle') {
          return false;
        }

        const placement = entity.placement;
        if (!placement || placement.room_id !== this.activeRoomId || !placement.hex) {
          return false;
        }

        return Number(placement.hex.q) === q && Number(placement.hex.r) === r;
      });

      if (!obstacle) {
        return null;
      }

      const objectDefinition = this.getObjectDefinition(obstacle?.entity_ref?.content_id);
      const metadata = obstacle?.state?.metadata || {};
      const definitionMovement = objectDefinition?.movement || {};
      const contentId = String(obstacle?.entity_ref?.content_id || '').toLowerCase();

      const movable = (typeof metadata.movable === 'boolean') ? metadata.movable : Boolean(objectDefinition?.movable);
      const passable = (typeof metadata.passable === 'boolean') ? metadata.passable : Boolean(definitionMovement.passable);
      const stackable = (typeof metadata.stackable === 'boolean') ? metadata.stackable : Boolean(objectDefinition?.stackable);
      const indicatorValues = [
        metadata.fixture_type,
        metadata.obstacle_type,
        objectDefinition?.category,
        objectDefinition?.type,
        objectDefinition?.object_type,
        contentId,
      ]
        .filter((value) => typeof value === 'string' && value.length)
        .map((value) => value.toLowerCase());
      const tagValues = [
        ...(Array.isArray(objectDefinition?.tags) ? objectDefinition.tags : []),
        ...(Array.isArray(objectDefinition?.traits) ? objectDefinition.traits : []),
      ]
        .filter((value) => typeof value === 'string' && value.length)
        .map((value) => value.toLowerCase());
      const isWall =
        metadata.is_wall === true ||
        indicatorValues.some((value) => value.includes('wall')) ||
        tagValues.some((value) => value === 'wall' || value.includes('boundary_wall') || value.includes('perimeter_wall'));

      return { movable, passable, stackable, isWall };
    },

    /**
     * Describe passability text for a hex.
     */
    describePassability: function (obstacleProfile, inActiveRoom) {
      if (obstacleProfile) {
        if (!obstacleProfile.passable && !obstacleProfile.movable) {
          return 'Impassable (fixed)';
        }
        if (!obstacleProfile.passable && obstacleProfile.movable) {
          return 'Impassable (movable)';
        }
        if (obstacleProfile.passable && obstacleProfile.movable) {
          return 'Passable (movable)';
        }
        return 'Passable';
      }

      return inActiveRoom ? 'Open floor' : 'Outside active room';
    },

    /**
     * Describe entities at a hex (live ECS first, then payload fallback).
     */
    describeEntitiesAtHex: function (q, r) {
      const labels = [];

      if (this.entityManager) {
        const liveEntities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent', 'CombatComponent');
        liveEntities.forEach((entity) => {
          const pos = entity.getComponent('PositionComponent');
          if (pos?.q !== q || pos?.r !== r) {
            return;
          }
          const identity = entity.getComponent('IdentityComponent');
          const combat = entity.getComponent('CombatComponent');
          const teamLabel = combat?.team ? ` (${combat.team})` : '';
          labels.push(`${identity?.name || 'Entity'}${teamLabel}`);
        });
      }

      if (labels.length) {
        return labels;
      }

      const payloadEntities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      const fallback = payloadEntities.filter((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }
        const hex = candidate.placement.hex;
        return hex && Number(hex.q) === q && Number(hex.r) === r;
      });

      fallback.forEach((candidate) => {
        const metadata = candidate?.state?.metadata || {};
        const displayName = metadata.display_name || metadata.name;
        if (displayName) {
          labels.push(displayName);
          return;
        }
        const contentId = candidate?.entity_ref?.content_id;
        labels.push(contentId ? String(contentId).replace(/[_-]+/g, ' ') : String(candidate.entity_type || 'entity'));
      });

      return labels;
    },

    /**
     * Describe objects on a hex from room payload and object definitions.
     */
    describeObjectsAtHex: function (hex, q, r) {
      const labels = [];

      if (hex && Array.isArray(hex.objects)) {
        hex.objects.forEach((object) => {
          if (object?.label) {
            labels.push(object.label);
          } else if (object?.object_id) {
            labels.push(String(object.object_id).replace(/[_-]+/g, ' '));
          }
        });
      }

      const obstacleLabel = this.getObstacleMobilityAtHex(q, r) ? this.getObjectLabelAtHex(q, r) : null;
      if (obstacleLabel) {
        labels.push(obstacleLabel);
      }

      return labels;
    },

    /**
     * Describe connection metadata for a hex if present.
     */
    describeConnectionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      if (!connections.length) {
        return null;
      }

      const match = connections.find((connection) => {
        const fromHex = connection?.from_hex;
        const toHex = connection?.to_hex;
        return (fromHex && Number(fromHex.q) === q && Number(fromHex.r) === r) ||
               (toHex && Number(toHex.q) === q && Number(toHex.r) === r);
      });

      if (!match) {
        return null;
      }

      const targetRoom = match.to_room === this.activeRoomId ? match.from_room : match.to_room;
      const status = [];
      status.push(match.is_passable ? 'passable' : 'blocked');
      if (match.is_discovered) {
        status.push('discovered');
      }

      return `${match.type || 'connection'} -> ${targetRoom || 'unknown'} (${status.join(', ')})`;
    },

    /**
     * Build a detail payload for the hovered hex.
     */
    getHexDetail: function (q, r) {
      const room = this.getActiveRoomData();
      if (!room) {
        return null;
      }

      const hex = Array.isArray(room.hexes) ? room.hexes.find((candidate) => Number(candidate.q) === q && Number(candidate.r) === r) : null;
      const inRoom = Boolean(hex);
      const obstacleProfile = this.getObstacleMobilityAtHex(q, r);

       const terrainKey = this.resolveTerrainKey(obstacleProfile, inRoom);
       const roomTerrain = room.terrain?.type && room.terrain.type !== 'unknown' ? String(room.terrain.type) : null;
       const terrainLabel = roomTerrain ? `${terrainKey} (${roomTerrain})` : terrainKey;

      return {
        roomName: inRoom ? room.name : `${room.name} (outside footprint)` ,
        terrain: terrainLabel,
        elevationFt: inRoom && Number.isFinite(Number(hex?.elevation_ft)) ? Number(hex.elevation_ft) : null,
        lighting: room.lighting?.level || 'unknown',
        passability: this.describePassability(obstacleProfile, inRoom),
        objects: this.describeObjectsAtHex(hex, q, r),
        entities: this.describeEntitiesAtHex(q, r),
        connection: this.describeConnectionAtHex(q, r)
      };
    },

    /**
     * Get object label (if any) at a given hex in the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {string|null}
     */
    getObjectLabelAtHex: function (q, r) {
      // Prefer live ECS entities so session-placed objects are labeled
      if (this.entityManager) {
        const liveEntities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
        const match = liveEntities.find((candidate) => {
          const pos = candidate.getComponent('PositionComponent');
          return pos && pos.q === q && pos.r === r;
        });

        if (match) {
          const identity = match.getComponent('IdentityComponent');
          if (identity?.name) {
            return identity.name;
          }
        }
      }

      // Fallback to dungeon payload for pre-seeded entities
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const entity = entities.find((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }

        const hex = candidate.placement.hex;
        if (!hex) {
          return false;
        }
        return Number(hex.q) === q && Number(hex.r) === r;
      });

      if (!entity) {
        return null;
      }

      const contentId = entity?.entity_ref?.content_id;
      const definition = this.getObjectDefinition(contentId);
      if (definition?.label) {
        return definition.label;
      }

      if (contentId) {
        return String(contentId).replace(/[_-]+/g, ' ');
      }

      return entity.entity_type ? String(entity.entity_type) : null;
    },

    /**
     * Get object identifier (if any) at a given hex in the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {string|null}
     */
    getObjectIdAtHex: function (q, r) {
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const entity = entities.find((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }

        const hex = candidate.placement.hex;
        if (!hex) {
          return false;
        }
        return Number(hex.q) === q && Number(hex.r) === r;
      });

      if (!entity) {
        return null;
      }

      return entity?.instance_id || entity?.entity_ref?.content_id || entity?.entity_type || null;
    },

    /**
     * Set active room and redraw room content.
     * @param {string} roomId - Target room ID
     */
    setActiveRoom: function (roomId) {
      if (!roomId || !this.dungeonData?.rooms || !this.dungeonData.rooms[roomId]) {
        return;
      }

      this.activeRoomId = roomId;
      this.stateManager.set('activeRoomId', roomId);
      this.paintActiveRoom();
      this.renderActiveRoomEntities();
      this.renderOrientationReferenceHex();
      this.refreshFogOfWar();
      // Load channels and chat history for the newly active room
      if (this.uiManager) {
        // Reset to room channel on room transition.
        this.uiManager.activeChannel = 'room';
        if (this.uiManager.loadChannels) {
          this.uiManager.loadChannels();
        }
        // Reload the currently active session view.
        if (this.uiManager.activeSessionView === 'room') {
          if (this.uiManager.loadChatHistory) {
            this.uiManager.loadChatHistory();
          }
        } else {
          // Refresh session view (narrative changes per-room).
          this.uiManager.loadSessionViewMessages(this.uiManager.activeSessionView);
        }
      }

      // Display room banner with scene description.
      const room = this.dungeonData.rooms[roomId];
      if (room) {
        const terrainLabel = room.terrain?.type
          ? room.terrain.type.replace(/_/g, ' ')
          : '';
        const lightingLabel = room.lighting && room.lighting !== 'normal'
          ? ` | Lighting: ${room.lighting}`
          : '';
        const sizeLabel = room.size_category && room.size_category !== 'medium'
          ? ` | ${room.size_category}`
          : '';
        const subtitle = [terrainLabel, lightingLabel, sizeLabel].filter(Boolean).join('').replace(/^\s*\|\s*/, '');

        // Canvas HUD banner
        if (room.name) {
          this.showRoomBanner(room.name, subtitle || null);
        }

        // Chat log messages
        const meta = subtitle ? ` (${subtitle})` : '';
        if (room.name) {
          this.uiManager.appendChatLine('System', `📍 ${room.name}${meta}`, 'system');
        }
        if (room.description) {
          this.uiManager.appendChatLine('System', room.description, 'system');
        }

        // Show active gameplay effects if any.
        const effects = room.gameplay_state?.active_effects || [];
        if (effects.length > 0) {
          const effectNames = effects.map(e => e.name?.replace(/_/g, ' ') || 'unknown').join(', ');
          this.uiManager.appendChatLine('System', `✨ Active effects: ${effectNames}`, 'system');
        }
      }

      this.renderDungeonStateInspector();

      console.log('Active room set:', roomId);
    },

    /**
     * Initialize quest data from drupalSettings and render the quest journal.
     */
    initQuestData: function () {
      const settings = drupalSettings || {};
      this.questData = settings?.dungeoncrawlerContent?.hexmapQuestSummary || {};
      const activeQuests = this.questData.active || [];
      if (this.uiManager) {
        this.uiManager.renderQuestJournal(activeQuests);
      }
      this.refreshQuestConfirmations();
      console.log('Quest data initialized:', { active: activeQuests.length, available: (this.questData.available || []).length });
    },

    /**
     * Refresh quest journal from API and re-render active quests.
     */
    refreshQuestJournalFromApi: async function () {
      const campaignId = this.resolveCampaignId();
      if (!campaignId || !this.uiManager) {
        return;
      }

      const characterId = Number(this.launchContext?.character_id || 0);
      const endpoint = characterId > 0
        ? `/api/campaign/${campaignId}/character/${characterId}/quest-journal`
        : `/api/campaign/${campaignId}/quest-journal`;

      try {
        const response = await fetch(endpoint, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
          return;
        }

        const payload = await response.json();
        if (!payload?.success) {
          return;
        }

        const tracking = Array.isArray(payload.tracking) ? payload.tracking : [];
        const inactiveStatuses = new Set(['completed', 'failed', 'abandoned', 'archived']);
        const activeQuests = tracking.filter((quest) => {
          const status = String(quest?.status || '').trim().toLowerCase();
          return status === '' || !inactiveStatuses.has(status);
        });

        this.questData = this.questData || {};
        this.questData.active = activeQuests;
        this.uiManager.renderQuestJournal(activeQuests);
      } catch (error) {
        console.warn('Failed to refresh quest journal from API:', error);
      }
    },

    /**
     * Fetch and render pending quest confirmations.
     */
    refreshQuestConfirmations: async function () {
      const campaignId = this.resolveCampaignId();
      if (!campaignId || !this.uiManager) {
        return;
      }

      const characterId = Number(this.launchContext?.character_id || 0);
      const query = characterId > 0 ? `?character_id=${encodeURIComponent(String(characterId))}` : '';

      try {
        const response = await fetch(`/api/campaign/${campaignId}/quest-confirmations${query}`, {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
          this.renderQuestConfirmations([]);
          return;
        }

        const payload = await response.json();
        const confirmations = payload?.success && Array.isArray(payload.confirmations)
          ? payload.confirmations
          : [];
        this.renderQuestConfirmations(confirmations);
      } catch (error) {
        console.warn('Failed to fetch quest confirmations:', error);
        this.renderQuestConfirmations([]);
      }
    },

    /**
     * Render pending confirmation cards and wire resolve actions.
     * @param {Array} confirmations
     */
    renderQuestConfirmations: function (confirmations) {
      const countEl = this.uiManager?.elements?.questConfirmationCount;
      const listEl = this.uiManager?.elements?.questConfirmationList;
      if (!listEl) {
        return;
      }

      const rows = Array.isArray(confirmations) ? confirmations : [];
      if (countEl) {
        countEl.textContent = String(rows.length);
      }

      if (rows.length === 0) {
        listEl.innerHTML = '<li class="quest-empty">No pending confirmations</li>';
        return;
      }

      listEl.innerHTML = rows.map((entry) => {
        const confirmationId = escapeQuestHtml(entry?.confirmation_id || '');
        const prompt = escapeQuestHtml(entry?.prompt || 'Confirm objective mapping');
        const candidates = Array.isArray(entry?.candidates) ? entry.candidates : [];
        const candidateHtml = candidates.map((candidate) => {
          const objectiveId = escapeQuestHtml(candidate?.objective_id || '');
          const label = escapeQuestHtml(candidate?.label || objectiveId || 'Objective');
          const questName = escapeQuestHtml(candidate?.quest_name || candidate?.quest_id || 'Quest');
          return `<li>${label} <span class="quest-status">(${questName})</span></li>`;
        }).join('');

        const approveActions = candidates.map((candidate) => {
          const objectiveId = escapeQuestHtml(candidate?.objective_id || '');
          const label = escapeQuestHtml(candidate?.label || objectiveId || 'objective');
          return `<button class="quest-confirmation-action quest-confirmation-action--approve" data-confirmation-id="${confirmationId}" data-decision="approved" data-objective-id="${objectiveId}">Approve: ${label}</button>`;
        }).join('');

        return `<li class="quest-confirmation-entry" data-confirmation-id="${confirmationId}">
          <div class="quest-confirmation-entry__prompt">${prompt}</div>
          <ul class="quest-confirmation-entry__candidates">${candidateHtml}</ul>
          <div class="quest-confirmation-entry__actions">
            ${approveActions}
            <button class="quest-confirmation-action quest-confirmation-action--reject" data-confirmation-id="${confirmationId}" data-decision="rejected">Reject</button>
          </div>
        </li>`;
      }).join('');

      listEl.querySelectorAll('.quest-confirmation-action').forEach((button) => {
        button.addEventListener('click', async (event) => {
          event.preventDefault();
          const actionButton = event.currentTarget;
          const confirmationId = actionButton.dataset.confirmationId || '';
          const resolution = actionButton.dataset.decision || 'rejected';
          const selectedObjectiveId = actionButton.dataset.objectiveId || null;
          if (!confirmationId) {
            return;
          }

          const entry = actionButton.closest('.quest-confirmation-entry');
          if (entry) {
            entry.querySelectorAll('.quest-confirmation-action').forEach((btn) => {
              btn.disabled = true;
            });
          }

          await this.resolveQuestConfirmation(confirmationId, resolution, selectedObjectiveId);
        });
      });
    },

    /**
     * Resolve one pending confirmation and refresh quest/confirmation state.
     * @param {string} confirmationId
     * @param {'approved'|'rejected'} resolution
     * @param {string|null} selectedObjectiveId
     */
    resolveQuestConfirmation: async function (confirmationId, resolution, selectedObjectiveId = null) {
      const campaignId = this.resolveCampaignId();
      if (!campaignId) {
        return;
      }

      try {
        const response = await fetch(`/api/campaign/${campaignId}/quest-confirmations/${encodeURIComponent(confirmationId)}/resolve`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            resolution,
            selected_objective_id: selectedObjectiveId,
            resolved_by: 'player',
          }),
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.success) {
          this.uiManager?.appendChatLine('Quest', 'Failed to resolve confirmation.', 'system');
          await this.refreshQuestConfirmations();
          return;
        }

        if (resolution === 'approved') {
          this.uiManager?.showQuestToast('Quest confirmation approved and applied.', 'success');
        } else {
          this.uiManager?.showQuestToast('Quest confirmation rejected.', 'info');
        }

        await this.refreshQuestJournalFromApi();
        await this.refreshQuestConfirmations();
      } catch (error) {
        console.warn('Failed to resolve quest confirmation:', error);
        this.uiManager?.appendChatLine('Quest', 'Unable to resolve confirmation right now.', 'system');
        await this.refreshQuestConfirmations();
      }
    },

    /**
     * Collect a quest item entity — update progress via API, remove from grid, refresh journal.
     * @param {Entity} actor - The player entity performing the collection.
     * @param {Entity} itemEntity - The quest item ECS entity.
     * @param {Object} metadata - Item entity metadata (quest_id, objective_id, item_name).
     */
    collectQuestItem: async function (actor, itemEntity, metadata) {
      const campaignId = this.resolveCampaignId();
      if (!campaignId) {
        return;
      }

      const questId = metadata.quest_id;
      const objectiveId = metadata.objective_id;
      const itemName = metadata.item_name || 'quest item';

      // Show pickup feedback immediately.
      this.uiManager.appendChatLine('System', `You picked up: ${itemName}`, 'system');

      // Remove item entity from ECS and dungeon data.
      if (itemEntity.id != null && this.entityManager) {
        this.entityManager.removeEntity(itemEntity.id);
      }
      // Also remove from dungeonData.entities so it doesn't reappear on re-render.
      const instanceId = itemEntity.dcEntityRef || metadata.instance_id;
      if (instanceId && Array.isArray(this.dungeonData?.entities)) {
        this.dungeonData.entities = this.dungeonData.entities.filter(
          e => (e.instance_id || e.entity_instance_id) !== instanceId
        );
        this.renderDungeonStateInspector();
      }

      // Call quest progress API.
      try {
        const response = await fetch(`/api/campaign/${campaignId}/quests/${questId}/progress`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            objective_id: objectiveId,
            action: 'collect',
            entity_id: instanceId || objectiveId,
            character_id: this.launchContext?.character_id || null,
          }),
        });

        if (response.ok) {
          const result = await response.json();
          if (result.success) {
            console.info('Quest progress updated:', result);

            // Update local quest data to reflect progress.
            this.updateLocalQuestProgress(questId, objectiveId, 1);

            // Check if this objective is now complete.
            const objState = this.getObjectiveState(questId, objectiveId);
            if (objState && objState.current >= objState.target) {
              this.uiManager.showQuestToast(`Objective complete: ${objState.description || objectiveId}`, 'success');

              // Check if all objectives in the quest are complete.
              if (this.isQuestComplete(questId)) {
                this.uiManager.showQuestToast(`Quest ready for turn-in: ${this.getQuestTitle(questId)}`, 'success');
              }
            }
          }
        } else {
          console.warn('Quest progress API error:', response.status);
        }
      } catch (error) {
        console.error('Quest progress update failed:', error);
      }

      // Refresh quest journal UI.
      if (this.uiManager && this.questData?.active) {
        this.uiManager.renderQuestJournal(this.questData.active);
      }
    },

    /**
     * Attempt to turn in a quest to an NPC.
     * @param {Entity} actor - Player entity.
     * @param {string} npcRef - NPC instance_id or entity_ref.
     * @param {Entity} npcEntity - The NPC ECS entity.
     * @returns {boolean} Whether a quest was turned in.
     */
    tryQuestTurnIn: async function (actor, npcRef, npcEntity) {
      const campaignId = this.resolveCampaignId();
      if (!campaignId) return false;

      const activeQuests = this.questData?.active || [];
      const npcIdentity = npcEntity?.getComponent?.('IdentityComponent');
      const npcName = npcIdentity?.name || 'NPC';

      // Find a quest that has all collect objectives complete and an interact
      // objective targeting this NPC type.
      for (const quest of activeQuests) {
        const phases = extractQuestPhases(quest);
        const objectiveIndex = buildObjectiveStateIndex(quest);
        const questId = quest.quest_id || quest.id;
        const questData = quest.quest_data || {};
        const giverNpcId = questData.variables?.giver_npc_id;

        for (const phase of phases) {
          for (const obj of (phase.objectives || [])) {
            if (obj.type !== 'interact') continue;

            // Check if the NPC matches the quest target.
            const target = (obj.target || '').toLowerCase();
            const npcNameLower = npcName.toLowerCase();
            const npcRefLower = (npcRef || '').toLowerCase();
            const matchesTarget = npcNameLower.includes(target) || npcRefLower.includes(target) ||
              (giverNpcId && npcEntity.dcCharacterId === giverNpcId);

            if (!matchesTarget) continue;

            // Check if all collect objectives in earlier phases are complete.
            const allCollectsDone = phases.every(p => {
              return (p.objectives || []).filter(o => o.type === 'collect').every(o => {
                const merged = mergeObjectiveProgress(o, objectiveIndex);
                return merged.current >= (merged.target_count || 1);
              });
            });

            if (!allCollectsDone) {
              this.uiManager.appendChatLine(npcName, `You haven't gathered everything yet. Keep looking!`, 'npc');
              return true;
            }

            // Complete the quest via API.
            this.uiManager.appendChatLine(npcName, `Excellent work! Here is your reward.`, 'npc');

            try {
              const completeRes = await fetch(`/api/campaign/${campaignId}/quests/${questId}/complete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ entity_id: npcRef, outcome: 'success' }),
              });

              if (completeRes.ok) {
                const completeResult = await completeRes.json();
                console.info('Quest completed:', completeResult);

                // Claim rewards.
                const rewardRes = await fetch(`/api/campaign/${campaignId}/quests/${questId}/rewards/claim`, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                  body: JSON.stringify({ character_id: this.launchContext?.character_id }),
                });

                if (rewardRes.ok) {
                  const rewardResult = await rewardRes.json();
                  const rewards = quest.generated_rewards || {};
                  const xp = rewards.xp || 0;
                  const gold = rewards.gold || 0;
                  const rewardParts = [];
                  if (xp > 0) rewardParts.push(`${xp} XP`);
                  if (gold > 0) rewardParts.push(`${gold} gold`);
                  this.uiManager.showQuestToast(
                    `Quest complete: ${resolveQuestTitle(quest)}! Rewards: ${rewardParts.join(', ') || 'none'}`,
                    'success'
                  );

                  // Grant XP to character.
                  if (xp > 0 && this.launchContext?.character_id) {
                    await fetch(`/api/character/${this.launchContext.character_id}/experience`, {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                      body: JSON.stringify({ xp_amount: xp, source: `quest:${questId}` }),
                    }).catch(err => console.warn('XP grant failed:', err));
                  }
                }

                // Remove completed quest from local active list.
                this.questData.active = (this.questData.active || []).filter(q => (q.quest_id || q.id) !== questId);
                this.uiManager.renderQuestJournal(this.questData.active);
              }
            } catch (error) {
              console.error('Quest completion failed:', error);
            }

            return true;
          }
        }
      }

      return false;
    },

    /**
     * Update local quest objective progress.
     * @param {string|number} questId
     * @param {string} objectiveId
     * @param {number} increment
     */
    updateLocalQuestProgress: function (questId, objectiveId, increment) {
      const activeQuests = this.questData?.active || [];
      for (const quest of activeQuests) {
        if ((quest.quest_id || quest.id) != questId) continue;

        const generatedPhases = Array.isArray(quest.generated_objectives) ? quest.generated_objectives : [];
        for (const phase of generatedPhases) {
          for (const obj of (phase.objectives || [])) {
            if (obj.objective_id === objectiveId) {
              obj.current = (obj.current || 0) + increment;
            }
          }
        }

        // Update objective_states (supports flat and nested shapes).
        if (!quest.objective_states) quest.objective_states = [];
        let found = false;

        for (const os of quest.objective_states) {
          if (os && Array.isArray(os.objectives)) {
            for (const objective of os.objectives) {
              if (objective.objective_id === objectiveId) {
                objective.current = (objective.current || 0) + increment;
                found = true;
                break;
              }
            }
            if (found) {
              break;
            }
            continue;
          }

          if (os.objective_id === objectiveId) {
            os.current = (os.current || 0) + increment;
            found = true;
            break;
          }
        }

        if (!found) {
          const firstNestedPhase = quest.objective_states.find(os => os && Array.isArray(os.objectives));
          if (firstNestedPhase) {
            firstNestedPhase.objectives.push({ objective_id: objectiveId, current: increment, target_count: 1, completed: false });
          } else {
            quest.objective_states.push({ objective_id: objectiveId, current: increment });
          }
        }
        break;
      }
    },

    /**
     * Get the current state of a specific objective.
     * @returns {{current: number, target: number, description: string}|null}
     */
    getObjectiveState: function (questId, objectiveId) {
      const activeQuests = this.questData?.active || [];
      for (const quest of activeQuests) {
        if ((quest.quest_id || quest.id) != questId) continue;
        const phases = extractQuestPhases(quest);
        const objectiveIndex = buildObjectiveStateIndex(quest);
        for (const phase of phases) {
          for (const obj of (phase.objectives || [])) {
            if (obj.objective_id === objectiveId) {
              const merged = mergeObjectiveProgress(obj, objectiveIndex);
              return { current: merged.current, target: merged.target_count || 1, description: merged.description || '' };
            }
          }
        }

        // Fallback for state-only objectives that don't exist in generated_objectives.
        const fallback = objectiveIndex[objectiveId];
        if (fallback) {
          return { current: fallback.current || 0, target: fallback.target || 1, description: fallback.description || '' };
        }
      }
      return null;
    },

    /**
     * Check if all objectives in a quest are complete.
     * @param {string|number} questId
     * @returns {boolean}
     */
    isQuestComplete: function (questId) {
      const activeQuests = this.questData?.active || [];
      for (const quest of activeQuests) {
        if ((quest.quest_id || quest.id) != questId) continue;
        const phases = extractQuestPhases(quest);
        for (const phase of phases) {
          for (const obj of (phase.objectives || [])) {
            if (obj.type === 'interact') continue; // Turn-in objectives checked separately.
            const state = this.getObjectiveState(questId, obj.objective_id);
            if (!state || state.current < state.target) return false;
          }
        }
        return true;
      }
      return false;
    },

    /**
     * Get quest title by ID.
     * @param {string|number} questId
     * @returns {string}
     */
    getQuestTitle: function (questId) {
      const activeQuests = this.questData?.active || [];
      for (const quest of activeQuests) {
        if ((quest.quest_id || quest.id) == questId) return resolveQuestTitle(quest);
      }
      return 'Quest';
    },

    /**
     * Apply dungeon payload and initialize active room view.
     */
    applyDungeonData: function () {
      // Validate schema version for compatibility
      const schemaVersion = this.dungeonData?.schema_version;
      if (!schemaVersion) {
        console.warn('Dungeon payload missing schema_version field. Assuming 1.0.0.');
      } else if (schemaVersion !== '1.0.0') {
        console.warn(`Dungeon schema version ${schemaVersion} may not be fully compatible. Expected 1.0.0.`);
      }

      const rooms = this.dungeonData?.rooms;
      if (!rooms || Object.keys(rooms).length === 0) {
        return;
      }

      if (!this.activeRoomId || !rooms[this.activeRoomId]) {
        this.activeRoomId = Object.keys(rooms)[0];
      }

      this.setActiveRoom(this.activeRoomId);
      this.renderDungeonStateInspector();
    },

    /**
     * Render object definitions and full dungeon JSON state.
     */
    renderDungeonStateInspector: function () {
      const summaryEl = document.getElementById('dungeon-state-summary');
      const gridEl = document.getElementById('dungeon-objects-grid');
      const entitiesSummaryEl = document.getElementById('dungeon-entities-summary');
      const entitiesAnalysisEl = document.getElementById('dungeon-entities-analysis');
      const entitiesGridEl = document.getElementById('dungeon-entities-grid');
      const jsonEl = document.getElementById('dungeon-state-json');

      if (!summaryEl || !gridEl || !entitiesSummaryEl || !entitiesAnalysisEl || !entitiesGridEl || !jsonEl) {
        return;
      }

      const dungeon = this.dungeonData || {};
      const rooms = dungeon.rooms && typeof dungeon.rooms === 'object' ? dungeon.rooms : {};
      const entities = Array.isArray(dungeon.entities) ? dungeon.entities : [];
      const defs = dungeon.object_definitions && typeof dungeon.object_definitions === 'object'
        ? dungeon.object_definitions
        : {};

      const roomCount = Object.keys(rooms).length;
      const objectCount = Object.keys(defs).length;
      const entityCount = entities.length;
      summaryEl.textContent = `Active room: ${this.activeRoomId || 'n/a'} · Rooms: ${roomCount} · Objects: ${objectCount} · Entities: ${entityCount}`;

      const usageCounts = {};
      const requiresObjectDefinition = (entityType) => {
        const t = String(entityType || '').toLowerCase();
        return t === 'obstacle' || t === 'item';
      };

      entities.forEach((entity) => {
        if (!requiresObjectDefinition(entity?.entity_type)) {
          return;
        }
        const contentId = entity?.entity_ref?.content_id;
        if (!contentId) {
          return;
        }
        usageCounts[contentId] = (usageCounts[contentId] || 0) + 1;
      });

      const usedObjectIds = Object.keys(usageCounts);
      const missingDefinitionIds = usedObjectIds.filter((objectId) => !defs[objectId]);
      const usedDefinitionIds = usedObjectIds.filter((objectId) => !!defs[objectId]);
      const unusedDefinitionIds = Object.keys(defs).filter((objectId) => !usageCounts[objectId]);

      const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

      const entries = usedDefinitionIds
        .map((objectId) => [objectId, defs[objectId]])
        .sort(([, a], [, b]) => {
          const aLabel = String(a?.label || a?.object_id || '');
          const bLabel = String(b?.label || b?.object_id || '');
        return aLabel.localeCompare(bLabel);
      });

      summaryEl.textContent = `Active room: ${this.activeRoomId || 'n/a'} · Rooms: ${roomCount} · Objects Used: ${usedDefinitionIds.length}/${objectCount} · Entities: ${entityCount}`;

      if (!entries.length && !missingDefinitionIds.length) {
        gridEl.innerHTML = '<div class="dungeon-objects-empty">No object definitions available.</div>';
      } else {
        const cards = entries.map(([objectId, definition]) => {
          const label = definition?.label || objectId;
          const category = definition?.category || 'unknown';
          const spriteId = definition?.visual?.sprite_id || 'n/a';
          const color = definition?.visual?.color || '';
          const size = definition?.visual?.size || 'n/a';
          const used = usageCounts[objectId] || 0;
          const colorSwatch = color
            ? `<span class="dungeon-object-color" style="background:${escapeHtml(color)}"></span>`
            : '';

          const spriteLine = spriteId && spriteId !== 'n/a'
            ? `<li><strong>Sprite:</strong> ${escapeHtml(spriteId)}</li>`
            : '';
          const sizeLine = size && size !== 'n/a'
            ? `<li><strong>Size:</strong> ${escapeHtml(size)}</li>`
            : '';
          const colorLine = color
            ? `<li><strong>Color:</strong> ${escapeHtml(color)} ${colorSwatch}</li>`
            : '';

          return `
            <div class="dungeon-object-card">
              <div class="dungeon-object-card__title">
                <span>${escapeHtml(label)}</span>
                <span class="pill pill-muted">${escapeHtml(category)}</span>
              </div>
              <ul class="dungeon-object-card__meta">
                <li><strong>ID:</strong> ${escapeHtml(objectId)}</li>
                ${spriteLine}
                ${sizeLine}
                ${colorLine}
                <li><strong>Used:</strong> ${used} placed</li>
              </ul>
            </div>`;
        });

        const missingCards = missingDefinitionIds.map((objectId) => `
          <div class="dungeon-object-card">
            <div class="dungeon-object-card__title">
              <span>${escapeHtml(objectId)}</span>
              <span class="pill pill-warning">missing definition</span>
            </div>
            <ul class="dungeon-object-card__meta">
              <li><strong>ID:</strong> ${escapeHtml(objectId)}</li>
              <li><strong>Used:</strong> ${usageCounts[objectId] || 0} placed</li>
            </ul>
          </div>
        `);

        const footer = `
          <div class="dungeon-objects-empty">
            ${unusedDefinitionIds.length} unused definitions hidden from this view.
          </div>
        `;

        gridEl.innerHTML = [...cards, ...missingCards, footer].join('');
      }

      const activeRoomEntities = entities.filter((entity) => entity?.placement?.room_id === this.activeRoomId);
      entitiesSummaryEl.textContent = `Active room entities: ${activeRoomEntities.length} · Total entities: ${entities.length}`;

      let missingPlacement = 0;
      let missingHex = 0;
      let missingInstance = 0;
      let missingContent = 0;
      let missingTeam = 0;
      let otherRoom = 0;

      entities.forEach((entity) => {
        const roomId = String(entity?.placement?.room_id || '');
        const q = Number(entity?.placement?.hex?.q);
        const r = Number(entity?.placement?.hex?.r);
        const hasHex = Number.isFinite(q) && Number.isFinite(r);
        const instanceId = String(entity?.instance_id || entity?.entity_instance_id || '');
        const contentId = String(entity?.entity_ref?.content_id || '');
        const team = entity?.state?.metadata?.team;

        if (!roomId) {
          missingPlacement++;
        } else if (roomId !== this.activeRoomId) {
          otherRoom++;
        }
        if (!hasHex) {
          missingHex++;
        }
        if (!instanceId) {
          missingInstance++;
        }
        if (!contentId) {
          missingContent++;
        }
        if (!team) {
          missingTeam++;
        }
      });

      entitiesAnalysisEl.textContent = `Not in active room: ${otherRoom} · Missing placement: ${missingPlacement} · Missing hex: ${missingHex} · Team N/A: ${missingTeam} · Content ID N/A: ${missingContent} · Instance N/A: ${missingInstance}`;

      const sortedEntities = [...entities].sort((a, b) => {
        const roomA = String(a?.placement?.room_id || '');
        const roomB = String(b?.placement?.room_id || '');
        if (roomA !== roomB) return roomA.localeCompare(roomB);
        const typeA = String(a?.entity_type || '');
        const typeB = String(b?.entity_type || '');
        if (typeA !== typeB) return typeA.localeCompare(typeB);
        const nameA = String(a?.state?.metadata?.display_name || a?.entity_ref?.content_id || a?.instance_id || '');
        const nameB = String(b?.state?.metadata?.display_name || b?.entity_ref?.content_id || b?.instance_id || '');
        return nameA.localeCompare(nameB);
      });

      if (!sortedEntities.length) {
        entitiesGridEl.innerHTML = '<div class="dungeon-objects-empty">No entities available.</div>';
      } else {
        entitiesGridEl.innerHTML = sortedEntities.map((entity) => {
          const roomId = String(entity?.placement?.room_id || 'n/a');
          const q = Number(entity?.placement?.hex?.q);
          const r = Number(entity?.placement?.hex?.r);
          const hasHex = Number.isFinite(q) && Number.isFinite(r);
          const entityType = String(entity?.entity_type || 'unknown');
          const contentId = String(entity?.entity_ref?.content_id || 'n/a');
          const instanceId = String(entity?.instance_id || entity?.entity_instance_id || 'n/a');
          const displayName = entity?.state?.metadata?.display_name || contentId;
          const team = entity?.state?.metadata?.team || (entityType === 'obstacle' || entityType === 'item' ? 'n/a (non-combat)' : 'n/a');
          const settingState = entity?.state?.metadata?.setting_state === true ? 'yes' : 'no';
          const isActiveRoom = roomId === this.activeRoomId;

          return `
            <div class="dungeon-entity-card ${isActiveRoom ? 'dungeon-entity-card--active-room' : ''}">
              <div class="dungeon-entity-card__title">
                <span>${escapeHtml(displayName)}</span>
                <span class="pill pill-muted">${escapeHtml(entityType)}</span>
              </div>
              <ul class="dungeon-entity-card__meta">
                <li><strong>Instance:</strong> ${escapeHtml(instanceId)}</li>
                <li><strong>Content ID:</strong> ${escapeHtml(contentId)}</li>
                <li><strong>Room:</strong> ${escapeHtml(roomId)}</li>
                <li><strong>Hex:</strong> ${hasHex ? `(${q}, ${r})` : 'n/a'}</li>
                <li><strong>Team:</strong> ${escapeHtml(team)}</li>
                <li><strong>Setting State:</strong> ${escapeHtml(settingState)}</li>
              </ul>
            </div>`;
        }).join('');
      }

      jsonEl.textContent = JSON.stringify(dungeon, null, 2);
    },

    /**
     * Try to transition to a connected room at a given hex.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {boolean}
     */
    tryTransitionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      if (!connections.length || !this.activeRoomId) {
        return false;
      }

      const match = connections.find((connection) => {
        if (connection?.is_passable === false) {
          return false;
        }

        const fromMatch = connection.from_room === this.activeRoomId &&
          Number(connection?.from_hex?.q) === q &&
          Number(connection?.from_hex?.r) === r;
        const toMatch = connection.to_room === this.activeRoomId &&
          Number(connection?.to_hex?.q) === q &&
          Number(connection?.to_hex?.r) === r;

        return fromMatch || toMatch;
      });

      if (!match) {
        return false;
      }

      let nextRoomId = null;
      let nextHex = null;

      if (match.from_room === this.activeRoomId) {
        nextRoomId = match.to_room;
        nextHex = match.to_hex;
      } else {
        nextRoomId = match.from_room;
        nextHex = match.from_hex;
      }

      // Move the selected player entity's dungeon placement to the destination room/hex
      // so it persists across the room transition and re-renders in the new room.
      const selectedEntity = this.stateManager.get('selectedEntity');
      if (selectedEntity && Array.isArray(this.dungeonData?.entities)) {
        const identity = selectedEntity.getComponent?.('IdentityComponent');
        const combat = selectedEntity.getComponent?.('CombatComponent');
        const isPlayer = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');

        if (isPlayer) {
          const entityRef = selectedEntity.dcEntityRef;
          // Update the dungeon payload entity placement to the new room.
          for (const de of this.dungeonData.entities) {
            const deRef = de.instance_id || de.entity_instance_id;
            if (deRef === entityRef || (selectedEntity.dcCharacterId && de?.state?.metadata?.character_id == selectedEntity.dcCharacterId)) {
              de.placement = {
                room_id: nextRoomId,
                hex: { q: Number(nextHex?.q || 0), r: Number(nextHex?.r || 0) },
              };
              break;
            }
          }

          // Also move ally NPCs to the new room (adjacent hexes).
          const npcEntities = this.dungeonData.entities.filter(e =>
            e.entity_type === 'npc' && e?.state?.metadata?.team === 'ally'
          );
          const destQ = Number(nextHex?.q || 0);
          const destR = Number(nextHex?.r || 0);
          const offsets = [{ q: 1, r: 0 }, { q: -1, r: 0 }, { q: 0, r: 1 }, { q: 0, r: -1 }, { q: 1, r: -1 }, { q: -1, r: 1 }];
          npcEntities.forEach((npc, i) => {
            const offset = offsets[i % offsets.length];
            npc.placement = {
              room_id: nextRoomId,
              hex: { q: destQ + offset.q, r: destR + offset.r },
            };
          });

          // Persist entity position to server.
          const campaignId = this.resolveCampaignId();
          if (campaignId && entityRef) {
            fetch(`/api/campaign/${campaignId}/entity/${entityRef}/move`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              body: JSON.stringify({ room_id: nextRoomId, q: Number(nextHex?.q || 0), r: Number(nextHex?.r || 0) }),
            }).catch(err => console.warn('Entity move persist failed:', err));
          }
        }
      }

      // Deselect current entity before switching rooms (will be re-selected after render).
      if (selectedEntity) {
        this.deselectEntity();
      }

      this.setActiveRoom(nextRoomId);

      const destinationHex = this.findHexByCoords(Number(nextHex?.q), Number(nextHex?.r));
      if (destinationHex) {
        const previousSelectedHex = this.stateManager.get('selectedHex');
        if (previousSelectedHex && previousSelectedHex !== destinationHex) {
          this.onHexOut(previousSelectedHex);
        }
        this.setSelectedHex(destinationHex);
      }

      // Re-select the player entity in the new room after re-render.
      const newPlayerEntity = this.findLaunchPlayerEntity();
      if (newPlayerEntity) {
        this.selectEntity(newPlayerEntity);
        if (this.uiManager && this.launchCharacter) {
          this.uiManager.showLaunchCharacter(this.launchCharacter);
        }
      }

      console.log('Transitioned room:', this.activeRoomId, 'via connection', match.connection_id);
      return true;
    },

    /**
     * Apply campaign launch context to initialize map state.
     */
    applyLaunchContext: function () {
      const context = this.launchContext || {};
      const hasContext = Boolean(
        (Number(context.campaign_id) > 0) ||
        context.room_id ||
        context.dungeon_level_id ||
        context.map_id
      );

      if (!hasContext) {
        return;
      }

      const startQ = Number.isFinite(Number(context.start_q)) ? Number(context.start_q) : 0;
      const startR = Number.isFinite(Number(context.start_r)) ? Number(context.start_r) : 0;
      const startHex = this.findHexByCoords(startQ, startR);

      if (startHex) {
        const previousSelectedHex = this.stateManager.get('selectedHex');
        if (previousSelectedHex && previousSelectedHex !== startHex) {
          this.onHexOut(previousSelectedHex);
        }
        this.setSelectedHex(startHex);
        this.refreshFogOfWar();
        console.log('Applied launch context start hex:', startQ, startR, context);
      } else {
        console.warn('Launch context start hex not found in current grid:', startQ, startR, context);
      }
    }

    ,

    /**
     * Render an in-map orientation reference on hex 7,2 using side labels.
     * Labels are aligned to flat-top hex edge centers: N, NE, SE, S, SW, NW.
     */
    renderOrientationReferenceHex: function () {
      if (!this.uiContainer) {
        return;
      }

      if (this._orientationReferenceOverlay) {
        this.uiContainer.removeChild(this._orientationReferenceOverlay);
        this._orientationReferenceOverlay.destroy({ children: true });
        this._orientationReferenceOverlay = null;
      }

      const referenceQ = 7;
      const referenceR = 2;
      const referenceHex = this.findHexByCoords(referenceQ, referenceR);
      if (!referenceHex || !this.isHexInActiveRoom(referenceQ, referenceR)) {
        return;
      }

      const overlay = new PIXI.Container();
      overlay.zIndex = 9050;
      overlay.interactive = false;
      overlay.eventMode = 'none';

      const center = this.axialToPixel(referenceQ, referenceR, this.config.hexSize);
      const radius = this.config.hexSize;

      const ring = new PIXI.Graphics();
      ring.lineStyle(2, 0x22c55e, 0.95);
      ring.beginFill(0x22c55e, 0.08);
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = center.x + radius * Math.cos(angle);
        const y = center.y + radius * Math.sin(angle);
        if (i === 0) {
          ring.moveTo(x, y);
        } else {
          ring.lineTo(x, y);
        }
      }
      ring.closePath();
      ring.endFill();
      overlay.addChild(ring);

      const labels = [
        { text: 'N', angle: -Math.PI / 2, color: 0xe53e3e },
        { text: 'NE', angle: -Math.PI / 6, color: 0xe2e8f0 },
        { text: 'SE', angle: Math.PI / 6, color: 0xe2e8f0 },
        { text: 'S', angle: Math.PI / 2, color: 0xe2e8f0 },
        { text: 'SW', angle: (5 * Math.PI) / 6, color: 0xe2e8f0 },
        { text: 'NW', angle: -(5 * Math.PI) / 6, color: 0xe2e8f0 },
      ];

      labels.forEach(({ text, angle, color }) => {
        const edgeX = center.x + Math.cos(angle) * (radius * 0.88);
        const edgeY = center.y + Math.sin(angle) * (radius * 0.88);

        const tick = new PIXI.Graphics();
        tick.lineStyle(2, color, 0.95);
        tick.moveTo(center.x + Math.cos(angle) * (radius * 0.65), center.y + Math.sin(angle) * (radius * 0.65));
        tick.lineTo(edgeX, edgeY);
        overlay.addChild(tick);

        const label = new PIXI.Text(text, {
          fontFamily: 'Arial',
          fontSize: 10,
          fill: color,
          fontWeight: 'bold',
          stroke: 0x0f172a,
          strokeThickness: 3,
          align: 'center',
        });
        label.anchor.set(0.5);
        label.x = center.x + Math.cos(angle) * (radius * 1.22);
        label.y = center.y + Math.sin(angle) * (radius * 1.22);
        overlay.addChild(label);
      });

      const title = new PIXI.Text('Orientation Ref', {
        fontFamily: 'Arial',
        fontSize: 9,
        fill: 0x86efac,
        fontWeight: 'bold',
        stroke: 0x0f172a,
        strokeThickness: 2,
      });
      title.anchor.set(0.5);
      title.x = center.x;
      title.y = center.y;
      overlay.addChild(title);

      this.uiContainer.addChild(overlay);
      this._orientationReferenceOverlay = overlay;
    }

    ,

    /**
     * Find best player entity candidate for launch hydration.
     * Prefers a player-team entity on the launch start hex.
     * @returns {Entity|null}
     */
    findLaunchPlayerEntity: function () {
      if (!this.entityManager) {
        return null;
      }

      const entities = this.entityManager.getEntitiesWith('PositionComponent', 'CombatComponent');
      if (!Array.isArray(entities) || !entities.length) {
        return null;
      }

      const playerEntities = entities.filter((entity) => {
        const combat = entity.getComponent('CombatComponent');
        if (!combat) {
          return false;
        }

        return combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
      });

      if (!playerEntities.length) {
        return null;
      }

      const startQ = Number.isFinite(Number(this.launchContext?.start_q)) ? Number(this.launchContext.start_q) : 0;
      const startR = Number.isFinite(Number(this.launchContext?.start_r)) ? Number(this.launchContext.start_r) : 0;
      const onStartHex = playerEntities.find((entity) => {
        const pos = entity.getComponent('PositionComponent');
        return pos && pos.q === startQ && pos.r === startR;
      });

      return onStartHex || playerEntities[0] || null;
    }

    ,

    /**
     * Select launch player entity to hydrate character sheet.
     * @returns {boolean}
     */
    applyLaunchCharacterSelection: function () {
      if (!this.stateManager || this.stateManager.get('selectedEntity')) {
        return true;
      }

      const hasCampaignContext = Number(this.launchContext?.campaign_id || 0) > 0;
      if (!hasCampaignContext) {
        return false;
      }

      const entity = this.findLaunchPlayerEntity();
      if (!entity) {
        return false;
      }

      this.selectEntity(entity);

      // selectEntity → showEntityInfo only sets basic ECS stats (HP, AC, speed).
      // Also populate the full character sheet (abilities, skills, feats,
      // inventory, currency, etc.) from the launch character payload and the
      // character state API so the sidebar is complete.
      if (this.uiManager && this.launchCharacter) {
        this.uiManager.showLaunchCharacter(this.launchCharacter);
      }
      const characterId = Number(this.launchContext?.character_id || 0);
      if (characterId > 0) {
        this.loadCharacterFromApi(characterId);
      }

      return true;
    }

    ,

    /**
     * Populate character sheet from launch character context when no entity is selected.
     */
    applyLaunchCharacterSummary: function () {
      if (!this.uiManager || !this.stateManager) {
        return;
      }

      if (this.stateManager.get('selectedEntity')) {
        return;
      }

      const hasCampaignContext = Number(this.launchContext?.campaign_id || 0) > 0;
      const characterId = Number(this.launchContext?.character_id || 0);

      if (!hasCampaignContext) {
        return;
      }

      // First display launch character, then load full state from API if available
      this.uiManager.showLaunchCharacter(this.launchCharacter || {});

      // Load full character state from API
      if (characterId > 0) {
        this.loadCharacterFromApi(characterId);
      }
    }

    ,

    /**
     * Load full character state from API and display in character sheet.
     */
    loadCharacterFromApi: function (characterId) {
      if (!characterId || !this.uiManager) {
        return;
      }

      const url = `/api/character/${characterId}/state`;
      
      fetch(url)
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          if (data.success && data.data) {
            this.uiManager.showLaunchCharacter(data.data);
          }
        })
        .catch(error => {
          console.log('Character API load optional; demo continues:', error);
        });
    }
  };

})(Drupal, once);
