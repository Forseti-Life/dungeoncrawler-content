Now I have enough context. I need to:
1. Add `very-sneaky` to Goblin ANCESTRY_FEATS in CharacterManager
2. Add the `case 'very-sneaky':` handler in FeatEffectManager with the two flags

Now add Very Sneaky to the Goblin feat list in CharacterManager:

Committed `0b0e87998`. Outbox:

---

- Status: done
- Summary: Implemented Goblin ancestry feat Very Sneaky (dc-cr-goblin-very-sneaky, release-l). Added `very-sneaky` to Goblin `ANCESTRY_FEATS` in `CharacterManager.php` with correct level-1 traits and benefit text. Added `case 'very-sneaky':` in `FeatEffectManager.php` that sets two derived flags: `very_sneaky_sneak_distance_bonus = 5` (grants the +5 ft Sneak movement up to Speed) and `very_sneaky_eot_visibility_delay = TRUE` (suppresses the Observed transition at end of Sneak action when cover/concealment is maintained at end of turn). PHP lint clean, cache rebuilt. Committed `0b0e87998`.

## Next actions
- Route to qa-dungeoncrawler for Gate 2 verification

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 11
- Rationale: Completes a core Goblin level-1 feat for release-l; clean two-file implementation with no regressions. Unblocks QA gate.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-172632-impl-dc-cr-goblin-very-sneaky
- Generated: 2026-04-14T17:39:32+00:00
