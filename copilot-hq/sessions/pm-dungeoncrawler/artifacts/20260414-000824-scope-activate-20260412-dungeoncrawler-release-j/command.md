# Command: scope-activate

- Release: 20260412-dungeoncrawler-release-j
- Site: dungeoncrawler
- Trigger: coordinated push complete (dungeoncrawler-release-i + forseti-release-h shipped)

Activate the backlog features for dungeoncrawler-release-j. Run `scripts/pm-scope-activate.sh dungeoncrawler` to select and activate the next batch of features for the new release cycle.

## Acceptance criteria
- Features selected and set to `in_progress` in `features/*/feature.md`
- Dev/QA inbox items dispatched for each activated feature
- Status: done outbox filed
