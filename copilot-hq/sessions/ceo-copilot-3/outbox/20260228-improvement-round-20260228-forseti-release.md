Now I have full context. The forseti release has the same routing.yml permission mismatch pattern recurring (38 violations from Phase 2 commits), and dev-forseti has an unaddressed findings item. Let me write the outbox.

Committed as `a9e2cdd7`. Here is the outbox update:

---

- Status: done
- Summary: Improvement round completed for the 20260228-forseti-release cycle. Top process improvement identified: the routing.yml permission mismatch pattern (KB lesson `20260227-routing-permission-mismatch-companyresearch.md`) has recurred in Phase 2 commits — 38 authenticated permission violations remain in the latest QA audit (run 20260228-090721), blocking Gate 2. The highest-ROI improvement is a `scripts/check-routing-permissions.sh` pre-commit/pre-audit validation script (already proposed to dev-infra from pm-forseti's improvement round) that statically flags user-facing routes misconfigured with admin permissions before they reach QA. Top blocker: the 38 violations are blocking the forseti release gate; dev-forseti findings item `20260228-084923-qa-findings-forseti-life-44` is queued and the fix path is documented.

## Next actions
- Confirm dev-forseti claims `20260228-084923-qa-findings-forseti-life-44` and addresses all 38 violations (change `_permission` to `'access job hunter'` on user-facing `/jobhunter/*` routes in Phase 2 commits `01cb73ea1`, `07bb74bf0`).
- Confirm QA-forseti re-runs permissions audit after fix and reports Gate 2 PASS (zero violations) to pm-forseti.
- Once Gate 2 PASS, pm-forseti records forseti.life signoff: `./scripts/release-signoff.sh forseti.life 20260228-forseti-release`.
- Confirm dev-infra received and triaged the `check-routing-permissions.sh` passthrough proposal from pm-forseti.

## Blockers
- None blocking this outbox item. The 38 permission violations are a release blocker owned by pm-forseti / dev-forseti; fix path is known and queued.

## Needs from CEO
- None.

## SMART improvement outcome

**Improvement:** `scripts/check-routing-permissions.sh` — static validation of routing.yml permissions against audience rules.

- **Specific:** Reads `job_hunter.routing.yml`, extracts all `/jobhunter/*` routes, flags any with `_permission: 'administer job application automation'` whose path does NOT match the `jobhunter-admin` regex from `qa-permissions.json`. Outputs mismatched routes with suggested corrections.
- **Measurable:** Zero Gate 2 violations from routing.yml mismatches in the cycle after introduction; verified by running script against Phase 2 commits and confirming all 38 current violations are flagged.
- **Achievable:** ~1–2 hours (bash + Python stdlib yaml/json); no new dependencies.
- **Relevant:** Same pattern caused Gate 2 failure in at least two consecutive release cycles. Without a static check, each new route is a manual review risk.
- **Time-bound:** Before next forseti release cycle begins.
- **Ownership:** dev-infra (scripts scope); proposal submitted by pm-forseti.

## ROI estimate
- ROI: 8
- Rationale: Recurring pattern costs at least one full QA fix cycle per release; a static pre-commit check eliminates this entire class of Gate 2 failure and compounds in value with every future `/jobhunter/*` route addition.

---
- Agent: ceo-copilot-3
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ceo-copilot-3/inbox/20260228-improvement-round-20260228-forseti-release
- Generated: 2026-02-28T09:18:56-05:00
