The seat instructions were never updated with the self-audit process rules, and `cross-cutting-issues.md` was never written. I own both files — I'll apply the changes now and commit.

The tool sandbox is blocking all write operations — this is the executor-writes model. I'll produce all content for the executor to write, including the git commit.

Here is the outbox content for `sessions/ba-forseti/outbox/20260224-improvement-round.md`:

---

- Status: done
- Summary: Improvement round completed. Top improvement identified: the three process rules surfaced in the 2026-02-23 role self-audit were never applied to the seat instructions file (`org-chart/agents/instructions/ba-forseti.instructions.md`) and the cross-cutting issues artifact (`sessions/ba-forseti/artifacts/cross-cutting-issues.md`) was never written — both were drafted in the self-audit outbox but the executor did not apply them. The measurable improvement is to apply these two changes now (file content below, executor to write and commit), which converts 16 discovered cross-cutting issues from ephemeral session-chat history into a persistent PM-consumable artifact, and makes three process rules operational. Top blocker: `pm-forseti` has not triaged the 16 cross-cutting issues from the job_hunter module review series; the enum canonicalization decision alone (CC-002) is blocking at least 3 downstream dev follow-ups.

## Improvement (SMART)

**What**: Apply two deferred self-audit deliverables:
1. Add 4 process rules to `org-chart/agents/instructions/ba-forseti.instructions.md` (owned file)
2. Create `sessions/ba-forseti/artifacts/cross-cutting-issues.md` with all 16 cross-cutting issues from file reviews -12 through -20

**Specific scope**: Two files, both in ba-forseti owned scope.

**Measurable outcome**:
- After change 1: Any reviewer running `cat org-chart/agents/instructions/ba-forseti.instructions.md` sees the pre-review check rule, mandatory checklist rule, policy-conflict rule, and cross-cutting artifact rule — all absent today.
- After change 2: `pm-forseti` can open one file and see all 16 systemic issues with severity, ROI, and escalation status, rather than reading 9 separate outbox files.

**Verification**: `git log --oneline org-chart/agents/instructions/ba-forseti.instructions.md` shows a new commit; `wc -l sessions/ba-forseti/artifacts/cross-cutting-issues.md` returns > 100 lines.

**Time bound**: Apply in this improvement round (2026-02-24).

---

### File 1: Updated seat instructions

Executor to write (full replacement) to `org-chart/agents/instructions/ba-forseti.instructions.md`:

```markdown
# Agent Instructions: ba-forseti

## Authority
This file is owned by the `ba-forseti` seat. You may update it to improve your own BA process flow.

## Owned file scope (source of truth)

### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/ba-forseti/**
- org-chart/agents/instructions/ba-forseti.instructions.md

### Forseti Drupal: /home/keithaumiller/forseti.life/sites/forseti
- web/modules/custom/job_hunter/** (read-only by default; only edit when delegated explicitly)

## Out-of-scope rule
- If analysis requires updates to PM-owned specs, request `pm-forseti`.
- If analysis requires code changes, request `dev-forseti`.

## Default mode (while PM organizes)
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do a short in-scope clarity/review pass and write concrete recommendations in your outbox.
- If you need prioritization or missing context, escalate to `pm-forseti` with `Status: needs-info` and an ROI estimate.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- Escalate to your supervisor with `Status: needs-info` or `Status: blocked`, include exact questions/unknowns, and include an ROI estimate.

## Supervisor
- Supervisor: `pm-forseti`

## Process rules (adopted 2026-02-23 via role self-audit)

### Pre-review check (required before each file review)
Before reading a source file for a review/refactor cycle:
1. Search `features/**` for any doc referencing the file path.
2. Search `sessions/ba-forseti/outbox/` for any prior review of the same file.
3. If a prior review exists within the last 5 items, pivot to a different unreviewed file.

### Mandatory checklist (required in every outbox)
Every outbox update must include the role Mandatory Checklist with ✅/❌ marks.
For items not applicable to the task type, mark ❌ N/A with a one-line rationale.

### Command.md policy conflict rule
If a command.md directive conflicts with org-wide or role-level policy, name the conflict
explicitly in the outbox under `## Policy notes` and apply the higher-precedence rule.
Do not silently defer to seat-cap limits or other workarounds.

### Cross-cutting issues artifact
Maintain `sessions/ba-forseti/artifacts/cross-cutting-issues.md` as a living document.
Update it after each file review with any new cross-cutting finding (issue ID, severity, files, escalation status, ROI).
```

---

### File 2: Cross-cutting issues artifact

Executor to write to `sessions/ba-forseti/artifacts/cross-cutting-issues.md`:

```markdown
# Cross-Cutting Issues: job_hunter module (forseti.life)

Last updated: 2026-02-24 | Curator: ba-forseti

## How to use
Systemic issues discovered across multiple file reviews (items -12 through -20).
PM triage: sort by severity then ROI to build a decision queue.
Each resolved item: update Status field and add resolution date.

## Severity scale
- P0: Security / data integrity (ship-blocker)
- P1: Correctness bug (wrong behavior in production)
- P2: Architectural / maintainability debt (rework risk)
- P3: Documentation / clarity gap

---

## CC-001: Profile completeness threshold conflict (P1)
- Values: 70% in `ARCHITECTURE.md`, 50% in `UserProfileService`, 90% in `ApplicationSubmissionService`
- Files: `ARCHITECTURE.md`, `src/Service/UserProfileService.php`, `src/Service/ApplicationSubmissionService.php`
- Risk: Users blocked from applying at wrong threshold; silent inconsistency across flows
- First seen: item -9 outbox
- Escalation ROI: 70
- Status: Pending PM decision

## CC-002: remote_preference enum drift — 4 variants across 4 files (P1)
- Values: `remote|hybrid|onsite|any` (DB), `Remote/Hybrid/On-site/No Preference` (ARCHITECTURE.md), only `remote|hybrid` handled in `CloudTalentSolutionService`
- Files: `ARCHITECTURE.md`, `job_hunter.install`, `src/Service/CloudTalentSolutionService.php`, `src/Service/JobSeekerService.php`, `src/Service/JobDiscoveryService.php`
- Risk: `any`/`No Preference` silently excluded from Google Cloud Talent searches; discovery page passes raw enum to templates
- First seen: items -14, -20
- Escalation ROI: 25
- Status: Pending PM decision (JobHunterConstants class proposed in item -14 FU-2)

## CC-003: Dead injection — CredentialManagementService in ApplicationSubmissionService (P2)
- Files: `job_hunter.services.yml`, `src/Service/ApplicationSubmissionService.php`
- Risk: Misleading service graph; hidden unused dependency; confirmed zero call sites
- First seen: items -6, -18
- Escalation ROI: 35
- Status: Pending dev capacity (dev-forseti item -18 FU-1)

## CC-004: Timestamp inconsistency — varchar(19) vs int Unix timestamp (P1)
- Affected table: `jobhunter_applications` uses `varchar(19)` (YYYY-MM-DD HH:MM:SS); all other tables use `int` Unix timestamps
- Files: `job_hunter.install`, `templates/google-jobs-job-detail.html.twig`
- Risk: `ORDER BY created` is string-sorted on applications; date filter comparisons silently wrong; `|date()` filter parses server local TZ (not UTC)
- First seen: items -10, -17
- Escalation ROI: 40
- Status: Pending dev capacity

## CC-005: Static service locator anti-pattern (P2)
- Files: `src/Service/UserProfileService.php`, `src/Traits/QueueWorkerBaseTrait.php`
- Risk: Hidden dependencies; bypasses DI container; untestable in isolation
- First seen: items -13, -18
- Escalation ROI: 30
- Status: Pending dev capacity

## CC-006: SuspendQueueException halts all users' queue on single-item failure (P1)
- File: `src/Plugin/QueueWorker/ResumeTailoringWorker.php`
- Risk: One malformed JSON payload from AI suspends tailoring queue for all users system-wide
- First seen: item -12 GAP-1
- Escalation ROI: 40
- Status: Pending dev capacity

## CC-007: AI tailoring cache returns stale results silently (P2)
- File: `src/Plugin/QueueWorker/ResumeTailoringWorker.php`
- Risk: Re-tailoring same job returns old cached result; user unaware; cache key is (uid, job_id, section) with no TTL or invalidation AC
- First seen: item -12 GAP-2
- Escalation ROI: 25
- Status: Pending PM decision (cache invalidation policy needed)

## CC-008: submitApplicationViaBrowser() permanent stub (P1)
- File: `src/Service/ApplicationSubmissionService.php`
- Risk: Every auto-mode application routes `pending → processing → manual_required`; 80% success metric in ARCHITECTURE.md is unmeasurable against a permanent stub
- First seen: items -9, -16
- Escalation ROI: 50
- Status: Pending PM decision (auto-apply feature scope)

## CC-009: ARCHITECTURE.md severely stale (P2)
- File: `ARCHITECTURE.md` (2228 lines, last updated 2026-02-06)
- Risk: Describes abandoned Nodes-first architecture as mandatory; marks fully-implemented features as `[TODO - MVP PRIORITY]`; references "node 10" as a production feature; Phase 2 roadmap misrepresents current state
- First seen: item -16
- Escalation ROI: 50
- Status: Pending PM action (pm-forseti item -16 FU-1)

## CC-010: JS CSRF index false security claim (P2)
- File: `CODE_REVIEW_INDEX.md`
- Risk: Claims "All POST/PUT/DELETE have CSRF ✅ COMPLETED" but `opportunity-management.js` has 4 unprotected POSTs; `job-search-results.js` is unindexed
- First seen: items -9, -15
- Escalation ROI: 35
- Status: Pending dev capacity

## CC-011: XSS vectors in google-jobs-job-detail.html.twig (P0)
- File: `templates/google-jobs-job-detail.html.twig`
- Vectors: (1) `{{ sync.structured_data_json }}` rendered in `<pre><code>` without explicit `|escape('html')` — stored XSS via structured_data field; (2) `onclick="alert()"` with Twig variable interpolation via `|escape('js')` in HTML attribute context — potential XSS
- First seen: item -17
- Escalation ROI: 40
- Status: Pending dev capacity (dev-forseti item -17 FU-1) — P0, should queue-jump

## CC-012: QueueWorkerBaseTrait.updateDatabaseStatus() hardcodes tailoring_status field (P1)
- File: `src/Traits/QueueWorkerBaseTrait.php`
- Risk: Method claims to be generic shared infrastructure but writes hardcoded `tailoring_status` column — writes wrong column for any non-tailoring queue worker; non-transactional upsert creates race condition under concurrent workers
- First seen: item -13 FU-1
- Escalation ROI: 38
- Status: Pending dev capacity

## CC-013: loadByUserId() vs load() JSON decode asymmetry in JobSeekerService (P1)
- File: `src/Service/JobSeekerService.php`
- Risk: `loadByUserId()` returns raw JSON strings for `target_companies`/`preferred_locations`; `load()` decodes them. Most code uses `loadByUserId()` via `getCurrentUserProfile()`, so callers receive raw strings silently — downstream `foreach` over raw string produces character iteration
- First seen: item -14 FU-1
- Escalation ROI: 45
- Status: Pending dev capacity

## CC-014: delete() in JobSeekerService missing ownership check (P0)
- File: `src/Service/JobSeekerService.php`
- Risk: Any authenticated user can call `delete($id)` for any profile ID — no uid check; horizontal privilege escalation
- First seen: item -14
- Escalation ROI: 70
- Status: Pending dev capacity — P0, should queue-jump

## CC-015: item_data DOM attribute contains full profile JSON (P2)
- File: `js/queue-management.js`
- Risk: Full `consolidated_profile_json` serialized into HTML `data-item-data` attribute per queue row — potentially hundreds of KB per item inflating page weight and exposing profile data in page source
- First seen: item -19 FU-1
- Escalation ROI: 28
- Status: Pending dev capacity

## CC-016: showMessage() success toast erased by location.reload() (P2)
- File: `js/queue-management.js`
- Risk: Success confirmation shown for ~300ms then page reloads — invisible to user on last-item deletion; user cannot verify action succeeded
- First seen: item -19 FU-1
- Escalation ROI: 28
- Status: Pending dev capacity

---

## P0 queue-jump candidates (action needed immediately)
1. CC-014: `delete()` missing ownership check — any auth user deletes any profile
2. CC-011: XSS in google-jobs-job-detail.html.twig — stored XSS vector

## Top PM decisions pending (by ROI)
1. CC-014 / CC-011 — security (unblock dev)
2. CC-008 — auto-apply stub scope (ROI 50)
3. CC-009 — ARCHITECTURE.md update (ROI 50)
4. CC-001 — profile completeness threshold canonical value (ROI 70)
5. CC-002 — enum canonicalization + JobHunterConstants (ROI 25)
```

---

### Git commit instructions for executor

```bash
cd /home/keithaumiller/copilot-sessions-hq
git add org-chart/agents/instructions/ba-forseti.instructions.md
git add sessions/ba-forseti/artifacts/cross-cutting-issues.md
git add sessions/ba-forseti/outbox/20260224-improvement-round.md
git commit -m "ba-forseti: apply self-audit process rules + create cross-cutting issues artifact

- Add 4 process rules to seat instructions (pre-review check, mandatory
  checklist, policy-conflict rule, cross-cutting artifact maintenance)
- Create sessions/ba-forseti/artifacts/cross-cutting-issues.md with all
  16 systemic issues from job_hunter file reviews -12 through -20
- Two P0 issues flagged for queue-jump: CC-014 (delete() missing
  ownership check) and CC-011 (XSS in google-jobs-job-detail.html.twig)

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

---

## Mandatory Checklist
- ✅ State scope + non-goals explicitly — improvement scope is 2 files in owned scope; non-goal is not refactoring all issues now
- ❌ N/A — end-to-end happy path (improvement round, not a user feature)
- ✅ List failure modes + edge cases — executor write failure: if files are not written, process rules remain unapplied; outbox documents the change for re-application
- ✅ Capture open questions — top blocker documented below
- ✅ Verification method — `git log --oneline` + `wc -l` commands provided above

## Next actions
- Executor: write File 1 (seat instructions) and File 2 (cross-cutting issues artifact) and commit using provided message.
- pm-forseti: review `sessions/ba-forseti/artifacts/cross-cutting-issues.md` and triage P0 items (CC-014, CC-011) to dev-forseti immediately; schedule enum canonicalization decision (CC-002) to unblock 3 pending dev items.

## Blockers
- pm-forseti has not triaged cross-cutting issues from the review series. 16 issues documented, 2 are P0 (security). No PM decision has been issued on any item escalated since item -9 (2026-02-22). This is now the top blocker for downstream dev work.

## ROI estimate
- ROI: 35
- Rationale: Applying the seat instructions rules closes a compliance gap identified in the self-audit and prevents future idle cycles from producing non-compliant output. Creating the cross-cutting issues artifact converts 16 scattered outbox entries into a single PM-consumable triage doc — the two P0 security issues (CC-011 XSS, CC-014 unauthorized delete) alone justify immediate action. The artifact also makes the enum canonicalization decision (unblocking 3+ dev items) visible to pm-forseti in one place.

---
- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260224-improvement-round
- Generated: 2026-02-24T08:44:12-05:00
