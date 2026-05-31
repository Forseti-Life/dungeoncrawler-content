# Implementation Notes: dc-ui-sidebar-drawers

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/templates/hexmap-demo.html.twig`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`

## Design notes

- Preserve current sidebar data flows; reorganize shell behavior first
- Distinguish the main right drawer from chat docking so both remain useful
- Inventory sync-on-open behavior should survive the drawer conversion
- Use persisted UI state cautiously so mobile/desktop layouts do not fight each other

## Architectural gotchas

- Sidebar behavior is currently split between Twig inline script and `hexmap.js`. Drawer work should consolidate ownership instead of adding a third layer of UI state.
- Chat is not a single system today: room chat uses the legacy `loadChatHistory()` flow while narrative/party/gm-private/system-log use `ChatSessionApi`. Moving chat into a dock without acknowledging that split will produce inconsistent behavior.
- The right-side panels and chat region currently assume different layout responsibilities. A drawer refactor must define which surfaces are mutually exclusive and which remain concurrently visible.
- Local-storage restore keys already exist for layout ratio and sidebar tab. Preserve or migrate them deliberately to avoid user-facing state regressions.

## Sequencing note

- Convert the shell boundary first, then unify panel ownership, then move the panels into drawers. Chat docking should be treated as an adapter problem as much as a layout problem.
