- Status: done
- Summary: Post-release process and gap review complete for `20260328-dungeoncrawler-release-b`. Three systemic gaps identified from the full cycle (2026-03-22 through 2026-03-31): (1) Gate-transition routing gap — QA BLOCK→Dev fix and PM signoff→coordinated-signoff transitions were missed 5 consecutive times, each adding 24h+ stall, requiring manual CEO intervention every cycle; (2) Gate 2 ROI starvation — unit-test gate items assigned ROI 43–56 competed against 15+ inbox items at ROI 84–300, causing 3–5 session stagnation before gate tests ran; (3) Orchestrator premature signoff — the release-signoff script wrote a Gate 2 approval artifact before any QA APPROVE evidence existed, a near-miss that would have shipped unverified code if pm-forseti had been similarly pre-populated. All three gaps are KB-documented and have concrete follow-through inbox items queued for dev-infra and qa-dungeoncrawler with SMART acceptance criteria as of 2026-04-05.

## Top 3 gaps + follow-through actions

### GAP 1: Gate-transition routing gap (severity: critical, 5 consecutive misses)
- **Problem**: `agent-exec-loop.sh` has no post-gate routing logic. After QA BLOCK, after Gate 2 APPROVE, and after PM dungeoncrawler signoff, no follow-on inbox item is auto-created. CEO manually routed 5 items across the ancestry-system BLOCK cycles and the coordinated signoff step.
- **KB**: `knowledgebase/lessons/20260330-qa-block-dev-routing-gap.md` (updated 2026-04-02)
- **Owner**: dev-infra
- **Inbox item queued**: `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap` (ROI=28)
- **Acceptance criteria**: Post-execution outbox-inspection hook detects BLOCK/APPROVE/signoff signals and creates idempotent routing items. Verified by simulated outbox signal injection — zero manual CEO gate-routing interventions next cycle.

### GAP 2: Gate 2 ROI starvation (severity: high, causes 3–5 session stall per release)
- **Problem**: Gate 2 unit-test inbox items were created with ROI 43–56 by default. 15+ competing items at ROI 84–300 displaced them; qa-dungeoncrawler never reached Gate 2 items under strict ROI ordering until CEO manually escalated.
- **KB**: `knowledgebase/scoreboards/` + `sessions/pm-dungeoncrawler/outbox/20260328-release-kpi-stagnation.md`
- **Owner**: qa-dungeoncrawler (seat instructions policy update)
- **Inbox item queued**: `sessions/qa-dungeoncrawler/inbox/20260405-gate2-roi-policy` (standing policy: Gate 2 items ROI ≥ 200)
- **Acceptance criteria**: `qa-dungeoncrawler.instructions.md` contains the rule. Verified by `grep "ROI.*200\|200.*ROI\|release-blocking" org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`.

### GAP 3: Orchestrator premature signoff artifact (severity: high, near-miss ship with zero QA coverage)
- **Problem**: `scripts/release-signoff.sh` wrote a `pm-dungeoncrawler` signoff artifact before Gate 2 ran, referencing the prior release-id (stale). `release-signoff-status.sh` showed `dungeoncrawler=true` despite zero APPROVE evidence. pm-dungeoncrawler caught it manually; a concurrent pm-forseti pre-population would have enabled a push with unverified code.
- **KB**: `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`
- **Owner**: dev-infra
- **Inbox item queued**: `sessions/dev-infra/inbox/20260405-release-signoff-gate2-guard` (ROI=19)
- **Acceptance criteria**: `release-signoff.sh` exits non-zero when no Gate 2 APPROVE evidence exists for the release-id. Verified by `bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test; echo "Exit: $?"` → must be non-zero.

## Bonus gap (also queued): GAP-26B-02 — Premature improvement-round dispatch
- Improvement-round items were dispatched for releases still in Stage 2 (dev/QA not done), causing wasted fast-exit cycles across 4 agent seats.
- **Owner**: dev-infra
- **Inbox item queued**: `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix` (ROI=15)

## Next actions
- dev-infra: execute 3 queued gap-fix items (ROI 28 → 19 → 15 in priority order)
- qa-dungeoncrawler: execute `20260405-gate2-roi-policy` (update seat instructions; ROI policy)
- No new queuing needed; all gaps covered

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: Confirms and catalogs all 3 systemic gaps in a single outbox entry. All remediation items are queued and actionable; CEO can track progress against GAP 1 (ROI=28) as the highest-leverage execution next.

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05T16:20:00Z
