# Acceptance Criteria — PROJ-009 Open Source Initiative

**Feature:** `forseti-open-source-initiative`  
**Project:** `PROJ-009`

## AC-1 — First candidate remains explicit

- `drupal-ai-conversation` is the first publication candidate.
- The feature docs point to the candidate gate and freeze plan artifacts.
- Publication uses curated extracts / mirrors, not a public flip of the live monorepo.

## AC-2 — Candidate-local NO-GO findings are explicitly tracked

- The feature grooming packet lists the currently blocking candidate-local findings:
  1. HQ/session coupling
  2. stale absolute HQ path fallback
  3. site-specific logging reference
  4. Forseti-specific default prompt/config defaults
  5. unresolved suggestion/inbox automation publication decision
  6. provider/support/default-story drift

## AC-3 — Security/governance gate is explicit

- The feature docs state that publication remains blocked until:
  - external AWS rotation confirmation exists,
  - history scrub / sensitive-data cleanup is complete,
  - private-path exclusions are enforced,
  - the candidate freeze packet exists.

## AC-4 — Freeze packet contents are defined

The feature is groomed only when the freeze packet requirements are concrete:

- frozen repo/archive path
- frozen commit SHA
- packaging decision
- final include/exclude boundary
- public docs package
- sanitized config/env examples
- supported version matrix
- CI baseline evidence
- intentional delta note

## AC-5 — QA handoff contract is explicit

- QA receives exactly one frozen candidate for validation.
- Required QA inputs are documented:
  - frozen path/archive
  - frozen commit SHA
  - packaging decision
  - CI evidence
  - support matrix
  - public docs/config examples
  - intentional deltas

## AC-6 — Near-term work lanes are sequenced

- The feature grooming packet separates the work into:
  1. candidate sanitization
  2. security/governance
  3. freeze packaging
  4. validation

## AC-7 — Release path is actionable

- The next action owners are explicit:
  - `dev-open-source` for candidate-local remediation
  - `sec-analyst-open-source` for publication security review
  - `ceo-copilot-2` for external credential-rotation confirmation
  - `pm-open-source` for freezing the curated extract
  - `qa-open-source` for validating the frozen packet

## AC-8 — Exclusion boundary is explicit

- Public candidates must exclude:
  - `sessions/**`
  - `inbox/**`
  - `tmp/**`
  - `prod-config/**`
  - `database-exports/**`
  - key material
  - credential-bearing runtime config

## AC-9 — Success outcome is measurable

- The feature is considered materially advanced when a single sanitized `drupal-ai-conversation` candidate can be frozen and handed to QA with no unresolved candidate-local NO-GO findings.
