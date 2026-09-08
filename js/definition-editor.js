/**
 * @file
 * Drupal behavior bootstrap for canonical-library GM panels.
 */

import { DefinitionEditorShell } from './v2/editor/DefinitionEditorShell.js';

(function (Drupal, drupalSettings, once) {
  'use strict';

  let activeShell = null;

  Drupal.behaviors.definitionEditorGm = {
    attach(context, settings) {
      const [wrapper] = once('definition-editor-gm', '[data-definition-editor]', context);
      if (!wrapper) return;
      const shellSettings = settings?.dungeoncrawlerContent?.definitionEditor ?? {};
      activeShell = new DefinitionEditorShell(wrapper, shellSettings);
      window.DungeonCrawlerDefinitionEditor = activeShell;
      activeShell.init().catch((error) => {
        console.error('[DefinitionEditor] initialization failed', error);
        throw error;
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      activeShell?.destroy();
      activeShell = null;
      window.DungeonCrawlerDefinitionEditor = null;
    },
  };
})(Drupal, drupalSettings, once);
