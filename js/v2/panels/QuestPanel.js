/**
 * @file panels/QuestPanel.js
 *
 * Renders the quest journal: active quests, objective tree,
 * expansion state, and toast notifications for quest progress.
 *
 * Display only — game-state quest logic lives in QuestSystem.
 *
 * Subscribes to bus events:
 *   quest:progress-updated  — re-render affected quest objectives
 *   quest:completed         — show completion toast + update journal
 *   quest:item-collected    — show collection toast
 *   game:init               — initial render of quest journal
 *
 * Fires no bus events (display only).
 *
 * Phase 8 implementation.
 * @see systems/QuestSystem
 */

export class QuestPanel {
  /**
   * @param {HTMLElement} container
   * @param {import('../GameEventBus').GameEventBus} bus
   */
  constructor(container, bus) {
    this.container = container;
    this.bus = bus;
    this._unsubs = [];
  }

  init() {}

  destroy() {
    this._unsubs.forEach((fn) => fn());
  }
}
