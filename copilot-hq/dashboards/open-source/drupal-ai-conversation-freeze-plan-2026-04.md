# `drupal-ai-conversation` Freeze Plan — 2026-04

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `in_progress`
- Mode: `planning/documentation only`

## Purpose

Translate the current gate evidence for the first public code candidate into a concrete freeze-ready plan.

This artifact is about the path from **selected candidate** to **sanitized frozen extract**. It does not publish the repo, rewrite history, or clear QA by itself.

## Current baseline

These items are already established:

1. `drupal-ai-conversation` is the first public code candidate.
2. `forseti-platform` remains the parallel docs-first overview track.
3. Public release uses curated extracts / mirrors, not a public flip of the live monorepo.
4. PM, BA, QA, and dev all already have candidate-specific artifacts for this same module.

## Blocking findings to plan around

### 1. Candidate-local public-safety blockers

The candidate is still **NO-GO for freeze** until these are removed or intentionally redesigned:

1. HQ/session coupling in `AIApiService.php`
2. stale absolute HQ fallback path in `AIApiService.php`
3. site-specific logging reference (`thetruthperspective.logging`) in `ConfigurableLoggingTrait.php`
4. Forseti-specific install-time default prompt
5. unresolved publication decision for suggestion/inbox automation
6. drift in the public support/provider/default configuration story

### 2. Repo-wide security and governance blockers

The overall project remains blocked on:

1. external confirmation that previously exposed AWS credentials were rotated
2. full history scrub / sensitive-data cleanup for material that cannot appear in a public history
3. current private-path enforcement in export/extract tooling
4. exclusion of keys, sessions, inbox/runtime artifacts, prod config, and database exports from any public candidate

### 3. Freeze-packet gap

There is still no frozen sanitized candidate packet with:

1. a concrete repo path or archive
2. a frozen commit SHA
3. the final included/excluded boundary
4. the public docs/config package
5. the CI/validation inputs QA expects

## Recommended planning split

Keep the next work in four separate lanes so the gate stays understandable:

1. **Candidate sanitization lane** — remove or redesign `drupal-ai-conversation` internals that are not public-safe.
2. **Security/governance lane** — finish the repo-wide rotation, history, and private-path controls that block any safe public push.
3. **Freeze packaging lane** — assemble one explicit sanitized extract for PM to freeze.
4. **Validation lane** — hand QA a complete frozen packet and validate that exact artifact.

## Candidate sanitization lane

Before PM can mark the candidate GO for freeze, this lane should produce:

1. removal of HQ/session coupling from the public candidate
2. removal of stale absolute-path behavior
3. neutralized logging integration that is module-local or Drupal-standard
4. neutral public default prompt behavior
5. an explicit publication decision for suggestion automation:
   - remove from the public candidate, or
   - keep only behind an optional/public-safe interface
6. a reconciled support statement for Drupal/PHP and provider defaults

## Security and boundary lane

This lane should define and enforce the public boundary:

1. confirm AWS rotation externally
2. keep `sessions/**`, `inbox/**`, `tmp/**`, `prod-config/**`, `database-exports/**`, and key material out of the candidate
3. fix export tooling so it does not recreate private runtime paths such as `inbox/responses`
4. record whether the candidate will publish from:
   - a brand-new clean extracted repo history, or
   - a sanitized mirror export with intentionally clean history

## Freeze packet definition

The first freeze packet for `drupal-ai-conversation` should contain all of the following:

1. frozen extracted repo path or archive path
2. frozen commit SHA
3. repo packaging decision:
   - standalone Drupal module repo, or
   - extracted package layout
4. final included/excluded file boundary
5. public-safe docs:
   - `README.md`
   - `LICENSE`
   - `CONTRIBUTING.md`
   - `SECURITY.md`
   - `CODE_OF_CONDUCT.md`
6. public-safe configuration examples / environment variable placeholders
7. explicit supported-version matrix for Drupal and PHP
8. CI baseline and run evidence for the frozen candidate
9. intentional-delta note describing what was removed or changed relative to the private module

## QA handoff contract

QA should receive exactly one frozen candidate and validate that exact artifact.

Required handoff inputs:

1. frozen repo/archive path
2. frozen commit SHA
3. packaging decision
4. CI run URL or artifact bundle
5. supported-version matrix
6. public README/config docs
7. sanitized config/env examples
8. intentional deltas from the private module

## Entry criteria for the freeze step

Start the actual freeze only when all of these are true:

1. candidate-local NO-GO findings are closed
2. external AWS rotation confirmation exists
3. public boundary/exclusion list is explicit
4. packaging model is frozen
5. public docs/config package exists
6. extract/export tooling matches the exclusion policy

## Exit criteria for this planning slice

This planning slice is complete when the work can move cleanly into implementation:

1. the ordered remediation lanes are explicit
2. the freeze packet is defined
3. the QA handoff contract is defined
4. the main `PROJ-009` feature doc points at this freeze path instead of stale steps

## Ordered next action

1. Clear the `drupal-ai-conversation` candidate-local NO-GO findings and freeze the packaging decision.
2. Close the security/governance blockers that still prevent a safe public candidate.
3. Build one sanitized extract with the exact freeze packet contents above.
4. Hand that frozen packet to QA for Gate 2 validation.
