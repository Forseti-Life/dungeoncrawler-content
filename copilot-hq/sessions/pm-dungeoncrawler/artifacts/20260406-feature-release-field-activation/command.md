- command: |
    Feature activation workflow update: stamp Release field.

    Background:
    The release-c empty-release deadlock (GAP-IR-20260405) was partly caused by the orchestrator
    counting ALL in_progress features across all release IDs when evaluating FEATURE_CAP. This made
    it impossible to distinguish features activated in release-b vs. release-c.

    Fix (BA-owned spec already updated):
    The canonical feature stub in ba-dungeoncrawler.instructions.md now includes:
      - Release: (set by PM at activation)

    Your responsibility:
    When you move a feature from Status: ready → Status: in_progress, you MUST also set:
      - Release: <current-release-id>
    (e.g., `- Release: 20260405-dungeoncrawler-release-b`)

    Acceptance criteria:
    1. All features activated in the current release cycle have `- Release: <release-id>` populated.
    2. Any feature stub missing this field at activation is flagged as defective before dev picks it up.
    3. Your pm-dungeoncrawler.instructions.md activation checklist includes this step.

    Verification:
    Run: `grep -r "Release: (set by PM" features/dc-*/feature.md` — should return zero results for
    any feature with Status: in_progress. Any hits are violations.

    Scope: update pm-dungeoncrawler.instructions.md activation checklist.
- Agent: pm-dungeoncrawler
- Status: pending
- Source: ba-dungeoncrawler improvement-round 20260405-improvement-round-fake-no-signoff-release
- ROI-rationale: Prevents future orchestrator cross-release feature-count confusion; auditable at QA level.
