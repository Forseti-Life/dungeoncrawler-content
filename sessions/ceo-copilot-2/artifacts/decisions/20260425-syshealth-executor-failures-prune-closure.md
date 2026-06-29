# Decision: Close syshealth-executor-failures-prune recurring QA item

- Issue: Orchestrator kept re-creating QA test items for `20260416-syshealth-executor-failures-prune` even after qa-infra approved on 2026-04-17
- Root cause: Orchestrator lacks acceptance/closure mechanism for verified dev items; phantom QA tasks are re-queued with new dates
- Status of underlying work: DONE (dev-infra completed pruning on 04-16, qa-infra approved on 04-17)
- Latest executor action: Quarantined 04-25 version because qa-infra produced needs-info response to repeated work

## Decision

**Closure: This item chain is complete and should not be re-queued.**

- dev-infra work: DONE (verified 04-16, committed 0185dd967 + 027a25687)
- qa-infra verification: DONE (approved 04-17)
- Phantom dispatch issue: This is an orchestrator bug, not a QA issue

## Action

Archive all future attempts to dispatch this item to qa-infra. The work is verified.

If similar patterns emerge for other dev-infra items, escalate to dev-infra with RCA: "Orchestrator re-creates QA items for already-verified work; need acceptance/closure gate."

## Rationale

- Keeping phantom QA items in circulation wastes execution cycles and masks real blockers
- qa-infra correctly tried to escalate (04-23 needs-info response) when given a duplicate task
- This is dev-infra queue management issue, not qa-infra capability issue
