- Status: done
- Summary: Gate 2 for dc-cr-equipment-system is APPROVE (updated from prior BLOCK). Product: dungeoncrawler / dungeoncrawler_content module / dc-cr-equipment-system feature. All catalog and endpoint AC items passed in original pass: 6 simple / 7 martial weapons, 3 light / 3 medium / 1 heavy armor, 3 shields, 10 gear; `GET /equipment?type=X` returns correct filtered results (anonymous, HTTP 200); invalid type returns HTTP 400; `GET /classes/fighter/starting-equipment` returns weapons/armor/gear/currency correctly. Original BLOCK 1 (encumbrance formula: wrong capacity `5+STR_mod`, wrong thresholds, `overburdened` label) and BLOCK 2 (no `str_req` enforcement in equip path) were both dispatched to Dev and resolved in commit `889d129a3`. QA re-verified: `getInventoryCapacity()` returns `10+STR_mod` (PF2e spec); `getEncumbranceStatus()` returns `immobilized` at `STR_score+5`; `applyArmorStrPenalty()` sets `str_penalty_active: TRUE` on equip when `char_str < str_req`. Checklist line 97 APPROVE. Evidence: `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-fix-from-qa-block-dungeoncrawler.md`.

## Next actions
- None. Both BLOCKs resolved and verified. PM may include dc-cr-equipment-system in release gate.

## Blockers
- None. Prior BLOCKs resolved.

## Decision needed
- Originally: (BLOCK 1) What is the correct PF2e encumbrance formula — `5 + STR_mod` capacity with 75%/100% thresholds, or `10 + STR_mod` with `STR/2+5` encumbered and `STR+5` immobilized thresholds? **Decision: PF2e spec is `10 + STR_mod` capacity; encumbered at `bulk >= floor(STR/2)+5`; immobilized at `bulk >= STR+5`; condition name is `immobilized` (not `overburdened`).**
- (BLOCK 2) Should equipping armor below the STR requirement block the equip, or just apply a penalty flag? **Decision: equipping is never blocked — only set `str_penalty_active: TRUE` and `str_penalty_check_penalty` flag for downstream skill-check resolution.**

## Recommendation
- For encumbrance: always use the published PF2e rulebook formula. The `5+STR_mod` approach was a custom approximation that diverged from the spec at higher STR scores. The `10+STR_mod` formula is the canonical PF2e bulk capacity. Tradeoff: retroactive change to encumbrance thresholds may affect existing characters already at high bulk — acceptable since this is a beta product and correctness takes priority.
- For STR req: flag-only approach (no blocking) is correct for PF2e. Blocking equip would be out-of-spec and would cause frustrating UX — the game's intent is that low-STR characters can wear heavy armor but are penalized. Tradeoff: flag must be propagated to skill-check resolution (downstream work) — advisory, non-blocking for this feature.

## ROI estimate
- ROI: 20
- Rationale: Equipment/encumbrance system now matches PF2e spec; both defects closed. Feature cleared for release.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-dc-cr-equipment-system
- Updated: 2026-04-06T19:46:00+00:00
