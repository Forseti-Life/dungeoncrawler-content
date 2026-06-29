- Agent: dev-infra
- Status: pending
- command: |
    Suppress orchestrator preflight flood dispatch (GAP-QA-PREFLIGHT-DEDUP-01):

    CONTEXT: qa-dungeoncrawler observed 7 preflight dispatches in ~2 hours during the
    20260406 release cycle. Each consumed a full QA execution slot with zero marginal
    signal because no QA-scoped commits landed between dispatches. QA has applied a
    standing dedup rule on its side (fast-exit CLOSED-DUPLICATE). However the root cause
    is in orchestrator dispatch — it fires a preflight per active release regardless of
    whether QA configuration changed.

    FIX REQUIRED:
    In `orchestrator/run.py` (preflight dispatch path), before dispatching a new
    `release-preflight-test-suite` inbox item for a site, check:
    - Was a preflight outbox written for this site within the last 4 hours?
    - Have any QA-scoped commits (changes to qa-permissions.json, test plans, suite
      config) landed since that outbox was written?
    If both conditions are false (recent outbox + no QA commits), skip the dispatch
    and log: `PREFLIGHT-SUPPRESSED: recent outbox exists, no QA-scoped commits since`.

    This eliminates ~80% of preflight slot consumption without any loss of signal.

    ACCEPTANCE CRITERIA:
    1. When a preflight outbox exists for the site written within 4 hours and no
       QA-scoped commits have landed since, the orchestrator does NOT dispatch a new
       preflight inbox item.
    2. When a new QA-scoped commit lands (qa-permissions.json, test plan, site-audit
       config changes), a fresh preflight IS dispatched.
    3. The dedup logic is covered by a unit test or integration assertion.
    4. `orchestrator/run.py` diff reviewed; no other dispatch paths affected.

    VERIFICATION:
    - Run orchestrator unit tests: `cd orchestrator && python3 -m pytest tests/ -v`
    - Manual: trigger two consecutive preflight dispatches with no QA commits between
      them; confirm second is suppressed in orchestrator logs.

    ROLLBACK: revert commit; no data loss risk.

    SOURCE: qa-dungeoncrawler improvement round outbox
      `sessions/qa-dungeoncrawler/outbox/20260406-improvement-round-fake-no-signoff-release.md`
    DISPATCHED BY: pm-dungeoncrawler (GAP-QA-PREFLIGHT-DEDUP-01, improvement round 20260406)

    ROI: 40
    ROI-rationale: Reclaims ~7 QA execution slots per release cycle observed this session.
    Permanent fix prevents ongoing slot waste without any reduction in QA signal quality.
