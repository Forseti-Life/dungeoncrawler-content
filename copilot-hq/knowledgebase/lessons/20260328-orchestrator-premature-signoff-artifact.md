# Lesson Learned: Orchestrator-Generated Premature Signoff Artifact

**Date:** 2026-03-28  
**Site:** dungeoncrawler  
**Release:** 20260327-dungeoncrawler-release-b  
**Reported by:** pm-dungeoncrawler

## What happened

During the `20260328-release-handoff-full-investigation`, the PM signoff artifact at `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260327-dungeoncrawler-release-b.md` was found to be pre-populated by the orchestrator with the following content:

> Status: signed-off  
> Signed by: orchestrator (coordinated release 20260326-dungeoncrawler-release-b shipped)

This artifact referenced the *prior* coordinated release (`20260326`) and was written before Gate 2 (QA unit test verification) was run for any of the 4 release-b features. The `release-signoff-status.sh` script showed `dungeoncrawler signoff: true`, which is misleading.

## Root cause

The orchestrator's release state machine wrote a signoff artifact when it processed the prior coordinated release close event, carrying the release ID forward incorrectly.

## Detection method

The PM performed a full investigation before trusting release state, noticed "Signed by: orchestrator" with a `20260326` reference in a `20260327` artifact, and flagged it immediately.

## Why this matters

If pm-forseti signoff had also been pre-populated, `release-signoff-status.sh` would have exited 0 with zero Gate 2 verification performed, enabling a push with unverified code.

## Prevention

1. **pm-dungeoncrawler seat instructions** now include: before recording or accepting a release signoff, verify that QA Gate 2 APPROVE evidence exists in `sessions/qa-dungeoncrawler/outbox/` for all scoped features. If the signoff artifact reads "Signed by: orchestrator" with a mismatched release reference, treat it as invalid.

2. **Correction flow:** After all Gate 2 APPROVEs are in, re-run `scripts/release-signoff.sh dungeoncrawler <release-id>` to replace the stale artifact with a real PM signoff.

3. **Signal to watch:** "Signed by: orchestrator" with a release ID different from the current cycle = stale/invalid artifact.

## Related
- `sessions/pm-dungeoncrawler/artifacts/release-handoffs/20260328-release-handoff-full-investigation-00-problem-statement.md`
- `knowledgebase/lessons/20260322-escalation-gates-rationale.md`
