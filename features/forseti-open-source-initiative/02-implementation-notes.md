# Implementation Notes — PROJ-009 Open Source Initiative

**Feature:** `forseti-open-source-initiative`  
**Project:** `PROJ-009`

## Scope for this grooming packet

This feature is a program-level driver for the first public publication candidate. The immediate execution target is **not** “publish everything”; it is to move `drupal-ai-conversation` from selected candidate to freeze-ready sanitized extract.

## Reference artifacts

- `dashboards/open-source/drupal-ai-conversation-freeze-plan-2026-04.md`
- `sessions/pm-open-source/artifacts/20260414-proj-009-publication-candidate-gate-drupal-ai-conversation.md`
- `sessions/qa-open-source/artifacts/20260414-proj-009-drupal-ai-conversation-validation-plan.md`
- `runbooks/private-public-dual-repo.md`

## Execution lanes

### 1. Candidate sanitization lane

Owner: `dev-open-source`

Required outputs:

- remove HQ/session coupling from the public candidate
- remove stale absolute-path behavior
- neutralize logging to Drupal-standard or module-local behavior
- replace Forseti-specific default prompt/config defaults with public-safe neutral defaults
- decide whether suggestion/inbox automation is:
  - removed from candidate, or
  - retained only behind a public-safe optional interface

### 2. Security and boundary lane

Owners: `dev-open-source`, `sec-analyst-open-source`, `ceo-copilot-2`

Required outputs:

- external confirmation of AWS rotation
- explicit exclusion boundary for public candidate
- export/extract tooling aligned to that boundary
- packaging model frozen: clean extracted history vs sanitized mirror export

### 3. Freeze packaging lane

Owner: `pm-open-source`

Required outputs:

- frozen extracted repo or archive
- frozen commit SHA
- final include/exclude boundary
- public docs/config examples
- support matrix
- CI run evidence
- intentional delta note

### 4. Validation lane

Owner: `qa-open-source`

Required outputs:

- Gate 2 validation against the exact frozen packet
- APPROVE/BLOCK report tied to the frozen SHA/artifact

## Immediate technical targets

### Target candidate

- Source root today: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation`

### Public packaging direction

- Preferred near-term model: standalone extracted Drupal module repo
- Public repo should contain module source plus public-safe repo metadata only

### Public-safe docs bundle

- `README.md`
- `LICENSE`
- `CONTRIBUTING.md`
- `SECURITY.md`
- `CODE_OF_CONDUCT.md`
- sanitized configuration examples

## Boundary rules

Never include in the candidate:

- `sessions/**`
- `inbox/**`
- `tmp/**`
- `prod-config/**`
- `database-exports/**`
- `sites/*/keys/**`
- literal credentials, tokens, or private infra references

## Freeze entry criteria

Do not freeze until all are true:

1. candidate-local NO-GO findings are closed
2. external AWS rotation confirmation exists
3. boundary/exclusion list is explicit
4. packaging decision is frozen
5. docs/config examples exist
6. export/extract tooling matches the exclusion policy

## Implementation notes for owners

### Dev

- Keep the candidate self-contained and module-local.
- Remove private org coupling rather than documenting around it.
- Produce a concrete sanitized extraction boundary, not just prose.

### PM

- Freeze one exact artifact only.
- Do not hand QA a moving target or a live branch reference.

### QA

- Validate the frozen candidate, not the private monorepo copy.
- Require CI evidence before spending time on clean-machine validation.

## Completion signal for grooming

This feature is considered groomed when the lane split, entry criteria, freeze packet, and owner-specific next actions are explicit enough that PM/dev/QA/security can execute without re-deriving the plan.
