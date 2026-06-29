No test plan yet. Creating it:

- Status: done
- Summary: Wrote `features/dc-cr-skills-calculator-hardening/03-test-plan.md` with 16 TCs (TC-CALC-01–16) covering: trained-only action gating (`calculateSkillCheck()` blocked + explicit error message, trained char not blocked); proficiency rank ceiling (`submitSkillIncrease()` gates Expert→Master at level 7, Master→Legendary at level 15, boundary-inclusive, explicit error not silent no-op); armor check penalty (applied to Str/Dex non-attack rolls, NOT applied to attack-trait actions Grapple/Trip/Disarm, zero penalty for unarmored); server-side API bypass enforcement (direct call still blocked); ACL regression (no new routes per security AC exemption). 10 TCs immediately activatable; 6 conditional on dc-cr-character-leveling (level field needed for rank-ceiling fixture). Feature marked `ready`, PM notified. Committed at `9452273fa`.

## Next actions
- PM open questions flagged: trained-gate error string format; Str/Dex skill-to-ability mapping table; attack-trait flag model; whether any Dex-based skills carry the attack trait (TC-CALC-14 may need rework); server-side vs form-only enforcement for TC-CALC-15
- Process remaining testgen inbox items in this batch

## Blockers
- None

## ROI estimate
- ROI: 7
- Rationale: Calculator hardening is a cross-cutting service-layer fix that affects every skill check in the game; the server-side enforcement gap (TC-CALC-15) is a potential bypass vulnerability that PM should confirm before Stage 0 activation.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-152830-testgen-dc-cr-skills-calculator-hardening
- Generated: 2026-04-07T17:08:57+00:00
