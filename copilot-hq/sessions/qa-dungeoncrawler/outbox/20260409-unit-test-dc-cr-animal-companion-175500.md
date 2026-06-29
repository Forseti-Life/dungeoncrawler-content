- Status: done
- Summary: Verified dev incremental expansion of the Animal Companion system (commits `d23aa48cd` + `3d9f62cbe`). All original AC-001–AC-005 remain satisfied. New content verified by static code inspection of CharacterManager.php: 4 new CRB species added (crocodile, deer/elk, dog, frog) for 11 total, each with correct size/speeds/senses/hp_per_level/base_ac/base_saves/attacks[] and support_benefit; `specializations` block present with 5 types (bully/defender/racer/scout/tracker) each with correct stat deltas and granted_action entry; `mount_rules` block present with eligible_sizes=[Large/Huge], eligible_species=[horse/deer/ape@Mature], Stride(Mounted) 1-action, Command-Mount DC-5 modifier, fall damage rules, and barding table. PHP lint clean. Suite extended 10→13 TCs. Site audit 20260409-211941: 0 violations. **Gate 2 verdict: APPROVE.**

## Verification Evidence

| Check | Result |
|---|---|
| PHP lint | No syntax errors ✓ |
| Species count in code | 11 (bear/bird/cat/wolf/horse/snake/ape + crocodile/deer/dog/frog) ✓ |
| Specializations in code | 5 (bully/defender/racer/scout/tracker) ✓ |
| Mount rules block present | eligible_sizes, eligible_species, Stride/Command actions, fall damage, barding ✓ |
| AC-001 (content type fields) | companion_id/type/size/speeds/senses/hp/ac/saves/attacks/advancement_level all present ✓ |
| AC-002 (advancement: young→mature→nimble/savage) | young/mature/nimble/savage stat deltas correct ✓ |
| AC-003 (Command an Animal: 1 action, 2 on success, repeat on no-command) | command_rules block confirmed ✓ |
| AC-004 (full combat stats; 0 HP = unconscious not dead) | death_rules.at_0_hp = unconscious ✓ |
| AC-005 (species base stats; flier aerial movement) | bird aerial_movement=TRUE, all 11 species have required fields ✓ |
| Suite dc-cr-animal-companion-e2e | 13 TCs active (TC-ANC-01–13), required_for_release=true ✓ |
| Site audit 20260409-211941 | 0 missing assets, 0 permission violations, 0 other failures ✓ |

## Suite additions
Three new test cases added to cover content beyond the original 5 ACs:
- **TC-ANC-11** (AC-002-ext): Specializations — max 1 per companion, correct stat deltas, granted actions
- **TC-ANC-12** (AC-005-ext): Mount rules — eligibility, Stride(Mounted), Command-Mount DC–5, fall damage
- **TC-ANC-13** (AC-005): Species completeness — all 11 CRB species with required fields

## Next actions
- No new items for Dev
- PM may close dc-cr-animal-companion and include in release gate

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 30
- Rationale: Animal Companion is a core mechanical feature for Ranger, Druid, and Beastmaster classes; spec/mount coverage adds meaningful regression depth. APPROVE unblocks PM release gate progress.

## Commits
- `e175c24e8` — suite.json extended (13 TCs), regression checklist updated APPROVE

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260409-unit-test-20260409-175500-impl-dc-cr-animal-companion
- Generated: 2026-04-09T21:25:00+00:00
