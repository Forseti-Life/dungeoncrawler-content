# PROJ-009 Publication Candidate Gate — `drupal-ai-conversation`

- **Date:** 2026-04-14
- **Owner:** `pm-open-source`
- **Candidate type:** first public code repo
- **Candidate source root:** `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation`
- **Parallel docs track:** `forseti-platform`

## Candidate decision

**Selected first candidate:** `drupal-ai-conversation`

Why this candidate first:
- Smallest credible reusable unit in the Forseti platform
- Clear external audience in the Drupal ecosystem
- Lets the project prove the curated-extract publication model before broader platform extraction

## Gate status

**Decision today:** **NO-GO** for public freeze / public push.

This PM gate is complete and the first candidate is now explicitly baselined. Publication remains blocked because Dev's Phase 1 audit found candidate-local internal coupling that still must be removed, and external AWS credential rotation is still unconfirmed.

## PASS / FAIL summary

| Lane | Status | Evidence |
|---|---|---|
| First candidate explicitly chosen | PASS | This artifact; `sessions/pm-open-source/artifacts/oss-project-schedule.md` |
| BA packaging/support intent exists | PASS | `sessions/ba-open-source/artifacts/20260414-proj-009-drupal-ai-conversation-packaging-brief.md` |
| QA validation intent exists | PASS | `sessions/qa-open-source/artifacts/20260414-proj-009-drupal-ai-conversation-validation-plan.md` |
| Candidate included/excluded content defined | PASS | Included/excluded sections below |
| Dev Phase 1 security/history gate | FAIL | `sessions/dev-open-source/artifacts/20260414-proj-009-phase1-security-gate-drupal-ai-conversation.md` |
| Candidate freeze packet ready | FAIL | No sanitized frozen extract/commit exists yet |
| External AWS credential rotation confirmed | FAIL | No confirmation recorded in PM/dev evidence |

## Included content (intentional)

The first public candidate should include only the curated standalone module package for `drupal-ai-conversation`, specifically:

1. Module source from `sites/forseti/web/modules/custom/ai_conversation/`
2. Module-local install/config assets needed for the public package
3. Public-safe docs and repo metadata created for the extracted repo:
   - `README.md`
   - `LICENSE`
   - `CONTRIBUTING.md`
   - `SECURITY.md`
   - `CODE_OF_CONDUCT.md`
   - sanitized configuration examples
4. Repo-local packaging metadata required by the final packaging model

## Excluded content (intentional)

The first public candidate must exclude:

1. Site-level config sync exports such as `sites/forseti/config/sync/**`
2. All HQ/internal operations content:
   - `sessions/**`
   - `inbox/**`
   - `tmp/**`
   - org-only automation wiring
3. Private/server data and deployment material:
   - `prod-config/**`
   - `database-exports/**`
   - keys, tokens, literal credentials
4. Current candidate-local internal references called out by Dev until they are removed:
   - HQ suggestion/inbox auto-queue behavior
   - stale absolute HQ paths
   - `thetruthperspective.logging`
   - Forseti-specific default prompt text

## Dev evidence summary

Referenced artifact: `sessions/dev-open-source/artifacts/20260414-proj-009-phase1-security-gate-drupal-ai-conversation.md`

Dev's current gate result is **FAIL / NO-GO** because:
1. `AIApiService.php` still includes HQ/session coupling
2. `AIApiService.php` still includes a stale absolute HQ fallback path
3. `ConfigurableLoggingTrait.php` still references `thetruthperspective.logging`
4. install-time provider settings still ship a Forseti-specific default prompt
5. previously exposed AWS credential rotation remains unconfirmed externally

PM interpretation: the candidate choice stands, but the public freeze must wait for a sanitized extract and rotation confirmation.

## QA validation intent

Referenced artifact: `sessions/qa-open-source/artifacts/20260414-proj-009-drupal-ai-conversation-validation-plan.md`

QA is aligned to validate this same candidate after freeze. The plan already defines:
- clean Ubuntu + Drupal 10 + Drupal 11 validation lanes
- CI baseline requirements
- documentation required for APPROVE
- exact frozen-candidate inputs PM/Dev must hand over

## BA packaging intent

Referenced artifact: `sessions/ba-open-source/artifacts/20260414-proj-009-drupal-ai-conversation-packaging-brief.md`

BA is aligned to package this same candidate and has already surfaced the remaining PM decisions:
1. standalone module repo vs extracted package layout
2. treatment of HQ suggestion automation
3. neutral public default prompt
4. public support/version matrix

## Go / no-go criteria for the next PM check

Mark the candidate **GO for freeze** only after all of the following are true:
1. Dev removes or cleanly strips the HQ/session coupling from the public candidate
2. Dev removes stale absolute HQ paths and site-specific logging references
3. Dev neutralizes the install-time default prompt and any Forseti-specific defaults
4. PM freezes a curated sanitized extract with a concrete commit SHA/archive
5. CEO confirms previously exposed AWS credentials were rotated externally
6. QA receives the frozen packet defined in its validation plan

## Next action owner

- **Dev-open-source:** remediate the candidate-local NO-GO findings and produce the sanitized extraction boundary
- **CEO / supervisor:** confirm external AWS credential rotation so PM can clear the remaining pre-push governance gate
- **PM-open-source:** freeze the curated extract and hand the packet to QA immediately after the two items above are complete
