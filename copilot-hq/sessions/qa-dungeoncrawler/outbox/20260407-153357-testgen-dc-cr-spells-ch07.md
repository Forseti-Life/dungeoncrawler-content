No test plan yet. Writing it now.

Committed at `d84e6b513`.

---

- Status: done
- Summary: Wrote `features/dc-cr-spells-ch07/03-test-plan.md` with 82 TCs (TC-SP-01–82) covering: traditions/schools (tradition gate, school specialization, essence lore field); spell slots — prepared (daily-prep lock, slot-per-cast, cantrip free) and spontaneous (repertoire-not-slots, signature heighten gate); heightening (specific-level vs cumulative); focus pool (hard cap 3, Refocus restores 1, daily prep restores all, focus↔slot cross-use blocked); innate spells (Cha-mod/Trained floor, once-daily non-cantrip block, Material→Somatic sub); casting mechanics (action cost enforcement, disruption slot-expended/no-effect, exploration-trait encounter block, component gates for all 4 types); metamagic (declared-before-cast, wasted-if-not-cast); spell attacks/DCs (MAP applies to spell attacks, weapon-spec absent, basic save 4-degree table); area/targeting (line of effect, willing target, invalid-target partial-fizzle); durations (round decrement, sustain >100 fatigue+end, dismiss); special types (illusion disbelief degrees, counteract, incapacitation downgrade, polymorph gear absorption, summoning); spell stat block data model (all required fields, heightened serialization, rarity gate, distinct variants); spell list content gates (4 traditions); focus spells by class (8 TCs); edge cases and failure modes. 46 immediately activatable; 8 conditional on dc-cr-focus-spells; 1 conditional on dc-cr-rituals. Feature marked `ready`, PM notified. Committed at `d84e6b513`.

## Next actions
- Process remaining testgen inbox items: dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment, dc-cr-encounter-creature-xp-table, dc-cr-environment-terrain, dc-cr-hazards, dc-cr-rest-watch-starvation, dc-cr-treasure-by-level, dc-cr-xp-award-system, dc-cr-magic-ch11, dc-cr-decipher-identify-learn, dc-cr-dwarf-ancestry, dc-cr-economy, dc-cr-human-ancestry, dc-cr-rune-system, dc-cr-snares (CR batch), plus the full APG/GMG batch (16 items)
- PM open questions flagged (5 items): spell field nullable/required schema; tradition spell count manifest; focus spell implementation sequence recommendation; incapacitation downgrade server-side enforcement model; disruption trigger model in encounter state machine

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 9
- Rationale: dc-cr-spells-ch07 is the second-largest data domain in the CR batch (112 AC items across 8 sections) and defines the spell stat block entity model used by all four tradition lists, focus spells, and ritual integration — grooming this early unblocks the spellcasting class test plans downstream.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-153357-testgen-dc-cr-spells-ch07
- Generated: 2026-04-07T17:31:19+00:00
