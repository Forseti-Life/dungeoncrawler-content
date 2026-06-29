- Status: done
- Summary: Wrote `features/dc-cr-skills-lore-earn-income/03-test-plan.md` with 16 TCs (TC-LRE-01 through TC-LRE-16) covering: Recall Knowledge (Lore) subcategory scoping, same 4-degree resolution as other Recall Knowledge actions, multi-subcategory independence (each tracked separately), untrained use allowed within the owned subcategory; Earn Income as a downtime activity with Lore/Crafting/Performance skill gate, DC by task-level (1–20), character-level hard cap, Trained-rank additional cap at level–1, 4 degrees of success (Crit Success next-tier income / Success on-level income / Failure 0 no penalty / Crit Fail 0 + employer-specific block ~1 week), above-cap as hard block not penalty, and ACL regression. All 16 TCs are immediately activatable at Stage 0 — zero external module dependencies beyond dc-cr-skill-system (already done). PM notified. Committed at `23ba3b301`.

## Next actions
- Continue skills grooming batch — check inbox for remaining `testgen-dc-cr-skills-*` items
- PM flags: Lore domain-scope mapping model; Earn Income skill-selection constraint; DC/income table exact values; Trained cap confirmation; employer-block scalar (7 days exact vs GM-variable)
- Recommend PM prioritize this feature for Stage 0 alongside Acrobatics (both 0 deferred TCs)

## Blockers
- None

## ROI estimate
- ROI: 10
- Rationale: Earn Income is a universal downtime mechanic used by every character regardless of class or build; 16/16 TCs are immediately activatable with zero dependency risk, making it the highest-readiness feature in the skills batch for Stage 0.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-152523-testgen-dc-cr-skills-lore-earn-income
- Generated: 2026-04-07T16:39:50+00:00
