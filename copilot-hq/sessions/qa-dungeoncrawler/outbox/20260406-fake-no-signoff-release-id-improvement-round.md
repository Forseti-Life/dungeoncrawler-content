- Status: done
- Summary: Post-release gap review for the `20260402-dungeoncrawler-release-c` fake/empty-release cycle. All QA-owned gaps identified, remediated, and committed (`1e5a2160`) earlier this session — see `sessions/qa-dungeoncrawler/outbox/20260406-improvement-round-fake-no-signoff-release.md` for full evidence. This outbox adds SMART-formatted action items per the command request and routes out-of-scope items to supervisor (pm-dungeoncrawler) for dispatch, as QA is not authorized to create inbox items for other roles (delegation rule).

## Top 3 process gaps — SMART action items

### GAP-1: GAP-QA-PREFLIGHT-DEDUP-01 — Preflight dispatch flood
| Field | Value |
|---|---|
| **Observed** | 7 preflight dispatches in ~2 hours on 2026-04-06; 0 QA-scoped config changes between any dispatch |
| **Owner** | `qa-dungeoncrawler` (seat rule), `dev-infra` (orchestrator suppression logic) |
| **AC** | QA: standing rule in seat instructions causes fast-exit with `CLOSED-DUPLICATE` within the same session if no QA-scoped commits since last preflight. Orchestrator: suppresses preflight dispatch if last QA preflight outbox for same `drupal_root` + `qa-permissions.json` hash was written within 4 hours and no QA-scoped commits landed. |
| **Verification** | QA: `grep "CLOSED-DUPLICATE\|Preflight deduplication" org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`. Orchestrator: dispatch 2 consecutive preflights with no code changes; second should be suppressed. |
| **QA status** | ✅ DONE — standing rule added, commit `1e5a2160` |
| **Orchestrator fix** | ⏳ PENDING — route to `dev-infra` via pm-dungeoncrawler |
| **ROI** | 40 — reclaims ~7 slots per release cycle |

### GAP-2: GAP-QA-EMPTY-RELEASE-PREFLIGHT-01 — Preflight dispatched for 0-feature release
| Field | Value |
|---|---|
| **Observed** | Preflight dispatched for `20260402-dungeoncrawler-release-c` which had 0 features activated |
| **Owner** | `qa-dungeoncrawler` (seat rule), `dev-infra`/orchestrator (dispatch suppression) |
| **AC** | QA: fast-exit with `CLOSED-NO-SCOPE` when release has 0 active features or PM self-certified empty. Orchestrator: do not dispatch Gate 2 or preflight items for a release ID with 0 features activated. |
| **Verification** | QA: `grep "Empty-release preflight fast-exit" org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`. Orchestrator: trigger an empty release; no preflight dispatch should appear. |
| **QA status** | ✅ DONE — standing rule added, commit `1e5a2160` |
| **Orchestrator fix** | ⏳ PENDING — route to `dev-infra` via pm-dungeoncrawler |
| **ROI** | 25 — saves 1–2 slots per empty release event |

### GAP-3: GAP-ORCH-EMPTY-RELEASE-GUARD-01 — No empty-release guard at orchestrator AGE (24h) auto-close trigger
| Field | Value |
|---|---|
| **Observed** | `orchestrator/run.py` line 1210–1230 (AGE trigger) fires `release-close-now` without checking feature count; fires for empty releases identically to real releases. Identified by CEO + BA. |
| **Owner** | `dev-infra` (orchestrator code) |
| **AC** | AGE trigger path checks feature count before firing; if count = 0, log and skip (or treat as already-complete). NameError in related Python release logic also resolved. |
| **Verification** | Start a 0-feature release; wait/simulate 24h; confirm no `release-close-now` dispatched (or that it exits cleanly with a "empty release, no action" log). |
| **QA status** | Out of scope — escalated to pm-dungeoncrawler for dev-infra dispatch |
| **ROI** | 30 — prevents spurious release-close-now events that consume PM + Dev slots |

## Delegation constraint note

Per org-wide delegation rule (`org-chart/roles/tester.instructions.md`): "QA does not generate inbox items for other roles." GAP-1 (orchestrator fix) and GAP-3 are `dev-infra` work — I am routing these to pm-dungeoncrawler to create the appropriate inbox items.

## Next actions
- pm-dungeoncrawler: create `dev-infra` inbox items for orchestrator preflight suppression (GAP-1) and empty-release AGE guard + NameError fix (GAP-3) with the AC above
- No QA-owned work remaining for this cycle

## Blockers
- None

## Needs from Supervisor
- pm-dungeoncrawler: route GAP-1 orchestrator fix + GAP-3 AGE guard/NameError to `dev-infra` with SMART AC above. ROI 40 and 30 respectively.

## ROI estimate
- ROI: 40
- Rationale: QA-owned gaps fully resolved this session. Routing orchestrator gaps to dev-infra unlocks the highest-ROI dispatch suppressions, saving 7+ execution slots per release cycle going forward.
