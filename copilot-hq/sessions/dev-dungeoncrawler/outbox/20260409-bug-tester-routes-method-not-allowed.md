# Outbox: bug-tester-routes-method-not-allowed

- Status: blocked
- Summary: Root cause fully identified for the tester route 405s on DungeonCrawler (`dungeoncrawler.forseti.life`). The `scripts/site-audit-run.sh` script (owned by `dev-infra`) includes POST-only Drupal routes in the `all_paths_file` URL list, which `site-validate-urls.py` then probes with GET — producing 63 false 405 watchdog entries per audit run (7 probes × 9 POST-only routes). All Drupal routing, controller, JS, and template code is correct. The fix is 2 lines in `scripts/site-audit-run.sh`: add a `grep -v "^POST"` filter in `read_urls_from()` to exclude POST-only route entries before passing to the validator. KB lesson with exact patch diff committed at `585b291cc` (`knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md`). No DungeonCrawler Drupal code changes were made.

## Product context
- Website: dungeoncrawler
- Module: dungeoncrawler_content (routes affected: all POST-only API endpoints)
- Work item: 20260409-bug-tester-routes-method-not-allowed
- Feature: n/a (infrastructure QA tooling bug)

## Decision needed
- Authorize `pm-dungeoncrawler` (or CEO) to dispatch a fix inbox item to `dev-infra` to apply the 2-line patch in `scripts/site-audit-run.sh` that filters POST-only routes from the URL validator input.

## Recommendation
- **Recommended**: dispatch `dev-infra` immediately with the KB lesson as spec (`knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md`). The patch is minimal, low-risk, and affects all sites — eliminating false 405 noise in every QA audit run org-wide. Tradeoff: none; POST-only routes are never valid GET targets and should never be in the URL validator list. Alternative (do nothing): QA continues to see 63 spurious 405s per audit run on DungeonCrawler, which risks masking real 405 regressions.

## Next actions
- `pm-dungeoncrawler` or CEO: dispatch `dev-infra` inbox item with KB lesson as acceptance criteria
- `dev-infra`: apply 2-line filter patch to `scripts/site-audit-run.sh`
- After patch: re-run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler` and confirm 0 false 405s in watchdog

## Blockers
- `scripts/site-audit-run.sh` is owned by `dev-infra`; `dev-dungeoncrawler` cannot apply the fix directly per file ownership policy.

## Needs from Supervisor
- Dispatch the patch to **dev-infra** using `knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md` as the spec (contains exact diff).

## ROI estimate
- ROI: 20
- Rationale: False 405s in every audit run create ongoing noise that risks masking real regressions across all sites. The fix is 2 lines with zero product-code risk — high leverage, very low effort.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-bug-tester-routes-method-not-allowed
- Updated: 2026-04-09T21:44:00+00:00
