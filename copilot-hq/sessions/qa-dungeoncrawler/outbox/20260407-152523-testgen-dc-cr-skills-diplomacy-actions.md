- Status: done
- Summary: Wrote `features/dc-cr-skills-diplomacy-actions/03-test-plan.md` with 29 TCs (TC-DIP-01 through TC-DIP-29) covering: Gather Information (downtime 2hr, DC tiers by topic secrecy, Success yields info payload, Crit Fail community-awareness flag), Make an Impression (downtime 10min, 4 degrees shifting NPC attitude ±1/±2 on Hostile→Helpful scale, clamped at both bounds), Request (1-action auditory+linguistic+mental, Friendly/Helpful gate or leverage, unreasonable-request penalty, 4 degrees including comply+attitude-shift on Crit Success), Coerce (downtime 10min, compliance 1-week/1-month windows then Unfriendly, post-coerce 1-week immunity timer), Demoralize (1-action, language-barrier hard-block, frightened 1/2, 10-min post-attempt immunity), and ACL regression. 10 TCs immediately activatable at Stage 0; 13 conditional on `dc-cr-npc-system` (NPC attitude model/timers) and 4 conditional on `dc-cr-conditions` (frightened condition). PM notified. Committed at `fc9652dd1`.

## Next actions
- Continue skills grooming batch — check inbox for remaining `testgen-dc-cr-skills-*` items
- Key PM flags raised: DC tier offsets for Gather Information; NPC system stub availability (affects Make an Impression Stage 0 activation count); Request low-attitude behavior (auto-fail vs –4); Intimidation grouping confirmation (Coerce+Demoralize with Diplomacy vs separate stub)

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: Diplomacy/Intimidation covers core NPC interaction mechanics critical to social encounters; however, 19 of 29 TCs depend on dc-cr-npc-system which is the most significant new dependency identified in this grooming batch — worth flagging to PM for Release B planning.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-152523-testgen-dc-cr-skills-diplomacy-actions
- Generated: 2026-04-07T16:36:03+00:00
