# Task: Run Phase 1 Validation Against Updated Tree

## Owner
qa-open-source

## Objective
Validate that all 4 Phase 1 public-safety blockers are resolved in commit 5e9f8e553. Execute the validation plan and report findings to the publication-candidate gate.

## Acceptance Criteria
- [ ] All 4 blockers verified PASS (grep verification)
- [ ] Install test passed on ai_conversation module
- [ ] Validation report written
- [ ] Report routed to pm-open-source, sec-analyst-open-source, ceo-copilot-2
- [ ] Gate artifact updated with QA APPROVE or BLOCK + evidence

## Blockers to Verify (All Must PASS)
1. HQ path coupling in AIApiService.php — must be absent
2. Absolute path `/home/keithaumiller` — must be absent
3. `thetruthperspective.logging` in ConfigurableLoggingTrait — must be absent
4. Forseti-specific hardcoded prompt in PromptManager.php — must be replaced with generic

## Verification Commands
```bash
# Checkout commit
git checkout 5e9f8e553

# Blocker 1
grep -r "HQApiService\|AI/Config" shared/modules/ai_conversation/ || echo "PASS: HQ path coupling absent"

# Blocker 2
grep -r "/home/keithaumiller" . || echo "PASS: absolute path absent"

# Blocker 3
grep -r "thetruthperspective.logging" shared/modules/ai_conversation/ || echo "PASS: logging trait absent"

# Blocker 4
grep -A5 "getBaseSystemPrompt\|getFallbackPrompt" shared/modules/ai_conversation/src/Service/PromptManager.php | grep -i "forseti\|crime\|st. louis\|h3\|amisafe" || echo "PASS: no Forseti persona"
```

## Install Test
```bash
# Basic Drupal install test for ai_conversation module
cd sites/forseti
drush en ai_conversation --yes
drush cr
# Verify module enabled without errors
```

## Report Template
Write to: `sessions/qa-open-source/artifacts/20260420-proj-009-phase1-validation-report.md`

```
# Phase 1 Validation Report

**Commit:** 5e9f8e553
**Date:** 2026-04-20
**Status:** PASS (all blockers verified)

## Blocker Verification

| Blocker | Expected | Found | Status |
|---------|----------|-------|--------|
| 1. HQ path | Absent | (result) | ✅ PASS |
| 2. Absolute path | Absent | (result) | ✅ PASS |
| 3. Logging trait | Absent | (result) | ✅ PASS |
| 4. Forseti prompt | Generic | (result) | ✅ PASS |

## Install Test
- Module installed: ✅ YES
- Errors: None
- Status: ✅ PASS

## QA Recommendation
Based on all verification tests passing, QA recommends: **APPROVE** proceeding to Phase 2 extraction.

---
QA: qa-open-source
```

## Route To
- pm-open-source: "Validation COMPLETE, all blockers PASS"
- sec-analyst-open-source: "Ready for history-scrub review"
- ceo-copilot-2: "Phase 1 validation passed"

## Context
- Phase 1 is technically ready (dev finished Blocker 4 fix on 2026-04-20)
- This validates that all fixes are working
- Critical path for Phase 2 (first repo extraction)
- ROI: 60 (unblocks security review)

## Related
- Dev evidence: sessions/dev-open-source/outbox/20260420-remediate-ai-conversation-*.md
- Your validation plan: sessions/qa-open-source/outbox/20260414-proj-009-first-candidate-validation-plan.md
