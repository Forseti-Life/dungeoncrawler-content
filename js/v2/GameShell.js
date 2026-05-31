/**
 * @file GameShell.js
 *
 * Top-level orchestrator for hexmap-v2.
 *
 * Responsibilities:
 *   - Parse Drupal settings into a structured launch context
 *   - Instantiate GameEventBus, ECS, canvas modules, systems, and panels
 *   - Wire ECS system callbacks → bus events (no direct callbacks between modules)
 *   - Delegate all sub-domain logic to owned modules
 *
 * NOT responsible for:
 *   - Rendering (HexCanvas owns that)
 *   - DOM manipulation (panels own that)
 *   - Game rules (systems own that)
 *
 * @see GameEventBus
 * @see canvas/HexCanvas
 * @see systems/EncounterSystem
 * @see panels/PortraitPanel
 */

import { GameEventBus } from './GameEventBus.js';
import { HexCanvas } from './canvas/HexCanvas.js';
import { HexTokenRenderer } from './canvas/HexTokenRenderer.js';
import { HexFogOfWar } from './canvas/HexFogOfWar.js';
import { HexInputHandler } from './canvas/HexInputHandler.js';
import { EncounterSystem } from './systems/EncounterSystem.js';
import { NavigationSystem } from './systems/NavigationSystem.js';
import { PlayerAutomation } from './systems/PlayerAutomation.js';
import { QuestSystem } from './systems/QuestSystem.js';
import { PortraitPanel } from './panels/PortraitPanel.js';
import { MerchantPanel } from './panels/MerchantPanel.js';
import { CombatPanel } from './panels/CombatPanel.js';
import { ActionRailPanel } from './panels/ActionRailPanel.js';
import { ChatPanel } from './panels/ChatPanel.js';
import { QuestPanel } from './panels/QuestPanel.js';
import { InventoryPanel } from './panels/InventoryPanel.js';
import { CharacterPanel } from './panels/CharacterPanel.js';
import { RoomViewPanel } from './panels/RoomViewPanel.js';
import { PartyRailPanel } from './panels/PartyRailPanel.js';
import { StatusPanel } from './panels/StatusPanel.js';
import {
  EntityManager,
  RenderSystem,
  MovementSystem,
  TurnManagementSystem,
  CombatSystem,
} from '../ecs/index.js';

/** Canvas config defaults — matches old hexmap behavior.config */
const DEFAULT_CANVAS_CONFIG = {
  hexSize: 30,
  gridWidth: 20,
  gridHeight: 20,
  minZoom: 0.5,
  maxZoom: 3.0,
  defaultVisionRange: 8,
  backgroundColor: 0x1a1a2e,
  serverStateSyncIntervalMs: 3000,
};

export class GameShell {
  /**
   * @param {HTMLElement} container - Root DOM container for hexmap-v2
   * @param {object} rawSettings    - drupalSettings.dungeoncrawlerContent subset
   */
  constructor(container, rawSettings = {}) {
    this.container = container;

    /** Parsed launch context from Drupal settings */
    this.launchContext = rawSettings.hexmapLaunchContext || {};
    /** Full dungeon payload (room graph, entity instances, quest data) */
    this.dungeonData = rawSettings.hexmapDungeonData || {};
    /** Canonical visual bootstrap state from MapVisualStateProjector */
    this.mapVisualState = rawSettings.map_visual_state || {};
    /** Launch character summary for initial sheet hydration */
    this.launchCharacter = rawSettings.hexmapLaunchCharacter || {};
    /** Quest summary payload for initial QuestPanel render */
    this.questSummary = rawSettings.hexmapQuestSummary || {};

    this.currentUserId = Number(rawSettings.userId || 0);
    this.activeRoomId =
      this.mapVisualState?.map_meta?.active_room_id ||
      this.launchContext?.room_id ||
      null;

    // Sub-module handles — populated in init()
    this.bus = null;

    /** @type {{ app: import('./canvas/HexCanvas').HexCanvas, tokens: HexTokenRenderer, fog: HexFogOfWar, input: HexInputHandler }} */
    this.canvas = null;

    /** @type {{ encounter: EncounterSystem, navigation: NavigationSystem, automation: PlayerAutomation, quest: QuestSystem }} */
    this.systems = {};

    /** @type {{ portrait: PortraitPanel, merchant: MerchantPanel, combat: CombatPanel, actionRail: ActionRailPanel, chat: ChatPanel, quest: QuestPanel, inventory: InventoryPanel, character: CharacterPanel, roomView: RoomViewPanel, partyRail: PartyRailPanel, status: StatusPanel }} */
    this.panels = {};

    // ECS — populated in _initECS()
    this.entityManager = null;
    this.renderSystem = null;
    this.movementSystem = null;
    this.combatSystem = null;
    this.turnManagementSystem = null;
  }

  /**
   * Initialize all sub-modules. Called from Drupal.behaviors.hexMapV2.attach.
   * Order: bus → ECS → canvas → systems → panels → emit game:init
   */
  init() {
    this.bus = new GameEventBus();
    this._initECS();
    this._initCanvas();
    this._initSystems();
    this._initPanels();
    this.bus.emit('game:init', {
      launchContext: this.launchContext,
      // Canonical keys panels expect
      character:     this.launchCharacter,
      inventory:     {
        items:    this.launchCharacter?.inventory?.items ?? [],
        currency: this.launchCharacter?.currency ?? this.launchCharacter?.inventory?.currency ?? {},
      },
      quests:        [
        ...(Array.isArray(this.questSummary?.active) ? this.questSummary.active : []),
        ...(Array.isArray(this.questSummary?.offers) ? this.questSummary.offers : []),
        ...(Array.isArray(this.questSummary?.leads)  ? this.questSummary.leads  : []),
      ],
      // Raw payloads for systems that need full context
      launchCharacter: this.launchCharacter,
      questSummary:  this.questSummary,
      dungeonData:   this.dungeonData,
      mapVisualState: this.mapVisualState,
      activeRoomId:  this.activeRoomId,
    });
    this._emitInitialRoomState();
  }

  /**
   * Emit room:changed and room:occupants-changed for the active room on startup,
   * using the bootstrapped mapVisualState from Drupal settings.
   * @private
   */
  _emitInitialRoomState() {
    const roomId = this.activeRoomId;
    if (!roomId) return;

    const visualRooms = this.mapVisualState?.topology?.rooms ?? {};
    const room = visualRooms[roomId] ?? null;
    const roomName = room?.name ?? roomId;

    this.bus.emit('room:changed', {
      roomId,
      roomName,
      sceneImageUrl: room?.image_url ?? null,
      responders: [],
    });

    const occupantsData = this.mapVisualState?.occupants ?? {};
    const partyOccupants = (Array.isArray(occupantsData.party) ? occupantsData.party : [])
      .map((o) => ({ ...o, is_party: true }));
    const entityOccupants = Array.isArray(occupantsData.entities) ? occupantsData.entities : [];
    const allOccupants = [...partyOccupants, ...entityOccupants];
    const roomOccupants = allOccupants.filter(
      (o) => String(o?.room_id ?? '') === roomId && o?.state?.hidden !== true,
    );

    this.bus.emit('room:occupants-changed', {
      roomId,
      roomName,
      occupants: roomOccupants,
    });
  }

  /**
   * Create ECS entity manager and systems; wire callbacks → bus events.
   * @private
   */
  _initECS() {
    const bus = this.bus;
    this.entityManager = new EntityManager();

    // RenderSystem: phase 2 will pass PIXI containers once HexCanvas is init'd
    this.renderSystem = new RenderSystem(this.entityManager, null, {
      hex: null,
      object: null,
      ui: null,
    });
    this.entityManager.addSystem(this.renderSystem);

    this.movementSystem = new MovementSystem(this.entityManager);
    this.entityManager.addSystem(this.movementSystem);

    this.combatSystem = new CombatSystem(this.entityManager);
    this.combatSystem.onAttack((attackData) => {
      bus.emit('combat:attack-performed', attackData);
    });
    this.combatSystem.onDamage((damageData) => {
      bus.emit('combat:damage-dealt', damageData);
    });
    this.entityManager.addSystem(this.combatSystem);

    this.turnManagementSystem = new TurnManagementSystem(this.entityManager);
    this.turnManagementSystem.onTurnChange((entity, turnIndex, totalTurns) => {
      bus.emit('combat:turn-changed', { entity, turnIndex, totalTurns });
    });
    this.turnManagementSystem.onRoundChange?.((roundNumber) => {
      bus.emit('combat:round-changed', { roundNumber });
    });
    this.turnManagementSystem.onCombatStateChange?.((state) => {
      bus.emit('combat:state-changed', { state });
    });
    this.entityManager.addSystem(this.turnManagementSystem);
  }

  /**
   * Create canvas modules and wire them to the bus.
   * Phase 2 fills HexCanvas with real PIXI rendering; stubs safe for now.
   * @private
   */
  _initCanvas() {
    const canvasContainer = this.container.querySelector('[data-hexmap-canvas]') ?? this.container;
    const hexCanvas = new HexCanvas(canvasContainer, this.bus, DEFAULT_CANVAS_CONFIG);
    hexCanvas.init();

    // Update RenderSystem with real PIXI containers now that canvas is initialized
    if (this.renderSystem && hexCanvas.objectContainer) {
      this.renderSystem.containers = {
        hex: hexCanvas.hexContainer,
        object: hexCanvas.objectContainer,
        ui: hexCanvas.uiContainer,
      };
    }

    const tokens = new HexTokenRenderer(hexCanvas, this.bus);
    tokens.init();

    const fog = new HexFogOfWar(hexCanvas, this.bus);
    fog.init();

    const input = new HexInputHandler(hexCanvas, this.bus);
    input.init();

    this.canvas = { app: hexCanvas, tokens, fog, input };
  }

  /**
   * Create game systems and wire them to the bus.
   * Phase 4–8 fill these in; stubs safe for now.
   * @private
   */
  _initSystems() {
    this.systems.navigation = new NavigationSystem(this, this.bus);
    this.systems.navigation.init();

    this.systems.encounter = new EncounterSystem(this, this.bus);
    this.systems.encounter.init();

    this.systems.automation = new PlayerAutomation(this, this.bus);
    this.systems.automation.init();

    this.systems.quest = new QuestSystem(this, this.bus);
    this.systems.quest.init();
  }

  /**
   * Create panels and wire them to the bus.
   * Phase 4–9 fill these in; stubs safe for now.
   * @private
   */
  _initPanels() {
    const c = this.container;
    const bus = this.bus;
    const panel = (sel) => c.querySelector(sel) ?? c;

    this.panels.portrait   = new PortraitPanel(panel('[data-panel="portrait"]'), bus);
    this.panels.merchant   = new MerchantPanel(panel('[data-panel="merchant"]'), bus);
    this.panels.combat     = new CombatPanel(panel('[data-panel="combat"]'), bus);
    this.panels.actionRail = new ActionRailPanel(panel('[data-panel="action-rail"]'), bus);
    this.panels.chat       = new ChatPanel(panel('[data-panel="chat"]'), bus);
    this.panels.quest      = new QuestPanel(panel('[data-panel="quest"]'), bus);
    this.panels.inventory  = new InventoryPanel(panel('[data-panel="inventory"]'), bus);
    this.panels.character  = new CharacterPanel(panel('[data-panel="character"]'), bus);
    this.panels.roomView   = new RoomViewPanel(panel('[data-panel="room-view"]'), bus);
    this.panels.partyRail  = new PartyRailPanel(panel('[data-panel="party-rail"]'), bus);
    this.panels.status     = new StatusPanel(panel('[data-panel="status"]'), bus);

    Object.values(this.panels).forEach((p) => p.init());
  }

  /**
   * Tear down all sub-modules in reverse init order.
   * Called from Drupal.behaviors.hexMapV2.detach.
   */
  destroy() {
    Object.values(this.panels).forEach((p) => p?.destroy?.());
    Object.values(this.systems).forEach((s) => s?.destroy?.());
    this.canvas?.input?.destroy?.();
    this.canvas?.fog?.destroy?.();
    this.canvas?.tokens?.destroy?.();
    this.canvas?.app?.destroy?.();
    this.bus?.destroy?.();

    this.entityManager = null;
    this.canvas = null;
    this.systems = {};
    this.panels = {};
    this.bus = null;
  }
}
