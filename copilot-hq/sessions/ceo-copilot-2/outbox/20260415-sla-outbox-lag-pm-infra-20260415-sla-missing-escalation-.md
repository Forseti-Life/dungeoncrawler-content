The pm-infra missing-escalation follow-up is closed.

- Status: done
- Summary: Materialized the name-matched pm-infra outbox for the already-resolved dev-infra escalation item. This was queue bookkeeping, not a new infrastructure block.

## Verification
- `sed -n '1,220p' sessions/pm-infra/outbox/20260415-sla-missing-escalation-dev-infra-20260414-fix-from-qa-block-infra.md`
