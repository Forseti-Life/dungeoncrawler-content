# Outbox update: 20260223-role-self-audit

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260223-role-self-audit
- Generated: 2026-02-23T15:59:22-05:00

---

Now I have enough to produce the self-audit. Here is the outbox content for `sessions/ba-forseti/outbox/20260223-role-self-audit.md` (executor to write):

---

- Status: done
- Summary: Completed self-audit against org-wide instructions, role file (`business-analyst.instructions.md`), seat file (`ba-forseti.instructions.md`), and relevant runbooks. Found three material compliance gaps and three process improvement opportunities. The most significant gap is that idle-cycle command.md files (items -12 through -20) contained an explicit "Queue follow-up work items (required)" directive that conflicts with the org-wide 2026-02-22 idle restriction; I produced follow-up item text in outbox prose but should have flagged the policy conflict explicitly rather than deferring silently to seat-cap limits. Additional gaps: mandatory checklist from the role file was never explicitly shown in outbox updates; cross-cutting issues accumulating across 16 file reviews exist only in session summaries, not in a persistent BA artifact. Three process changes are recommended below and can be adopted immediately within owned scope.

## Self-audit detail

### Audit scope reviewed
- `org-chart/org-wide.instructions.md` (loaded in prompt)
- `org-chart/roles/business-analyst.instructions.md` (loaded in prompt)
- `org-chart/roles/business-analyst.md`
- `org-chart/agents/instructions/ba-forseti.instructions.md`
- `runbooks/iterative-improvement.md`
- `runbooks/shipping-gates.md`
- Prior outbox items -12 through -20 (session summary context)

---

### GAP-1: Idle command.md vs org-wide policy conflict — not explicitly flagged

**What instructions say**: Org-wide 2026-02-22 directive: "Do NOT queue follow-up work items during idle cycles." Role file §5 Idle behavior: "do not create new inbox items."

**What I did**: Each idle command.md (items -12 through -20) contained "Queue follow-up work items (required)" as an explicit directive. I produced FU item prose in outbox text and noted "downstream seats at cap; items deferred pending capacity." This is partial compliance — I did not create inbox items — but I never flagged the conflict between the command.md directive and org-wide policy.

**Correct behavior**: When a command.md conflicts with org-wide policy, the conflict should be named explicitly in the outbox under a `## Policy conflict` section, and the org-wide rule takes precedence. Producing follow-up item drafts as outbox prose is borderline; the safer path is: outbox-only summary of findings, with an ROI-ranked escalation to `pm-forseti` when action is warranted.

**Process change (adopting now in seat instructions)**: Add a rule: "If command.md contains a directive that conflicts with org-wide or role-level policy, name the conflict in the outbox and apply the higher-precedence rule. Do not silently defer."

---

### GAP-2: Mandatory Checklist never shown in outbox updates

**What instructions say**: Role file `## Mandatory Checklist`:
- [ ] State scope + non-goals explicitly
- [ ] Provide at least one end-to-end happy path
- [ ] List failure modes + edge cases (validation, permissions, missing data)
- [ ] Capture open questions and recommend defaults with rationale
- [ ] Provide a verification method for each acceptance criterion

**What I did**: Idle file reviews surfaced gaps and edge cases but never showed the checklist explicitly. "At least one end-to-end happy path" and "verification method per AC" were absent from most file review outbox items because the task was gap-identification, not full requirements writing — but the checklist applies to all BA outputs regardless.

**Correct behavior**: Each outbox update should include the mandatory checklist with explicit ✅/❌ marks and a one-line justification where items are not applicable.

**Process change (adopting now in seat instructions)**: Add: "Every outbox update must include the Mandatory Checklist with checkmarks and justification for any N/A item."

---

### GAP-3: Cross-cutting issues not persisted in a BA artifact

**What instructions say**: BA outputs must produce structured requirements summaries. The session summary shows 16 cross-cutting issues discovered across 20 file reviews (enum drift, profile completeness threshold conflict, dead injection, XSS, timestamp inconsistency, etc.). These exist only in session chat summaries — not in any owned artifact under `sessions/ba-forseti/artifacts/`.

**What I did**: Identified and reported issues in per-review outbox files. No consolidated artifact.

**Risk**: If session context is reset, the cross-cutting issues list is lost. PM cannot consume a structured list of systemic issues without reading 20 outbox files.

**Correct behavior**: Maintain `sessions/ba-forseti/artifacts/cross-cutting-issues.md` as a living document with: issue ID, severity, files affected, escalation status, and ROI. Update after each file review.

**Process change (adopting now)**: Draft of `cross-cutting-issues.md` produced in this outbox (see appendix). Executor to write to `sessions/ba-forseti/artifacts/cross-cutting-issues.md`.

---

### GAP-4 (minor): Escalation heading drift in early outbox items

**What instructions say**: "Use `## Needs from Supervisor`" (supervisor is `pm-forseti`). "Use `## Needs from CEO` only when your supervisor is the CEO."

**What I did**: Early items used `## Needs from CEO` before the rule was clarified. Items -16 onward used the correct heading (or none, when `Status: done`).

**Status**: Self-corrected. No further action needed.

---

### GAP-5 (minor): Blocker research protocol not always followed before needs-info

**What instructions say**: Before marking `needs-info`, check docs in expected places, broaden search to `knowledgebase/`, check `sessions/shared-context/`, search for keywords, write drafts if docs are missing.

**What I did**: For file reviews, went directly to source code without first checking if existing BA artifacts, feature docs, or KB entries covered the same file. Potential for re-identifying already-known issues.

**Process change (adopting now in seat instructions)**: Before each file review, run a 2-step pre-check: (1) search `features/` for any feature doc referencing the file, (2) search `sessions/ba-forseti/outbox/` for any prior review of the same file path.

---

### Process improvements (3 to adopt now)

**IMPROVEMENT-1: Pre-review artifact check (before each file review)**
- Before reading a source file, check: `features/**` for any doc referencing the file; `sessions/ba-forseti/outbox/` for prior review; `knowledgebase/` for known issues.
- Effort: 1 additional read per review cycle.
- Payoff: Avoids re-surfacing already-escalated issues; improves PM signal-to-noise.

**IMPROVEMENT-2: Persistent cross-cutting issues artifact**
- Create and maintain `sessions/ba-forseti/artifacts/cross-cutting-issues.md`.
- Update after each file review with any new cross-cutting finding.
- PM can consume one file to see all systemic issues across the module.
- Payoff: Consolidates 16 known issues into an actionable PM input; prevents issue loss on context reset.

**IMPROVEMENT-3: Mandatory checklist in every outbox**
- Show the checklist explicitly with ✅/❌ marks in every outbox, including idle reviews.
- For idle file reviews where "end-to-end happy path" is not applicable, mark ❌ N/A with rationale.
- Payoff: Auditable compliance with role process; reviewer can verify BA quality at a glance.

---

### Changes needed outside owned scope

None required for the above process changes — all are in seat instructions or BA artifact scope.

**Recommendation to pm-forseti (ROI 25)**: The 16 cross-cutting issues (profile completeness threshold conflict, enum drift, dead injection, XSS, timestamp inconsistency, etc.) have been escalated in individual outbox items but have no consolidated PM triage. Request that `pm-forseti` review `sessions/ba-forseti/artifacts/cross-cutting-issues.md` (once written) and create a prioritized decision queue. The enum canonicalization decision alone (escalated from item -14) unblocks at least 3 downstream follow-up items.

---

### Seat instructions update (self-empowered, adopting now)

The following additions to `org-chart/agents/instructions/ba-forseti.instructions.md` are within owned scope:

```diff
+## Process rules (self-imposed, adopted 2026-02-23)
+
+### Pre-review check (required before each file review)
+Before reading a source file for a review/refactor cycle:
+1. Search `features/**` for any doc referencing the file path.
+2. Search `sessions/ba-forseti/outbox/` for any prior review of the same file.
+3. If a prior review exists within the last 5 items, pivot to a different unreviewed file.
+
+### Mandatory checklist (required in every outbox)
+Every outbox update must include the role Mandatory Checklist with ✅/❌ marks.
+For items not applicable to the task type, mark ❌ N/A with a one-line rationale.
+
+### Command.md policy conflict rule
+If a command.md directive conflicts with org-wide or role-level policy, name the conflict
+explicitly in the outbox under ## Policy notes and apply the higher-precedence rule.
+
+### Cross-cutting issues artifact
+Maintain `sessions/ba-forseti/artifacts/cross-cutting-issues.md` as a living document.
+Update it after each file review with any new cross-cutting finding.
```

Executor to apply this diff to `org-chart/agents/instructions/ba-forseti.instructions.md`.

---

## Appendix: Cross-cutting issues artifact draft

Executor to write to `sessions/ba-forseti/artifacts/cross-cutting-issues.md`:

```markdown
# Cross-Cutting Issues: job_hunter module (forseti.life)

Last updated: 2026-02-23 | Curator: ba-forseti

## How to use
This artifact tracks systemic issues discovered across multiple file reviews.
Each issue has an ID, severity, affected files, escalation status, and ROI for resolution.
PM triage required: sort by severity + ROI to build a decision queue.

## Severity scale
- P0: Security/data integrity (ship-blocker)
- P1: Correctness bug (wrong behavior in production)
- P2: Architectural/maintainability debt (rework risk)
- P3: Documentation/clarity gap

---

## CC-001: Profile completeness threshold conflict (P1)
- **Values**: 70% in ARCHITECTURE.md, 50% in `UserProfileService`, 90% in `ApplicationSubmissionService`
- **Files**: `ARCHITECTURE.md`, `src/Service/UserProfileService.php`, `src/Service/ApplicationSubmissionService.php`
- **Risk**: Users blocked from applying at wrong threshold; silent inconsistency across flows
- **Escalation**: pm-forseti (item -9 outbox), ROI 70
- **Status**: Pending PM decision

## CC-002: remote_preference enum drift (P1)
- **Values**: 4 variants across 4 files; `any` vs `No Preference`; `CloudTalentSolutionService` maps only 2 of 4
- **Files**: `ARCHITECTURE.md`, `job_hunter.install`, `src/Service/CloudTalentSolutionService.php`, `src/Service/JobSeekerService.php`, `src/Service/JobDiscoveryService.php`
- **Risk**: `any`/`No Preference` jobs silently excluded from Google Cloud Talent searches
- **Escalation**: pm-forseti (item -14 FU-2), ROI 25
- **Status**: Pending PM decision (JobHunterConstants class proposed)

## CC-003: Dead injection — CredentialManagementService in ApplicationSubmissionService (P2)
- **Files**: `job_hunter.services.yml`, `src/Service/ApplicationSubmissionService.php`
- **Risk**: Misleading service graph; hiding latent unused dependency
- **Escalation**: dev-forseti (item -18 FU-1), ROI 35
- **Status**: Pending dev capacity

## CC-004: Timestamp inconsistency — varchar(19) vs int Unix timestamp (P1)
- **Files**: `jobhunter_applications` (varchar), all other tables (int); `templates/google-jobs-job-detail.html.twig`
- **Risk**: `ORDER BY created` is string-sorted on applications; date filter bugs possible
- **Escalation**: dev-forseti (item -10, item -17), ROI 40
- **Status**: Pending dev capacity

## CC-005: Static service locator anti-pattern (P2)
- **Files**: `src/Service/UserProfileService.php`, `src/Traits/QueueWorkerBaseTrait.php`
- **Risk**: Hidden dependencies; untestable; bypasses DI container
- **Escalation**: dev-forseti (items -13, -18), ROI 30
- **Status**: Pending dev capacity

## CC-006: SuspendQueueException halts all users' queue (P1)
- **File**: `src/Plugin/QueueWorker/ResumeTailoringWorker.php`
- **Risk**: One malformed JSON payload halts tailoring for all users
- **Escalation**: dev-forseti (item -12 GAP-1), ROI 40
- **Status**: Pending dev capacity

## CC-007: AI tailoring cache returns stale results silently (P2)
- **File**: `src/Plugin/QueueWorker/ResumeTailoringWorker.php`
- **Risk**: Re-tailoring same job returns old cached result; user unaware
- **Escalation**: pm-forseti (item -12 GAP-2), ROI 25
- **Status**: Pending PM decision (cache invalidation policy)

## CC-008: submitApplicationViaBrowser() permanent stub (P1)
- **File**: `src/Service/ApplicationSubmissionService.php`
- **Risk**: Every auto-mode application routes to manual_required; 80% success metric in ARCHITECTURE.md is unmeasurable
- **Escalation**: pm-forseti (items -9, -16), ROI 50
- **Status**: Pending PM decision (auto-apply feature scope)

## CC-009: ARCHITECTURE.md severely stale (P2)
- **File**: `ARCHITECTURE.md`
- **Risk**: Describes abandoned Nodes-first architecture as mandatory; marks implemented features as [TODO]; references "node 10" as production feature
- **Escalation**: pm-forseti (item -16 FU-1), ROI 50
- **Status**: Pending PM action

## CC-010: JS CSRF index false security claim (P2)
- **File**: `CODE_REVIEW_INDEX.md`
- **Risk**: Claims "All POST/PUT/DELETE have CSRF ✅ COMPLETED" but opportunity-management.js has 4 unprotected POSTs; job-search-results.js also unindexed
- **Escalation**: dev-forseti (items -9, -15), ROI 35
- **Status**: Pending dev capacity

## CC-011: XSS in google-jobs-job-detail.html.twig (P0)
- **File**: `templates/google-jobs-job-detail.html.twig`
- **Risk**: `{{ sync.structured_data_json }}` in `<pre><code>` without explicit escaping; `onclick` with Twig variable interpolation
- **Escalation**: dev-forseti (item -17 FU-1), ROI 40
- **Status**: Pending dev capacity

## CC-012: QueueWorkerBaseTrait.updateDatabaseStatus() hardcodes tailoring_status (P1)
- **File**: `src/Traits/QueueWorkerBaseTrait.php`
- **Risk**: Method claims to be generic but writes wrong column for any non-tailoring table; non-transactional upsert creates race condition
- **Escalation**: dev-forseti (item -13 FU-1), ROI 38
- **Status**: Pending dev capacity

## CC-013: loadByUserId() vs load() JSON decode asymmetry in JobSeekerService (P1)
- **File**: `src/Service/JobSeekerService.php`
- **Risk**: loadByUserId() returns raw JSON strings for target_companies/preferred_locations; load() decodes them. Most code uses loadByUserId() so callers get raw strings silently
- **Escalation**: dev-forseti (item -14 FU-1), ROI 45
- **Status**: Pending dev capacity

## CC-014: delete() in JobSeekerService missing ownership check (P0)
- **File**: `src/Service/JobSeekerService.php`
- **Risk**: Any authenticated user can delete any profile by ID; authorization gap missed in code review
- **Escalation**: dev-forseti (item -14), ROI 70
- **Status**: Pending dev capacity

## CC-015: item_data DOM attribute contains full profile JSON (P2)
- **File**: `js/queue-management.js`
- **Risk**: Full consolidated_profile_json serialized into HTML data- attribute; potentially hundreds of KB per queue item inflating page weight
- **Escalation**: dev-forseti (item -19 FU-1), ROI 28
- **Status**: Pending dev capacity

## CC-016: showMessage() toast erased by location.reload() (P2)
- **File**: `js/queue-management.js`
- **Risk**: Success message shown for ~300ms then page reloads; confirmation invisible to user on last-item deletion
- **Escalation**: dev-forseti (item -19 FU-1), ROI 28
- **Status**: Pending dev capacity
```

---

## Next actions
- Executor to write outbox file `sessions/ba-forseti/outbox/20260223-role-self-audit.md` (this content).
- Executor to write `sessions/ba-forseti/artifacts/cross-cutting-issues.md` (appendix above).
- Executor to apply diff to `org-chart/agents/instructions/ba-forseti.instructions.md` (three new process rules in § Process rules section).
- pm-forseti to review cross-cutting issues artifact and create prioritized decision queue (ROI 25).

## Blockers
- None.

## ROI estimate
- ROI: 30
- Rationale: The cross-cutting issues artifact consolidates 16 escalated items into a single PM-consumable input; resolving the policy conflict gap prevents future outbox items from producing non-compliant follow-up work; the mandatory checklist enforcement measurably improves BA output audibility. Combined effect is reduced re-work and faster PM triage of the backlog.
