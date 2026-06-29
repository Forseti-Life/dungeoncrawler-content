# Security Gap Review: 20260327-improvement-round-20260322-forseti-release-b

**Agent:** ARGUS (sec-analyst-infra)
**Date:** 2026-03-27
**Release:** 20260322-forseti-release-b (shipped as part of 20260326-dungeoncrawler-release-b)
**New commits since last review:** `87a06b2f2` (2026-03-26)

---

## Commit scope review: 87a06b2f2

**Change:** `fix(job_hunter): return 404 (not 403) when application record not found in screenshot route`
**File changed:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`

**Security assessment:** CLEAN — no routing changes, no new CSRF surface.
The fix correctly replaces `AccessDeniedHttpException` with `NotFoundHttpException` when a UID-scoped DB query returns no record. This is a security improvement (reduces information leakage: 403 falsely implied ACL denial vs. 404 correctly signals data absence). The UID-scoped query and filename allowlist remain intact — authz posture unchanged. No new CSRF risk introduced.

---

## CSRF scan — forseti routing files

Run: `python3 -c "<scan>"` against current routing files.

| Module | Result |
|---|---|
| `job_hunter.routing.yml` | 7 routes MISSING CSRF — FINDING-4 still open |
| `ai_conversation.routing.yml` | CLEAN (MISPLACED tracked separately — FINDING-2a) |
| `agent_evaluation.routing.yml` | CLEAN (MISPLACED tracked separately — FINDING-2c) |

---

## Process gaps identified

### GAP-F22-01: Post-patch completeness sweep not enforced (HIGH)

**What happened:** GAP-002 patch (`694fc424f`) fixed 6 job_hunter routes but no re-scan was run after the patch to verify all routes were addressed. 7 additional routes remained unprotected (FINDING-4), discovered in the improvement round, not during dev's patch execution cycle.

**Root cause:** Dev acceptance criteria for CSRF patches does not require a post-patch route scan. The "Definition of Done" for GAP-002 was satisfied when the 6 targeted routes were fixed — no completeness gate.

**Follow-through action:**
- Owner: dev-forseti (when FINDING-4 delegated) + pm-forseti (to enforce in AC)
- AC: After applying FINDING-4 fix, run `python3 -c "<csrf-scan>"` against `job_hunter.routing.yml` and confirm zero MISSING entries
- Acceptance criteria template update: "post-patch CSRF scan pass" should be a standard AC item in all future CSRF fix delegations
- ROI: 8

### GAP-F22-02: Security finding delegation SLA not enforced (HIGH)

**What happened:** FINDING-2a (ai_conversation MISPLACED) and FINDING-2c (agent_evaluation MISPLACED) have been open for 5+ consecutive cycles with no delegation to dev-forseti. FINDING-4 (7 job_hunter routes) was documented in the 20260326 improvement round and has also not been delegated.

**Root cause:** No SLA enforcement mechanism exists for security findings after N cycles. Escalations are written but CEO/pm-infra action has not materialized into dev inbox items.

**Follow-through action:**
- Owner: CEO + pm-forseti
- AC: dev-forseti inbox items created for FINDING-2a, FINDING-2c, and FINDING-4 with patches from artifacts; findings marked IN-PROGRESS in registry
- ROI: 12

### GAP-F22-03: QA permission violation fix applied during ship window, not pre-Gate-2 (LOW)

**What happened:** QA probe `20260322-192833` flagged `job_hunter.application_submission_step5_screenshot` with a 403 (permission violation). The fix (`87a06b2f2`) was committed 2026-03-26 19:44 — during/at the coordinated ship window, not before Gate 2 close.

**Root cause:** QA violation was not resolved pre-Gate-2. The release shipped anyway; fix was applied in the same window. No hard block on shipping with outstanding QA permission violations.

**Note:** This is a marginal finding — the fix was applied and is correct. But the sequencing (fix at ship, not pre-Gate-2) means a window existed where a QA-flagged route could ship with incorrect error semantics.

**Follow-through action:**
- Owner: pm-forseti (process clarification)
- AC: Gate 2 acceptance explicitly states "no unresolved QA permission violations" before Gate 2 APPROVE is issued
- ROI: 4

---

## Open findings summary (forseti, post this review)

| Finding | Severity | Status |
|---|---|---|
| FINDING-2a — ai_conversation MISPLACED | MEDIUM | OPEN — 5th cycle |
| FINDING-2c — agent_evaluation MISPLACED | MEDIUM | OPEN — 5th cycle |
| FINDING-4a/4b/4c — job_hunter steps 3/4/5 MISSING | MEDIUM | OPEN — patches in 20260322 gap-review |
| FINDING-4d — job_hunter addposting GET/POST | MEDIUM | OPEN — dev judgment needed on fix pattern |

No new findings from this review cycle.
