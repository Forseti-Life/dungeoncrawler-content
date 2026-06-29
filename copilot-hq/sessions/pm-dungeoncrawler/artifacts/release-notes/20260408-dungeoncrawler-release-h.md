# Release Notes: 20260408-dungeoncrawler-release-h

- Release: 20260408-dungeoncrawler-release-h
- Site: dungeoncrawler
- Opened: 2026-04-08T19:28:33+00:00
- Closed: 2026-04-08 (auto-close trigger)
- Features shipped: 0
- Features deferred: 14

## Summary

Release-h was auto-closed by the release-close-now trigger: 14 features were activated in scope, exceeding the 10-feature auto-close threshold (cap overflow). No features reached Gate 2 APPROVE before the close fired — dev implementation was in progress for dc-apg-class-expansions only (commits `76e6c627f`, `b4ab1348b`), and no QA Gate 2 verification had been filed for any release-h feature.

Per close policy: features without Gate 2 APPROVE are deferred (Status: ready, Release field cleared). Release-h closes empty.

## Deferred features (all 14 → Status: ready for release-i)

| Feature | Notes |
|---|---|
| dc-apg-ancestries | Deferred — no dev completion |
| dc-apg-archetypes | Deferred — no dev completion |
| dc-apg-class-expansions | Deferred — dev done (commits `76e6c627f`, `b4ab1348b`), no Gate 2 APPROVE |
| dc-apg-class-investigator | Deferred — no dev completion |
| dc-apg-class-oracle | Deferred — no dev completion |
| dc-apg-class-swashbuckler | Deferred — no dev completion |
| dc-apg-class-witch | Deferred — no dev completion |
| dc-apg-equipment | Deferred — no dev completion |
| dc-apg-feats | Deferred — no dev completion |
| dc-apg-focus-spells | Deferred — no dev completion |
| dc-apg-rituals | Deferred — no dev completion |
| dc-apg-spells | Deferred — no dev completion |
| dc-cr-animal-companion | Deferred — no dev completion |
| dc-cr-class-alchemist | Deferred — no dev completion |

## Root cause

The 10-feature auto-close threshold was hit immediately after batch 2 activation (6 features from a prior session + 8 features in the current session = 14 total), triggering auto-close before dev/QA could proceed. This is expected behavior per org-wide release policy.

## Recommended release-i priority

dc-apg-class-expansions should be first in release-i scope as it already has a dev implementation. QA Gate 2 verification can proceed immediately upon activation.
