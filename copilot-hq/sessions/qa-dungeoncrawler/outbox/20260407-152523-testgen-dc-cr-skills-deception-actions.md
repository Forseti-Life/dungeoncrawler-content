- Status: done
- Summary: Wrote `features/dc-cr-skills-deception-actions/03-test-plan.md` with 26 TCs (TC-DEC-01 through TC-DEC-26) covering: Create a Diversion (1-action, trait-by-method, +4 Stealth bonus vs targeted observers for 10 rounds, success → Hidden not Undetected, revert-to-Observed rule, Strike-while-hidden → target flat-footed then attacker Observed, bonus-reset edge case), Impersonate (exploration 10 min, disguise-kit gate, no-kit –2 penalty path, passive Perception vs Deception DC, Seek for active searchers, Crit Fail true-identity reveal), Lie (secret check, single roll vs each target's Perception DC individually, Failure grants +4 resist bonus scoped to current conversation, conversation-end clears bonus, GM delayed recheck trigger path), Feint (1-action mental trait, Trained gate, melee-reach gate, Crit Success full-turn flat-footed, Success next-one-attack flat-footed, Crit Fail attacker flat-footed), and ACL regression. 19 TCs immediately activatable at Stage 0; 7 conditional on `dc-cr-conditions` (hidden/flat-footed/observed states) or `dc-cr-equipment-system` (disguise kit inventory). PM notified. Committed at `6e822e6d1`.

## Next actions
- Continue skills grooming batch — check inbox for remaining `testgen-dc-cr-skills-*` items
- PM flags raised: Diversion method selection model (player input vs AC type); Diversion duration scalar (combat rounds vs exploration time); Impersonate no-kit default (–2 vs hard block); Lie conversation-scope boundary; GM delayed recheck trigger design

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: Deception covers encounter actions (Create a Diversion, Feint) and exploration (Impersonate, Lie) — the flat-footed outcomes are core combat mechanics dependent on dc-cr-conditions which is already in-progress Release B; 19 of 26 TCs activate immediately.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-152523-testgen-dc-cr-skills-deception-actions
- Generated: 2026-04-07T16:33:15+00:00
