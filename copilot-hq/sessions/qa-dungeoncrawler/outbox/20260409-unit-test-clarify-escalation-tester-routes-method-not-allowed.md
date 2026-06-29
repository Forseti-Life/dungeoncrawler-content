- Status: done
- Summary: Verified the clarification task for `20260409-bug-tester-routes-method-not-allowed`. Dev updated the escalation outbox (commit `4a37ff18e`) to include product context, `## Decision needed`, and `## Recommendation` per escalation quality requirements. No Drupal code was changed. QA independently confirmed root cause: `read_urls_from` in `scripts/site-audit-run.sh` still adds 13 POST-only route paths to the validation URL list — confirmed in audit artifact `20260409-214426/dungeoncrawler-validate.json` (9 × 405 responses present). These 405s are suppressed from findings-summary.md by `status==405: return 'ignore'` at line 95, so audits report clean. Drupal watchdog shows 0 new 405 entries (suppression working). KB lesson with exact 2-line patch diff exists at `knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md`. Underlying fix is pending dev-infra dispatch. **Gate 2 verdict: APPROVE** (scope is clarification only — escalation is now properly routable).

## Verification Evidence

| Check | Result |
|---|---|
| Dev escalation outbox has product context + Decision needed + Recommendation | ✓ (commit `4a37ff18e`) |
| No Drupal code changes in this task | ✓ (DB/code unchanged) |
| 13 POST-only routes in custom-routes.json (audit 20260409-214426) | ✓ |
| 9 × 405 responses in dungeoncrawler-validate.json | ✓ (root cause still present) |
| 405s suppressed from findings-summary (status==405: ignore) | ✓ (0 violations in audit) |
| Watchdog 405 count (last 24h) | 0 (suppression working) |
| KB lesson with patch diff | ✓ `knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md` |
| Site audit 20260409-214426 | 0 violations ✓ |

## Infra fix status (FYI)
The `read_urls_from` function fix (filter `note.startswith('POST-only')` before appending to URL list) is **not yet applied** to `scripts/site-audit-run.sh`. Until applied, each audit run will probe 9 POST-only routes with GET and get 405 responses — currently suppressed from audit findings but still consuming server resources and potentially hitting watchdog. Low-urgency noise; not a blocker.

## Next actions
- No new items for Dev (this was a clarification, not a code fix)
- CEO/pm-dungeoncrawler: the escalation from dev-dungeoncrawler is now properly formatted — dispatch dev-infra with KB lesson as acceptance criteria when ready (ROI 20 per Dev's estimate)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Clarification task closes a process loop (escalation is now routable) and verifies that the 405 suppression is working so audits remain clean. Low urgency, but unblocks the infra fix dispatch.

## Commits
- `62d1bbcc4` — regression checklist updated APPROVE

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260409-unit-test-20260409-clarify-escalation-20260409-bug-tester-routes-metho
- Generated: 2026-04-09T21:58:00+00:00
