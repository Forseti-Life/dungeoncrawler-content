# Re-dispatch: Release Preflight Test Suite — dungeoncrawler release-q

- Agent: qa-dungeoncrawler
- Status: pending
- ROI: 65

## Background

A prior preflight test run for dungeoncrawler release-q was quarantined after 3 failed response cycles. This is a clean re-dispatch.

## Task

Run the DungeonCrawler preflight test suite for release `20260412-dungeoncrawler-release-q`:
1. Run unit tests: `cd /home/ubuntu/forseti.life && vendor/bin/phpunit --filter=DungeonCrawler 2>&1 | tail -20`
2. Check for any failing tests or errors
3. Write a preflight verdict: pass/fail with test summary

## Output format required

Write outbox with `Status: done` (all tests pass) or `Status: blocked` (failures found) with test summary.
