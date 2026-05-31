# GitHub Workflows

- deploy.yml: Deploy Forseti to production.
- auto-assign-copilot.yml: Assign copilot to PRs when opened, reopened, or marked ready for review.
- merge-issue-branches-into-testing.yml: On schedule or manual dispatch, merges open PRs into testing only when all guards pass: base branch is `main`, PR is non-draft, Copilot is requested or assigned, merge state is `CLEAN`/`HAS_HOOKS`, no pending/failing checks, and no `CHANGES_REQUESTED` review decision. Skips non-eligible PRs and pushes testing only when merges occur.

Run merge-issue-branches-into-testing manually via GitHub Actions → "Run workflow" when you want to sync ready PR branches into testing immediately.
