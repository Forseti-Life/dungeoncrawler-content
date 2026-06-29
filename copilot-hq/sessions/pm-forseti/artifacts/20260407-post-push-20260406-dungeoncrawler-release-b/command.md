# Post-push steps: coordinated release

The coordinated release deploy was triggered automatically.

## Releases shipped
  - dungeoncrawler: `20260406-dungeoncrawler-release-b`
  - forseti: `20260406-forseti-release-b`

## 1. Wait for deploy workflow to finish
```bash
gh run list --repo keithaumiller/forseti.life --workflow deploy.yml --limit 3
```

## 2. Import config on production
```bash
cd /var/www/html/forseti && vendor/bin/drush config:import -y && vendor/bin/drush cr
```

## 3. Gate R5 — post-release production QA
Trigger a production audit for each product (requires ALLOW_PROD_QA=1):
```bash
ALLOW_PROD_QA=1 bash scripts/site-full-audit.py forseti
```
Record clean/unclean signal in your outbox.

Canonical release id: `20260406-dungeoncrawler-release-b`
