/**
 * @file dungeon-editor.js
 *
 * Drupal behavior entry point for the canonical Dungeon Editor.
 *
 * Sole JS asset of the `dungeon-editor` library. Reads
 * drupalSettings.dungeoncrawlerContent.dungeonEditor and instantiates
 * DungeonEditorShell, which imports GameEventBus, HexCanvas, and the shared
 * placement transform via ES modules. It does not fork the renderer.
 */

import { DungeonEditorShell } from './v2/editor/DungeonEditorShell.js';

(function (Drupal, drupalSettings, once) {
  'use strict';

  let activeShell = null;

  Drupal.behaviors.dungeonEditor = {
    attach(context, settings) {
      const [wrapper] = once('dungeon-editor', '[data-dungeon-editor]', context);
      if (!wrapper) return;

      const shellSettings = settings?.dungeoncrawlerContent?.dungeonEditor ?? {};
      activeShell = new DungeonEditorShell(wrapper, shellSettings);
      window.DungeonCrawlerDungeonEditor = activeShell;
      try {
        activeShell.init();
      } catch (error) {
        console.error('[DungeonEditor] initialization failed', error);
        throw error;
      }
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      activeShell?.destroy();
      activeShell = null;
      window.DungeonCrawlerDungeonEditor = null;
    },
  };
})(Drupal, drupalSettings, once);
