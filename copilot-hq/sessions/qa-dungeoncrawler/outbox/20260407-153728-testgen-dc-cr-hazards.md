- Status: done
- Summary: Wrote `features/dc-cr-hazards/03-test-plan.md` with 29 TCs (TC-HAZ-01–29) covering: hazard stat block completeness (AC/saves/Hardness/HP/BT); detection rules (auto-roll for no-min-proficiency hazards, Search-only for min-proficiency hazards, Detect Magic for magical hazards); passive vs active trigger types; simple vs complex hazard structure (one-reaction vs encounter-starting routines, initiative insertion into active encounter); disable mechanics (2-action cost, detection prerequisite, min-proficiency gate, multi-success for complex hazards, crit-fail trigger); broken/destroyed states (BT rule, attack triggers hazard unless destroyed outright); magical hazard counteract with crit-fail trigger; hazard XP table (simple < complex, level-delta scaling, once-per-hazard-per-party lock). Two PM notes: hazard XP table exact values need BA extraction from Table 10–14 before Dev implements; TC-HAZ-20 and TC-HAZ-28 (counteract) are conditional on dc-cr-spells-ch07. Feature marked `ready`, PM notified. Committed at `a3d208df0`.

## Next actions
- Process `20260407-153728-testgen-dc-cr-rest-watch-starvation`
- Continue: dc-cr-treasure-by-level, dc-cr-xp-award-system, dc-cr-magic-ch11, dc-cr-decipher-identify-learn, dc-cr-dwarf-ancestry, dc-cr-economy, dc-cr-human-ancestry, dc-cr-rune-system, dc-cr-snares
- APG/GMG batch (16 items) follows

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Hazards are a core GM tool used in exploration and encounter design; the hazard XP note cross-references the encounter XP table plan and surfaces a concrete data gap (Table 10–14 values) that would block Dev without early BA input.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-153728-testgen-dc-cr-hazards
- Generated: 2026-04-07T17:44:35+00:00
