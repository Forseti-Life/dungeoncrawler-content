/**
 * @file room-editor.js
 *
 * Drupal behavior entry point for the canonical Room Editor.
 *
 * This file is the sole JS asset declared in the `room-editor` Drupal
 * library. It reads drupalSettings.dungeoncrawlerContent.roomEditor and
 * instantiates RoomEditorShell, which imports GameEventBus and HexCanvas
 * via ES module syntax.
 *
 * Lifecycle:
 *   attach  — instantiate RoomEditorShell, call shell.init()
 *   detach  — call shell.destroy() to unsubscribe listeners and destroy PIXI
 */

import { RoomEditorShell } from './v2/editor/RoomEditorShell.js';

(function (Drupal, drupalSettings, once) {
  'use strict';

  /** @type {RoomEditorShell|null} Active shell instance */
  let activeShell = null;

  Drupal.behaviors.roomEditor = {
    attach(context, settings) {
      const [wrapper] = once('room-editor', '[data-room-editor]', context);
      if (!wrapper) return;

      const shellSettings = settings?.dungeoncrawlerContent?.roomEditor ?? {};
      activeShell = new RoomEditorShell(wrapper, shellSettings);
      activeShell.init();
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      activeShell?.destroy();
      activeShell = null;
    },
  };
})(Drupal, drupalSettings, once);
