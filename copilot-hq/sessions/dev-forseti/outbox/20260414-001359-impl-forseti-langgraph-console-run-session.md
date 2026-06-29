Now I have a complete picture. Let me implement all four AC gaps in one pass:

Now add the warning banner and Session Health (AC-5) to the `run()` page, and fix AC-1 empty-state message:

Now fix `subRunStreamEvents()` — add 120-char result summary column (AC-2) and fix empty-state (AC-2):

Now fix `subRunResumeRetry()` — add individual blocked items from outbox scan (AC-3):

Now fix `subRunConcurrency()` — add AC-4 empty-state when `pick_agents` absent:

Now run `drush cr` and verify:

---

- Status: done
- Summary: Implemented all open AC gaps for forseti-langgraph-console-run-session. AC-2 (stream events): added 120-char result summary column and fixed empty-state wording. AC-3 (resume/retry): added individual blocked-item detail table using the correct outbox glob scan (`sessions/*/outbox/*.md`, most-recent per seat, `Status: blocked|needs-info` pattern). AC-4 (concurrency): added empty-state for when `pick_agents` key is absent. AC-5 (session health): added Session Health section to `run()` page displaying tick sequence (derived as JSONL line count), provider, parity badge, and last tick timestamp. AC-7 (COPILOT_HQ_ROOT): added `hqRootWarning()` helper and yellow banner on `run()` page when env var is not set. AC-1 empty-state wording corrected. Cache rebuilt and controller verified via drush.

## Next actions
- QA (qa-forseti) to run test plan against `/admin/copilot/console/run` and all `run/*` subsections

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: Closes all AC gaps on an in-release feature, unblocking QA Gate 2. The blocked-item detail scan (AC-3) directly surfaces operational blockers to operators in the console UI.

**Commit:** `751074a14`

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260414-001359-impl-forseti-langgraph-console-run-session
- Generated: 2026-04-14T00:46:24+00:00
