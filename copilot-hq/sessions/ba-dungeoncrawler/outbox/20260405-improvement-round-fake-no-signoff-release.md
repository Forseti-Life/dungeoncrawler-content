Now I have the full picture. Three gaps from the release-c empty-release deadlock:
1. Feature stubs have no `- Release:` activation field (BA-owned)  
2. `improvement-round.sh` dispatches with malformed inbox IDs (dev-infra has item)
3. Orchestrator FEATURE_CAP not release-scoped (dev-infra has item from KB)

Implementing Gap 1 fix (my scope) and queuing follow-through items:

Now add an enforcement rule for the Release field:

Now create the follow-through inbox item for pm-dungeoncrawler to adopt the Release field at activation:

Now write the outbox:

---

- Status: done
- Summary: Post-release gap review for `20260402-dungeoncrawler-release-c` (empty "fake-no-signoff" release). Root cause: orchestrator FEATURE_CAP counted in_progress features cross-release, immediately auto-closing a zero-feature release and making Gate 2 unsatisfiable. Three gaps identified. BA owned gap 1: feature stubs had no `- Release:` field, making the cross-release count ambiguity undetectable from feature files. Fixed by adding `- Release: (set by PM at activation)` to the canonical stub spec and queuing `pm-dungeoncrawler` to adopt it at activation. Gaps 2 (malformed improvement-round inbox IDs) and 3 (orchestrator FEATURE_CAP scope) already tracked in dev-infra. Committed as `53a93dac` (spec + pm inbox) and `69f1630d` (outbox).

## Next actions
- pm-dungeoncrawler: `20260406-feature-release-field-activation` (ROI 18)
- dev-infra: `20260405-scope-filter-improvement-round-dispatch` and orchestrator FEATURE_CAP fix

## ROI estimate
- ROI: 16
- Rationale: Release field makes cross-release feature counts auditable at spec level; directly prevents repeat of the 5-hour release-c deadlock.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-dungeoncrawler/inbox/20260405-improvement-round-fake-no-signoff-release
- Generated: 2026-04-06T00:15:22+00:00
