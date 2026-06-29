Now I have the full context. The fix is clear: replace the three `in_array($degree, ['success', 'critical_success'])` blocks with four-tier logic. The key insight: these blocks currently only fire for `save` delivery; they also fire for `strike` delivery where `$degree` comes from the attack roll (not inverted). I need to make sure the half-damage logic only applies to basic saves, not strikes.

The structure is clear. The three effect blocks need to be aware of delivery type. For `save` delivery, `'failure'` (target partially saved) → half damage. For `attack`, `'failure'` is a miss so 0 damage (no change needed there). I'll replace the flat two-tier logic with delivery-aware four-tier logic:

Now verify all four AC outcomes with a drush eval:

All 4 AC items + regression checks pass. Now update `dc_requirements` req 2097 status to `implemented`:

---

- Status: done
- Summary: Implemented PF2E basic saving throw four-tier damage outcomes (req 2097) in `ActionProcessor.php`. The existing code only handled two tiers (full on success, double on crit) and produced 0 damage when the target beat the DC without a critical — it should produce half damage. The fix replaces both the damage and healing effect blocks with delivery-aware four-tier logic: `critical_success` = double, `success` = full, `failure` + `save` delivery = `floor(base / 2)` (new), `critical_failure` or non-save delivery failure = 0. Attack delivery regression is preserved (failure on an attack is a miss → 0 damage). Conditions unchanged: they apply only on success/crit. All 4 AC items and regression cases verified on production via drush eval. `dc_requirements` id=2097 updated to `implemented`. Committed `689339ea7`.

## Next actions
- QA notified for Gate 2 retest: verify all four save damage tiers, confirm attack delivery regression is clean, verify `dc_requirements` row 2097 = `implemented`.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 30
- Rationale: This is a core PF2E rules correctness fix — every basic saving throw spell was silently broken for the most common outcome (target beats DC but not by 10). Without this fix, nearly all spell-based encounters produce wrong damage, which breaks balance across all classes that use spells.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-save-half-damage
- Generated: 2026-04-06T20:04:46+00:00
