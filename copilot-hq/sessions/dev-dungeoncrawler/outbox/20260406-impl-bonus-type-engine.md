Now I have a full picture. Let me look at the `dungeoncrawler_content.services.yml` to understand service registration pattern:

Now I'll create `BonusResolver.php` and then update the three calculator files:

Now update `Calculator.php` — replace the four `array_sum` sites:

Now update `CombatCalculator.php` — update `calculateAttackBonus` and `calculateSpellSaveDC` to support structured arrays:

Now lint all modified files and run `drush cr`:

Now verify the AC items with a quick PHP snippet:

All 5 AC items + backward-compat pass. Now also finish the Alchemist update hook from earlier in this session. The error was the bundle was `character_class` not `character`. Let me fix `update_10035`:

Fix the bundle name from `character` to `character_class`:

Both hooks applied. Verify the Alchemist node field and the new research field:

All verified. Now commit everything:

Good - `CharacterManager.php` changes (alchemist CLASS_ADVANCEMENT) were already in a prior commit (the auto-checkpoint). `BonusResolver.php` — let me check:

BonusResolver.php was already captured in the auto-checkpoint. Now commit the remaining staged changes:

Now write the outbox:

---

- Status: done
- Summary: Implemented the PF2E typed bonus/penalty resolution engine as specified in Req 2079 and Req 2082. Created `BonusResolver` (new file, already captured in auto-checkpoint `680f58ec6`) with `resolve()` and `resolvePenalties()` methods enforcing stacking rules: typed bonuses (circumstance/item/status) take the highest of the same type; untyped always stack; typed penalties take the worst of the same type; different penalty types always stack. Backwards compatible — plain ints are treated as untyped. Replaced all `array_sum` bonus calls in `Calculator` (calculateInitiative, calculateAttackBonus, rollSavingThrow, rollSkillCheck) and updated `CombatCalculator` (calculateAttackBonus, calculateSpellSaveDC) to accept structured arrays. Also completed the Alchemist class advancement update hook (`update_10035`) which failed in the prior session due to wrong bundle name; fixed to `character_class`, verified `field_char_research_field` created and Alchemist nid=29 populated with 24 features across levels 1, 5, 7, 9, 11, 13, 15, 17, 19.

## Next actions
- QA notified for Gate 2 retest: BonusResolver AC items, Calculator regression (single-bonus-per-type must produce same result as before), Alchemist node field verification.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 25
- Rationale: Fixes a correctness bug affecting every attack, save, skill check, and spell DC in the game when multiple bonuses of the same type are present. This is foundational to all encounter math; without it every class that grants multiple circumstance or status bonuses would produce inflated results. Alchemist data gap also closes a missing advancement table that blocks character creation for that class.

---

**Commits (forseti.life `main`):**
- `680f58ec6` — auto-checkpoint: BonusResolver.php (new file), CharacterManager.php Alchemist CLASS_ADVANCEMENT, install update_10035 (initial, wrong bundle)
- `b17eb7430` — BonusResolver integration in Calculator/CombatCalculator + update_10035 bundle fix (HEAD)

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-bonus-type-engine
- Generated: 2026-04-06T20:00:00+00:00
