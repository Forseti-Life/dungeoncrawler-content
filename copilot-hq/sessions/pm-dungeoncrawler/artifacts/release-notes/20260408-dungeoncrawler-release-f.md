# Release Notes: 20260408-dungeoncrawler-release-f

- Site: dungeoncrawler
- Release ID: 20260408-dungeoncrawler-release-f
- Released at: 2026-04-08
- PM: pm-dungeoncrawler

## Summary

Release-f ships 7 features covering DC rarity/spell adjustment, human ancestry, session structure, athletics actions, skills calculator hardening, medicine actions, and recall knowledge. 3 features (stealth/hide/sneak, thievery/disable/pick-lock, spellcasting) were deferred to release-g at the 10-feature auto-close cap due to incomplete Gate 2 verification.

## Shipped features

| Feature | Description | QA Verdict |
|---|---|---|
| dc-cr-dc-rarity-spell-adjustment | DC tables for rarity/spell difficulty (32 probes pass, 5 endpoints) | APPROVE |
| dc-cr-human-ancestry | Human ancestry with 4 heritages, vision_override, cross_ancestry_feat_pool | APPROVE |
| dc-cr-session-structure | dc_sessions table, SessionService, PlaySessionController (8 routes, CSRF+auth) | APPROVE |
| dc-cr-skills-athletics-actions | 9 Athletics actions in EncounterPhaseHandler (53 TCs) | APPROVE |
| dc-cr-skills-calculator-hardening | trained_only gate, armor check penalty, Expert→Master level blocks, medicine actions | APPROVE |
| dc-cr-skills-medicine-actions | administer_first_aid, treat_poison, treat_wounds, treat_disease | APPROVE |
| dc-cr-skills-recall-knowledge | recall_knowledge (encounter + exploration), decipher_writing, identify_magic, learn_a_spell | APPROVE |

## Deferred to release-g

- dc-cr-skills-stealth-hide-sneak (suite activated; no Gate 2 APPROVE)
- dc-cr-skills-thievery-disable-pick-lock (suite activated; no Gate 2 APPROVE)
- dc-cr-spellcasting (suite activated; no Gate 2 APPROVE)

## Rollback

Each shipped feature has rollback notes in its feature.md and dev commit messages. All implementations are Drupal custom module additions — rollback via module disable/uninstall or targeted update hook reverts.
