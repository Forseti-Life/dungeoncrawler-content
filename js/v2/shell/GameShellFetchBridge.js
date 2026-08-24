import { SessionViewBridge } from './SessionViewBridge.js';

/**
 * Fetch/event bridge for GameShell runtime API flows.
 *
 * Isolates request handlers and token-guarded chat-history loading so
 * GameShell remains orchestration-first.
 */
export class GameShellFetchBridge {
  constructor(shell) {
    this.shell = shell;
    this._sessionViewBridge = null;
    this._off = [];
  }

  register() {
    const shell = this.shell;
    const bus = shell?.bus;
    if (!bus || typeof bus.on !== 'function') {
      return;
    }

    if (!this._sessionViewBridge) {
      this._sessionViewBridge = new SessionViewBridge(
        bus,
        shell.fetchSessionViewData.bind(shell),
      );
      this._sessionViewBridge.register();
    }

    this._off.push(
      bus.on('user:chat-history-requested', () => this.loadChatHistory()),
      bus.on('room:view-refresh-intent', (opts) => this.handleRoomViewRefreshIntent(opts, 'room:view-refresh-intent')),
      bus.on('room:view-reload-requested', (opts) => this.handleRoomViewRefreshIntent(opts, 'room:view-reload-requested')),
    );
  }

  destroy() {
    if (this._sessionViewBridge) {
      this._sessionViewBridge.destroy();
      this._sessionViewBridge = null;
    }
    this._off.forEach((off) => {
      if (typeof off === 'function') {
        off();
      }
    });
    this._off = [];
  }

  handleRoomViewRefreshIntent(options = {}, eventName = 'room:view-refresh-intent') {
    const shell = this.shell;
    const requestedRoomId = String(options?.roomId || shell.activeRoomId || '').trim();
    const activeRoomId = String(shell.activeRoomId || '').trim();
    if (!requestedRoomId || !activeRoomId || requestedRoomId !== activeRoomId) {
      return;
    }
    shell._loadRoomView({
      ...options,
      roomId: requestedRoomId,
      preserveExisting: options?.preserveExisting !== false,
    });
    if (eventName === 'room:view-reload-requested') {
      console.debug('[GameShell] room:view-reload-requested is legacy; prefer room:view-refresh-intent');
    }
  }

  async loadChatHistory() {
    const shell = this.shell;
    const campaignId = shell.launchContext?.campaign_id;
    const roomId = shell.activeRoomId;
    const requestRoomId = String(roomId || '').trim();
    const charId = Number(
      shell.resolveLaunchCharacterRuntimeContext?.().characterId
      || shell.launchCharacter?.id
      || shell.launchContext?.character_id
      || 0
    ) || null;
    if (!campaignId || !roomId) {
      console.warn('[GameShell] _loadChatHistory: missing campaignId or roomId', { campaignId, roomId });
      return;
    }
    const mapId = String(
      shell.hexmap?.dungeonData?.map_id
      || shell.hexmap?.launchContext?.map_id
      || shell.launchContext?.map_id
      || shell.stateManager?.get?.('mapId')
      || ''
    ).trim();
    const requestKey = `${campaignId}:${requestRoomId}:${charId || 0}:${mapId || ''}`;
    if (!(shell._chatHistoryInflight instanceof Map)) {
      shell._chatHistoryInflight = new Map();
    }
    if (!(shell._chatHistoryLastLoadedAt instanceof Map)) {
      shell._chatHistoryLastLoadedAt = new Map();
    }
    if (shell._chatHistoryInflight.has(requestKey)) {
      return shell._chatHistoryInflight.get(requestKey);
    }
    const loadedAt = Number(shell._chatHistoryLastLoadedAt.get(requestKey) || 0);
    if (loadedAt > 0 && (Date.now() - loadedAt) < 1200) {
      return;
    }

    const requestToken = ++shell._chatHistoryRequestToken;
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
        if (requestToken !== shell._chatHistoryRequestToken) {
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
        if (requestToken !== shell._chatHistoryRequestToken) {
          return;
        }
        if (!result?.success || !Array.isArray(result.data?.messages)) {
          console.warn('[GameShell] _loadChatHistory: unexpected response', { ok: resp.ok, success: result?.success, messageCount: result?.data?.messages?.length });
          return;
        }

        const payloadRoomId = String(result?.data?.roomId || result?.data?.room_id || requestRoomId).trim();
        const activeRoomId = String(shell.activeRoomId || '').trim();
        if (payloadRoomId && activeRoomId && payloadRoomId !== activeRoomId) {
          console.info('[GameShell] _loadChatHistory: stale response dropped', {
            requestedRoomId: requestRoomId,
            payloadRoomId,
            activeRoomId,
          });
          return;
        }

        shell._chatHistoryLoaded = true;
        shell._chatHistoryLastLoadedAt.set(requestKey, Date.now());
        console.log('[GameShell] _loadChatHistory: loaded', { lineCount: result.data.messages.length });
        shell.bus.emit('chat:history-loaded', {
          ...result,
          roomId: payloadRoomId || requestRoomId,
          campaignId: Number(campaignId) || null,
          requestToken,
        });
        shell.queueRoomEntryAcknowledgement({
          campaignId: Number(campaignId) || null,
          roomId: payloadRoomId || requestRoomId,
          characterId: charId,
          mapId,
        });
      } catch (_) {
        // Chat history is best-effort; no user-facing error.
      } finally {
        if (shell._chatHistoryInflight.get(requestKey) === request) {
          shell._chatHistoryInflight.delete(requestKey);
        }
      }
    })();

    shell._chatHistoryInflight.set(requestKey, request);
    await request;
  }

  async loadRoomView(options = {}) {
    const shell = this.shell;
    const campaignId = shell.launchContext?.campaign_id;
    const roomId = shell.activeRoomId;
    if (!campaignId || !roomId) {
      console.warn('[GameShell] _loadRoomView: missing campaignId or roomId', { campaignId, roomId });
      return;
    }

    const force = Boolean(options.force);
    const preserveExisting = Boolean(options.preserveExisting);
    const viewKey = `${campaignId}:${roomId}`;
    const visualRoom = shell.mapVisualState?.topology?.rooms?.[roomId] ?? {};
    const payloadRoomBase = { ...visualRoom, room_id: visualRoom?.room_id || roomId };

    if (!force && shell._roomViewLastKey === viewKey && shell._roomViewHasContent) {
      console.log('[GameShell] _loadRoomView: skipped (cached)', { viewKey });
      return;
    }
    if (shell._roomViewInflight.has(viewKey)) {
      console.log('[GameShell] _loadRoomView: skipped (inflight)', { viewKey });
      return;
    }

    shell._roomViewLastKey = viewKey;
    const token = ++shell._roomViewRequestToken;
    const backendRequestId = `room-view-${viewKey}-${token}`;
    console.log('[GameShell] _loadRoomView', { campaignId, roomId, force, preserveExisting });

    if (!preserveExisting || !shell._roomViewHasContent) {
      shell.bus.emit('room:view-loaded', {
        room: payloadRoomBase,
        viewState: { statusLabel: 'Generating', placeholderText: 'Loading room scene...', entries: [] },
      });
    }

    const request = (async () => {
      shell.bus.emit('game:backend-request-start', {
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
      if (token !== shell._roomViewRequestToken) return;
      if (!resp.ok) {
        shell.bus.emit('game:server-unavailable', { message: `Room view unavailable (${resp.status})` });
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

      const apiRoom = shell._isPlainObject(result.data.room) ? result.data.room : {};
      const payloadRoom = shell._mergeRoomMetadata(visualRoom, apiRoom, roomId);

      const dataStatus = String(result.data.status || '').toLowerCase();
      shell._roomViewHasContent = entries.length > 0;

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

      shell.bus.emit('room:view-loaded', { room: payloadRoom, viewState: { statusLabel, placeholderText, entries } });
      if (entries.length === 0 && dataStatus === 'pending') {
        this.scheduleRoomViewRetry(roomId, viewKey);
      } else {
        this.clearRoomViewRetry();
      }
    })();

    shell._roomViewInflight.set(viewKey, request);
    try {
      await request;
    } catch (err) {
      if (token !== shell._roomViewRequestToken) return;
      shell.bus?.emit('room:view-loaded', {
        room: payloadRoomBase,
        viewState: { statusLabel: 'Unavailable', placeholderText: err?.message || 'Room view generation failed.', entries: [] },
      });
    } finally {
      shell._roomViewInflight.delete(viewKey);
      shell.bus?.emit('game:backend-request-end', { requestId: backendRequestId, source: 'room-view' });
    }
  }

  scheduleRoomViewRetry(roomId, viewKey) {
    const shell = this.shell;
    this.clearRoomViewRetry();
    shell._roomViewRetryTimer = window.setTimeout(() => {
      shell._roomViewRetryTimer = null;
      if (shell._roomViewLastKey !== viewKey) return;
      console.log('[GameShell] _loadRoomView: retrying pending', { viewKey });
      shell._loadRoomView({ force: true, preserveExisting: true });
    }, 5000);
  }

  clearRoomViewRetry() {
    const shell = this.shell;
    if (shell._roomViewRetryTimer) {
      window.clearTimeout(shell._roomViewRetryTimer);
      shell._roomViewRetryTimer = null;
    }
  }

  async loadMerchantStock() {
    const shell = this.shell;
    if (shell._merchantStockLoading) return;
    shell._merchantStockLoading = true;
    try {
      await this.loadMerchantStockImpl();
    } finally {
      shell._merchantStockLoading = false;
    }
  }

  async loadMerchantStockImpl() {
    const shell = this.shell;
    const campaignId = shell.launchContext?.campaign_id;
    const roomId = shell.activeRoomId;
    const charId = shell.launchCharacter?.id ?? shell.launchContext?.character_id;
    if (!campaignId || !roomId) return;

    const merchants = shell._currentOccupants.filter((o) => o?.presentation?.is_merchant);
    console.log('[GameShell] _loadMerchantStock start', {
      merchantCount: merchants.length,
      merchantRefs: merchants.map((m) => m?.occupant_id ?? m?.content_id ?? null),
      activeTab: shell.activeGameShellTab,
    });
    if (!merchants.length) return;

    const updatedOccupants = [...shell._currentOccupants];
    await Promise.all(merchants.map(async (merchant) => {
      const merchantRef = merchant.occupant_id ?? merchant.content_id;
      if (!merchantRef) return;
      const token = (shell._merchantRequestTokens.get(merchantRef) ?? 0) + 1;
      shell._merchantRequestTokens.set(merchantRef, token);

      try {
        const params = charId ? `?character_id=${encodeURIComponent(charId)}` : '';
        const url = `/api/campaign/${encodeURIComponent(campaignId)}/room/${encodeURIComponent(roomId)}/merchant/${encodeURIComponent(merchantRef)}${params}`;
        const resp = await fetch(url, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (shell._merchantRequestTokens.get(merchantRef) !== token) return;
        if (!resp.ok) return;
        const result = await resp.json().catch(() => ({}));
        if (!result?.success || !result?.context) return;

        const ctx = result.context;
        const idx = updatedOccupants.findIndex((o) => o.occupant_id === merchant.occupant_id);
        if (idx === -1) return;
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
            stock: Array.isArray(ctx.stock) ? ctx.stock : [],
            player_currency: ctx.player?.currency ?? ctx.player_currency ?? {},
          },
        };
      } catch (_) {
        // Per-merchant failure is silent.
      }
    }));

    shell._currentOccupants = updatedOccupants;
    const room = shell.mapVisualState?.topology?.rooms?.[roomId];
    console.log('[GameShell] _loadMerchantStock complete', {
      activeTab: shell.activeGameShellTab,
      stockedMerchantCount: updatedOccupants.filter((o) => o?.presentation?.stock).length,
    });
    shell.bus.emit('room:occupants-decoration-changed', {
      roomId,
      roomName: room?.name ?? roomId,
      source: 'merchant-stock',
    });
    shell.bus.emit('merchant:stock-loaded', {
      roomId,
      roomName: room?.name ?? roomId,
      merchantCount: updatedOccupants.filter((entry) => entry?.presentation?.is_merchant).length,
      occupants: updatedOccupants,
    });
  }
}
