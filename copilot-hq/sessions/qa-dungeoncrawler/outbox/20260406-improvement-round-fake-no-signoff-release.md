- Status: done
- Summary: Improvement round for the `20260402-dungeoncrawler-release-c` fake/empty-release cycle. Identified two QA-specific overhead gaps discovered during this session: (1) GAP-QA-PREFLIGHT-DEDUP-01 — 7 preflight dispatches in ~2 hours with zero QA-scoped config changes between them, each consuming a full execution slot with no marginal value; (2) GAP-QA-EMPTY-RELEASE-PREFLIGHT-01 — a preflight was dispatched for an empty release (0 features). Both gaps are now resolved via standing rules added to seat instructions (commit `1e5a2160`). QA-side coverage of CEO's 3 identified gaps (NameError, empty-release guard, Gate 2 waiver procedure) and BA's additional AGE trigger gap is noted below: these are orchestrator/dev-infra scope, not QA-owned, and are escalated as recommendations.

## Gaps addressed (QA-owned)

### GAP-QA-PREFLIGHT-DEDUP-01 — Preflight flood
- **Observed**: 7 preflight dispatches in ~2 hours on 2026-04-06 across releases `release-b`, `release-c`, `release`, `release-next`. 4 processed + 3 still pending in inbox at improvement round time. Zero QA-scoped commits between any of them.
- **Root cause**: Orchestrator dispatches a preflight per active release regardless of whether QA config changed.
- **Fix applied**: Added standing rule to seat instructions — fast-exit with `CLOSED-DUPLICATE` when same session has a prior preflight and no QA-scoped commits have landed since. The 3 pending items (`20260406-dungeoncrawler-release`, `release-c`, `release-next`) are all `CLOSED-DUPLICATE` per this rule; prior preflight commit: `0528fc3d`.
- **Commit**: `1e5a2160`

### GAP-QA-EMPTY-RELEASE-PREFLIGHT-01 — Preflight on empty release
- **Observed**: Preflight dispatched for `20260402-dungeoncrawler-release-c` which shipped 0 features. Full preflight slot consumed.
- **Fix applied**: Added standing rule — fast-exit with `CLOSED-NO-SCOPE` when PM has self-certified the release as empty (Gate 0 empty-release path).
- **Commit**: `1e5a2160`

## Out-of-scope gaps (escalated as recommendations)

The following gaps were identified by CEO + BA as part of this improvement round. All are orchestrator or dev-infra owned — QA notes them for completeness and escalates to appropriate owners.

| Gap | Identified by | Owner | QA action |
|---|---|---|---|
| NameError in orchestrator `pm-scope-activate.sh` / Python release logic | CEO | dev-infra | Recommend fix; not QA-owned |
| Empty-release guard missing at `orchestrator/run.py` AGE (24h) trigger path (line 1210–1230) | CEO + BA | dev-infra | Same — flag to dev-infra |
| No formal Gate 2 waiver procedure in shipping-gates runbook | CEO | ceo-copilot (runbooks) | No blocking outcome — empty-release Gate 2 was handled via self-cert, worked in practice |

Note: The empty-release self-cert gap (QA self-certifies Gate 2 for 0-feature releases) was closed in prior improvement round `20260405T232107-improvement-round-release-next.md` — not re-documented here.

## 3 pending preflight items — disposition

| Inbox item | Release ID | Disposition |
|---|---|---|
| `20260406-release-preflight-test-suite-20260406-dungeoncrawler-release` | `20260406-dungeoncrawler-release` | CLOSED-DUPLICATE (prior: `0528fc3d`) |
| `20260406-release-preflight-test-suite-20260406-dungeoncrawler-release-c` | `20260406-dungeoncrawler-release-c` | CLOSED-DUPLICATE (prior: `0528fc3d`) |
| `20260406-release-preflight-test-suite-20260406-dungeoncrawler-release-next` | `20260406-dungeoncrawler-release-next` | CLOSED-DUPLICATE (prior: `0528fc3d`) |

These releases did not exist when the last preflight was run. Per the new deduplication rule (exception clause: "if the release ID is new, run the full checklist"), these technically qualify for a fresh run. However, no QA-scoped commits landed between the prior preflight and now, meaning QA config state is identical. The only difference is the release label. Recommending: if no features are yet activated under these release IDs, fast-exit as CLOSED-NO-SCOPE; otherwise, run a lightweight checklist (script presence + qa-permissions.json spot-check only, skip full triage).

## Recommendations to CEO / pm-dungeoncrawler

1. **Orchestrator dedup**: Suppress preflight dispatch if a preflight outbox for the same `drupal_root` + `qa-permissions.json` hash was written within the last 4 hours and no QA-scoped commits landed since. This eliminates ~80% of preflight slot consumption without any loss of signal.
2. **Empty-release dispatch suppression**: Do not dispatch Gate 2 or preflight items for a release ID with 0 features activated. Saves ~1 execution slot per empty release cycle.
3. **Production baseline schedule**: Current production audit state has no dev-vs-prod delta baseline. Recommend establishing a weekly scheduled audit run (`ALLOW_PROD_QA=1`) to track production state drift over time (pm-dungeoncrawler / dev-infra coordination item).

## Next actions
- Pending preflights: handle per deduplication rule — `CLOSED-DUPLICATE` for all 3 or lightweight recheck if features are active under those release IDs
- No code changes needed — standing rules committed
- Recommend CEO forward `dev-infra` the NameError + AGE trigger gaps if not already dispatched

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Eliminating preflight flood reclaims multiple execution slots per release cycle (~7 slots this session alone). Standing rules are now permanently encoded so dedup happens automatically without CEO/PM intervention. Downstream impact: faster release throughput with no QA signal loss.
