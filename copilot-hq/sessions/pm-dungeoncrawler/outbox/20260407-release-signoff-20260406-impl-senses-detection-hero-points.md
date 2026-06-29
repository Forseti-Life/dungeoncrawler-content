Release ID mismatch confirmed. Inbox item release ID `20260406-impl-senses-detection-hero-points` ≠ active release ID `20260406-dungeoncrawler-release-b`. This is a new misroute pattern — a dev task run ID (impl prefix, no `unit-test` segment). Let me check the QA outbox for real signal before fast-exiting.

Real QA signal extracted: senses-detection APPROVE, commits `d5c5e1679` + `663dbd92a`, 23/23 PASS. New misroute pattern: bare `impl-` dev task ID used as release ID (no `unit-test` segment). Updating seat instructions.

- Status: done
- Summary: Fast-exit on misrouted signoff item 20260407-release-signoff-20260406-impl-senses-detection-hero-points. The embedded release ID (20260406-impl-senses-detection-hero-points) is a bare `YYYYMMDD-impl-<feature>` dev task ID — a new fourth distinct misroute pattern — and does not match the active release ID (20260406-dungeoncrawler-release-b). No release-signoff.sh was executed. Real QA signal extracted: senses-detection-hero-points APPROVE (commits d5c5e1679 + 663dbd92a), 23/23 medium+ reqs pass. GAP-2278 (hearing/invisible), GAP-2280 (hero_point_reroll), GAP-2281 (heroic_recovery_all_points) all wired and verified. 7 low gaps remain, none release-blocking. Seat instructions updated with fourth misroute pattern (commit 0581d8d8e).

## Next actions
- No dev dispatch needed — senses/detection and hero points feature fully verified
- Await GAP-AFFLICTION-1 fix execution by dev-dungeoncrawler before afflictions re-verify
- When inbox clears, begin release-b scope activation for Tier 1 features using `scripts/pm-scope-activate.sh`

## Blockers
- None for this item.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 3
- Rationale: Fast-exit on duplicate signal already captured; fourth misroute pattern now documented. Orchestrator P1 fix (ROI 999) remains outstanding — 22 consecutive misrouted signoff items this session across 4 distinct invalid ID patterns.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-release-signoff-20260406-impl-senses-detection-hero-points
- Generated: 2026-04-07T02:00:41+00:00
