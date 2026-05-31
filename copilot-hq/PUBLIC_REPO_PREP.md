# Public Repository Preparation Checklist

This checklist is for preparing `copilot-sessions-hq` to be published as a public repository safely and clearly.

## 0) Publication strategy
- Decide whether to publish:
  - current repo as-is after cleanup, or
  - a curated mirror repository (recommended if history may contain sensitive artifacts).

Recommended: curated mirror if there is any uncertainty about historical data leakage.

**Decision (2026-04-13):** use curated mirrors / extracted repos for public release. Do **not** flip the live operational repo public.

## 1) Security + privacy scrub (required)
### 1.1 Current tree scan
- Ensure no secrets, tokens, credentials, host-specific paths, private URLs, or personal data remain in tracked files.
- Review:
  - `sessions/**` (outbox/artifacts often contain operational details)
  - `inbox/**`
  - `tmp/**` (should generally be excluded)
  - `inbox/responses/**` (logs may include environment details)

### 1.2 Git history scan
- Scan full history for leaked secrets, keys, credentials, private hostnames, and email addresses.
- If anything sensitive appears in history, either:
  - rewrite history before publication, or
  - publish via curated mirror with clean history.

### 1.3 Runtime-state exclusions
- Confirm `.gitignore` excludes runtime state and volatile lock/pid files.
- Confirm no new runtime files are currently tracked.

## 2) License + legal metadata (required)
- Add a `LICENSE` file (MIT/Apache-2.0/etc., per your preference).
- Add/verify:
  - `CODE_OF_CONDUCT.md`
  - `CONTRIBUTING.md`
  - `SECURITY.md` (how to report vulnerabilities)

## 3) Documentation readiness
- Root `README.md` should include:
  - project purpose
  - architecture summary
  - monitoring/control path
  - quickstart (minimal)
  - operational safety notes
- Publish and keep updated `runbooks/public-repo-positioning.md` covering:
  - value proposition
  - public repo purpose
  - explicit boundary and relationship to `forseti.life`
- Ensure runbook links are valid and non-internal.

## 4) Public-safe defaults
- Verify defaults do not point to private infrastructure.
- Ensure local/prod URLs in docs are intentionally public or clearly placeholders.
- Ensure scripts fail safely when required env vars/paths are missing.

## 5) Session data policy (important)
Decide what to do with `sessions/**` in a public repo:
- Option A: keep full audit trail (high transparency, higher leak risk).
- Option B: keep only curated examples and redact the rest.
- Option C: exclude most session artifacts and publish framework/runbooks/scripts only.

**Decision (2026-04-13):** choose **Option C**. Keep `sessions/**`, `inbox/responses/**`, and `tmp/**` private by default. If examples are ever published, copy sanitized examples intentionally rather than exporting live operational history.

Also keep these private or sanitize them before any public candidate is created:
- `prod-config/**`
- `database-exports/**`
- `sites/*/keys/**`
- credential-bearing runtime config files

Document this policy in README / QUICKSTART for each public repo.

## 6) CI and baseline checks
Before publishing, run a baseline validation set:
- shell script syntax checks for `scripts/*.sh`
- Python lint/smoke where relevant
- key operational scripts in `--help`/status modes

Record outcomes in a short publication readiness note.

## 7) Final publication steps
1. Freeze/branch for publication prep.
2. Apply scrub/redaction and docs/legal updates.
3. Re-run security + history scan.
4. Tag release candidate.
5. Publish repository visibility.
6. Monitor issue tracker for first-week feedback.

## 8) Optional hardening for open source
- Add GitHub Actions for secret scanning and basic lint checks.
- Add issue/pr templates.
- Add architecture diagram under `docs/`.
- Add a minimal demo workflow for local operators.

## Current cleanup snapshot (2026-02-24)
Completed in working tree/index:
- Hardened ignore rules in `.gitignore` for:
  - `sessions/**` (except `sessions/README.md`)
  - `tmp/**` (except `tmp/.gitkeep`)
  - `inbox/responses/**` (except `.gitkeep`)
  - `orchestrator/.venv/` and `**/.venv/`
  - runtime pid/state files
- Untracked large volatile/sensitive categories from git index.

Staged cleanup totals at snapshot time:
- Deletions: ~15k files (sessions/tmp/venv/runtime)
- Session files removed from index: ~11.8k
- Temp files removed from index: 73
- Orchestrator venv files removed from index: ~3.2k

Remaining pre-public actions:
- Human review of docs/scripts referencing token workflows (e.g., GitHub token sourcing).
- Apply the decided private-only policy for `sessions/**` except where a sanitized curated example is intentionally copied into public docs/examples.
- Run full-history scrub and rewrite/truncate history before changing visibility.

Current note (2026-04-14):
- `scripts/export-public-mirror.sh` now removes excluded paths from existing mirrors and no longer recreates `inbox/responses/.gitkeep`. The remaining hard blockers are current-tree key material, secret-bearing history, and governance confirmation of external credential rotation.

## Quick command ideas (manual runbook)
Use these as prompts for your own execution runbook (adapt as needed):
- `git status --porcelain`
- `git ls-files`
- `git log --oneline --decorate --graph --all | head -n 200`
- `grep -R -n -E "(token|apikey|api_key|secret|password|PRIVATE KEY)" .`

> Note: command results require human review; do not rely on regex-only checks as your sole security control.
