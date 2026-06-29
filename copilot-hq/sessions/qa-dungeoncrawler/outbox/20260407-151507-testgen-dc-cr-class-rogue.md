---

- Status: done
- Summary: Wrote `features/dc-cr-class-rogue/03-test-plan.md` with 38 test cases (TC-ROG-01 through TC-ROG-38) covering: class identity/HP (8+CON)/DEX default key ability with Racket overrides (STR=Ruffian/CHA=Scoundrel), unique double Expert saves (Reflex+Will at level 1), specific weapon list (hand crossbow/rapier/sap/shortbow/shortsword), Trained light armor, skill feat every level + skill increases every level from 2nd, Racket selection and mechanics (Ruffian sneak attack any simple weapon + crit spec ≤d8/no d10-d12, Scoundrel Feint flat-footed success/crit-success scope + expiry, Thief DEX-to-damage finesse melee only), Sneak Attack (flat-footed gate/immune creature types/1d6 scaling every 4 levels), Surprise Attack (Deception/Stealth initiative → pre-act flat-footed round 1), Debilitation mutual exclusivity, feat/boost schedule, and ACL regression. 3 TCs (TC-ROG-18/19/20) deferred on `dc-cr-conditions` (Scoundrel Feint flat-footed condition tracking); 35 immediately activatable. Completion script run; feature.md marked `ready`; PM notified. Committed at `15ccfa5c3`.

## Next actions
- Process remaining inbox items (APG/GMG batch: Investigator, Oracle, Swashbuckler, Witch, Ancestries, Archetypes, Class Expansions, Equipment, Feats, Focus Spells, Rituals, Spells, Hazards, NPC Gallery, Running Guide, Subsystems)
- At Stage 0: activate 35 immediately-activatable Rogue TCs; flag TC-ROG-18/19/20 as `pending-dev-confirmation` on `dc-cr-conditions`

## Blockers
- None

## ROI estimate
- ROI: 14
- Rationale: Rogue has the highest mechanical complexity in this batch (Racket-conditional key ability overrides, double Expert saves at level 1, skill feat every level, Sneak Attack immunities) with only 3 deferred TCs — the 35 immediately activatable TCs cover high-risk precision-damage edge cases that frequently regress in class implementations.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-151507-testgen-dc-cr-class-rogue
- Generated: 2026-04-07T16:05:58+00:00
