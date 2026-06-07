/**
 * @file hexmap-v2.js
 *
 * Drupal behavior entry point for hexmap-v2.
 *
 * This file is the sole JS asset declared in the `hexmap-v2` Drupal library.
 * All `js/v2/**` modules are imported here via ES module syntax and are NOT
 * listed individually in dungeoncrawler_content.libraries.yml.
 *
 * Lifecycle:
 *   attach  — instantiate GameShell, call shell.init()
 *   detach  — call shell.destroy() to unsubscribe all listeners and destroy PIXI
 *
 * The hexmap-v2 library is NOT attached to any page until Phase 10.
 * During Phase 10 parallel testing it runs alongside the old hexmap library.
 */

import { GameShell } from './v2/GameShell.js?v=20260607-v2-action-navigate-panel-service-2';

(function (Drupal, drupalSettings, once) {
  'use strict';

  /** @type {GameShell|null} Active shell instance */
  let activeShell = null;

  Drupal.behaviors.hexMapV2 = {
    attach(context, settings) {
      const [wrapper] = once('hexmap-v2', '[data-hexmap-v2]', context);
      if (!wrapper) return;

      const shellSettings = {
        ...(settings?.dungeoncrawlerContent ?? {}),
        userId: Number(settings?.user?.uid || settings?.dungeoncrawlerContent?.userId || 0),
      };
      activeShell = new GameShell(wrapper, shellSettings);
      activeShell.init();
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      activeShell?.destroy();
      activeShell = null;
    },
  };
})(Drupal, drupalSettings, once);
