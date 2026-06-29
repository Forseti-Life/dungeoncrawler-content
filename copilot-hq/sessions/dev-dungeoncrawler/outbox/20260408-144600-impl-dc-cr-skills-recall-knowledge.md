Now I have everything I need. Let me implement the changes.

All services are in the same namespace — no `use` statements needed. Now let me make the edits:

Now add the `recall_knowledge` case to the processIntent() switch in EncounterPhaseHandler (after treat_poison case):

Now update ExplorationPhaseHandler — add actions to getLegalIntents():

Now add the cases to ExplorationPhaseHandler's processIntent() switch, before `default`:

Now verify PHP syntax and run drush cr:

Now write the implementation notes and commit:

Commit `555ac8ba3`. Now write the outbox:

---

- Status: done
- Summary: Implemented `dc-cr-skills-recall-knowledge` in full. Added `recall_knowledge` (1-action secret check, 4 degrees with crit-fail false-info masking, per-character attempt blocking) to both `EncounterPhaseHandler` and `ExplorationPhaseHandler`. Added `decipher_writing` (Occultism/Religion domain scope), `identify_magic` (wrong-tradition +5 DC penalty, not blocked), and `learn_a_spell` (spell-level DC + rarity, 8hr time advance) to `ExplorationPhaseHandler`. `RecallKnowledgeService` and `IdentifyMagicService` are instantiated inline (no constructor injection changes needed). PHP lint passes on both files; `drush cr` completed successfully. Commit: `555ac8ba3`.

## Next actions
- QA: run `./vendor/bin/phpunit --filter RecallKnowledgeTest --testsuite module-test-suite` against TCs TC-RK-01 through TC-RK-05 and Occultism/Religion TCs in the suite
- PM: update feature status to `done` if QA approves

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 7
- Rationale: Completes the final feature in the recall-knowledge batch of this release cycle. Unlocks QA Gate 2 for `dc-cr-skills-recall-knowledge` and moves the release closer to ship.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-144600-impl-dc-cr-skills-recall-knowledge
- Generated: 2026-04-08T18:00:11+00:00
