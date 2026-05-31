/**
 * @file
 * Tabbed shell controller for the simplified hexmap UI.
 */

/* global Drupal, once */

(function (Drupal, once) {
  'use strict';

  function listGameShellTabs(shell) {
    return Array.from(shell.querySelectorAll('[data-game-tab]'))
      .map((tab) => (typeof tab.dataset.gameTab === 'string' ? tab.dataset.gameTab.trim() : ''))
      .filter((tabId) => tabId !== '');
  }

  function normalizeGameShellTab(shell, tabId, fallbackTab = 'map') {
    const validTabs = listGameShellTabs(shell);
    if (!validTabs.length) {
      return '';
    }

    const requestedTab = typeof tabId === 'string' ? tabId.trim() : '';
    if (requestedTab !== '' && validTabs.includes(requestedTab)) {
      return requestedTab;
    }

    const normalizedFallback = typeof fallbackTab === 'string' ? fallbackTab.trim() : '';
    if (normalizedFallback !== '' && validTabs.includes(normalizedFallback)) {
      return normalizedFallback;
    }

    return validTabs[0];
  }

  function resolveLegacyGameShellTab(storage, legacyKey = 'dc_sidebar_tab') {
    if (!storage || typeof storage.getItem !== 'function') {
      return '';
    }

    const legacyTab = storage.getItem(legacyKey);
    return ['character', 'spells-feats', 'inventory', 'quests'].includes(legacyTab) ? 'character' : '';
  }

  function resolveInitialGameShellTab(shell, storage = null, storageKey = 'dc_game_shell_surface') {
    const defaultTab = normalizeGameShellTab(shell, shell.dataset.gameShellDefault || 'map', 'map');
    if (!defaultTab) {
      return '';
    }

    if (storage && typeof storage.getItem === 'function') {
      const storedTab = storage.getItem(storageKey);
      if (storedTab !== null) {
        return normalizeGameShellTab(shell, storedTab, defaultTab);
      }
    }

    const migratedTab = resolveLegacyGameShellTab(storage);
    if (migratedTab) {
      return normalizeGameShellTab(shell, migratedTab, defaultTab);
    }

    return defaultTab;
  }

  function persistGameShellTab(storage, tabId, storageKey = 'dc_game_shell_surface') {
    if (!storage || typeof storage.setItem !== 'function' || typeof tabId !== 'string' || tabId.trim() === '') {
      return;
    }

    storage.setItem(storageKey, tabId);
  }

  function activateGameShellTab(shell, tabId, options = {}) {
    const storage = options.storage || null;
    const storageKey = options.storageKey || 'dc_game_shell_surface';
    const tabs = shell.querySelectorAll('[data-game-tab]');
    const panels = shell.querySelectorAll('.game-shell__panel');
    const activeTabId = normalizeGameShellTab(shell, tabId, shell.dataset.gameShellDefault || 'map');
    if (!activeTabId) {
      return;
    }

    tabs.forEach((tab) => {
      const active = tab.dataset.gameTab === activeTabId;
      tab.classList.toggle('game-shell__tab--active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.setAttribute('tabindex', '0');
    });

    panels.forEach((panel) => {
      const active = panel.id === `game-panel-${activeTabId}`;
      panel.classList.toggle('game-shell__panel--active', active);
      panel.hidden = !active;
    });

    shell.dataset.gameShellActive = activeTabId;
    persistGameShellTab(storage, activeTabId, storageKey);

    window.dispatchEvent(new CustomEvent('dungeoncrawler:game-shell-tab-changed', {
      detail: { tabId: activeTabId },
    }));
    window.dispatchEvent(new Event('resize'));
  }

  function initGameShellTabs(shell) {
    const tabs = shell.querySelectorAll('[data-game-tab]');
    const panels = shell.querySelectorAll('.game-shell__panel');
    console.log('[GameShellTabs] initGameShellTabs', { tabCount: tabs.length, panelCount: panels.length });
    if (!tabs.length || !panels.length) {
      console.warn('[GameShellTabs] no tabs or panels found — aborting');
      return;
    }

    const storage = typeof window !== 'undefined' && window.localStorage ? window.localStorage : null;
    const initialTab = resolveInitialGameShellTab(shell, storage);
    activateGameShellTab(shell, initialTab, { storage });

    shell.addEventListener('dungeoncrawler:activate-tab', (event) => {
      const requestedTab = event?.detail?.tabId;
      if (typeof requestedTab === 'string' && requestedTab.trim() !== '') {
        activateGameShellTab(shell, requestedTab, { storage });
      }
    });

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        console.log('[GameShellTabs] tab clicked', { tabId: tab.dataset.gameTab });
        activateGameShellTab(shell, tab.dataset.gameTab, { storage });
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
