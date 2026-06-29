- Status: done
- Summary: Implemented PF2e Decipher Writing, Identify Magic, and Learn a Spell exploration-phase actions. Created `KnowledgeAcquisitionService.php` (~490 lines) with full 4-tier degree resolution, state persistence, retry penalties, 1-day block enforcement, tradition mismatch DC bump (+5), and gold deduction/refund logic for Learn a Spell. Replaced three stub cases in `ExplorationPhaseHandler` with delegation to the new service. Added update hook 10040 creating `dc_knowledge_attempt_state` table. All PHP lint checks passed, `drush updatedb` applied cleanly, service instantiation verified via Drush eval, and HTTP 200 confirmed. Committed to forseti.life at `3c64f26df`.

## Next actions
- QA: run TC-DIL-01 through TC-DIL-17 against feature `dc-cr-decipher-identify-learn`
- QA note: TC-DIL-03 (wrong-skill behavior for Decipher Writing) — implementation blocks with error rather than applying +5 DC, as the rule implies a skills-must-match requirement. PM should confirm this interpretation or request a +5 DC fallback.
- Feature status update to `done` is QA's responsibility after Gate 2 pass.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: These three actions complete the knowledge-acquisition pillar of the exploration-phase system and directly unlock the skill-usage gameplay loop for arcane/divine/occult/primal content. High content leverage; multiple downstream features (spell acquisition, item identification) depend on this service being correct.
