# QA Verification: dc-cr-fey-fellowship

- **Inbox item:** `20260409-unit-test-20260409-050000-impl-dc-cr-fey-fellowship`
- **Dev commit:** `ddc3d4e19`
- **Audit run:** `20260409-051852`
- **Regression checklist commit:** `606f98f06`

---

## Verdict: APPROVE

---

## AC verification

### Availability
- Feat present in `ANCESTRY_FEATS['Gnome']` at line 614: `id='fey-fellowship'`, `level=1`, `traits=['Gnome']`, `prerequisites=''` ✅

### Combat/Perception Bonuses vs. Fey
- `conditions.fey_target_required = TRUE` — bonuses gated on fey trait ✅
- `conditions.perception_bonus = {type: 'circumstance', value: 2, against: 'fey creatures'}` ✅
- `conditions.save_bonus = {type: 'circumstance', value: 2, against: 'fey creatures'}` ✅
- `benefit` text states "Multiple circumstance bonuses vs. fey do not stack (only highest applies)" — non-stacking edge case covered ✅

### Immediate Social Diplomacy
- `conditions.immediate_diplomacy.action_cost = 1` ✅
- `conditions.immediate_diplomacy.check = 'Diplomacy (Make an Impression)'` ✅
- `conditions.immediate_diplomacy.penalty = -5` ✅
- `conditions.immediate_diplomacy.retry_allowed = TRUE` ✅
- `conditions.immediate_diplomacy.retry_penalty = 0` — no further penalty on retry ✅
- `conditions.immediate_diplomacy.retry_duration = '1 minute (normal Make an Impression)'` ✅

### Glad-Hand Interaction
- `conditions.glad_hand_interaction.feat_required = 'Glad-Hand'` ✅
- `conditions.glad_hand_interaction.target_must_be_fey = TRUE` — waiver only for fey targets ✅
- `conditions.glad_hand_interaction.effect = 'Waives the –5 penalty on the immediate Diplomacy check.'` ✅

### Edge Cases / Failure Modes
- Non-fey targets: `fey_target_required=TRUE` ensures bonuses do not apply to non-fey ✅
- Glad-Hand non-fey: `target_must_be_fey=TRUE` prevents waiver for non-fey targets ✅
- Retry penalty: `retry_penalty=0` confirms AC "no further penalty from this feat" ✅

---

## Evidence

| Check | Result |
|---|---|
| PHP lint | No syntax errors |
| Dev correction noted | Prior PF1e stub ("attitude upgrade by one step") replaced with full PF2e mechanics — correctness fix verified |
| Suite `dc-cr-fey-fellowship-e2e` | 8 TCs, activated for `20260409-dungeoncrawler-release-c`, `required_for_release: true` |
| Site audit `20260409-051852` | 0 violations, 0 failures (no new routes introduced) |
| Regression checklist | Updated to APPROVE — commit `606f98f06` |
