# Coordinated Release Process (Forseti + Dungeoncrawler)

Master process flow (authoritative): `runbooks/release-cycle-process-flow.md`

## Purpose
We ship changes in a coordinated way across product areas so that:
- Forseti and Dungeoncrawler pushes happen in the same release window.
- PM/Dev/QA/Security are aligned on readiness thresholds.
- The change list and verification evidence are centralized and reviewable.
- The push happens only after Dev + QA gates are clean.

## Roles
- **Release Coordinator (default: pm-forseti)**
  - Coordinates the release window and cross-product dependencies.
  - Runs the final `git push` for the coordinated release once gates are clean.
  - Ensures the readiness artifacts are complete.
- **Coordinated dependency (required)**
  - For coordinated pushes, `pm-forseti` performs the official push, but must wait for **all required** PM signoffs from the product registry:
    - `org-chart/products/product-teams.json` entries with `active=true` and `coordinated_release_default=true`
  - Signoff artifacts are recorded with:
    - `scripts/release-signoff.sh <site-or-team-alias> <release-id>`
  - `pm-forseti` verifies readiness with:
    - `scripts/release-signoff-status.sh <release-id>`
- **Product stream owners (as assigned)**
  - Any role may create/edit content artifacts (change list, risks, docs) during the cycle when it unblocks delivery.
- **Dev seats** (`dev-*`)
  - Confirm deploy steps/rollback and provide a concrete list of changed files/features/defects fixed.
- **QA seats** (`qa-*`)
  - Provide verification evidence and explicit APPROVE/BLOCK for release-bound items.
- **Security seats** (`sec-analyst-*`, recommend-only)
  - Provide a short “release security note”: new risks, mitigations, and any Critical/High open items.

## Release unit
A release is a **single coordinated event** that includes:
- Forseti stream (job_hunter + agent tracker if applicable)
- Dungeoncrawler stream

If one stream is not ready, the default is **do not release** unless PM explicitly accepts the risk and documents it in the release candidate.

## Readiness thresholds (must all be true)
### Gate R0 — Change set defined (PM)
- A single release candidate folder exists (see below).
- Contains a clear change list (features/defects) for **both** streams.
- Scope is bounded and risks are stated.

Scope freeze rule:
- After Gate R0 is met for a release id, the change list is considered scope-frozen for that cycle.
- New net-new scope intake during the cycle is queued for the next cycle (or explicitly listed as deferred).
- Stabilization fixes required to satisfy already in-scope acceptance criteria are allowed; record them explicitly as stabilization (not new scope).

### Gate R1 — Implemented (Dev)
- Each release-bound item has implementation notes and identifies verification steps.
- Rollback plan exists (even if it’s “revert commit(s) / restore backup”).
- Dev confirms code changes are committed (commit hash(es) recorded in outbox + release candidate).

### Gate R2 — Verified (QA)
- QA has a verification report for each release-bound item.
- No release-bound item is BLOCK.
- If QA identifies **no new Dev work**, QA explicitly states “no new items identified for Dev” in the verification summary.

Test-case source of truth requirement:
- QA evidence must include results from the product’s central automated PASS/FAIL suites (not only manual checklists).

### Gate R5 — Post-release QA (QA, production)
- After the release push, QA reruns the audit against production base URL(s).
- If clean: QA states “post-release QA clean” and “no new items identified for Dev”.
- If unclean: QA records the unclean signal with evidence (no inbox dispatch).

Policy:
- If post-release is unclean, the next release cycle is remediation-only (no new features).
- PM escalates to CEO if there are 3 unclean releases in a row for a product/site.

### Gate R3 — Security sanity check (Security)
- No known Critical/High security finding is being shipped without explicit acknowledgement.

### Gate R4 — Operational readiness (optional: CEO/Infra)
### Gate R6 — Joint PM signoff (PM)
- All required coordinated PM seats have signed off for this `release-id`.
- The release operator checks readiness with:
  - `./scripts/release-signoff-status.sh <release-id>` (exit code 0 means ready)
- Release notes are complete and validated:
  - `./scripts/release-notes-lint.sh sessions/<lead-pm>/artifacts/release-candidates/<YYYYMMDD-release-id>/`
- Backup/restore approach is stated.
- A single release window is chosen.
- Communication path is working (PM ↔ HQ ↔ tracker UI).

## Where the release candidate lives (source of truth)
Create a release candidate folder (owned by the release operator/PM):
- `sessions/<lead-pm>/artifacts/release-candidates/<YYYYMMDD-release-id>/`

Required artifacts inside the folder:
- `00-release-plan.md`
- `01-change-list.md`
- `02-test-evidence.md`
- `03-risk-security.md`
- `04-rollback.md`
- `05-release-notes.md`

Release-notes quality gate (required):
- Run `./scripts/release-notes-lint.sh <release-candidate-folder>` before PM signoff/push.

Use the template files under `templates/release/`.

## Flow (end-to-end)
0) Start of cycle (QA preflight, once per release id)
- Queue the QA preflight task (one per cycle):
  - `scripts/release-cycle-start.sh <site> <release-id>`
  - QA reviews/refactors QA test scripts/configs before verifying release-bound items.
  - For coordinated releases, prefer queuing both sites in one command:
    - `scripts/coordinated-release-cycle-start.sh <release-id>`

1) Lead PM(s) propose a release window and compile the change list.
2) Dev confirms implementation status + rollback.
3) QA confirms verification evidence + APPROVE.
4) Security provides a short release note (recommend-only).
5) PM verifies Dev + QA gates are clean and artifacts are complete.
6) PM performs the release push (Forseti + Dungeoncrawler together).

Before the push (required):
- `pm-forseti` runs `./scripts/release-signoff-status.sh <release-id>` and proceeds only if it reports ready.
- `pm-forseti` runs `./scripts/release-notes-lint.sh sessions/<lead-pm>/artifacts/release-candidates/<YYYYMMDD-release-id>/` and proceeds only if it passes.
7) QA performs post-release audit against production.
8) If post-release clean, PM starts the next release cycle (Gate R0) using the newest QA signal.

## Notes
- This runbook defines the coordination and thresholds only. Actual deploy commands may vary by environment.
- Do not bypass this process unless PM documents an explicit exception/risk acceptance in the release candidate.
