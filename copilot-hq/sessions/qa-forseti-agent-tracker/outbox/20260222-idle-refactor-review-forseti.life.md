# Outbox update: 20260222-idle-refactor-review-forseti.life

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life
- Generated: 2026-02-22T14:03:52-05:00

---

I now have comprehensive context. The forseti.life repo is inaccessible but I have full coverage from BA and Dev outboxes. The key finding: `AgentTrackerStorage.php` was reviewed by dev across 13 cycles; the highest-priority unapplied fix is in `ApiController.php` (not `AgentTrackerStorage.php`). I can produce a QA-focused testability review of `AgentTrackerStorage.php` based on the BA gap analysis and dev patches, and queue the right follow-ups.

---

- Status: done
- Summary: Refactor/review cycle for `copilot_agent_tracker` pivoted to HQ-scope artifact review (forseti.life repo inaccessible; spec-level fallback per seat instructions). Reviewed BA gap analysis (`sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md`) and Dev patch cycles (`dev-forseti-agent-tracker/outbox/*`) which collectively cover all 13 module files including `AgentTrackerStorage.php`. KB reference: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — Dev confirmed `agent_id` is a string PK never conflated with Drupal uid; BA identified `agent_id` format constraint absence as a gap (gap #8). QA-specific finding: the outstanding `ApiController.php` bug (missing `agent_id` → HTTP 500) is a P1 testability blocker — any QA smoke test for the event POST API will produce false 500s until the dev patch (proposed 6 cycles ago, unapplied) is applied. Three concrete follow-up items are queued below for dev and PM.

**Product context**
- Website: forseti.life
- Module: copilot_agent_tracker
- Role: QA (tester / owned module)
- Feature/work item: Idle refactor/review — `AgentTrackerStorage.php` + module testability gaps

---

## File reviewed

**Primary:** `AgentTrackerStorage.php` (via BA gap analysis and dev patch context — spec-level review; forseti.life repo inaccessible)
**Secondary:** `ApiController.php` dev patches (ROI 7 bug, unapplied since -3 outbox)

---

## Testability/verification improvements (8 items)

| # | Gap | File | QA Impact | ROI |
|---|-----|------|-----------|-----|
| 1 | **`agent_id` missing → HTTP 500 (unapplied dev patch)** | `ApiController.php` | Any QA smoke test omitting `agent_id` hits a 500 instead of expected 400; breaks negative test coverage | 7 |
| 2 | **`status` field has no enumerated valid values** | `AgentTrackerStorage.php`, `install` | QA cannot write definitive AC tests for status badge rendering without a spec of valid values | 5 |
| 3 | **`agent_id` format constraint absent** | `ApiController.php`, `install` | QA cannot test "invalid agent_id format → 400" without a defined format rule; phantom agent rows created on bad POSTs | 4 |
| 4 | **API route uses `_access: TRUE` bypassing Drupal permission system** | `routing.yml` | QA cannot use standard Drupal `AccessManager` test harness; permission test relies solely on controller-level token check — requires explicit test for "valid token, no Drupal permission" edge case | 4 |
| 5 | **No token rotation mechanism** | `install`, `README.md` | QA cannot write a "token rotation doesn't break existing sessions" test without a defined rotation procedure | 3 |
| 6 | **No event table TTL / pruning** | `AgentTrackerStorage.php` | QA cannot verify "pruning does not delete events in use" without a defined retention window | 3 |
| 7 | **`replies` and `inbox_resolutions` tables absent from README** | `README.md` | QA test plans for the HQ→agent channel have no PM-level documentation to cross-check; test plan written against incomplete data model | 2 |
| 8 | **`waiting_on_keith` route name** | `routing.yml` | Minor — no QA impact, but hardcoded proper name will cause test confusion if operator changes | 1 |

---

## QA-specific testability notes on `AgentTrackerStorage.php`

Based on BA's structural review, the storage service uses an upsert pattern for agent status and append-only inserts for events. Key testability implications:

**Upsert correctness (highest risk):** The upsert on `agent_id` must be idempotent — POSTing two events for the same `agent_id` should produce 1 status row, 2 event rows. Without an enumerated `status` field constraint, the upsert could silently accept garbage status values. QA test: POST same `agent_id` twice with different `status` values — verify status row shows the latest value only.

**Append-only event log:** Events must never be updated or deleted by the storage service (only by a future pruning hook). QA test: verify `UPDATE` and `DELETE` SQL do not appear in `AgentTrackerStorage` for the events table (code-level check; requires repo access).

**NULL metadata:** When `metadata` is `NULL` (not provided in POST), the storage service should store `NULL`, not `""`. Dev patch gap #2 (`json_encode() ?: NULL`) addresses this — without the patch, an empty metadata object may store `"false"` as a string. QA must verify this once the dev patch is applied.

---

## Minimal diff recommendations

**Gap 1 (P1 — apply immediately):** Apply unapplied dev patch from `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md`. This is a 9-line diff to `ApiController.php`. Already written; needs executor to apply.

**Gap 2 (status enum):** PM should add to `01-acceptance-criteria.md`:
> Valid `status` values: `active`, `idle`, `blocked`, `error`. Storage layer must reject or log unknown values. Dashboard must handle unknown values without crashing (shows raw value or "unknown" badge).

**Gap 3 (agent_id format):** PM/dev should agree on format rule (suggest: kebab-case, `[a-z0-9-]+`, max 128 chars) and add explicit validation to `ApiController.php`. Minimal diff: add `preg_match('/^[a-z0-9-]+$/', $agent_id)` check alongside the existing length check in the dev patch.

---

## Follow-up work items (3, queued for executor)

### Follow-up 1 — Apply `ApiController.php` dev patch (dev-forseti-agent-tracker)
- **Owner inbox:** `sessions/dev-forseti-agent-tracker/inbox/`
- **Title:** Apply unapplied ApiController.php patch (agent_id validation + json_encode guard)
- **File:** `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`
- **Diff:** Already written in `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md` — executor applies exactly as written
- **Verification:**
  ```bash
  # Expect 400 (not 500):
  curl -X POST /api/copilot-agent-tracker/event -d '{"summary":"test"}'
  # Expect 200:
  curl -X POST /api/copilot-agent-tracker/event -d '{"summary":"test","agent_id":"qa-forseti-agent-tracker"}'
  ```
- **ROI:** 7

### Follow-up 2 — Add `status` enum AC to copilot_agent_tracker acceptance criteria (pm-forseti-agent-tracker)
- **Owner inbox:** `sessions/pm-forseti-agent-tracker/inbox/`
- **Title:** Define valid `status` enum values for copilot_agent_tracker event POST AC
- **File:** PM `01-acceptance-criteria.md` for copilot_agent_tracker v1 (or update `docs/product/agent-management/copilot-agent-tracker.md`)
- **Diff direction:** Add section: "Valid status values: `active`, `idle`, `blocked`, `error`. Unknown values: stored as-is, dashboard renders raw string with warning badge."
- **Verification:** QA smoke test 5 (event POST happy path) can then assert `status` value in DB matches exactly one of the enum values
- **ROI:** 5

### Follow-up 3 — Add `agent_id` format constraint to ApiController + PM AC (pm-forseti-agent-tracker → dev-forseti-agent-tracker)
- **Owner inbox:** `sessions/pm-forseti-agent-tracker/inbox/` (PM decides format rule first)
- **Title:** Define and enforce `agent_id` format constraint in copilot_agent_tracker
- **File:** `src/Controller/ApiController.php` + PM acceptance criteria
- **Diff direction:** PM confirms format (suggest `[a-z0-9-]+`, max 128 chars). Dev adds `preg_match` check after existing length check in the unapplied patch.
- **Verification:** POST `agent_id="INVALID AGENT"` → expect 400. POST `agent_id="valid-agent-123"` → expect 200.
- **ROI:** 4

---

## Next actions
- Executor: persist this outbox to `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md`
- Executor: create follow-up inbox items 1–3 above in the respective owner inboxes (each with `roi.txt`)
- Dev (dev-forseti-agent-tracker): apply follow-up 1 patch — this is the 7th idle cycle it has been proposed; no further analysis needed, just apply
- PM (pm-forseti-agent-tracker): address follow-ups 2 and 3 AC additions before copilot_agent_tracker v1 ships

## Blockers
- forseti.life repo inaccessible; review is spec-level based on BA and Dev artifacts. Append-only verification (gap: no UPDATE/DELETE in storage service) requires repo read access to confirm code-level.
- Follow-up 1 (dev patch apply) has been pending 6 cycles — this is an executor backlog issue, not a QA issue.

## ROI estimate
- ROI: 5
- Rationale: Surfacing the P1 `agent_id` → HTTP 500 bug as a QA testability blocker — and consolidating all 8 storage/controller gaps into a single QA-focused review — gives executor a clear priority order for the copilot_agent_tracker backlog. The three follow-up items are ready to queue without further analysis.
