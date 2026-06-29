# Documentation Framework — Forseti Community Resource Mesh

This feature can grow into a large multi-release body of work. To keep it manageable, document it as a **layered specification set** rather than a single expanding brief.

## Recommended framework

Use five layers:

1. **Charter**
   - Purpose: explain why the work exists, its mission fit, current scope, owner, and project linkage.
   - File: `feature.md`

2. **Contract**
   - Purpose: define what must be true regardless of implementation details.
   - Files:
     - `01-acceptance-criteria.md`
     - future requirement packs with stable IDs

3. **Architecture**
   - Purpose: explain how the system is expected to work conceptually.
   - Files:
     - `02-implementation-notes.md`
     - future architecture extensions by domain

4. **Verification**
   - Purpose: define how claims are proven.
   - Files:
     - `03-test-plan.md`
     - future interoperability, security, and operational validation packs

5. **Decisions**
   - Purpose: record why major choices were made and what alternatives were rejected.
   - Recommended future location:
     - `adr/ADR-001-*.md`
     - `adr/ADR-002-*.md`

## Scaling pattern for a large feature

As the mesh grows, avoid putting everything into `02-implementation-notes.md`. Split by domain using stable ranges:

- `10-domain-model.md`
  - entities, identifiers, lifecycle states
- `20-handshake-and-trust.md`
  - admission flow, authentication, trust tiers, mission attestation
- `30-policy-and-governance.md`
  - export rules, operator approvals, suspension/review rules
- `40-service-catalog.md`
  - capabilities, needs, service types, routing constraints
- `50-message-protocol.md`
  - message schemas, delivery semantics, retries, idempotency
- `60-admin-and-ux.md`
  - operator workflows, dashboards, review queues
- `70-security-controls.md`
  - keys, nonce handling, replay prevention, audit and abuse handling
- `80-rollout-and-operations.md`
  - migration, onboarding, rollout phases, monitoring
- `90-roadmap.md`
  - future-state compute/storage and later federation extensions

This gives the work a predictable home for new requirements instead of forcing repeated restructuring.

## Requirement ID strategy

Adopt stable requirement identifiers early.

Suggested prefixes:

- `CRM-AC-*`
  - acceptance criteria
- `CRM-HS-*`
  - handshake and trust requirements
- `CRM-POL-*`
  - policy and governance requirements
- `CRM-SVC-*`
  - service-catalog requirements
- `CRM-SEC-*`
  - security requirements
- `CRM-OPS-*`
  - operational requirements

Example:

- `CRM-HS-001` — A peer handshake must include a signed mission-and-values attestation.
- `CRM-POL-003` — A local installation must be able to mark a capability as `approval_required`.

## Traceability model

Large systems stay coherent when every major statement can be traced.

For this feature, maintain traceability like this:

- **Charter** states purpose and scope
- **Acceptance criteria** define what must hold true
- **Domain/architecture docs** elaborate the mechanism
- **ADR records** explain why key decisions exist
- **Test plan** points back to requirement IDs

That means future test cases and implementation slices can reference stable requirement IDs instead of prose paragraphs.

## Change-management rules

To keep the docs healthy over time:

- keep `feature.md` short and strategic
- keep `01-acceptance-criteria.md` stable and additive
- move detailed design into domain-specific docs once a section grows materially
- use ADRs for consequential decisions, not inline paragraphs
- prefer adding a new focused file over turning one file into a catch-all
- treat mission/profile versions as versioned contracts, not loose narrative text

## Build strategy rule

For this feature, follow an **open-source adopt-before-build** rule:

1. when a hard technical requirement appears, search first for a usable open-source solution
2. adopt or extend an existing open-source component when it satisfies the requirement cleanly
3. build custom Forseti-specific logic only when:
   - no acceptable open-source option exists
   - the existing option violates mission, trust, or autonomy requirements
   - the gap is specific to the Forseti mesh model

This keeps the project focused on its differentiators: governance, mission alignment, and mesh behavior, instead of rebuilding generic infrastructure unnecessarily.

## Recommended next evolution for this feature

The current mesh notes are ready to split along the future structure above. The best next documents would be:

1. `20-handshake-and-trust.md`
2. `30-policy-and-governance.md`
3. `40-service-catalog.md`
4. `50-message-protocol.md`

That would let the current `02-implementation-notes.md` remain a high-level architecture overview while deeper requirements expand cleanly underneath it.
