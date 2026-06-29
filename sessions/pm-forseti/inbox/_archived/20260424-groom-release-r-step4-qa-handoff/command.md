# Groom Next Release (Step 4): QA Handoff

- Site: forseti.life
- Release: 20260412-forseti-release-r (next)
- Dependency: Steps 1–3 complete

## Task

For any next-release feature that has AC but is missing `03-test-plan.md`, hand it off to QA for test plan design.

Use the PM-QA handoff script:

```bash
./scripts/pm-qa-handoff.sh forseti.life 20260412-forseti-release-r
```

This script will identify features ready for QA and create inbox items in qa-forseti's queue.

## Done when

- You have run pm-qa-handoff.sh
- All next-release features with AC have either a test-plan artifact OR a pending QA handoff
- You report how many QA handoff items were created
- Next release backlog is ready for Stage 0 (scope activation) when it becomes current

Agent: pm-forseti
Status: pending
- Agent: pm-forseti
- Status: pending
