/**
 * @file panels/MerchantPanel.js
 *
 * Renders the merchant shop for NPCs where presentation.is_merchant === true.
 *
 * Data source: canonical occupant API — is_merchant flag set server-side
 * by MapVisualStateProjector (keyword detection + explicit flags).
 * No client-side merchant heuristics.
 *
 * Subscribes to bus events:
 *   room:occupants-changed  — re-evaluate merchant occupants for current room
 *   user:merchant-selected  — { occupantId } — show that merchant's stock
 *
 * Fires bus events:
 *   user:purchase-requested  — { merchantId, itemId }
 *
 * Phase 6 implementation.
 * @see PortraitPanel — sibling panel, same data source
 */

export class MerchantPanel {
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
