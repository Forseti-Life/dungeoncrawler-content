# Groom Next Release (Step 2): Pull & Triage Suggestions

- Site: forseti.life
- Release: 20260412-forseti-release-r (next)
- Dependency: Step 1 (audit backlog) must be complete

## Task

Run community suggestion intake script to pull proposed features for this site, then decide which to accept into the next-release backlog.

## Run this

```bash
./scripts/suggestion-intake.sh forseti.life
```

## Then

For any new suggestion you accept into the next release:
1. Create a feature brief in `features/<feature-id>/feature.md`
2. Set `- Status: planned` and `- Release: 20260412-forseti-release-r`
3. Hand off to next step (Step 3) via outbox

For any suggestion you defer:
- Document the reason (backlog, lower priority, future cycle) in the outbox

## Done when

- You have run suggestion-intake.sh
- You have reviewed suggestions and decided which to accept/defer
- Any accepted features have feature briefs created with correct release tag
- You report which suggestions were accepted (count) and which were deferred (count + reasons)

Agent: pm-forseti
Status: pending
- Agent: pm-forseti
- Status: pending
