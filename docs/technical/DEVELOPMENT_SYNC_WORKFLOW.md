# Development Sync Workflow (Local + Production)

## Purpose

Prevent lost work and accidental overwrite when both the local dev machine and production server create commits.

For the production-master to local-worker command protocol used by HQ automation,
see `copilot-hq/runbooks/production-master-dev-worker.md`.

## Source of truth and deployment

- `main` in GitHub is the source of truth for code history.
- Production deployment is manual-only.
- A push to GitHub does not deploy by itself.
- Production pulls changes only when an operator explicitly runs deploy.

Important distinction:
- Changes made directly on the production host are immediately live in production runtime.
- Changes made on local/off-host environments are not live until explicitly promoted.

## Standard operating flow

### 1) Start-of-work sync (required in both environments)

```bash
git fetch origin --prune
git status
git pull --ff-only origin main
```

### 2) Branch naming convention

- Local work: `local/<scope>-<YYYYMMDD>-<short>`
- Production urgent fixes: `prod-hotfix/<scope>-<YYYYMMDD>-<short>`

Examples:
- `local/jobhunter-20260419-cio-form-mapper`
- `prod-hotfix/forseti-20260419-drush-bootstrap`

### 3) Capture in-flight work early

Immediately push the branch after first commit:

```bash
git push -u origin <branch>
```

This ensures partially completed work is visible and recoverable.

### 4) Integrate to main quickly

After verification:

```bash
git checkout main
git pull --ff-only origin main
git merge --no-ff <branch>
git push origin main
```

Keep branches short-lived. Smaller diffs merge with lower risk.

### 5) Promote to production explicitly

Run `.github/workflows/deploy.yml` with `workflow_dispatch` only when ready.

## Merge conflict prevention rules

1. No long-running branches for shared files.
2. Rebase before merge and before resumed work after context switch.
3. If a production hotfix lands first, local branches must rebase on latest `main` before continuing.
4. Do not force-push shared branches or `main`.
5. For the same-file conflict, preserve production-tested behavior first; layer local improvements in follow-up commits.

## Capturing development done off-production

When work is done on local machine while production also moves:

1. Push local branch immediately.
2. Pull latest `main` (with production commits).
3. Rebase local branch onto updated `main`.
4. Resolve conflicts file-by-file with production behavior as baseline for urgent paths.
5. Re-run verification, merge, and push.
6. Deploy manually when explicitly approved.

## Fast safety checklist

Before opening a merge:
- [ ] `git fetch origin --prune`
- [ ] Branch rebased on latest `origin/main`
- [ ] No force-push to shared branches
- [ ] Verification run completed
- [ ] Deployment decision explicit (not assumed)

## Incident mode (production-first)

For active production incidents:

1. Fix on production branch `prod-hotfix/...`.
2. Push branch + merge to `main` as soon as validated.
3. Local machine rebases active branches on updated `main` before further work.
4. Record incident context in commit message and related issue.
