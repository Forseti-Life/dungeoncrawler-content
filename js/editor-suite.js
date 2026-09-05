/**
 * @file
 * Drupal behavior bootstrap for the editor suite hub.
 */

import { EditorSuiteShell } from './v2/editor/EditorSuiteShell.js';

(function (Drupal, drupalSettings, once) {
  'use strict';

  let activeShell = null;

  Drupal.behaviors.editorSuite = {
    attach(context, settings) {
      const [wrapper] = once('editor-suite', '[data-editor-suite]', context);
      if (!wrapper) return;

      const shellSettings = settings?.dungeoncrawlerContent?.editorSuite ?? {};
      activeShell = new EditorSuiteShell(wrapper, shellSettings);
      window.DungeonCrawlerEditorSuite = activeShell;
      activeShell.init().catch((error) => {
        console.error('[EditorSuite] initialization failed', error);
        throw error;
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      activeShell?.destroy();
      activeShell = null;
      window.DungeonCrawlerEditorSuite = null;
    },
  };
})(Drupal, drupalSettings, once);
