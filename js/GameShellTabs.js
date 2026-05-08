/**
 * @file
 * Tabbed shell controller for the simplified hexmap UI.
 */

/* global Drupal, once */

(function (Drupal, once) {
  'use strict';

  function activateGameShellTab(shell, tabId) {
    const tabs = shell.querySelectorAll('[data-game-tab]');
    const panels = shell.querySelectorAll('.game-shell__panel');

    tabs.forEach((tab) => {
      const active = tab.dataset.gameTab === tabId;
      tab.classList.toggle('game-shell__tab--active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.setAttribute('tabindex', active ? '0' : '-1');
    });

    panels.forEach((panel) => {
      const active = panel.id === `game-panel-${tabId}`;
      panel.classList.toggle('game-shell__panel--active', active);
      panel.hidden = !active;
    });

    window.dispatchEvent(new Event('resize'));
  }

  function initGameShellTabs(shell) {
    const tabs = shell.querySelectorAll('[data-game-tab]');
    const panels = shell.querySelectorAll('.game-shell__panel');
    if (!tabs.length || !panels.length) {
      return;
    }

    const initialTab = shell.dataset.gameShellDefault || tabs[0]?.dataset.gameTab || 'map';
    activateGameShellTab(shell, initialTab);

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        activateGameShellTab(shell, tab.dataset.gameTab);
      });
    });
  }

  Drupal.behaviors.dungeoncrawlerGameShellTabs = {
    attach(context) {
      once('dungeoncrawlerGameShellTabs', '[data-game-shell]', context).forEach((shell) => {
        initGameShellTabs(shell);
      });
    },
  };
})(Drupal, once);
