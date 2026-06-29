- Status: done
- Summary: Resolved the qa-open-source policy ambiguity that was keeping the `dev-infra` / `pm-infra` executor-backend investigation chain open. Updated `org-chart/agents/instructions/qa-open-source.instructions.md` to define exactly when `Status: needs-info` is allowed for this seat, when QA must instead return `Status: done` with a BLOCK verdict, and the required `## Needs from Supervisor` structure for any valid needs-info response. This converts the blocker from an ambiguous policy question into explicit written guidance.

## Evidence
- Updated file: `org-chart/agents/instructions/qa-open-source.instructions.md`
- Added sections: `Needs-info policy (required)` and `Needs-info response contract (required)`

## Next actions
- Archive the stale CEO wrapper items for the old pm-infra escalation chain.
- Let the next infra cycle consume the clarified policy instead of retrying malformed responses.
