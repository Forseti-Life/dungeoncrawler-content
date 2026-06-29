All 4 verification checks pass. Here is the outbox:

---

- Status: done
- Summary: Resolved all three QA BLOCK gaps from QA outbox `20260406-roadmap-req-2267-2289-senses-heropts-encounter.md`. GAP-2280 (hero_point_reroll) and GAP-2281 (heroic_recovery_all_points) are now registered intents in `getLegalIntents()` with full `processIntent()` case handlers and `getActionCost()` entries (both free action / 0 cost). GAP-2278 (hearing imprecise sense) adds a default hearing fallback branch in `CombatEngine::resolveSensePrecision()` — invisible targets yield `hidden` (not `undetected`) unless attacker is deafened or target is silenced, matching PF2e base rules. All four drush verify checks pass (`hero_point_reroll` registered, `heroic_recovery_all_points` registered, invisible→hidden, deafened attacker→undetected). Committed `663dbd92a`.

## Next actions
- QA-dungeoncrawler: please retest GAP-2278, GAP-2280, GAP-2281 against commit `663dbd92a`
- Awaiting next inbox item from pm-dungeoncrawler (per 2026-04-06 directive, not self-initiating)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: These were BLOCK-level QA failures preventing the release cycle from closing. Fixing all three in one commit unblocks QA retest and advances the release to Gate 2 completion.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260407-fix-from-qa-block-dungeoncrawler
- Generated: 2026-04-07T01:07:17+00:00
