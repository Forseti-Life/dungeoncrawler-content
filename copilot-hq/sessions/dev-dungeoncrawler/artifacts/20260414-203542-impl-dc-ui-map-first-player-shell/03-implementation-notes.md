# dc-ui-map-first-player-shell — Implementation Notes

Commit: `264d8f351`

## What changed

### 1. `HexMapController.php`
- Moved `$is_admin` computation above the `campaign_id > 0` guard so it is always set
- Removed duplicate inline computation
- Passes `'#is_admin' => $is_admin` to the render array

### 2. `dungeoncrawler_content.module` — `hook_theme`
- Added `'is_admin' => FALSE` as a declared variable for `hexmap_demo` theme

### 3. `hexmap-demo.html.twig`
- `.hexmap-container` gets `data-admin="1"` when admin (for future JS use)
- `hexmap-controls` div → adds `hexmap-debug-panel` class (hides config controls from players)
- `dungeon-state` div → adds `hexmap-debug-panel` class
- Launch Context info panel → adds `hexmap-debug-panel` class
- Active Room info panel → adds `hexmap-debug-panel` class
- Header restructured: `hexmap-header__actions` flex group wraps fullscreen + debug toggle buttons
- Debug toggle button rendered only when `is_admin` (server-side conditional)
- New IIFE: debug mode toggle reads/writes `localStorage['dc_debug_mode']`; toggles `hexmap-debug-active` class on `#hexmap-container`

### 4. `hexmap.css`
- `.hexmap-debug-panel { display: none; }` — default hidden
- `.hexmap-debug-active .hexmap-debug-panel { display: block; }` — toggled by JS
- `.hexmap-header__actions` flex styling
- `.btn--debug-toggle` button styles; active state uses `aria-pressed` attribute

## Layout ratio
No change needed: `--sidebar-pct: 20` (80/20 board/sidebar) was already the CSS default in `.game-layout`.

## Play panels (always visible)
Map Info, Hex Details, Selected Hex Contents, Selected Entity, Legend, Instructions, sidebar tabs (character/inventory/quests), board canvas, chat, turn HUD, exploration/encounter actions.

## Inventory sync seam preserved
`if (panel === 'inventory') syncInventory();` in sidebar-tab handler unchanged.

## QA selector note
Debug DOM nodes remain in markup; they are CSS-hidden, not deleted. QA selectors targeting `#dungeon-state`, `#dungeon-state-json`, etc. still pass.
