/**
 * @file systems/QuestSystem.js
 *
 * Quest game-state relay.
 *
 * THIN ADAPTER: reads quest state from server-provided dungeon payload
 * (via game:init), relays quest events from ECS to the bus, and emits
 * bus events that QuestPanel renders.
 *
 * No quest logic runs client-side — the server determines completion,
 * objective progress, and item collection outcomes.
 *
 * Fires bus events:
 *   quest:progress-updated  — { questSummary } canonical summary snapshot
 *   quest:completed         — { quest, questSummary, message } quest marked complete
 *   quest:item-collected    — { itemName } collectible entity removed
 *
 * Responds to bus events:
 *   game:init           — { dungeonData } seed initial quest list
 *   room:changed        — re-broadcast active quests for the room
 *   entity:interacted   — { entity }  relay item-collection events
 */

export class QuestSystem {
  /**
   * @param {import('../GameShell').GameShell} shell
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(shell, bus) {
    this.shell = shell;
    this.bus = bus;
    this._unsubs = [];
    /** @type {Map<string, object>} questId → quest (server-provided shape) */
    this._quests = new Map();
  }

  init() {
    this._subscribe();
  }

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._unsubs = [];
    this._quests.clear();
  }

  // ---------------------------------------------------------------------------
  // Bus
  // ---------------------------------------------------------------------------

  _subscribe() {
    this._unsubs.push(
      this.bus.on('game:init',         (data) => this._onGameInit(data)),
      this.bus.on('room:changed',      ()     => this._broadcastCurrentQuests()),
      this.bus.on('entity:interacted', (data) => this._onEntityInteracted(data)),
    );
  }

  _onGameInit({ dungeonData } = {}) {
    const quests = dungeonData?.quests ?? this.shell.dungeonData?.quests ?? [];
    this._quests.clear();
    quests.forEach((q) => {
      const questKey = this._resolveQuestIdentityKey(q);
      if (!questKey) {
        return;
      }
      this._quests.set(questKey, q);
    });
    // Seed QuestPanel with initial quest list
    this.bus.emit('game:init-quests', { quests: [...this._quests.values()] });
    this._emitQuestProgressUpdated();
  }

  _broadcastCurrentQuests() {
    // Re-emit current quest summary so QuestPanel can refresh after room transition.
    this._emitQuestProgressUpdated();
  }

  _onEntityInteracted({ entity } = {}) {
    if (!entity) return;
    // Check if this entity is a collectible quest item (server-flagged)
    const identity = entity.getComponent?.('IdentityComponent');
    const isCollectible = identity?.tags?.includes?.('quest-collectible') ?? false;
    if (!isCollectible) return;

    const itemName = identity?.displayName ?? 'item';
    this.bus.emit('quest:item-collected', { itemName, entityId: entity.id });
  }

  /**
   * Update quest state from server payload (called by HexmapStateSync or similar).
   * @param {object} quest
   */
  updateQuest(quest) {
    const questKey = this._resolveQuestIdentityKey(quest);
    if (!questKey) return;
    const existing = this._quests.get(questKey);
    this._quests.set(questKey, quest);

    if (quest.status === 'completed' && existing?.status !== 'completed') {
      this.bus.emit('quest:completed', {
        quest,
        message: `Quest completed: ${quest?.quest_name || quest?.title || quest.quest_id}`,
        questSummary: this._buildQuestSummarySnapshot(quest),
      });
    } else {
      this._emitQuestProgressUpdated(quest);
    }
  }

  _emitQuestProgressUpdated(overrideQuest = null) {
    this.bus.emit('quest:progress-updated', {
      questSummary: this._buildQuestSummarySnapshot(overrideQuest),
    });
  }

  _buildQuestSummarySnapshot(overrideQuest = null) {
    const baseSummary = this.shell?.questSummary && typeof this.shell.questSummary === 'object'
      ? this.shell.questSummary
      : null;
    const summary = {
      ...(baseSummary || {}),
      schema_version: String(baseSummary?.schema_version || 'quest-summary-v2'),
      location_id: String(baseSummary?.location_id || this.shell?.resolveActiveRoomId?.() || ''),
      management_tree: Array.isArray(baseSummary?.management_tree) ? baseSummary.management_tree : [],
      active: [],
      offers: [],
      leads: [],
      completed: [],
    };

    const questIndex = new Map();
    ['active', 'offers', 'leads', 'completed'].forEach((bucket) => {
      const bucketQuests = Array.isArray(baseSummary?.[bucket]) ? baseSummary[bucket] : [];
      bucketQuests.forEach((quest) => {
        const questKey = this._resolveQuestIdentityKey(quest);
        if (!quest || !questKey) {
          return;
        }
        questIndex.set(questKey, quest);
      });
    });
    this._quests.forEach((quest, questId) => {
      if (!quest || !questId) {
        return;
      }
      questIndex.set(String(questId), quest);
    });
    const overrideQuestKey = this._resolveQuestIdentityKey(overrideQuest);
    if (overrideQuest && overrideQuestKey) {
      questIndex.set(overrideQuestKey, overrideQuest);
    }

    questIndex.forEach((quest) => {
      const bucket = this._resolveSummaryBucket(quest?.status);
      summary[bucket].push(quest);
    });

    summary.counts = {
      active: summary.active.length,
      offers: summary.offers.length,
      leads: summary.leads.length,
      completed: summary.completed.length,
    };
    return summary;
  }

  _resolveSummaryBucket(status) {
    const normalized = String(status || '').trim().toLowerCase();
    if (normalized === 'completed' || normalized === 'complete') {
      return 'completed';
    }
    if (normalized === 'offered' || normalized === 'offer') {
      return 'offers';
    }
    if (normalized === 'lead' || normalized === 'leads') {
      return 'leads';
    }
    return 'active';
  }

  _resolveQuestIdentityKey(quest) {
    if (!quest || typeof quest !== 'object') {
      return '';
    }
    return String(quest.quest_id || quest.quest_key || quest.id || '').trim();
  }
}
