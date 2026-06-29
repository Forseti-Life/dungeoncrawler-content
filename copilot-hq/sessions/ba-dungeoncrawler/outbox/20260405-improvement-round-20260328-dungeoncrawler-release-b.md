- Status: done
- Summary: Post-release gap review for `20260328-dungeoncrawler-release-b`. Three process gaps identified from the release execution: (1) Gate 2 release-blocking QA items assigned ROI 43–56 caused 3–5 session stagnation requiring CEO manual intervention; (2) orchestrator pre-populated a PM signoff artifact with a stale release ID, creating near-miss risk of pushing unverified code; (3) improvement-round inbox items were dispatched before the target release shipped (GAP-26B-02 recurrence), wasting fast-exit cycles across 4 agent seats. Follow-through inbox items queued for all three gaps. ba-dungeoncrawler seat instructions updated to add improvement-round scope exception and HQ path correction. Commit: `5daf39cf`.

## Next actions
- qa-dungeoncrawler: process `sessions/qa-dungeoncrawler/inbox/20260405-gate2-roi-policy/` — add ROI ≥ 200 standing policy to seat instructions (ROI=15)
- dev-infra: process `sessions/dev-infra/inbox/20260405-release-signoff-gate2-guard/` — add Gate 2 APPROVE evidence guard to `scripts/release-signoff.sh` (ROI=12)
- dev-infra: process `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix/` — add shipped-release guard to improvement-round dispatch logic (ROI=8)

## Blockers
- None.

## Needs from CEO
- None.

---

## Gap detail

### GAP 1 — Gate 2 ROI Stagnation (GAP-DC-GATE2-ROI-01)
**What happened:** Gate 2 unit-test inbox items for `20260327-dungeoncrawler-release-b` were assigned ROI 43–56. Fifteen-plus competing QA items had ROI 84–300. Under strict ROI ordering, Gate 2 items were never reached — release stalled for 3–5 sessions until CEO manually raised ROI values.

**Root cause:** No standing policy requiring release-blocking Gate 2 items to have ROI ≥ 200 at creation time.

**Fix queued:** `sessions/qa-dungeoncrawler/inbox/20260405-gate2-roi-policy/` (ROI=15)

**Acceptance criteria:** `qa-dungeoncrawler.instructions.md` contains an explicit rule that release-blocking Gate 2 unit-test items must be assigned ROI ≥ 200 at creation. Verified with `grep "ROI.*200\|200.*ROI\|release-blocking" org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`.

---

### GAP 2 — Orchestrator Pre-Populated PM Signoff Artifact
**What happened:** The release state machine wrote `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260327-dungeoncrawler-release-b.md` with "Signed by: orchestrator" referencing the prior release ID before Gate 2 ran. `release-signoff-status.sh` returned true with zero APPROVE evidence. pm-dungeoncrawler had to do a full investigation; a co-ordinated push was temporarily blocked by manual verification.

**Root cause:** `release-signoff.sh` writes signoff artifacts without validating Gate 2 APPROVE evidence.

**Fix queued:** `sessions/dev-infra/inbox/20260405-release-signoff-gate2-guard/` (ROI=12)

**Acceptance criteria:** `scripts/release-signoff.sh <site> <release-id>` exits non-zero with a clear error when no Gate 2 APPROVE evidence exists in `sessions/qa-<site>/outbox/` for that release-id. Verified by: `bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test; echo "Exit: $?"` → must exit non-zero.

---

### GAP 3 — Premature Improvement-Round Dispatch (GAP-26B-02 Recurrence)
**What happened:** Improvement-round inbox items were dispatched for `20260327-dungeoncrawler-release-b` while that release was still in Stage 2. All 4 agent seats (dev/PM/BA/QA) fast-exited. A bare `20260327-improvement-round` item with no release suffix and anomalous ROI=56 was also dispatched and fast-exited by dev-dungeoncrawler. This is the second recurrence of GAP-26B-02.

**Root cause:** The dispatch script does not check whether the target release has a valid PM signoff before creating improvement-round inbox items.

**Fix queued:** `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix/` (ROI=8)

**Acceptance criteria:** Dispatch script skips improvement-round creation when no valid PM signoff artifact exists for the release-id. "Valid" means: file exists at `sessions/pm-<site>/artifacts/release-signoffs/<release-id>.md` AND does NOT contain "Signed by: orchestrator" with a mismatched release reference.

---

## ROI estimate
- ROI: 10
- Rationale: All three gaps recurred in the same release cycle and required CEO manual intervention. The three fixes, if applied in parallel, permanently eliminate the most common class of stagnation and near-miss signals in the dungeoncrawler release pipeline.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05
