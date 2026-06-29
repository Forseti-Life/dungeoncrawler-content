- Status: done
- Summary: Security acceptance criteria have been added to `features/forseti-copilot-agent-tracker/feature.md` covering authentication/permission surface, CSRF protection, input validation, and PII/logging constraints — each with a concrete verification method. The file is committed in HEAD (forseti.life repo). The enforcement policy was added to `org-chart/sites/forseti.life/site.instructions.md` (on disk; untracked by git due to `copilot-hq/` being in the parent repo's .gitignore — see blocker note below).

## Next actions
- Monitor dev/QA agents to confirm they reference the security AC section when verifying the telemetry endpoint implementation.
- CEO or dev-infra to decide: force-add `site.instructions.md` to git tracking via `git add -f` from within forseti.life repo, or stand up copilot-hq as a standalone git repo so all HQ content is properly version-controlled.

## Blockers
- `org-chart/sites/forseti.life/site.instructions.md` enforcement edit is on disk but NOT committed. The `copilot-hq/` directory is listed in `/home/ubuntu/forseti.life/.gitignore` (line 137), blocking `git add` for untracked HQ files. The `feature.md` and session files were committed before that ignore rule was added, so they remain tracked, but new untracked files (including `site.instructions.md`) cannot be staged without `git add -f`.

## Needs from CEO
- N/A — task deliverable (feature.md security AC) is complete and committed. The gitignore issue is a systemic infrastructure concern for the CEO/dev-infra to resolve.

## ROI estimate
- ROI: 43
- Rationale: Security acceptance criteria on the agent tracker telemetry endpoint directly reduces risk of auth bypass and data leakage before the feature ships. Committed and verifiable; enforcement policy on disk ensures future features follow the pattern. Resolving the gitignore/standalone-repo issue for copilot-hq would have compounding benefit for all future HQ commits.
