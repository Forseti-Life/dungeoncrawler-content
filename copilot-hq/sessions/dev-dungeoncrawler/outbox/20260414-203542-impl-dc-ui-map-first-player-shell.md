Now I understand the full template. Let me check what variables are available in the controller and the current CSS:

Now let me look at the CSS layout rules:

Now I have everything I need. Let me implement all the changes:

Now add `'#is_admin'` to the render array:

Now add `is_admin` to `hook_theme` variable list:

Now update the Twig template. Add `hexmap-debug-panel` class to debug surfaces and add the debug toggle:

Now let me find the `dungeon-state` and the dev info panels to add the debug class:

Now I need to add the debug toggle JS. Let me find the layout IIFE block at the bottom of the twig:

Now add CSS rules to `hexmap.css` for debug panel gating and toggle button:

Now append the debug panel CSS at the end of the CSS file:

Now verify syntax and check JS:

Now write the artifact and outbox:

- Status: done
- Summary: Implemented `dc-ui-map-first-player-shell` (commit `264d8f351`): gated all debug panels (`hexmap-controls`, `dungeon-state`, Launch Context, Active Room) behind CSS class `.hexmap-debug-panel` (hidden by default, exposed via `.hexmap-debug-active` toggled by admin-only button); added `is_admin` server-side flag to `hook_theme`, controller, and twig; added debug toggle IIFE with `localStorage` persistence; board-first 80/20 ratio was already the CSS default (`--sidebar-pct: 20`), no layout change needed; play panels and inventory sync seam untouched; QA selectors intact (CSS-hide, not DOM deletion).

## Next actions
- Verify with `drush cr` on the dungeoncrawler site after review
- QA can now test: player sees only board + play panels; admin sees toggle button and can expose dev panels

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: Cleans up the player-facing map UI; debug noise was a friction point in playtests. Board-first layout + panel gating directly improves the play experience and reduces support load for new players.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-203542-impl-dc-ui-map-first-player-shell
- Generated: 2026-04-17T04:08:02+00:00
