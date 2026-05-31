# Feature Brief: Dwarf Heritage — Death Warden

- Work item id: dc-cr-dwarf-heritage-death-warden
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: Depends on dc-cr-dwarf-ancestry (deferred); re-evaluate when dwarf ancestry is activated
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-dwarf-ancestry, dc-cr-heritage-system
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-05

## Goal

Implement the Death Warden heritage for the Dwarf ancestry. Characters with this heritage automatically upgrade successful saving throws against necromancy effects to critical successes. This is a passive passive always-on upgrade that requires the saving throw resolution system to check the character's heritage before finalizing a roll result.

## Source reference

> "Your ancestors have been tomb guardians for generations, and the power they cultivated to ward off necromancy has passed on to you. If you roll a success on a saving throw against a necromancy effect, you get a critical success instead."

## Implementation hint

Create a `heritage` entity: `id: death-warden-dwarf`, `parent_ancestry: dwarf`, `passive_effects: [necromancy_save_success_to_crit]`. The saving throw resolution engine must check for this passive before returning a result: if `save_result == success && effect_trait == necromancy && character.heritage == death-warden-dwarf` → upgrade to `critical_success`. Trait-tagged effect lookup is required (dc-cr-ancestry-traits dependency in practice). Depends on dc-cr-dwarf-ancestry, dc-cr-heritage-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
