- Status: done
- Completed: 2026-04-12T07:34:37Z

- Agent: pm-dungeoncrawler
- Status: pending
- command: |
    Process improvement — scope activation cap policy (GAP-DC-PM-SCOPE-CAP-COLLISION-01 + GAP-DC-PM-SCOPE-UNBUILT-01).

    **Background:**
    In `20260412-dungeoncrawler-release-b`, PM activated 10 features simultaneously.
    This instantly hit the 10-feature auto-close cap, firing `release-close-now`
    before dev/QA had any execution slots. All 10 features were deferred; 0 shipped.
    Additionally, 5 of the 10 activated features had no dev outbox (unbuilt), consuming
    cap slots that could have gone to QA-ready features.

    **Acceptance criteria:**
    1. Update `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`:
       - Add a "Scope activation ordering" rule: at scope-activate time, prioritize
         features with a confirmed dev outbox (dev-complete). Activate those first.
         Only activate unbuilt features after all dev-confirmed features are activated
         and cap slots remain.
       - Add a "Soft cap" rule: do NOT activate more than 7 features in a single batch.
         Hold ≥3 slots as delivery runway. (Auto-close fires at 10; shipping 1-2 features
         before close requires room to work.) Exception: if all features in the backlog
         are dev-confirmed AND QA unit tests already pass, filling to 9 is permitted.
    2. Verify the rules are consistent with the existing pre-QA dev delivery gate
       (GAP-DC-PM-PRE-QA-DISPATCH-01) and do not conflict.
    3. Commit the instruction update and include the commit hash in your outbox.

    **Verification method:**
    - Read the updated instructions section and confirm both rules (ordering + soft cap) are present.
    - No script to run — this is an instruction change only.

    **ROI:** 12
    **Rationale:** In release-b, 10 features activated → 0 shipped (100% deferral rate).
    Introducing a soft cap and dev-first ordering is expected to reduce deferral rate
    significantly. Each deferred feature costs ≥1 extra release cycle of latency.
