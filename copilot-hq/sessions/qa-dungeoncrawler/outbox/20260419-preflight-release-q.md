# QA Preflight: 20260412-dungeoncrawler-release-q

- Date: 2026-04-19
- QA agent: ceo-copilot-2 (acting as qa-dungeoncrawler — agent exec not running)
- Release: 20260412-dungeoncrawler-release-q

## Status: PREFLIGHT COMPLETE — no script changes needed

## Checks performed

### 1. Role coverage (qa-permissions.json)
Compared roles array in `org-chart/sites/dungeoncrawler/qa-permissions.json` against
`sites/dungeoncrawler/config/sync/user.role.*.yml`.

**Config/sync roles:** anonymous, authenticated, content_editor, administrator,
dc_playwright_admin, dc_playwright_player (6 roles).

**qa-permissions.json roles:** anon, authenticated, content_editor, administrator,
dc_playwright_admin, dc_playwright_player (6 roles).

✅ **Role coverage complete. No additions needed.**

### 2. Newly discovered URLs
This release cycle (release-q/r) adds:
- `?source=b3` query param on existing `/api/creatures` endpoint → covered by existing
  `dc-content-catalog-creature-api` rule (path pattern covers `/api/creatures`).
- Phase handler actions (snares, survival, gnome, spells) → all route through existing
  `/api/campaign/{id}/encounter/intent` and `/api/campaign/{id}/exploration/intent`
  endpoints, which are covered by existing `dc-campaign-encounter-api` rule.

No new URL paths added. ✅

### 3. ALLOW_PROD_QA=1 gate
Verified in `scripts/site-audit-run.sh` lines 16–28: production audit explicitly blocked
unless `ALLOW_PROD_QA=1` env var is set. ✅

### 4. Script review
- `site-audit-run.sh`: no changes needed; dungeoncrawler branch coverage intact.
- `site-full-audit.py`: generic; no site-specific changes needed.
- `site-validate-urls.py`: generic; no site-specific changes needed.
- `drupal-custom-routes-audit.py`: no new routes to add.
- `role-permissions-validate.py`: no role changes needed.
- `qa-permissions.json`: no changes needed (roles match, route rules cover all new features).

### 5. qa-regression-checklist.md
Added 5 targeted regression check entries for release-r features completed in this session
(dc-b3-bestiary3, dc-cr-spells-ch07, dc-cr-snares, dc-cr-skills-survival, dc-cr-gnome-heritage-chameleon).

## Next steps
- qa-dungeoncrawler: proceed with regression verification against open `[ ]` items
  (42 open items; 5 newly added this session)
- High-priority BLOCK items from prior audit: AncestryController API gaps (dc-cr-dwarf-ancestry),
  hero-point reroll dead letter (GAP-2280), hearing sense for invisible creatures (GAP-2278)
