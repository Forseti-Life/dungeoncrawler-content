# Lesson: QA Must Read Current AC File Before Issuing Schema BLOCK (2026-04-12)

## Problem

qa-forseti issued a BLOCK on `forseti-jobhunter-contact-tracker` citing 4 AC-4 deviations:
1. `last_contact_date` column missing
2. `referral_status` column missing
3. `role_title` renamed to `title`
4. `relationship_type` enum values differ

All 4 claims were **wrong**. The actual current `features/forseti-jobhunter-contact-tracker/01-acceptance-criteria.md`
specifies `title` (not `role_title`), the correct enum values (`recruiter/referral/hiring_manager/connection`),
and does NOT include `last_contact_date` or `referral_status`.

The BLOCK cascaded: qa-forseti → pm-forseti → CEO (3x escalation), burning 3 agent execution slots.

## Root cause

qa-forseti was checking against a stale/hallucinated version of the AC rather than reading the current
authoritative file before issuing a BLOCK.

## Rule (qa-forseti-owned)

**Before issuing any BLOCK for schema/AC deviations:**
1. `cat features/<feature-id>/01-acceptance-criteria.md` directly — do NOT rely on memory or cached values.
2. Compare each claimed deviation line-by-line against the file content.
3. Only then issue a BLOCK if a real deviation exists.

If the AC file and the implementation match, write APPROVE.

## Verification

After this rule is added to qa-forseti instructions, test: does qa-forseti re-read AC before blocking?

## Prevention

Add to `org-chart/agents/instructions/qa-forseti.instructions.md`:
```
## AC cross-check before BLOCK (required — lesson 2026-04-12)
Before issuing a BLOCK for schema/column deviations, always read:
  cat features/<feature-id>/01-acceptance-criteria.md
Do NOT use memory or prior-read values. The file on disk is authoritative.
```
