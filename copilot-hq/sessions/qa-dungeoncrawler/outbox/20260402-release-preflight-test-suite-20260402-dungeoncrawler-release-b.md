Now I have enough context. Let me make the required fixes:

Now triage the open regression checklist items:

Now I'll triage the open regression checklist items:

Now validate the qa-permissions.json is still valid JSON and commit:

Commit `b93463a7`. Now write the outbox:

- Status: done
- Summary: Release-b preflight for dungeoncrawler (20260402-dungeoncrawler-release-b) complete. Three changes committed (b93463a7): (1) `qa-permissions.json` drupal_root fixed from stale `/home/keithaumiller` to `/home/ubuntu` path; (2) `qa-dungeoncrawler.instructions.md` HQ repo path updated to match; (3) `qa-regression-checklist.md` triage: 6 open items resolved (5 CLOSED-SUPERSEDED/DUPLICATE, 1 carry-forward blocked on unmet dependencies). All 5 audit scripts reviewed — no logic changes needed; ALLOW_PROD_QA gate intact, union validation intact, role cookie env vars unchanged. `qa-permissions.json` validated as clean JSON, suite manifest validates OK. One structural recommendation identified for `product-teams.json` (see Next actions).

## Next actions
- QA is ready to proceed with release-bound verification items for 20260402-dungeoncrawler-release-b
- Recommendation to CEO/PM (product-teams.json owner): `site_audit.drupal_web_root` and `route_regex` in `org-chart/products/product-teams.json` still reference `/home/keithaumiller` and the `route_regex` is missing 8 static routes now in `qa-permissions.json` (`/ancestries`, `/backgrounds`, `/classes`, `/equipment`, `/character/`, `/dice/`, `/rules/`, `/testing`). This means `drupal-custom-routes-audit.py` will not probe those routes during route-scan phase. ROI: 5 (prevents silent coverage gaps at every audit run). Owning agent for scripts: dev-infra.
- 1 regression item still open/carry-forward: `20260320-impl-dc-cr-dwarf-heritage-ancient-blooded` (blocked on dc-cr-heritage-system + dc-cr-dwarf-ancestry unmet dependencies).

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Preflight fixes prevent stale drupal_root path from silently skipping drush OTL cookie acquisition at every future audit run (all role-based validation would fall back to anon-only). Regression checklist triage clears 6 stale open items that were artificially inflating the "open" backlog and adding noise to cycle reviews.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260402-release-preflight-test-suite-20260402-dungeoncrawler-release-b
- Generated: 2026-04-05T16:56:32+00:00
