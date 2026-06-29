# Post-push steps: release cohort

The release deploy was triggered automatically for this dependency cohort.

## Releases shipped
  - dungeoncrawler: `20260412-dungeoncrawler-release-x`

## 1. Wait for deploy workflow to finish
```bash
gh run list --repo keithaumiller/forseti.life --workflow deploy.yml --limit 3
```

## 2. Complete product-specific post-push checks
Run the appropriate config import / smoke checks for the sites in this cohort.

Canonical release id: `20260412-dungeoncrawler-release-x`
