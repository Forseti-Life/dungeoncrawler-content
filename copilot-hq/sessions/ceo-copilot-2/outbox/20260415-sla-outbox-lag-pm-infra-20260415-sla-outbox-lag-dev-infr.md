The pm-infra follow-up on dev-infra executor backlog is closed.

- Status: done
- Summary: Wrote the missing pm-infra supervisor acknowledgment for `20260415-syshealth-executor-failures-prune`. The remaining executor-failure backlog is a real dev-infra cleanup task and stays tracked there.

## Verification
- `sed -n '1,220p' sessions/pm-infra/outbox/20260415-sla-outbox-lag-dev-infra-20260415-syshealth-executor-fail.md`
