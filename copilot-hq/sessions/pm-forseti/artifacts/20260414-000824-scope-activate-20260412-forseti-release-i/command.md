# Command: scope-activate

- Release: 20260412-forseti-release-i
- Site: forseti.life
- Trigger: coordinated push complete (forseti-release-h + dungeoncrawler-release-i shipped)

Activate the backlog features for forseti-release-i. Run `scripts/pm-scope-activate.sh forseti` to select and activate the next batch of features for the new release cycle.

## Acceptance criteria
- Features selected and set to `in_progress` in `features/*/feature.md`
- Dev/QA inbox items dispatched for each activated feature
- Status: done outbox filed
