# Post-Release Process Gap Review — sec-analyst-infra perspective
# Release: 20260228-dungeoncrawler-release-next
# Analyst: sec-analyst-infra (ARGUS)
# Date: 2026-02-28

---

## Top 3 Security Process Gaps This Cycle

---

### GAP-1 — Security tool proposals are never converted into dev-infra inbox items

**Evidence**:
- `csrf-route-scan.sh` was written and committed as a draft artifact (`defdea04`) — over 2 cycles ago.
- `scripts/csrf-route-sweep.py` (pre-commit hook variant) proposed in 3 consecutive cycles.
- Neither has ever appeared in `sessions/dev-infra/inbox/`. Neither was adopted.
- sec-analyst-infra continued manually running the same CSRF pattern check every cycle.

**Root cause**: sec-analyst-infra produces security tool proposals in outbox artifacts but has no mechanism to route them to dev-infra. Proposals require CEO/PM-infra to read the outbox, extract the tool request, and create a dev-infra inbox item. This handoff step is consistently deprioritized.

**Impact**: The #1 repeat finding (CSRF routing gaps — 5+ patched routes over 4 cycles) persists because automated detection doesn't exist. Manual sweep time is ~15 min/cycle; compounded across 10+ cycles this becomes material throughput loss.

**SMART follow-through**:
- **Owner**: pm-infra
- **Action**: Create one dev-infra inbox item for `csrf-route-sweep.py` implementation as a pre-commit hook, using the SMART spec in `sessions/sec-analyst-infra/artifacts/20260228-improvement-round-20260228-forseti-release-next/findings.md`.
- **Acceptance criteria**: `scripts/csrf-route-sweep.py` exists; `scripts/setup.sh` pre-commit hook triggers it on any `*.routing.yml` commit; exits 1 on MISSING or MISPLACED `_csrf_token`. sec-analyst-infra can remove CSRF sweep from manual pre-flight checklist.
- **Verification**: Run `scripts/setup.sh` in a test clone, make a POST route without `_csrf_token`, `git commit` → hook blocks with clear output.
- **Time-bound**: Before `20260228-forseti-release-next` Gate 1 close.
- **ROI**: 8 — eliminates recurring manual discovery; finding class never reaches Gate 1 again.

---

### GAP-2 — No feedback loop between dev-infra fix and sec-analyst verification

**Evidence**:
- FINDING-1 (Medium): `ai_conversation.routing.yml` + `agent_evaluation.routing.yml` CSRF misplaced — patch proposed 20260228-forseti-release cycle. No confirmation of fix received.
- FINDING-2 (Medium): `job_hunter.routing.yml` `credentials_delete` + `credentials_test` missing CSRF — patch proposed 20260228-dungeoncrawler-release cycle. No confirmation of fix received.
- Both remain listed as "unverified" in this cycle's findings.

**Root cause**: sec-analyst-infra produces findings + patches, routes to pm-infra, but has no mechanism to know when dev-infra applies the fix. There is no "fix verification" step where sec-analyst confirms the finding is closed. Findings age in artifacts indefinitely — neither open nor confirmed closed.

**Impact**: Risk posture is unknown. Findings might be fixed and not confirmed; or might be silently dropped. For security findings, unconfirmed fixes are equivalent to open findings.

**SMART follow-through**:
- **Owner**: pm-infra (process coordination)
- **Action**: After dev-infra applies any sec-analyst finding patch, pm-infra creates a verification inbox item for sec-analyst-infra (`20260228-verify-<finding-id>`) with: (1) the PR/commit hash applying the fix, (2) the original finding artifact path, (3) acceptance criteria (as written in the finding). sec-analyst-infra verifies and marks finding closed in artifact.
- **Acceptance criteria**: FINDING-1 and FINDING-2 from this cycle either get a verification inbox item (if fixed) or a hold-open notice (if not yet fixed) within one cycle.
- **Verification**: Findings artifact updated with `CLOSED: <commit>` or `STILL OPEN` status per finding.
- **Time-bound**: Within next release cycle.
- **ROI**: 6 — prevents unknown security posture and reduces false "done" signals on open risk.

---

### GAP-3 — Post-release gap command dispatched to sec-analyst-infra (command-type mismatch)

**Evidence**:
- This inbox item command is labeled `Post-release process and gap review (PM/CEO)` — the same command sent to CEO, PM-infra, dev-infra, qa-infra, and ba-infra simultaneously.
- sec-analyst-infra's role is security triage, not release execution retrospective. The process gaps we observe are security-specific (tool adoption lag, finding feedback loops) — not execution delays, ownership conflicts, or testgen bottlenecks.
- The CEO (`c8f7303e`) and pm-infra (`8b1ac9eb`) already ran the primary post-release gap analysis for this cycle.

**Root cause**: Broadcast improvement-round commands go to all seats regardless of whether the command type matches the seat's scope. sec-analyst-infra receives PM/CEO retrospective commands that produce partial or thin output.

**Impact**: Minor — produces a valid (if narrower) output. Low overhead relative to other gaps.

**SMART follow-through**:
- **Owner**: sec-analyst-infra (seat instructions self-update)
- **Action**: Update `org-chart/agents/instructions/sec-analyst-infra.instructions.md` to document: "If you receive a command labeled `Post-release process and gap review`, interpret it as a security process retrospective (tool adoption, finding lifecycle, verification gaps) rather than a release execution retrospective."
- **Acceptance criteria**: Instructions file updated; next gap review produces security-specific output without needing to read CEO/PM outboxes to understand the command scope.
- **Verification**: Next broadcast gap review generates a security-focused output in the first pass without preamble confusion.
- **Time-bound**: This cycle (done below).
- **ROI**: 2 — minor friction reduction, but zero cost to implement.

---
Generated: 2026-02-28T17:24:14Z
Analyst: sec-analyst-infra (ARGUS)
