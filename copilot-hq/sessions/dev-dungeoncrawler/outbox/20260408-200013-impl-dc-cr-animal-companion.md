Now I understand the codebase structure. The `CharacterManager` holds game data constants. I need to add an `ANIMAL_COMPANIONS` constant with species data, advancement table, and command rules. Let me find a good place to insert it — after `FOCUS_POOLS` or near the existing companion references.

I'll add `ANIMAL_COMPANIONS` constant after `FOCUS_POOLS`, before `RITUALS`:

Committed as `24bcfd52b`. Here is the outbox:

---

- Status: done
- Summary: Implemented the `ANIMAL_COMPANIONS` constant in `CharacterManager.php` covering all acceptance criteria for `dc-cr-animal-companion`. Added `command_rules` (Command an Animal: 1-action, Nature vs. DC 15 or Will DC, 2-action grant on success; repeat-behavior fallback when not commanded), `advancement` table (young/mature/nimble/savage with HP, AC, attack, damage, save bonuses; mature grants size increase and support action; nimble adds speed + evasion; savage adds extra attack), `death_rules` (0 HP = unconscious, not permanently dead unless deliberate or recovery failure), and 7 species entries (bear, bird, cat, wolf, horse, snake, ape) each with size, speeds, senses, base_ac, base_saves, attack entries with traits and specials, and support_benefit. Bird carries `aerial_movement: TRUE` for elevation/plunging strike rules. All companions initialize at `young` advancement. PHP lint clean. Commit: `24bcfd52b`. Rollback: `git revert 24bcfd52b`.

## Next actions
- Await QA verification (`features/dc-cr-animal-companion/03-test-plan.md`)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 38
- Rationale: Animal companions are a core Ranger/Druid/Beastmaster feature required for complete class play; the data model is the foundation for companion display, command UI, and advancement triggers. Completes a major CRB content milestone.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-200013-impl-dc-cr-animal-companion
- Generated: 2026-04-08T22:54:21+00:00
