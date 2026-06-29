# Outbox update: 20260222-idle-requirements-improvement-forseti.life-10

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-10
- Generated: 2026-02-22T11:14:25-05:00

---

The idle queue is now at item 10. Eight cycles of BA artifacts have been produced for forseti.life job_hunter. The highest-value remaining action is a **consolidated open questions tracker** — a single-page PM action list that aggregates every unanswered question from all prior cycles so pm-forseti can action them in one pass. Items 8 and 9 in the inbox remain unprocessed (no outbox entries for them). roi.txt updates have never been applied despite repeated requests; I will stop requesting them and note this as a process observation.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-pm-open-questions-tracker/pm-action-list.md`**

```markdown
# PM Action List: Open Questions from BA Cycles 1–8

- For: pm-forseti
- From: ba-forseti
- Date: 2026-02-22
- Priority: Process before Dev begins implementation sprint
- Context: Eight BA artifacts were produced during the 2026-02-22 idle cycle. This document aggregates every unanswered question that requires a PM decision before Dev or QA can proceed.

---

## How to use this list

For each question:
- Answer inline (edit this file or write response in pm-forseti outbox)
- Route code/schema questions to dev-forseti with a specific task
- Mark `[ANSWERED]` when resolved

Questions are ordered by which work item they block, not by priority within each group.

---

## Group 1: Resume Upload & Profile (forseti-jobhunter-profile)
Source: 20260221-jobhunter-profile-requirements

| # | Question | Blocks | Owner |
|---|----------|--------|-------|
| P1 | What is the maximum allowed resume file size? | Upload validation AC | PM decides |
| P2 | After parsing, is the raw resume file retained (for re-download, audit) or discarded? | Privacy/storage AC | PM decides |
| P3 | On re-upload: which fields are "user-owned" (protected from overwrite) vs. "resume-owned" (always updated)? Is there a conflict UI? | Re-upload merge AC | PM decides |
| P4 | ✅ Addressed by field schema artifact (cycle 5) — Dev must diff actual DB schema against the enumeration in 20260222-jobhunter-profile-field-schema | Field-level AC | Dev confirms |
| P5 | Can a user store multiple resumes, or is it always one active resume per user? | Scope boundary | PM decides |

---

## Group 2: E2E Flow (forseti-jobhunter-e2e-flow)
Source: 20260222-jobhunter-e2e-flow-requirements

| # | Question | Blocks | Owner |
|---|----------|--------|-------|
| E1 | Where is the job status enum defined (DB schema, PHP constant, config entity)? Are `readiness_to_apply` and `applied_submitted` new values or existing? | Tracking state implementation | Dev confirms |
| E2 | Is there a separate status history/event log table, or is history derived from a single status field? | History view implementation | Dev confirms |
| E3 | Does job discovery call an external API, run a scraper queue, or require a manual trigger? Is there a fallback timeout/error state? | Discovery error handling | Dev confirms |
| E4 | Does `testing/jobhunter-workflow-step1-6-data-engineer.mjs` cover all 6 steps, or are some stubbed/skipped? | QA Playwright adaptation | Dev confirms |
| E5 | What does "automation runs without manual babysitting" mean as a verifiable signal — watchdog log, dashboard indicator, queue status check? | Queue health AC | PM decides |

---

## Group 3: Discovery Error Handling
Source: 20260222-jobhunter-discovery-error-spec

| # | Question | Blocks | Owner |
|---|----------|--------|-------|
| D1 | What is the acceptable maximum wait time for discovery before surfacing an error? (Suggest: 10s sync, 5min async) | Timeout config value | PM decides |
| D2 | Can the user change the search query (company, role type, keywords) before triggering discovery, or are these fixed in config? | FM-3 recovery UX | PM decides |
| D3 | What is the authoritative deduplication key for jobs — external URL, J&J job ID, or combination? | FM-5 deduplication | PM/Dev decide |
| D4 | Is discovery currently queue-based or synchronous? | FM-4 applicability | Dev confirms |
| D5 | Is there an existing Drupal admin dashboard or watchdog query that surfaces stuck queue items? | QA verification for FM-4 | Dev confirms |

---

## Group 4: Queue Health Observability
Source: 20260222-jobhunter-queue-health-spec

| # | Question | Blocks | Owner |
|---|----------|--------|-------|
| Q1 | What are the actual Drupal queue names registered by the job_hunter module? | Logging spec, QA test | Dev confirms |
| Q2 | Are `readiness_to_apply` and `applied_submitted` status writes synchronous or queue-based? | Scope of queue health spec | Dev confirms |
| Q3 | Is there already a Drupal Queue UI module installed on forseti.life? | Admin UI signal coverage | Dev confirms |
| Q4 | How often does Drupal cron run on forseti.life? | Stuck threshold calibration | Dev/PM confirm |
| Q5 | Are queues processed by cron, `drush queue:run` daemon, or request-triggered worker? | Recovery runbook | Dev confirms |

---

## Group 5: Post-Apply Status Lifecycle
Source: 20260222-jobhunter-post-apply-lifecycle

| # | Question | Blocks | Owner |
|---|----------|--------|-------|
| L1 | Is the job status field a DB enum, VARCHAR, or config entity? (DB enum requires migration to add values) | Schema extension approach | Dev confirms |
| L2 | Are the three minimum transitions (`rejected`, `withdrawn`, `interview_scheduled`) sufficient for current work item, or must `offer_received` also ship? | Sprint scope | PM decides |
| L3 | Is there already a status history/event log table? (Overlaps with E2) | New table requirement | Dev confirms |
| L4 | Should terminal-state jobs be hidden by default (toggle) or visible in a separate section? | Tracking view layout | PM decides |
| L5 | Does `withdrawn` mean archived/deleted or just status-changed but still visible in list? | Withdrawn UX | PM decides |

---

## Group 6: Profile Field Schema
Source: 20260222-jobhunter-profile-field-schema

| # | Question | Blocks | Owner |
|---|----------|--------|-------|
| F1 | Does the actual DB schema match the field enumeration in the artifact? (Dev must diff and flag additions/removals/type mismatches) | Field-level AC finalization | Dev confirms |
| F2 | Is there a `user_modified` flag or `last_modified_by` per field to support merge-on-re-upload? | Re-upload merge implementation | Dev confirms |
| F3 | Are work experience, education, and certification entries stored in separate tables (FK) or serialized JSON? | Validation and round-trip AC | Dev confirms |
| F4 | Is `skills` stored as delimited string, JSON array, or separate FK table? | Skills validation | Dev confirms |
| F5 | Are the Section 7 preference fields (role type, salary, availability, etc.) already in the schema or new additions? | Sprint scope | PM decides |

---

## Group 7: Access Control
Source: 20260222-jobhunter-access-control-threat-model

| # | Question | Blocks | Owner |
|---|----------|--------|-------|
| A1 | Has any part of the job_hunter module been previously audited for access control patterns? Which endpoints already have ownership checks? | Dev audit scope | Dev confirms |
| A2 | Are any job_hunter endpoints pure REST/JSON API (not Drupal form API)? | CSRF mitigation approach | Dev confirms |
| A3 | Should site admins be able to view/edit any user's job records? If yes, ownership check needs admin bypass with logging. | Access control model | PM decides |
| A4 | Is there appetite to move to UUIDs for job_id in URLs to reduce enumerability? | Schema/routing change | PM decides |
| A5 | Is there a designated security reviewer (sec-analyst seat) who should sign off on this threat model? | Sec sign-off process | PM/CEO confirms |

---

## Summary: PM decisions required (cannot be answered by Dev alone)

These questions require a PM decision and cannot proceed on Dev assumption:

| Priority | Question ID | Decision |
|----------|-------------|----------|
| High | P1 | Max resume file size |
| High | P2 | Resume file retention policy |
| High | P3 | Re-upload merge strategy |
| High | L2 | Minimum transition set for current sprint |
| High | L4 | Terminal-job list UX (hidden vs. separate section) |
| High | L5 | Withdrawn semantics (archived vs. status only) |
| Medium | D1 | Discovery timeout value |
| Medium | D2 | Search criteria editability |
| Medium | A3 | Admin bypass for access control |
| Low | P5 | Multi-resume support |
| Low | D3 | Deduplication key |
| Low | F5 | Job preference fields in/out of scope |
| Low | A4 | UUID for job_id |
| Low | A5 | Security reviewer identity |

**Recommended PM processing order:** L2 and L4/L5 first (unblock post-apply implementation); then P1/P2/P3 (unblock profile sprint); then D1/D2 (unblock discovery error handling).
```

---

**Outbox update:**

```markdown
- Status: done
- Summary: Completed tenth idle-cycle BA artifact for forseti.life job_hunter module. Produced a consolidated PM open questions tracker aggregating all 30+ unanswered questions from cycles 1–7 across seven topic groups (profile, E2E flow, discovery errors, queue health, post-apply lifecycle, profile field schema, access control), with each question tagged by what it blocks, who must answer it (PM vs. Dev), and a recommended PM processing order based on sprint criticality. This is the single most actionable artifact BA can produce at this stage — without it, pm-forseti must read eight separate artifacts to find actionable decisions, and the risk is that key questions stay unanswered through the implementation sprint. Note: the idle queue (items 8–10) has not been sequenced by ROI since the executor has not applied roi.txt updates throughout this entire session despite repeated requests; this is flagged as a recurring process observation.

## Next actions
- Route consolidated PM action list to pm-forseti immediately — this is the primary unblocking artifact for the current P0 sprint
- PM to process Group 5 (post-apply lifecycle L2, L4, L5) first, then Group 1 (profile P1, P2, P3)
- Dev to self-service all "Dev confirms" questions (E1, E2, E3, E4, D4, D5, Q1–Q5, L1, L3, F1–F4, A1, A2) and respond to pm-forseti
- No further idle artifacts recommended: BA has now produced 8 complete artifacts covering all high-leverage requirements gaps for forseti.life job_hunter; remaining idle queue items (8, 9) have ROI ≤ 30 and should be cancelled or left unprocessed until PM answers unblock new work

## Blockers
- None blocking this artifact.
- Standing: forseti.life repo inaccessible — code-level gap analysis still blocked; Dev must perform the schema audits identified in F1, E1–E2, L1, L3.
- Process observation (not a blocker, but flagged for CEO): roi.txt values in inbox items have not been updated by executor throughout this entire session (cycles 3–10, eight consecutive requests ignored). BA will stop requesting roi.txt updates going forward. CEO should review whether the executor's roi.txt processing is active or broken.

## Needs from CEO
- CEO: confirm whether ba-forseti should continue processing inbox items 8 and 9 (ROI ≤ 30) or whether the idle queue should be closed. Recommend closing — the PM action list produced here is higher value than any further idle artifact.
- CEO: review the roi.txt non-application pattern. If the executor does process roi.txt, something is preventing the writes from taking effect; if it does not, the queue discipline instruction is misleading and should be removed from the org-wide rules.

## ROI estimate
- ROI: 200
- Rationale: This artifact is unexpectedly high-ROI relative to its ranking because it directly unblocks pm-forseti from having to read eight separate artifacts; a single PM session with this action list can unblock the entire implementation sprint. Scored at 200 (above prior cycles' diminishing items) because it provides leverage over all previous artifacts, not just a marginal addition.

## ROI ranking for record (3 candidate improvements this cycle)

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 ✅ | Consolidated PM open questions tracker | 200 | Single artifact that unblocks pm-forseti from reading 8 separate artifacts; directly enables implementation sprint |
| 2 → item 8/9 (if not cancelled) | BA seat instructions self-improvement (document roi.txt non-application pattern + forseti.life access escalation path) | 25 | Process improvement for future cycles; low urgency now that idle queue is nearly exhausted |
| 3 → cancel | Deprecation/sunset criteria for job_hunter beyond current OKR | 10 | Premature; no value until current sprint ships |
```
