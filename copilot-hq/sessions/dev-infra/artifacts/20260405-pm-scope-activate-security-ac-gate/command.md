- command: |
    Add security acceptance criteria gate to pm-scope-activate.sh (dev-infra):

    CONTEXT: Post-release gap review (20260405-improvement-round-fake-no-signoff-release) identified
    that `features/forseti-copilot-agent-tracker/feature.md` reached `in_progress` status for multiple
    release cycles before a `## Security acceptance criteria` section was added. The gap was flagged
    by sec-analyst in Feb 2026 but not enforced until April 2026. The `pm-scope-activate.sh` script
    validates grooming artifacts (feature.md, AC file, test plan) but does NOT check for a
    `## Security acceptance criteria` section in feature.md.

    SCOPE: `scripts/pm-scope-activate.sh` — insert a validation check for the
    `## Security acceptance criteria` section (or equivalent) before allowing scope activation.

    ACCEPTANCE CRITERIA:
    1. `pm-scope-activate.sh` exits non-zero with a clear error message if feature.md does NOT
       contain a `## Security acceptance criteria` section (case-insensitive grep, non-empty section).
    2. The check is applied to ALL sites/features (not just forseti-copilot-agent-tracker).
    3. Error message directs the user to add the section with minimum subsections:
       Authentication/permission surface, CSRF expectations, Input validation, PII/logging constraints.
    4. An escape hatch field `- Security AC exemption: <reason>` in feature.md bypasses the check
       (for features with no security surface, e.g. pure content).
    5. Existing test: run `./scripts/pm-scope-activate.sh forseti.life forseti-copilot-agent-tracker`
       on a feature.md WITHOUT the section — confirm exit 1. Add the section — confirm exit 0.

    VERIFICATION:
    - Manual test with a stripped feature.md (remove security section → expect exit 1, add it → expect exit 0)
    - No regressions to existing passing features (run scope-activate on a known-good feature.md)

    KB reference: None — this is a new gate pattern. Add a lessons-learned entry to
    `knowledgebase/lessons/20260405-security-ac-gate-enforcement.md` after implementation.

- Agent: dev-infra
- Status: pending
