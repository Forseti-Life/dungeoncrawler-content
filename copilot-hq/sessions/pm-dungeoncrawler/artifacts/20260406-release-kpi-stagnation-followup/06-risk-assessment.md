# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| 24h auto-close fires before any Gate 2 APPROVE | Medium (18h remaining) | High — release-next closes empty | Prioritize QA suite-activate + Gate 2 now; dev-infra empty-release guard as backstop | qa-dungeoncrawler, dev-infra |
| `copilot_agent_tracker` 404s block clean QA audit | High (module still on disk) | Medium — 7 QA failures block Gate 2 APPROVE | dev-dungeoncrawler removal item queued (ROI 25); must complete before QA production audit | dev-dungeoncrawler |
| pm-scope-activate re-run during release-b activation re-introduces mis-tagging | Medium (human/agent timing) | Medium — same stagnation pattern recurs | Seat instructions updated this cycle with confirmation step; lesson learned in KB | pm-dungeoncrawler |
| QA uses stale pre-correction suite-activate items (dated pre-052034) | Low | Low — test plan content is identical; only dispatch timing differs | QA should use 052034-* items; older item (020530) is safe fallback | qa-dungeoncrawler |
| dev-infra empty-release guard not complete before close | Low (ROI 30 item in queue) | Medium — empty release ships if close fires and 0 APPROVEs | PM monitors dev-infra inbox; escalate ROI if needed | pm-dungeoncrawler |

## Rollback Trigger
- If any Gate 2 BLOCK is unresolvable within 1 fix cycle (3+ consecutive BLOCKs on same feature): PM pulls the feature from release-next scope (reset to `ready`) and ships remaining features.
- If release-next closes with 0 Gate 2 APPROVEs despite corrections: treat as failed release, open post-release gap review immediately for release-b planning.

## Monitoring
- What to watch post-correction:
  - `sessions/qa-dungeoncrawler/outbox/` — Gate 2 APPROVE files appearing for release-next features
  - `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json` — should show 0 `copilot_agent_tracker` failures after dev-dungeoncrawler removal
  - `tmp/release-cycle-active/dungeoncrawler.release_id` — confirm stays `20260406-dungeoncrawler-release-next` until close
- Where: HQ repo `sessions/` and `features/` directories; 24h clock expires `2026-04-07T04:47Z`.
