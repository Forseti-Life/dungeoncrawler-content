# Implementation Notes: dc-ui-map-first-player-shell

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/templates/hexmap-demo.html.twig`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- Existing map shell CSS/library assets in `dungeoncrawler_content`

## Design notes

- Preserve current gameplay APIs and DOM hooks where possible; this is a shell refactor, not a rules rewrite
- Separate `player-visible shell` concerns from `debug/admin diagnostics` concerns so both can coexist cleanly
- Keep the existing board/header/action/chat systems functional during refactor; move or gate panels instead of deleting capabilities
- Use deterministic CSS/layout state for responsiveness; do not couple shell layout to combat/session logic

## Architectural gotchas

- `hexmap-demo.html.twig` currently owns layout-ratio persistence and sidebar-tab activation in inline script. If the shell is moved without replacing that behavior in the same change, saved layout and panel restore will silently break.
- Inventory refresh is currently triggered from the inline sidebar-tab handler (`if (panel === 'inventory') syncInventory();`). Any drawer or shell rewrite must preserve that refresh seam or move it explicitly into the new controller.
- Existing `/hexmap` functional coverage asserts the presence of several info/debug selectors. Prefer gating/collapsing those surfaces over deleting DOM hooks outright until QA expectations are updated in the same slice.
- `hexmap.js` is a large mixed-responsibility runtime. Shell work should isolate presentation seams first rather than widening the blast radius into combat/chat/server-sync logic.

## Sequencing note

- Do the shell boundary first. Once the player shell owns board-vs-support-panel layout cleanly, later rail/drawer/token work can land with less template churn.
