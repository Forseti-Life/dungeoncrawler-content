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
import { EncounterSystem } from './systems/EncounterSystem.js?v=20260811-v2-targeting-debug-3';
import { NavigationSystem } from './systems/NavigationSystem.js?v=20260728-v2-nav-transition-receipt-4';
import { PlayerAutomation } from './systems/PlayerAutomation.js?v=20260608-v2-chat-persistence-dev-1';
import { QuestSystem } from './systems/QuestSystem.js?v=20260608-v2-quest-summary-merge-2';
import { MerchantPanel } from './panels/MerchantPanel.js';
import { CombatPanel } from './panels/CombatPanel.js?v=20260811-v2-turn-sync-ui-11';
import { ActionRailPanel } from './panels/ActionRailPanel.js?v=20260811-v2-target-pick-hardening-15';
import { ChatPanel } from './panels/ChatPanel.js?v=20260812-v2-map-status-centralization-1';
import { QuestPanel } from './panels/QuestPanel.js?v=20260723-v2-quest-storyline-grouping-2';
import { InventoryPanel } from './panels/InventoryPanel.js';
import { CharacterPanel } from './panels/CharacterPanel.js?v=20260731-v2-relationships-ui-11';
import { RoomViewPanel } from './panels/RoomViewPanel.js';
import { StatusPanel } from './panels/StatusPanel.js?v=20260812-v2-map-status-centralization-1';
import { normalizeInventoryState } from './utils/inventory-utils.js';
import { normalizeQuestSummaryPayload } from './utils/quest-utils.js?v=20260607-quest-summary-const-4';
import { SpriteService } from '../SpriteService.js';
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
const HEXMAP_UI_BUILD_VERSION = '20260810-rm-ui-14';

export class GameShell {
  /**
   * @param {HTMLElement} container - Root DOM container for hexmap-v2
   * @param {object} rawSettings    - drupalSettings.dungeoncrawlerContent subset
   */
  constructor(container, rawSettings = {}) {
    console.info('[GameShell] module loaded', {
      version: '20260805-v2-action-rail-runtime-contract-fix-1',
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
  }

  /**
   * Refresh quest journal state from the server and emit the canonical summary.
   *
   * @returns {Promise<boolean>}
   *   TRUE when refreshed successfully; otherwise FALSE.
   */
  async refreshQuestJournalFromApi(context = {}) {
    const campaignId = this.resolveCampaignId();
    if (!campaignId || typeof fetch !== 'function') {
      return false;
    }

    const requestedCharacterId = Number(context?.characterId || 0);
    const hasExplicitCharacterScope = Object.prototype.hasOwnProperty.call(context || {}, 'characterId');
    if (hasExplicitCharacterScope && requestedCharacterId <= 0) {
      this.questSummary = normalizeQuestSummaryPayload({
        schema_version: 'quest-summary-v2',
        location_id: this.resolveActiveRoomId() || '',
        active: [],
        offers: [],
        leads: [],
        completed: [],
        management_tree: [],
      });
      this.bus?.emit('quest:progress-updated', { questSummary: this.questSummary, characterId: null, campaignId });
      return true;
    }
    const runtimeCharacterId = Number(this.resolveLaunchCharacterRuntimeContext?.().characterId || 0);
    const characterId = requestedCharacterId > 0
      ? requestedCharacterId
      : (runtimeCharacterId > 0
        ? runtimeCharacterId
        : Number(this.launchContext?.character_id || 0));
    const endpoint = characterId > 0
      ? `/api/campaign/${campaignId}/character/${characterId}/quest-journal`
      : `/api/campaign/${campaignId}/quest-journal`;

    try {
      const response = await fetch(endpoint, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        return false;
      }

      const payload = await response.json().catch(() => null);
      if (!payload?.success) {
        return false;
      }

      if (payload.quest_summary && typeof payload.quest_summary === 'object') {
        this.questSummary = normalizeQuestSummaryPayload(payload.quest_summary);
      } else {
        const tracking = Array.isArray(payload.tracking) ? payload.tracking : [];
        const active = [];
        const offers = [];
        const leads = [];
        const completed = [];
        tracking.forEach((row) => {
          const status = String(row?.status || '').trim().toLowerCase();
          const completedAt = Number(row?.completed_at || 0);
          if (status === 'completed' || completedAt > 0) {
            completed.push(row);
          } else if (status === 'offered') {
            offers.push(row);
          } else if (status === 'lead') {
            leads.push(row);
          } else {
            active.push(row);
          }
        });
        this.questSummary = normalizeQuestSummaryPayload({
          schema_version: 'quest-summary-v2',
          location_id: this.resolveActiveRoomId() || '',
          active,
          offers,
          leads,
          completed,
          management_tree: [],
        });
      }

      this.bus?.emit('quest:progress-updated', { questSummary: this.questSummary, characterId: characterId || null, campaignId });
      return true;
    } catch (error) {
      console.warn('[GameShell] refreshQuestJournalFromApi failed', { campaignId, error });
      return false;
    }
  }

  /**
   * Apply quest updates from authoritative chat payloads.
   * First refreshes from quest-journal API; falls back to local merge if needed.
   *
   * @param {Array} questUpdates
   * @returns {Promise<boolean>}
   */
  async applyQuestUpdates(questUpdates = []) {
    if (!Array.isArray(questUpdates) || questUpdates.length === 0) {
      return false;
    }

    const refreshed = await this.refreshQuestJournalFromApi();
    if (refreshed) {
      return true;
    }

    if (!this.questSummary || typeof this.questSummary !== 'object') {
      this.questSummary = { active: [], offers: [], leads: [], completed: [] };
    }
    ['active', 'offers', 'leads', 'completed'].forEach((bucket) => {
      if (!Array.isArray(this.questSummary?.[bucket])) {
        this.questSummary[bucket] = [];
      }
    });

    questUpdates.forEach((q) => {
      if (!q || typeof q !== 'object') {
        return;
      }

      const questKey = String(q.quest_id || q.quest_key || q.id || '').trim();
      if (!questKey) {
        return;
      }

      const status = String(q.status || 'active').trim().toLowerCase();
      const completedAt = Number(q?.completed_at || 0);
      const targetBucket = status === 'completed' || completedAt > 0
        ? 'completed'
        : (status === 'offered'
        ? 'offers'
        : (status === 'lead' ? 'leads' : 'active'));
      const updated = { ...q, objectives: _flattenQuestObjectives(q) };

      ['active', 'offers', 'leads', 'completed'].forEach((bucket) => {
        this.questSummary[bucket] = this.questSummary[bucket].filter(
          (entry) => String(entry?.quest_id || entry?.quest_key || entry?.id || '').trim() !== questKey
        );
      });
      this.questSummary[targetBucket].push(updated);
    });

    this.bus?.emit('quest:progress-updated', { questSummary: this.questSummary });
    return true;
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
      });
  }

  buildRuntimeBundleQueryForRoom(roomId = '', options = {}) {
    const normalizedRoomId = String(roomId || '').trim();
    const campaignId = Number(this.resolveCampaignId?.() || this.launchContext?.campaign_id || 0) || 0;
    const characterId = Number(this.launchContext?.character_id || 0) || 0;
    const mapId = String(
      options?.mapId
      || this.launchContext?.map_id
      || this.dungeonData?.map_id
      || this.dungeonData?.dungeon_id
      || ''
    ).trim();
    const dungeonLevelId = String(
      options?.dungeonLevelId
      || this.launchContext?.dungeon_level_id
      || this.dungeonData?.level_id
      || ''
    ).trim();
    const nextRoomId = String(options?.nextRoomId || '').trim();
    const startQ = Number.isFinite(Number(options?.startQ))
      ? Number(options.startQ)
      : Number(this.launchContext?.start_q ?? 0);
    const startR = Number.isFinite(Number(options?.startR))
      ? Number(options.startR)
      : Number(this.launchContext?.start_r ?? 0);

    const query = {
      campaign_id: campaignId,
      start_q: Number.isFinite(startQ) ? startQ : 0,
      start_r: Number.isFinite(startR) ? startR : 0,
    };
    if (characterId > 0) {
      query.character_id = characterId;
    }
    if (normalizedRoomId !== '') {
      query.room_id = normalizedRoomId;
    }
    if (mapId !== '') {
      query.map_id = mapId;
    }
    if (dungeonLevelId !== '') {
      query.dungeon_level_id = dungeonLevelId;
    }
    if (nextRoomId !== '') {
      query.next_room_id = nextRoomId;
    }

    return query;
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
    const pending = this.dungeonData?.pending_room_generation;
    if (!pending || typeof pending !== 'object') {
      return;
    }
    const campaignId = Number(this.launchContext?.campaign_id || 0);
    const characterId = Number(this.launchContext?.character_id || 0);
    const roomId = String(pending.room_id || '').trim();
    const originRoomId = String(pending.origin_room_id || '').trim();
    if (!campaignId || !roomId) {
      return;
    }

    console.log('[GameShell] Auto-generating quest destination room:', roomId);
    this.bus?.emit('chat:system-message', {
      speaker: 'System',
      kind: 'info',
      text: `Generating quest destination: ${roomId}...`,
    });

    try {
      const response = await fetch(`/api/campaign/${campaignId}/navigation/locations/request`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({
          destination: roomId,
          origin_room_id: originRoomId || roomId,
          character_id: characterId || undefined,
        }),
      });
      const result = await response.json().catch(() => ({}));
      if (result?.success && result?.data?.navigation?.target_room_id) {
        const nav = result.data.navigation;
        console.log('[GameShell] Quest destination generated:', nav.target_room_id);
        // Apply the navigation result to update dungeon data and transition
        if (typeof this.applyNavigationResult === 'function') {
          this.applyNavigationResult(nav);
        } else {
          this.bus?.emit('navigation:apply-result', { navigation: nav });
        }
      } else {
        console.warn('[GameShell] Quest destination generation failed:', result?.error || 'unknown');
        this.bus?.emit('chat:system-message', {
          speaker: 'System',
          kind: 'error',
          text: `Could not generate destination: ${result?.error || 'Room generation failed'}`,
        });
      }
    } catch (err) {
      console.warn('[GameShell] Quest destination generation error:', err?.message || err);
    }
  }


  _initApiHandlers() {
    // Tab change → trigger appropriate API load
    this._tabChangedHandler = (e) => this._onTabChanged(e.detail?.tabId ?? '');
    window.addEventListener('dungeoncrawler:game-shell-tab-changed', this._tabChangedHandler);

    // Chat submit → POST to server, emit response lines
    this.bus.on('user:chat-submitted', (data) => this._handleChatSubmit(data));

    // Session view data request from ChatPanel
    this.bus.on('user:session-view-requested', ({ view, options } = {}) => {
      if (!view) return;
      void this.fetchSessionViewData(view, options ?? {}).then((data) => {
        this.bus.emit('session:view-data', { view, data });
      }).catch((err) => {
        console.error(`fetchSessionViewData(${view}) failed:`, err?.message);
      });
    });

    // Session message submit from ChatPanel non-room view
    this.bus.on('user:session-message-submitted', (d) => this._postSessionViewMessage(d));

    // ChatPanel requests room chat history refresh
    this.bus.on('user:chat-history-requested', () => this._loadChatHistory());
    this.bus.on('user:target-pick-requested', (data) => this._beginTargetPickSession(data || {}));

    // Shell-owned room-view refresh entrypoint. Legacy reload event is bridged here.
    this.bus.on('room:view-refresh-intent', (opts) => this._handleRoomViewRefreshIntent(opts, 'room:view-refresh-intent'));
    this.bus.on('room:view-reload-requested', (opts) => this._handleRoomViewRefreshIntent(opts, 'room:view-reload-requested'));

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
      if (charId) this.bus.emit('character:sheet-requested', { characterId: charId });
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
    const campaignId = Number(this.launchContext?.campaign_id || 0);
    const statusEl = document.getElementById('campaign-settings-status');
    if (!campaignId) {
      if (statusEl) statusEl.textContent = 'Campaign settings are unavailable outside campaign mode.';
      return;
    }
    if (!force && this._campaignSettingsLoaded && this._campaignSettingsPayload) {
      this._renderCampaignSettings(this._campaignSettingsPayload);
      return;
    }
    if (this._campaignSettingsLoading) {
      return;
    }

    this._campaignSettingsLoading = true;
    if (statusEl) statusEl.textContent = 'Loading campaign settings...';
    try {
      const response = await fetch(`/api/campaign/${campaignId}/settings`, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        const error = String(payload?.error || `HTTP ${response.status}`).trim();
        if (statusEl) statusEl.textContent = `Unable to load settings: ${error}`;
        return;
      }
      this._campaignSettingsPayload = payload;
      this._campaignSettingsLoaded = true;
      this._renderCampaignSettings(payload);
    } catch (error) {
      if (statusEl) statusEl.textContent = `Unable to load settings: ${error?.message || 'network error'}`;
    } finally {
      this._campaignSettingsLoading = false;
    }
  }

  /**
   * Render settings payload into the settings tab.
   */
  _renderCampaignSettings(payload) {
    const statusEl = document.getElementById('campaign-settings-status');
    const titleEl = document.getElementById('campaign-settings-title');
    const memberListEl = document.getElementById('campaign-member-list');
    const playerBtn = document.getElementById('campaign-mode-player');
    const gmBtn = document.getElementById('campaign-mode-gm');
    if (!memberListEl || !playerBtn || !gmBtn) {
      return;
    }

    const campaignName = String(payload?.settings?.campaign_name || '').trim();
    if (titleEl && campaignName) {
      titleEl.textContent = `${campaignName} settings`;
    }

    const canManage = payload?.capabilities?.can_manage === true;
    const canUseGmMode = payload?.capabilities?.can_use_gm_mode === true;
    const canUsePlayerMode = payload?.capabilities?.can_use_player_mode !== false;
    const mode = String(payload?.settings?.mode || 'player').trim().toLowerCase() === 'gm' ? 'gm' : 'player';
    playerBtn.classList.toggle('btn-primary', mode === 'player');
    playerBtn.classList.toggle('btn-secondary', mode !== 'player');
    gmBtn.classList.toggle('btn-primary', mode === 'gm');
    gmBtn.classList.toggle('btn-secondary', mode !== 'gm');
    gmBtn.disabled = !canUseGmMode;
    playerBtn.disabled = !canUsePlayerMode;

    playerBtn.onclick = () => this._setCampaignMode('player');
    gmBtn.onclick = () => this._setCampaignMode('gm');

    this.campaignAccess = this._normalizeCampaignAccess({
      ...(payload?.campaign_access || {}),
      campaign_id: Number(payload?.campaign_id || this.campaignAccess?.campaign_id || 0),
      current_mode: mode,
      can_use_player_mode: canUsePlayerMode,
      can_use_gm_mode: canUseGmMode,
    });
    this.activeCampaignMode = mode;
    this._applyCampaignModeGates();

    const members = Array.isArray(payload?.members) ? payload.members : [];
    memberListEl.innerHTML = '';
    if (!members.length) {
      memberListEl.innerHTML = '<p class="muted">No campaign members found.</p>';
    } else {
      members.forEach((member) => {
        const uid = Number(member?.uid || 0);
        if (!uid) return;
        const role = String(member?.role || 'player').trim().toLowerCase();
        const status = String(member?.status || 'active').trim().toLowerCase();
        const name = String(member?.display_name || `User ${uid}`).trim();
        const email = String(member?.email || '').trim();
        const row = document.createElement('div');
        row.className = 'campaign-settings-panel__member-row';

        const identity = document.createElement('div');
        const identityName = document.createElement('strong');
        identityName.textContent = name;
        const identityMeta = document.createElement('p');
        identityMeta.className = 'campaign-settings-panel__member-meta';
        identityMeta.textContent = email || `UID ${uid}`;
        identity.appendChild(identityName);
        identity.appendChild(identityMeta);

        const roleBadge = document.createElement('span');
        roleBadge.className = 'pill pill-muted';
        roleBadge.textContent = role === 'owner_gm' ? 'owner_gm' : role;

        const roleControl = document.createElement('select');
        roleControl.className = 'merchant-trade-panel__select';
        roleControl.innerHTML = `
          <option value="player"${role === 'player' ? ' selected' : ''}>player</option>
          <option value="gm"${role === 'gm' ? ' selected' : ''}>gm</option>
        `;
        roleControl.disabled = !canManage || role === 'owner_gm' || status === 'revoked';
        roleControl.onchange = () => this._updateCampaignMemberRole(uid, roleControl.value);

        row.appendChild(identity);
        row.appendChild(roleBadge);
        row.appendChild(roleControl);
        memberListEl.appendChild(row);
      });
    }

    if (statusEl) {
      statusEl.textContent = canManage
        ? 'You can manage campaign members and GM mode.'
        : 'You can switch your own mode; member management is GM-only.';
    }
  }

  /**
   * Persist user mode preference in campaign settings.
   */
  async _setCampaignMode(mode) {
    const normalizedMode = String(mode || '').trim().toLowerCase();
    if (!['player', 'gm'].includes(normalizedMode)) {
      return;
    }
    const campaignId = Number(this.launchContext?.campaign_id || 0);
    if (!campaignId) {
      return;
    }
    const statusEl = document.getElementById('campaign-settings-status');
    if (statusEl) statusEl.textContent = 'Saving mode preference...';

    try {
      const response = await fetch(`/api/campaign/${campaignId}/settings/mode`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mode: normalizedMode }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        const error = String(payload?.error || `HTTP ${response.status}`).trim();
        if (statusEl) statusEl.textContent = `Unable to save mode: ${error}`;
        return;
      }
      this.activeCampaignMode = normalizedMode;
      this.campaignAccess = this._normalizeCampaignAccess({
        ...this.campaignAccess,
        current_mode: normalizedMode,
      });
      this._applyCampaignModeGates();
      await this._loadCampaignSettings(true);
    } catch (error) {
      if (statusEl) statusEl.textContent = `Unable to save mode: ${error?.message || 'network error'}`;
    }
  }

  _normalizeCampaignAccess(input = {}) {
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
    const mode = String(this.activeCampaignMode || this.campaignAccess?.current_mode || 'player').trim().toLowerCase() === 'gm'
      ? 'gm'
      : 'player';
    const canUseGmMode = this.campaignAccess?.can_use_gm_mode === true;
    const effectiveMode = (mode === 'gm' && canUseGmMode) ? 'gm' : 'player';
    this.activeCampaignMode = effectiveMode;
    this.campaignAccess = this._normalizeCampaignAccess({
      ...this.campaignAccess,
      current_mode: effectiveMode,
    });

    const shell = this.container?.closest?.('[data-game-shell]')
      || this.container?.querySelector?.('[data-game-shell]')
      || null;
    if (shell) {
      shell.dataset.campaignMode = effectiveMode;
      shell.dataset.canUseGmMode = canUseGmMode ? '1' : '0';
    }

    const gmSessionTab = this.container?.querySelector?.('.session-view-tab[data-view="gm-private"]') || null;
    const gmViewEnabled = canUseGmMode && effectiveMode === 'gm';
    if (gmSessionTab) {
      gmSessionTab.hidden = !gmViewEnabled;
      gmSessionTab.setAttribute('aria-hidden', gmViewEnabled ? 'false' : 'true');
      gmSessionTab.tabIndex = gmViewEnabled ? 0 : -1;
    }
    if (!gmViewEnabled && this.panels?.chat?.activeSessionView === 'gm-private') {
      this.panels.chat.switchSessionView('room');
    }
  }

  /**
   * Persist campaign member role assignment.
   */
  async _updateCampaignMemberRole(memberUid, role) {
    const campaignId = Number(this.launchContext?.campaign_id || 0);
    const uid = Number(memberUid || 0);
    const normalizedRole = String(role || '').trim().toLowerCase();
    if (!campaignId || !uid || !['player', 'gm'].includes(normalizedRole)) {
      return;
    }
    const statusEl = document.getElementById('campaign-settings-status');
    if (statusEl) statusEl.textContent = 'Saving member role...';

    try {
      const response = await fetch(`/api/campaign/${campaignId}/settings/members/${uid}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ role: normalizedRole, status: 'active' }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        const error = String(payload?.error || `HTTP ${response.status}`).trim();
        if (statusEl) statusEl.textContent = `Unable to update member: ${error}`;
        return;
      }
      await this._loadCampaignSettings(true);
    } catch (error) {
      if (statusEl) statusEl.textContent = `Unable to update member: ${error?.message || 'network error'}`;
    }
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
    const requestedRoomId = String(options?.roomId || this.activeRoomId || '').trim();
    const activeRoomId = String(this.activeRoomId || '').trim();
    if (!requestedRoomId || !activeRoomId || requestedRoomId !== activeRoomId) {
      return;
    }
    this._loadRoomView({
      ...options,
      roomId: requestedRoomId,
      preserveExisting: options?.preserveExisting !== false,
    });
    if (eventName === 'room:view-reload-requested') {
      console.debug('[GameShell] room:view-reload-requested is legacy; prefer room:view-refresh-intent');
    }
  }

  /**
   * Load chat history for the active room and emit chat:history-loaded.
   * @private
   */
  async _loadChatHistory() {
    const campaignId = this.launchContext?.campaign_id;
    const roomId     = this.activeRoomId;
    const requestRoomId = String(roomId || '').trim();
    const charId     = Number(
      this.resolveLaunchCharacterRuntimeContext?.().characterId
      || this.launchCharacter?.id
      || this.launchContext?.character_id
      || 0
    ) || null;
    if (!campaignId || !roomId) {
      console.warn('[GameShell] _loadChatHistory: missing campaignId or roomId', { campaignId, roomId });
      return;
    }
    const mapId = String(
      this.hexmap?.dungeonData?.map_id
      || this.hexmap?.launchContext?.map_id
      || this.launchContext?.map_id
      || this.stateManager?.get?.('mapId')
      || ''
    ).trim();
    const requestKey = `${campaignId}:${requestRoomId}:${charId || 0}:${mapId || ''}`;
    if (!(this._chatHistoryInflight instanceof Map)) {
      this._chatHistoryInflight = new Map();
    }
    if (!(this._chatHistoryLastLoadedAt instanceof Map)) {
      this._chatHistoryLastLoadedAt = new Map();
    }
    if (this._chatHistoryInflight.has(requestKey)) {
      return this._chatHistoryInflight.get(requestKey);
    }
    const loadedAt = Number(this._chatHistoryLastLoadedAt.get(requestKey) || 0);
    if (loadedAt > 0 && (Date.now() - loadedAt) < 1200) {
      return;
    }

    const requestToken = ++this._chatHistoryRequestToken;
    console.log('[GameShell] _loadChatHistory', { campaignId, roomId, requestToken });

    const request = (async () => {
      try {
        let url = `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/chat`;
        const params = new URLSearchParams();
        if (charId) params.set('character_id', String(charId));
        if (mapId) params.set('map_id', mapId);
        if (params.toString()) url += `?${params.toString()}`;
        const resp = await fetch(url, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (requestToken !== this._chatHistoryRequestToken) {
          return;
        }
        if (!resp.ok) {
          const responseText = await resp.text().catch(() => '');
          console.error('[GameShell] _loadChatHistory failed', {
            campaignId,
            roomId,
            characterId: charId,
            status: resp.status,
            body: responseText,
          });
          return;
        }
        const result = await resp.json().catch(() => ({}));
        if (requestToken !== this._chatHistoryRequestToken) {
          return;
        }
        if (!result?.success || !Array.isArray(result.data?.messages)) {
          console.warn('[GameShell] _loadChatHistory: unexpected response', { ok: resp.ok, success: result?.success, messageCount: result?.data?.messages?.length });
          return;
        }

        const payloadRoomId = String(result?.data?.roomId || result?.data?.room_id || requestRoomId).trim();
        const activeRoomId = String(this.activeRoomId || '').trim();
        if (payloadRoomId && activeRoomId && payloadRoomId !== activeRoomId) {
          console.info('[GameShell] _loadChatHistory: stale response dropped', {
            requestedRoomId: requestRoomId,
            payloadRoomId,
            activeRoomId,
          });
          return;
        }

        this._chatHistoryLoaded = true;
        this._chatHistoryLastLoadedAt.set(requestKey, Date.now());
        console.log('[GameShell] _loadChatHistory: loaded', { lineCount: result.data.messages.length });
        this.bus.emit('chat:history-loaded', {
          ...result,
          roomId: payloadRoomId || requestRoomId,
          campaignId: Number(campaignId) || null,
          requestToken,
        });
        this.queueRoomEntryAcknowledgement({
          campaignId: Number(campaignId) || null,
          roomId: payloadRoomId || requestRoomId,
          characterId: charId,
          mapId,
        });
      } catch (_) {
        // Chat history is best-effort; no user-facing error
      } finally {
        if (this._chatHistoryInflight.get(requestKey) === request) {
          this._chatHistoryInflight.delete(requestKey);
        }
      }
    })();

    this._chatHistoryInflight.set(requestKey, request);
    await request;
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
    const campaignId = this.launchContext?.campaign_id;
    const roomId     = this.activeRoomId;
    if (!campaignId || !roomId) {
      console.warn('[GameShell] _loadRoomView: missing campaignId or roomId', { campaignId, roomId });
      return;
    }

    const force          = Boolean(options.force);
    const preserveExisting = Boolean(options.preserveExisting);
    const viewKey        = `${campaignId}:${roomId}`;
    const visualRoom     = this.mapVisualState?.topology?.rooms?.[roomId] ?? {};
    const payloadRoomBase = { ...visualRoom, room_id: visualRoom?.room_id || roomId };

    // Dedup: skip if same key already loaded unless forced
    if (!force && this._roomViewLastKey === viewKey && this._roomViewHasContent) {
      console.log('[GameShell] _loadRoomView: skipped (cached)', { viewKey });
      return;
    }

    // In-flight dedup: return same promise if already fetching
    if (this._roomViewInflight.has(viewKey)) {
      console.log('[GameShell] _loadRoomView: skipped (inflight)', { viewKey });
      return;
    }

    this._roomViewLastKey = viewKey;
    const token = ++this._roomViewRequestToken;
    const backendRequestId = `room-view-${viewKey}-${token}`;
    console.log('[GameShell] _loadRoomView', { campaignId, roomId, force, preserveExisting });

    // Show "Generating" immediately unless preserving existing gallery
    if (!preserveExisting || !this._roomViewHasContent) {
      this.bus.emit('room:view-loaded', {
        room: payloadRoomBase,
        viewState: { statusLabel: 'Generating', placeholderText: 'Loading room scene...', entries: [] },
      });
    }

    const request = (async () => {
      this.bus.emit('game:backend-request-start', {
        requestId: backendRequestId,
        label: 'Waiting for room view generation...',
        source: 'room-view',
      });
      const resp = await fetch(
        `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/view-image`,
        {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        },
      );
      if (token !== this._roomViewRequestToken) return;
      if (!resp.ok) {
        this.bus.emit('game:server-unavailable', { message: `Room view unavailable (${resp.status})` });
        return;
      }
      const result = await resp.json().catch(() => ({}));
      if (!result?.success || !result?.data) {
        console.warn('[GameShell] _loadRoomView: bad result', { success: result?.success, hasData: !!result?.data });
        return;
      }

      const entries = Array.isArray(result.data.entries)
        ? result.data.entries.filter((e) => e?.image?.url || e?.image?.data_uri)
        : [];
      const first = entries[0];
      const sceneImageUrl = first?.image?.url ?? first?.image?.data_uri ?? null;

      const apiRoom = _isPlainObject(result.data.room) ? result.data.room : {};
      const payloadRoom = _mergeRoomMetadata(visualRoom, apiRoom, roomId);
      const roomName = payloadRoom?.name ?? visualRoom?.name ?? roomId;

      const dataStatus = String(result.data.status || '').toLowerCase();
      this._roomViewHasContent = entries.length > 0;

      const statusLabel = entries.length > 0
        ? `${entries.length} Scene${entries.length === 1 ? '' : 's'}`
        : (dataStatus === 'pending' ? 'Generating' : (result.data.available === false ? 'Unavailable' : 'Pending'));
      const placeholderText = entries.length > 0
        ? ''
        : (dataStatus === 'pending'
          ? 'Room scene is being generated — checking again shortly...'
          : (result.data.message || 'No room view image is available yet.'));

      console.log('[GameShell] _loadRoomView: result', {
        rawEntries: result.data.entries?.length ?? 0,
        filteredEntries: entries.length,
        sceneImageUrl: !!sceneImageUrl,
        available: result.data.available,
        status: dataStatus,
        message: result.data.message ?? null,
        visualRoomHasDescription: Boolean(String(visualRoom?.description ?? '').trim()),
        apiRoomHasDescription: Boolean(String(apiRoom?.description ?? '').trim()),
        payloadRoomHasDescription: Boolean(String(payloadRoom?.description ?? '').trim()),
        payloadRoomName: payloadRoom?.name ?? null,
      });

      this.bus.emit('room:view-loaded', { room: payloadRoom, viewState: { statusLabel, placeholderText, entries } });

      // Auto-retry when pending (image generation queued server-side)
      if (entries.length === 0 && dataStatus === 'pending') {
        this._scheduleRoomViewRetry(roomId, viewKey);
      } else {
        this._clearRoomViewRetry();
      }
    })();

    this._roomViewInflight.set(viewKey, request);
    try {
      await request;
    } catch (err) {
      if (token !== this._roomViewRequestToken) return;
      this.bus?.emit('room:view-loaded', {
        room: payloadRoomBase,
        viewState: { statusLabel: 'Unavailable', placeholderText: err?.message || 'Room view generation failed.', entries: [] },
      });
    } finally {
      this._roomViewInflight.delete(viewKey);
      this.bus?.emit('game:backend-request-end', { requestId: backendRequestId, source: 'room-view' });
    }
  }

  _scheduleRoomViewRetry(roomId, viewKey) {
    this._clearRoomViewRetry();
    this._roomViewRetryTimer = window.setTimeout(() => {
      this._roomViewRetryTimer = null;
      if (this._roomViewLastKey !== viewKey) return;
      console.log('[GameShell] _loadRoomView: retrying pending', { viewKey });
      this._loadRoomView({ force: true, preserveExisting: true });
    }, 5000);
  }

  _clearRoomViewRetry() {
    if (this._roomViewRetryTimer) {
      window.clearTimeout(this._roomViewRetryTimer);
      this._roomViewRetryTimer = null;
    }
  }

  /**
   * Fetch merchant context metadata for all merchant occupants in the room and
   * emit decoration + merchant-specific refresh events.
   * @private
   */
  async _loadMerchantStock() {
    // Prevent concurrent duplicate fetches; re-trigger is handled by MerchantPanel's own retry.
    if (this._merchantStockLoading) return;
    this._merchantStockLoading = true;

    try {
      await this.__loadMerchantStockImpl();
    } finally {
      this._merchantStockLoading = false;
    }
  }

  async __loadMerchantStockImpl() {
    const campaignId = this.launchContext?.campaign_id;
    const roomId     = this.activeRoomId;
    const charId     = this.launchCharacter?.id ?? this.launchContext?.character_id;
    if (!campaignId || !roomId) return;

    const merchants = this._currentOccupants.filter((o) => o?.presentation?.is_merchant);
    console.log('[GameShell] _loadMerchantStock start', {
      merchantCount: merchants.length,
      merchantRefs: merchants.map((m) => m?.occupant_id ?? m?.content_id ?? null),
      activeTab: this.activeGameShellTab,
    });
    if (!merchants.length) return;

    const updatedOccupants = [...this._currentOccupants];

    await Promise.all(merchants.map(async (merchant) => {
      const merchantRef = merchant.occupant_id ?? merchant.content_id;
      if (!merchantRef) return;
      const token = (this._merchantRequestTokens.get(merchantRef) ?? 0) + 1;
      this._merchantRequestTokens.set(merchantRef, token);

      try {
        const params = charId ? `?character_id=${encodeURIComponent(charId)}` : '';
        const url = `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/merchant/${encodeURIComponent(merchantRef)}${params}`;
        const resp = await fetch(url, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (this._merchantRequestTokens.get(merchantRef) !== token) return;
        if (!resp.ok) return;
        const result = await resp.json().catch(() => ({}));
        if (!result?.success || !result?.context) return;

        const ctx = result.context;
        const idx = updatedOccupants.findIndex((o) => o.occupant_id === merchant.occupant_id);
        if (idx !== -1) {
          updatedOccupants[idx] = {
            ...updatedOccupants[idx],
            presentation: {
              ...updatedOccupants[idx].presentation,
              role: ctx.merchant?.role ?? updatedOccupants[idx].presentation?.role ?? '',
              merchant_summary: ctx.merchant?.summary ?? '',
              merchant_profile: ctx.merchant?.profile ?? '',
              merchant_profile_label: ctx.merchant?.profile_label ?? '',
              merchant_wares_label: ctx.merchant?.wares_label ?? '',
              merchant_wares_types: Array.isArray(ctx.merchant?.wares_types) ? ctx.merchant.wares_types : [],
              stock:           Array.isArray(ctx.stock) ? ctx.stock : [],
              player_currency: ctx.player?.currency ?? ctx.player_currency ?? {},
            },
          };
        }
      } catch (_) {
        // Per-merchant failure is silent
      }
    }));

    this._currentOccupants = updatedOccupants;
    const room = this.mapVisualState?.topology?.rooms?.[roomId];
    console.log('[GameShell] _loadMerchantStock complete', {
      activeTab: this.activeGameShellTab,
      stockedMerchantCount: updatedOccupants.filter((o) => o?.presentation?.stock).length,
    });
    this.bus.emit('room:occupants-decoration-changed', {
      roomId,
      roomName: room?.name ?? roomId,
      source: 'merchant-stock',
    });
    this.bus.emit('merchant:stock-loaded', {
      roomId,
      roomName: room?.name ?? roomId,
      merchantCount: updatedOccupants.filter((entry) => entry?.presentation?.is_merchant).length,
      occupants: updatedOccupants,
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
        entity.addComponent('CombatComponent', new CombatComponent({
          team: blueprint.team,
          initiativeBonus: blueprint.initiativeBonus,
          attackBonus: blueprint.attackBonus,
        }));
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
    const clone = document.createElement('button');
    clone.type = 'button';
    const source = button instanceof HTMLButtonElement ? button : null;
    if (source?.dataset) {
      Object.entries(source.dataset).forEach(([key, value]) => {
        clone.dataset[key] = String(value ?? '');
      });
    }
    clone.dataset.actionRailExecute = String(source?.dataset?.actionRailExecute || '').trim();
    clone.dataset.actionLabel = String(source?.dataset?.actionLabel || source?.textContent || source?.dataset?.actionRailExecute || 'action').trim();
    return clone;
  }

  _normalizeTargetPickKindsForAction(actionKey = '', button = null) {
    const key = String(actionKey || '').trim().toLowerCase();
    const targeting = String(button?.dataset?.targeting || '').trim().toLowerCase();
    if (targeting === 'hex' || targeting === 'area_origin' || targeting === 'connected_room' || targeting === 'room_hazard' || targeting === 'room' || targeting === 'self_or_target') {
      return [targeting];
    }
    if (['skill', 'feat', 'consume_item', 'consumable'].includes(key) && targeting) {
      return [targeting];
    }
    if (key === 'attack' || key === 'demoralize') {
      return ['hostile_entity'];
    }
    if (key === 'feint' || key === 'point_out') {
      return ['hostile_entity'];
    }
    if (key === 'talk') {
      return ['entity_or_room'];
    }
    if (key === 'interact') {
      return ['entity_or_object'];
    }
    if (key === 'command_animal') {
      return ['ally'];
    }
    if ([
      'aid_setup',
      'administer_first_aid',
      'battle_medicine',
      'treat_poison',
      'treat_wounds',
    ].includes(key)) {
      return ['ally_or_self'];
    }
    if (key === 'stride' || key === 'step') {
      return ['hex'];
    }
    if (key === 'cast_spell' || key === 'spell') {
      if (targeting) {
        return [targeting];
      }
      return ['contextual'];
    }
    return ['contextual'];
  }

  _setTargetPickOverlay(active = false, promptLabel = 'Pick target') {
    if (!(this._targetPickPromptEl instanceof HTMLElement)) {
      this._targetPickPromptEl = document.getElementById('map-target-pick-prompt');
    }
    const container = this.container?.closest?.('#hexmap-container')
      || (typeof document !== 'undefined' ? document.getElementById('hexmap-container') : null)
      || this.container
      || null;
    if (container instanceof HTMLElement) {
      container.classList.toggle('dc-target-pick-active', Boolean(active));
    }
    if (this._targetPickPromptEl instanceof HTMLElement) {
      this._targetPickPromptEl.hidden = !active;
      this._targetPickPromptEl.textContent = active ? String(promptLabel || 'Pick target').trim() : '';
    }
    const instruction = document.getElementById('action-instruction');
    if (instruction instanceof HTMLElement && active) {
      instruction.hidden = false;
      instruction.textContent = String(promptLabel || 'Pick target').trim();
    }
  }

  _clearTargetPickSession(reason = 'cleared') {
    if (!this._targetPickSession) {
      this._setTargetPickOverlay(false);
      return;
    }
    console.info('[GameShell] target pick session cleared', { reason, actionKey: this._targetPickSession.actionKey });
    this._targetPickSession = null;
    this._setTargetPickOverlay(false);
  }

  _beginTargetPickSession({ actionKey = '', button = null, promptLabel = '' } = {}) {
    const normalizedAction = String(actionKey || '').trim().toLowerCase();
    if (!normalizedAction) {
      return;
    }
    const executionButton = this._cloneActionButtonForTargetPick(button);
    const allowedKinds = this._normalizeTargetPickKindsForAction(normalizedAction, executionButton);
    const targetActorRef = this._resolveTargetPickActorRef(executionButton);
    const minTargets = Number.isFinite(Number(executionButton?.dataset?.minTargets))
      ? Math.max(1, Math.trunc(Number(executionButton.dataset.minTargets)))
      : 1;
    const maxTargets = Number.isFinite(Number(executionButton?.dataset?.maxTargets))
      ? Math.max(minTargets, Math.trunc(Number(executionButton.dataset.maxTargets)))
      : minTargets;
    const selectionMode = String(executionButton?.dataset?.selectionMode || (maxTargets > 1 ? 'multi' : 'single')).trim().toLowerCase();
    const completionPolicy = String(
      executionButton?.dataset?.completionPolicy
      || (selectionMode === 'multi' ? 'max_targets' : 'auto')
    ).trim().toLowerCase();
    const allowDuplicateTargets = executionButton?.dataset?.allowDuplicateTargets === '1';
    const rangeFt = Number(executionButton?.dataset?.rangeFt || 0);
    const maxRangeFt = Number.isFinite(rangeFt) && rangeFt > 0 ? Math.max(0, Math.trunc(rangeFt)) : null;
    const resolvedPrompt = String(promptLabel || '').trim() || 'Pick target';
    this._targetPickSession = {
      actionKey: normalizedAction,
      button: executionButton,
      promptLabel: resolvedPrompt,
      allowedKinds,
      actorRef: targetActorRef,
      minTargets,
      maxTargets,
      selectionMode,
      completionPolicy,
      allowDuplicateTargets,
      maxRangeFt,
      selectedTargets: [],
      sourceSurface: 'action-rail',
    };
    this.activateGameShellTab('map');
    const prompt = maxTargets > 1 ? `${resolvedPrompt} (0/${maxTargets})` : resolvedPrompt;
    this._setTargetPickOverlay(true, prompt);
    console.info('[GameShell] target pick session started', {
      actionKey: normalizedAction,
      promptLabel: resolvedPrompt,
      allowedKinds,
      actorRef: targetActorRef,
      minTargets,
      maxTargets,
      selectionMode,
      completionPolicy,
      maxRangeFt,
    });
  }

  _resolveTargetPickActorRef(button = null) {
    const explicitActorRef = String(button?.dataset?.actorRef || '').trim();
    if (explicitActorRef) {
      return explicitActorRef;
    }

    const snapshot = this.gameCoordinator?.phaseManager?.getSnapshot?.() || {};
    const encounterActorRef = String(
      snapshot?.actionContract?.actor_id
      || snapshot?.turn?.entity
      || ''
    ).trim();
    if (encounterActorRef) {
      return encounterActorRef;
    }

    const selectedEntity = this._getStateValue('selectedEntity') || null;
    const selectedRef = this.getEntityInstanceRef(selectedEntity);
    if (selectedRef) {
      return selectedRef;
    }

    return this.getEntityInstanceRef(this.findLaunchPlayerEntity?.() || null);
  }

  _resolveEntityByInstanceRef(actorRef = '') {
    const targetRef = String(actorRef || '').trim();
    if (!targetRef || !this.entityManager?.getEntitiesWith) {
      return null;
    }
    const entities = this.entityManager.getEntitiesWith('PositionComponent');
    return entities.find((entity) => this.getEntityInstanceRef(entity) === targetRef) || null;
  }

  _isHostileEntityTarget(entity, actorEntity) {
    if (!entity || !actorEntity || entity.id === actorEntity.id) {
      return false;
    }
    const actorCombat = actorEntity.getComponent?.('CombatComponent') || null;
    const targetCombat = entity.getComponent?.('CombatComponent') || null;
    if (!actorCombat || !targetCombat) {
      return false;
    }
    if (typeof actorCombat.isHostileTo === 'function') {
      return actorCombat.isHostileTo(targetCombat);
    }
    const actorTeam = String(actorCombat.team || '').trim().toLowerCase();
    const targetTeam = String(targetCombat.team || '').trim().toLowerCase();
    return Boolean(actorTeam && targetTeam && actorTeam !== targetTeam);
  }

  _isAllyEntityTarget(entity, actorEntity) {
    if (!entity || !actorEntity || entity.id === actorEntity.id) {
      return false;
    }
    const actorCombat = actorEntity.getComponent?.('CombatComponent') || null;
    const targetCombat = entity.getComponent?.('CombatComponent') || null;
    if (!actorCombat || !targetCombat) {
      return false;
    }
    const actorTeam = String(actorCombat.team || '').trim().toLowerCase();
    const targetTeam = String(targetCombat.team || '').trim().toLowerCase();
    return Boolean(actorTeam && targetTeam && actorTeam === targetTeam);
  }

  _resolvePrimaryHexEntity(q, r, provided = []) {
    const entities = Array.isArray(provided) && provided.length
      ? provided
      : this.getEntitiesAtHex(q, r);
    if (!entities.length) {
      return null;
    }
    const selectedId = this._getStateValue('selectedEntity')?.id || null;
    if (selectedId) {
      const match = entities.find((entity) => entity?.id === selectedId);
      if (match) {
        return match;
      }
    }
    return entities[0] || null;
  }

  _handleTargetPickHexClick(q, r, providedEntities = []) {
    const session = this._targetPickSession;
    if (!session) {
      return false;
    }
    const actor = this._resolveEntityByInstanceRef(session.actorRef) || this.findLaunchPlayerEntity?.() || null;
    const targetEntity = this._resolvePrimaryHexEntity(q, r, providedEntities);
    const kinds = Array.isArray(session.allowedKinds) ? session.allowedKinds : [];
    const button = session.button;
    console.info('[GameShell] target pick click received', {
      actionKey: session.actionKey,
      actorRef: session.actorRef,
      q: Number(q),
      r: Number(r),
      selectedCount: Array.isArray(session.selectedTargets) ? session.selectedTargets.length : 0,
      minTargets: session.minTargets,
      maxTargets: session.maxTargets,
      completionPolicy: session.completionPolicy,
      selectionMode: session.selectionMode,
      allowDuplicateTargets: session.allowDuplicateTargets,
      targetEntityRef: this.getEntityInstanceRef(targetEntity),
      targetEntityId: String(targetEntity?.id || ''),
      targetEntityName: _getEntityDisplayName(targetEntity),
    });

    const chooseEntityTarget = (entity, kind = 'entity') => {
      if (!entity) {
        return false;
      }
      const targetRef = String(entity?.dcEntityRef || entity?.dcEntityInstanceId || entity?.instanceId || '').trim();
      const targetName = _getEntityDisplayName(entity);
      button.dataset.targetId = String(entity.id || '');
      button.dataset.targetEntityId = String(entity.id || '');
      button.dataset.targetName = targetName;
      if (targetRef) {
        button.dataset.targetRef = targetRef;
      }
      this.selectEntity(entity, { suppressCoordinatorResync: true });
      return {
        target_kind: kind,
        target_ref: targetRef || null,
        target_entity_id: String(entity.id || '').trim() || null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: targetName || null,
      };
    };

    const chooseHexTarget = (kind = 'hex') => {
      button.dataset.targetQ = String(q);
      button.dataset.targetR = String(r);
      return {
        target_kind: kind,
        target_ref: null,
        target_entity_id: null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: `Hex (${q}, ${r})`,
      };
    };

    const chooseSelfTarget = () => {
      if (!actor) {
        return false;
      }
      const actorRef = this.getEntityInstanceRef(actor);
      const actorLabel = _getEntityDisplayName(actor);
      if (actorRef) {
        button.dataset.targetRef = actorRef;
      }
      button.dataset.targetEntityId = String(actor?.id || '');
      button.dataset.targetId = String(actor?.id || '');
      button.dataset.targetName = actorLabel;
      this.selectEntity(actor, { suppressCoordinatorResync: true });
      return {
        target_kind: 'self',
        target_ref: actorRef || null,
        target_entity_id: String(actor?.id || '').trim() || null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: actorLabel || 'self',
      };
    };

    let selection = null;
    if (kinds.includes('hostile_entity')) {
      selection = this._isHostileEntityTarget(targetEntity, actor) ? chooseEntityTarget(targetEntity, 'hostile_entity') : null;
    } else if (kinds.includes('ally') || kinds.includes('ally_or_self')) {
      selection = this._isAllyEntityTarget(targetEntity, actor) ? chooseEntityTarget(targetEntity, 'ally') : null;
      if (!selection && kinds.includes('ally_or_self')) {
        selection = chooseSelfTarget();
      }
    } else if (kinds.includes('self_or_target')) {
      const actorRef = this.getEntityInstanceRef(actor);
      const targetRef = this.getEntityInstanceRef(targetEntity);
      if (targetEntity && actor && actorRef && targetRef && actorRef === targetRef) {
        selection = chooseSelfTarget();
      } else {
        selection = chooseEntityTarget(targetEntity, 'self_or_target');
      }
    } else if (kinds.includes('entity_or_object') || kinds.includes('entity_or_room') || kinds.includes('contextual')) {
      selection = chooseEntityTarget(targetEntity);
      if (!selection) {
        selection = chooseHexTarget();
      }
    } else if (kinds.includes('hex')) {
      selection = chooseHexTarget('hex');
    } else if (kinds.includes('area_origin')) {
      button.dataset.areaOriginQ = String(q);
      button.dataset.areaOriginR = String(r);
      button.dataset.targetQ = String(q);
      button.dataset.targetR = String(r);
      selection = {
        target_kind: 'area_origin',
        target_ref: null,
        target_entity_id: null,
        target_hex: { q: Number(q), r: Number(r) },
        target_label: `Area origin (${q}, ${r})`,
      };
    } else if (kinds.includes('connected_room')) {
      const capability = this.resolveNavigationCapabilityAtHex?.(q, r) || null;
      if (capability?.available && capability?.target_room_id) {
        button.dataset.targetRoomId = String(capability.target_room_id);
        button.dataset.targetRoomName = String(capability.target_room_name || capability.target_room_id);
        button.dataset.targetRef = String(capability.target_room_id);
        selection = {
          target_kind: 'connected_room',
          target_ref: String(capability.target_room_id),
          target_entity_id: null,
          target_room_id: String(capability.target_room_id),
          target_hex: { q: Number(q), r: Number(r) },
          target_label: String(capability.target_room_name || capability.target_room_id),
        };
      }
    } else if (kinds.includes('room_hazard') || kinds.includes('room')) {
      button.dataset.targetRoomId = String(this.resolveActiveRoomId() || '');
      selection = chooseEntityTarget(targetEntity, kinds.includes('room_hazard') ? 'room_hazard' : 'room')
        || {
          ...chooseHexTarget(kinds.includes('room_hazard') ? 'room_hazard' : 'room'),
          target_room_id: String(this.resolveActiveRoomId() || ''),
        };
    } else {
      selection = chooseEntityTarget(targetEntity);
    }

    if (!selection || !this._appendTargetPickSelection(session, selection)) {
      console.warn('[GameShell] target pick selection rejected', {
        actionKey: session.actionKey,
        q: Number(q),
        r: Number(r),
        selection,
        selectedTargets: session.selectedTargets,
      });
      this._setTargetPickOverlay(true, `${session.promptLabel} (invalid target)`);
      return true;
    }

    const selectedCount = Array.isArray(session.selectedTargets) ? session.selectedTargets.length : 0;
    const maxTargets = Number.isFinite(Number(session.maxTargets)) ? Number(session.maxTargets) : 1;
    const minTargets = Number.isFinite(Number(session.minTargets)) ? Number(session.minTargets) : 1;
    const completionPolicy = String(session.completionPolicy || '').trim().toLowerCase();
    const shouldComplete = selectedCount >= minTargets
      && (
        session.selectionMode !== 'multi'
        || completionPolicy === 'min_targets'
        || selectedCount >= maxTargets
      );
    if (!shouldComplete) {
      console.info('[GameShell] target pick awaiting additional selections', {
        actionKey: session.actionKey,
        selectedCount,
        minTargets,
        maxTargets,
        completionPolicy,
        selectedTargets: session.selectedTargets,
      });
      this._setTargetPickOverlay(true, `${session.promptLabel} (${selectedCount}/${maxTargets})`);
      return true;
    }

    this._applyLegacySelectionDataset(button, session.selectedTargets[0] || null);
    button.dataset.targetsJson = JSON.stringify(session.selectedTargets || []);
    button.dataset.targetQ = button.dataset.targetQ || String(q);
    button.dataset.targetR = button.dataset.targetR || String(r);
    this.setSelectedHex(q, r, { emitDetails: false });
    const actionKey = String(session.actionKey || '').trim();
    console.info('[GameShell] target pick finalizing action dispatch', {
      actionKey,
      actorRef: session.actorRef,
      minTargets,
      maxTargets,
      completionPolicy,
      targets: session.selectedTargets,
      datasetTargetRef: String(button?.dataset?.targetRef || ''),
      datasetTargetsJson: String(button?.dataset?.targetsJson || ''),
    });
    this._clearTargetPickSession('picked');
    this.bus.emit('user:action-selected', { actionKey, button });
    return true;
  }

  _appendTargetPickSelection(session, selection) {
    if (!session || !selection || typeof selection !== 'object') {
      return false;
    }
    if (!Array.isArray(session.selectedTargets)) {
      session.selectedTargets = [];
    }
    const key = [
      String(selection.target_kind || '').trim(),
      String(selection.target_ref || '').trim(),
      String(selection.target_entity_id || '').trim(),
      Number.isFinite(Number(selection?.target_hex?.q)) ? Number(selection.target_hex.q) : '',
      Number.isFinite(Number(selection?.target_hex?.r)) ? Number(selection.target_hex.r) : '',
      String(selection.target_room_id || '').trim(),
    ].join(':');
    const existingKeys = new Set((session.selectedTargets || []).map((entry) => [
      String(entry?.target_kind || '').trim(),
      String(entry?.target_ref || '').trim(),
      String(entry?.target_entity_id || '').trim(),
      Number.isFinite(Number(entry?.target_hex?.q)) ? Number(entry.target_hex.q) : '',
      Number.isFinite(Number(entry?.target_hex?.r)) ? Number(entry.target_hex.r) : '',
      String(entry?.target_room_id || '').trim(),
    ].join(':')));
    if (!session.allowDuplicateTargets && existingKeys.has(key)) {
      console.warn('[GameShell] target pick duplicate blocked', {
        actionKey: session.actionKey,
        key,
        selection,
        allowDuplicateTargets: session.allowDuplicateTargets,
      });
      return false;
    }
    const maxTargets = Number.isFinite(Number(session.maxTargets)) ? Number(session.maxTargets) : 1;
    if (session.selectedTargets.length >= maxTargets) {
      console.warn('[GameShell] target pick max target count reached', {
        actionKey: session.actionKey,
        selectedCount: session.selectedTargets.length,
        maxTargets,
      });
      return false;
    }
    if (Number.isFinite(Number(session.maxRangeFt)) && Number(session.maxRangeFt) > 0) {
      const actor = this._resolveEntityByInstanceRef(session.actorRef) || this.findLaunchPlayerEntity?.() || null;
      const actorPos = actor?.getComponent?.('PositionComponent') || null;
      const targetQ = Number(selection?.target_hex?.q);
      const targetR = Number(selection?.target_hex?.r);
      const distanceHexes = actorPos && Number.isFinite(targetQ) && Number.isFinite(targetR) && this.movementSystem?.hexDistance
        ? this.movementSystem.hexDistance(Number(actorPos.q), Number(actorPos.r), targetQ, targetR)
        : null;
      const hexCost = Number(actor?.getComponent?.('MovementComponent')?.hexMovementCost || 5);
      const distanceFt = Number.isFinite(Number(distanceHexes))
        ? Number(distanceHexes) * (Number.isFinite(hexCost) && hexCost > 0 ? hexCost : 5)
        : null;
      if (Number.isFinite(Number(distanceFt)) && Number(distanceFt) > Number(session.maxRangeFt)) {
        console.warn('[GameShell] target pick range blocked', {
          actionKey: session.actionKey,
          actorRef: session.actorRef,
          maxRangeFt: session.maxRangeFt,
          distanceFt,
          selection,
        });
        return false;
      }
    }
    session.selectedTargets.push(selection);
    console.info('[GameShell] target pick selection appended', {
      actionKey: session.actionKey,
      selectedCount: session.selectedTargets.length,
      maxTargets: session.maxTargets,
      selection,
      selectedTargets: session.selectedTargets,
    });
    return true;
  }

  _applyLegacySelectionDataset(button, selection) {
    if (!button?.dataset || !selection || typeof selection !== 'object') {
      return;
    }
    if (selection.target_ref) {
      button.dataset.targetRef = String(selection.target_ref);
    }
    if (selection.target_entity_id) {
      button.dataset.targetEntityId = String(selection.target_entity_id);
      button.dataset.targetId = String(selection.target_entity_id);
    }
    if (selection.target_label) {
      button.dataset.targetName = String(selection.target_label);
    }
    if (selection.target_room_id) {
      button.dataset.targetRoomId = String(selection.target_room_id);
    }
    if (selection?.target_hex && Number.isFinite(Number(selection.target_hex.q)) && Number.isFinite(Number(selection.target_hex.r))) {
      button.dataset.targetQ = String(selection.target_hex.q);
      button.dataset.targetR = String(selection.target_hex.r);
    }
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

      const state = await this.gameCoordinator.api.getState({
        actor: actorRef || undefined,
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
    } catch (error) {
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
    const selectedIsControlledFollower = selectedEntity ? this.isControlledFollowerEntity(selectedEntity) : false;
    return {
      campaignId: this.resolveCampaignId(),
      characterId: (selectedIsLaunchActor || selectedIsControlledFollower)
        ? (selectedCharacterId || launchCharacterId || null)
        : (launchCharacterId || null),
      instanceId: (selectedIsLaunchActor || selectedIsControlledFollower)
        ? selectedInstanceId
        : (this.launchCharacter?.instanceId || this.launchCharacter?.instance_id || null),
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
    const presentationStatus = String(
      encounterState?.encounter_presentation?.status
      ?? encounterState?.status
      ?? this.turnManagementSystem?.getEncounterStatus?.()
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
    return matchesLaunchCharacter || this.isControlledFollowerEntity(entity);
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
    if (!this.canDragEntityOnMap(entity)) {
      return false;
    }

    const validation = this.resolveMapDragDropValidation(entity, targetQ, targetR);
    if (!validation.valid) {
      return false;
    }

    if (Number(sourceQ) === Number(targetQ) && Number(sourceR) === Number(targetR)) {
      return true;
    }

    if (this.isLiveCombatEncounterActive()) {
      const combatPlan = this.buildCombatDragMovementPlan(entity, targetQ, targetR);
      if (!combatPlan.valid) {
        return false;
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
      return Boolean(result?.success);
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
    }).catch((err) => console.warn('[Location] Entity move persist failed:', err));

    if (entityRef) {
      this.launchCharacter = {
        ...this.launchCharacter,
        instanceId: entityRef,
        instance_id: entityRef,
      };
      this.characterData = this.launchCharacter;
    }
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
        const state = await coordinator.api.getState({
          actor: actorRef || null,
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


// ---------------------------------------------------------------------------
// Module-level helpers
// ---------------------------------------------------------------------------

function _getPresentationObjectDefinitions(mapVisualState = {}, dungeonData = {}) {
  const visualDefinitions = mapVisualState?.presentation?.object_definitions;
  if (visualDefinitions && typeof visualDefinitions === 'object') {
    return visualDefinitions;
  }
  return {};
}

function _getVisualOccupants(mapVisualState = {}) {
  return [
    ...(Array.isArray(mapVisualState?.occupants?.party)
      ? mapVisualState.occupants.party.map((occupant) => ({ ...occupant, is_party: true }))
      : []),
    ...(Array.isArray(mapVisualState?.occupants?.entities) ? mapVisualState.occupants.entities : []),
  ];
}

function _getVisualActorRoster(mapVisualState = {}) {
  const roster = mapVisualState?.actor_roster;
  const entries = Array.isArray(roster?.entries) ? roster.entries : [];
  return entries
    .filter((entry) => entry && typeof entry === 'object')
    .map((entry) => ({ ...entry }));
}

function _getEntityDisplayName(entity = null) {
  if (!entity || typeof entity !== 'object') {
    return 'Unknown';
  }

  const identity = entity.getComponent?.('IdentityComponent');
  if (identity?.name) {
    return String(identity.name);
  }

  return String(
    entity?.dcLabel
    || entity?.dcName
    || entity?.dcStatePayload?.label
    || entity?.dcStatePayload?.display_name
    || entity?.dcStatePayload?.name
    || entity?.dcStatePayload?.metadata?.name
    || entity?.dcEntityRef
    || entity?.id
    || 'Unknown'
  );
}

function _isVisualOccupantVisible(occupant, activeRoomId = '') {
  if (!occupant) {
    return false;
  }

  const hidden = occupant?.hidden === true || occupant?.state?.hidden === true;
  const detected = occupant?.detected === true || occupant?.state?.detected === true;
  const inActiveRoom = String(occupant?.room_id || '').trim() !== ''
    && String(occupant?.room_id || '').trim() === String(activeRoomId || '').trim();

  if (occupant.visible === true) {
    return true;
  }

  if (occupant.visible === false) {
    return false;
  }

  if (hidden && !detected) {
    return false;
  }

  return true;
}

function _parseVisualHexId(hexId) {
  const normalized = String(hexId || '').trim();
  if (!normalized) {
    return null;
  }

  const segments = normalized.split(':');
  if (segments.length < 3) {
    return null;
  }

  const r = Number(segments.pop());
  const q = Number(segments.pop());
  const roomId = segments.join(':');
  if (!roomId || !Number.isFinite(q) || !Number.isFinite(r)) {
    return null;
  }

  return {
    room_id: roomId,
    q,
    r,
  };
}

function _getConnectionRoomId(connection, side) {
  const key = side === 'to' ? 'to' : 'from';
  return String(connection?.[`${key}_room_id`] || connection?.[`${key}_room`] || '').trim() || null;
}

function _getConnectionHex(connection, side) {
  const key = side === 'to' ? 'to' : 'from';
  return _parseVisualHexId(connection?.[`${key}_hex_id`]) || connection?.[`${key}_hex`] || null;
}

function _getActiveRoomData(rooms = {}, activeRoomId = null) {
  const roomId = String(activeRoomId || '').trim();
  if (!roomId) {
    return null;
  }
  return rooms?.[roomId] || null;
}

function _getActiveRoomHex(room = null, q, r) {
  if (!room || !Array.isArray(room.hexes)) {
    return null;
  }

  return room.hexes.find((candidate) => Number(candidate?.q) === Number(q) && Number(candidate?.r) === Number(r)) || null;
}

function _buildActiveRoomOccupantSummary(roomId, occupants = [], isVisible = () => true) {
  const normalizedRoomId = String(roomId || '').trim();
  if (!normalizedRoomId) {
    return '';
  }

  const groupedNames = { pc: [], npc: [], creature: [] };
  const seen = new Set();
  const pushGroupedName = (bucket, name) => {
    if (!bucket || !name) {
      return;
    }
    const dedupeKey = `${bucket}:${String(name).toLowerCase()}`;
    if (seen.has(dedupeKey)) {
      return;
    }
    seen.add(dedupeKey);
    groupedNames[bucket].push(name);
  };

  occupants
    .filter((occupant) => String(occupant?.room_id || '') === normalizedRoomId && isVisible(occupant))
    .forEach((occupant) => {
      const rawType = String(occupant?.occupant_type || '').toLowerCase();
      let bucket = '';
      if (rawType === 'player_character' || rawType === 'player' || rawType === 'pc') {
        bucket = 'pc';
      } else if (rawType === 'npc') {
        bucket = 'npc';
      } else if (rawType === 'creature') {
        bucket = 'creature';
      }

      const name = String(occupant?.label || occupant?.content_id || '').trim();
      pushGroupedName(bucket, name);
    });

  const parts = [];
  if (groupedNames.pc.length) {
    parts.push(`Party present: ${groupedNames.pc.join(', ')}`);
  }
  if (groupedNames.npc.length) {
    parts.push(`NPCs present: ${groupedNames.npc.join(', ')}`);
  }
  if (groupedNames.creature.length) {
    parts.push(`Other creatures present: ${groupedNames.creature.join(', ')}`);
  }

  return parts.join('. ');
}

function _getObjectDefinition(contentId, mapVisualState = {}, dungeonData = {}) {
  if (!contentId) {
    return null;
  }

  const definitions = _getPresentationObjectDefinitions(mapVisualState, dungeonData);
  return definitions && typeof definitions === 'object' ? (definitions[contentId] || null) : null;
}

function _buildObstacleMobilityProfile(objectDefinition, metadata = {}, contentId = '') {
  const definitionMovement = objectDefinition?.movement || {};
  const normalizedContentId = String(contentId || '').toLowerCase();
  const metadataBlocksMovement = (typeof metadata.blocks_movement === 'boolean') ? metadata.blocks_movement : null;
  const definitionBlocksMovement = (typeof definitionMovement.blocks_movement === 'boolean')
    ? definitionMovement.blocks_movement
    : ((typeof objectDefinition?.blocks_movement === 'boolean') ? objectDefinition.blocks_movement : null);
  const movable = (typeof metadata.movable === 'boolean') ? metadata.movable : Boolean(objectDefinition?.movable);
  const passable = (typeof metadata.passable === 'boolean')
    ? metadata.passable
    : (metadataBlocksMovement !== null)
      ? !metadataBlocksMovement
      : (typeof definitionMovement.passable === 'boolean')
        ? definitionMovement.passable
        : (definitionBlocksMovement === true ? false : Boolean(definitionMovement.passable));
  const stackable = (typeof metadata.stackable === 'boolean') ? metadata.stackable : Boolean(objectDefinition?.stackable);
  const indicatorValues = [
    metadata.fixture_type,
    metadata.obstacle_type,
    metadata.category,
    metadata.type,
    objectDefinition?.category,
    objectDefinition?.type,
    objectDefinition?.object_type,
    normalizedContentId,
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
}

function _getObstacleMobilityAtHex(room = null, definitions = {}, q, r) {
  const roomHex = _getActiveRoomHex(room, q, r);
  const roomObjects = Array.isArray(roomHex?.objects) ? roomHex.objects : [];
  const candidate = roomObjects.find((object) => {
    const objectId = String(object?.object_id || '').trim();
    const objectDefinition = definitions?.[objectId] || null;
    const category = String(object?.category || objectDefinition?.category || '').toLowerCase();
    const movement = objectDefinition?.movement || {};
    if (typeof object?.blocks_movement === 'boolean' || typeof object?.passable === 'boolean') {
      return object.blocks_movement === true || object.passable === false;
    }
    return movement.blocks_movement === true
      || movement.passable === false
      || ['obstacle', 'wall', 'barrier', 'barricade', 'door', 'collapsed'].some((token) => category.includes(token));
  });
  if (!candidate) {
    return null;
  }

  const objectId = String(candidate?.object_id || '').trim();
  return _buildObstacleMobilityProfile(definitions?.[objectId] || null, candidate || {}, objectId);
}

function _getAxialLine(fromQ, fromR, toQ, toR, movementSystem = null) {
  const toCube = (q, r) => ({ x: q, z: r, y: -q - r });
  const fromCube = toCube(fromQ, fromR);
  const targetCube = toCube(toQ, toR);
  const distance = movementSystem?.hexDistance
    ? movementSystem.hexDistance(fromQ, fromR, toQ, toR)
    : Math.max(Math.abs(fromQ - toQ), Math.abs(fromR - toR), Math.abs((fromQ + fromR) - (toQ + toR)));

  const points = [];
  for (let step = 0; step <= distance; step += 1) {
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
}

function _hasLineOfSight(fromQ, fromR, toQ, toR, getObstacleMobilityAtHex, movementSystem = null) {
  if (fromQ === toQ && fromR === toR) {
    return true;
  }

  const line = _getAxialLine(fromQ, fromR, toQ, toR, movementSystem);
  for (let i = 1; i < line.length - 1; i += 1) {
    const { q, r } = line[i];
    const obstacle = getObstacleMobilityAtHex(q, r);
    if (obstacle && !obstacle.passable) {
      return false;
    }
  }

  return true;
}

function _getHostileTargets(actor, entityManager, movementSystem = null, hasLineOfSight = () => true) {
  const actorCombat = actor?.getComponent?.('CombatComponent');
  const actorPos = actor?.getComponent?.('PositionComponent');
  if (!actorCombat || !actorPos || !entityManager?.getEntitiesWith) {
    return [];
  }

  const candidates = entityManager.getEntitiesWith('CombatComponent', 'StatsComponent', 'PositionComponent');
  const hostileTargets = [];

  candidates.forEach((candidate) => {
    if (candidate.id === actor.id) {
      return;
    }

    const targetCombat = candidate.getComponent('CombatComponent');
    const targetStats = candidate.getComponent('StatsComponent');
    const targetPos = candidate.getComponent('PositionComponent');
    if (!targetCombat || !targetPos) {
      return;
    }

    const alive = typeof targetStats?.isAlive === 'function'
      ? targetStats.isAlive()
      : Number(targetStats?.currentHp ?? 1) > 0;
    if (!alive) {
      return;
    }

    const actorTeam = String(actorCombat?.team || '').toLowerCase();
    const targetTeam = String(targetCombat?.team || '').toLowerCase();
    const hostile = typeof actorCombat?.isHostileTo === 'function'
      ? actorCombat.isHostileTo(targetCombat)
      : (actorTeam && targetTeam && actorTeam !== targetTeam);
    if (!hostile) {
      return;
    }

    const distance = movementSystem?.hexDistance
      ? movementSystem.hexDistance(actorPos.q, actorPos.r, targetPos.q, targetPos.r)
      : Math.max(Math.abs(actorPos.q - targetPos.q), Math.abs(actorPos.r - targetPos.r), Math.abs((actorPos.q + actorPos.r) - (targetPos.q + targetPos.r)));
    if (!hasLineOfSight(actorPos.q, actorPos.r, targetPos.q, targetPos.r)) {
      return;
    }
    hostileTargets.push({ target: candidate, distance });
  });

  hostileTargets.sort((left, right) => left.distance - right.distance);
  return hostileTargets;
}

function _normalizeAuthoritativeNavigationCapability(exit, activeRoomId) {
  const targetRoomId = String(exit?.target_room_id || '').trim();
  const targetRoomName = String(exit?.target_room_name || exit?.to_room_name || '').trim();
  const destinationType = String(exit?.destination_type || 'room').trim().toLowerCase() || 'room';
  const destinationId = String(exit?.destination_id || (destinationType === 'room' ? targetRoomId : '')).trim();
  const type = String(exit?.type || 'passage').trim() || 'passage';
  const distance = Number.isFinite(Number(exit?.distance)) ? Math.max(0, Math.trunc(Number(exit.distance))) : 0;
  const blockedReason = String(exit?.blocked_reason || '').trim() || null;
  const isDiscovered = Object.prototype.hasOwnProperty.call(exit || {}, 'is_discovered') ? Boolean(exit.is_discovered) : true;
  const isPassable = Object.prototype.hasOwnProperty.call(exit || {}, 'is_passable') ? Boolean(exit.is_passable) : true;
  const available = typeof exit?.available === 'boolean'
    ? exit.available
    : (blockedReason === null && Boolean(targetRoomId) && isDiscovered && isPassable);
  const originHex = _normalizeHexPayload(exit?.origin_hex) || _normalizeHexPayload(_getConnectionHex(exit, 'from'));
  const targetHex = _normalizeHexPayload(exit?.target_hex) || _normalizeHexPayload(_getConnectionHex(exit, 'to'));

  return {
    connection_id: String(exit?.connection_id || `${activeRoomId || 'unknown'}__${targetRoomId || 'unknown'}`),
    origin_room_id: String(exit?.origin_room_id || activeRoomId || '').trim(),
    target_room_id: targetRoomId,
    target_room_name: targetRoomName,
    destination_type: destinationType,
    destination_id: destinationId,
    type,
    available,
    blocked_reason: blockedReason || (available ? null : 'blocked'),
    is_discovered: isDiscovered,
    is_passable: isPassable,
    bidirectional: Object.prototype.hasOwnProperty.call(exit || {}, 'bidirectional')
      ? Boolean(exit.bidirectional)
      : type !== 'one_way',
    requires_interaction: Object.prototype.hasOwnProperty.call(exit || {}, 'requires_interaction')
      ? Boolean(exit.requires_interaction)
      : !isPassable,
    distance,
    quest_reference: exit?.quest_reference === true,
    quest_ids: Array.isArray(exit?.quest_ids)
      ? exit.quest_ids.map((value) => String(value || '').trim()).filter(Boolean)
      : [],
    origin_hex: originHex,
    target_hex: targetHex,
    connection: exit,
  };
}

function _normalizeHexPayload(hex) {
  if (!hex || typeof hex !== 'object') {
    return null;
  }
  const q = Number(hex.q);
  const r = Number(hex.r);
  if (!Number.isFinite(q) || !Number.isFinite(r)) {
    return null;
  }
  return { q, r };
}

function _findLaunchPlayerEntity(entityManager, launchContext = {}, launchCharacterId = 0) {
  if (!entityManager?.getEntitiesWith) {
    return null;
  }

  const entities = entityManager.getEntitiesWith('PositionComponent');
  if (!Array.isArray(entities) || !entities.length) {
    return null;
  }

  const playerEntities = entities.filter((entity) => {
    const combat = entity.getComponent?.('CombatComponent');
    if (combat) {
      return typeof combat.isPlayerTeam === 'function'
        ? combat.isPlayerTeam()
        : String(combat?.team || '').toLowerCase() === 'player';
    }

    const entityType = String(entity?.dcEntityType || entity?.dcStatePayload?.entity_type || '').toLowerCase();
    const metadata = entity?.dcStatePayload?.state?.metadata || entity?.dcStatePayload?.metadata || {};
    const metadataTeam = String(metadata.team || '').toLowerCase();
    const campaignCharacterId = Number(metadata.campaign_character_id || metadata.character_id || entity?.dcCharacterId || 0);

    return entityType === 'player_character'
      || metadataTeam === 'player'
      || (launchCharacterId > 0 && campaignCharacterId === launchCharacterId);
  });

  if (!playerEntities.length) {
    return null;
  }

  const preferredPlayerEntities = playerEntities.filter((entity) => {
    const entityRef = String(
      entity?.dcEntityRef
      || entity?.dcEntityInstanceId
      || entity?.instanceId
      || entity?.id
      || ''
    ).trim().toLowerCase();
    const entityType = String(entity?.dcEntityType || entity?.dcStatePayload?.entity_type || '').trim().toLowerCase();
    const metadata = entity?.dcStatePayload?.state?.metadata || entity?.dcStatePayload?.metadata || {};
    const followerKind = String(metadata?.follower_kind || metadata?.bond_contract?.follower_kind || '').trim().toLowerCase();
    const roleKind = String(
      metadata?.role
      || metadata?.bond_contract?.role
      || entity?.dcStatePayload?.role
      || entity?.dcStatePayload?.state?.role
      || ''
    ).trim().toLowerCase();
    const isFollowerLike = entityRef.startsWith('familiar-')
      || entityRef.startsWith('companion-')
      || entityRef.startsWith('follower-')
      || followerKind === 'familiar'
      || followerKind === 'companion'
      || followerKind === 'follower'
      || roleKind.includes('familiar')
      || roleKind.includes('companion')
      || roleKind.includes('follower');
    if (isFollowerLike) {
      return false;
    }
    const campaignCharacterId = Number(metadata.campaign_character_id || metadata.character_id || entity?.dcCharacterId || 0);
    return entityType === 'player_character'
      || (launchCharacterId > 0 && campaignCharacterId === launchCharacterId);
  });
  const launchCandidates = preferredPlayerEntities.length ? preferredPlayerEntities : playerEntities;

  const startQ = Number.isFinite(Number(launchContext?.start_q)) ? Number(launchContext.start_q) : 0;
  const startR = Number.isFinite(Number(launchContext?.start_r)) ? Number(launchContext.start_r) : 0;
  const onStartHex = launchCandidates.find((entity) => {
    const pos = entity.getComponent?.('PositionComponent');
    return pos && pos.q === startQ && pos.r === startR;
  });

  return onStartHex || launchCandidates[0] || null;
}

function _preloadSpriteUrls(spriteService, blueprints = [], objectDefinitions = {}, launchCharacter = null) {
  if (!spriteService?.preloadUrl) {
    return;
  }

  blueprints.forEach((blueprint) => {
    const spriteId = String(blueprint?.render?.spriteKey || '').trim();
    if (!spriteId) {
      return;
    }

    const definition = objectDefinitions?.[blueprint?.contentId] || {};
    const url = String(
      definition?.visual?.image_url
      || definition?.visual?.portrait_url
      || definition?.visual?.url
      || '',
    ).trim();
    if (url) {
      spriteService.preloadUrl(spriteId, url);
    }
  });

  const portraitSpriteId = String(
    launchCharacter?.portrait?.sprite_id
    || launchCharacter?.portrait_sprite_id
    || launchCharacter?.portraitSpriteId
    || '',
  ).trim();
  const portraitUrl = String(
    launchCharacter?.portrait?.url
    || launchCharacter?.portrait_url
    || launchCharacter?.portraitUrl
    || '',
  ).trim();
  if (portraitSpriteId && portraitUrl) {
    spriteService.preloadUrl(portraitSpriteId, portraitUrl);
  }
}

/**
 * Flatten phase-based objectives from a quest entry (server shape) into a
 * flat array that QuestPanel can render directly.
 *
 * Server shape: quest.objective_states = [{ phase_id, objectives: [{label, status, ...}] }]
 *
 * @param {object} quest
 * @returns {Array<{label: string, status: string, children?: Array}>}
 */
function _flattenQuestObjectives(quest) {
  const phases = quest.objective_states ?? quest.generated_objectives ?? [];
  if (!Array.isArray(phases)) return [];
  return phases.flatMap((phase) => Array.isArray(phase.objectives) ? phase.objectives : []);
}

function _isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function _hasMeaningfulValue(value) {
  if (value === null || value === undefined) return false;
  if (typeof value === 'string') return value.trim() !== '';
  if (Array.isArray(value)) return value.length > 0;
  if (typeof value === 'object') return Object.keys(value).length > 0;
  return true;
}

function _mergeRoomMetadata(visualRoom = {}, apiRoom = {}, roomId = '') {
  const merged = {
    ...(_isPlainObject(visualRoom) ? visualRoom : {}),
    ...(_isPlainObject(apiRoom) ? apiRoom : {}),
    room_id: apiRoom?.room_id || visualRoom?.room_id || roomId,
  };

  ['name', 'description', 'room_type', 'size_category', 'terrain', 'lighting'].forEach((key) => {
    if (!_hasMeaningfulValue(apiRoom?.[key]) && _hasMeaningfulValue(visualRoom?.[key])) {
      merged[key] = visualRoom[key];
    }
  });

  if (typeof merged.lighting !== 'string') {
    delete merged.lighting;
  }
  if (!_isPlainObject(merged.terrain) || typeof merged.terrain.type !== 'string') {
    delete merged.terrain;
  }

  if (!_hasMeaningfulValue(merged.subtitle)) {
    merged.subtitle = _buildRoomSubtitle(merged);
  }

  return merged;
}

function _buildRoomSubtitle(room = {}) {
  if (!_isPlainObject(room)) {
    return '';
  }

  const terrainValue = typeof room?.terrain?.type === 'string' ? room.terrain.type : '';
  const terrainLabel = String(terrainValue || '').replace(/_/g, ' ').trim();
  const lightingValue = typeof room?.lighting === 'string' ? room.lighting : '';
  const lightingLabel = lightingValue && lightingValue !== 'normal'
    ? `Lighting: ${String(lightingValue).replace(/_/g, ' ')}`
    : '';
  const sizeLabel = room?.size_category && room.size_category !== 'medium'
    ? String(room.size_category).replace(/_/g, ' ')
    : '';

  return [terrainLabel, lightingLabel, sizeLabel].filter(Boolean).join(' | ');
}

/**
 * Build a connections array for the navigate sub-panel from mapVisualState topology.
 * Returns connections that originate FROM the given roomId (or are passable to/from it).
 *
 * @param {string} roomId
 * @param {object} mapVisualState
 * @returns {Array<{room_id, room_name, connection_id, direction?}>}
 */
function _buildRoomConnections(roomId, mapVisualState) {
  const topology = mapVisualState?.topology ?? {};
  const rooms = topology.rooms ?? {};
  const room = rooms?.[roomId] ?? null;
  const exits = Array.isArray(room?.exits) ? room.exits : [];

  const result = [];
  const seen = new Set();

  exits.forEach((exit) => {
    if (!exit?.is_passable) return;

    const targetRoomId = String(exit?.target_room_id || '').trim();
    const connectionId = String(exit?.connection_id || '').trim();
    if (!targetRoomId || !connectionId || seen.has(connectionId)) return;

    seen.add(connectionId);
    result.push({
      connection_id: connectionId,
      room_id:       targetRoomId,
      room_name:     rooms[targetRoomId]?.name ?? targetRoomId,
      type:          exit.type ?? 'open_passage',
    });
  });

  return result;
}

function _buildRenderableEntityBlueprints(dungeonData = {}, activeRoomId = '', launchCharacter = {}, mapVisualState = {}) {
  const roomId = String(activeRoomId || '').trim();
  if (!roomId) {
    return [];
  }

  const objectDefinitions = _getPresentationObjectDefinitions(mapVisualState, dungeonData);
  const visualOccupants = _buildVisualOccupantIndex(mapVisualState);
  const blueprints = [];
  const seen = new Set();
  const projectedEntitySignatures = new Set();
  const logicalActorSignatures = new Set();
  const launchCharacterId = Number(
    launchCharacter?.id
    || launchCharacter?.character_id
    || 0,
  ) || null;
  const launchCharacterName = String(
    launchCharacter?.basicInfo?.name
    || launchCharacter?.name
    || launchCharacter?.character_name
    || '',
  ).trim();
  const normalizedLaunchCharacterName = launchCharacterName.toLowerCase();
  const launchPortraitSpriteId = String(
    launchCharacter?.portrait?.sprite_id
    || launchCharacter?.portrait_sprite_id
    || launchCharacter?.portraitSpriteId
    || '',
  ).trim();

  const entities = Array.isArray(dungeonData?.entities) ? dungeonData.entities : [];
  entities.forEach((entity) => {
    const placement = _isPlainObject(entity?.placement) ? entity.placement : {};
    const hex = _isPlainObject(placement?.hex) ? placement.hex : {};
    const entityRoomId = String(placement?.room_id || '').trim();
    const q = Number(hex?.q);
    const r = Number(hex?.r);
    if (entityRoomId !== roomId || !Number.isFinite(q) || !Number.isFinite(r)) {
      return;
    }

    const metadata = _isPlainObject(entity?.state?.metadata) ? entity.state.metadata : {};
    const rawType = String(entity?.entity_type || entity?.entityType || '').trim().toLowerCase();
    const entityType = _normalizeRenderableEntityType(rawType, entity?.entity_ref?.content_type, metadata);
    const contentId = String(entity?.entity_ref?.content_id || '').trim();
    const definition = contentId ? (objectDefinitions[contentId] || {}) : {};
    const instanceId = String(entity?.entity_instance_id || entity?.instance_id || entity?.id || '').trim()
      || `payload-entity:${roomId}:${q}:${r}:${contentId || rawType || 'unknown'}`;
    const visual = _resolveVisualOccupant(visualOccupants, instanceId, contentId, roomId, q, r);
    const entityCharacterId = Number(metadata?.character_id || entity?.character_id || 0) || null;
    const isLaunchPlayerEntity = entityType === 'player_character'
      || Boolean(entityCharacterId && launchCharacterId && entityCharacterId === launchCharacterId);
    const name = String(
      metadata?.display_name
      || metadata?.name
      || entity?.display_name
      || (isLaunchPlayerEntity ? launchCharacterName : '')
      || definition?.label
      || contentId
      || rawType
      || 'entity',
    ).trim();
    const team = _normalizeRenderableEntityTeam(
      metadata?.team
      || visual?.presentation?.badge
      || (entityCharacterId && launchCharacterId && entityCharacterId === launchCharacterId ? 'player' : ''),
    );
    const hidden = visual?.visible === false || entity?.state?.hidden === true;
    const logicalActorKey = _buildLogicalActorIdentityKey(rawType, metadata, instanceId, roomId);

    const blueprint = {
      key: _buildRenderableEntityKey(instanceId, contentId, q, r),
      sourceKind: 'entity',
      roomId,
      q,
      r,
      instanceId,
      entityRef: instanceId,
      entityType,
      contentId,
      characterId: entityCharacterId,
      name: name !== '' ? name : 'entity',
      description: String(metadata?.description || definition?.description || '').trim(),
      hidden,
      combatCapable: entityType === 'player_character' || entityType === 'npc' || entityType === 'creature',
      team,
      actionsPerTurn: Number(metadata?.actions_per_turn || 3) || 3,
      initiativeBonus: Number(metadata?.initiative_bonus || 0) || 0,
      attackBonus: Number(metadata?.attack_bonus || 0) || 0,
      stats: {
        maxHp: Number(metadata?.stats?.maxHp ?? metadata?.stats?.max_hp ?? metadata?.max_hp ?? 10) || 10,
        currentHp: Number(metadata?.stats?.currentHp ?? metadata?.stats?.current_hp ?? metadata?.hp ?? metadata?.max_hp ?? 10) || 10,
        ac: Number(metadata?.stats?.ac ?? metadata?.armor_class ?? 10) || 10,
        perception: Number(metadata?.stats?.perception ?? metadata?.perception ?? 0) || 0,
        speed: Number(metadata?.movement_speed ?? metadata?.stats?.speed ?? 30) || 30,
      },
      render: {
        spriteKey: String(
          metadata?.sprite_id
          || definition?.visual?.sprite_id
          || visual?.presentation?.sprite_id
          || (isLaunchPlayerEntity ? launchPortraitSpriteId : '')
          || '',
        ).trim() || null,
        scale: Number(metadata?.render_scale ?? (entityType === 'item' ? 0.55 : 1)) || (entityType === 'item' ? 0.55 : 1),
        orientation: String(placement?.orientation || metadata?.orientation || definition?.visual?.orientation || 'n').trim().toLowerCase() || 'n',
        objectCategory: String(definition?.category || metadata?.object_category || '').trim() || null,
        objectColor: definition?.visual?.color || metadata?.object_color || visual?.presentation?.color || null,
      },
      state: _isPlainObject(entity?.state) ? entity.state : {},
      source: entity,
    };

    if (!hidden && !seen.has(blueprint.key) && (!logicalActorKey || !logicalActorSignatures.has(logicalActorKey))) {
      seen.add(blueprint.key);
      if (logicalActorKey) {
        logicalActorSignatures.add(logicalActorKey);
      }
      if (contentId) {
        projectedEntitySignatures.add(_buildRenderableProjectionKey(contentId, roomId, q, r));
      }
      blueprints.push(blueprint);
    }
  });

  const activeRoom = mapVisualState?.topology?.rooms?.[roomId];
  const roomHexes = Array.isArray(activeRoom?.hexes) ? activeRoom.hexes : [];
  roomHexes.forEach((hex) => {
    const q = Number(hex?.q);
    const r = Number(hex?.r);
    if (!Number.isFinite(q) || !Number.isFinite(r)) {
      return;
    }

    const objects = Array.isArray(hex?.objects) ? hex.objects : [];
    objects.forEach((object, objectIndex) => {
      const contentId = String(object?.object_id || object?.id || '').trim();
      if (!contentId) {
        return;
      }

      const definition = objectDefinitions[contentId] || {};
      const entityType = _normalizeRenderableEntityType('', object?.category, object);
      const projectionKey = _buildRenderableProjectionKey(contentId, roomId, q, r);
      if (projectedEntitySignatures.has(projectionKey)) {
        return;
      }

      const instanceId = String(object?.object_instance_id || '').trim()
        || `room-object:${roomId}:${q}:${r}:${contentId}:${objectIndex}`;
      const key = _buildRenderableEntityKey(instanceId, roomId, q, r);
      if (seen.has(key)) {
        return;
      }

      const blueprint = {
        key,
        sourceKind: 'hex-object',
        roomId,
        q,
        r,
        instanceId,
        entityRef: contentId,
        entityType,
        contentId,
        characterId: null,
        name: String(object?.label || object?.name || definition?.label || contentId).trim() || contentId,
        description: String(object?.description || definition?.description || '').trim(),
        hidden: false,
        combatCapable: false,
        team: 'neutral',
        actionsPerTurn: 0,
        initiativeBonus: 0,
        attackBonus: 0,
        stats: {
          maxHp: 10,
          currentHp: 10,
          ac: 10,
          perception: 0,
          speed: 0,
        },
        render: {
          spriteKey: String(object?.visual?.sprite_id || definition?.visual?.sprite_id || '').trim() || null,
          scale: Number(entityType === 'item' ? 0.55 : 0.95) || 1,
          orientation: String(object?.orientation || definition?.visual?.orientation || 'n').trim().toLowerCase() || 'n',
          objectCategory: String(object?.category || definition?.category || '').trim() || null,
          objectColor: object?.visual?.color || definition?.visual?.color || null,
        },
        state: {
          active: true,
          metadata: {
            passable: object?.passable,
            movable: object?.movable,
            collectible: object?.collectible,
            blocks_movement: object?.blocks_movement,
            stackable: typeof object?.stackable === 'boolean' ? object.stackable : Boolean(definition?.stackable),
          },
        },
        source: object,
      };

      seen.add(key);
      blueprints.push(blueprint);
    });
  });

  _getVisualOccupants(mapVisualState)
    .filter((occupant) => {
      if (!_isVisualOccupantVisible(occupant)) {
        return false;
      }
      return String(occupant?.room_id || '').trim() === roomId;
    })
    .forEach((occupant, occupantIndex) => {
      const q = Number(occupant?.placement?.q);
      const r = Number(occupant?.placement?.r);
      if (!Number.isFinite(q) || !Number.isFinite(r)) {
        return;
      }

      const contentId = String(occupant?.content_id || '').trim();
      const occupantId = String(occupant?.occupant_id || '').trim();
      const projectionKey = contentId ? _buildRenderableProjectionKey(contentId, roomId, q, r) : '';
      if ((projectionKey && projectedEntitySignatures.has(projectionKey)) || (occupantId && seen.has(_buildRenderableEntityKey(occupantId, roomId, q, r)))) {
        return;
      }

      const definition = contentId ? (objectDefinitions[contentId] || {}) : {};
      const occupantType = String(occupant?.occupant_type || '').trim().toLowerCase();
      const entityType = _normalizeRenderableEntityType(occupantType, definition?.category, occupant);
      const isPartyOccupant = occupant?.is_party === true || occupantType === 'player_character' || occupantType === 'player' || occupantType === 'pc';
      const occupantCharacterId = Number(occupant?.character_id || occupant?.state?.character_id || 0) || null;
      const occupantLabel = String(occupant?.label || '').trim();
      const isLaunchPlayerOccupant = isPartyOccupant && (
        Boolean(occupantCharacterId && launchCharacterId && occupantCharacterId === launchCharacterId)
        || Boolean(occupantLabel && normalizedLaunchCharacterName && occupantLabel.toLowerCase() === normalizedLaunchCharacterName)
      );
      const instanceId = occupantId || `visual-occupant:${roomId}:${q}:${r}:${contentId || entityType || occupantIndex}`;
      const occupantMetadata = _isPlainObject(occupant?.state?.metadata) ? occupant.state.metadata : {};
      const key = _buildRenderableEntityKey(instanceId, roomId, q, r);
      const logicalActorKey = _buildLogicalActorIdentityKey(occupantType, occupantMetadata, instanceId, roomId, Boolean(isPartyOccupant));
      if (seen.has(key) || (logicalActorKey && logicalActorSignatures.has(logicalActorKey))) {
        return;
      }

      const team = _normalizeRenderableEntityTeam(
        isPartyOccupant
          ? 'player'
          : (occupant?.presentation?.badge || occupant?.team || occupant?.state?.team || '')
      );
      const combatCapable = entityType === 'player_character' || entityType === 'npc' || entityType === 'creature';
      const blueprint = {
        key,
        sourceKind: 'visual-occupant',
        roomId,
        q,
        r,
        instanceId,
        entityRef: occupantId || contentId || instanceId,
        entityType,
        contentId,
        characterId: occupantCharacterId,
        name: String(occupantLabel || (isLaunchPlayerOccupant ? launchCharacterName : '') || definition?.label || contentId || occupantType || 'occupant').trim(),
        description: String(occupant?.presentation?.summary || definition?.description || '').trim(),
        hidden: false,
        combatCapable,
        team,
        actionsPerTurn: Number(occupant?.state?.actions_per_turn || 3) || 3,
        initiativeBonus: Number(occupant?.state?.initiative_bonus || 0) || 0,
        attackBonus: Number(occupant?.state?.attack_bonus || 0) || 0,
        stats: {
          maxHp: Number(occupant?.state?.max_hp ?? occupant?.state?.stats?.max_hp ?? occupant?.state?.stats?.maxHp ?? 10) || 10,
          currentHp: Number(occupant?.state?.hp ?? occupant?.state?.current_hp ?? occupant?.state?.stats?.current_hp ?? occupant?.state?.stats?.currentHp ?? occupant?.state?.max_hp ?? 10) || 10,
          ac: Number(occupant?.state?.armor_class ?? occupant?.state?.stats?.ac ?? 10) || 10,
          perception: Number(occupant?.state?.perception ?? occupant?.state?.stats?.perception ?? 0) || 0,
          speed: Number(occupant?.state?.movement_speed ?? occupant?.state?.stats?.speed ?? 30) || 30,
        },
        render: {
          spriteKey: String(
            occupant?.presentation?.sprite_id
            || definition?.visual?.sprite_id
            || (isLaunchPlayerOccupant ? launchPortraitSpriteId : '')
            || '',
          ).trim() || null,
          scale: Number(occupant?.presentation?.render_scale ?? (entityType === 'item' ? 0.55 : 1)) || (entityType === 'item' ? 0.55 : 1),
          orientation: String(occupant?.placement?.orientation || occupant?.presentation?.orientation || definition?.visual?.orientation || 'n').trim().toLowerCase() || 'n',
          objectCategory: String(definition?.category || occupant?.category || '').trim() || null,
          objectColor: occupant?.presentation?.color || definition?.visual?.color || null,
        },
        state: _isPlainObject(occupant?.state) ? occupant.state : {},
        source: occupant,
      };

      seen.add(key);
      if (logicalActorKey) {
        logicalActorSignatures.add(logicalActorKey);
      }
      if (projectionKey) {
        projectedEntitySignatures.add(projectionKey);
      }
      blueprints.push(blueprint);
    });

  return blueprints;
}

function _buildVisualOccupantIndex(mapVisualState = {}) {
  const index = new Map();
  const buckets = mapVisualState?.occupants || {};
  const occupants = [
    ...(Array.isArray(buckets.party) ? buckets.party : []),
    ...(Array.isArray(buckets.entities) ? buckets.entities : []),
  ];

  occupants.forEach((occupant) => {
    const occupantId = String(occupant?.occupant_id || '').trim();
    const contentId = String(occupant?.content_id || '').trim();
    const roomId = String(occupant?.room_id || '').trim();
    const q = Number(occupant?.placement?.q);
    const r = Number(occupant?.placement?.r);
    if (occupantId) {
      index.set(occupantId, occupant);
    }
    if (contentId && roomId && Number.isFinite(q) && Number.isFinite(r)) {
      index.set(_buildRenderableProjectionKey(contentId, roomId, q, r), occupant);
    }
    if (contentId && roomId && !index.has(`${roomId}:${contentId}`)) {
      index.set(`${roomId}:${contentId}`, occupant);
    }
  });

  return index;
}

function _resolveVisualOccupant(visualOccupants, instanceId = '', contentId = '', roomId = '', q = 0, r = 0) {
  if (!(visualOccupants instanceof Map)) {
    return null;
  }

  const normalizedInstanceId = String(instanceId || '').trim();
  if (normalizedInstanceId && visualOccupants.has(normalizedInstanceId)) {
    return visualOccupants.get(normalizedInstanceId) || null;
  }

  const normalizedContentId = String(contentId || '').trim();
  const normalizedRoomId = String(roomId || '').trim();
  if (!normalizedContentId || !normalizedRoomId) {
    return null;
  }

  const exactKey = _buildRenderableProjectionKey(normalizedContentId, normalizedRoomId, q, r);
  if (visualOccupants.has(exactKey)) {
    return visualOccupants.get(exactKey) || null;
  }

  const roomKey = `${normalizedRoomId}:${normalizedContentId}`;
  if (visualOccupants.has(roomKey)) {
    return visualOccupants.get(roomKey) || null;
  }

  return null;
}

function _normalizeRenderableEntityType(rawType = '', fallbackCategory = '', metadata = {}) {
  const normalizedType = String(rawType || '').trim().toLowerCase();
  if (normalizedType === 'player_character' || normalizedType === 'player' || normalizedType === 'pc') {
    return 'player_character';
  }
  if (normalizedType === 'npc') {
    return 'npc';
  }
  if (normalizedType === 'creature') {
    return 'creature';
  }
  if (normalizedType === 'item' || normalizedType === 'treasure') {
    return 'item';
  }
  if (normalizedType === 'obstacle' || normalizedType === 'trap' || normalizedType === 'hazard') {
    return normalizedType;
  }

  const category = String(fallbackCategory || metadata?.category || metadata?.type || '').trim().toLowerCase();
  if (metadata?.is_party === true || metadata?.party_member === true || metadata?.isPlayer === true) {
    return 'player_character';
  }
  if (
    category.includes('item')
    || category.includes('loot')
    || category.includes('collect')
    || category.includes('quest_item')
    || metadata?.collectible === true
  ) {
    return 'item';
  }

  return 'obstacle';
}

function _normalizeRenderableEntityTeam(rawTeam = '') {
  const normalized = String(rawTeam || '').trim().toLowerCase();
  if (normalized === 'player' || normalized === 'ally' || normalized === 'enemy' || normalized === 'neutral') {
    return normalized;
  }
  return 'neutral';
}

function _buildRenderableEntityKey(instanceId = '', roomId = '', q = 0, r = 0) {
  const stableId = String(instanceId || '').trim() || 'entity';
  const stableRoomId = String(roomId || '').trim() || 'room';
  return `${stableRoomId}:${stableId}:${Number(q)}:${Number(r)}`;
}

function _buildRenderableProjectionKey(contentId = '', roomId = '', q = 0, r = 0) {
  const stableContentId = String(contentId || '').trim();
  const stableRoomId = String(roomId || '').trim() || 'room';
  if (stableContentId === '') {
    return '';
  }
  return `${stableRoomId}:${stableContentId}:${Number(q)}:${Number(r)}`;
}

function _buildLogicalActorIdentityKey(rawType = '', metadata = {}, instanceId = '', roomId = '', isPartyMember = false) {
  const stableRoomId = String(roomId || '').trim();
  if (!stableRoomId || !_isPlainObject(metadata)) {
    return '';
  }

  const entityType = String(rawType || '').trim().toLowerCase();
  const team = _normalizeRenderableEntityTeam(metadata?.team || '');
  const followerKind = String(metadata?.follower_kind || metadata?.bond_contract?.follower_kind || '').trim().toLowerCase();
  const sourceCharacterId = Number(metadata?.source_character_id || 0) || 0;
  const campaignCharacterId = Number(metadata?.campaign_character_id || 0) || 0;
  const characterId = Number(metadata?.character_id || 0) || 0;
  const ownerSourceCharacterId = Number(metadata?.owner_source_character_id || metadata?.bond_contract?.owner_source_character_id || 0) || 0;
  const ownerCharacterId = Number(metadata?.owner_character_id || metadata?.bond_contract?.owner_character_id || 0) || 0;
  const followerSourceCharacterId = Number(metadata?.follower_source_character_id || 0) || 0;
  const runtimeEntityId = String(metadata?.runtime_entity_id || instanceId || '').trim();
  const isPlayerLike = Boolean(isPartyMember)
    || entityType === 'player_character'
    || entityType === 'player'
    || entityType === 'pc'
    || team === 'player';

  if (followerKind && followerSourceCharacterId > 0) {
    return `${stableRoomId}:follower-source:${followerSourceCharacterId}:${followerKind}`;
  }
  if (followerKind && ownerSourceCharacterId > 0) {
    return `${stableRoomId}:follower-owner-source:${ownerSourceCharacterId}:${followerKind}`;
  }
  if (followerKind && ownerCharacterId > 0) {
    return `${stableRoomId}:follower-owner:${ownerCharacterId}:${followerKind}`;
  }
  if (isPlayerLike && sourceCharacterId > 0) {
    return `${stableRoomId}:player-source:${sourceCharacterId}`;
  }
  if (campaignCharacterId > 0) {
    return `${stableRoomId}:campaign-character:${campaignCharacterId}`;
  }
  if (isPlayerLike && characterId > 0) {
    return `${stableRoomId}:player-character:${characterId}`;
  }
  if (runtimeEntityId) {
    return `${stableRoomId}:runtime:${runtimeEntityId}`;
  }

  return '';
}
