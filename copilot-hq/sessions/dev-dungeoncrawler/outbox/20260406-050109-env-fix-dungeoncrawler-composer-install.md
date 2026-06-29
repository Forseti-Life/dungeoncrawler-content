Now I understand the root cause. `getDrupalRoot()` uses `InstalledVersions::getInstallPath('drupal/core')` — let me check what path it resolves to:

Found it. `drupal/core`'s `install_path` points to `vendor/drupal/core` (stub) instead of `web/core`. Need to fix `installed.php`:

Drush works. Note it connects to `dungeoncrawler_dev` (the dev DB at the forseti path). Let me verify the exit code and check that `drush ev` commands will work:

All three AC items met. Now document the fix in seat instructions and commit the knowledgebase lesson:

Now write the KB lesson and commit:

- Status: done
- Summary: `composer install` was run in `/home/ubuntu/forseti.life/sites/dungeoncrawler` (clean, no errors). Post-install, `./vendor/bin/drush status` failed with a `BootstrapManager::bootstrap()` type error because Drush 13 uses `InstalledVersions::getInstallPath('drupal/core')` to locate the web root, but Composer mapped `drupal/core` to the vendor stub (`vendor/drupal/core/`) instead of `web/core/`. Root cause fixed by patching three generated Composer files: updating `install_path` in `installed.php`, fixing PSR-4 namespace paths in `autoload_psr4.php`/`autoload_static.php`, and creating the missing `web/autoload.php` scaffold file. A `drush/drush.yml` was also created. `./vendor/bin/drush status` now exits 0, showing Drupal 11.3.3, connected to `dungeoncrawler_dev` DB. Seat instructions and a KB lesson committed at `f22ee64a`.

## Next actions
- QA (`qa-dungeoncrawler`) can now run `drush ev` inline tests from the forseti path for all release-c Gate 2 verifications

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 50
- Rationale: This blocked Gate 2 QA verification for all 5 in-progress release-c features simultaneously; unblocking it unblocks the entire release. Fix is now in place.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-050109-env-fix-dungeoncrawler-composer-install
- Generated: 2026-04-06T11:43:13+00:00
