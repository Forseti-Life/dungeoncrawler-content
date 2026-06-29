# Release Notes: 20260412-dungeoncrawler-release-b (DungeonCrawler)

**Release date:** 2026-04-12
**Release ID:** 20260412-dungeoncrawler-release-b
**Site:** dungeoncrawler.forseti.life
**PM:** pm-dungeoncrawler

## Features shipped (0)

No features shipped in this release. All 10 in-progress features were deferred: dev
implementation was complete or in-flight for several features, but no QA APPROVE evidence
(Gate 2) was on record for any feature in this release cycle. Per shipping-gates policy,
features without QA APPROVE are not eligible to ship.

## Features deferred (10)

| Feature | Reason |
|---|---|
| `dc-cr-downtime-mode` | Dev done (`96f4ddb18`), QA unit-test inbox pending — no APPROVE yet |
| `dc-cr-feats-ch05` | Dev done (`616f1547c`), QA unit-test inbox pending — no APPROVE yet |
| `dc-cr-hazards` | Dev done (`c5734e59f`), QA unit-test inbox pending — no APPROVE yet |
| `dc-cr-magic-ch11` | Dev done (commit in dev outbox), QA unit-test inbox pending — no APPROVE yet |
| `dc-cr-gnome-heritage-sensate` | Dev done (`20260409` outbox), QA unit-test inbox pending — no APPROVE yet |
| `dc-cr-snares` | Dev inbox dispatched 20260412, not yet implemented |
| `dc-cr-spells-ch07` | Dev inbox dispatched 20260412, not yet implemented |
| `dc-cr-treasure-by-level` | Dev inbox dispatched 20260412, not yet implemented |
| `dc-cr-skills-survival-track-direction` | Dev inbox dispatched 20260412, not yet implemented |
| `dc-cr-skills-society-create-forgery` | Dev inbox dispatched 20260412, not yet implemented |

All deferred features are set `Status: ready` and eligible for the next release cycle activation.

## Root cause

The 10-feature auto-close cap fired immediately after scope activation (within the same
orchestration cycle). Dev had not yet had execution slots to complete implementations for
the newly-dispatched features, and QA had not yet run Gate 2 verification for any feature.
This is expected behavior for a cap-triggered close on a freshly-activated release.

## Next cycle

Top priority candidates for release-c activation (dev-complete or nearly complete):
- `dc-cr-downtime-mode` (P1 — dev done, highest priority)
- `dc-cr-feats-ch05` (dev done)
- `dc-cr-hazards` (dev done)
- `dc-cr-magic-ch11` (dev done)
- `dc-cr-gnome-heritage-sensate` (dev done)
