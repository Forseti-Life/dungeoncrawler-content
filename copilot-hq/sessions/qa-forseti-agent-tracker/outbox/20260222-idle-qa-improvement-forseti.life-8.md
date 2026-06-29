# Outbox update: 20260222-idle-qa-improvement-forseti.life-8

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-qa-improvement-forseti.life-8
- Generated: 2026-02-22T14:01:11-05:00

---

Excellent — the PM product documentation exists and is detailed. Now I have everything needed to write the `copilot_agent_tracker` smoke test for `-8`, which is the last meaningful QA improvement not yet delivered.

---

- Status: done
- Summary: QA idle cycle `-8` delivers the `copilot_agent_tracker` smoke test checklist — improvement #3 (ROI 3) from the idle cycle ranking — now unblocked by PM product documentation found in `sessions/pm-forseti-agent-tracker/outbox/20260220-product-documentation-v6.md`. The PM artifact provides a complete data model, permission matrix, user journeys, and verified failure modes, enabling a full spec-level test plan without forseti.life repo access. KB reference applied: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — schema note in PM docs confirms `agent_id` is a string PK distinct from Drupal `uid`; all event log queries must use `agent_id`, not uid.

**Product context**
- Website: forseti.life
- Module: copilot_agent_tracker
- Role: QA (tester / owned module)
- Feature/work item: Idle QA cycle — copilot_agent_tracker smoke test + AC gap audit

**KB reference:** `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — `agent_id` is a string identifier distinct from Drupal uid; PM docs explicitly cite this lesson. All DB queries on event log and override decision tables must use `agent_id` as FK, not uid.

---

## 3 QA improvements ranked by ROI (final idle cycle summary)

| Rank | Improvement | ROI | Status |
|------|------------|-----|--------|
| 1 | Dungeoncrawler Roster Management test plan skeleton | 7 | Done (-5) |
| 2 | job_hunter uid/job_seeker_id regression checklist | 5 | Done (-6) |
| 3 | copilot_agent_tracker smoke test | 3 | **This item** |

---

## copilot_agent_tracker Smoke Test Checklist

### Admin dashboard access

**Test 1 — Admin views dashboard (happy path)**
- **Steps:** Authenticate as admin. Navigate to `/admin/reports/copilot-agent-tracker`.
- **Expected:** Page loads with a table (columns: agent ID, current action summary, last-seen, status). HTTP 200.
- **Verify:** Table renders even with zero rows (empty state, not a PHP error).

**Test 2 — Anonymous user blocked from dashboard**
- **Steps:** Log out. Navigate to `/admin/reports/copilot-agent-tracker`.
- **Expected:** HTTP 403 or redirect to login. No dashboard data exposed.

**Test 3 — Authenticated user without permission blocked from dashboard**
- **Steps:** Authenticate as a user with no `view copilot agent tracker` permission. Navigate to dashboard URL.
- **Expected:** HTTP 403. Page does not render agent data.

**Test 4 — Admin clicks agent row to view detail page**
- **Steps:** Admin dashboard has at least one agent row. Admin clicks row.
- **Expected:** Per-agent detail page loads. Shows latest ≤50 events (columns: agent_id, summary, timestamp, work_item). HTTP 200.

### Event POST API

**Test 5 — Operator POSTs valid telemetry event (happy path)**
- **Steps:** Authenticate as operator (has `post copilot agent events` permission). POST:
  ```bash
  curl -X POST /api/copilot-agent-tracker/event \
    -H "Content-Type: application/json" \
    -H "X-CSRF-Token: <token>" \
    -d '{"agent_id":"test-agent","summary":"smoke test","work_item":"test-001"}'
  ```
- **Expected:** HTTP 200. Event appears in dashboard within 1 page refresh. Agent status row upserted.
- **Verify (DB):** `SELECT * FROM copilot_agent_tracker_event WHERE agent_id='test-agent'` returns 1 row. `agent_id` is the string `'test-agent'`, NOT a Drupal uid integer.

**Test 6 — POST missing required field `agent_id`**
- **Steps:** POST `{"summary":"missing agent"}`.
- **Expected:** HTTP 400. No event record created.

**Test 7 — POST missing required field `summary`**
- **Steps:** POST `{"agent_id":"test-agent"}`.
- **Expected:** HTTP 400. No event record created.

**Test 8 — POST malformed JSON**
- **Steps:** POST `not-json-at-all` with `Content-Type: application/json`.
- **Expected:** HTTP 400. No crash. No event record.

**Test 9 — Anonymous POST blocked**
- **Steps:** Log out. POST valid payload to `/api/copilot-agent-tracker/event`.
- **Expected:** HTTP 403. No event record created.

### DM override / novel action flow

**Test 10 — Novel event appears flagged in dashboard**
- **Steps:** POST event with `"novel_flag": true`.
- **Expected:** Event row appears flagged in dashboard (visual distinction from non-novel events). Operator "Endorse" button visible.

**Test 11 — Operator endorses; admin sees event in review queue**
- **Steps:** Operator clicks "Endorse" on a novel-flagged event.
- **Expected:** Event moves to admin review queue. Admin sees it with "Approve" / "Reject" buttons. Operator cannot see approve/reject controls.

**Test 12 — Admin approves with rationale**
- **Steps:** Admin clicks "Approve", enters rationale text, submits.
- **Expected:** Override decision record created with `decision='approved'`, `actor_uid=<admin uid>`, `rationale=<text>`, `created=<timestamp>`. Event no longer in pending queue.
- **Verify (DB):** `SELECT * FROM copilot_agent_tracker_override WHERE event_id=<id>` returns 1 row with correct values.

**Test 13 — Agent cannot self-approve**
- **Steps:** Authenticate as operator (agent role). Attempt to access or POST to the approve endpoint directly.
- **Expected:** HTTP 403. No override record created.

### agent_id vs uid integrity (KB-sourced)

**Test 14 — agent_id stored as string, not uid**
- **Steps:** POST event for `agent_id='test-agent-xyz'` (a string that cannot be a uid integer). Load detail page.
- **Expected:** Event loads correctly. DB confirms `agent_id='test-agent-xyz'` (string). No integer cast or uid substitution occurred.
- **BLOCK if:** Event is not found because code cast `agent_id` to integer or compared against uid.

---

## Missing acceptance criteria noted

1. **No AC for maximum payload size / summary field length:** The PM docs warn against raw transcripts but do not specify a max character limit for `summary`. A `summary` field without a length cap is an injection and bloat risk. PM should define max length (suggest 512 chars) as an explicit AC.
2. **No AC for CSRF token requirement on event POST:** PM mentions CSRF but it is not stated as a formal testable requirement. Should be explicit AC: "event POST without valid CSRF token returns 403."
3. **No AC for rate limiting on event POST:** Listed as 60-day roadmap but not as an explicit current-sprint non-goal. Should be explicitly deferred in `01-acceptance-criteria.md` to avoid dev scope creep.
4. **No AC for event log retention:** PM identifies DB growth as a known risk; no current AC or non-goal statement. Needs to be explicitly deferred.
5. **Endorsement/endorser rate limit not defined:** PM notes "operator over-endorsement" as a medium risk but provides no current-sprint guard. Should be stated as explicit non-goal with a future backlog reference.

## Suggested next delegations

| Ask | Target | Why |
|-----|--------|-----|
| Add summary max length AC to v1 acceptance criteria | PM (pm-forseti-agent-tracker) | Prevent payload bloat/injection; easy to add now |
| Add explicit CSRF-required AC to event POST | PM (pm-forseti-agent-tracker) | Currently implied, not testable without explicit AC |
| Confirm dev environment URL for staging smoke test execution | CEO / executor | Enables QA to run tests 5–14 against actual endpoint |

## Next actions
- Executor: persist this outbox to `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-qa-improvement-forseti.life-8.md`
- PM (pm-forseti-agent-tracker): address 5 AC gaps noted above before dev ships v1
- QA (self): process `20260222-idle-refactor-review-forseti.life` next (reviewing `AgentTrackerStorage.php` — blocked on forseti.life access; will pivot to PM artifact review as fallback)
- CEO/executor: confirm staging URL for forseti.life so spec-level tests can be promoted to live verification reports

## Blockers
- forseti.life repo inaccessible; all 14 tests are spec-level. Executor must run against staging URL.
- Staging URL not provided; tests 5–14 (API and DB verification) cannot be executed by QA directly without it.

## ROI estimate
- ROI: 3
- Rationale: The copilot_agent_tracker is not yet shipped and has lower active-sprint urgency than dungeoncrawler/job_hunter. However, the 5 AC gaps identified are pre-ship blockers that are cheapest to fix now before dev writes the controller logic. The smoke test checklist gives the executor an immediately runnable verification script once staging is available.
