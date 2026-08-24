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

import { GameShell } from './v2/GameShell.js?v=20260818-v2-authoritative-actor-guard-2';

const HEXMAP_V2_ENTRY_VERSION = '20260818-v2-authoritative-actor-guard-2';

function enforceBackendWaitDock(wrapper) {
  if (!(wrapper instanceof HTMLElement)) {
    return;
  }

  const tracker = wrapper.querySelector('#map-initiative-tracker')
    || wrapper.querySelector('.map-initiative-tracker')
    || wrapper.querySelector('#initiative-tracker')
    || wrapper.querySelector('.initiative-tracker');
  if (!(tracker instanceof HTMLElement)) {
    return;
  }

  let statusHost = tracker.querySelector('.map-initiative-status');
  if (!(statusHost instanceof HTMLElement)) {
    statusHost = document.createElement('div');
    statusHost.className = 'map-initiative-status';
    const list = tracker.querySelector('.initiative-list');
    if (list?.parentNode === tracker) {
      tracker.insertBefore(statusHost, list);
    } else {
      tracker.appendChild(statusHost);
    }
  }

  const candidates = Array.from(wrapper.querySelectorAll('.backend-wait-banner'));
  if (!candidates.length) {
    return;
  }

  const banner = candidates.find((node) => node.closest('.map-initiative-status')) || candidates[0];
  if (!(banner instanceof HTMLElement)) {
    return;
  }

  if (!statusHost.contains(banner)) {
    statusHost.appendChild(banner);
  }
  banner.style.position = 'static';
  banner.style.top = 'auto';
  banner.style.left = 'auto';
  banner.style.transform = 'none';
  banner.style.maxWidth = '100%';
  banner.style.width = '100%';
  banner.style.zIndex = 'auto';
}

(function (Drupal, drupalSettings, once) {
  'use strict';

  /** @type {GameShell|null} Active shell instance */
  let activeShell = null;

  Drupal.behaviors.hexMapV2 = {
    attach(context, settings) {
      const [wrapper] = once('hexmap-v2', '[data-hexmap-v2]', context);
      if (!wrapper) return;

      console.info('[hexmap-v2] entrypoint loaded', {
        version: HEXMAP_V2_ENTRY_VERSION,
      });
      enforceBackendWaitDock(wrapper);

      const shellSettings = {
        ...(settings?.dungeoncrawlerContent ?? {}),
        userId: Number(settings?.user?.uid || settings?.dungeoncrawlerContent?.userId || 0),
      };
      activeShell = new GameShell(wrapper, shellSettings);
      activeShell.init();
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      const wrapper = context?.querySelector?.('[data-hexmap-v2]') || document.querySelector('[data-hexmap-v2]');
      enforceBackendWaitDock(wrapper);
      activeShell?.destroy();
      activeShell = null;
    },
  };
})(Drupal, drupalSettings, once);
