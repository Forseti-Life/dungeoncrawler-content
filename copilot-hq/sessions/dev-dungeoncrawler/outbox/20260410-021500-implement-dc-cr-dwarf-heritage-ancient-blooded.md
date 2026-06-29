The `$granted_reactions` variable was computed but never added to the output. I need to add it to the return array:

Also fix the comment on line 263 that still says the old id:

Now check the `getHeritageGrantedAbilities` docblock (still says `'ancient-blooded'` as example) and validate character creation controller still works with the new id:

Clean. Now commit and update feature.md + outbox:

---

- Status: done
- Summary: Completed the Ancient-Blooded Dwarf heritage. Fixed heritage ID (`ancient-blooded` → `ancient-blooded-dwarf`) in HERITAGES, HERITAGE_REACTIONS, and docblocks. Corrected benefit/special to CRB reaction semantics. Wired `$granted_reactions` into `buildCharacterJson` return (`granted_abilities` + `reactions` keys). Also updated Dwarf heritage count 4→5 in header comment (added death-warden). PHP lint clean; site 200.

**Commits:** forseti.life `4f1a61af5`, copilot-hq `20cf23168`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-021500-implement-dc-cr-dwarf-heritage-ancient-blooded
- Generated: 2026-04-10T03:38:38+00:00
