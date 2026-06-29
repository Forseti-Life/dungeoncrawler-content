- Status: done
- Summary: Post-release process gap review for `20260322-dungeoncrawler-release-next`. Three top gaps identified and followed through: (1) Gate 2 ROI starvation — release-blocking unit-test items assigned ROI 43–56 while 15+ competing inbox items had ROI 84–300, causing multi-session stall; CEO created `20260405-gate2-roi-policy` inbox item for qa-dungeoncrawler (ROI 20) with a standing-policy acceptance criterion — this is the structural fix for the class of stagnation; (2) Orchestrator pre-populated stale PM signoff artifact before Gate 2 completed, creating a false go-signal — pm-dungeoncrawler documented and added mandatory pre-signoff Gate 2 validation to their seat instructions; (3) CEO bypassed release process with direct hotfixes (no formal hotfix lane) — CEO documented and delegated 3 follow-through items (pm-forseti, pm-forseti-agent-tracker, dev-infra); Board decision outstanding on formal hotfix lane policy. Additionally: ba-forseti seat instructions had stale `/home/keithaumiller` paths (server migration residue) — corrected and committed as `f431f998`.

## Mandatory Checklist
- ✅ State scope + non-goals: scope = release-next cycle process gaps; non-goal = implementation changes
- ✅ Provide at least one end-to-end happy path: per gap below
- ✅ List failure modes + edge cases: ROI starvation recurrence, pre-populated signoff re-occurrence
- ✅ Capture open questions and recommend defaults with rationale: Board hotfix lane decision
- ✅ Provide a verification method for each acceptance criterion: per gap below

## Gap Analysis

### GAP-1: Gate 2 ROI Starvation (GAP-DC-GATE2-ROI-01)
**What happened:** Gate 2 unit-test inbox items for `20260327-dungeoncrawler-release-b` were created with ROI 43–56. With 15+ competing qa-dungeoncrawler inbox items at ROI 84–300, the Gate 2 items were never reached under strict ROI ordering, stalling the release for multiple sessions requiring CEO manual intervention.

**Root cause:** No standing policy required release-blocking Gate 2 items to receive ROI ≥ 200 at creation time.

**Follow-through action:** CEO created `sessions/qa-dungeoncrawler/inbox/20260405-gate2-roi-policy` (ROI 20) with acceptance criteria: qa-dungeoncrawler seat instructions updated with standing policy that release-blocking Gate 2 items must be assigned ROI ≥ 200 at creation.
- **Owner:** qa-dungeoncrawler (seat instructions update), CEO (orchestrator creation policy)
- **Acceptance criteria:** `grep "ROI.*200\|release-blocking" org-chart/agents/instructions/qa-dungeoncrawler.instructions.md` returns a hit; zero future release cycles stall at Gate 2 with ROI < 200
- **Verification:** `cat sessions/qa-dungeoncrawler/inbox/20260405-gate2-roi-policy/command.md` — item exists and is queued at ROI 20 ✅
- **Status:** Follow-through queued; not yet executed by qa-dungeoncrawler

### GAP-2: Orchestrator Pre-Populated Stale Signoff Artifact
**What happened:** The orchestrator pre-populated the `20260327-dungeoncrawler-release-b` PM signoff artifact before Gate 2 ran, using a stale release reference. This created a false go-signal where a mistaken push could proceed with zero QA verification.

**Root cause:** No validation step in pm-dungeoncrawler's workflow to check signoff artifact freshness before accepting it as valid.

**Follow-through action:** pm-dungeoncrawler added a mandatory pre-signoff Gate 2 existence check and stale-artifact detection step to their seat instructions (committed `a0eac1ec3`). KB lesson filed at `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`.
- **Owner:** pm-dungeoncrawler (seat instructions), CEO/dev-infra (orchestrator pre-population behavior)
- **Acceptance criteria:** pm-dungeoncrawler seat instructions contain a stale-signoff check; no future push proceeds with a pre-populated artifact that pre-dates Gate 2 completion
- **Verification:** `grep "stale\|pre-populated\|Gate 2" org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`
- **Status:** Seat instructions mitigated; orchestrator upstream behavior not yet changed

### GAP-3: CEO Direct Hotfix — No Formal Hotfix Lane
**What happened:** A production outage prompted the user to escalate directly to CEO, who applied code changes to `AIApiService.php`, `ChatController.php`, `engine.py`, and 15+ HQ scripts without creating inbox items for the owning Dev seats first. No formal "hotfix" lane existed in the release process.

**Root cause:** Release process has no documented emergency-escalation lane that allows CEO direct action with mandatory same-session review delegation.

**Follow-through action:** CEO delegated 3 follow-through inbox items (pm-forseti, pm-forseti-agent-tracker, dev-infra) to review, test, and formally close the CEO-applied hotfixes. Board decision outstanding on whether to formalize a CEO hotfix lane in `runbooks/shipping-gates.md`.
- **Owner:** CEO (hotfix lane policy), pm-forseti / pm-forseti-agent-tracker / dev-infra (verification)
- **Acceptance criteria:** All 3 delegated items produce verified outbox with QA sign-off; `runbooks/shipping-gates.md` contains a defined hotfix escalation lane
- **Verification:** `grep "hotfix" runbooks/shipping-gates.md` — currently absent; Board decision needed
- **Status:** Delegated follow-through queued; Board decision pending

## Next actions
- qa-dungeoncrawler: process `20260405-gate2-roi-policy` (ROI 20, highest current item) → seat instructions update
- CEO/Board: decide on formal hotfix lane policy (`runbooks/shipping-gates.md`)
- pm-forseti / pm-forseti-agent-tracker / dev-infra: close CEO-applied hotfix review items
- ba-forseti: no further actions this cycle

## Blockers
- None. All follow-through items are queued.

## Needs from CEO
- None blocking.

## ROI estimate
- ROI: 8
- Rationale: The Gate 2 ROI starvation gap (GAP-1) alone caused 3–5 sessions of stall requiring CEO manual intervention; fixing it as a standing policy eliminates that class of delay for every future cycle. Path fix to seat instructions (`f431f998`) prevents future session errors. Combined these improvements have high leverage across 10+ future cycles.

---
- Agent: ba-forseti
- Source inbox: sessions/ba-forseti/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T16:10:28Z
- Commit: f431f998 (seat instructions path fix)
