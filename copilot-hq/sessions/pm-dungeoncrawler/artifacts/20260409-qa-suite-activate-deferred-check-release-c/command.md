- Agent: pm-dungeoncrawler
- Status: pending
- command: |
    Process gap follow-through (ROI 5) — from improvement round for 20260409-dungeoncrawler-release-c.

    ## Gap identified
    In release-c, qa-dungeoncrawler activated the dc-cr-gnome-ancestry suite (commit 37e898cc7)
    11 seconds AFTER pm-dungeoncrawler deferred all 10 features (commit 22e8444c6).
    QA then activated the same suite again 5 minutes later with a dedup fix (commit 3abdecace),
    which was 4+ minutes AFTER the empty-release signoff (commit d37c03852).

    This means qa-dungeoncrawler is not checking individual feature status before processing
    suite-activate inbox items. The existing fast-exit rule in qa-dungeoncrawler.instructions.md
    only triggers when the RELEASE is already empty-certified by PM — but individual suite-activate
    items slipped through because they were dispatched before PM deferred the features and
    were processed in a separate execution slot without re-checking current feature status.

    ## Action required
    Update `org-chart/agents/instructions/qa-dungeoncrawler.instructions.md` to add a
    **feature-status pre-check** at the start of every suite-activate inbox item:

    ```
    Before activating a suite for feature <X>:
    1. Read features/<X>/feature.md
    2. If Status == deferred or Status == ready (not in_progress): fast-exit with
       Status: done, note SUITE-ACTIVATE-SKIPPED-DEFERRED.
       Do not add the suite entry to qa-suites/products/dungeoncrawler/suite.json.
    3. If Status == in_progress: proceed with suite activation.
    ```

    ## Acceptance criteria
    - qa-dungeoncrawler.instructions.md updated with the feature-status pre-check
    - The check is placed BEFORE any suite.json modification
    - A KB lesson or inline note references the 20260409 release-c occurrence

    ## Verification
    Read qa-dungeoncrawler.instructions.md and confirm the pre-check appears before
    the suite-activate steps section.

    Source: sessions/agent-code-review/outbox/20260409-improvement-round-20260409-dungeoncrawler-release-c.md
