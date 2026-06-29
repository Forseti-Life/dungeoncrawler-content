# Deployment (Production)

## Auto-deploy (GitHub Actions)

Production deploys run automatically on pushes to `main` that touch:
- `sites/forseti/web/modules/custom/**`
- `sites/forseti/web/themes/custom/**`
- `sites/forseti/composer.json` / `sites/forseti/composer.lock`
- `sites/dungeoncrawler/**` equivalents
- `h3-geolocation/**`
- `script/production/**`

Workflow: [.github/workflows/deploy.yml](../../../.github/workflows/deploy.yml)

### What the workflow does (Forseti + Dungeoncrawler)

- Sparse-clones the repo on the production host
- `rsync`s custom modules/themes into `/var/www/html/<site>/web/{modules,themes}/custom/`
- Runs `composer install` only if `composer.json` changed
- Runs `drush updatedb -y` when modules/composer changed
- Runs `drush cache:rebuild` when modules/composer/config changed
- Fixes ownership/perms
- Performs basic HTTPS health checks

### Important note: config deployment is disabled

`config/sync` is intentionally not auto-deployed/imported by the workflow. Follow the manual config workflow in [script/production/README.md](../../../script/production/README.md).

## Manual deploy (if needed)

```bash
ssh root@<prod-host>
cd /var/www/html/forseti
git pull origin main
sudo -u www-data ./vendor/bin/drush updatedb -y
sudo -u www-data ./vendor/bin/drush cache:rebuild
```

## Post-deploy verification (Copilot Agent Tracker dashboards)

Recent changes live under:
- `sites/forseti/web/modules/custom/copilot_agent_tracker/**`

After a deploy (auto or manual), verify as an admin:

1) Waiting on Keith page loads and queue looks right:
- `/admin/reports/waitingonkeith`
- Paused seats do **not** appear in the Agents queue table.

2) Release Notes page loads:
- `/admin/reports/copilot-agent-tracker/releases`

3) If a new route/menu link does not appear, run:
```bash
sudo -u www-data ./vendor/bin/drush cache:rebuild
```

## HQ → Drupal data dependency

These dashboards depend on HQ publishing metadata into Drupal. If the page loads but looks empty/stale, run from the HQ repo:

```bash
cd /home/keithaumiller/copilot-sessions-hq
./scripts/publish-forseti-agent-tracker.sh
```

## Rollback

- `git revert <bad-commit>` on `main`, push, and let the auto-deploy run.
- If needed, run `drush cache:rebuild` after rollback.
