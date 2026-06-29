Now I have the full picture. The AC requires:
1. Fix CLASSES `witch` record: add `armor_proficiency: unarmored_only`, update saves, clarify key ability
2. Add witch to `CLASS_ADVANCEMENT` with proper features
3. Add witch `CLASS_FEATS` section with lesson feats and hex feats
4. Update `WITCH_PATRONS` with patron skill info
5. Add `WITCH_HEXES` constant
6. Update `CASTER_SPELL_SLOTS` for witch (familiar model: 10 cantrips + 5 first-level)

Now I'll implement all changes:

1. Update `CLASSES['witch']` with armor proficiency and familiar details
2. Update `WITCH_PATRONS` with patron skill and hex cantrip data
3. Update `CASTER_SPELL_SLOTS['witch']` to reflect familiar model
4. Add `WITCH_HEXES` constant
5. Add witch to `CLASS_FEATS` with lesson feats
6. Add witch to `CLASS_ADVANCEMENT`

Now add WITCH_HEXES constant after WITCH_PATRONS, and add witch to CLASS_FEATS and CLASS_ADVANCEMENT:

Now add witch CLASS_FEATS (lesson feats):

Now add witch to CLASS_ADVANCEMENT. Find the closing bracket of the last class in CLASS_ADVANCEMENT:

Now create implementation notes and the outbox:

- Status: done
- Summary: Completed full APG Witch class mechanics implementation for `dc-apg-class-witch`. Added CLASS_ADVANCEMENT['witch'] (the one remaining item from prior session), verified PHP lint passes, cleared Drupal cache, and committed. All 6 implementation areas are complete: CLASSES['witch'] expansion (armor proficiency, familiar block, hexes block), WITCH_PATRONS enrichment (patron_skill/hex_cantrip/granted_spell for all 7 patrons), CASTER_SPELL_SLOTS familiar model, new WITCH_HEXES constant (7 hex cantrips + cackle + phase familiar + 10 lesson hexes), CLASS_FEATS['witch'] (10 lesson feats across 3 tiers), and CLASS_ADVANCEMENT['witch'] (L1–L19 milestones). Implementation notes created at `features/dc-apg-class-witch/02-implementation-notes.md`.

## Next actions
- QA: run test plan TC-WCH-01–18 against `features/dc-apg-class-witch/03-test-plan.md`
- Await next inbox dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 18
- Rationale: Witch is the most mechanically complex APG class (familiar model + patron themes + hex system + lesson feat gating). Completing this unblocks QA gate and brings the DungeonCrawler APG release scope one feature closer to ship. Commits `a66af1bf3` and `b3d9d8f88`.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-194600-impl-dc-apg-class-witch
- Generated: 2026-04-08T20:30:03+00:00
