/**
 * @file GameShell.js
 *
 * Top-level orchestrator for hexmap-v2.
 *
 * Responsibilities:
 *   - Read Drupal settings and build launch context
 *   - Instantiate GameEventBus, canvas modules, systems, and panels
 *   - Wire ECS (EntityManager, systems) to bus events
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

// Phase 1 implementation.
export class GameShell {
  /**
   * @param {object} settings - Drupal.settings subset
   * @param {HTMLElement} container - Root DOM container
   */
  constructor(settings, container) {
    this.settings = settings;
    this.container = container;

    // Populated in Phase 1
    this.bus = null;
    this.canvas = null;
    this.systems = {};
    this.panels = {};
  }

  /**
   * Initialize all sub-modules. Called from Drupal.behaviors.hexMapV2.attach.
   */
  init() {
    // Phase 1: implement
  }

  /**
   * Tear down all sub-modules. Called from Drupal.behaviors.hexMapV2.detach.
   */
  destroy() {
    Object.values(this.panels).forEach((p) => p?.destroy?.());
    Object.values(this.systems).forEach((s) => s?.destroy?.());
    this.canvas?.destroy?.();
    this.bus?.destroy?.();
  }
}
