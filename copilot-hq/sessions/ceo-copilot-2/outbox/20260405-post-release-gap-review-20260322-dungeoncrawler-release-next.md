- Status: done
- Summary: Post-release gap review for 20260322-dungeoncrawler-release-next. Identified 3 process gaps from this session — CEO acting as dev, HQ scripts with stale server paths, and LangGraph dashboard disconnected from real telemetry. Delegated all three as inbox items to the correct owning seats with acceptance criteria.

## Gaps identified

### Gap 1: CEO bypassed release cycle — acted as dev agent
**What happened:** CEO made direct code changes to AIApiService.php, ChatController.php, engine.py, and 15+ HQ scripts instead of creating inbox items for dev-forseti, dev-infra, and dev-forseti-agent-tracker. This happened because (a) user escalated a production outage directly, and (b) there was no clear "hotfix" lane in the release process.

**Follow-through action:** Inbox items created for pm-forseti, pm-forseti-agent-tracker, and dev-infra to review, test, and formally close the CEO-applied hotfixes. Each item has acceptance criteria and a verification method.

**Owner:** pm-forseti (forseti fixes), pm-forseti-agent-tracker (LangGraph), dev-infra (HQ scripts)

**Acceptance criteria:** All 3 delegated items produce verified outbox with QA sign-off. No CEO-applied changes remain unreviewed after the current release cycle.

### Gap 2: HQ scripts had stale server paths (/home/keithaumiller)
**What happened:** Server migrated from /home/keithaumiller to /home/ubuntu. 15+ scripts had hardcoded old paths. publish-forseti-agent-tracker.sh failed silently for weeks (FORSETI_SITE_DIR pointed to wrong location). This means agent telemetry was NOT being published to the Drupal dashboard.

**Root cause:** No centralized path config. Each script hardcoded its own paths.

**Follow-through action:** Inbox item created for dev-infra (20260405-hq-script-path-migration) to audit all remaining paths AND create scripts/lib/site-paths.sh as a single source of truth for site directory paths.

**Owner:** dev-infra
**Acceptance criteria:** Zero hardcoded /home/ paths remain in scripts/; site-paths.sh sourced by top 5 scripts; publish script confirmed working.

### Gap 3: LangGraph dashboard was dark (no telemetry files written)
**What happened:** The Drupal LangGraph Dashboard at /admin/reports/copilot-agent-tracker/langgraph reads from langgraph-ticks.jsonl and langgraph-parity-latest.json, but the orchestrator engine never wrote these files. COPILOT_HQ_ROOT was also not set in Apache's environment, so the dashboard fell back to the wrong HQ path.

**CEO hotfixes applied (needs verification):**
- Added COPILOT_HQ_ROOT=SetEnv to Apache forseti-le-ssl.conf
- Added _write_tick_telemetry() to engine.py to write ticks + parity JSON after each tick

**Follow-through action:** Inbox item created for pm-forseti-agent-tracker (20260405-langgraph-telemetry-integration) to verify the telemetry pipeline, confirm dashboard panels show real data, and produce a prioritized list of which LangGraphConsoleStub pages to implement next.

**Owner:** pm-forseti-agent-tracker → dev-forseti-agent-tracker
**Acceptance criteria:** After one orchestrator tick, both files exist and the Session Health + Parity dashboard panels show real data.

## Next actions
- Run orchestrator tick once to generate telemetry files and re-publish agent tracker
- Monitor dev-infra, pm-forseti, pm-forseti-agent-tracker inbox items for completion
- Once pm-forseti closes the Bedrock fix review, advance forseti release cycle to next stage

## Needs from CEO
- None currently — all items delegated

## Decision needed
- Board: Should the CEO have a "hotfix" authority lane that allows direct dev action on production outages, with a mandatory 24h review inbox item to the PM? Or should all production changes, even outages, go through PM first with a max 1h SLA? Recommend: hotfix lane with mandatory same-session review delegation (what we did here).
