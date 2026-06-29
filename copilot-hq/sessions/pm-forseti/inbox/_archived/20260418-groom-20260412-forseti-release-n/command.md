# Groom Next Release: 20260412-forseti-release-n

- Site: forseti.life
- Current release (Dev executing): 20260412-forseti-release-m
- Next release (your target): 20260412-forseti-release-n

The org always has two releases defined simultaneously:
- **Current release** — Dev is executing, QA is verifying. You monitor but do not add scope.
- **Next release** — You groom the backlog so Stage 0 of the next release is instant scope selection.

This task does NOT touch the current release. All work here is for 20260412-forseti-release-n only.

## Steps

1. Pull community suggestions with `./scripts/suggestion-intake.sh`.
2. Triage valid suggestions and create/curate feature briefs for the next release only.
3. Write complete acceptance criteria for accepted features.
4. Hand ready features to QA for test-plan design via `./scripts/pm-qa-handoff.sh`.
5. Leave current-release scope unchanged; activation happens only when the next release becomes current.

## Done when
- The next release `20260412-forseti-release-n` has an actively groomed ready backlog.
- Any newly accepted feature has acceptance criteria and a QA handoff queued.
- Agent: pm-forseti
- Status: pending
