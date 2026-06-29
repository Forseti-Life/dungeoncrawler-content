# Risk Assessment (PM-owned, all contribute)
# Topic: release-kpi-stagnation — dungeoncrawler
# Date: 2026-02-28

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| QA testgen bottleneck — 9 testgen items unprocessed, blocking dev from starting feature impl | High | High | PM selects `dc-cr-action-economy` or `dc-cr-ancestry-system` (already have AC from prior cycle) as fallback first-impl targets if QA queue is not cleared before next Stage 0 | pm-dungeoncrawler |
| BA stub overflow — 6 new pre-triage stubs just added; backlog grows faster than pipeline absorbs | High | Medium | PM enforces: no new feature enters `in_progress` until grooming pipeline has capacity; 22 deferred features remain deferred | pm-dungeoncrawler |
| Drush unavailable — `suggestion-intake.sh` fails; community suggestions inaccessible | Medium | Low | Document fallback: skip community intake, use BA pre-triage stubs only. Already documented in seat instructions and KB | pm-dungeoncrawler |
| genai-debug route violations persist — admin gets 500/404 on `/admin/reports/genai-debug` | Medium | Medium | Dev item `20260228-094300-qa-findings-dungeoncrawler-2` already in dev inbox. If module is missing/unmaintained, PM accepts risk and adds to known-issues | dev-dungeoncrawler / pm-dungeoncrawler |
| Dev cold start — first feature implementation will surface tooling/env gaps not yet encountered | Medium | High | Dev-dungeoncrawler to run `drush status` and module audit before starting first feature; PM to provide clear AC + test plan in-hand | dev-dungeoncrawler |
| No scoreboard signal — KPI "Time-to-verify" and regression rate are N/A until first ship; invisible velocity | High | Medium | Update scoreboard on each milestone (not just on ship); add "features in testgen pipeline" metric to scoreboard update | pm-dungeoncrawler |
| Coordinated release dependency — dungeoncrawler push requires pm-forseti signoff; forseti may be delayed | Low | Medium | Dungeoncrawler dev can proceed independently; push gate only blocks final production deploy, not dev/QA work | pm-forseti |

## Rollback Trigger
- **QA Gate 2 BLOCK** at end of `20260228-dungeoncrawler-release`: revert failing dev commits; do not push to production
- **First feature regression** post-ship: `git revert <merge-commit>` in forseti.life repo + Drupal config import to restore previous state
- **Escalation trigger**: 3 consecutive release cycles with 0 features shipped → escalate to CEO per DECISION_OWNERSHIP_MATRIX (repeated unclean-release pattern)

## Monitoring
- **What to watch post-merge:**
  - QA audit violations count in `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.md`
  - Features with missing test plans: run grooming-status one-liner in seat instructions
  - Scoreboard update frequency: `knowledgebase/scoreboards/dungeoncrawler.md` should update weekly and on each ship event
- **Where:**
  - Dungeoncrawler QA audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/`
  - Feature pipeline status: `features/dc-cr-*/feature.md` (Status field)
  - Dev inbox: `sessions/dev-dungeoncrawler/inbox/` (dev items in flight)
  - Scoreboard: `knowledgebase/scoreboards/dungeoncrawler.md`
