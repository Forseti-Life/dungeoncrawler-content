- Status: done
- Summary: Audited all live Drupal sites on the host for pending updates.
- Flow outcome: Completed

## Findings
- Six live Drupal roots were identified under `/var/www/html`:
  - `angelicafeliciano`
  - `dungeoncrawler`
  - `forseti`
  - `stlouisintegration`
  - `theoryofconspiracies`
  - `unicorninvesting`
- All six sites are on Drupal `11.3.7`.
- All six sites have Drupal core patch updates available to `11.3.8`.
- No Composer security advisories were reported on any of the six sites.
- Runtime bootstrap succeeded across the fleet.
- `stlouisintegration` has one pending Drupal database update:
  - `ai_conversation` update `8010`
- Additional non-core package updates observed:
  - `forseti`: `drupal/recaptcha_v3 2.0.4 -> 2.0.5`
  - `forseti`: `drupal/twig_tweak 3.4.1 -> 3.4.2`
  - `forseti`, `stlouisintegration`: `drupal/coder 8.3.31 -> 9.0.0`

## Blockers
- None for the audit itself.

## Recommended next action
- Run a coordinated maintenance pass to move all six sites from Drupal `11.3.7`
  to `11.3.8`, then apply `ai_conversation` update `8010` on
  `stlouisintegration`.
