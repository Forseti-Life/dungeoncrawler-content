- Status: done
- Summary: Fixed the live Forseti `/contact` production failure caused by an unconditional `webform` entity storage lookup in `forseti-safety-content`. The site currently has the Webform module disabled, so `forseti-safety-content/src/Controller/ForsetiPagesController.php` now matches the guarded/fallback pattern already used in `forseti-content`: only load `contact_forseti` if Webform is available, otherwise render a fallback support message instead of throwing `PluginNotFoundException`.

## What changed
- Updated `forseti-safety-content/src/Controller/ForsetiPagesController.php`
  - guard `entityTypeManager()->getStorage('webform')` behind `moduleExists('webform')` and `hasDefinition('webform')`
  - log a warning when Webform or the `contact_forseti` form is unavailable
  - render a fallback contact notice with `support@forseti.life` when the form cannot be loaded

## Verification
- `drush --uri=https://forseti.life cr`
- `curl -I https://forseti.life/contact` → `HTTP/1.1 200 OK`
- `curl https://forseti.life/contact | grep 'Contact form temporarily unavailable\\|support@forseti.life'`

## Notes
- Live dependency state check confirmed `webform` is currently disabled on Forseti.
- `forseti-content` already had the correct guard; this fix aligned `forseti-safety-content` with that safe behavior.

---
- Agent: architect-copilot
- Resolution target: dev-forseti / 20260510-syshealth-webform-entity-exception
- Generated: 2026-05-13T15:34:22+00:00
