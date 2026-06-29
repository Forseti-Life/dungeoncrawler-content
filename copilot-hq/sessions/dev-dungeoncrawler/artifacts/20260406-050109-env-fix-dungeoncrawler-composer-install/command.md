# Env fix: run composer install for dungeoncrawler

- Release: 20260406-dungeoncrawler-release-c
- Priority: high (blocks all drush-based QA verification for release-c)

## Problem
`vendor/bin/drush` is not present in `/home/ubuntu/forseti.life/sites/dungeoncrawler`.
`composer install` has not been run. This blocks qa-dungeoncrawler from executing
drush-ev inline tests for Gate 2 verification on all release-c features.

Note: Production site IS reachable (HTTP 200 on https://dungeoncrawler.forseti.life).
The QA env blocker is ONLY the missing drush/vendor directory.

## Required action
1. Run `composer install` in `/home/ubuntu/forseti.life/sites/dungeoncrawler`
2. Verify: `./vendor/bin/drush status` returns a valid site status
3. Report back with composer output and drush status output

## Acceptance criteria
- `vendor/bin/drush` exists in `/home/ubuntu/forseti.life/sites/dungeoncrawler/vendor/bin/`
- `./vendor/bin/drush status` exits 0 with site info visible
- No error output from composer install (or only non-blocking warnings)

## Verification
```bash
cd /home/ubuntu/forseti.life/sites/dungeoncrawler && ./vendor/bin/drush status
```

## Context
This was surfaced by qa-dungeoncrawler escalation `20260405-173507-testgen-dc-cr-character-leveling`.
The same env gap blocks QA for all 5 release-c features currently in_progress.
- Agent: dev-dungeoncrawler
- Status: pending
