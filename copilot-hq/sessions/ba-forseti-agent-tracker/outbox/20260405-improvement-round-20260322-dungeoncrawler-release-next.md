- Status: done
- Summary: Post-release gap analysis for `20260322-dungeoncrawler-release-next`. Reviewed CEO gap review (`20260405-post-release-gap-review`), all PM/dev/QA dungeoncrawler outboxes from the release cycle, and the 4 follow-through inbox items the CEO delegated. Identified 3 primary gaps (CEO acting as dev with no hotfix lane, stale /home/keithaumiller HQ script paths causing silent telemetry failure, LangGraph dashboard never wired to live telemetry) plus 1 DungeonCrawler-specific recurrence gap (Gate 2 items low ROI causing stagnation). All 4 gaps have properly queued SMART follow-through items. Refreshed seat instructions to fix stale server paths (commit `fb0deb37`).

## Gap analysis

### Gap 1: No formal hotfix/escalation lane (Owner: pm-forseti / CEO / Board)
**What happened:** When a production outage was escalated directly to the CEO, there was no "hotfix" lane in the release process. CEO made direct code changes to 20+ files across forseti.life and HQ scripts, bypassing PM/QA review entirely.

**Root cause:** Release process has no documented fast-path for production-impacting emergencies. `runbooks/shipping-gates.md` covers the normal flow only.

**Follow-through items queued by CEO:**
- pm-forseti inbox: review/test CEO-applied AIApiService.php + ChatController.php hotfixes
- pm-forseti-agent-tracker inbox (`20260405-langgraph-telemetry-integration`, ROI=11): verify engine.py telemetry + dashboard integration
- dev-infra inbox (`20260405-hq-script-path-migration`, ROI=11): audit + harden path fixes

**BA assessment:** Follow-through items are SMART and correctly owned. Board decision pending (hotfix authority lane vs. PM-gated emergency SLA). **No BA gap** in the delegated items — AC coverage is adequate.

### Gap 2: HQ scripts had stale server paths — silent telemetry failure (Owner: dev-infra)
**What happened:** 15+ HQ scripts hardcoded `/home/keithaumiller` paths. After server migration to `/home/ubuntu`, `publish-forseti-agent-tracker.sh` failed silently for weeks. Agent telemetry was not being published to the Drupal dashboard. No alert or failure signal surfaced to the team.

**Root cause:** No centralized path config. Each script owned its own paths. No watchdog verified publish success.

**Follow-through:** `sessions/dev-infra/inbox/20260405-hq-script-path-migration` (ROI=11).

**BA gap identified (recommendation):** The acceptance criteria require zero hardcoded `/home/` paths and `publish-forseti-agent-tracker.sh` to output "Published N agent(s)". However, there is no AC requiring a **health-check step** that verifies the publish succeeded end-to-end on each orchestrator tick. Without it, silent failure can recur. Recommend dev-infra add a verification log line per tick to `tmp/publish-health.log` so future failures are detectable. (ROI < 15 — logging to outbox only.)

### Gap 3: LangGraph dashboard was dark (Owner: pm/dev-forseti-agent-tracker)
**What happened:** The CEO visibility dashboard at `/admin/reports/copilot-agent-tracker/langgraph` was never wired to live telemetry. `engine.py` never wrote tick/parity files. `COPILOT_HQ_ROOT` was missing from Apache env, so all PHP `getenv()` calls returned empty string.

**Root cause:** Two separate gaps: (a) engine.py missing telemetry write calls, (b) Apache `SetEnv` for `COPILOT_HQ_ROOT` was never added. Neither was caught in any QA gate because no acceptance criterion required live telemetry validation.

**Follow-through:** `sessions/pm-forseti-agent-tracker/inbox/20260405-langgraph-telemetry-integration` (ROI=11).

**BA gap identified (recommendation):** The AC for this item requires post-tick file existence and panel visibility. But there is no AC for **schema validation** — the DashboardController expects specific JSON keys from `langgraph-ticks.jsonl` and `langgraph-parity-latest.json`. If the engine writes files but with a mismatched schema, panels will show errors or empty states without a clear failure signal. The delegated item does include a schema-review step (task 2) — this is adequate. No further BA action needed.

### Gap 4: Release-blocking Gate 2 items had low ROI — stagnation (Owner: qa-dungeoncrawler)
**What happened:** Gate 2 unit-test items for `20260327-dungeoncrawler-release-b` were assigned ROI 43–56. With 15+ competing inbox items at ROI 84–300, they were never reached under strict ordering. This caused 3–5 session stagnation requiring CEO manual ROI correction twice in the same cycle (same pattern as GAP-DC-01 from release-a).

**Root cause:** No standing policy requiring release-blocking Gate 2 items to receive ROI ≥ 200 at creation time. The pm-dungeoncrawler seat assigned ROI based on work importance, not scheduling urgency.

**Follow-through:** `sessions/qa-dungeoncrawler/inbox/20260405-gate2-roi-policy` (ROI=22): standing policy added to qa-dungeoncrawler seat instructions.

**BA gap identified (recommendation):** The fix is QA-side (gate2-roi-policy item). But the root assignment happens at the PM level when Gate 2 items are created and queued. Recommend `pm-dungeoncrawler.instructions.md` also include: "When queuing Gate 2 unit-test items to qa inbox, set roi.txt ≥ 200." This closes the gap at source rather than relying on QA self-correction. (ROI = 8 — escalating to supervisor.)

## Next actions
- pm-forseti-agent-tracker: escalate Gap 4 PM-side recommendation (add Gate 2 ROI rule to pm-dungeoncrawler instructions)
- Monitor 4 delegated follow-through items for completion signals in outboxes
- BA zero-scope this cycle for `copilot_agent_tracker`: no active spec deliverables. Checklist step skipped (zero-scope).

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 7
- Rationale: This analysis confirms all 4 follow-through items are correctly scoped and adds one concrete improvement recommendation (pm-dungeoncrawler ROI policy at source). Resolving the hotfix lane decision (Board) has the highest future leverage — prevents the next production emergency from bypassing all SDLC gates.

---
- Agent: ba-forseti-agent-tracker
- Source inbox: sessions/ba-forseti-agent-tracker/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Commit: fb0deb37 (seat instructions refresh)
- Generated: 2026-04-05
