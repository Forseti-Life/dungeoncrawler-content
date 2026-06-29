- command: |
    Post-release data cleanup (release-b gap review — GAP-RB-02):

    CONTEXT: During the close of `20260406-dungeoncrawler-release-b`, 11 features were
    deferred back to `Status: ready`. However, 4 of those features still show
    `Status: in_progress` with a stale release ID (`20260319-dungeoncrawler-release-b`).
    They were NOT properly reset during the deferred-close. Their orphaned state does not
    count against the current release cap (thanks to `04e29e01` fix) but their status is
    incorrect and they appear as abandoned work in progress.

    ORPHANED FEATURES (verify each):
    - features/dc-cr-action-economy/feature.md — Status: in_progress, Release: 20260319-dungeoncrawler-release-b
    - features/dc-cr-ancestry-system/feature.md — Status: in_progress, Release: 20260319-dungeoncrawler-release-b
    - features/dc-cr-dice-system/feature.md — Status: in_progress, Release: 20260319-dungeoncrawler-release-b
    - features/dc-cr-difficulty-class/feature.md — Status: in_progress, Release: 20260319-dungeoncrawler-release-b

    TASKS:
    1. For each orphaned feature, determine correct state:
       - If it has dev commits and QA work in the current release-c: update Release: to
         `20260406-dungeoncrawler-release-c` (leave Status: in_progress).
       - If it has NO dev commits for the current cycle: set Status: ready (clear Release: or
         set to the current release-c once activated).
    2. Commit the corrected feature.md files with message:
       "chore(features): reset orphaned in_progress features from release-b deferred close"
    3. Verify: `grep "Status:\|Release:" features/dc-cr-action-economy/feature.md` etc.

    ACCEPTANCE CRITERIA:
    - All 4 features show either: (a) Status: in_progress with Release: 20260406-dungeoncrawler-release-c,
      OR (b) Status: ready.
    - No feature shows Status: in_progress with Release: 20260319-dungeoncrawler-release-b.
    - Changes committed.

    ROI: 40
    Rationale: Orphaned in_progress state causes false signal in audit tools and KPI dashboards;
    quick data cleanup prevents repeat confusion in the next release cycle.
- Agent: pm-dungeoncrawler
- Status: pending
