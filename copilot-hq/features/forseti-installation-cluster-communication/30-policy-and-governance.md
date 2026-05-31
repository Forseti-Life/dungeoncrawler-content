# Policy and Governance — Forseti Community Resource Mesh

This document defines the operator controls that preserve local autonomy and the soul of the mesh.

## MVP policy objective

A local Forseti installation must be able to decide:

1. which peers it trusts
2. which service categories it exposes
3. which requests require human approval
4. when a peer should be downgraded, suspended, or rejected

## Requirement set

- `CRM-POL-001` — Export policy must be defined locally per installation.
- `CRM-POL-002` — Trust and export rules must be operator-visible and auditable.
- `CRM-POL-003` — Capabilities and service categories must support `local_only`, `trusted_peers`, and `approval_required`.
- `CRM-POL-004` — Mission misalignment must block or downgrade admission.
- `CRM-POL-005` — High-impact workflows must be approval-capable.
- `CRM-POL-006` — Routing must be local-first and non-transitive.
- `CRM-POL-007` — Suspensions and rejections must capture explicit reasons.

## Export levels

### `local_only`

- capability is not advertised to peers
- requests for this capability never leave the installation

### `trusted_peers`

- capability may be advertised
- eligible trusted peers may request it
- automatic acceptance is still optional

### `approval_required`

- capability may be advertised as available by policy
- every inbound or outbound use requires operator approval before execution

## Trust-tier policy matrix

| Trust tier | Status sync | Capability sync | Need sync | Service requests |
| --- | --- | --- | --- | --- |
| `discovered` | No | No | No | No |
| `pending_review` | Limited manual verification only | No | No | No |
| `verified` | Yes | Yes | Yes | Only if explicitly allowed and low risk |
| `operational_partner` | Yes | Yes | Yes | Yes, within export policy |
| `suspended` | No | No | No | No |
| `rejected` | No | No | No | No |

## Approval rules for the MVP

Approval should be required when:

- the capability is marked `approval_required`
- the request includes sensitive data
- the peer is newly admitted and not yet operationally trusted
- the request has strategic, governance, or release impact
- the operator has configured a peer-specific restriction

Approval should be optional when:

- the peer is an `operational_partner`
- the capability is explicitly marked `trusted_peers`
- the request type is low risk and well bounded

## Soul-preservation rules

### Mission before scale

- the mesh may grow only through reviewed admission
- no automation may bypass values review for the sake of speed

### Local autonomy before federation

- each node remains sovereign over its exports, imports, and trust relationships
- the mesh is a coordination fabric, not a central authority

### Accountability before convenience

- important decisions must remain attributable to a local operator
- exceptions and overrides must be logged

### Drift must be visible

- peer misalignment is expected to happen occasionally and must be handled explicitly
- the system should normalize re-review, suspension, and scoped trust

## Suspension and rejection triggers

- invalid signatures or key mismatch
- nonce/replay abuse
- repeated failure to respect declared policy
- refusal to refresh mission/value attestation
- governance note materially diverges from Forseti baseline expectations
- operator determination of harmful or incompatible behavior

## MVP operator decisions

For each peer, the operator must be able to set:

- trust tier
- mission alignment status
- allowed service categories
- default approval behavior
- suspension/reinstatement state
- operator note explaining the decision

## Non-goals for the MVP

- globally shared governance state across all peers
- automatic consensus-based policy propagation
- a requirement that all installations expose identical service catalogs
