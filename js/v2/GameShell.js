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
 *   - Authoritative game-state decisions (server engine owns that)
 *
 * Authority boundary:
 *   - This shell is a projection/runtime host for server-authored state.
 *   - Do not add client-side rule engines that reinterpret encounter or
 *     navigation legality. Consume server contracts and render/dispatch only.
 *
 * @see GameEventBus
 * @see canvas/HexCanvas
 * @see systems/EncounterSystem
 */
import { GameEventBus } from './GameEventBus.js';
import { HexCanvas } from './canvas/HexCanvas.js';
import { HexTokenRenderer } from './canvas/HexTokenRenderer.js';
import { HexFogOfWar } from './canvas/HexFogOfWar.js';
import { HexInputHandler } from './canvas/HexInputHandler.js';
import { EncounterSystem } from './systems/EncounterSystem.js?v=20260828-v4-combat-drag-routing-1';
import { NavigationSystem } from './systems/NavigationSystem.js?v=20260728-v2-nav-transition-receipt-4';
import { PlayerAutomation } from './systems/PlayerAutomation.js?v=20260608-v2-chat-persistence-dev-1';
import { QuestSystem } from './systems/QuestSystem.js?v=20260608-v2-quest-summary-merge-2';
import { MerchantPanel } from './panels/MerchantPanel.js';
import { CombatPanel } from './panels/CombatPanel.js?v=20260827-v2-bootstrap-status-12';
import { ActionRailPanel } from './panels/ActionRailPanel.js?v=20260828-v3-suggest-next-move-1';
import { ChatPanel } from './panels/ChatPanel.js?v=20260812-v2-map-status-centralization-1';
import { QuestPanel } from './panels/QuestPanel.js?v=20260723-v2-quest-storyline-grouping-2';
import { InventoryPanel } from './panels/InventoryPanel.js';
import { CharacterPanel } from './panels/CharacterPanel.js?v=20260828-v4-combat-drag-routing-1';
import { RoomViewPanel } from './panels/RoomViewPanel.js';
import { StatusPanel } from './panels/StatusPanel.js?v=20260828-v4-chat-backend-wait-1';
import { normalizeInventoryState } from './utils/inventory-utils.js';
import { normalizeQuestSummaryPayload } from './utils/quest-utils.js?v=20260607-quest-summary-const-4';
import { normalizeAuthoritativeStateActorRef } from './utils/authoritative-state-utils.js';
import { GameShellFetchBridge } from './shell/GameShellFetchBridge.js';
import { GameShellCampaignSettingsCoordinator } from './shell/GameShellCampaignSettingsCoordinator.js';
import { GameShellTargetPickController } from './shell/GameShellTargetPickController.js';
import { GameShellQuestCoordinator } from './shell/GameShellQuestCoordinator.js';
import { GameShellRoomGenerationCoordinator } from './shell/GameShellRoomGenerationCoordinator.js';
import { SpriteService } from '../SpriteService.js?v=20260828-v5-map-actor-portraits-1';
import { GameCoordinator } from '../game-coordinator/GameCoordinator.js?v=20260811-v2-target-pick-hardening-15';
import {
  EntityManager,
  PositionComponent,
  RenderComponent,
  IdentityComponent,
  MovementComponent,
  StatsComponent,
  ActionsComponent,
  CombatComponent,
  RenderSystem,
  MovementSystem,
  TurnManagementSystem,
  CombatSystem,
} from '../ecs/index.js';
import {
  _getPresentationObjectDefinitions,
  _getVisualOccupants,
  _getVisualActorRoster,
  _getEntityDisplayName,
  _isVisualOccupantVisible,
  _parseVisualHexId,
  _getConnectionRoomId,
  _getConnectionHex,
  _getActiveRoomData,
  _getActiveRoomHex,
  _buildActiveRoomOccupantSummary,
  _getObjectDefinition,
  _buildObstacleMobilityProfile,
  _getObstacleMobilityAtHex,
  _getAxialLine,
  _hasLineOfSight,
  _getHostileTargets,
  _normalizeAuthoritativeNavigationCapability,
  _normalizeHexPayload,
  _findLaunchPlayerEntity,
  _preloadSpriteUrls,
  _flattenQuestObjectives,
  _isPlainObject,
  _hasMeaningfulValue,
  _mergeRoomMetadata,
  _buildRoomSubtitle,
  _buildRoomConnections,
  _buildRenderableEntityBlueprints,
  _buildVisualOccupantIndex,
  _resolveVisualOccupant,
  _normalizeRenderableEntityType,
  _normalizeRenderableEntityTeam,
  _buildRenderableEntityKey,
  _buildRenderableProjectionKey,
  _buildLogicalActorIdentityKey,
  _buildRuntimeBundleQueryForRoom,
} from './shell/GameShellProjectionHelpers.js?v=20260828-v5-map-actor-portraits-1';
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
const HEXMAP_UI_BUILD_VERSION = '20260827-v2-bootstrap-status-1';
export class GameShell {
  /**
   * @param {HTMLElement} container - Root DOM container for hexmap-v2
   * @param {object} rawSettings    - drupalSettings.dungeoncrawlerContent subset
   */
  constructor(container, rawSettings = {}) {
    console.info('[GameShell] module loaded', {
      version: HEXMAP_UI_BUILD_VERSION,
    });
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
    /** Server-side bootstrap timing breakdown for current page load */
    this.bootstrapPerf = rawSettings.hexmapBootstrapPerf || {};

    this.currentUserId = Number(rawSettings.userId || rawSettings.user?.uid || 0);
    this.campaignAccess = this._normalizeCampaignAccess(rawSettings.campaignAccess || {});
    this.activeCampaignMode = this.campaignAccess.current_mode || this.campaignAccess.default_mode || 'player';
    this.activeRoomId = null;
    this._syncActiveRoomAuthorityFromRuntimePayload();
    this.characterData = this.launchCharacter;
    this.spriteService = new SpriteService();
    this._hexmapShim = null;
    this._stateManagerShim = null;
    this._domUnsubs = [];
    this._busUnsubs = [];
    this._inventoryRefreshSequence = 0;
    this._characterRefreshSequence = 0;
    this._lastRosterRuntimeRefreshAt = 0;
    this.reset();

    // Sub-module handles — populated in init()
    this.bus = null;

    /** @type {{ app: import('./canvas/HexCanvas').HexCanvas, tokens: HexTokenRenderer, fog: HexFogOfWar, input: HexInputHandler }} */
    this.canvas = null;

    /** @type {{ encounter: EncounterSystem, navigation: NavigationSystem, automation: PlayerAutomation, quest: QuestSystem }} */
    this.systems = {};

    /** @type {{ merchant: MerchantPanel, combat: CombatPanel, actionRail: ActionRailPanel, chat: ChatPanel, quest: QuestPanel, inventory: InventoryPanel, character: CharacterPanel, roomView: RoomViewPanel, status: StatusPanel }} */
    this.panels = {};

    /** @type {GameCoordinator|null} */
    this.gameCoordinator = null;
    /** @type {GameShellFetchBridge|null} */
    this.fetchBridge = null;
    /** @type {GameShellCampaignSettingsCoordinator|null} */
    this.campaignSettingsCoordinator = new GameShellCampaignSettingsCoordinator(this);
    /** @type {GameShellTargetPickController|null} */
    this.targetPickController = new GameShellTargetPickController(this);
    /** @type {GameShellQuestCoordinator|null} */
    this.questCoordinator = new GameShellQuestCoordinator(this);
    /** @type {GameShellRoomGenerationCoordinator|null} */
    this.roomGenerationCoordinator = new GameShellRoomGenerationCoordinator(this);

    // ECS — populated in _initECS()
    this.entityManager = null;
    this.renderSystem = null;
    this.movementSystem = null;
    this.combatSystem = null;
    this.turnManagementSystem = null;

    /** @type {Array} Latest flat occupant list for the active room (used by merchant stock loader) */
    this._currentOccupants = [];

    /** @type {Map<string, number>} merchantRef → request token (prevent stale responses) */
    this._merchantRequestTokens = new Map();
    /** @type {number} room-view request token */
    this._roomViewRequestToken = 0;
    /** @type {Map<string, Promise>} in-flight room view requests keyed by campaignId:roomId */
    this._roomViewInflight = new Map();
    /** @type {string|null} last fetched view key */
    this._roomViewLastKey = null;
    /** @type {boolean} current room view has gallery content */
    this._roomViewHasContent = false;
    /** @type {number|null} pending retry timer */
    this._roomViewRetryTimer = null;
    /** @type {Map<string, number>} room-view key -> retry window start ms */
    this._roomViewRetryStartedAt = new Map();
    /** @type {number} total room-view retry window before hard failure */
    this._roomViewRetryWindowMs = 180000;
    /** @type {boolean} chat history already loaded for this session */
    this._chatHistoryLoaded = false;
    /** @type {number} monotonic chat-history request sequence */
    this._chatHistoryRequestToken = 0;
    /** @type {{transitionId:string, roomId:string, campaignId:number, characterId:number|null, mapId:string}|null} */
    this._pendingRoomEntryAcknowledgement = null;
    /** @type {Set<string>} transition-scoped acknowledgement requests already sent */
    this._completedRoomEntryAcknowledgements = new Set();
    /** @type {string} active acknowledgement request key */
    this._roomEntryAcknowledgementInflightKey = '';
    /** @type {string} currently active tab id */
    this.activeGameShellTab = 'map';
    /** @type {boolean} settings panel hydration guard */
    this._campaignSettingsLoaded = false;
    /** @type {boolean} settings panel fetch lock */
    this._campaignSettingsLoading = false;
    /** @type {object|null} last settings payload */
    this._campaignSettingsPayload = null;
    /** @type {Set<string>} dedupe set for missing navigation capability contract warnings */
    this._missingNavigationExitsWarnings = new Set();
    /** @type {{ sourceRef: Array|null, sourceLength: number, normalized: Array, byRoom: Map<string, Array> }} */
    this._navigationCapabilitiesCache = {
      sourceRef: null,
      sourceLength: 0,
      normalized: [],
      byRoom: new Map(),
    };
    /** @type {number} monotonic room transition sequence */
    this._roomTransitionSequence = 0;
    /** @type {string} last processed external room transition id */
    this._lastExternalRoomTransitionId = '';
    /** @type {HTMLDivElement|null} map right-click context menu */
    this._hexContextMenuEl = null;
    /** @type {Function|null} global context menu dismiss handler */
    this._hexContextMenuDismissHandler = null;
    /** @type {{ actionKey:string, button:HTMLButtonElement, promptLabel:string, allowedKinds:string[], sourceSurface:string }|null} */
    this._targetPickSession = null;
    /** @type {HTMLElement|null} */
    this._targetPickPromptEl = null;
    /** @type {Function|null} */
    this._authoritativeGameEventsHandler = null;
  }

  /**
   * Refresh quest journal state from the server and emit the canonical summary.
   *
   * @returns {Promise<boolean>}
   *   TRUE when refreshed successfully; otherwise FALSE.
   */
  async refreshQuestJournalFromApi(context = {}) {
    return this.questCoordinator?.refreshQuestJournalFromApi(context);
  }

  /**
   * Apply quest updates from authoritative chat payloads.
   * First refreshes from quest-journal API; falls back to local merge if needed.
   *
   * @param {Array} questUpdates
   * @returns {Promise<boolean>}
   */
  async applyQuestUpdates(questUpdates = []) {
    return this.questCoordinator?.applyQuestUpdates(questUpdates);
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
    this._applyCampaignModeGates();
    this._bindMapControls();
    this._bindInteractionEvents();
    this.setupFullscreenToggle();
    // Portrait textures load asynchronously, often after the token layer has
    // already rendered. Re-emit the entity set when one lands so map tokens
    // pick up the same art the chat tab shows instead of keeping placeholders.
    if (!this._spriteTextureReadyUnsub) {
      this._spriteTextureReadyUnsub = this.spriteService?.onTextureReady?.(() => {
        this._scheduleTokenSpriteRefresh();
      }) || null;
    }
    if (typeof window !== 'undefined' && typeof window.addEventListener === 'function' && !this._authoritativeGameEventsHandler) {
      this._authoritativeGameEventsHandler = (customEvent = {}) => {
        const events = Array.isArray(customEvent?.detail?.events) ? customEvent.detail.events : [];
        this._projectAuthoritativeMovementEvents(events);
      };
      window.addEventListener('dungeoncrawler:game-events', this._authoritativeGameEventsHandler);
    }

    // Build flat quests array with objectives flattened from phases
    const allQuests = [
      ...(Array.isArray(this.questSummary?.active) ? this.questSummary.active : []),
      ...(Array.isArray(this.questSummary?.offers) ? this.questSummary.offers : []),
      ...(Array.isArray(this.questSummary?.leads)  ? this.questSummary.leads  : []),
    ].map((q) => ({ ...q, objectives: _flattenQuestObjectives(q) }));

    const launchCharacterId = this.launchCharacter?.id ?? this.launchContext?.character_id ?? null;
    const launchCampaignId = Number(
      this.launchContext?.campaign_id
      || this.launchCharacter?.campaignId
      || this.launchCharacter?.campaign_id
      || 0
    ) || null;
    const launchInventoryContext = {
      characterId: launchCharacterId,
      campaignId: launchCampaignId,
      inventory: this.launchCharacter?.inventory ?? {
        items: this.launchCharacter?.inventory?.items ?? [],
        currency: this.launchCharacter?.currency ?? this.launchCharacter?.inventory?.currency ?? {},
      },
      equipment: Array.isArray(this.launchCharacter?.equipment) ? this.launchCharacter.equipment : [],
      currency: this.launchCharacter?.currency ?? this.launchCharacter?.inventory?.currency ?? {},
      abilities: this.launchCharacter?.abilities ?? this.launchCharacter?.data?.abilities ?? {},
    };

    this.currentCharacterInventoryContext = launchInventoryContext;

    this.bus.emit('game:init', {
      launchContext: this.launchContext,
      // Canonical keys panels expect
      character:     this.launchCharacter,
      inventory:     launchInventoryContext.inventory,
      inventoryContext: launchInventoryContext,
      quests: allQuests,
      // Raw payloads for systems that need full context
      launchCharacter: this.launchCharacter,
      questSummary:  this.questSummary,
      dungeonData:   this.dungeonData,
      mapVisualState: this.mapVisualState,
      activeRoomId:  this.activeRoomId,
    });
    if (launchInventoryContext.characterId) {
      // Hydrate from canonical runtime API state on boot so sheet XP/items are never stale.
      void this.loadCharacterFromApi(launchInventoryContext.characterId);
    }
    this._emitInitialRoomState();
    this._syncActiveRoomEntities();
    this._initApiHandlers();
    this._syncInitialActiveTabState();
    this._initGameCoordinator();
    void this._triggerPendingRoomGeneration();
  }

  _syncInitialActiveTabState() {
    const shell = this.container?.closest?.('[data-game-shell]')
      || this.container?.querySelector?.('[data-game-shell]')
      || (typeof document !== 'undefined' ? document.querySelector('[data-game-shell]') : null)
      || null;
    const activeTab = shell?.dataset?.gameShellActive || '';
    console.log('[GameShell] _syncInitialActiveTabState', {
      activeTab: activeTab || null,
      shellFound: Boolean(shell),
    });
    if (!activeTab) {
      return;
    }

    window.dispatchEvent(new CustomEvent('dungeoncrawler:game-shell-tab-changed', {
      detail: {
        tabId: activeTab,
        source: 'gameshell-init-sync',
      },
    }));
  }

  _initGameCoordinator() {
    const campaignId = Number(this.resolveCampaignId?.() || this.launchContext?.campaign_id || 0) || 0;
    if (campaignId <= 0 || !this.canUseServerCombatApi()) {
      this.gameCoordinator = null;
      return;
    }

    const hexmapShim = this._buildHexmapShim();
    this.gameCoordinator = new GameCoordinator(campaignId, hexmapShim);
    const coordinatorRequestId = `encounter-bootstrap-${campaignId}-${Date.now()}`;
    this.bus?.emit?.('game:backend-request-start', {
      requestId: coordinatorRequestId,
      label: 'Hydrating encounter state...',
      source: 'encounter-bootstrap',
    });
    if (this.bootstrapPerf && typeof console !== 'undefined' && typeof console.info === 'function') {
      console.info('[GameShell] hexmap bootstrap timings (ms)', this.bootstrapPerf);
    }
    this.gameCoordinator.init()
      .then(async () => {
        const authoritativeRoomId = String(this.gameCoordinator?.phaseManager?.activeRoomId || '').trim();
        if (authoritativeRoomId && authoritativeRoomId !== this.activeRoomId) {
          // Keep room authority and navigation capabilities atomic: coordinator
          // bootstrap room changes must be hydrated from canonical visual-state.
          try {
            await this.loadRuntimeStateBundle(
              this.buildRuntimeBundleQueryForRoom(authoritativeRoomId, {
                startQ: this.launchContext?.start_q,
                startR: this.launchContext?.start_r,
              }),
            );
          } catch (error) {
            console.warn('[GameShell] Coordinator bootstrap room sync failed; suppressing unsynchronized room activation', {
              authoritativeRoomId,
              activeRoomId: this.activeRoomId || null,
              status: Number(error?.status || 0) || null,
              code: String(error?.code || '').trim() || null,
              message: error?.message || error,
            });
          }
        }
        this.panels?.actionRail?.refreshActionRail?.();
      })
      .catch((error) => {
        console.warn('GameCoordinator init failed; coordinator-driven action dispatch disabled:', error?.message || error);
        this.gameCoordinator = null;
        this.panels?.actionRail?.refreshActionRail?.();
      })
      .finally(() => {
        this.bus?.emit?.('game:backend-request-end', {
          requestId: coordinatorRequestId,
          source: 'encounter-bootstrap',
        });
      });
  }

  buildRuntimeBundleQueryForRoom(roomId = '', options = {}) {
    return _buildRuntimeBundleQueryForRoom(this, roomId, options);
  }

  /**
   * Emit room:changed and room:occupants-changed for the active room on startup,
   * using the bootstrapped mapVisualState from Drupal settings.
   * @private
   */
  _emitInitialRoomState() {
    const roomId = this.activeRoomId;
    if (!roomId) return;
    this._synchronizePartyOccupantsToRoom(roomId);

    const visualRooms = this.mapVisualState?.topology?.rooms ?? {};
    const room = _mergeRoomMetadata(visualRooms[roomId] ?? null, {}, roomId);
    const roomName = room?.name ?? roomId;
    this._activeRoomData = room ?? null;
    const roomHexes = Array.isArray(room?.hexes) ? room.hexes : [];
    const roomObjectCount = roomHexes.reduce((count, hex) => count + (Array.isArray(hex?.objects) ? hex.objects.length : 0), 0);
    console.info('[GameShell] active room resolved', {
      roomId,
      roomName,
      hasRoom: !!room,
      hasDescription: Boolean(String(room?.description ?? '').trim()),
      descriptionLength: String(room?.description ?? '').trim().length,
      hexCount: roomHexes.length,
      objectCount: roomObjectCount,
      connectionCount: _buildRoomConnections(roomId, this.mapVisualState).length,
      mapMetaActiveRoomId: this.mapVisualState?.map_meta?.active_room_id ?? null,
    });

    const occupantsData = this.mapVisualState?.occupants ?? {};
    const partyOccupants = (Array.isArray(occupantsData.party) ? occupantsData.party : [])
      .map((o) => ({ ...o, is_party: true }));
    const entityOccupants = Array.isArray(occupantsData.entities) ? occupantsData.entities : [];
    const allOccupants = [...partyOccupants, ...entityOccupants];
    const roomOccupants = allOccupants.filter(
      (o) => String(o?.room_id ?? '') === roomId && o?.state?.hidden !== true,
    );

    this._currentOccupants = roomOccupants;
    console.log('[GameShell] _emitInitialOccupants', {
      total: allOccupants.length,
      forRoom: roomOccupants.length,
      npcsWithPortrait: roomOccupants.filter((o) => o?.presentation?.portrait_url).length,
      npcSample: roomOccupants.filter((o) => o?.occupant_type === 'npc').map((o) => ({ name: o?.label, portrait: o?.presentation?.portrait_url ? o.presentation.portrait_url.slice(-40) : null })),
      playerPlacementSample: roomOccupants
        .filter((o) => String(o?.occupant_type ?? '') === 'player_character' || o?.is_party === true)
        .map((o) => ({
          label: o?.label ?? null,
          actorRef: o?.occupant_ref ?? o?.actor_ref ?? null,
          roomId: o?.room_id ?? null,
          placement: o?.placement ?? null,
          source: o?.source ?? null,
        })),
    });
    this._emitCanonicalRoomChanged({
      roomId,
      roomName,
      room,
      occupants: roomOccupants,
      source: 'shell-initial-state',
      phase: 'bootstrap',
      loadData: false,
    });
  }

  _nextRoomTransitionId(source = 'shell', phase = 'applied') {
    this._roomTransitionSequence += 1;
    return `room-transition-${source}-${phase}-${this._roomTransitionSequence}`;
  }

  _emitCanonicalRoomChanged({
    roomId,
    roomName = '',
    room = null,
    occupants = null,
    source = 'shell',
    phase = 'applied',
    transitionId = '',
    loadData = true,
    forceView = false,
    preserveView = false,
  } = {}) {
    const normalizedRoomId = String(roomId || '').trim();
    if (!normalizedRoomId) {
      return;
    }

    const resolvedRoom = _mergeRoomMetadata(
      this.mapVisualState?.topology?.rooms?.[normalizedRoomId] ?? null,
      room || {},
      normalizedRoomId,
    );
    const resolvedRoomName = String(roomName || resolvedRoom?.name || normalizedRoomId).trim() || normalizedRoomId;
    const transition = {
      id: String(transitionId || '').trim() || this._nextRoomTransitionId(source, phase),
      source,
      phase,
      roomId: normalizedRoomId,
      timestamp: Date.now(),
    };
    this._chatHistoryLoaded = false;
    this.activeRoomId = normalizedRoomId;
    this._synchronizePartyOccupantsToRoom(normalizedRoomId);
    if (this.mapVisualState && typeof this.mapVisualState === 'object') {
      if (!this.mapVisualState.map_meta || typeof this.mapVisualState.map_meta !== 'object') {
        this.mapVisualState.map_meta = {};
      }
      this.mapVisualState.map_meta.active_room_id = normalizedRoomId;
    }
    this._activeRoomData = resolvedRoom;
    this._setStateValue('activeRoomId', normalizedRoomId);
    this._clearRoomViewRetry();
    this._roomViewLastKey = null;
    this._roomViewHasContent = false;
    this._merchantStockLoading = false;
    this._pendingRoomEntryAcknowledgement = {
      transitionId: transition.id,
      roomId: normalizedRoomId,
      campaignId: Number(this.launchContext?.campaign_id || 0) || 0,
      characterId: Number(
        this.resolveLaunchCharacterRuntimeContext?.().characterId
        || this.launchCharacter?.id
        || this.launchContext?.character_id
        || 0
      ) || null,
      mapId: String(
        this.dungeonData?.map_id
        || this.launchContext?.map_id
        || ''
      ).trim(),
    };

    this.bus.emit('room:changed', {
      roomId: normalizedRoomId,
      roomName: resolvedRoomName,
      room: resolvedRoom,
      sceneImageUrl: resolvedRoom?.image_url ?? null,
      connections: _buildRoomConnections(normalizedRoomId, this.mapVisualState),
      responders: [],
      transition,
      _source: 'shell',
    });
    this.bus.emit('room:transitioned', {
      roomId: normalizedRoomId,
      roomName: resolvedRoomName,
      room: resolvedRoom,
      transition,
      source,
      phase,
      _source: 'shell',
    });

    const resolvedOccupants = Array.isArray(occupants)
      ? occupants
      : this.getVisualOccupants().filter((entry) => String(entry?.room_id || '') === normalizedRoomId && this.isVisualOccupantVisible(entry));
    this._currentOccupants = resolvedOccupants;
    const resolvedActorRoster = this.getActiveRoomActorRoster(normalizedRoomId);
    this.bus.emit('room:occupants-membership-changed', {
      roomId: normalizedRoomId,
      roomName: resolvedRoomName,
      occupants: resolvedOccupants,
      transition,
      _source: 'shell',
    });
    // Compatibility event retained during staged bus-migration.
    this.bus.emit('room:occupants-changed', {
      roomId: normalizedRoomId,
      roomName: resolvedRoomName,
      occupants: resolvedOccupants,
      transition,
      _source: 'shell',
    });
    this.bus.emit('room:actor-roster-changed', {
      roomId: normalizedRoomId,
      roomName: resolvedRoomName,
      actorRoster: {
        schema_version: String(this.mapVisualState?.actor_roster?.schema_version || 'actor-roster-v1'),
        room_id: normalizedRoomId,
        default_filter: String(this.mapVisualState?.actor_roster?.default_filter || 'party'),
        available_filters: Array.isArray(this.mapVisualState?.actor_roster?.available_filters)
          ? this.mapVisualState.actor_roster.available_filters
          : ['all', 'party', 'allied', 'hostile', 'neutral', 'hazard'],
        sort_modes: Array.isArray(this.mapVisualState?.actor_roster?.sort_modes)
          ? this.mapVisualState.actor_roster.sort_modes
          : ['alpha', 'initiative'],
        entries: resolvedActorRoster,
      },
      transition,
      _source: 'shell',
    });

    this._syncActiveRoomEntities(normalizedRoomId);
    if (!loadData) {
      return;
    }
    this._loadChatHistory();
    this._loadRoomView({ force: Boolean(forceView), preserveExisting: Boolean(preserveView) });
    this.prefetchConnectedRoomContext();
  }

  /**
   * If the dungeon payload signals an ungenerated room, auto-trigger location
   * generation so the player lands in the correct room after dungeon-switching
   * to a quest destination that hasn't been created yet.
   * @private
   */
  async _triggerPendingRoomGeneration() {
    return this.roomGenerationCoordinator?.triggerPendingRoomGeneration();
  }


  _initApiHandlers() {
    // Tab change → trigger appropriate API load
    this._tabChangedHandler = (e) => this._onTabChanged(e.detail?.tabId ?? '');
    window.addEventListener('dungeoncrawler:game-shell-tab-changed', this._tabChangedHandler);

    // Chat submit → POST to server, emit response lines
    this.bus.on('user:chat-submitted', (data) => this._handleChatSubmit(data));

    this.fetchBridge?.destroy?.();
    this.fetchBridge = new GameShellFetchBridge(this);
    this.fetchBridge.register();

    // Session message submit from ChatPanel non-room view
    this.bus.on('user:session-message-submitted', (d) => this._postSessionViewMessage(d));

    this.bus.on('user:target-pick-requested', (data) => this._beginTargetPickSession(data || {}));

    // Bridge: entity:select-request (from stateManager shim / HexInputHandler) →
    // resolve entity from ECS and emit entity:selected for CharacterPanel etc.
    this.bus.on('entity:select-request', ({ id } = {}) => {
      if (!id) {
        this._setStateValue('selectedEntity', null);
        this.bus.emit('entity:deselected');
        return;
      }
      const entity = this.entityManager?.getEntity(id) ?? null;
      if (entity) {
        this._setStateValue('selectedEntity', entity);
        this.syncLaunchCharacterRuntimeFromEntity(entity);
        this.bus.emit('entity:selected', { entity });
      } else {
        console.warn('[GameShell] entity:select-request — entity not found in ECS:', id);
      }
    });

    this.bus.on('entity:deselected', () => {
      this._setStateValue('selectedEntity', null);
      if (this._targetPickSession && this._targetPickSession.sourceSurface !== 'map-click') {
        this._clearTargetPickSession('selection-cleared');
      }
    });

    // CharacterPanel requests inventory refresh from API
    this.bus.on('character:inventory-refresh-requested', (ctx) => {
      if (ctx) void this.refreshCharacterInventoryFromApi(ctx);
      if (this.activeGameShellTab === 'merchant') {
        void this._loadMerchantStock(true);
      }
    });
    this.bus.on('character:runtime-state-refresh-requested', (payload = {}) => {
      const context = (payload && typeof payload.context === 'object') ? payload.context : payload;
      const reason = String(payload?.reason || 'character-panel-runtime-refresh').trim() || 'character-panel-runtime-refresh';
      void this.refreshCharacterRuntimeState(context, reason);
    });

    // Character/quest UI requests canonical quest journal refresh.
    this.bus.on('quest:refresh-requested', (ctx) => {
      void this.refreshQuestJournalFromApi(ctx || {});
    });

    this.bus.on('inventory:changed', (ctx) => {
      if (!ctx?.characterId) {
        return;
      }
      this.currentCharacterInventoryContext = {
        ...(this.currentCharacterInventoryContext || {}),
        ...ctx,
      };
    });

    // Compatibility bridge for any non-shell room:changed producer.
    this.bus.on('room:changed', ({ roomId, roomName, room, occupants, _source, transition } = {}) => {
      if (this._targetPickSession) {
        this._clearTargetPickSession('room-changed');
      }
      if (_source === 'shell' || !roomId) {
        return;
      }
      const transitionId = String(transition?.id || '').trim();
      if (transitionId && transitionId === this._lastExternalRoomTransitionId) {
        return;
      }
      if (transitionId) {
        this._lastExternalRoomTransitionId = transitionId;
      }
      this._emitCanonicalRoomChanged({
        roomId,
        roomName,
        room,
        occupants,
        source: String(transition?.source || _source || 'external-event'),
        phase: 'event-relay',
        transitionId: String(transition?.id || '').trim(),
        forceView: false,
        preserveView: false,
      });
    });

    this.bus.on('combat:state-changed', ({ state, statusRaw } = {}) => {
      const raw = String(statusRaw || '').trim().toLowerCase();
      const normalized = String(state || '').trim().toLowerCase();
      const active = ['active', 'in_progress', 'rolling_initiative'].includes(normalized)
        || ['active', 'setup', 'rolling_initiative', 'paused'].includes(raw);
      this._setStateValue('combatActive', active);
      if (!active) {
        if (this._targetPickSession) {
          this._clearTargetPickSession('combat-inactive');
        }
        this._setStateValue('encounterId', null);
        this._setStateValue('latestEncounterState', null);
        this._setStateValue('serverCombatMode', false);
      }
    });

    this.bus.on('combat:turn-changed', ({ entity } = {}) => {
      if (!this._targetPickSession) {
        return;
      }
      const turnActorRef = this.getEntityInstanceRef(entity);
      const sessionActorRef = String(this._targetPickSession?.actorRef || '').trim();
      if (turnActorRef && sessionActorRef && turnActorRef === sessionActorRef) {
        return;
      }
      this._clearTargetPickSession('turn-changed');
    });

    this.bus.on('game:state-refreshed', ({ phaseSnapshot } = {}) => {
      if (!this._targetPickSession) {
        return;
      }
      const sessionActorRef = String(this._targetPickSession?.actorRef || '').trim();
      const snapshotActorRef = String(
        phaseSnapshot?.actionContract?.actor_id
        || phaseSnapshot?.turn?.entity
        || ''
      ).trim();
      if (!snapshotActorRef || (sessionActorRef && sessionActorRef === snapshotActorRef)) {
        return;
      }
      this._clearTargetPickSession('state-refreshed');
    });

    // Load chat history and room view on startup
    this._loadChatHistory();
    this._loadRoomView();
  }

  /**
   * Handle top-level game-shell tab activation.
   * @param {string} tabId
   * @private
   */
  _onTabChanged(tabId) {
    const prevTab = this.activeGameShellTab;
    this.activeGameShellTab = tabId;
    console.log('[GameShell] _onTabChanged', { tabId, prevTab });
    if (tabId === 'map') this._refreshMapCanvasViewport();
    if (tabId === 'view')      this._loadRoomView({ preserveExisting: this._roomViewHasContent });
    if (tabId === 'merchant')  this._loadMerchantStock();
    if (tabId !== 'view' && prevTab === 'view') this._clearRoomViewRetry();
    if (tabId === 'chat' && !this._chatHistoryLoaded) this._loadChatHistory();
    if (tabId === 'party' || tabId === 'character') {
      const charId = this.launchCharacter?.id ?? this.launchContext?.character_id ?? null;
      console.log('[GameShell] party tab → sheet-requested', { charId });
      if (charId) {
        this.bus.emit('character:sheet-requested', { characterId: charId });
        const now = Date.now();
        if ((now - this._lastRosterRuntimeRefreshAt) > 1000) {
          this._lastRosterRuntimeRefreshAt = now;
          void this.loadCharacterFromApi(charId);
        }
      }
      const questRefreshContext = typeof this.panels?.character?.buildQuestRefreshContext === 'function'
        ? this.panels.character.buildQuestRefreshContext('game-shell-party-tab-open')
        : {};
      void this.refreshQuestJournalFromApi(questRefreshContext);
    }
    if (tabId === 'settings') {
      if (!this._campaignSettingsLoaded) {
        void this._loadCampaignSettings();
      } else if (this._campaignSettingsPayload) {
        this._renderCampaignSettings(this._campaignSettingsPayload);
      }
    }
  }

  /**
   * Load campaign settings payload for the settings tab.
   */
  async _loadCampaignSettings(force = false) {
    return this.campaignSettingsCoordinator?.loadCampaignSettings(force);
  }

  /**
   * Render settings payload into the settings tab.
   */
  _renderCampaignSettings(payload) {
    this.campaignSettingsCoordinator?.renderCampaignSettings(payload);
  }

  /**
   * Persist user mode preference in campaign settings.
   */
  async _setCampaignMode(mode) {
    return this.campaignSettingsCoordinator?.setCampaignMode(mode);
  }

  _normalizeCampaignAccess(input = {}) {
    if (this.campaignSettingsCoordinator?.normalizeCampaignAccess) {
      return this.campaignSettingsCoordinator.normalizeCampaignAccess(input);
    }
    const access = input && typeof input === 'object' ? input : {};
    const canUseGmMode = access.can_use_gm_mode === true;
    const canUsePlayerMode = access.can_use_player_mode !== false;
    const defaultMode = String(access.default_mode || (canUseGmMode ? 'gm' : 'player')).trim().toLowerCase() === 'gm'
      ? 'gm'
      : 'player';
    let currentMode = String(access.current_mode || defaultMode).trim().toLowerCase() === 'gm'
      ? 'gm'
      : 'player';
    if (currentMode === 'gm' && !canUseGmMode) {
      currentMode = 'player';
    }
    if (currentMode === 'player' && !canUsePlayerMode && canUseGmMode) {
      currentMode = 'gm';
    }
    return {
      campaign_id: Number(access.campaign_id || this.launchContext?.campaign_id || 0) || 0,
      membership_role: String(access.membership_role || '').trim().toLowerCase() || 'player',
      membership_status: String(access.membership_status || '').trim().toLowerCase() || 'active',
      can_use_player_mode: canUsePlayerMode,
      can_use_gm_mode: canUseGmMode,
      default_mode: defaultMode,
      current_mode: currentMode,
      playable_principals: Array.isArray(access.playable_principals) ? access.playable_principals : [],
      gm_principals: Array.isArray(access.gm_principals) ? access.gm_principals : [],
    };
  }

  _applyCampaignModeGates() {
    this.campaignSettingsCoordinator?.applyCampaignModeGates();
  }

  /**
   * Persist campaign member role assignment.
   */
  async _updateCampaignMemberRole(memberUid, role) {
    return this.campaignSettingsCoordinator?.updateCampaignMemberRole(memberUid, role);
  }

  /**
   * Ensure canvas geometry is refreshed when the map panel becomes visible.
   */
  _refreshMapCanvasViewport() {
    const canvas = this.canvas?.app;
    if (!canvas || typeof canvas.resizeToContainer !== 'function') {
      return;
    }

    const applyResize = () => {
      canvas.resizeToContainer();
    };

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(applyResize);
      });
      return;
    }
    applyResize();
  }

  _handleRoomViewRefreshIntent(options = {}, eventName = 'room:view-refresh-intent') {
    this.fetchBridge?.handleRoomViewRefreshIntent(options, eventName);
  }

  /**
   * Load chat history for the active room and emit chat:history-loaded.
   * @private
   */
  async _loadChatHistory() {
    return this.fetchBridge?.loadChatHistory();
  }

  _isPlainObject(value) {
    return _isPlainObject(value);
  }

  _mergeRoomMetadata(visualRoom, apiRoom, roomId) {
    return _mergeRoomMetadata(visualRoom, apiRoom, roomId);
  }

  queueRoomEntryAcknowledgement({ campaignId = null, roomId = '', characterId = null, mapId = '' } = {}) {
    const pending = this._pendingRoomEntryAcknowledgement;
    const normalizedRoomId = String(roomId || '').trim();
    const normalizedCampaignId = Number(campaignId || 0) || 0;
    if (!pending || !normalizedCampaignId || !normalizedRoomId) {
      return;
    }
    if (pending.campaignId !== normalizedCampaignId || pending.roomId !== normalizedRoomId) {
      return;
    }
    if (String(this.activeRoomId || '').trim() !== normalizedRoomId) {
      return;
    }

    const requestKey = [
      pending.transitionId,
      pending.roomId,
      Number(characterId || pending.characterId || 0) || 0,
    ].join(':');
    if (this._completedRoomEntryAcknowledgements.has(requestKey) || this._roomEntryAcknowledgementInflightKey === requestKey) {
      return;
    }

    this._roomEntryAcknowledgementInflightKey = requestKey;
    void this.requestRoomEntryAcknowledgement({
      requestKey,
      campaignId: normalizedCampaignId,
      roomId: normalizedRoomId,
      characterId: Number(characterId || pending.characterId || 0) || null,
      mapId: String(mapId || pending.mapId || '').trim(),
      transitionId: pending.transitionId,
    });
  }

  async requestRoomEntryAcknowledgement({ requestKey = '', campaignId = 0, roomId = '', characterId = null, mapId = '', transitionId = '' } = {}) {
    try {
      const response = await fetch(
        `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/chat/entry-acknowledgement`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            character_id: characterId,
            map_id: mapId,
            transition_id: transitionId,
          }),
        },
      );
      if (!response.ok) {
        return;
      }
      const result = await response.json().catch(() => ({}));
      if (!result?.success) {
        return;
      }
      if (String(this.activeRoomId || '').trim() !== roomId) {
        return;
      }
      this._completedRoomEntryAcknowledgements.add(requestKey);
      if (!result?.data?.acknowledged) {
        return;
      }
      void this._loadChatHistory();
    } catch (_) {
      // Entry acknowledgement is best-effort and must never block room load.
    } finally {
      if (this._roomEntryAcknowledgementInflightKey === requestKey) {
        this._roomEntryAcknowledgementInflightKey = '';
      }
    }
  }

  /**
   * POST a player chat message and emit the server's response lines.
   * @param {{ message: string, channel: string }} data
   * @private
   */
  async _handleChatSubmit({ message = '', channel = 'room' } = {}) {
    const campaignId = this.launchContext?.campaign_id;
    const roomId     = this.activeRoomId;
    const charId     = this.launchCharacter?.id ?? this.launchContext?.character_id;
    const speaker    = this.launchCharacter?.name ?? 'Player';
    if (!campaignId || !roomId || !message.trim()) return;
    const requestId = `chat-submit-${Date.now()}`;

    this.bus.emit('game:backend-request-start', {
      requestId,
      label: 'Waiting for narrator response...',
      source: 'chat-submit',
    });

    try {
      const resp = await fetch(
        `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/chat`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            speaker,
            message: message.trim(),
            type: 'player',
            character_id: charId ?? null,
            channel,
            stream: false,
          }),
        },
      );
      if (!resp.ok) {
        this.bus.emit('game:server-unavailable', { message: `Server error (${resp.status})` });
        return;
      }
      const result = await resp.json().catch(() => ({}));
      if (!result?.success) return;

      this.bus.emit('game:server-available');

      // Render the server-authoritative player message (no local fabrication).
      const primary = result.data?.message;
      if (primary?.speaker && primary?.message) {
        this.bus.emit('chat:message-received', {
          line: {
            speaker: primary.speaker,
            message: primary.message,
            type: primary.type ?? 'player',
            channel: primary.channel ?? channel,
          },
          channel: primary.channel ?? channel,
        });
      }

      // Render any additional server-provided lines (GM narration, NPC replies, system)
      (result.data?.messages ?? []).forEach((msg) => {
        if (!msg?.speaker || !msg?.message) return;
        this.bus.emit('chat:message-received', {
          line: {
            speaker: msg.speaker,
            message: msg.message,
            type: msg.type ?? 'npc',
            channel: msg.channel ?? channel,
          },
          channel: msg.channel ?? channel,
        });
      });

      // Relay any quest progress updates — merge into local summary and emit full summary
      const questUpdates = Array.isArray(result.data?.quest_updates) ? result.data.quest_updates : [];
      if (questUpdates.length > 0) {
        await this.applyQuestUpdates(questUpdates);
        const launchCharacterId = this.resolveLaunchCharacterStateId();
        if (launchCharacterId > 0) {
          await this.loadCharacterFromApi(launchCharacterId);
        }
      }

      // Notify ChatPanel the turn is complete
      this.bus.emit('chat:turn-status-changed', { status: 'idle' });

      // Refresh view tab if open (AI may have generated a new scene image)
      this._loadRoomView({ force: true, preserveExisting: true });
    } catch (_) {
      this.bus.emit('game:server-unavailable', { message: 'Server unreachable. Please check your connection.' });
    } finally {
      this.bus.emit('game:backend-request-end', { requestId, source: 'chat-submit' });
    }
  }

  /**
   * Handle session view message post from ChatPanel.
   * Routes to the appropriate API based on active session view.
   * @private
   */
  async _postSessionViewMessage({ characterName, message, characterId } = {}) {
    const campaignId = this.launchContext?.campaign_id;
    if (!campaignId || !message?.trim()) return;
    const speaker = characterName ?? this.launchCharacter?.name ?? 'Player';
    // Optimistic echo
    this.bus.emit('chat:message-received', {
      line: { speaker, message: message.trim(), type: 'player', channel: 'session' },
      channel: 'session',
    });
    // Full implementation deferred to full ChatPanel session-view sprint;
    // for now emit turn-status-changed to unblock the UI
    this.bus.emit('chat:turn-status-changed', { status: 'idle' });
  }

  async fetchSessionViewData(view, options = {}) {
    const chatPanel = this.panels?.chat || null;
    if (!chatPanel || typeof chatPanel.fetchSessionViewData !== 'function') {
      throw new Error('ChatPanel session view data adapter unavailable.');
    }
    return chatPanel.fetchSessionViewData(view, options);
  }

  async fetchRoomChatHistoryForContext(context = {}, options = {}) {
    const chatPanel = this.panels?.chat || null;
    if (chatPanel && typeof chatPanel.fetchRoomChatHistoryForContext === 'function') {
      return chatPanel.fetchRoomChatHistoryForContext(context, options);
    }

    const campaignId = Number(context?.campaignId || 0);
    const roomId = String(context?.roomId || '').trim();
    const characterId = Number(context?.characterId || 0) || null;
    const mapId = String(context?.mapId || '').trim();
    const channelKey = String(options?.channelKey || 'room').trim() || 'room';
    if (!campaignId || !roomId) {
      return null;
    }

    let url = `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/chat?channel=${encodeURIComponent(channelKey)}`;
    if (mapId) {
      url += `&map_id=${encodeURIComponent(mapId)}`;
    }
    if (characterId) {
      url += `&character_id=${characterId}`;
    }

    const response = await fetch(url, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });
    if (response.status === 403) {
      return null;
    }
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
  }

  async fetchRoomViewPayload(campaignId, roomId, options = {}) {
    const numericCampaignId = Number(campaignId || 0);
    const normalizedRoomId = String(roomId || '').trim();
    if (!numericCampaignId || !normalizedRoomId) {
      return null;
    }

    const response = await fetch(
      `/api/campaign/${encodeURIComponent(numericCampaignId)}/room/${encodeURIComponent(normalizedRoomId)}/view-image`,
      {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      },
    );
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result?.success || !result?.data) {
      throw new Error(result?.error || result?.message || `Room view unavailable (${response.status})`);
    }
    return result.data;
  }

  /**
   * Fetch room view images and emit room:changed with sceneImageUrl.
   * @private
   */
  async _loadRoomView(options = {}) {
    return this.fetchBridge?.loadRoomView(options);
  }

  _scheduleRoomViewRetry(roomId, viewKey) {
    this.fetchBridge?.scheduleRoomViewRetry(roomId, viewKey);
  }

  _clearRoomViewRetry() {
    this.fetchBridge?.clearRoomViewRetry();
  }

  /**
   * Fetch merchant context metadata for all merchant occupants in the room and
   * emit decoration + merchant-specific refresh events.
   * @private
   */
  async _loadMerchantStock() {
    return this.fetchBridge?.loadMerchantStock();
  }

  async __loadMerchantStockImpl() {
    return this.fetchBridge?.loadMerchantStockImpl();
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
      const identity = entity?.getComponent?.('IdentityComponent') || null;
      const combat = entity?.getComponent?.('CombatComponent') || null;
      const actions = entity?.getComponent?.('ActionsComponent') || null;
      const movement = entity?.getComponent?.('MovementComponent') || null;
      const launchPlayer = this.findLaunchPlayerEntity?.() || null;
      bus.emit('combat:turn-changed', {
        entity,
        turnIndex,
        totalTurns,
        name: identity?.name || entity?.name || entity?.actorName || 'Unknown combatant',
        actions,
        movement,
        hasReaction: typeof actions?.hasReactionAvailable === 'function'
          ? actions.hasReactionAvailable()
          : Boolean(actions?.hasReaction),
        team: combat?.team || null,
        isPlayersTurn: Boolean(entity && launchPlayer && entity.id === launchPlayer.id),
      });
    });
    this.turnManagementSystem.onRoundChange?.((roundNumber) => {
      bus.emit('combat:round-changed', { roundNumber });
    });
    this.turnManagementSystem.onCombatStateChange?.((state) => {
      bus.emit('combat:state-changed', {
        state,
        statusRaw: this.turnManagementSystem.getEncounterStatus?.() || null,
        roundNumber: this.turnManagementSystem.currentRound || 0,
        turnIndex: this.turnManagementSystem.currentTurnIndex,
        totalTurns: Array.isArray(this.turnManagementSystem.initiativeOrder) ? this.turnManagementSystem.initiativeOrder.length : 0,
      });
    });
    this.turnManagementSystem.onOrderChange?.((order = []) => {
      bus.emit('combat:order-changed', { order });
    });
    this.entityManager.addSystem(this.turnManagementSystem);
  }

  /**
   * Hydrate ECS entities for the active room so the V2 canvas can render
   * authored room artifacts and occupants on the map tab.
   * @param {string|null} roomId
   * @private
   */
  _syncActiveRoomEntities(roomId = null) {
    if (!this.entityManager || !this.bus) {
      return;
    }

    const activeRoomId = String(roomId || this.activeRoomId || '').trim();
    if (!activeRoomId) {
      this.entityManager.clear();
      this.bus.emit('room:entities-changed', { roomId: null, entities: [] });
      return;
    }

    const blueprints = _buildRenderableEntityBlueprints(
      this.dungeonData,
      activeRoomId,
      this.launchCharacter,
      this.mapVisualState,
    );
    _preloadSpriteUrls(
      this.spriteService,
      blueprints,
      this.getPresentationObjectDefinitions(),
      this.launchCharacter,
    );

    this.entityManager.clear();

    blueprints.forEach((blueprint) => {
      const entity = this.entityManager.createEntity();
      entity.instanceId = blueprint.instanceId || null;
      entity.placement = {
        room_id: blueprint.roomId,
        hex: { q: blueprint.q, r: blueprint.r },
      };
      entity.dcEntityRef = blueprint.entityRef || blueprint.instanceId || blueprint.contentId || null;
      entity.dcContentId = blueprint.contentId || null;
      entity.dcCharacterId = blueprint.characterId || null;
      entity.dcStatePayload = blueprint.state || null;
      entity.dcEntityPayload = blueprint.source || null;
      entity.state = blueprint.state || null;
      // Carried directly on the entity (not a formal ECS component) so the
      // map token renderer can show the unconscious/dead badge and hover
      // condition list without needing a dedicated component + system for
      // what is purely presentational, read-only combat metadata.
      entity.dcIsDefeated = Boolean(blueprint.isDefeated);
      entity.dcConditions = Array.isArray(blueprint.conditions) ? blueprint.conditions : [];

      const position = new PositionComponent(blueprint.q, blueprint.r);
      position.roomId = blueprint.roomId;
      entity.addComponent('PositionComponent', position);
      entity.addComponent(
        'IdentityComponent',
        new IdentityComponent(blueprint.name, blueprint.entityType, blueprint.description || ''),
      );

      const render = new RenderComponent(blueprint.render.spriteKey || null);
      render.scale = Number.isFinite(blueprint.render.scale) ? blueprint.render.scale : 1;
      render.orientation = blueprint.render.orientation || 'n';
      render.objectCategory = blueprint.render.objectCategory || null;
      render.objectColor = blueprint.render.objectColor || null;
      render.visible = blueprint.hidden !== true;
      entity.addComponent('RenderComponent', render);

      const stats = new StatsComponent({
        maxHp: blueprint.stats.maxHp,
        currentHp: blueprint.stats.currentHp,
        ac: blueprint.stats.ac,
        perception: blueprint.stats.perception,
        speed: blueprint.stats.speed,
      });
      entity.addComponent('StatsComponent', stats);

      if (blueprint.combatCapable) {
        entity.addComponent('MovementComponent', new MovementComponent(blueprint.stats.speed));
        entity.addComponent('ActionsComponent', new ActionsComponent(blueprint.actionsPerTurn));
        const combatComponent = new CombatComponent({
          team: blueprint.team,
          initiativeBonus: blueprint.initiativeBonus,
          attackBonus: blueprint.attackBonus,
        });
        combatComponent.isDefeated = Boolean(blueprint.isDefeated);
        entity.addComponent('CombatComponent', combatComponent);
      }
    });

    this.entityManager.invalidateQueryCache();
    this.bus.emit('entity:deselected');
    this.bus.emit('room:entities-changed', {
      roomId: activeRoomId,
      entities: this.entityManager.getEntitiesWith('PositionComponent', 'RenderComponent'),
    });

    const objectDefinitions = this.getPresentationObjectDefinitions();
    const spriteDungeonData = {
      ...(this.dungeonData && typeof this.dungeonData === 'object' ? this.dungeonData : {}),
      object_definitions: objectDefinitions,
    };
    const campaignId = this.resolveCampaignId();
    void this.spriteService?.resolveAndApply?.(
      this.entityManager,
      this.renderSystem,
      spriteDungeonData,
      activeRoomId,
      campaignId,
    ).finally(() => {
      this.bus?.emit('room:entities-changed', {
        roomId: activeRoomId,
        entities: this.entityManager?.getEntitiesWith?.('PositionComponent', 'RenderComponent') || [],
      });
    });
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

    const tokens = new HexTokenRenderer(hexCanvas, this.bus, {
      canDragEntity: (entity) => this.canDragEntityOnMap(entity),
      onDragStart: (entity) => {
        this.selectEntity(entity, { suppressCoordinatorResync: true });
        this.showMovementHighlightBandsForEntity(entity);
      },
      onDragEnd: () => this.clearMovementHighlightBands(),
      onTokenSelected: (entity) => {
        if (this._targetPickSession) {
          const pos = entity?.getComponent?.('PositionComponent') || null;
          const q = Number(pos?.q);
          const r = Number(pos?.r);
          if (Number.isFinite(q) && Number.isFinite(r)) {
            const consumed = this._handleTargetPickHexClick(Number(q), Number(r), [entity]);
            if (consumed) {
              return;
            }
          }
        }
        this.selectEntity(entity, {
          suppressCoordinatorResync: Boolean(this._targetPickSession),
        });
      },
      onDropEntity: (payload) => this.handleMapActorDrop(payload),
    });
    tokens.init();

    const fog = new HexFogOfWar(hexCanvas, this.bus);
    fog.init();

    const input = new HexInputHandler(hexCanvas, this.bus);
    input.init();

    this.canvas = { app: hexCanvas, tokens, fog, input };
  }

  _bindMapControls() {
    this._domUnsubs.forEach((fn) => fn());
    this._domUnsubs = [];

    const bindClick = (id, handler) => {
      const element = document.getElementById(id);
      if (!element) {
        return;
      }
      element.addEventListener('click', handler);
      this._domUnsubs.push(() => element.removeEventListener('click', handler));
    };

    bindClick('toggle-coordinates', () => {
      const enabled = !Boolean(this._getStateValue('showCoordinates'));
      this._setStateValue('showCoordinates', enabled);
      this.bus?.emit('canvas:coordinates-toggled', { enabled });
    });

    bindClick('toggle-grid', () => {
      const enabled = !Boolean(this._getStateValue('showGrid'));
      this._setStateValue('showGrid', enabled);
      this.bus?.emit('canvas:grid-toggled', { enabled });
    });

    bindClick('toggle-fog', () => {
      const enabled = !Boolean(this._getStateValue('showFog'));
      this._setStateValue('showFog', enabled);
      this.bus?.emit('canvas:fog-toggled', { enabled });
    });

    bindClick('reset-view', () => {
      this.bus?.emit('canvas:reset-view');
    });

    const onKeydown = (event) => {
      if (event.key !== 'Escape') {
        return;
      }
      if (this._targetPickSession) {
        this._clearTargetPickSession('escape');
      }
    };
    window.addEventListener('keydown', onKeydown);
    this._domUnsubs.push(() => window.removeEventListener('keydown', onKeydown));
  }

  _bindInteractionEvents() {
    this._busUnsubs.forEach((fn) => fn());
    this._busUnsubs = [];

    this._busUnsubs.push(
      this.bus.on('runtime:state-committed', ({ snapshot } = {}) => {
        this._syncEncounterPlacementsFromRuntimeSnapshot(snapshot);
      }),

      this.bus.on('hex:hovered', ({ q, r } = {}) => {
        this._setStateValue('hoveredHex', Number.isFinite(Number(q)) && Number.isFinite(Number(r)) ? { q: Number(q), r: Number(r) } : null);
        this.bus.emit('hex:details', this.getHexDetail(q, r));
      }),

      this.bus.on('hex:out', () => {
        this._setStateValue('hoveredHex', null);
      }),

      this.bus.on('hex:clicked', ({ q, r, button = 0, clientX = null, clientY = null, entities = [] } = {}) => {
        if (!Number.isFinite(Number(q)) || !Number.isFinite(Number(r))) {
          return;
        }

        if (this._targetPickSession && Number(button) !== 2) {
          const consumed = this._handleTargetPickHexClick(Number(q), Number(r), entities);
          if (consumed) {
            return;
          }
        }

        const hexEntities = Array.isArray(entities) && entities.length ? entities : this.getEntitiesAtHex(q, r);
        if (Number(button) === 2) {
          this.setSelectedHex(q, r, { emitDetails: false });
          this._showHexContextMenuForHex(Number(q), Number(r), {
            clientX: Number(clientX),
            clientY: Number(clientY),
            hasOccupants: hexEntities.length > 0,
          });
          return;
        }
        this._hideHexContextMenu();

        this.setSelectedHex(q, r, { emitDetails: false });
        if (this.tryTransitionAtHex(q, r)) {
          return;
        }

        if (hexEntities.length === 1) {
          this.selectEntity(hexEntities[0]);
        } else if (hexEntities.length > 1) {
          const selectedEntityId = this._getStateValue('selectedEntity')?.id || null;
          const occupants = hexEntities.map((entity) => ({
            entityId: entity.id,
            name: _getEntityDisplayName(entity),
            typeLabel: String(entity.getComponent?.('IdentityComponent')?.entityType || entity?.dcEntityType || 'entity'),
            teamLabel: entity.getComponent?.('CombatComponent')?.team || null,
            canSelect: true,
            isSelected: selectedEntityId === entity.id,
          }));
          this.bus.emit('hex:contents', {
            q: Number(q),
            r: Number(r),
            occupants,
            onChoose: (entityId) => this.selectEntity(entityId),
          });
        } else {
          this.deselectEntity();
        }

        this.bus.emit('hex:details', this.getHexDetail(q, r));
      }),
    );
  }

  activateGameShellTab(tabId = '') {
    const requestedTab = String(tabId || '').trim();
    if (!requestedTab) {
      return;
    }
    const shell = this.container?.closest?.('[data-game-shell]')
      || this.container?.querySelector?.('[data-game-shell]')
      || (typeof document !== 'undefined' ? document.querySelector('[data-game-shell]') : null)
      || null;
    if (!(shell instanceof HTMLElement)) {
      return;
    }
    shell.dispatchEvent(new CustomEvent('dungeoncrawler:activate-tab', {
      detail: { tabId: requestedTab },
    }));
  }

  _cloneActionButtonForTargetPick(button) {
    return this.targetPickController?.cloneActionButtonForTargetPick(button);
  }

  _normalizeTargetPickKindsForAction(actionKey = '', button = null) {
    return this.targetPickController?.normalizeTargetPickKindsForAction(actionKey, button);
  }

  _setTargetPickOverlay(active = false, promptLabel = 'Pick target') {
    this.targetPickController?.setTargetPickOverlay(active, promptLabel);
  }

  _clearTargetPickSession(reason = 'cleared') {
    this.targetPickController?.clearTargetPickSession(reason);
  }

  _beginTargetPickSession({ actionKey = '', button = null, promptLabel = '' } = {}) {
    this.targetPickController?.beginTargetPickSession({ actionKey, button, promptLabel });
  }

  _resolveTargetPickActorRef(button = null) {
    return this.targetPickController?.resolveTargetPickActorRef(button);
  }

  _resolveEntityByInstanceRef(actorRef = '') {
    return this.targetPickController?.resolveEntityByInstanceRef(actorRef);
  }

  _isHostileEntityTarget(entity, actorEntity) {
    return this.targetPickController?.isHostileEntityTarget(entity, actorEntity);
  }

  _isAllyEntityTarget(entity, actorEntity) {
    return this.targetPickController?.isAllyEntityTarget(entity, actorEntity);
  }

  _resolvePrimaryHexEntity(q, r, provided = []) {
    return this.targetPickController?.resolvePrimaryHexEntity(q, r, provided);
  }

  _handleTargetPickHexClick(q, r, providedEntities = []) {
    return this.targetPickController?.handleTargetPickHexClick(q, r, providedEntities);
  }

  _appendTargetPickSelection(session, selection) {
    return this.targetPickController?.appendTargetPickSelection(session, selection);
  }

  _applyLegacySelectionDataset(button, selection) {
    this.targetPickController?.applyLegacySelectionDataset(button, selection);
  }

  /**
   * Create game systems and wire them to the bus.
   * Phase 4–8 fill these in; stubs safe for now.
   * @private
   */
  _initSystems() {
    const stateManager = this._buildStateManagerShim();
    this.systems.navigation = new NavigationSystem(this, this.bus);
    this.systems.navigation.init(this.dungeonData, stateManager);

    this.systems.encounter = new EncounterSystem(this, this.bus);
    this.systems.encounter.init(this.dungeonData, stateManager);

    this.systems.automation = new PlayerAutomation(this, this.bus);
    this.systems.automation.init(this.dungeonData, stateManager);

    this.systems.quest = new QuestSystem(this, this.bus);
    this.systems.quest.init();
  }

  /**
   * Build a lightweight shim that satisfies the `stateManager.hexmap` interface
   * used by ported panel methods. Returns an object with the most-used accessors.
   * Missing methods degrade gracefully via optional chaining at call sites.
   * @private
   */
  _buildHexmapShim() {
    if (this._hexmapShim) {
      return this._hexmapShim;
    }

    const shell = this;
    const resolveRuntimeCharacterId = () => Number(
      shell.launchCharacter?.id
      || shell.launchContext?.character_id
      || 0
    ) || null;
    const resolveRuntimeInstanceId = () => {
      const explicitInstanceId = String(
        shell.launchCharacter?.instanceId
        || shell.launchCharacter?.instance_id
        || ''
      ).trim();
      if (explicitInstanceId) {
        return explicitInstanceId;
      }

      const campaignId = Number(shell.launchContext?.campaign_id || 0) || null;
      const canonicalCharacterId = Number(
        shell.launchCharacter?.sheet_character_id
        || shell.launchCharacter?.character_id
        || shell.launchContext?.character_id
        || 0
      ) || null;
      return campaignId && canonicalCharacterId ? `pc-${campaignId}-${canonicalCharacterId}` : null;
    };
    this._hexmapShim = {
      // Core resolution
      resolveCampaignId:   () => shell.resolveCampaignId(),
      resolveActiveRoomId: () => shell.resolveActiveRoomId(),
      resolveLaunchCharacterStateId: resolveRuntimeCharacterId,
      resolveLaunchCharacterRuntimeContext: () => shell.resolveLaunchCharacterRuntimeContext(),
      findLaunchPlayerEntity: () => shell.findLaunchPlayerEntity(),
      selectEntity: (entityOrId) => shell.selectEntity(entityOrId),
      deselectEntity: () => shell.deselectEntity(),
      setActiveRoom: (roomId) => shell.setActiveRoom(roomId),
      updateLaunchLocationContext: (roomId, q, r) => shell.updateLaunchLocationContext(roomId, q, r),
      persistLaunchLocationContext: (roomId, q, r, entityRef = null) => shell.persistLaunchLocationContext(roomId, q, r, entityRef),
      loadCharacterFromApi: (characterId) => shell.loadCharacterFromApi(characterId),
      syncLaunchCharacterRuntimeFromEntity: (entity) => shell.syncLaunchCharacterRuntimeFromEntity(entity),
      // Data refs (live values via getters for freshness)
      get dungeonData()    { return shell.dungeonData; },
      get launchContext()  { return shell.launchContext; },
      get characterData()  { return shell.launchCharacter; },
      get launchCharacter(){ return shell.launchCharacter; },
      get entityManager()  { return shell.entityManager; },
      get movementSystem() { return shell.movementSystem; },
      get combatSystem()   { return shell.combatSystem; },
      get turnManagementSystem() { return shell.turnManagementSystem; },
      get gameCoordinator() { return shell.gameCoordinator; },
      get bus() { return shell.bus; },
      // Occupant queries
      hasVisualOccupants:  () => shell.hasVisualOccupants(),
      getVisualOccupants:  () => shell.getVisualOccupants(),
      getVisualActorRoster: () => shell.getVisualActorRoster(),
      getActiveRoomActorRoster: (roomId = null) => shell.getActiveRoomActorRoster(roomId),
      getVisualRooms:      () => shell.getVisualRooms(),
      getPresentationObjectDefinitions: () => shell.getPresentationObjectDefinitions(),
      getVisualConnections: () => shell.getVisualConnections(),
      parseVisualHexId:    (hexId) => shell.parseVisualHexId(hexId),
      getConnectionRoomId: (connection, side) => shell.getConnectionRoomId(connection, side),
      getConnectionHex:    (connection, side) => shell.getConnectionHex(connection, side),
      getActiveRoomData:   () => shell.getActiveRoomData(),
      getActiveRoomHex:    (q, r) => shell.getActiveRoomHex(q, r),
      getEntitiesAtHex:    (q, r) => shell.getEntitiesAtHex(q, r),
      buildActiveRoomOccupantSummary: () => shell.buildActiveRoomOccupantSummary(),
      isVisualOccupantVisible: (occupant) => shell.isVisualOccupantVisible(occupant),
      getObjectDefinition: (contentId) => shell.getObjectDefinition(contentId),
      getObstacleMobilityAtHex: (q, r) => shell.getObstacleMobilityAtHex(q, r),
      spriteService: shell.spriteService,
      // Entity interaction
      // Navigation / automation stubs
      resolveNavigationCapabilities: (roomId) => shell.resolveNavigationCapabilities(roomId),
      resolveNavigationCapabilityAtHex: (q, r) => shell.resolveNavigationCapabilityAtHex(q, r),
      getPlayerAutomationState:      () => null,
      buildPlayerAutomationProfile:  () => ({ character_id: resolveRuntimeCharacterId() }),
      startPlayerAutomation:         () => {},
      stopPlayerAutomation:          () => {},
      // Combat
      startCombat:         () => shell.systems.encounter?.startCombat?.(),
      endCombat:           () => shell.systems.encounter?.endCombat?.(),
      endTurn:             () => shell.systems.encounter?.endCurrentTurn?.(),
      getEncounterServerState: () => shell.getEncounterServerState(),
      getHostileTargets:   (actor) => shell.getHostileTargets(actor),
      hasLineOfSight:      (fromQ, fromR, toQ, toR) => shell.hasLineOfSight(fromQ, fromR, toQ, toR),
      performCombatAction: (options) => shell.performCombatAction(options),
      applyQuestUpdates: (questUpdates = []) => shell.applyQuestUpdates(questUpdates),
      refreshQuestJournalFromApi: () => shell.refreshQuestJournalFromApi(),
      // Inner stateManager.get used by ActionRailPanel
      stateManager: {
        get: (key) => shell._getStateValue(key),
        set: (key, value) => shell._setStateValue(key, value),
      },
    };

    return this._hexmapShim;
  }

  _buildStateManagerShim() {
    if (this._stateManagerShim) {
      return this._stateManagerShim;
    }

    const shell = this;
    this._stateManagerShim = {
      hexmap: this._buildHexmapShim(),
      get(key) {
        return shell._getStateValue(key);
      },
      set(key, value) {
        return shell._setStateValue(key, value);
      },
    };

    return this._stateManagerShim;
  }

  _getStateValue(key) {
    switch (key) {
      case 'activeRoomId':
        return this.resolveActiveRoomId();
      default:
        return this.state?.[key] ?? null;
    }
  }

  _setStateValue(key, value) {
    if (!this.state || typeof this.state !== 'object') {
      this.reset();
    }
    this.state[key] = value;
    return value;
  }

  /**
   * Create panels and wire them to the bus.
   * Phase 4–9 fill these in; stubs safe for now.
   * @private
   */
  _initPanels() {
    const c = this.container;
    const bus = this.bus;
    const panel = (sel) => {
      // querySelector only searches descendants; also check if container itself matches
      const el = c.querySelector(sel) ?? (c.matches?.(sel) ? c : null);
      if (!el) console.warn('[GameShell] panel container NOT FOUND:', sel);
      return el ?? c;
    };
    const stateManager = this._buildStateManagerShim();
    const hexmap = stateManager.hexmap;

    console.log('[GameShell] _initPanels start', { dungeonData: !!this.dungeonData, launchCharacter: !!this.launchCharacter });

    this.panels.merchant   = new MerchantPanel(panel('[data-panel="merchant"]'), bus);
    this.panels.combat     = new CombatPanel(panel('[data-panel="combat"]'), bus);
    this.panels.actionRail = new ActionRailPanel(panel('[data-panel="action-rail"]'), bus);
    this.panels.chat       = new ChatPanel(panel('[data-panel="chat"]'), bus);
    this.panels.quest      = new QuestPanel(panel('[data-panel="quest"]'), bus);
    this.panels.inventory  = new InventoryPanel(panel('[data-panel="inventory"]'), bus);
    this.panels.character  = new CharacterPanel(panel('[data-panel="character"]'), bus);
    this.panels.roomView   = new RoomViewPanel(panel('[data-panel="room-view"]'), bus);
    this.panels.status     = new StatusPanel(panel('[data-panel="status"]'), bus);

    this.panels.merchant.init(this.dungeonData, stateManager, this.panels.inventory);
    this.panels.actionRail.init(this.dungeonData, stateManager);
    this.panels.chat.init(this.dungeonData, stateManager);
    this.panels.inventory.init(this.dungeonData, stateManager);
    this.panels.character.init(this.dungeonData, stateManager);
    // Panels with no-arg init
    this.panels.combat.init(this.dungeonData, stateManager);
    this.panels.quest.init();
    this.panels.roomView.init(this.dungeonData, stateManager);
    this.panels.status.init();

    console.log('[GameShell] _initPanels complete');
  }

  /**
   * Tear down all sub-modules in reverse init order.
   * Called from Drupal.behaviors.hexMapV2.detach.
   */
  destroy() {
    this._clearTargetPickSession('destroy');
    if (this._tabChangedHandler) {
      window.removeEventListener('dungeoncrawler:game-shell-tab-changed', this._tabChangedHandler);
      this._tabChangedHandler = null;
    }
    if (this._authoritativeGameEventsHandler) {
      window.removeEventListener('dungeoncrawler:game-events', this._authoritativeGameEventsHandler);
      this._authoritativeGameEventsHandler = null;
    }

    this.fetchBridge?.destroy?.();
    this.fetchBridge = null;

    Object.values(this.panels).forEach((p) => p?.destroy?.());
    Object.values(this.systems).forEach((s) => s?.destroy?.());
    this._domUnsubs.forEach((fn) => fn());
    this._domUnsubs = [];
    this._busUnsubs.forEach((fn) => fn());
    this._busUnsubs = [];
    this.canvas?.input?.destroy?.();
    this.canvas?.fog?.destroy?.();
    this.canvas?.tokens?.destroy?.();
    this.canvas?.app?.destroy?.();
    this.gameCoordinator?.destroy?.();
    this.gameCoordinator = null;
    this.bus?.destroy?.();
    this._hideHexContextMenu();
    if (typeof this._hexContextMenuDismissHandler === 'function') {
      document.removeEventListener('pointerdown', this._hexContextMenuDismissHandler, true);
      this._hexContextMenuDismissHandler = null;
    }

    this._currentOccupants = [];
    this._merchantRequestTokens.clear();
    if (typeof this._spriteTextureReadyUnsub === 'function') {
      this._spriteTextureReadyUnsub();
      this._spriteTextureReadyUnsub = null;
    }
    if (this._tokenSpriteRefreshHandle) {
      window.clearTimeout(this._tokenSpriteRefreshHandle);
      this._tokenSpriteRefreshHandle = null;
    }
    this.entityManager = null;
    this.canvas = null;
    this.systems = {};
    this.panels = {};
    this.bus = null;
  }

  _ensureHexContextMenu() {
    if (this._hexContextMenuEl && this._hexContextMenuEl.isConnected) {
      return this._hexContextMenuEl;
    }
    const menu = document.createElement('div');
    menu.className = 'hexmap-context-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', 'Hex actions');
    this.container.appendChild(menu);
    this._hexContextMenuEl = menu;

    if (typeof this._hexContextMenuDismissHandler !== 'function') {
      this._hexContextMenuDismissHandler = (event) => {
        const root = this._hexContextMenuEl;
        if (!root || root.hidden) {
          return;
        }
        if (event?.target instanceof Node && root.contains(event.target)) {
          return;
        }
        this._hideHexContextMenu();
      };
      document.addEventListener('pointerdown', this._hexContextMenuDismissHandler, true);
    }

    return menu;
  }

  _hideHexContextMenu() {
    if (!this._hexContextMenuEl) {
      return;
    }
    this._hexContextMenuEl.hidden = true;
    this._hexContextMenuEl.innerHTML = '';
  }

  _resolveHexMovementOption(actionType, q, r) {
    const normalizedAction = String(actionType || '').trim().toLowerCase();
    const snapshot = this.gameCoordinator?.phaseManager?.getSnapshot?.() || {};
    const liveEncounterActive = Number(snapshot?.encounterId || 0) > 0;
    const availableActions = Array.isArray(snapshot?.availableActions) ? snapshot.availableActions : [];
    const actionAvailable = availableActions.includes(normalizedAction);
    const selectedActor = this._getStateValue('selectedEntity') || null;
    const actor = this.canDragEntityOnMap(selectedActor)
      ? selectedActor
      : (this.findLaunchPlayerEntity?.() || null);
    const actorPos = actor?.getComponent?.('PositionComponent') || null;
    const movement = actor?.getComponent?.('MovementComponent') || null;
    const movementSystem = this.movementSystem || null;

    if (!actionAvailable) {
      return { available: false, reason: `${normalizedAction} is not available this turn.`, path: null, pathHexes: null, pathFeet: null };
    }
    if (!actor || !actorPos || !movement || !movementSystem) {
      return { available: false, reason: 'Movement context is unavailable.', path: null, pathHexes: null, pathFeet: null };
    }

    const occupants = this.getEntitiesAtHex(q, r);
    if (Array.isArray(occupants) && occupants.length > 0) {
      return { available: false, reason: 'Destination hex is occupied.', path: null, pathHexes: null, pathFeet: null };
    }

    const movementRemaining = Number(movement?.movementRemaining);
    const movementSpeed = Number(movement?.movementSpeed ?? movement?.movementRemaining);
    const hexCost = Number(movement?.hexMovementCost || 5);
    const movementBudgetFeet = liveEncounterActive ? movementRemaining : movementSpeed;
    const maxHexes = Number.isFinite(movementBudgetFeet) && Number.isFinite(hexCost) && hexCost > 0
      ? Math.floor(Math.max(0, movementBudgetFeet) / hexCost)
      : 0;

    const path = movementSystem.findPath(
      Number(actorPos.q),
      Number(actorPos.r),
      Number(q),
      Number(r),
      Math.max(0, maxHexes),
    );
    const pathHexes = Array.isArray(path) ? Math.max(0, path.length - 1) : null;
    const pathFeet = pathHexes !== null && Number.isFinite(hexCost) ? pathHexes * hexCost : null;

    if (!path || pathHexes === null) {
      return { available: false, reason: 'No reachable path within current movement range.', path, pathHexes, pathFeet };
    }
    if (normalizedAction === 'step' && pathHexes !== 1) {
      return { available: false, reason: 'Step requires a destination exactly 1 hex away.', path, pathHexes, pathFeet };
    }
    if (pathHexes < 1) {
      return { available: false, reason: 'Destination is your current hex.', path, pathHexes, pathFeet };
    }

    return { available: true, reason: '', path, pathHexes, pathFeet };
  }

  async _executeHexMovementAction(actionType, q, r) {
    const encounterSystem = this.systems?.encounter || null;
    if (this.isLiveCombatEncounterActive()) {
      if (!encounterSystem || typeof encounterSystem.executeDirectMovementAction !== 'function') {
        return;
      }
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.targetQ = String(q);
      button.dataset.targetR = String(r);
      button.dataset.actionLabel = actionType === 'step' ? 'Step' : 'Stride';
      this._hideHexContextMenu();
      await encounterSystem.executeDirectMovementAction(actionType, button);
      return;
    }

    const selectedActor = this._getStateValue('selectedEntity') || null;
    const actor = this.canDragEntityOnMap(selectedActor)
      ? selectedActor
      : (this.findLaunchPlayerEntity?.() || null);
    this._hideHexContextMenu();
    if (!this.canDragEntityOnMap(actor)) {
      return;
    }
    const roomId = String(this.resolveActiveRoomId() || '').trim();
    await this.moveEntityWithinRoom(actor, roomId, Number(q), Number(r));
  }

  _showHexContextMenuForHex(q, r, options = {}) {
    const hasOccupants = Boolean(options?.hasOccupants);
    if (hasOccupants) {
      this._hideHexContextMenu();
      return;
    }

    const menu = this._ensureHexContextMenu();
    const stride = this._resolveHexMovementOption('stride', q, r);
    const step = this._resolveHexMovementOption('step', q, r);
    const canShowMovement = stride.available || step.available || !!stride.reason || !!step.reason;
    if (!canShowMovement) {
      this._hideHexContextMenu();
      return;
    }

    const renderButton = (label, actionType, state) => {
      const disabledAttr = state.available ? '' : ' disabled aria-disabled="true"';
      const meta = state.pathHexes !== null
        ? `<span class="hexmap-context-menu__item-meta">${state.pathHexes} hex${state.pathHexes === 1 ? '' : 'es'}${state.pathFeet !== null ? ` (${state.pathFeet} ft)` : ''}</span>`
        : '';
      return `
        <button type="button" class="hexmap-context-menu__item" data-hex-action="${actionType}"${disabledAttr}>
          <span class="hexmap-context-menu__item-label">${label}</span>
          ${meta}
          ${state.available ? '' : `<span class="hexmap-context-menu__item-reason">${state.reason || 'Unavailable'}</span>`}
        </button>
      `;
    };

    menu.innerHTML = `
      <div class="hexmap-context-menu__title">Hex ${q}, ${r}</div>
      ${renderButton('Move here (Stride)', 'stride', stride)}
      ${renderButton('Move carefully (Step)', 'step', step)}
    `;
    menu.hidden = false;

    const rect = this.container.getBoundingClientRect();
    const clientX = Number(options?.clientX);
    const clientY = Number(options?.clientY);
    const menuX = Number.isFinite(clientX) ? clientX - rect.left : 24;
    const menuY = Number.isFinite(clientY) ? clientY - rect.top : 24;
    menu.style.left = `${Math.max(8, menuX)}px`;
    menu.style.top = `${Math.max(8, menuY)}px`;

    menu.querySelectorAll('[data-hex-action]').forEach((node) => {
      node.addEventListener('click', async (event) => {
        const action = String(event.currentTarget?.getAttribute('data-hex-action') || '').trim();
        if (!action) {
          return;
        }
        await this._executeHexMovementAction(action, q, r);
      });
    });
  }

  updateFullscreenViewportMetrics(container = null) {
    const target = container || document.getElementById('hexmap-container');
    if (!target) {
      return null;
    }

    const viewportHeight = Math.max(
      0,
      Math.round(
        window.visualViewport?.height
        || window.innerHeight
        || document.documentElement?.clientHeight
        || 0,
      ),
    );
    const tabs = target.querySelector('.game-shell__tabs');
    const headerHeight = tabs
      ? Math.max(0, Math.round(tabs.getBoundingClientRect().height))
      : 0;
    const bodyHeight = Math.max(0, viewportHeight - headerHeight - 16);

    target.style.setProperty('--dc-fullscreen-height', `${viewportHeight}px`);
    target.style.setProperty('--dc-fullscreen-header-height', `${headerHeight}px`);
    target.style.setProperty('--dc-fullscreen-body-height', `${bodyHeight}px`);
    target.dataset.fullscreenCompact = viewportHeight <= 820 ? 'true' : 'false';

    return {
      viewportHeight,
      headerHeight,
      bodyHeight,
    };
  }

  // --- ported from hexmap.js ---
  clearFullscreenViewportMetrics(container = null) {
    const target = container || document.getElementById('hexmap-container');
    if (!target) {
      return;
    }

    target.style.removeProperty('--dc-fullscreen-height');
    target.style.removeProperty('--dc-fullscreen-header-height');
    target.style.removeProperty('--dc-fullscreen-body-height');
    delete target.dataset.fullscreenCompact;
  }

  // --- ported from hexmap.js ---
  setupFullscreenToggle() {
    const btn = document.getElementById('fullscreen-toggle');
    if (!btn || btn.dataset.bound === 'true') {
      return;
    }
    const tabActions = btn.closest('.game-shell__tab-actions');
    let buildBadge = document.getElementById('ui-build-version-badge');
    if (!buildBadge && tabActions) {
      buildBadge = document.createElement('span');
      buildBadge.id = 'ui-build-version-badge';
      buildBadge.className = 'game-shell__build-version-badge';
      tabActions.appendChild(buildBadge);
    }
    if (buildBadge) {
      buildBadge.textContent = `UI v${HEXMAP_UI_BUILD_VERSION}`;
      buildBadge.setAttribute('title', 'UI build version');
    }

    const updateFullscreenButton = (button, isFullscreen) => {
      const label = button.querySelector('[data-fullscreen-label]');
      const icon = button.querySelector('[data-fullscreen-icon]');
      if (label) {
        label.textContent = isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen';
      }
      if (icon) {
        icon.textContent = isFullscreen ? '⛌' : '⛶';
      }
      button.setAttribute('title', isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen');
      button.setAttribute('aria-pressed', isFullscreen ? 'true' : 'false');
    };

    const syncFullscreenMetrics = () => {
      const container = document.getElementById('hexmap-container');
      if (!container || !container.classList.contains('fullscreen')) {
        return;
      }
      this.updateFullscreenViewportMetrics(container);
    };

    if (!window.__dcFullscreenMetricsBound) {
      window.addEventListener('resize', syncFullscreenMetrics);
      if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncFullscreenMetrics);
        window.visualViewport.addEventListener('scroll', syncFullscreenMetrics);
      }
      window.__dcFullscreenMetricsBound = true;
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
        updateFullscreenButton(btn, false);
        container.classList.remove('fullscreen');
        this.clearFullscreenViewportMetrics(container);
      } else {
        // Enter fullscreen
        this.updateFullscreenViewportMetrics(container);
        container.requestFullscreen().catch(() => {});
        updateFullscreenButton(btn, true);
        container.classList.add('fullscreen');
      }
    });

    // Listen for fullscreen change events (e.g., user presses Esc)
    document.addEventListener('fullscreenchange', () => {
      const btn = document.getElementById('fullscreen-toggle');
      const isFullscreen = document.fullscreenElement !== null;
      if (btn) {
        updateFullscreenButton(btn, isFullscreen);
        const container = document.getElementById('hexmap-container');
        if (container) {
          container.classList.toggle('fullscreen', isFullscreen);
          if (isFullscreen) {
            this.updateFullscreenViewportMetrics(container);
          } else {
            this.clearFullscreenViewportMetrics(container);
          }
        }
      }
    });

    updateFullscreenButton(btn, document.fullscreenElement !== null);
  }

  // --- ported from hexmap.js ---
  resolveCampaignId() {
    return Number(this.launchContext?.campaign_id || 0) || null;
  }

  async loadRuntimeStateBundle(query = {}) {
    // Canonical runtime contract comes from the server. Client must hydrate
    // from this payload, not locally reconstruct game state.
    if (typeof fetch !== 'function') {
      throw new Error('Runtime state API is unavailable in this environment.');
    }

    const params = new URLSearchParams();
    const numericCampaignId = Number(query.campaign_id || query.campaignId || this.resolveCampaignId() || 0);
    const numericCharacterId = Number(query.character_id || query.characterId || this.launchContext?.character_id || 0);
    const roomId = String(query.room_id || query.roomId || '').trim();
    const mapId = String(query.map_id || query.mapId || '').trim();
    const dungeonLevelId = String(query.dungeon_level_id || query.dungeonLevelId || '').trim();
    const nextRoomId = String(query.next_room_id || query.nextRoomId || '').trim();
    const startQ = Number.isFinite(Number(query.start_q ?? query.startQ)) ? Number(query.start_q ?? query.startQ) : 0;
    const startR = Number.isFinite(Number(query.start_r ?? query.startR)) ? Number(query.start_r ?? query.startR) : 0;

    if (!numericCampaignId) {
      throw new Error('Campaign id is required to load runtime state.');
    }

    params.set('campaign_id', String(numericCampaignId));
    if (numericCharacterId > 0) {
      params.set('character_id', String(numericCharacterId));
    }
    if (roomId) {
      params.set('room_id', roomId);
    }
    if (mapId) {
      params.set('map_id', mapId);
    }
    if (dungeonLevelId) {
      params.set('dungeon_level_id', dungeonLevelId);
    }
    if (nextRoomId) {
      params.set('next_room_id', nextRoomId);
    }
    params.set('start_q', String(startQ));
    params.set('start_r', String(startR));

    const requestId = `runtime-state-${numericCampaignId}:${roomId || this.activeRoomId || 'current'}:${Date.now()}`;
    this.bus?.emit?.('game:backend-request-start', {
      requestId,
      label: 'Hydrating runtime state...',
      source: 'runtime-state',
    });
    try {
      const response = await fetch(`/api/map/visual-state?${params.toString()}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload?.success) {
        const error = new Error(payload?.error || 'Unable to load runtime state.');
        error.status = Number(response.status || 0);
        error.code = String(payload?.error || '').trim().toLowerCase();
        error.retryAfter = Number(payload?.retry_after || response.headers.get('Retry-After') || 0) || 0;
        error.payload = payload;
        throw error;
      }

      this.applyRuntimeStateBundle(payload);
      return payload;
    } finally {
      this.bus?.emit?.('game:backend-request-end', { requestId, source: 'runtime-state' });
    }
  }

  applyRuntimeStateBundle(bundle = {}) {
    // Apply authoritative bundle as-is. This is a state projection/update, not
    // a client simulation step.
    const launchContext = bundle?.launch_context && typeof bundle.launch_context === 'object'
      ? bundle.launch_context
      : {};
    const dungeonPayload = bundle?.dungeon_payload && typeof bundle.dungeon_payload === 'object'
      ? bundle.dungeon_payload
      : {};
    const visualState = bundle?.map_visual_state && typeof bundle.map_visual_state === 'object'
      ? bundle.map_visual_state
      : {};
    const launchCharacter = bundle?.launch_character && typeof bundle.launch_character === 'object'
      ? bundle.launch_character
      : {};
    const questSummary = bundle?.quest_summary && typeof bundle.quest_summary === 'object'
      ? normalizeQuestSummaryPayload(bundle.quest_summary)
      : null;

    this.launchContext = {
      ...this.launchContext,
      ...launchContext,
    };
    if (Object.keys(dungeonPayload).length > 0) {
      this.dungeonData = dungeonPayload;
      this._navigationCapabilitiesCache = {
        sourceRef: null,
        sourceLength: 0,
        normalized: [],
        byRoom: new Map(),
      };
    }
    if (Object.keys(visualState).length > 0) {
      this.mapVisualState = visualState;
    }
    if (Object.keys(launchCharacter).length > 0) {
      this.launchCharacter = launchCharacter;
      this.characterData = launchCharacter;
    }
    if (questSummary) {
      this.questSummary = questSummary;
      this.bus?.emit('quest:progress-updated', { questSummary });
    }

    this._syncActiveRoomAuthorityFromRuntimePayload();
    this._chatHistoryLoaded = false;
    this._emitInitialRoomState();
    this._syncActiveRoomEntities(this.activeRoomId);
    this._loadChatHistory();
    this._loadRoomView({ force: true, preserveExisting: true });
    if (Array.isArray(this.dungeonData?.navigation_capabilities)) {
      this.bus?.emit('navigation:capabilities-updated', {
        roomId: this.resolveActiveRoomId?.() || this.activeRoomId || null,
        capabilityCount: this.dungeonData.navigation_capabilities.length,
      });
    }
    if (typeof this.panels?.actionRail?.invalidateActionRail === 'function') {
      this.panels.actionRail.invalidateActionRail(['room', 'navigation', 'character', 'inventory', 'quest', 'header']);
    } else if (typeof this.panels?.actionRail?.queueActionRailRefresh === 'function') {
      this.panels.actionRail.queueActionRailRefresh();
    } else {
      this.panels?.actionRail?.refreshActionRail?.();
    }
    void this.syncCoordinatorStateFromServer(this.resolveActiveRoomId?.() || this.activeRoomId || '');
  }

  async syncCoordinatorStateFromServer(expectedRoomId = '', runtimeContext = {}) {
    // Keep client coordinator aligned with authoritative server state after any
    // bundle swap to avoid local drift in phase/version snapshots.
    if (!this.gameCoordinator?.api?.getState || !this.gameCoordinator?.applyAuthoritativeUpdate) {
      return false;
    }

    try {
      const fallbackRuntimeContext = this.resolveLaunchCharacterRuntimeContext?.() || {};
      const actorRef = String(
        runtimeContext?.actor
        || fallbackRuntimeContext?.instanceId
        || ''
      ).trim();
      const characterId = Number(
        runtimeContext?.characterId
        || fallbackRuntimeContext?.characterId
        || 0
      ) || null;
      const syncKey = [
        String(expectedRoomId || this.resolveActiveRoomId?.() || this.activeRoomId || '').trim(),
        actorRef,
        String(characterId || ''),
      ].join('|');
      if (this._coordinatorStateSyncInFlightKey === syncKey) {
        return false;
      }
      this._coordinatorStateSyncInFlightKey = syncKey;

      const requestActorRef = normalizeAuthoritativeStateActorRef(actorRef, { runtimeContext: fallbackRuntimeContext });
      const state = await this.gameCoordinator.api.getState({
        actor: requestActorRef || undefined,
        characterId: characterId || undefined,
      });
      const responseActorRef = String(
        state?.action_contract?.actor_id
        ?? state?.game_state?.turn?.entity
        ?? state?.game_state?.encounter_presentation?.current_entity_id
        ?? ''
      ).trim();
      const canonicalExpectedRoomId = String(
        expectedRoomId
        || this.resolveActiveRoomId?.()
        || this.activeRoomId
        || ''
      ).trim();
      const serverRoomId = String(
        state?.active_room_id
        ?? state?.game_state?.active_room_id
        ?? ''
      ).trim();
      if (state?.success) {
        if (serverRoomId && canonicalExpectedRoomId && serverRoomId !== canonicalExpectedRoomId) {
          console.warn('[GameShell] Skipping stale coordinator resync snapshot after runtime bundle apply', {
            expectedRoomId: canonicalExpectedRoomId,
            serverRoomId,
          });
          return false;
        }
        if (actorRef && responseActorRef && responseActorRef !== actorRef) {
          console.warn('[GameShell] Skipping stale coordinator resync snapshot after actor context changed', {
            requestedActorRef: actorRef,
            responseActorRef,
          });
          return false;
        }
        this.gameCoordinator.applyAuthoritativeUpdate(state);
        return true;
      }
      this.gameCoordinator?.runtimeStateStore?.noteSyncFailure?.({
        code: 'coordinator_state_sync_failed',
        error: state?.error || 'unknown',
        expectedRoomId: canonicalExpectedRoomId || null,
      });
    } catch (error) {
      this.gameCoordinator?.runtimeStateStore?.noteSyncFailure?.({
        code: 'coordinator_state_sync_fetch_error',
        error: error?.message || String(error || ''),
        expectedRoomId: String(expectedRoomId || '').trim() || null,
      });
      console.warn('[GameShell] Failed to resync coordinator state after runtime bundle apply', error);
    } finally {
      this._coordinatorStateSyncInFlightKey = null;
    }
    return false;
  }

  // --- ported from hexmap.js ---
  resolveActiveRoomId() {
    const visualRoomId = this.mapVisualState?.map_meta?.active_room_id
      || Object.keys(this.mapVisualState?.topology?.rooms || {})[0]
      || null;
    return visualRoomId || this.activeRoomId || this.state?.activeRoomId || this.launchContext?.room_id || null;
  }

  _syncActiveRoomAuthorityFromRuntimePayload() {
    const runtimeRoomId = String(
      this.dungeonData?.active_room_id
      || this.dungeonData?.current_room_id
      || ''
    ).trim();
    if (runtimeRoomId !== '') {
      if (!this.mapVisualState || typeof this.mapVisualState !== 'object') {
        this.mapVisualState = {};
      }
      if (!this.mapVisualState.map_meta || typeof this.mapVisualState.map_meta !== 'object') {
        this.mapVisualState.map_meta = {};
      }
      this.mapVisualState.map_meta.active_room_id = runtimeRoomId;
    }
    this.activeRoomId =
      this.mapVisualState?.map_meta?.active_room_id
      || this.launchContext?.room_id
      || this.activeRoomId
      || null;
  }

  // --- ported from hexmap.js ---
  getVisualRooms() {
    const rooms = this.mapVisualState?.topology?.rooms;
    return rooms && typeof rooms === 'object' ? rooms : {};
  }

  // --- ported from hexmap.js ---
  getPresentationObjectDefinitions() {
    return _getPresentationObjectDefinitions(this.mapVisualState, this.dungeonData);
  }

  // --- ported from hexmap.js ---
  hasVisualOccupants() {
    return Array.isArray(this.mapVisualState?.occupants?.party)
      || Array.isArray(this.mapVisualState?.occupants?.entities);
  }

  // --- ported from hexmap.js ---
  getVisualOccupants() {
    return _getVisualOccupants(this.mapVisualState);
  }

  getVisualActorRoster() {
    return _getVisualActorRoster(this.mapVisualState);
  }

  getActiveRoomActorRoster(roomId = null) {
    const normalizedRoomId = String(roomId || this.resolveActiveRoomId() || '').trim();
    if (!normalizedRoomId) {
      return [];
    }
    return this.getVisualActorRoster().filter((entry) => String(entry?.room_id || '').trim() === normalizedRoomId);
  }

  // --- ported from hexmap.js ---
  isVisualOccupantVisible(occupant) {
    return _isVisualOccupantVisible(occupant, this.resolveActiveRoomId());
  }

  // --- ported from hexmap.js ---
  getVisualConnections() {
    return Array.isArray(this.mapVisualState?.topology?.connections)
      ? this.mapVisualState.topology.connections
      : [];
  }

  // --- ported from hexmap.js ---
  parseVisualHexId(hexId) {
    return _parseVisualHexId(hexId);
  }

  // --- ported from hexmap.js ---
  getConnectionRoomId(connection, side) {
    return _getConnectionRoomId(connection, side);
  }

  // --- ported from hexmap.js ---
  getConnectionHex(connection, side) {
    return _getConnectionHex(connection, side);
  }

  // --- ported from hexmap.js ---
  getActiveRoomData() {
    return _getActiveRoomData(this.getVisualRooms(), this.resolveActiveRoomId());
  }

  // --- ported from hexmap.js ---
  getActiveRoomHex(q, r) {
    return _getActiveRoomHex(this.getActiveRoomData(), q, r);
  }

  // --- ported from hexmap.js ---
  buildActiveRoomOccupantSummary() {
    return _buildActiveRoomOccupantSummary(
      this.resolveActiveRoomId(),
      this.getVisualOccupants(),
      (occupant) => this.isVisualOccupantVisible(occupant),
    );
  }

  // --- ported from hexmap.js ---
  getObjectDefinition(contentId) {
    return _getObjectDefinition(contentId, this.mapVisualState, this.dungeonData);
  }

  // --- ported from hexmap.js ---
  buildObstacleMobilityProfile(objectDefinition, metadata = {}, contentId = '') {
    return _buildObstacleMobilityProfile(objectDefinition, metadata, contentId);
  }

  // --- ported from hexmap.js ---
  getObstacleMobilityAtHex(q, r) {
    return _getObstacleMobilityAtHex(
      this.getActiveRoomData(),
      this.getPresentationObjectDefinitions(),
      q,
      r,
    );
  }

  // --- ported from hexmap.js ---
  resolveTerrainKey(obstacleProfile, inActiveRoom) {
    if (obstacleProfile?.isWall && !obstacleProfile.passable && !obstacleProfile.movable) {
      return 'wall';
    }
    if (!inActiveRoom) {
      return 'outside';
    }

    const room = this.getActiveRoomData();
    const terrainType = typeof room?.terrain?.type === 'string'
      ? String(room.terrain.type).toLowerCase()
      : '';
    if (terrainType.includes('wood') || terrainType.includes('tavern') || terrainType.includes('plank')) {
      return 'wooden_floor';
    }
    if (terrainType.includes('stone') || terrainType.includes('dungeon') || terrainType.includes('cave')) {
      return 'stone_floor';
    }
    return 'floor';
  }

  // --- ported from hexmap.js ---
  describePassability(obstacleProfile, inActiveRoom) {
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
  }

  // --- ported from hexmap.js ---
  getAxialLine(fromQ, fromR, toQ, toR) {
    return _getAxialLine(fromQ, fromR, toQ, toR, this.movementSystem);
  }

  // --- ported from hexmap.js ---
  hasLineOfSight(fromQ, fromR, toQ, toR) {
    return _hasLineOfSight(
      fromQ,
      fromR,
      toQ,
      toR,
      (q, r) => this.getObstacleMobilityAtHex(q, r),
      this.movementSystem,
    );
  }

  // --- ported from hexmap.js ---
  getHostileTargets(actor) {
    return _getHostileTargets(actor, this.entityManager, this.movementSystem, (fromQ, fromR, toQ, toR) => this.hasLineOfSight(fromQ, fromR, toQ, toR));
  }

  // --- ported from hexmap.js ---
  describeEntitiesAtHex(q, r) {
    const labels = [];
    const liveEntities = this.getEntitiesAtHex(q, r);
    liveEntities.forEach((entity) => {
      const identity = entity.getComponent?.('IdentityComponent');
      const combat = entity.getComponent?.('CombatComponent');
      const teamLabel = combat?.team ? ` (${combat.team})` : '';
      labels.push(`${identity?.name || _getEntityDisplayName(entity)}${teamLabel}`);
    });
    if (labels.length) {
      return labels;
    }

    if (!this.hasVisualOccupants()) {
      return labels;
    }

    this.getVisualOccupants()
      .filter((candidate) => {
        if (String(candidate?.room_id || '') !== String(this.resolveActiveRoomId() || '')) {
          return false;
        }
        if (!this.isVisualOccupantVisible(candidate)) {
          return false;
        }
        return Number(candidate?.placement?.q) === Number(q) && Number(candidate?.placement?.r) === Number(r);
      })
      .forEach((candidate) => {
        const label = String(candidate?.label || candidate?.content_id || candidate?.occupant_id || '').trim();
        if (!label) {
          return;
        }
        const team = String(candidate?.presentation?.badge || '').trim();
        labels.push(team ? `${label} (${team})` : label);
      });

    return labels;
  }

  // --- ported from hexmap.js ---
  getObjectLabelAtHex(q, r) {
    const liveEntity = this.getEntitiesAtHex(q, r)[0] || null;
    const liveIdentity = liveEntity?.getComponent?.('IdentityComponent');
    if (liveIdentity?.name) {
      return liveIdentity.name;
    }

    const roomHex = this.getActiveRoomHex(q, r);
    if (roomHex && Array.isArray(roomHex.objects) && roomHex.objects.length) {
      const object = roomHex.objects.find((candidate) => candidate && typeof candidate === 'object') || roomHex.objects[0];
      if (object?.label) {
        return object.label;
      }
      const objectId = String(object?.object_id || '').trim();
      const definition = this.getObjectDefinition(objectId);
      if (definition?.label) {
        return definition.label;
      }
      if (objectId) {
        return objectId.replace(/[_-]+/g, ' ');
      }
    }

    return null;
  }

  // --- ported from hexmap.js ---
  getObjectIdAtHex(q, r) {
    const roomHex = this.getActiveRoomHex(q, r);
    if (roomHex && Array.isArray(roomHex.objects) && roomHex.objects.length) {
      const object = roomHex.objects.find((candidate) => candidate && typeof candidate === 'object') || roomHex.objects[0];
      const objectId = String(object?.object_id || '').trim();
      if (objectId) {
        return objectId;
      }
    }
    return null;
  }

  // --- ported from hexmap.js ---
  describeObjectsAtHex(hex, q, r) {
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
    if (obstacleLabel && !labels.includes(obstacleLabel)) {
      labels.push(obstacleLabel);
    }

    return labels;
  }

  // --- ported from hexmap.js ---
  describeConnectionAtHex(q, r) {
    const connections = this.getVisualConnections();
    if (!connections.length) {
      return null;
    }

    const activeRoomId = String(this.resolveActiveRoomId() || '');
    const match = connections.find((connection) => {
      const fromHex = this.getConnectionHex(connection, 'from');
      const toHex = this.getConnectionHex(connection, 'to');
      return (fromHex && this.getConnectionRoomId(connection, 'from') === activeRoomId && Number(fromHex.q) === Number(q) && Number(fromHex.r) === Number(r))
        || (toHex && this.getConnectionRoomId(connection, 'to') === activeRoomId && Number(toHex.q) === Number(q) && Number(toHex.r) === Number(r));
    });
    if (!match) {
      return null;
    }

    const targetRoom = this.getConnectionRoomId(match, 'to') === activeRoomId
      ? this.getConnectionRoomId(match, 'from')
      : this.getConnectionRoomId(match, 'to');
    const status = [];
    status.push(match.is_passable ? 'passable' : 'blocked');
    if (match.is_discovered) {
      status.push('discovered');
    }

    return `${match.type || 'connection'} -> ${targetRoom || 'unknown'} (${status.join(', ')})`;
  }

  // --- ported from hexmap.js ---
  resolveNavigationCapabilities(roomId = null) {
    // Navigation capability source-of-truth is the campaign navigation service.
    // If this contract is missing, fail closed rather than inferring legality.
    const activeRoomId = String(roomId || this.resolveActiveRoomId() || '').trim();
    const capabilities = Array.isArray(this.dungeonData?.navigation_capabilities)
      ? this.dungeonData.navigation_capabilities
      : [];
    if (!Array.isArray(this.dungeonData?.navigation_capabilities)) {
      if (activeRoomId === '') {
        return [];
      }
      if (!this._missingNavigationExitsWarnings.has(activeRoomId)) {
        this._missingNavigationExitsWarnings.add(activeRoomId);
        console.warn('[Navigation] Missing authoritative campaign navigation capabilities for active room', {
          activeRoomId,
          dungeonDataKeys: this.dungeonData && typeof this.dungeonData === 'object' ? Object.keys(this.dungeonData) : [],
        });
      }
      return [];
    }

    const cache = this._navigationCapabilitiesCache || {
      sourceRef: null,
      sourceLength: 0,
      normalized: [],
      byRoom: new Map(),
    };
    const sourceLength = capabilities.length;
    const cacheIsCurrent = cache.sourceRef === capabilities && cache.sourceLength === sourceLength;
    if (!cacheIsCurrent) {
      cache.sourceRef = capabilities;
      cache.sourceLength = sourceLength;
      cache.normalized = capabilities
        .map((capability) => _normalizeAuthoritativeNavigationCapability(capability, activeRoomId))
        .filter((capability) => capability.target_room_id);
      cache.byRoom = new Map();
      this._navigationCapabilitiesCache = cache;
    }
    const normalizedCapabilities = cache.normalized;
    if (activeRoomId === '') {
      return normalizedCapabilities;
    }

    if (cache.byRoom.has(activeRoomId)) {
      return cache.byRoom.get(activeRoomId);
    }

    const roomScopedCapabilities = normalizedCapabilities.filter((capability) => {
      const originRoomId = String(capability?.origin_room_id || '').trim();
      return originRoomId === '' || originRoomId === activeRoomId;
    });
    if (roomScopedCapabilities.length > 0 || normalizedCapabilities.length === 0) {
      cache.byRoom.set(activeRoomId, roomScopedCapabilities);
      return roomScopedCapabilities;
    }

    const explicitOriginRoomIds = Array.from(new Set(
      normalizedCapabilities
        .map((capability) => String(capability?.origin_room_id || '').trim())
        .filter(Boolean)
    ));
    if (explicitOriginRoomIds.length > 0) {
      console.warn('[Navigation] Refusing stale navigation capabilities for mismatched active room', {
        activeRoomId,
        capabilityOriginRoomIds: explicitOriginRoomIds,
        capabilityCount: normalizedCapabilities.length,
      });
      cache.byRoom.set(activeRoomId, []);
      return [];
    }

    // Unscoped capability payloads are still usable when the server omitted
    // per-room origin ids; keep those visible rather than blanking the menu.
    cache.byRoom.set(activeRoomId, normalizedCapabilities);
    return normalizedCapabilities;
  }

  // --- ported from hexmap.js ---
  resolveNavigationCapabilityAtHex(q, r) {
    return this.resolveNavigationCapabilities(this.resolveActiveRoomId()).find((capability) => {
      const originHex = capability?.origin_hex;
      return capability?.available
        && originHex
        && Number(originHex.q) === Number(q)
        && Number(originHex.r) === Number(r);
    }) || null;
  }

  // --- ported from hexmap.js ---
  findLaunchPlayerEntity() {
    return _findLaunchPlayerEntity(this.entityManager, this.launchContext, this.resolveLaunchCharacterStateId());
  }

  // --- ported from hexmap.js ---
  resolveLaunchCharacterStateId() {
    return Number(
      this.launchCharacter?.id
      || this.launchCharacter?.characterId
      || this.launchContext?.character_id
      || 0,
    ) || 0;
  }

  // --- ported from hexmap.js ---
  resolveLaunchCharacterRuntimeContext() {
    const selectedEntity = this._getStateValue('selectedEntity');
    const selectedCharacterId = Number(selectedEntity?.dcCharacterId || selectedEntity?.dcStatePayload?.metadata?.character_id || 0);
    const launchCharacterId = this.resolveLaunchCharacterStateId();
    const selectedInstanceId = selectedEntity?.dcEntityRef || selectedEntity?.dcEntityInstanceId || null;
    const selectedIsLaunchActor = launchCharacterId > 0 && selectedCharacterId === launchCharacterId;
    const launchCharacterInstanceId = this.launchCharacter?.instanceId || this.launchCharacter?.instance_id || null;
    const authoritativeInstanceId = launchCharacterInstanceId || (selectedIsLaunchActor ? selectedInstanceId : null);
    return {
      campaignId: this.resolveCampaignId(),
      characterId: selectedIsLaunchActor
        ? (selectedCharacterId || launchCharacterId || null)
        : (launchCharacterId || null),
      instanceId: selectedIsLaunchActor
        ? (selectedInstanceId || authoritativeInstanceId)
        : authoritativeInstanceId,
      roomId: this.resolveActiveRoomId(),
      questSummary: this.questSummary && typeof this.questSummary === 'object' ? this.questSummary : {},
    };
  }

  // --- ported from hexmap.js ---
  syncLaunchCharacterRuntimeFromEntity(entity) {
    if (!entity || !this.launchCharacter) {
      return;
    }

    const launchCharacterId = this.resolveLaunchCharacterStateId();
    const entityCharacterId = Number(entity?.dcCharacterId || entity?.dcStatePayload?.metadata?.character_id || 0);
    if (launchCharacterId <= 0 || entityCharacterId !== launchCharacterId) {
      return;
    }

    this.launchCharacter = {
      ...this.launchCharacter,
      instanceId: this.launchCharacter?.instanceId || entity?.dcEntityRef || null,
      instance_id: this.launchCharacter?.instance_id || entity?.dcEntityRef || null,
    };
    this.characterData = this.launchCharacter;
  }

  // --- ported from hexmap.js ---
  selectEntity(entityOrId, options = {}) {
    if (!entityOrId) {
      this.deselectEntity();
      return;
    }

    const entity = typeof entityOrId === 'object'
      ? entityOrId
      : (this.entityManager?.getEntity?.(entityOrId) || null);
    if (!entity) {
      return;
    }

    const suppressCoordinatorResync = options?.suppressCoordinatorResync === true;
    const selectedEntity = this._getStateValue('selectedEntity');
    if (selectedEntity?.id === entity?.id && suppressCoordinatorResync) {
      return;
    }

    this._setStateValue('selectedEntity', entity);
    this.syncLaunchCharacterRuntimeFromEntity(entity);
    this.bus?.emit('entity:selected', { entity });
    if (!suppressCoordinatorResync && this.canResyncCoordinatorForSelectedEntity(entity)) {
      void this.syncCoordinatorStateFromServer(this.resolveActiveRoomId() || '', {
        actor: this.getEntityInstanceRef(entity),
        characterId: this.getEntityCharacterId(entity) || null,
      });
    }
  }

  getEntityInstanceRef(entity) {
    return String(
      entity?.dcEntityRef
      || entity?.dcEntityInstanceId
      || entity?.instanceId
      || entity?.id
      || ''
    ).trim();
  }

  getEntityCharacterId(entity) {
    return Number(
      entity?.dcCharacterId
      || entity?.dcStatePayload?.metadata?.character_id
      || entity?.dcStatePayload?.state?.metadata?.character_id
      || 0
    ) || 0;
  }

  getEntityMetadata(entity) {
    return entity?.dcStatePayload?.metadata
      || entity?.dcStatePayload?.state?.metadata
      || entity?.dcEntityPayload?.state?.metadata
      || entity?.dcEntityPayload?.metadata
      || {};
  }

  isFollowerLikeEntity(entity) {
    const metadata = this.getEntityMetadata(entity);
    const followerKind = String(metadata?.follower_kind || metadata?.bond_contract?.follower_kind || '').trim().toLowerCase();
    const roleKind = String(
      metadata?.role
      || metadata?.bond_contract?.role
      || entity?.dcStatePayload?.role
      || entity?.dcStatePayload?.state?.role
      || ''
    ).trim().toLowerCase();
    const entityRef = this.getEntityInstanceRef(entity).toLowerCase();
    return followerKind === 'familiar'
      || followerKind === 'companion'
      || followerKind === 'follower'
      || roleKind.includes('familiar')
      || roleKind.includes('companion')
      || roleKind.includes('follower')
      || entityRef.startsWith('familiar-')
      || entityRef.startsWith('companion-')
      || entityRef.startsWith('follower-');
  }

  isControlledFollowerEntity(entity) {
    if (!this.isFollowerLikeEntity(entity)) {
      return false;
    }

    const metadata = this.getEntityMetadata(entity);
    const launchCharacterId = this.resolveLaunchCharacterStateId();
    const ownerSourceCharacterId = Number(metadata?.owner_source_character_id || metadata?.bond_contract?.owner_source_character_id || 0) || 0;
    const ownerCharacterId = Number(metadata?.owner_character_id || metadata?.bond_contract?.owner_character_id || 0) || 0;
    return launchCharacterId > 0 && (ownerSourceCharacterId === launchCharacterId || ownerCharacterId === launchCharacterId);
  }

  isActorEntity(entity) {
    const entityType = String(entity?.dcEntityType || entity?.dcStatePayload?.entity_type || entity?.getComponent?.('IdentityComponent')?.entityType || '').trim().toLowerCase();
    return ['player_character', 'pc', 'npc', 'creature', 'character'].includes(entityType);
  }

  isLiveCombatEncounterActive() {
    const snapshot = this.gameCoordinator?.phaseManager?.getSnapshot?.() || null;
    const encounterId = Number(
      snapshot?.encounterId
      || this.gameCoordinator?.phaseManager?.encounterId
      || this._getStateValue('encounterId')
      || 0
    ) || 0;
    const phase = String(snapshot?.phase || '').trim().toLowerCase();
    const encounterState = this.getEncounterServerState?.() || {};
    // Only server-provided encounter state may declare an encounter non-live.
    // The legacy ECS TurnManagementSystem defaults to 'idle' and is never fed
    // server state in the v2 shell, so consulting it here would let a stale
    // client-side default silently veto an authoritative coordinator snapshot
    // and misroute combat moves down the non-combat room-move path.
    const presentationStatus = String(
      snapshot?.encounterPresentation?.status
      ?? encounterState?.encounter_presentation?.status
      ?? encounterState?.status
      ?? ''
    ).trim().toLowerCase();

    if (phase && phase !== 'encounter') {
      return false;
    }
    if (presentationStatus && !['active', 'in_progress', 'setup', 'rolling_initiative', 'paused'].includes(presentationStatus)) {
      return false;
    }
    return encounterId > 0;
  }

  isCombatDragActorTurn(entity) {
    if (!this.isLiveCombatEncounterActive() || !entity) {
      return true;
    }
    const turnActorRef = String(this.gameCoordinator?.phaseManager?.turn?.entity || '').trim();
    const entityRef = this.getEntityInstanceRef(entity);
    return turnActorRef !== '' && entityRef !== '' && turnActorRef === entityRef;
  }

  canDragEntityOnMap(entity) {
    if (!entity) {
      return false;
    }

    const followerLike = this.isFollowerLikeEntity(entity);
    if (!this.isActorEntity(entity) && !followerLike) {
      return false;
    }

    const entityRoomId = String(entity?.dcStatePayload?.placement?.room_id || entity?.dcStatePayload?.state?.placement?.room_id || this.resolveActiveRoomId() || '').trim();
    if (entityRoomId !== String(this.resolveActiveRoomId() || '').trim()) {
      return false;
    }

    const canUseGmMode = this.campaignAccess?.can_use_gm_mode === true;
    const isGmMode = String(this.activeCampaignMode || this.campaignAccess?.current_mode || 'player').trim().toLowerCase() === 'gm';
    if (canUseGmMode && isGmMode) {
      return this.isCombatDragActorTurn(entity);
    }

    const launchCharacterId = this.resolveLaunchCharacterStateId();
    const canDragAsPlayer = (
      launchCharacterId > 0 && this.getEntityCharacterId(entity) === launchCharacterId
    ) || this.isControlledFollowerEntity(entity);
    if (!canDragAsPlayer) {
      return false;
    }

    return this.isCombatDragActorTurn(entity);
  }

  canResyncCoordinatorForSelectedEntity(entity) {
    if (!entity) {
      return false;
    }
    const launchCharacterId = this.resolveLaunchCharacterStateId();
    const matchesLaunchCharacter = launchCharacterId > 0 && this.getEntityCharacterId(entity) === launchCharacterId;
    return matchesLaunchCharacter;
  }

  resolveMapDragDropValidation(entity, targetQ, targetR) {
    const q = Number(targetQ);
    const r = Number(targetR);
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      return { valid: false, reason: 'Drop target is not a valid hex.' };
    }

    const roomHex = this.getActiveRoomHex(q, r);
    if (!roomHex) {
      return { valid: false, reason: 'Drag movement must stay within the active room.' };
    }

    const obstacleProfile = this.getObstacleMobilityAtHex(q, r);
    if (obstacleProfile && obstacleProfile.passable === false) {
      return { valid: false, reason: 'Destination hex is blocked.' };
    }

    const entityId = entity?.id ?? null;
    const occupants = this.getEntitiesAtHex(q, r).filter((candidate) => candidate && candidate.id !== entityId);
    if (occupants.length > 0) {
      return { valid: false, reason: 'Destination hex is occupied.' };
    }

    return { valid: true, reason: '' };
  }

  applyLocalEntityPlacement(entity, roomId, q, r) {
    if (!entity) {
      return;
    }

    const position = entity.getComponent?.('PositionComponent') || null;
    if (position?.setHex) {
      position.setHex(Number(q), Number(r));
    } else if (position) {
      position.q = Number(q);
      position.r = Number(r);
    }

    if (entity?.placement && typeof entity.placement === 'object') {
      entity.placement.roomId = roomId;
      entity.placement.q = Number(q);
      entity.placement.r = Number(r);
    }

    const payloadCandidates = [
      entity?.dcStatePayload,
      entity?.dcEntityPayload,
    ];
    payloadCandidates.forEach((payload) => {
      if (!payload || typeof payload !== 'object') {
        return;
      }
      if (!payload.placement || typeof payload.placement !== 'object') {
        payload.placement = {};
      }
      payload.placement.room_id = roomId;
      payload.placement.hex = { q: Number(q), r: Number(r) };
      if (payload.state && typeof payload.state === 'object') {
        if (!payload.state.placement || typeof payload.state.placement !== 'object') {
          payload.state.placement = {};
        }
        payload.state.placement.room_id = roomId;
        payload.state.placement.hex = { q: Number(q), r: Number(r) };
      }
    });

    this.bus?.emit('entity:moved', { entity });
    this.setSelectedHex(Number(q), Number(r), { emitDetails: false });
    if (this.getEntityCharacterId(entity) === this.resolveLaunchCharacterStateId()) {
      this.updateLaunchLocationContext(roomId, Number(q), Number(r));
    }
  }

  _resolveEntityForAuthoritativeRef(actorRef = '') {
    const normalizedRef = String(actorRef || '').trim();
    if (!normalizedRef || !this.entityManager) {
      return null;
    }

    const turnResolved = this.turnManagementSystem?.resolveEntityFromServerId?.(normalizedRef) || null;
    if (turnResolved) {
      return turnResolved;
    }

    return this.entityManager.getEntitiesWith('PositionComponent').find((entity) => (
      String(this.getEntityInstanceRef(entity) || '').trim() === normalizedRef
      || String(entity?.dcEntityInstanceId || '').trim() === normalizedRef
      || String(entity?.id || '').trim() === normalizedRef
    )) || null;
  }

  _emitActiveRoomEntitiesChanged() {
    if (!this.bus || !this.entityManager) {
      return;
    }

    this.bus.emit('room:entities-changed', {
      roomId: String(this.resolveActiveRoomId() || this.activeRoomId || '').trim() || null,
      entities: this.entityManager.getEntitiesWith('PositionComponent', 'RenderComponent'),
    });
  }

  _syncEncounterPlacementsFromRuntimeSnapshot(snapshot = null) {
    const gameState = snapshot?.gameState && typeof snapshot.gameState === 'object'
      ? snapshot.gameState
      : null;
    const initiativeOrder = Array.isArray(gameState?.initiative_order) ? gameState.initiative_order : [];
    if (!this.entityManager || initiativeOrder.length === 0) {
      return;
    }

    let applied = false;
    for (const participant of initiativeOrder) {
      if (!participant || typeof participant !== 'object') {
        continue;
      }

      const actorRef = String(participant.entity_id || '').trim();
      const roomId = String(
        participant.room_id
        || gameState?.active_room_id
        || snapshot?.activeRoomId
        || this.resolveActiveRoomId()
        || this.activeRoomId
        || ''
      ).trim();
      const q = Number(participant.position_q);
      const r = Number(participant.position_r);
      if (!actorRef || !roomId || !Number.isFinite(q) || !Number.isFinite(r)) {
        continue;
      }

      const entity = this._resolveEntityForAuthoritativeRef(actorRef);
      if (!entity) {
        continue;
      }

      const position = entity.getComponent?.('PositionComponent') || null;
      const currentRoomId = String(entity?.placement?.room_id || position?.roomId || '').trim();
      if (currentRoomId === roomId && Number(position?.q) === q && Number(position?.r) === r) {
        continue;
      }

      this.applyLocalEntityPlacement(entity, roomId, q, r);
      applied = true;
    }

    if (applied) {
      this._emitActiveRoomEntitiesChanged();
    }
  }

  _projectAuthoritativeMovementEvents(events = []) {
    if (!Array.isArray(events) || events.length === 0 || !this.entityManager) {
      return;
    }

    const movementTypes = new Set(['stride', 'step', 'crawl', 'climb', 'swim', 'fly', 'leap', 'sneak', 'burrow', 'forced_movement']);
    let applied = false;
    for (const event of events) {
      if (!event || typeof event !== 'object') {
        continue;
      }

      const rawType = String(event.type || '').trim().toLowerCase();
      const normalizedType = rawType.startsWith('npc_') ? rawType.slice(4) : rawType;
      if (!movementTypes.has(normalizedType)) {
        continue;
      }

      const data = event.data && typeof event.data === 'object' ? event.data : {};
      const resolutionEnvelope = data?.resolution_envelope && typeof data.resolution_envelope === 'object'
        ? data.resolution_envelope
        : null;
      const resolutionPackets = Array.isArray(resolutionEnvelope?.packets)
        ? resolutionEnvelope.packets.filter((packet) => packet && typeof packet === 'object')
        : [];
      const movementPacket = resolutionPackets.find((packet) => String(packet?.kind || '').trim().toLowerCase() === 'movement_resolution')
        || (data?.movement_packet && typeof data.movement_packet === 'object' ? data.movement_packet : null);
      const actorRef = String(movementPacket?.actor_entity_ref || event.actor || '').trim();
      const toHex = movementPacket?.to_hex && typeof movementPacket.to_hex === 'object'
        ? movementPacket.to_hex
        : (data?.to_hex && typeof data.to_hex === 'object'
          ? data.to_hex
          : (data?.to && typeof data.to === 'object' ? data.to : null));
      const roomId = String(
        data?.room_id
        || movementPacket?.metadata?.room_id
        || this.resolveActiveRoomId()
        || this.activeRoomId
        || ''
      ).trim();
      const q = Number(toHex?.q);
      const r = Number(toHex?.r);
      if (!actorRef || !roomId || !Number.isFinite(q) || !Number.isFinite(r)) {
        continue;
      }

      const entity = this._resolveEntityForAuthoritativeRef(actorRef);
      if (!entity) {
        continue;
      }

      this.applyLocalEntityPlacement(entity, roomId, q, r);
      applied = true;
    }

    if (applied) {
      this._emitActiveRoomEntitiesChanged();
    }
  }

  buildCombatDragMovementPlan(entity, targetQ, targetR) {
    const position = entity?.getComponent?.('PositionComponent') || null;
    const movement = entity?.getComponent?.('MovementComponent') || null;
    if (!position || !movement || !this.movementSystem?.findPath) {
      return { valid: false, reason: 'Combat movement context is unavailable.' };
    }
    if (!this.isCombatDragActorTurn(entity)) {
      return { valid: false, reason: 'Only the active turn actor can drag-move during combat.' };
    }

    const actionsRemaining = Number(this.gameCoordinator?.phaseManager?.turn?.actions_remaining ?? movement?.actionsRemaining ?? 0);
    const movementSpeed = Number(movement?.movementSpeed ?? movement?.movementRemaining);
    const hexCost = 5;
    if (!Number.isFinite(actionsRemaining) || actionsRemaining <= 0) {
      return { valid: false, reason: 'No movement actions remain.' };
    }
    if (!Number.isFinite(movementSpeed) || movementSpeed <= 0) {
      return { valid: false, reason: 'Actor has no movement speed.' };
    }

    const maxHexes = Math.floor((movementSpeed * actionsRemaining) / hexCost);
    const path = this.movementSystem.findPath(
      Number(position.q),
      Number(position.r),
      Number(targetQ),
      Number(targetR),
      Math.max(0, maxHexes),
    );
    const pathHexes = Array.isArray(path) ? Math.max(0, path.length - 1) : null;
    if (pathHexes === null) {
      return { valid: false, reason: 'No reachable combat path to that hex.' };
    }
    if (pathHexes < 1) {
      return { valid: true, noop: true };
    }
    if (pathHexes === 1) {
      return {
        valid: true,
        actionType: 'step',
        actionCost: 1,
        distanceFt: hexCost,
      };
    }

    const distanceFt = pathHexes * hexCost;
    const actionCost = Math.ceil(distanceFt / movementSpeed);
    if (actionCost > actionsRemaining) {
      return {
        valid: false,
        reason: `Movement requires ${actionCost} actions but only ${actionsRemaining} remain.`,
      };
    }

    return {
      valid: true,
      actionType: 'stride',
      actionCost,
      distanceFt,
    };
  }

  buildMovementHighlightBands(entity) {
    const position = entity?.getComponent?.('PositionComponent') || null;
    const movement = entity?.getComponent?.('MovementComponent') || null;
    const activeRoom = this.getActiveRoomData();
    if (!position || !movement || !this.movementSystem || !Array.isArray(activeRoom?.hexes)) {
      return null;
    }

    const movementSpeed = Number(movement?.movementSpeed ?? movement?.movementRemaining);
    const hexCost = 5;
    const combatActionsRemaining = Number(this.gameCoordinator?.phaseManager?.turn?.actions_remaining ?? 0) || 0;
    const maxActions = this.isLiveCombatEncounterActive()
      ? Math.max(0, Math.min(3, combatActionsRemaining))
      : 3;
    const maxHexes = Number.isFinite(movementSpeed) && movementSpeed > 0
      ? Math.floor((movementSpeed * maxActions) / hexCost)
      : 0;
    if (maxHexes <= 0) {
      return null;
    }

    const bands = { step: [], stride1: [], stride2: [], stride3: [] };
    const maxStrideOneHexes = Math.max(1, Math.floor(movementSpeed / hexCost));

    activeRoom.hexes.forEach((hex) => {
      const q = Number(hex?.q);
      const r = Number(hex?.r);
      if (!Number.isFinite(q) || !Number.isFinite(r)) {
        return;
      }
      if (q === Number(position.q) && r === Number(position.r)) {
        return;
      }

      const validation = this.resolveMapDragDropValidation(entity, q, r);
      if (!validation.valid) {
        return;
      }

      const path = this.movementSystem.findPath(
        Number(position.q),
        Number(position.r),
        q,
        r,
        Math.max(0, maxHexes),
      );
      const pathHexes = Array.isArray(path) ? Math.max(0, path.length - 1) : null;
      if (pathHexes === null || pathHexes < 1) {
        return;
      }

      if (pathHexes === 1) {
        bands.step.push({ q, r });
        return;
      }

      const actionBand = Math.ceil(pathHexes / maxStrideOneHexes);
      if (actionBand === 1) {
        bands.stride1.push({ q, r });
      } else if (actionBand === 2) {
        bands.stride2.push({ q, r });
      } else if (actionBand === 3) {
        bands.stride3.push({ q, r });
      }
    });

    return bands;
  }

  showMovementHighlightBandsForEntity(entity) {
    const bands = this.buildMovementHighlightBands(entity);
    this._setStateValue('movementRange', bands);
    this.canvas?.app?.renderMovementBandOverlay?.(bands || {});
  }

  clearMovementHighlightBands() {
    this._setStateValue('movementRange', null);
    this.canvas?.app?.clearMovementBandOverlay?.();
  }

  async moveEntityWithinRoom(entity, roomId, q, r) {
    const campaignId = this.resolveCampaignId();
    const instanceId = this.getEntityInstanceRef(entity);
    if (!campaignId || !roomId || !instanceId) {
      return false;
    }
    const fromHex = entity?.placement?.hex && typeof entity.placement.hex === 'object'
      ? {
          q: Number(entity.placement.hex.q),
          r: Number(entity.placement.hex.r),
        }
      : null;

    try {
      const response = await fetch(`/api/campaign/${campaignId}/entity/${encodeURIComponent(instanceId)}/move`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          locationType: 'room',
          locationRef: roomId,
          stateData: {
            placement: {
              room_id: roomId,
              hex: {
                q: Number(q),
                r: Number(r),
              },
            },
          },
        }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        console.warn('[GameShell] room move rejected', { status: response.status, payload });
        this.bus?.emit?.('chat:system-message', {
          speaker: 'System',
          kind: 'error',
          text: String(payload?.error || '').trim() || 'Move rejected by the server.',
        });
        return false;
      }
      this.applyLocalEntityPlacement(entity, roomId, q, r);
      const launchActor = this.findLaunchPlayerEntity?.() || null;
      const fallbackActorRef = this.getEntityInstanceRef(launchActor);
      const fallbackCharacterId = this.getEntityCharacterId(launchActor) || this.resolveLaunchCharacterStateId() || null;
      const canResyncAsEntity = this.canResyncCoordinatorForSelectedEntity(entity);
      await this.syncCoordinatorStateFromServer(roomId, {
        actor: canResyncAsEntity ? instanceId : fallbackActorRef,
        characterId: canResyncAsEntity ? (this.getEntityCharacterId(entity) || null) : fallbackCharacterId,
      });
      const actorLabel = _getEntityDisplayName(entity);
      const movementPacket = {
        contract_version: 'combat.movement_packet.v1',
        kind: 'movement_resolution',
        actor_entity_ref: String(instanceId),
        movement_mode: 'room_move',
        from_hex: fromHex,
        to_hex: { q: Number(q), r: Number(r) },
        distance_ft: 0,
        action_cost: 0,
        metadata: {
          room_id: String(roomId),
          source: 'in_room_move',
        },
      };
      if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function' && typeof window.CustomEvent === 'function') {
        window.dispatchEvent(new window.CustomEvent('dungeoncrawler:game-events', {
          detail: {
            events: [{
              type: 'stride',
              actor: String(instanceId),
              target: null,
              narration: '',
              data: {
                actor_name: actorLabel,
                from: fromHex,
                to: { q: Number(q), r: Number(r) },
                movement_packet: movementPacket,
                is_forced: false,
                action_cost: 0,
              },
            }],
          },
        }));
      }
      const hazardEvents = Array.isArray(payload?.data?.hazardEvents) ? payload.data.hazardEvents : [];
      if (
        hazardEvents.length > 0 &&
        typeof window !== 'undefined' &&
        typeof window.dispatchEvent === 'function' &&
        typeof window.CustomEvent === 'function'
      ) {
        window.dispatchEvent(new window.CustomEvent('dungeoncrawler:game-events', {
          detail: {
            events: hazardEvents.map((hazardEvent = {}) => ({
              type: 'hazard_triggered',
              source: 'room-move',
              timestamp: new Date().toISOString(),
              actor_id: actorId,
              actor_name: actorLabel,
              room_id: String(hazardEvent?.room_id || roomId),
              q: Number(q),
              r: Number(r),
              data: {
                actor_name: actorLabel,
                room_id: String(hazardEvent?.room_id || roomId),
                q: Number(q),
                r: Number(r),
                hazard: hazardEvent,
              },
              hazard: hazardEvent,
            })),
          },
        }));
      }
      hazardEvents.forEach((hazardEvent = {}) => {
        const hazardName = String(hazardEvent?.name || 'Hazard').trim() || 'Hazard';
        const effect = hazardEvent?.effect && typeof hazardEvent.effect === 'object' ? hazardEvent.effect : {};
        const effectDescription = String(effect?.description || '').trim();
        const resolvedDamage = Number(effect?.damage_applied ?? effect?.resolved_damage);
        const damageText = Number.isFinite(resolvedDamage) && resolvedDamage > 0
          ? String(Math.floor(resolvedDamage))
          : String(effect?.damage || '').trim();
        const damageType = String(effect?.damage_type || '').trim();
        const effectSuffixParts = [];
        if (effectDescription) {
          effectSuffixParts.push(effectDescription);
        }
        if (damageText) {
          effectSuffixParts.push(`${damageText}${damageType ? ` ${damageType}` : ''}`.trim());
        }
        const effectSuffix = effectSuffixParts.length > 0 ? ` ${effectSuffixParts.join(' ')}` : '';
        this.bus?.emit('chat:system-message', {
          text: `${hazardName} triggers as ${actorLabel} moves to (${Number(q)}, ${Number(r)}).${effectSuffix}`,
          speaker: 'System',
          kind: 'system',
          view: 'room',
          channel: 'room',
          source: 'hazard-system',
          authority: 'authoritative',
          messageClass: 'authoritative_transcript',
        });
      });
      return true;
    } catch (error) {
      console.warn('[GameShell] room move failed', error);
      return false;
    }
  }

  async handleMapActorDrop({ entity = null, sourceQ = 0, sourceR = 0, targetQ = 0, targetR = 0 } = {}) {
    const rejectDrop = (reason) => {
      console.warn('[GameShell] map actor drop rejected', { reason, targetQ, targetR });
      this.bus?.emit?.('chat:system-message', {
        speaker: 'System',
        kind: 'error',
        text: String(reason || '').trim() || 'That move is not allowed.',
      });
      return false;
    };

    if (!this.canDragEntityOnMap(entity)) {
      return rejectDrop(
        this.isLiveCombatEncounterActive() && !this.isCombatDragActorTurn(entity)
          ? 'You can only move an actor on its own turn.'
          : 'You are not allowed to move this actor.'
      );
    }

    const validation = this.resolveMapDragDropValidation(entity, targetQ, targetR);
    if (!validation.valid) {
      return rejectDrop(validation.reason);
    }

    if (Number(sourceQ) === Number(targetQ) && Number(sourceR) === Number(targetR)) {
      return true;
    }

    if (this.isLiveCombatEncounterActive()) {
      const combatPlan = this.buildCombatDragMovementPlan(entity, targetQ, targetR);
      if (!combatPlan.valid) {
        return rejectDrop(combatPlan.reason);
      }
      if (combatPlan.noop) {
        return true;
      }
      const result = await this.performCombatAction({
        actionType: combatPlan.actionType || 'stride',
        actorId: entity?.id,
        characterId: this.getEntityCharacterId(entity) || null,
        targetHex: { q: Number(targetQ), r: Number(targetR) },
        actionCost: combatPlan.actionCost,
        distanceFt: combatPlan.distanceFt,
      });
      if (!result?.success) {
        return rejectDrop('The server rejected that combat movement.');
      }
      return true;
    }

    return this.moveEntityWithinRoom(entity, String(this.resolveActiveRoomId() || ''), Number(targetQ), Number(targetR));
  }

  // --- ported from hexmap.js ---
  setSelectedHex(q, r, options = {}) {
    const nextHex = Number.isFinite(Number(q)) && Number.isFinite(Number(r))
      ? { q: Number(q), r: Number(r) }
      : null;
    this._setStateValue('selectedHex', nextHex);
    if (nextHex && options.emitDetails !== false) {
      this.bus?.emit('hex:details', this.getHexDetail(nextHex.q, nextHex.r));
    }
  }

  // --- ported from hexmap.js ---
  getEntitiesAtHex(q, r) {
    if (!this.entityManager?.getEntitiesWith) {
      return [];
    }

    const activeRoomId = this.resolveActiveRoomId();
    return this.entityManager.getEntitiesWith('PositionComponent').filter((entity) => {
      const position = entity?.getComponent?.('PositionComponent');
      return position
        && Number(position.q) === Number(q)
        && Number(position.r) === Number(r)
        && String(position.roomId || position.room_id || activeRoomId) === String(activeRoomId);
    });
  }

  // --- ported from hexmap.js ---
  getHexDetail(q, r) {
    const activeRoom = this.getActiveRoomData();
    if (!activeRoom) {
      return null;
    }

    const activeRoomHex = this.getActiveRoomHex(q, r);
    const inRoom = Boolean(activeRoomHex);
    const obstacleProfile = this.getObstacleMobilityAtHex(q, r);
    const terrainKey = this.resolveTerrainKey(obstacleProfile, inRoom);
    const roomTerrain = typeof activeRoom?.terrain?.type === 'string'
      ? String(activeRoom.terrain.type).trim()
      : '';
    const terrainLabel = roomTerrain && roomTerrain !== 'unknown'
      ? `${terrainKey} (${roomTerrain})`
      : terrainKey;

    return {
      q: Number(q),
      r: Number(r),
      roomId: this.resolveActiveRoomId(),
      roomName: inRoom ? (activeRoom?.name || this.resolveActiveRoomId()) : `${activeRoom?.name || this.resolveActiveRoomId()} (outside footprint)`,
      isEntry: Boolean(activeRoomHex?.is_entry),
      isVisible: inRoom ? activeRoomHex?.is_visible !== false : false,
      isDiscovered: inRoom ? activeRoomHex?.is_discovered !== false : false,
      terrain: terrainLabel,
      lighting: typeof activeRoom?.lighting === 'string' ? activeRoom.lighting : 'unknown',
      elevationFt: inRoom && Number.isFinite(Number(activeRoomHex?.elevation_ft)) ? Number(activeRoomHex.elevation_ft) : null,
      passability: this.describePassability(obstacleProfile, inRoom),
      entities: this.describeEntitiesAtHex(q, r),
      objects: this.describeObjectsAtHex(activeRoomHex, q, r),
      objectCount: Array.isArray(activeRoomHex?.objects) ? activeRoomHex.objects.length : 0,
      connection: this.describeConnectionAtHex(q, r),
    };
  }

  // --- ported from hexmap.js ---
  tryTransitionAtHex(q, r) {
    const capability = this.resolveNavigationCapabilityAtHex(q, r);
    if (!capability?.available || !capability?.target_room_id) {
      return false;
    }

    this.persistLaunchLocationContext(capability.target_room_id, capability.target_hex?.q ?? null, capability.target_hex?.r ?? null);
    this.setActiveRoom(capability.target_room_id);
    return true;
  }

  // --- ported from hexmap.js ---
  deselectEntity() {
    this._setStateValue('selectedEntity', null);
    this.bus?.emit('entity:deselected');
  }

  // --- ported from hexmap.js ---
  setActiveRoom(roomId, options = {}) {
    const normalizedRoomId = String(roomId || '').trim();
    if (!normalizedRoomId) {
      return;
    }
    const loadData = options?.loadData !== false;
    const source = String(options?.source || 'set-active-room').trim() || 'set-active-room';
    const phase = String(options?.phase || 'navigation-apply').trim() || 'navigation-apply';
    const room = _mergeRoomMetadata(this.getVisualRooms()[normalizedRoomId] || null, {}, normalizedRoomId);
    const occupants = this.getVisualOccupants().filter(
      (occupant) => String(occupant?.room_id || '') === normalizedRoomId && this.isVisualOccupantVisible(occupant)
    );
    this._emitCanonicalRoomChanged({
      roomId: normalizedRoomId,
      roomName: room?.name ?? normalizedRoomId,
      room,
      occupants,
      source,
      phase,
      loadData,
    });
  }

  _synchronizePartyOccupantsToRoom(roomId) {
    const normalizedRoomId = String(roomId || '').trim();
    if (!normalizedRoomId || !Array.isArray(this.mapVisualState?.occupants?.party)) {
      return;
    }
    const partyOffsets = [
      { q: 0, r: 0 },
      { q: 1, r: 0 }, { q: -1, r: 0 }, { q: 0, r: 1 }, { q: 0, r: -1 }, { q: 1, r: -1 }, { q: -1, r: 1 },
    ];
    const anchorQ = Number.isFinite(Number(this.launchContext?.start_q))
      ? Number(this.launchContext.start_q)
      : Number(this.mapVisualState.occupants.party?.[0]?.placement?.q || 0);
    const anchorR = Number.isFinite(Number(this.launchContext?.start_r))
      ? Number(this.launchContext.start_r)
      : Number(this.mapVisualState.occupants.party?.[0]?.placement?.r || 0);
    this.mapVisualState.occupants.party = this.mapVisualState.occupants.party.map((occupant, index) => {
      const offset = partyOffsets[index] || partyOffsets[index % partyOffsets.length];
      const existingQ = Number(occupant?.placement?.q);
      const existingR = Number(occupant?.placement?.r);
      const hasExistingPlacement = Number.isFinite(existingQ) && Number.isFinite(existingR);
      return {
        ...occupant,
        room_id: normalizedRoomId,
        visible: occupant?.state?.hidden === true ? false : true,
        placement: {
          ...(occupant?.placement || {}),
          q: hasExistingPlacement ? existingQ : (anchorQ + offset.q),
          r: hasExistingPlacement ? existingR : (anchorR + offset.r),
        },
      };
    });
  }

  // --- ported from hexmap.js ---
  updateLaunchLocationContext(roomId, q = null, r = null) {
    const nextRoomId = roomId || this.resolveActiveRoomId();
    if (!nextRoomId) {
      return;
    }

    this.launchContext = {
      ...(this.launchContext || {}),
      room_id: nextRoomId,
    };

    if (q != null && Number.isFinite(Number(q))) {
      this.launchContext.start_q = Number(q);
    }
    if (r != null && Number.isFinite(Number(r))) {
      this.launchContext.start_r = Number(r);
    }

    if (typeof window === 'undefined' || !window.location || !window.history?.replaceState) {
      return;
    }

    const params = new URLSearchParams(window.location.search);
    const campaignId = this.resolveCampaignId();
    const characterId = Number(this.launchContext?.character_id || 0);
    if (campaignId) {
      params.set('campaign_id', String(campaignId));
    }
    if (characterId > 0) {
      params.set('character_id', String(characterId));
    }
    params.set('room_id', String(nextRoomId));
    if (q != null && Number.isFinite(Number(q))) {
      params.set('start_q', String(Number(q)));
    }
    if (r != null && Number.isFinite(Number(r))) {
      params.set('start_r', String(Number(r)));
    }

    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
  }

  // --- ported from hexmap.js ---
  persistLaunchLocationContext(roomId, q = null, r = null, entityRef = null) {
    const campaignId = this.resolveCampaignId();
    const nextRoomId = roomId || this.resolveActiveRoomId();
    const resolvedEntityRef = entityRef
      || this.launchCharacter?.instanceId
      || this.launchCharacter?.instance_id
      || null;

    this.updateLaunchLocationContext(roomId, q, r);
    if (!campaignId || !nextRoomId || !resolvedEntityRef) {
      return;
    }

    fetch(`/api/campaign/${campaignId}/entity/${resolvedEntityRef}/move`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify({
        locationType: 'room',
        locationRef: nextRoomId,
        stateData: {
          placement: {
            room_id: nextRoomId,
            hex: {
              q: Number.isFinite(Number(q)) ? Number(q) : 0,
              r: Number.isFinite(Number(r)) ? Number(r) : 0,
            },
          },
        },
      }),
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(`Entity move persist failed (${response.status}).`);
        }
        if (String(this.resolveActiveRoomId() || '').trim() !== String(nextRoomId).trim()) {
          return;
        }
        await this.loadRuntimeStateBundle(this.buildRuntimeBundleQueryForRoom(nextRoomId, {
          startQ: q,
          startR: r,
        }));
      })
      .catch((err) => console.warn('[Location] Entity move persist failed:', err));

    if (entityRef) {
      this.launchCharacter = {
        ...this.launchCharacter,
        instanceId: entityRef,
        instance_id: entityRef,
      };
      this.characterData = this.launchCharacter;
    }
  }

  /**
   * Re-emit the current entity set so token sprites pick up newly loaded
   * textures. Coalesced because a room's portraits resolve independently and
   * would otherwise trigger one full token rebuild per image.
   */
  _scheduleTokenSpriteRefresh() {
    if (this._tokenSpriteRefreshHandle) {
      return;
    }
    const schedule = (typeof window !== 'undefined' && typeof window.setTimeout === 'function')
      ? window.setTimeout
      : setTimeout;
    this._tokenSpriteRefreshHandle = schedule(() => {
      this._tokenSpriteRefreshHandle = null;
      if (!this.bus || !this.entityManager) {
        return;
      }
      this.bus.emit('room:entities-changed', {
        roomId: this.resolveActiveRoomId(),
        entities: this.entityManager.getEntitiesWith('PositionComponent', 'RenderComponent'),
      });
    }, 0);
  }

  // --- ported from hexmap.js ---
  canUseServerCombatApi() {
    return typeof fetch === 'function' && this.currentUserId > 0 && this.resolveCampaignId() !== null;
  }

  // --- ported from hexmap.js ---
  notifyServerUnavailable() {
    console.error('Unable to connect to server. Please try again.');
  }

  // --- ported from hexmap.js ---
  cacheEncounterServerState(serverState = null) {
    this._setStateValue('latestEncounterState', serverState && typeof serverState === 'object' && serverState.encounter_id ? serverState : null);
  }

  // --- ported from hexmap.js ---
  getEncounterServerState() {
    return this._getStateValue('latestEncounterState') || null;
  }

  // --- adapted from hexmap.js ---
  async performCombatAction(options = {}) {
    const encounterId = Number(
      this.gameCoordinator?.phaseManager?.encounterId
      || this._getStateValue('encounterId')
      || 0
    ) || null;
    const actionType = String(options?.actionType || '').trim();
    if (!actionType) {
      console.error('[GameShell] performCombatAction missing actionType', { options });
      return null;
    }
    if (!this.canUseServerCombatApi()) {
      console.error('[GameShell] performCombatAction unavailable: combat API disabled', { actionType });
      return null;
    }
    if (!encounterId) {
      console.error('[GameShell] performCombatAction missing encounterId', { actionType, options });
      this.bus?.emit?.('chat:system-message', {
        speaker: 'System',
        kind: 'error',
        text: 'Encounter contract error: missing encounter ID for a turn-based action. Refresh the room state.',
      });
      return null;
    }

    const actorEntity = this.entityManager?.getEntity?.(options.actorId) || null;
    const actorRef = this.getEntityInstanceRef(actorEntity) || String(options.actorId || '').trim();
    if (!actorRef) {
      return null;
    }

    const coordinator = this.gameCoordinator || null;
    const campaignId = this.resolveCampaignId();
    if (!coordinator?.api || !campaignId) {
      console.error('[GameShell] performCombatAction unavailable: coordinator not initialized', { actionType, campaignId });
      return null;
    }
    const runtimeCharacterId = Number(options?.characterId || this.resolveLaunchCharacterRuntimeContext?.().characterId || 0) || null;

    const params = {
      action_cost: Number(options?.actionCost || 0) || 0,
      distance_ft: Number.isFinite(Number(options?.distanceFt)) ? Number(options.distanceFt) : null,
      character_id: Number(options?.characterId || 0) || null,
      target_hex: options?.targetHex ?? null,
      to_hex: options?.targetHex ?? null,
      destination_hex: options?.destinationHex ?? null,
      interaction_type: options?.interactionType ?? null,
      message: options?.message ?? null,
      skill_name: options?.skillName ?? null,
      skill_bonus: Number.isFinite(Number(options?.skillModifier)) ? Number(options.skillModifier) : null,
      feat_id: options?.featId ?? null,
      feat_name: options?.featName ?? null,
      spell_id: options?.spellId ?? null,
      spell_name: options?.spellName ?? null,
      spell_level: Number.isFinite(Number(options?.spellLevel)) ? Number(options.spellLevel) : null,
      is_focus_spell: options?.isFocusSpell === true,
      item: options?.item ?? null,
    };

    let targetRef = null;
    if (options?.targetId != null) {
      const targetEntity = this.entityManager?.getEntity?.(options.targetId) || null;
      targetRef = String(
        targetEntity?.dcEntityRef
        || targetEntity?.instanceId
        || options.targetId
        || ''
      ).trim() || null;
    }

    const refreshAuthoritativeState = async () => {
      if (!coordinator?.api?.getState || !coordinator?.applyAuthoritativeUpdate) {
        return false;
      }
      try {
        const fallbackRuntimeContext = this.resolveLaunchCharacterRuntimeContext?.() || {};
        const requestActorRef = normalizeAuthoritativeStateActorRef(actorRef, { runtimeContext: fallbackRuntimeContext });
        const state = await coordinator.api.getState({
          actor: requestActorRef || undefined,
          characterId: runtimeCharacterId,
        });
        if (state?.success) {
          coordinator.applyAuthoritativeUpdate(state);
          return true;
        }
      } catch (refreshErr) {
        console.warn('[GameShell] performCombatAction authoritative refresh failed', refreshErr);
      }
      return false;
    };

    const sendWithCurrentStateVersion = () => coordinator.api.sendAction(actionType, actorRef, params, {
      target: targetRef || undefined,
      stateVersion: coordinator.phaseManager?.stateVersion,
    });

    let data = null;
    try {
      data = await sendWithCurrentStateVersion();
    } catch (err) {
      const payload = err?.payload && typeof err.payload === 'object' ? err.payload : null;
      const errorText = String(payload?.error || err?.message || '').trim();
      if (payload && /State version mismatch/i.test(errorText)) {
        coordinator.applyAuthoritativeUpdate?.(payload);
        try {
          data = await sendWithCurrentStateVersion();
        } catch (retryErr) {
          const retryPayload = retryErr?.payload && typeof retryErr.payload === 'object' ? retryErr.payload : null;
          if (retryPayload) {
            await refreshAuthoritativeState();
            console.warn('[GameShell] performCombatAction rejected after resync', {
              actionType,
              error: retryPayload?.error,
              result: retryPayload?.result,
            });
            return null;
          }
          console.error('[GameShell] performCombatAction coordinator retry failed', retryErr);
          return null;
        }
      } else if (payload) {
        await refreshAuthoritativeState();
        console.warn('[GameShell] performCombatAction rejected', {
          actionType,
          error: payload?.error,
          result: payload?.result,
        });
        return null;
      } else {
        console.error('[GameShell] performCombatAction coordinator call failed', err);
        this.notifyServerUnavailable();
        return null;
      }
    }

    if (!data?.success) {
      console.warn('[GameShell] performCombatAction rejected', { actionType, error: data?.error, result: data?.result });
      return null;
    }

    coordinator.applyAuthoritativeUpdate?.(data);
    if (
      actorEntity
      && ['step', 'stride'].includes(actionType)
      && Number.isFinite(Number(options?.targetHex?.q))
      && Number.isFinite(Number(options?.targetHex?.r))
    ) {
      this.applyLocalEntityPlacement(
        actorEntity,
        String(this.resolveActiveRoomId() || ''),
        Number(options.targetHex.q),
        Number(options.targetHex.r),
      );
    }
    const questUpdates = Array.isArray(data?.quest_updates) ? data.quest_updates : [];
    if (questUpdates.length > 0) {
      await this.applyQuestUpdates(questUpdates);
      const launchCharacterId = this.resolveLaunchCharacterStateId();
      if (launchCharacterId > 0) {
        await this.loadCharacterFromApi(launchCharacterId);
      }
    }
    if (data?.game_state?.encounter_id) {
      this._setStateValue('encounterId', data.game_state.encounter_id);
    }
    this._setStateValue('serverCombatMode', true);
    return data;
  }

  // --- adapted from hexmap.js ---
  async loadCharacterFromApi(characterId) {
    if (!characterId || !this.bus) {
      return null;
    }

    const resolvedCharacterId = Number(characterId) || 0;
    if (resolvedCharacterId <= 0) {
      return null;
    }

    this.bus?.emit('character:sheet-requested', { characterId: resolvedCharacterId });

    const requestSequence = ++this._characterRefreshSequence;
    const runtimeContext = this.resolveLaunchCharacterRuntimeContext();
    const query = new URLSearchParams();
    if (runtimeContext.campaignId) {
      query.set('campaignId', String(runtimeContext.campaignId));
    }
    if (runtimeContext.instanceId) {
      query.set('instanceId', String(runtimeContext.instanceId));
    }

    const requestUrl = `/api/character/${encodeURIComponent(resolvedCharacterId)}/state${query.toString() ? `?${query.toString()}` : ''}`;

    try {
      const response = await fetch(requestUrl, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result?.success || !result?.data) {
        throw new Error(result?.error || `Character state refresh failed (${response.status}).`);
      }
      if (requestSequence !== this._characterRefreshSequence) {
        return null;
      }

      const hydrated = result.data;
      this.launchCharacter = {
        ...hydrated,
        id: Number(hydrated.characterId || hydrated.id || resolvedCharacterId) || resolvedCharacterId,
        instanceId: hydrated.instanceId || runtimeContext.instanceId || this.launchCharacter?.instanceId || null,
        instance_id: hydrated.instanceId || runtimeContext.instanceId || this.launchCharacter?.instance_id || null,
      };
      this.characterData = this.launchCharacter;
      this.syncLaunchCharacterRuntimeFromEntity(this._getStateValue('selectedEntity'));
      this.bus?.emit('character:updated', { launchCharacter: this.launchCharacter });

      if (resolvedCharacterId === this.resolveLaunchCharacterStateId()) {
        await this.refreshCharacterInventoryFromApi({
          ...this.resolveLaunchCharacterRuntimeContext(),
          characterId: resolvedCharacterId,
        });
      }

      return this.launchCharacter;
    } catch (error) {
      console.error('[GameShell] loadCharacterFromApi failed', error);
      return null;
    }
  }

  async refreshCharacterRuntimeState(context = {}, reason = 'character-panel-runtime-refresh') {
    const launchCharacterId = this.resolveLaunchCharacterStateId();
    const runtimeCharacterId = Number(
      context?.runtimeCharacterId
      || context?.sheetCharacterId
      || context?.characterId
      || this.launchCharacter?.id
      || this.launchContext?.character_id
      || 0
    ) || 0;
    if (runtimeCharacterId <= 0 || launchCharacterId <= 0 || runtimeCharacterId !== launchCharacterId) {
      return null;
    }

    const now = Date.now();
    if ((now - this._lastRosterRuntimeRefreshAt) < 350) {
      return null;
    }
    this._lastRosterRuntimeRefreshAt = now;
    const refreshed = await this.loadCharacterFromApi(runtimeCharacterId);
    if (!refreshed) {
      console.debug('[GameShell] runtime state refresh returned empty payload', {
        reason,
        runtimeCharacterId,
      });
    }
    return refreshed;
  }

  // --- ported from hexmap.js ---
  async refreshCharacterInventoryFromApi(context) {
    if (!context?.characterId || typeof fetch !== 'function') {
      return;
    }

    const requestSequence = ++this._inventoryRefreshSequence;

    const params = new URLSearchParams();
    if (context.campaignId) {
      params.set('campaign_id', String(context.campaignId));
    }
    if (context.instanceId) {
      params.set('instance_id', String(context.instanceId));
    }
    const requestUrl = `/api/inventory/character/${encodeURIComponent(context.characterId)}${params.toString() ? `?${params.toString()}` : ''}`;

    try {
      const response = await fetch(requestUrl, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result?.success || !result?.inventory) {
        throw new Error(result?.error || result?.message || 'Inventory refresh failed.');
      }
      if (requestSequence !== this._inventoryRefreshSequence) {
        return;
      }

      const currentContext = this.currentCharacterInventoryContext;
      if (!currentContext || String(currentContext.characterId || '') !== String(context.characterId || '') || Number(currentContext.campaignId || 0) !== Number(context.campaignId || 0)) {
        return;
      }

      const nextInventory = normalizeInventoryState(result.inventory || {}, currentContext.currency || context.currency || {});
      this.currentCharacterInventoryContext = {
        ...currentContext,
        inventory: nextInventory,
        currency: nextInventory.currency || currentContext.currency || context.currency || {},
      };
      // Emit bus event so InventoryPanel and MerchantPanel react
      this.bus?.emit('inventory:changed', {
        ...this.currentCharacterInventoryContext,
        inventory: nextInventory,
        currency: nextInventory.currency || currentContext.currency || context.currency || {},
        characterId: context.characterId,
        campaignId: context.campaignId || currentContext.campaignId || null,
      });
      if (this.activeGameShellTab === 'merchant') {
        this.panels?.merchant?.loadMerchantPanel?.(true);
      }
    } catch (error) {
      console.error('Character inventory refresh failed', error);
    }
  }

  // --- ported from hexmap.js ---
  prefetchConnectedRoomContext(limit = 2) {
    const campaignId    = this.launchContext?.campaign_id ?? null;
    const currentRoomId = this.activeRoomId ?? null;
    const characterId   = Number(this.launchCharacter?.id || 0) || null;
    const mapId         = String(
      this.dungeonData?.map_id
      || this.launchContext?.map_id
      || this.stateManager?.get?.('mapId')
      || ''
    ).trim() || null;
    const connections   = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
    if (!campaignId || !currentRoomId || !connections.length) {
      return;
    }

    const nextRoomIds = [];
    connections.forEach((connection) => {
      if (connection?.is_passable === false) {
        return;
      }
      const fromRoomId = connection?.from_room;
      const toRoomId   = connection?.to_room;
      if (fromRoomId === currentRoomId && toRoomId) {
        nextRoomIds.push(String(toRoomId));
      } else if (toRoomId === currentRoomId && fromRoomId) {
        nextRoomIds.push(String(fromRoomId));
      }
    });

    Array.from(new Set(nextRoomIds)).filter(Boolean).slice(0, limit).forEach((roomId) => {
      const context = { campaignId, roomId, characterId, mapId };
      void this.fetchRoomChatHistoryForContext(context, { channelKey: 'room' }).catch((error) => {
        console.debug(`Skipped connected-room chat warm for ${roomId}:`, error?.message || error);
      });
      void this.fetchRoomViewPayload(campaignId, roomId).catch((error) => {
        console.debug(`Skipped connected-room view warm for ${roomId}:`, error?.message || error);
      });
    });
  }

  // --- ported from hexmap.js ---
  prefetchSessionViews(views = ['narrative', 'party', 'gm-private', 'system-log']) {
    views.forEach((view) => {
      if (!view || view === this.activeSessionView || view === 'room') {
        return;
      }
      void this.fetchSessionViewData(view).catch((error) => {
        console.debug(`Skipped prefetch for ${view}:`, error?.message || error);
      });
    });
  }

  // --- ported from hexmap.js ---
  reset() {
    this._targetPickSession = null;
    this.state = {
      selectedEntity: null,
      selectedHex: null,
      hoveredHex: null,
      activeRoomId: this.activeRoomId || this.launchContext?.room_id || null,
      movementRange: null,
      movementRangeOverlay: null,
      combatActive: false,
      encounterId: null,
      latestEncounterState: null,
      runtimeSnapshotId: null,
      runtimeSyncHealth: 'healthy',
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
