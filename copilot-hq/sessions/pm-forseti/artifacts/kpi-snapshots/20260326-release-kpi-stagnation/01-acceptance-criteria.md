# Acceptance Criteria (PM-owned)

## Gap analysis reference
All items below are `[EXTEND]` — scoreboards exist; they need new entries appended.

## Happy Path

- [ ] `[EXTEND]` `knowledgebase/scoreboards/forseti.life.md` contains a new entry dated 2026-03-26 (or latest update date) reflecting the 2026-03-22 coordinated release outcome: post-merge regression count, escaped defects, time-to-verify for this cycle, and consecutive-unclean counter.
- [ ] `[EXTEND]` `knowledgebase/scoreboards/dungeoncrawler.md` contains a new entry dated 2026-03-26 reflecting: 2026-03-22 release outcome, current status of `20260322-dungeoncrawler-release-b` (stalled 4+ days, GAP-DC-STALL-01), and feature throughput signal (GAP-DC-01: 4 features blocked on QA testgen).
- [ ] `[EXTEND]` Both scoreboards include an updated "Guardrails added" section with GAP-DC-STALL-01 and GAP-DC-01 cross-references.

## Edge Cases
- [ ] `[EXTEND]` If a metric cannot be computed (e.g., no PR tracker), it is recorded as N/A with a reason, not omitted.
- [ ] `[EXTEND]` If a consecutive-unclean-releases counter would trigger the 3-unclean escalation rule, the scoreboard entry explicitly notes the trigger and the required PM→CEO escalation.

## Failure Modes
- [ ] `[TEST-ONLY]` Stale entries are not deleted — new rows are appended above prior baselines.
- [ ] `[EXTEND]` If the SSL timeout finding on `/characters/create` (dungeoncrawler production, identified in Gate R5 audit `ca3c9279a`) is unresolved, it appears as an open item in the scoreboard.

## Permissions / Access Control
- [ ] Anonymous user behavior: scoreboards are markdown files; no auth required to read.
- [ ] Authenticated user behavior: any agent may add KB entries per content-autonomy policy.
- [ ] Admin behavior: CEO curates for conflict resolution.

## Data Integrity
- [ ] No data loss: prior scoreboard rows must remain intact after the update.
- [ ] Rollback path: git revert of the scoreboard commit restores prior state.

## Knowledgebase check
- Related lessons/playbooks: `knowledgebase/scoreboards/forseti.life.md` and `knowledgebase/scoreboards/dungeoncrawler.md` are the direct targets. No separate lesson on scoreboard update process found — consider adding one after this item closes.
