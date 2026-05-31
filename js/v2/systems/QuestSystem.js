/**
 * @file systems/QuestSystem.js
 *
 * Quest game-state management.
 *
 * Responsibilities:
 *   - Quest data initialization from dungeon payload
 *   - Quest progress tracking and objective state
 *   - Collectible entity resolution and removal
 *   - Quest completion detection
 *
 * NOTE: Display (journal rendering, toast notifications) belongs to
 * QuestPanel, not here. This system owns game-state; QuestPanel owns DOM.
 *
 * Fires bus events:
 *   quest:progress-updated  — { questId, objectiveId, delta }
 *   quest:completed         — { questId, title }
 *   quest:item-collected    — { entityId, questId }
 *
 * Responds to bus events:
 *   room:changed            — re-scan for active quest triggers
 *   entity:interacted       — check for collectible quest items
 *
 * Phase 8 implementation.
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
    /** @type {Map<string, object>} questId → quest state */
    this._quests = new Map();
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
    this._quests.clear();
  }
}
