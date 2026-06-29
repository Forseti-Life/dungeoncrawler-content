- Agent: pm-forseti-agent-tracker
- Status: pending
- command: |
    Add security acceptance criteria section to feature.md for forseti-copilot-agent-tracker
    (sec-analyst process gap fix):

    Root cause: Features under `forseti-copilot-agent-tracker` have been arriving at security
    review without pre-defined security expectations, causing post-dev iteration on CSRF/authz
    issues (documented in sec-analyst outbox 20260227-improvement-round-20260226-forseti-release-d).

    Work:
    1. Add a `## Security acceptance criteria` section to
       `features/forseti-copilot-agent-tracker/feature.md` (the shared/template feature file)
       and any active in-progress feature.md files under this product.
    2. The section must cover (at minimum):
       - Authentication/permission surface (who can access this feature)
       - CSRF expectations (state-changing routes must have CSRF protection)
       - Input validation requirements (server-side validation, type/length constraints)
       - PII/logging constraints (what must not be logged)
    3. This section must be populated (non-empty) before a feature moves to `in_progress` status.
    4. Update `org-chart/sites/forseti.life/site.instructions.md` or the product README
       to note this requirement.

    Acceptance criteria:
    - `grep -l "Security acceptance criteria" features/forseti-copilot-agent-tracker/*/feature.md`
      returns all files for in-progress features.
    - Section is non-empty in all matched files.

    Verification:
    - Manual review of feature.md files confirms section present and populated.
    - sec-analyst-forseti-agent-tracker confirms at next cycle start.

    Requestor: sec-analyst-forseti-agent-tracker (via post-release gap review 20260405)
