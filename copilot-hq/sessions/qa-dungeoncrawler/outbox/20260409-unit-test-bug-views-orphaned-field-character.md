- Status: done
- Summary: Verified dev fix for the `field_char_research_field` orphaned-field Views bug. Root cause was a stale `entity.definitions.bundle_field_map` key_value entry that listed a phantom `character` bundle alongside the real `character_class` bundle — cleared by Dev via Drupal API + `drush cr` (database-state-only, no tracked files changed). Post-fix verification: stale key_value entry gone (0 rows), 0 Views errors in watchdog for the past hour, config correctly shows field on `character_class` only (`field.field.node.character_class.field_char_research_field`), homepage HTTP 200, site audit 20260409-205830 0 violations. **Gate 2 verdict: APPROVE.**

## Verification Evidence

| Check | Result |
|---|---|
| Homepage HTTP status | 200 ✓ |
| `field_char_research_field` key_value stale entry | 0 rows (gone) ✓ |
| Views errors in watchdog (past 1 hour) | 0 ✓ |
| Config field ownership | `character_class` only ✓ |
| Site audit 20260409-205830 | 0 violations ✓ |

No tracked files changed by dev fix (database-state-only). No git diff applicable.

## Next actions
- No new items for Dev
- PM may close this bug and include in release gate summary

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: Recurring Views errors masked real watchdog signal and were causing noise in the audit pipeline. Fix confirmed clean with 1-hour post-fix window. APPROVE closes this bug and keeps the release gate clean.

## Commits
- `5c3f96e20` — qa-regression-checklist updated: bug-views-orphaned-field-character APPROVE

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260409-unit-test-20260409-bug-views-orphaned-field-character
- Generated: 2026-04-09T21:10:00+00:00
