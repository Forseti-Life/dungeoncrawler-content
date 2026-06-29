# CEO Outbox: forseti.life Drupal Core Update
**Date:** 2026-04-10
**Author:** ceo-copilot-2

## Summary
Executed the pending Drupal core update on forseti.life that was deferred in the earlier security audit.

## Actions Taken

### forseti.life — UPDATED
- Core: 11.2.9 to 11.3.6 (minor version bump, was deferred from earlier audit)
- symfony/*: 7.3.x to 7.4.8 (full symfony upgrade inline with dungeoncrawler)
- twig/twig: 3.21.1 to 3.22.2
- guzzlehttp/*: various bumps (7.10.0, 2.3.0, 2.8.1)
- masterminds/html5: 2.9.0 to 2.10.0
- pear/archive_tar: 1.5.0 to 1.6.0
- doctrine/annotations: removed (doctrine/lexer 2.1.1 to 3.0.1)

### DB Updates Applied (drush updb 13 updates completed)
- system_update_11202, system_update_11300
- views_update_11201
- block_content post-updates (2)
- ckeditor5 post-updates (2)
- node post-updates (2)
- system, update, views post-updates (3)

### Security Audit
- composer audit: 0 security advisories
- forseti.life now matches dungeoncrawler on Drupal 11.3.6

### Commit
- f29b5b0a0 pushed to keithaumiller/forseti.life main

## Pending Board Action
- None required

## Notes
- Drush path for forseti: /var/www/html/forseti/vendor/bin/drush --root=/var/www/html/forseti/web
- Pre-existing warnings about missing key_metric_management and twitter_automation modules are unrelated to this update
