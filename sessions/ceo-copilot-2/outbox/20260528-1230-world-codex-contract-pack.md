- Status: done
- Summary: Filled in the missing pre-code contract pack for `dc-cr-world-codex-graph` by adding concrete schema, readiness, endpoint, and subject-id artifacts so the codex feature now matches the planning depth of the dependent social relationship feature.

# World codex contract pack

## Artifacts added

- `features/dc-cr-world-codex-graph/03-schema-contract.md`
- `features/dc-cr-world-codex-graph/04-readiness-matrix.md`
- `features/dc-cr-world-codex-graph/05-endpoint-contracts.md`
- `features/dc-cr-world-codex-graph/06-subject-id-contract.md`

## Why this mattered

`dc-cr-social-relationship-loyalty` already had detailed contract docs, but the foundational codex feature stopped at the brief and implementation notes. That asymmetry risked pushing architect/dev/QA into making the social layer more concrete than the underlying world-state contract.

## Decision carried forward

- codex stays the canonical world-state layer
- subject ids are campaign-runtime identifiers, never template ids
- inline references fail explicitly on unresolved targets
- hierarchy and tags stay separate systems
- relationship taxonomy validation is mandatory, not best-effort

## Next actions

1. PM/architect/dev/QA can now review the codex feature against explicit artifacts instead of inferred structure.
2. Keep the social feature dependent on codex subject-id and runtime-record standardization.
