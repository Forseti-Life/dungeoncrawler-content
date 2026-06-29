Now I have enough context. Let me read the acceptance criteria:

Now I have a clear picture. Let me implement all changes:

Now I'll implement all changes:

Now I'll make all changes in parallel:

Now add the bard CLASS_FEATS section (before ranger, after wizard):

Now add genie and nymph bloodlines to SORCERER_BLOODLINES:

Now update the alchemist research-field description to include Toxicologist:

Now verify PHP syntax and drush cr:

Clean. Now commit:

Now write implementation notes:

- Status: done
- Summary: Implemented all 7 APG core class expansions in `CharacterManager.php`. Added Toxicologist research field (1-action poison, class DC sub, L5/L15 discoveries), confirmed Superstition instinct was already present, added Warrior Muse with Martial Performance + Song of Strength, flagged evil Champion options as uncommon-access, added Eldritch Trickster and Mastermind rackets, added Genie (arcane, subtype_required) and Nymph (primal) bloodlines to `SORCERER_BLOODLINES`, and added Staff Nexus thesis. PHP lint clean; `drush cr` success. Commits: `76e6c627f` (code), `b4ab1348b` (notes).

## Next actions
- QA to run TC-EXP-01 through TC-EXP-16 against the test plan
- Future follow-up: EncounterPhaseHandler action-cost override for Toxicologist 1-action poison (currently documented in description text only; no action-economy enforcement yet)
- Genie `subtype_required: TRUE` flag should be picked up by character creation UI validation — verify in UI layer

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: Completes a full AC block for the APG expansion feature; unlocks QA Gate 2 verification pass and unblocks the release cycle for dc-apg-class-expansions.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-194600-impl-dc-apg-class-expansions
- Generated: 2026-04-08T20:18:56+00:00
