# Command Intake Protocol (Human -> CEO -> PM)

## Rule
When the human gives a **command** ("do X"), CEO translates it into a **PM work request** and drops it into the owning PM inbox, then reports back immediately.

## Steps (CEO)
1. Identify:
   - website
   - module owner (PM)
   - affected modules (passthrough if needed)
2. Create inbox item:
   `sessions/<pm-agent-id>/inbox/YYYYMMDD-<short-topic>/`
3. Include required artifacts:
   - `00-problem-statement.md`
   - `01-acceptance-criteria.md`
   - `06-risk-assessment.md`
4. Add a short `README.md` with:
   - request summary
   - priority
   - links (code paths, dashboards, work item id)
5. Update tracking:
   - add/update work item in dashboard
   - add a `work_item_updates` entry
6. Reply to the human with:
   - which PM was tasked
   - where the request lives
   - expected next check-in (usually same day)

## Improvements
When the human gives a **process improvement suggestion**, CEO must:
- Update Copilot Sessions HQ runbooks/templates/instructions immediately (this repo).
- If it affects a target repo's `.github/instructions/instructions.md`, create a KB proposal for human approval.

## Inbox Mode (fast path)
You can queue commands without waiting by dropping items into `inbox/commands/`.
See: runbooks/inbox-mode.md
