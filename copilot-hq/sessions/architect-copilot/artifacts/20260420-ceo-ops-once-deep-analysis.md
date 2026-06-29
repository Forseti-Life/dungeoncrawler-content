# CEO-OPS-ONCE — DEEP ANALYSIS

## Executive Summary
**ceo-ops-once**: Every 2 hours, runs comprehensive health check suite
**Status**: ✅ WORKING
**Necessity**: CONDITIONAL on whether orchestrator duplicates this work
**Recommendation**: INVESTIGATE orchestrator overlap, then decide consolidation

---

## What It Actually Does

Runs 9 health checks + generates recommendations:

1. **Priority Rankings** — Reads org-chart/priorities.yaml (weights for work prioritization)
2. **HQ Status** — Git merge conflicts, branch health via hq-status.sh
3. **CEO Inbox Scan** — Lists sessions/ceo-copilot-2/inbox/ items
4. **Gate 2 Backstop** — Clean-audit dry-run (no actual prod QA yet)
5. **Release Health** — Gate 2 approvals, PM signoffs, SLA state via ceo-release-health.sh
6. **Project Registry Link Audit** — Project linkage compliance
7. **System Health** — Orchestration warnings, runtime issues via ceo-system-health.sh
8. **Blockers Report** — Extracts latest blocker from each agent's outbox
9. **Suggested Actions** — AI-generated recommendations based on health status

### Example Actual Output (from today's run)
```
= Priority rankings =
...

== HQ status ==
...

== CEO inbox ==
...

== Release health ==
❌ Release health failed: review missing Gate 2 approvals, PM signoffs, cross-team signoffs

== System health ==
❌ System health failed: orchestration/runtime warnings...
   📥 Dispatched 3 item(s) to downstream teams

== CEO actions suggested ==
- Release health failed: review missing Gate 2 approvals
- System health failed: review orchestration/runtime warnings
- HQ status failed: clear merge/integration blockers
- Escalation compliance: 5 blocked items missing 'Matrix issue type'
```

---

## Purpose in Ecosystem

**Historical Purpose** (pre-orchestrator):
- Weekly/bi-weekly health snapshot for CEO
- Identified blockers and needed actions
- Acted as health dashboard

**Current Purpose** (with orchestrator):
- Still provides 2-hour health snapshot
- Auto-dispatches items to teams (e.g., qa-dungeoncrawler for stale audits)
- Generates CEO action recommendations
- Feeds blockers report to Keith

---

## Does Orchestrator Already Do This?

### Investigation Required
Need to check: orchestrator/run.py `publish` phase

**What I found:**
- Orchestrator has `publish` phase that "push telemetry to Drupal dashboard"
- Orchestrator has `health_check` subsystem (unknown scope)
- Orchestrator has `kpi_monitor` phase
- Orchestrator mentions "release_cycle" and "coordinated_push"

**Unknown:**
- Does `health_check` phase run the same shell scripts?
- Does `kpi_monitor` subsume the metrics collection?
- Is there an orchestrator "CEO health report" equivalent?
- Does orchestrator do the auto-dispatch to teams?

### Key Script Dependencies
These scripts are called by ceo-ops-once:
- `./scripts/hq-status.sh` — Git merge conflicts
- `./scripts/gate2-clean-audit-backstop.py` — Clean audit dry-run
- `./scripts/ceo-pipeline-remediate.py` — Release pipeline fixes
- `./scripts/project-registry-link-audit.py` — Project linkage
- `./scripts/ceo-release-health.sh` — Gate 2 state
- `./scripts/ceo-system-health.sh` — Orchestration warnings
- `./scripts/hq-blockers.sh` — Blocker extraction
- `./scripts/escalation-matrix-compliance.sh` — Matrix audit

**Question**: Are these scripts also called by orchestrator?

---

## Frequency Analysis

**Current**: Every 2 hours (0 */2 * * *)
**Runs per day**: 12
**Cost**: ~5-10 seconds per run (multiple shell scripts + Python)
**Total CPU**: ~60-120 seconds/day (negligible)

**Is 2-hour frequency appropriate?**
- ✅ For health snapshots: YES (every 2h is good)
- ✅ For team dispatch: YES (timely but not spammy)
- ❓ For real-time escalation: NO (should be event-driven)

---

## Current Status: Working Well

Example from today (2026-04-20 14:53):
- ✅ Git merge health checks passed
- ✅ Release health detected issues (missing Gate 2)
- ✅ System health detected issues (executor failures)
- ✅ Auto-dispatched 3 items to downstream teams:
  - sessions/dev-infra/inbox/20260420-syshealth-executor-failures-prune
  - sessions/dev-infra/inbox/20260420-syshealth-merge-health-remediation
  - sessions/qa-dungeoncrawler/inbox/20260420-syshealth-audit-stale-qa-dungeoncrawler

---

## Critical Question: Overlap with Orchestrator?

### Hypothesis 1: Orchestrator Already Does This
**Evidence**:
- orchestrator/run.py has `health_check`, `kpi_monitor`, `publish` phases
- Orchestrator mentions "release_cycle" (same concept as Gate 2)

**If True**: ceo-ops-once is REDUNDANT
- Solution: Remove cron job, let orchestrator handle it
- Trade-off: Less predictable 2-hour snapshot (depends on orchestrator tick)

### Hypothesis 2: Orchestrator & ceo-ops-once Are Complementary
**Evidence**:
- ceo-ops-once imports many external scripts
- Orchestrator may not call these specific scripts
- Different frequency (orchestrator tick vs 2-hour cron)

**If True**: Both are USEFUL
- Keep cro cron as health heartbeat
- Keep orchestrator for real-time monitoring
- Remove redundancy but coordinate outputs

### Hypothesis 3: ceo-ops-once Predates Orchestrator
**Evidence**:
- Comments in run.py: "Single process replacing: ceo-inbox-loop, inbox-loop, ceo-health-loop, 2-ceo-opsloop..."
- Suggests orchestrator was built to unify scattered CEO health checks

**If True**: ceo-ops-once Should Be RETIRED
- Orchestrator should handle all health checking
- cron job is legacy and should be removed
- But requires coordinated migration

---

## Risks of Keeping Both

1. **Dual Reporting**: Keith might see duplicate alerts
2. **Inconsistent Findings**: If scripts change, orchestrator might have stale logic
3. **Maintenance Burden**: Fixes need to be applied in two places
4. **Wasted Cycles**: Unnecessary redundancy (2 health checks every 2h)

---

## Risks of Removing Without Orchestrator Guarantee

1. **Gap in Health Visibility**: If orchestrator doesn't actually call these scripts
2. **Lost Auto-Dispatch**: Teams might not get notified of issues
3. **No Fallback**: If orchestrator fails, no health check runs

---

## Recommendation: INVESTIGATE FIRST

Do NOT retire without understanding orchestrator coverage.

**Action Items**:
1. [ ] Search orchestrator/run.py for calls to hq-status.sh, ceo-system-health.sh, etc
2. [ ] Check if orchestrator has equivalent of `ceo_ops_once` logic
3. [ ] Understand when orchestrator health_check phase runs
4. [ ] Ask user: "What's the intended architecture?"

**Pending User Decision**:
- Option A: Keep both (distributed model)
- Option B: Retire ceo-ops-once (unified orchestration model)
- Option C: Consolidate (hybrid with orchestrator as primary)

---

## Temporary Recommendation: KEEP AS-IS

Until you clarify orchestrator coverage, keep ceo-ops-once running because:
1. It's working correctly
2. It provides a known, reliable health check
3. Removing it risks losing team notifications
4. Can be retired after orchestrator consolidation is confirmed

