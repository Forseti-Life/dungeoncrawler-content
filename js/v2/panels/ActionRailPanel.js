/**
 * @file panels/ActionRailPanel.js
 *
 * Renders the 3-action economy rail for the active player's turn.
 *
 * Sub-panels (rendered on demand):
 *   - Attack          — target selection + attack roll
 *   - Spell           — spell selection by rank, cast
 *   - Skill           — skill check actions
 *   - Interact        — object/door/NPC interactions
 *   - Navigate        — room connection navigation
 *   - Consumable      — use consumable items
 *
 * Subscribes to bus events:
 *   combat:turn-changed    — rebuild rail for new active entity
 *   combat:state-changed   — show/hide rail based on combat state
 *   entity:selected        — update context for selected entity
 *
 * Fires bus events:
 *   user:action-selected   — { actionKey, cost, context }
 *   user:attack            — { targetEntityId }
 *   user:cast-spell        — { spellId, rank, targetEntityId }
 *   user:interact          — { targetEntityId, interactionType }
 *   user:navigate-to-room  — { roomId }
 *
 * Phase 5 implementation.
 */

export class ActionRailPanel {
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
