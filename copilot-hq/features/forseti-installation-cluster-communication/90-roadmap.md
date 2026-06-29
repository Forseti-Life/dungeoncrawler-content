# Roadmap — Forseti Community Resource Mesh

This roadmap turns the mesh MVP into a sequenced delivery pipeline for Forseti.

## Delivery posture

- **Backend-first:** build `forseti-meshd` before the full Drupal operator surface
- **Drupal-owned interface:** deliver the `forseti_cluster` module as the admin/UI layer over the daemon
- **Open-source adopt-before-build:** search for usable open-source components before writing custom infrastructure

## MVP milestone

The MVP is complete when two Forseti installations can:

1. run `forseti-meshd` independently
2. discover and handshake securely
3. complete operator trust review
4. advertise capabilities and needs
5. submit and process one shared-agent service request
6. submit and process one institutional-service request
7. review all peer, request, and audit state through Drupal

## Pipeline slices

| Slice | Title | Primary owner | Depends on | Exit criteria |
| --- | --- | --- | --- | --- |
| CRM-WI-001 | Open-source stack validation | pm-forseti / ba-forseti | none | shortlist confirmed for backend framework, signing library, and data stack |
| CRM-WI-002 | `forseti-meshd` runtime scaffold | dev-forseti | CRM-WI-001 | daemon starts, config loads, health endpoint responds |
| CRM-WI-003 | Identity, keys, and config model | dev-forseti | CRM-WI-002 | local installation identity and signing keys load from private storage |
| CRM-WI-004 | Peer registry and discovery | dev-forseti | CRM-WI-003 | peer records can be created and `/.well-known/forseti-node` works |
| CRM-WI-005 | Signed handshake and membership states | dev-forseti | CRM-WI-004 | handshake request/response works and peer enters `pending_review` |
| CRM-WI-006 | Policy engine and mission alignment review | dev-forseti / ba-forseti | CRM-WI-005 | trust tier, mission alignment, and export policy decisions are enforceable |
| CRM-WI-007 | Capability and need catalog | dev-forseti | CRM-WI-006 | local capabilities/needs publish and remote copies persist |
| CRM-WI-008 | Service request lifecycle | dev-forseti | CRM-WI-007 | request, decision, status, and completion flow work end to end |
| CRM-WI-009 | Delivery worker, retries, and audit log | dev-forseti | CRM-WI-008 | outbound queue, retry policy, and audit trail are operational |
| CRM-WI-010 | Drupal module admin shell | dev-forseti | CRM-WI-006 | peer registry, review, and audit screens render from daemon APIs |
| CRM-WI-011 | Drupal capability/request workflows | dev-forseti | CRM-WI-008, CRM-WI-010 | operator can manage capabilities and requests through Drupal |
| CRM-WI-012 | Two-node QA and release readiness | qa-forseti | CRM-WI-009, CRM-WI-011 | full MVP scenario passes in a two-installation environment |

## Recommended release grouping

### Release A — daemon foundation

- CRM-WI-001
- CRM-WI-002
- CRM-WI-003
- CRM-WI-004

Outcome:

- a standalone daemon exists and can represent local/peer identity

### Release B — trust and governance

- CRM-WI-005
- CRM-WI-006

Outcome:

- peers can handshake, enter review, and be admitted or rejected under policy

### Release C — mesh coordination

- CRM-WI-007
- CRM-WI-008
- CRM-WI-009

Outcome:

- the daemon can exchange useful work across two installations

### Release D — Drupal operator experience

- CRM-WI-010
- CRM-WI-011
- CRM-WI-012

Outcome:

- the mesh becomes operable from the Forseti UI and releasable as an MVP

## Immediate next tasks for Forseti pipeline

1. confirm the backend shortlist under the open-source adopt-before-build rule
2. reserve a Forseti release slot for Release A
3. dispatch BA elaboration for daemon config, peer review workflow, and policy defaults
4. dispatch dev on runtime scaffold immediately after the shortlist is approved

## Notes

- `forseti-meshd` is the critical path
- Drupal UI should not block daemon foundation work
- compute/storage remain explicitly out of the MVP
