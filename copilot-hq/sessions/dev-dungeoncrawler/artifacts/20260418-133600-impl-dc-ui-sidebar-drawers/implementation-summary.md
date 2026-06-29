# Artifact: dc-ui-sidebar-drawers Implementation

## Commit
`4ce401ed4`

## Files changed

### `templates/hexmap-demo.html.twig`
- Added `#sidebar-drawer-toggle` button to `hexmap-header__actions` — always visible, toggles sidebar
- Added `#chat-dock-toggle` button inside `.hexmap-chat__header` — collapses/expands chat
- Added Inspector tab (`data-panel="inspector"`) to sidebar tab bar — 4th tab
- Added `#sidebar-panel-inspector` div with placeholder + `#entity-info-panel` inside it
- Removed `#entity-info-panel` from `hexmap-info` section (placed reference comment)
- Added `#sidebar-backdrop` div for mobile overlay tap-to-close
- Added JS IIFE: Drawer toggle (localStorage `dc_drawer_open`; desktop uses `.game-layout--drawer-collapsed`, mobile uses `.game-layout--drawer-open` + backdrop)
- Added JS IIFE: Chat dock toggle (localStorage `dc_chat_collapsed`; adds `.hexmap-chat--collapsed`)
- Added JS IIFE: Inspector MutationObserver — watches `#entity-info-panel` class for `dc-is-hidden` removal; auto-activates Inspector tab on entity selection; syncs placeholder visibility

### `css/hexmap.css`
- `.game-layout--drawer-collapsed` — `grid-template-columns: 100%`, sidebar `display: none`
- `.btn--drawer-toggle` — styling for sidebar toggle chevron button
- `.btn--chat-dock-toggle` — transparent icon button for chat collapse
- `.hexmap-chat--collapsed` — hides session-tabs, channels, channel-indicator, log, input form
- `.inspector-placeholder` — muted hint text for empty inspector tab
- `.sidebar-backdrop` — fixed full-screen overlay for mobile tap-to-close
- `@media (max-width: 768px)` — sidebar becomes `position: fixed; transform: translateX(100%)`; `.game-layout--drawer-open` slides it in; grid collapses to 1 column

## AC coverage
- AC-001: single-primary drawer model — existing tab-switching IIFE already enforces one panel visible at a time; drawer open/close added on top
- AC-002: existing panel functionality preserved — character/inventory/quests panels unchanged; entity inspection now in sidebar-panel-inspector, UIManager targets same IDs
- AC-003: chat docked — chat toggle collapses to header-only; default behavior restored on expand
- AC-004: responsive — `@media (max-width: 768px)` slides sidebar in as overlay; backdrop dismisses it; last-open tab persisted via existing `dc_sidebar_tab` localStorage key; drawer state persisted via `dc_drawer_open`

## Security
- No authorization surface changes
- CSRF endpoints unchanged
- No new PII introduced; chat visibility rules unaffected
