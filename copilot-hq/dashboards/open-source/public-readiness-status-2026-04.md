# Public Readiness Status — 2026-04

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `blocked`
- Last updated: `2026-04-15`

## Summary

The open-sourcing effort is **not yet ready for public release**.

The GitHub org exists and the publication model is now decided, but the project is still blocked by **security cleanup, history hygiene, publication-candidate packaging, and release evidence**.

## Repo-breakout planning track

The current architect slice is **planning/documentation only** for the repo family that will sit under the community home and the `Forseti-Life` org. No repos are being created in this slice.

Canonical planning artifact:

- `dashboards/open-source/repo-breakout-plan-2026-04.md`

That plan defines:

1. the first-wave repos to prepare directionally now,
2. the role of the platform/community home repo,
3. source boundaries and exclusions for each repo,
4. the execution order to use later once security/history gates are cleared.

## What is already in place

- GitHub org `Forseti-Life` exists
- Public release prep docs exist:
  - `PUBLIC_REPO_PREP.md`
  - `runbooks/publication-readiness-20260308.md`
  - `runbooks/public-release-gate-20260308.md`
  - `runbooks/public-repo-positioning.md`
  - `runbooks/private-public-dual-repo.md`
- Community/legal files exist:
  - `LICENSE`
  - `CODE_OF_CONDUCT.md`
  - `CONTRIBUTING.md`
  - `SECURITY.md`
- Publication model is decided:
  - use **curated mirrors / extracted repos**
  - do **not** flip the live operational repo public

## What is still pending before public release

## 1. Security cleanup

These remain required before any public repo is pushed:

1. Rotate the previously exposed AWS credentials externally
2. Confirm current-tree public candidates contain no credential-bearing runtime config
3. Remove or exclude private key material such as `sites/*/keys/`
4. Confirm `prod-config/**` and `database-exports/**` stay out of public candidates

## 2. Full git history scrub

This is still the biggest blocker.

Pending work:

1. Run a full-history sensitive-data audit across the monorepo and any intended extracted repos
2. Review historical secrets, private keys, session artifacts, host-specific details, and client data exposure
3. Rewrite or truncate history where needed, or publish only from curated mirror repos with clean history
4. Add an entropy-based scanner (`gitleaks` or equivalent) in addition to regex-only scans

## 3. Publication candidate freeze

Public release cannot proceed straight from the live operational working tree.

Pending work:

1. Freeze a publication candidate branch or curated mirror export
2. Ensure the candidate has a clean working tree
3. Remove in-flight operational artifacts from the candidate
4. Record exactly what content is intentionally included in the first public repo

## 4. Validation evidence

The current gate is not final.

Pending work:

1. Run baseline validation for the publication candidate
2. Record outcomes in a fresh readiness note
3. Re-run the public release gate with final PASS/FAIL evidence

## 5. Repo packaging and first-release scope

The public strategy is no longer "make the whole private repo public."

Pending work:

1. Confirm the first repo to publish
2. Build the curated mirror / extracted repo for that candidate
3. Ensure the selected candidate has:
   - public-safe README
   - quickstart
   - CI baseline
   - community files
   - explicit dependency/env-var documentation

## 6. Governance follow-through

Still pending from the feature brief:

1. Confirm final owners/admins for the `Forseti-Life` GitHub org
2. Convert prep docs into a publication-candidate gate with pass/fail evidence for the first repo

## Recommended publication sequence

The current docs point to this order:

1. `forseti-platform` overview repo
2. `drupal-ai-conversation`
3. `copilot-agent-framework`
4. `drupal-platform`
5. `forseti-job-hunter`
6. `dungeoncrawler`

## Most important blockers right now

If we reduce this to the true go/no-go items, the project is blocked on:

1. **credential rotation**
2. **history scrub / sensitive-data audit**
3. **publication candidate freeze**
4. **final validation evidence**

## Recommended next action

Treat the next execution slice as a **publication-candidate security gate**, after the repo-breakout planning docs are approved:

1. rotate exposed AWS credentials
2. run full-history scrub + sensitive-data audit
3. freeze the first curated mirror candidate
4. record a new PASS/FAIL readiness report against that candidate
