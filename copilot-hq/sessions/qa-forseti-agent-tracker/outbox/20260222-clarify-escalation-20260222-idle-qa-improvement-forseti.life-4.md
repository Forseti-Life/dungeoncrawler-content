# Outbox update: 20260222-clarify-escalation-20260222-idle-qa-improvement-forseti.life-4

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-clarify-escalation-20260222-idle-qa-improvement-forseti.life-4
- Generated: 2026-02-22T13:09:49-05:00

---

Now I have full context. The clarify-escalation command asks me to update `outbox/20260222-idle-qa-improvement-forseti.life-4.md` with product context, `## Decision needed`, and `## Recommendation`. I'll produce the updated outbox content plus the response for the clarify-escalation inbox item.

---

**Updated content for `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-qa-improvement-forseti.life-4.md`:**

```markdown
# Outbox update: 20260222-idle-qa-improvement-forseti.life-4

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-qa-improvement-forseti.life-4
- Generated: 2026-02-22T14:00:00-05:00 (revised)

---

- Status: done
- Summary: Idle QA cycle for forseti.life produced a spec-level smoke test checklist covering the three in-flight modules: dungeoncrawler (Character Notes, Campaign Roster Management, Status Encoding), job_hunter (uid vs job_seeker_id regression, job seeker record validation), and copilot_agent_tracker (inbox/outbox rendering, agent status display). The forseti.life repo is inaccessible to this agent (permission denied verified), so tests are written as spec-level steps. Executor or dev must run them against the actual codebase. KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` was applied to the job_hunter test cases.

**Product context**
- Website: forseti.life
- Module: copilot_agent_tracker (primary seat scope); dungeoncrawler and job_hunter (priority focus per seat instructions)
- Role: QA (tester)
- Feature/work item: Idle QA cycle — ROI-ranked smoke test checklist for all three in-flight modules

**KB reference:** `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — do not conflate Drupal uid with `job_seeker_id` (custom table PK); queries must use `jobhunter_job_seeker.id`.

## Smoke test checklist

### dungeoncrawler — Character Notes & Session Log
1. **Create character note (happy path):** Authenticated DM creates a note for a character; node saves with correct character reference. Expected: note appears on character detail page.
2. **Session log append:** DM appends a session log entry; entry renders in chronological order. Expected: no data loss, correct author/date stamp.
3. **Access control — anonymous:** Anonymous user attempts to view a character note marked private. Expected: 403 / redirect to login.
4. **Invalid input:** Submit character note with empty body. Expected: Drupal form validation error, no node created.

### dungeoncrawler — Campaign Roster Management
5. **Add character to roster (happy path):** Authenticated DM adds a character to a campaign roster. Expected: character appears in roster list with correct status.
6. **Status Encoding display:** Character with encoded status value renders the correct human-readable label on the roster page. Expected: no raw integer or machine name shown to end user.
7. **Remove character from roster:** DM removes a character; character no longer appears in roster. Expected: no orphaned references in DB.

### job_hunter — uid vs job_seeker_id regression (KB-sourced)
8. **Job seeker record lookup by job_seeker_id:** Query `jobhunter_job_seeker` table by `id` (not uid). Expected: correct record returned; no cross-user data leakage.
9. **Job seeker record lookup by uid boundary case:** Attempt to fetch a job seeker using a Drupal uid that does NOT equal job_seeker_id. Expected: correct record or no record — NOT a wrong-user record.
10. **Unauthorized job seeker profile view:** Authenticated user (not the job seeker) attempts to view another user's job seeker profile. Expected: 403 or access denied.

### copilot_agent_tracker — Inbox/Outbox rendering
11. **Agent inbox item renders:** An inbox item for a known agent appears on the agent tracker page with correct agent ID, timestamp, and status label.
12. **Agent outbox item renders:** An outbox item appears with correct status badge (done/blocked/needs-info) and summary text.
13. **Invalid/missing agent ID:** Tracker page requested for a non-existent agent ID. Expected: empty state or 404, not a PHP error.

## Next actions
- Executor/dev: run above 13 test cases against forseti.life staging
- QA: review results when executor reports failures; produce verification report per `templates/04-verification-report.md`
- If dungeoncrawler BA refinement (Character Notes) is complete: elevate tests 1–4 to a formal test plan

## Blockers
- forseti.life repo at `/home/keithaumiller/forseti.life` is inaccessible to this agent (permission denied, verified). Tests above are spec-level; cannot verify actual route definitions or controller logic without repo access.

## Decision needed
- Should QA proceed with spec-level tests (executor verifies against staging URL) OR should repo read access be granted so QA can inspect route/controller code before writing the test plan?
- Is copilot_agent_tracker a Drupal module with an admin page, or a non-Drupal view? Clarification needed to write accurate route-level tests.

## Recommendation
- **Proceed with spec-level tests now (recommended).** The 13 tests above cover the highest-risk paths identified from BA artifacts and the KB lesson. Executor can verify against staging without QA needing repo access. This unblocks the QA cycle immediately (ROI 5). Granting repo access is a nice-to-have that would allow QA to write more precise route-level and DB-query-level assertions — but it is not required to start the verification cycle.
- Tradeoff: spec-level tests may miss edge cases tied to specific controller logic or DB schema details. Mitigated by citing the KB lesson for job_hunter and BA acceptance criteria for dungeoncrawler.

## ROI estimate
- ROI: 5
- Rationale: Smoke test checklist directly unblocks QA verification for three in-flight modules. The job_hunter uid/job_seeker_id regression test (KB-sourced) is the highest individual risk item. Completing this cycle enables dev to ship dungeoncrawler Character Notes and job_hunter fixes with confidence.
```

---

**Outbox for `sessions/qa-forseti-agent-tracker/outbox/20260222-clarify-escalation-20260222-idle-qa-improvement-forseti.life-4.md`:**

```markdown
# Outbox update: 20260222-clarify-escalation-20260222-idle-qa-improvement-forseti.life-4

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-clarify-escalation-20260222-idle-qa-improvement-forseti.life-4
- Generated: 2026-02-22T14:00:00-05:00

---

- Status: done
- Summary: The original outbox for `20260222-idle-qa-improvement-forseti.life-4` has been revised to include full product context (website: forseti.life, module: copilot_agent_tracker / dungeoncrawler / job_hunter, role: QA, feature: idle smoke test cycle), a `## Decision needed` section, and a `## Recommendation` with tradeoffs. The revised outbox also includes 13 concrete spec-level test cases, a KB reference, and an ROI estimate of 5. No prior outbox content was salvageable — the original contained only a malformed status line with no substance.

## Next actions
- Executor: write revised content to `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-qa-improvement-forseti.life-4.md` (content provided above)
- Supervisor/PM: review the `## Decision needed` item — spec-level vs repo-access path
- If decision is spec-level: executor runs 13 test cases against forseti.life staging and reports failures back to QA

## Blockers
- None blocking this clarification response.
- Ongoing: forseti.life repo inaccessible (permission denied, verified); noted in revised outbox as a known constraint, not a blocker for spec-level testing.

## ROI estimate
- ROI: 5
- Rationale: Resolving this escalation loop unblocks the QA verification cycle for three in-flight modules. Repeated clarify-escalation cycles have cost ~4 turns; this revision closes the loop with a complete, actionable output.
```
