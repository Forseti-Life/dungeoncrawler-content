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
 */

import { GameEventBus } from './GameEventBus.js';
import { HexCanvas } from './canvas/HexCanvas.js';
import { HexTokenRenderer } from './canvas/HexTokenRenderer.js';
import { HexFogOfWar } from './canvas/HexFogOfWar.js';
import { HexInputHandler } from './canvas/HexInputHandler.js';
import { EncounterSystem } from './systems/EncounterSystem.js?v=20260619-v2-search-reward-refresh-1';
import { NavigationSystem } from './systems/NavigationSystem.js?v=20260624-v2-room-sync-nav-2';
import { PlayerAutomation } from './systems/PlayerAutomation.js?v=20260608-v2-chat-persistence-dev-1';
import { QuestSystem } from './systems/QuestSystem.js?v=20260608-v2-quest-summary-merge-2';
import { MerchantPanel } from './panels/MerchantPanel.js';
import { CombatPanel } from './panels/CombatPanel.js';
import { ActionRailPanel } from './panels/ActionRailPanel.js?v=20260624-v2-room-sync-nav-2';
import { ChatPanel } from './panels/ChatPanel.js?v=20260624-v2-room-sync-nav-1';
import { QuestPanel } from './panels/QuestPanel.js?v=20260612-v2-quest-storyline-grouping-1';
import { InventoryPanel } from './panels/InventoryPanel.js';
import { CharacterPanel } from './panels/CharacterPanel.js?v=20260619-v2-character-loop-fix-1';
import { RoomViewPanel } from './panels/RoomViewPanel.js';
import { PartyRailPanel } from './panels/PartyRailPanel.js';
import { StatusPanel } from './panels/StatusPanel.js';
import { normalizeInventoryState } from './utils/inventory-utils.js';
import { normalizeQuestSummaryPayload } from './utils/quest-utils.js?v=20260607-quest-summary-const-4';
import { SpriteService } from '../SpriteService.js';
import { GameCoordinator } from '../game-coordinator/GameCoordinator.js?v=20260607-v2-search-coordinator-init-1';
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

    this.currentUserId = Number(rawSettings.userId || rawSettings.user?.uid || 0);
    this.activeRoomId =
      this.mapVisualState?.map_meta?.active_room_id ||
      this.launchContext?.room_id ||
      null;
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

    /** @type {{ merchant: MerchantPanel, combat: CombatPanel, actionRail: ActionRailPanel, chat: ChatPanel, quest: QuestPanel, inventory: InventoryPanel, character: CharacterPanel, roomView: RoomViewPanel, partyRail: PartyRailPanel, status: StatusPanel }} */
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
    /** @type {string} currently active tab id */
    this.activeGameShellTab = 'map';
  }

  /**
   * Refresh quest journal state from the server and emit the canonical summary.
   *
   * @returns {Promise<boolean>}
   *   TRUE when refreshed successfully; otherwise FALSE.
   */
  async refreshQuestJournalFromApi() {
    const campaignId = this.resolveCampaignId();
    if (!campaignId || typeof fetch !== 'function') {
      return false;
    }

    const characterId = Number(this.launchContext?.character_id || 0);
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

      this.bus?.emit('quest:progress-updated', { questSummary: this.questSummary });
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
    this._bindMapControls();
    this._bindInteractionEvents();

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
      void this.refreshCharacterInventoryFromApi(launchInventoryContext);
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
    this.gameCoordinator.init()
      .then(() => {
        const authoritativeRoomId = String(this.gameCoordinator?.phaseManager?.activeRoomId || '').trim();
        if (authoritativeRoomId && authoritativeRoomId !== this.activeRoomId) {
          const room = _mergeRoomMetadata(this.mapVisualState?.topology?.rooms?.[authoritativeRoomId] ?? null, {}, authoritativeRoomId);
          this.activeRoomId = authoritativeRoomId;
          this._activeRoomData = room;
          this._setStateValue('activeRoomId', authoritativeRoomId);
          this.bus.emit('room:changed', {
            roomId: authoritativeRoomId,
            roomName: room?.name ?? authoritativeRoomId,
            room,
            sceneImageUrl: room?.image_url ?? null,
            connections: _buildRoomConnections(authoritativeRoomId, this.mapVisualState),
            responders: [],
            _source: 'shell',
          });
          this._syncActiveRoomEntities(authoritativeRoomId);
          this._loadChatHistory();
          this._loadRoomView({ force: true, preserveExisting: true });
        }
        this.panels?.actionRail?.refreshActionRail?.();
      })
      .catch((error) => {
        console.warn('GameCoordinator init failed; coordinator-driven action dispatch disabled:', error?.message || error);
        this.gameCoordinator = null;
        this.panels?.actionRail?.refreshActionRail?.();
      });
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

    this.bus.emit('room:changed', {
      roomId,
      roomName,
      room,
      sceneImageUrl: room?.image_url ?? null,
      connections:   _buildRoomConnections(roomId, this.mapVisualState),
      responders: [],
      _source: 'shell',
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
    this.bus.emit('room:occupants-changed', {
      roomId,
      roomName,
      occupants: roomOccupants,
    });
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
      const response = await fetch(`/api/campaign/${campaignId}/gm/locations/request`, {
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

    // RoomViewPanel requests a room view reload (e.g. retry after pending)
    this.bus.on('room:view-reload-requested', (opts) => this._loadRoomView(opts ?? {}));

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
    });

    // CharacterPanel requests inventory refresh from API
    this.bus.on('character:inventory-refresh-requested', (ctx) => {
      if (ctx) void this.refreshCharacterInventoryFromApi(ctx);
      if (this.activeGameShellTab === 'merchant') {
        void this._loadMerchantStock(true);
      }
    });

    // Character/quest UI requests canonical quest journal refresh.
    this.bus.on('quest:refresh-requested', () => {
      void this.refreshQuestJournalFromApi();
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

    // Bridge: when NavigationSystem fires room:changed after a room transition,
    // relay occupants to room:occupants-changed and reload per-room data.
    // We mark our own internal room:changed emits with _source:'shell' to avoid loops.
    this.bus.on('room:changed', ({ roomId, roomName, occupants, _source } = {}) => {
      if (_source === 'shell' || !roomId) return;
      this._chatHistoryLoaded = false;
      this.activeRoomId = roomId;
      this._activeRoomData = _mergeRoomMetadata(this.mapVisualState?.topology?.rooms?.[roomId] ?? null, {}, roomId);
      this._setStateValue('activeRoomId', roomId);
      // Reset view state for new room
      this._clearRoomViewRetry();
      this._roomViewLastKey = null;
      this._roomViewHasContent = false;
      this._merchantStockLoading = false;
      // Update navigate panel connections for the new room
      this.bus.emit('room:changed', {
        roomId,
        roomName,
        room: this._activeRoomData,
        sceneImageUrl: this._activeRoomData?.image_url ?? null,
        connections: _buildRoomConnections(roomId, this.mapVisualState),
        _source: 'shell',
      });
      // Relay occupants (empty array clears panels for the new room — correct)
      if (Array.isArray(occupants)) {
        this._currentOccupants = occupants;
        this.bus.emit('room:occupants-changed', { roomId, roomName, occupants });
      }
      this._syncActiveRoomEntities(roomId);
      // Pre-load chat history and scene image for the new room
      this._loadChatHistory();
      this._loadRoomView();
      this.prefetchConnectedRoomContext();
    });

    this.bus.on('combat:state-changed', ({ state } = {}) => {
      const normalized = String(state || '').trim().toLowerCase();
      const active = normalized === 'active' || normalized === 'in_progress';
      this._setStateValue('combatActive', active);
      if (!active) {
        this._setStateValue('encounterId', null);
        this._setStateValue('latestEncounterState', null);
        this._setStateValue('serverCombatMode', false);
      }
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
    if (tabId === 'view')      this._loadRoomView({ preserveExisting: this._roomViewHasContent });
    if (tabId === 'merchant')  this._loadMerchantStock();
    if (tabId !== 'view' && prevTab === 'view') this._clearRoomViewRetry();
    if (tabId === 'chat' && !this._chatHistoryLoaded) this._loadChatHistory();
    if (tabId === 'character') {
      const charId = this.launchCharacter?.id ?? this.launchContext?.character_id ?? null;
      console.log('[GameShell] character tab → sheet-requested', { charId });
      if (charId) this.bus.emit('character:sheet-requested', { characterId: charId });
      void this.refreshQuestJournalFromApi();
    }
  }

  /**
   * Load chat history for the active room and emit chat:history-loaded.
   * @private
   */
  async _loadChatHistory() {
    const campaignId = this.launchContext?.campaign_id;
    const roomId     = this.activeRoomId;
    const charId     = this.launchCharacter?.id ?? this.launchContext?.character_id;
    if (!campaignId || !roomId) {
      console.warn('[GameShell] _loadChatHistory: missing campaignId or roomId', { campaignId, roomId });
      return;
    }
    console.log('[GameShell] _loadChatHistory', { campaignId, roomId });

    try {
      let url = `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/chat`;
      if (charId) url += `?character_id=${encodeURIComponent(charId)}`;
      const resp = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
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
      if (!result?.success || !Array.isArray(result.data?.messages)) {
        console.warn('[GameShell] _loadChatHistory: unexpected response', { ok: resp.ok, success: result?.success, messageCount: result?.data?.messages?.length });
        return;
      }

      this._chatHistoryLoaded = true;
      console.log('[GameShell] _loadChatHistory: loaded', { lineCount: result.data.messages.length });
      this.bus.emit('chat:history-loaded', result);
    } catch (_) {
      // Chat history is best-effort; no user-facing error
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
      const questUpdates = result.data?.quest_updates ?? [];
      if (questUpdates.length > 0) {
        await this.applyQuestUpdates(questUpdates);
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
   * re-emit room:occupants-changed with normalized merchant presentation fields.
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
    this.bus.emit('room:occupants-changed', {
      roomId,
      roomName: room?.name ?? roomId,
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
      bus.emit('combat:state-changed', { state });
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

    const tokens = new HexTokenRenderer(hexCanvas, this.bus);
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

      this.bus.on('hex:clicked', ({ q, r, button = 0, entities = [] } = {}) => {
        if (!Number.isFinite(Number(q)) || !Number.isFinite(Number(r))) {
          return;
        }

        if (Number(button) === 2) {
          this.deselectEntity();
          return;
        }

        this.setSelectedHex(q, r, { emitDetails: false });
        if (this.tryTransitionAtHex(q, r)) {
          return;
        }

        const hexEntities = Array.isArray(entities) && entities.length ? entities : this.getEntitiesAtHex(q, r);
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
      // Occupant queries
      hasVisualOccupants:  () => shell.hasVisualOccupants(),
      getVisualOccupants:  () => shell.getVisualOccupants(),
      getVisualRooms:      () => shell.getVisualRooms(),
      getPresentationObjectDefinitions: () => shell.getPresentationObjectDefinitions(),
      getVisualConnections: () => shell.getVisualConnections(),
      parseVisualHexId:    (hexId) => shell.parseVisualHexId(hexId),
      getConnectionRoomId: (connection, side) => shell.getConnectionRoomId(connection, side),
      getConnectionHex:    (connection, side) => shell.getConnectionHex(connection, side),
      getActiveRoomData:   () => shell.getActiveRoomData(),
      getActiveRoomHex:    (q, r) => shell.getActiveRoomHex(q, r),
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
    this.panels.partyRail  = new PartyRailPanel(panel('[data-panel="party-rail"]'), bus);
    this.panels.status     = new StatusPanel(panel('[data-panel="status"]'), bus);

    this.panels.merchant.init(this.dungeonData, stateManager, this.panels.inventory);
    this.panels.actionRail.init(this.dungeonData, stateManager);
    this.panels.chat.init(this.dungeonData, stateManager);
    this.panels.inventory.init(this.dungeonData, stateManager);
    this.panels.character.init(this.dungeonData, stateManager);
    this.panels.partyRail.init(this.dungeonData, stateManager);
    // Panels with no-arg init
    this.panels.combat.init();
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

    this._currentOccupants = [];
    this._merchantRequestTokens.clear();
    this.entityManager = null;
    this.canvas = null;
    this.systems = {};
    this.panels = {};
    this.bus = null;
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

  // --- ported from hexmap.js ---
  resolveActiveRoomId() {
    const visualRoomId = this.mapVisualState?.map_meta?.active_room_id
      || Object.keys(this.mapVisualState?.topology?.rooms || {})[0]
      || null;
    return this.activeRoomId || this.state?.activeRoomId || visualRoomId || this.launchContext?.room_id || null;
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

  // --- ported from hexmap.js ---
  isVisualOccupantVisible(occupant) {
    return _isVisualOccupantVisible(occupant);
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
    return _resolveNavigationCapabilities(this.getVisualConnections(), roomId || this.resolveActiveRoomId());
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
    return {
      campaignId: this.resolveCampaignId(),
      characterId: launchCharacterId || selectedCharacterId || null,
      instanceId: launchCharacterId > 0 && selectedCharacterId === launchCharacterId
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
  selectEntity(entityOrId) {
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

    this._setStateValue('selectedEntity', entity);
    this.syncLaunchCharacterRuntimeFromEntity(entity);
    this.bus?.emit('entity:selected', { entity });
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
      terrain: terrainLabel,
      lighting: typeof activeRoom?.lighting === 'string' ? activeRoom.lighting : 'unknown',
      elevationFt: inRoom && Number.isFinite(Number(activeRoomHex?.elevation_ft)) ? Number(activeRoomHex.elevation_ft) : null,
      passability: this.describePassability(obstacleProfile, inRoom),
      entities: this.describeEntitiesAtHex(q, r),
      objects: this.describeObjectsAtHex(activeRoomHex, q, r),
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
  setActiveRoom(roomId) {
    const normalizedRoomId = String(roomId || '').trim();
    if (!normalizedRoomId) {
      return;
    }

    const room = _mergeRoomMetadata(this.getVisualRooms()[normalizedRoomId] || null, {}, normalizedRoomId);
    const occupants = this.getVisualOccupants().filter((occupant) => String(occupant?.room_id || '') === normalizedRoomId && this.isVisualOccupantVisible(occupant));
    this.activeRoomId = normalizedRoomId;
    this._activeRoomData = room;
    this._setStateValue('activeRoomId', normalizedRoomId);
    this._syncActiveRoomEntities(normalizedRoomId);
    this.bus?.emit('room:changed', {
      roomId: normalizedRoomId,
      roomName: room?.name ?? normalizedRoomId,
      room,
      sceneImageUrl: room?.image_url ?? null,
      connections: _buildRoomConnections(normalizedRoomId, this.mapVisualState),
      occupants,
    });
    this.bus?.emit('room:occupants-changed', {
      roomId: normalizedRoomId,
      roomName: room?.name ?? normalizedRoomId,
      occupants,
    });
    this.prefetchConnectedRoomContext?.();
  }

  // --- ported from hexmap.js ---
  persistLaunchLocationContext(roomId, q = null, r = null, entityRef = null) {
    this.updateLaunchLocationContext(roomId, q, r);
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
    const encounterId = Number(this._getStateValue('encounterId') || 0) || null;
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
    const actorRef = String(
      actorEntity?.dcEntityRef
      || actorEntity?.instanceId
      || options.actorId
      || '',
    ).trim();
    if (!actorRef) {
      return null;
    }

    const coordinator = this.gameCoordinator || null;
    const campaignId = this.resolveCampaignId();
    if (!coordinator?.api || !campaignId) {
      console.error('[GameShell] performCombatAction unavailable: coordinator not initialized', { actionType, campaignId });
      return null;
    }

    const params = {
      action_cost: Number(options?.actionCost || 0) || 0,
      character_id: Number(options?.characterId || 0) || null,
      target_hex: options?.targetHex ?? null,
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

    let data = null;
    try {
      data = await coordinator.api.sendAction(actionType, actorRef, params, {
        target: targetRef || undefined,
        stateVersion: coordinator.phaseManager?.stateVersion,
      });
    } catch (err) {
      console.error('[GameShell] performCombatAction coordinator call failed', err);
      this.notifyServerUnavailable();
      return null;
    }

    if (!data?.success) {
      console.warn('[GameShell] performCombatAction rejected', { actionType, error: data?.error, result: data?.result });
      return null;
    }

    coordinator.applyAuthoritativeUpdate?.(data);
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

    this.bus.emit('character:sheet-requested', { characterId: resolvedCharacterId });

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
      this.bus.emit('character:updated', { launchCharacter: this.launchCharacter });

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
      const context = { campaignId, roomId, characterId };
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

function _isVisualOccupantVisible(occupant) {
  if (!occupant) {
    return false;
  }

  if (occupant.visible === true) {
    return true;
  }

  if (occupant.visible === false) {
    return false;
  }

  const hidden = occupant?.hidden === true || occupant?.state?.hidden === true;
  const detected = occupant?.detected === true || occupant?.state?.detected === true;
  return !(hidden && !detected);
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

function _resolveNavigationCapabilities(rawConnections = [], roomId = null) {
  const activeRoomId = String(roomId || '').trim();
  if (!activeRoomId || !Array.isArray(rawConnections) || !rawConnections.length) {
    return [];
  }

  return rawConnections
    .filter((connection) => connection && typeof connection === 'object' && (_getConnectionRoomId(connection, 'from') === activeRoomId || _getConnectionRoomId(connection, 'to') === activeRoomId))
    .map((connection) => {
      const travelsForward = _getConnectionRoomId(connection, 'from') === activeRoomId;
      const targetRoomId = String(travelsForward ? (_getConnectionRoomId(connection, 'to') || '') : (_getConnectionRoomId(connection, 'from') || ''));
      const isDiscovered = Object.prototype.hasOwnProperty.call(connection, 'is_discovered')
        ? Boolean(connection.is_discovered)
        : true;
      const isPassable = Object.prototype.hasOwnProperty.call(connection, 'is_passable')
        ? Boolean(connection.is_passable)
        : true;
      const type = String(connection.type || 'passage');
      const destinationTypeRaw = String(connection.destination_type || connection.to_type || '').trim().toLowerCase();
      const destinationType = (destinationTypeRaw === 'road' || destinationTypeRaw === 'room')
        ? destinationTypeRaw
        : 'room';
      const parsedDistance = Number(connection.distance ?? connection.travel_distance ?? connection.distance_units ?? 0);
      const distance = Number.isFinite(parsedDistance) ? Math.max(0, Math.trunc(parsedDistance)) : 0;
      const blockedReason = !targetRoomId
        ? 'unresolved_destination'
        : ((destinationType === 'room' && distance !== 0)
          ? 'invalid_distance_contract'
          : (!isDiscovered ? 'undiscovered' : (!isPassable ? 'blocked' : null)))
      ;

      return {
        connection_id: String(connection.connection_id || `${_getConnectionRoomId(connection, 'from') || 'unknown'}__${_getConnectionRoomId(connection, 'to') || 'unknown'}`),
        origin_room_id: activeRoomId,
        target_room_id: targetRoomId,
        destination_type: destinationType,
        destination_id: destinationType === 'road'
          ? String(connection.road_node_id || connection.road_id || connection.to_id || '')
          : targetRoomId,
        type,
        available: blockedReason === null,
        blocked_reason: blockedReason,
        is_discovered: isDiscovered,
        is_passable: isPassable,
        bidirectional: Object.prototype.hasOwnProperty.call(connection, 'bidirectional')
          ? Boolean(connection.bidirectional)
          : type !== 'one_way',
        requires_interaction: !isPassable || ['door', 'locked_door', 'secret_door', 'trapped_door', 'barricade', 'collapsed', 'magical_barrier'].includes(type),
        distance,
        origin_hex: travelsForward ? (_getConnectionHex(connection, 'from') || null) : (_getConnectionHex(connection, 'to') || null),
        target_hex: travelsForward ? (_getConnectionHex(connection, 'to') || null) : (_getConnectionHex(connection, 'from') || null),
        connection,
      };
    });
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

  const startQ = Number.isFinite(Number(launchContext?.start_q)) ? Number(launchContext.start_q) : 0;
  const startR = Number.isFinite(Number(launchContext?.start_r)) ? Number(launchContext.start_r) : 0;
  const onStartHex = playerEntities.find((entity) => {
    const pos = entity.getComponent?.('PositionComponent');
    return pos && pos.q === startQ && pos.r === startR;
  });

  return onStartHex || playerEntities[0] || null;
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

    if (!hidden && !seen.has(blueprint.key)) {
      seen.add(blueprint.key);
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
      const key = _buildRenderableEntityKey(instanceId, roomId, q, r);
      if (seen.has(key)) {
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
