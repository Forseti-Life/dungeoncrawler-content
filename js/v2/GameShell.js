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
   * Initialize all sub-modules. Called from Drupal.behaviors.hexMapV2.attach.
   * Order: bus → ECS → canvas → systems → panels → emit game:init
   */
  init() {
    this.bus = new GameEventBus();
    this._initECS();
    this._initCanvas();
    this._initSystems();
    this._initPanels();

    // Build flat quests array with objectives flattened from phases
    const allQuests = [
      ...(Array.isArray(this.questSummary?.active) ? this.questSummary.active : []),
      ...(Array.isArray(this.questSummary?.offers) ? this.questSummary.offers : []),
      ...(Array.isArray(this.questSummary?.leads)  ? this.questSummary.leads  : []),
    ].map((q) => ({ ...q, objectives: _flattenQuestObjectives(q) }));

    this.bus.emit('game:init', {
      launchContext: this.launchContext,
      // Canonical keys panels expect
      character:     this.launchCharacter,
      inventory:     {
        items:    this.launchCharacter?.inventory?.items ?? [],
        currency: this.launchCharacter?.currency ?? this.launchCharacter?.inventory?.currency ?? {},
      },
      quests: allQuests,
      // Raw payloads for systems that need full context
      launchCharacter: this.launchCharacter,
      questSummary:  this.questSummary,
      dungeonData:   this.dungeonData,
      mapVisualState: this.mapVisualState,
      activeRoomId:  this.activeRoomId,
    });
    this._emitInitialRoomState();
    this._initApiHandlers();
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
    this._activeRoomData = room ?? null;

    this.bus.emit('room:changed', {
      roomId,
      roomName,
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
   * Wire up API-driven handlers: tab-change triggers, chat submit, initial loads.
   * Called once after initial bus events are emitted.
   * @private
   */
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
        this.bus.emit('entity:deselected');
        return;
      }
      const entity = this.entityManager?.getEntity(id) ?? null;
      if (entity) {
        this.bus.emit('entity:selected', { entity });
      } else {
        console.warn('[GameShell] entity:select-request — entity not found in ECS:', id);
      }
    });

    // CharacterPanel requests inventory refresh from API
    this.bus.on('character:inventory-refresh-requested', (ctx) => {
      if (ctx) void this.refreshCharacterInventoryFromApi(ctx);
      if (this.activeGameShellTab === 'merchant') {
        void this._loadMerchantStock(true);
      }
    });

    // Bridge: when NavigationSystem fires room:changed after a room transition,
    // relay occupants to room:occupants-changed and reload per-room data.
    // We mark our own internal room:changed emits with _source:'shell' to avoid loops.
    this.bus.on('room:changed', ({ roomId, roomName, occupants, _source } = {}) => {
      if (_source === 'shell' || !roomId) return;
      this._chatHistoryLoaded = false;
      this.activeRoomId = roomId;
      this._activeRoomData = this.mapVisualState?.topology?.rooms?.[roomId] ?? null;
      // Reset view state for new room
      this._clearRoomViewRetry();
      this._roomViewLastKey = null;
      this._roomViewHasContent = false;
      // Update navigate panel connections for the new room
      this.bus.emit('room:changed', {
        roomId,
        roomName,
        connections: _buildRoomConnections(roomId, this.mapVisualState),
        _source: 'shell',
      });
      // Relay occupants (empty array clears panels for the new room — correct)
      if (Array.isArray(occupants)) {
        this._currentOccupants = occupants;
        this.bus.emit('room:occupants-changed', { roomId, roomName, occupants });
      }
      // Pre-load chat history and scene image for the new room
      this._loadChatHistory();
      this._loadRoomView();
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
      if (!resp.ok) return;
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

    // Optimistic echo of the player's line
    this.bus.emit('chat:message-received', {
      line: { speaker, message: message.trim(), type: 'say', channel },
      channel,
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
      // Emit non-player response lines (GM narration, NPC replies, system)
      (result.data?.messages ?? []).forEach((msg) => {
        const type = msg.type ?? 'npc';
        if (type === 'player') return;
        this.bus.emit('chat:message-received', {
          line: {
            speaker: msg.speaker ?? 'GM',
            message: msg.message ?? '',
            type,
            channel: msg.channel ?? channel,
          },
          channel: msg.channel ?? channel,
        });
      });

      // Relay any quest progress updates — merge into local summary and emit full summary
      const questUpdates = result.data?.quest_updates ?? [];
      if (questUpdates.length > 0) {
        if (!this.questSummary) this.questSummary = { active: [], offers: [], leads: [] };
        questUpdates.forEach((q) => {
          const updated = { ...q, objectives: _flattenQuestObjectives(q) };
          const questKey = q.quest_id ?? q.quest_key ?? q.id ?? null;
          ['active', 'offers', 'leads'].forEach((bucket) => {
            if (!Array.isArray(this.questSummary[bucket])) this.questSummary[bucket] = [];
            if (questKey) {
              const idx = this.questSummary[bucket].findIndex(
                (x) => (x?.quest_id ?? x?.quest_key ?? x?.id) === questKey
              );
              if (idx >= 0) this.questSummary[bucket][idx] = updated;
              else if (bucket === 'active') this.questSummary[bucket].push(updated);
            }
          });
        });
        this.bus.emit('quest:progress-updated', { questSummary: this.questSummary });
      }

      // Notify ChatPanel the turn is complete
      this.bus.emit('chat:turn-status-changed', { status: 'idle' });
    } catch (_) {
      this.bus.emit('game:server-unavailable', { message: 'Server unreachable. Please check your connection.' });
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
    const payloadRoomBase = { ...visualRoom, id: roomId };

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
    console.log('[GameShell] _loadRoomView', { campaignId, roomId, force, preserveExisting });

    // Show "Generating" immediately unless preserving existing gallery
    if (!preserveExisting || !this._roomViewHasContent) {
      this.bus.emit('room:view-loaded', {
        room: payloadRoomBase,
        viewState: { statusLabel: 'Generating', placeholderText: 'Loading room scene...', entries: [] },
      });
    }

    const request = (async () => {
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

      // id must come AFTER spread — API room object may contain id:undefined
      const payloadRoom = { ...(result.data.room ?? visualRoom), id: roomId };
      const roomName = payloadRoom?.name ?? visualRoom?.name ?? roomId;

      const statusLabel = entries.length > 0
        ? `${entries.length} Scene${entries.length === 1 ? '' : 's'}`
        : (result.data.available === false ? 'Unavailable' : 'Pending');
      const placeholderText = entries.length > 0
        ? ''
        : (result.data.message || 'No room view image is available yet.');

      const dataStatus = String(result.data.status || '').toLowerCase();
      this._roomViewHasContent = entries.length > 0;

      console.log('[GameShell] _loadRoomView: result', {
        rawEntries: result.data.entries?.length ?? 0,
        filteredEntries: entries.length,
        sceneImageUrl: !!sceneImageUrl,
        available: result.data.available,
        status: dataStatus,
        message: result.data.message ?? null,
      });

      this.bus.emit('room:changed', { roomId, roomName, sceneImageUrl, responders: [], _source: 'shell' });
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
      this.bus.emit('room:view-loaded', {
        room: payloadRoomBase,
        viewState: { statusLabel: 'Unavailable', placeholderText: err?.message || 'Room view generation failed.', entries: [] },
      });
    } finally {
      this._roomViewInflight.delete(viewKey);
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
   * Fetch stock for all merchant occupants in the current room and
   * re-emit room:occupants-changed with stock injected into presentation.
   * @private
   */
  async _loadMerchantStock() {
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
   * Build a lightweight shim that satisfies the `stateManager.hexmap` interface
   * used by ported panel methods. Returns an object with the most-used accessors.
   * Missing methods degrade gracefully via optional chaining at call sites.
   * @private
   */
  _buildHexmapShim() {
    const shell = this;
    return {
      // Core resolution
      resolveCampaignId:   () => shell.launchContext?.campaign_id ?? null,
      resolveActiveRoomId: () => shell.activeRoomId,
      // Data refs (live values via getters for freshness)
      get dungeonData()    { return shell.dungeonData; },
      get launchContext()  { return shell.launchContext; },
      get characterData()  { return shell.launchCharacter; },
      get launchCharacter(){ return shell.launchCharacter; },
      get entityManager()  { return shell.entityManager; },
      get movementSystem() { return shell.movementSystem; },
      // Occupant queries
      getVisualOccupants:  () => shell._currentOccupants,
      getVisualRooms:      () => [],
      getActiveRoomData:   () => shell._activeRoomData ?? null,
      buildActiveRoomOccupantSummary: () => '',
      isVisualOccupantVisible: () => true,
      getObjectDefinition: () => null,
      spriteService: { getCachedUrl: () => null },
      // Entity interaction
      selectEntity:        (id) => shell.bus.emit('entity:select-request', { id }),
      // Navigation / automation stubs
      resolveNavigationCapabilities: () => ({}),
      getPlayerAutomationState:      () => null,
      startPlayerAutomation:         () => {},
      stopPlayerAutomation:          () => {},
      // Combat
      startCombat:         () => shell.systems.encounter?.startCombat?.(),
      endCombat:           () => shell.systems.encounter?.endCombat?.(),
      endTurn:             () => shell.systems.encounter?.endCurrentTurn?.(),
      getHostileTargets:   () => [],
      hasLineOfSight:      () => false,
      performCombatAction: () => {},
      // Inner stateManager.get used by ActionRailPanel
      stateManager: {
        get: (_key) => null,
      },
    };
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
    const hexmap = this._buildHexmapShim();
    // stateManager.get needed by ActionRailPanel (this.stateManager?.get?.('selectedEntity'))
    const stateManager = { hexmap, get: (_key) => null };

    console.log('[GameShell] _initPanels start', { dungeonData: !!this.dungeonData, launchCharacter: !!this.launchCharacter });

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

    this.panels.portrait.init(this.dungeonData, stateManager);
    this.panels.merchant.init(this.dungeonData, stateManager, this.panels.inventory);
    this.panels.actionRail.init(this.dungeonData, stateManager);
    this.panels.chat.init(this.dungeonData, stateManager);
    this.panels.inventory.init(this.dungeonData, stateManager);
    this.panels.character.init(this.dungeonData, stateManager);
    this.panels.partyRail.init(this.dungeonData, stateManager);
    // Panels with no-arg init
    this.panels.combat.init();
    this.panels.quest.init();
    this.panels.roomView.init();
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
    this.canvas?.input?.destroy?.();
    this.canvas?.fog?.destroy?.();
    this.canvas?.tokens?.destroy?.();
    this.canvas?.app?.destroy?.();
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
  async refreshCharacterInventoryFromApi(context) {
    if (!context?.characterId || typeof fetch !== 'function') {
      return;
    }

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
      this.bus.emit('inventory:changed', {
        inventory: nextInventory,
        currency: nextInventory.currency || {},
        characterId: context.characterId,
      });
      if (this.activeGameShellTab === 'merchant') {
        this.panels.merchant.loadMerchantPanel(true);
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


// ---------------------------------------------------------------------------
// Module-level helpers
// ---------------------------------------------------------------------------

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
  const allConnections = Array.isArray(topology.connections) ? topology.connections : [];

  const result = [];
  const seen = new Set();

  allConnections.forEach((conn) => {
    if (!conn.is_passable) return;

    let targetRoomId = null;
    if (conn.from_room_id === roomId) targetRoomId = conn.to_room_id;
    else if (conn.to_room_id === roomId) targetRoomId = conn.from_room_id;
    if (!targetRoomId || seen.has(conn.connection_id)) return;

    seen.add(conn.connection_id);
    result.push({
      connection_id: conn.connection_id,
      room_id:       targetRoomId,
      room_name:     rooms[targetRoomId]?.name ?? targetRoomId,
      type:          conn.type ?? 'open_passage',
    });
  });

  return result;
}
