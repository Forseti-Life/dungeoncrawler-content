Now I understand the schema. `drupal_web_root` lives in `site_audit.drupal_web_root`. I need to add a startup validation block in `suggestion-intake.sh` — after team lookup but before the main processing — that reads `drupal_web_root`, verifies it exists, and exits 1 with an error + writes to `tmp/config-validation-failures/` if not.

The existing `resolve_drupal_root()` resolves the *Drupal root* (one level up from `web/`). I need to validate `drupal_web_root` explicitly as specified.

Now I'll add the `drupal_web_root` validation block right after the `resolve_drupal_root` call and before the drush check:

Now verify the AC manually — set a bad web root for dungeoncrawler, run, expect exit 1 + error message:

Both ACs verified: exit 1 + error to stderr + failure artifact written. Now commit:

---

- Status: done
- Summary: Added `drupal_web_root` startup validation to `scripts/suggestion-intake.sh` (GAP-DC-RB-IR-02). After resolving the Drupal root, the script now reads `site_audit.drupal_web_root` from `product-teams.json` for the target site and runs `test -d` before any processing. If the path does not exist, it prints `ERROR: drupal_web_root not reachable: <path>` to stderr with a remediation hint, writes a failure artifact to `tmp/config-validation-failures/<timestamp>-<site>.txt`, and exits 1. This prevents silent failures for an entire release cycle (root cause: dungeoncrawler release-b had dev path set instead of prod path). Both acceptance criteria verified manually. Committed `a64db4e4c`.

## Next actions
- None. Fix complete and verified.

## Blockers
- None.

## ROI estimate
- ROI: 20
- Rationale: Prevents silent release-cycle failures from misconfigured `drupal_web_root`; one cycle of silent breakage cost pm-dungeoncrawler mid-cycle self-diagnosis and manual remediation.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260408-dc-rb-ir-drupal-web-root-validation
- Generated: 2026-04-08T07:10:16+00:00
