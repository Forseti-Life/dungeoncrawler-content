# Post-push steps: forseti release

The forseti deploy for `20260412-forseti-release-r` was triggered automatically after PM signoff.

## 1. Wait for deploy workflow to finish
```bash
gh run list --repo keithaumiller/forseti.life --workflow deploy.yml --limit 3
```

## 2. Production config import / cache rebuild
```bash
cd /home/ubuntu/forseti.life/sites/forseti && vendor/bin/drush config:import -y && vendor/bin/drush cr
```

## 3. Advance this team's release cycle
```bash
bash scripts/post-coordinated-push.sh forseti 20260412-forseti-release-r
```

## 4. Gate R5 — post-release production QA
```bash
ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life
```

Record clean/unclean signal in your outbox.
