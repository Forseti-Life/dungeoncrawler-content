#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const vm = require('vm');

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

class FakeClassList {
  constructor(initial = []) {
    this.classes = new Set(initial);
  }

  toggle(className, force) {
    if (force) {
      this.classes.add(className);
      return;
    }
    this.classes.delete(className);
  }

  contains(className) {
    return this.classes.has(className);
  }
}

function createShell(activeTab = 'map') {
  const tabs = ['map', 'chat', 'view', 'character'].map((tabId) => ({
    dataset: { gameTab: tabId },
    classList: new FakeClassList(tabId === activeTab ? ['game-shell__tab--active'] : []),
    attributes: {},
    setAttribute(name, value) {
      this.attributes[name] = value;
    },
  }));

  const panels = ['map', 'chat', 'view', 'character'].map((tabId) => ({
    id: `game-panel-${tabId}`,
    hidden: tabId !== activeTab,
    classList: new FakeClassList(tabId === activeTab ? ['game-shell__panel--active'] : []),
  }));

  return {
    dataset: { gameShellDefault: 'map' },
    querySelectorAll(selector) {
      if (selector === '[data-game-tab]') {
        return tabs;
      }
      if (selector === '.game-shell__panel') {
        return panels;
      }
      return [];
    },
    tabs,
    panels,
  };
}

const source = fs.readFileSync(
  path.join(__dirname, '..', 'js', 'GameShellTabs.js'),
  'utf8',
);

global.Drupal = { behaviors: {} };
global.once = () => [];

vm.runInThisContext(
  source.replace(
    '})(Drupal, once);',
    'global.__gameShellTabsTest = { listGameShellTabs, normalizeGameShellTab, resolveLegacyGameShellTab, resolveInitialGameShellTab, activateGameShellTab }; })(Drupal, once);',
  ),
);

const runtime = global.__gameShellTabsTest;

const storageWithInvalidState = {
  getItem(key) {
    return key === 'dc_game_shell_surface' ? 'invalid-tab' : null;
  },
  setItem() {},
};
assert(
  runtime.resolveInitialGameShellTab(createShell(), storageWithInvalidState) === 'map',
  'Invalid persisted shell state should fall back to map.',
);

const storageWithLegacyState = {
  getItem(key) {
    if (key === 'dc_game_shell_surface') {
      return null;
    }
    if (key === 'dc_sidebar_tab') {
      return 'inventory';
    }
    return null;
  },
  setItem() {},
};
assert(
  runtime.resolveInitialGameShellTab(createShell(), storageWithLegacyState) === 'character',
  'Legacy sidebar state should migrate to the character surface.',
);

const writes = [];
const dispatchedEvents = [];
global.window = {
  dispatchEvent(event) {
    dispatchedEvents.push(event);
  },
};
global.CustomEvent = class CustomEvent {
  constructor(type, options = {}) {
    this.type = type;
    this.detail = options.detail || {};
  }
};
global.Event = class Event {
  constructor(type) {
    this.type = type;
  }
};

const shell = createShell();
runtime.activateGameShellTab(shell, 'chat', {
  storage: {
    setItem(key, value) {
      writes.push([key, value]);
    },
  },
});

const activeTabs = shell.tabs.filter((tab) => tab.classList.contains('game-shell__tab--active'));
const activePanels = shell.panels.filter((panel) => panel.classList.contains('game-shell__panel--active') && !panel.hidden);

assert(activeTabs.length === 1 && activeTabs[0].dataset.gameTab === 'chat', 'Chat tab should become active.');
assert(activePanels.length === 1 && activePanels[0].id === 'game-panel-chat', 'Chat panel should become visible.');
assert(shell.tabs.every((tab) => tab.attributes.tabindex === '0'), 'All shell tabs should remain keyboard reachable.');
assert(writes.length === 1 && writes[0][0] === 'dc_game_shell_surface' && writes[0][1] === 'chat', 'Active tab should persist under the canonical key.');
assert(
  dispatchedEvents.some((event) => event.type === 'dungeoncrawler:game-shell-tab-changed' && event.detail.tabId === 'chat'),
  'Tab activation should emit the shell change event.',
);

console.log('game_shell_tabs_test: ok');
