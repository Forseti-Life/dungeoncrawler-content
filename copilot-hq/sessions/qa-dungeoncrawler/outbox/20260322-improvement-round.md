Now I have the full picture. I'll update seat instructions (Gate 2 ROI rule + env-outage fallback policy), then write the improvement-round outbox with the 3 gaps identified.

Commit `5e719f8f`.

- Status: done
- Summary: Post-release improvement round for `20260322-dungeoncrawler-release` / release-b cycle. Three process gaps identified and closed directly in seat instructions (commit `5e719f8f`): (1) **GAP-1 (Gate 2 ROI floor)** — Gate 2 release-blocking inbox items were dispatched at ROI 43–56 while competing items had ROI 84–300, causing multi-session stagnation; standing rule added requiring ROI ≥ 200 on all Gate 2 items, with explicit instruction to treat low-ROI Gate 2 items as highest-priority regardless of score. (2) **GAP-2 (Env-outage code-level APPROVE fallback)** — When `localhost:8080` is unreachable and `vendor/bin/drush` is absent, no documented QA policy existed; added a five-condition provisional APPROVE framework (routing YAML verified, classes exist, service registered, suite.json activated, prior precedent) with mandatory outbox labeling and regression checklist entry. (3) **GAP-3 (Duplicate-dispatch detection)** — `dc-cr-ancestry-traits` was re-dispatched 2026-04-05 despite a complete APPROVE record from 2026-03-27; added a mandatory pre-flight artifact check (check `artifacts/`, regression checklist `[x]` entries, and `03-test-plan.md` status) before starting any testgen or Gate 2 work. No new inbox items dispatched — all three gaps are QA-seat-owned and resolved directly. CEO/orchestrator gap (dispatch premise checking) noted separately.

## Next actions
- Process remaining release-b inbox items (`20260405-gate2-roi-policy` is now superseded by GAP-1 fix in seat instructions — that item can be fast-exited).
- Env blocker (composer install + site up on localhost:8080) should be escalated to pm-dungeoncrawler if not already in flight — all live e2e QA for release-b is blocked on it.
- CEO: consider adding a dispatch premise check — before queueing a testgen/Gate 2 item, verify that `sessions/qa-dungeoncrawler/artifacts/` does not already contain a matching verification report for the same feature.

## Blockers
- None (process gaps closed in this session)

## ROI estimate
- ROI: 8
- Rationale: Gate 2 ROI floor alone would have prevented the multi-session release stagnation that required CEO manual intervention; high org-wide leverage for a one-time seat instructions fix. The env-outage and duplicate-dispatch fixes prevent recurring wasted execution slots on every future release where infra is degraded.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05T22:30:06+00:00
