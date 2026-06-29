# Implementation Notes — forseti-installation-cluster-communication

## Recommended MVP shape

1. Define installation identity and trust material.
2. Add peer registry and admin configuration UI.
3. Implement signed health/handshake endpoint.
4. Implement signed cluster message endpoint and outbound sender.
5. Add operator-facing status/log surface.

## Recommended technical foundation

Build this as a **Drupal-native federated node layer over HTTPS**, not as shared database clustering.

Adopt an **open-source buy-before-build** posture for hard technical requirements. Search first for usable open-source components, protocols, or libraries before designing custom infrastructure, and build custom code only where Forseti-specific trust, mission, or governance behavior is the real differentiator.

Recommended stack:

- Per-installation identity plus **Ed25519** signing keys
- Standard **HTTPS REST** endpoints between installations
- **HTTP message signatures** or signed JSON/JWS envelopes for request authentication
- Explicit **inbox/outbox** message flow with retries, idempotency, nonce replay protection, and operator-visible delivery results
- Peer registry, message log, capability metadata, and nonce tracking stored in site-owned Drupal tables/entities

This gives Forseti installations a secure communication substrate that works over normal web infrastructure and does not require a shared broker or shared database.

Longer term, treat this substrate as the base for a **community resource mesh**: independent Forseti installations should be able to advertise and consume shared commodity resources and community services without collapsing into one shared database or one centralized control plane.

Initial MVP focus:

- shared **agent expertise**
- shared **institutional-management services**

Future-state extensions:

- shared **compute**
- shared **storage**

## Suggested technical boundaries

- New module: `sites/forseti/web/modules/custom/forseti_cluster/`
- Keep private trust/signing material out of synced config.
- Use Drupal HTTP client for outbound peer calls.
- Use explicit signed JSON envelopes rather than implicit shared-state assumptions.
- Store peer definitions, peer status, and message log metadata in site-owned entities/tables.

## First use cases worth supporting

- Peer reachability / health exchange
- Cross-installation announcement broadcast
- Cross-installation suggestion relay
- Agent and institutional-service advertisement

## Conceptual model

Assume **autonomous peers** as the default relationship model: each Forseti installation owns its own users, data, policy, and uptime, but can selectively trust and communicate with other installations.

Under that model, split cross-installation behavior into three lanes:

### 1. Federated

Use federation for things that must be understood across installation boundaries but should still remain locally governed.

Examples:

- installation identity and trust bootstrap
- peer discovery / addressing
- capability advertisement
- resource and service advertisement
- cross-installation commands or requests
- portable references to shared objects, users, or workflows

Federated objects should be addressable, signed, versioned, and resilient to temporary disconnects.

### 2. Replicated

Replicate only data that truly benefits from local copies on more than one installation.

Examples:

- announcement feeds
- selected knowledge artifacts
- approved public or community-facing content
- narrow operational caches that improve resilience
- optional replicated service catalogs or public capability indexes

Replication should be selective, explicit, and policy-driven. Do **not** begin with full database or table replication.

### 3. "How you doing" traffic

Keep lightweight operational traffic separate from both federation and replication.

Examples:

- heartbeat / liveness
- peer health and version
- queue depth / backlog status
- last successful handshake
- maintenance mode / degraded-state notices

This traffic is mostly for operator visibility and routing decisions, not business-data synchronization.

## Resource-sharing model

The desired ecosystem is not just peer messaging. It should let Forseti installations contribute to and draw from a shared pool of community capacity.

Resource categories to design for:

- **Agent expertise:** specialized seats or role capabilities that another installation can request or subscribe to
- **Institutional services:** accounting, governance workflows, release oversight, QA capacity, knowledge curation
- **Individual contribution:** local members or operators contributing skill, review, data, or service capacity back into the mesh
- **Compute (future state):** background jobs, AI workloads, batch processing, scheduled automation
- **Storage (future state):** artifact hosting, mirror storage, backups, knowledge distribution, durable shared outputs

That suggests three additional concepts on top of transport:

1. **Capability advertisement** — what this installation can offer
2. **Need expression** — what this installation wants help with
3. **Placement / routing policy** — when a request should stay local versus be offered to trusted peers

## Default guidance for MVP scope

For the first implementation:

- **Federate:** identity, trust, handshake, capabilities, needs, and signed message delivery
- **Replicate:** nothing by default beyond optional low-risk announcements or relay-safe public artifacts
- **Exchange "how you doing" traffic:** yes, from day one, because it supports operator trust and peer status visibility

## Relevant precedents

There is no single existing system that exactly matches the Forseti Community Resource Mesh, but there are strong precedents for the individual patterns:

- **ActivityPub:** precedent for inbox/outbox federation, actor identity, and signed server-to-server delivery. Good model for how independent nodes exchange versioned activities without shared storage.
- **Matrix:** precedent for federated server-to-server communication with durable message/state exchange between autonomous homeservers. Good model for practical federation and peer trust between independent deployments.
- **XMPP:** precedent for decentralized routing, presence, capability exchange, and extensible inter-server communication. Good model for how independent operators can participate in a common network while keeping local control.
- **Email/SMTP-style store-and-forward:** precedent for resilient asynchronous delivery between independently run servers.
- **Distributed volunteer/resource systems:** precedent for contributing local capacity to a wider network, even though Forseti's initial mesh is service- and expertise-oriented rather than compute-oriented.

The takeaway is that Forseti should borrow:

- **inbox/outbox and actor-like identity** from ActivityPub
- **practical federated server-to-server patterns** from Matrix
- **capability/presence thinking** from XMPP
- **asynchronous retry-tolerant delivery** from email/store-and-forward systems

## Initial architecture sequence

Architect this in layers:

### Layer 1 — Trust and identity

- installation identity
- installation keys
- trusted peer registry
- handshake and peer verification

### Layer 2 — Transport

- signed outbound message sender
- signed inbound message endpoint
- nonce/replay protection
- delivery log, retry queue, failure handling

### Layer 3 — Capability and need exchange

- capability advertisement
- need expression
- peer status and service availability
- operator-visible admin UI

### Layer 4 — Resource coordination

- request/offer matching for agent expertise
- request/offer matching for institutional services
- approval/routing policy for cross-installation work
- audit trail of offered, accepted, rejected, and completed exchanges

### Layer 5 — Future-state extensions

- shared compute workloads
- shared storage/mirroring
- selective replicated catalogs or knowledge distribution

This keeps the mesh grounded: identity and trust first, then transport, then service coordination, then later infrastructure pooling.

## Required data structures

Define the MVP around a small set of explicit objects:

- **Installation**
  - installation_id
  - base_url
  - display_name
  - public_key
  - trust_status
  - trust_tier
  - mission_attestation_version
  - mission_alignment_status
  - capabilities_version
  - last_handshake_at

- **PeerCapability**
  - capability_id
  - installation_id
  - category (`agent_expertise`, `institutional_service`)
  - name
  - description
  - availability_status
  - export_policy
  - routing_constraints

- **PeerNeed**
  - need_id
  - installation_id
  - category
  - requested_service
  - priority
  - policy_constraints
  - requested_until

- **ClusterMessage**
  - message_id
  - message_type
  - sender_installation_id
  - recipient_installation_id
  - created_at
  - nonce
  - signature
  - payload
  - delivery_status

- **ServiceRequest**
  - request_id
  - requesting_installation_id
  - target_installation_id
  - request_type
  - requested_capability
  - requested_outcome
  - request_context
  - review_requirement
  - status (`requested`, `accepted`, `deferred`, `rejected`, `completed`, `cancelled`)
  - audit_ref

- **ExportPolicyRule**
  - rule_id
  - scope (`category`, `capability`, `peer`)
  - scope_ref
  - export_level (`local_only`, `trusted_peers`, `approval_required`)
  - allowed_trust_tiers
  - requires_human_review
  - updated_at

## Integration points

Keep the endpoint set narrow and explicit:

- `GET /.well-known/forseti-node`
  - optional discovery metadata
  - installation id, display name, canonical cluster endpoint URLs

- `POST /forseti-cluster/handshake`
  - peer verification and trust bootstrap

- `GET /forseti-cluster/status`
  - health/version/capability summary for trusted peers

- `POST /forseti-cluster/messages`
  - signed inbound message endpoint

- `GET /forseti-cluster/capabilities`
  - machine-readable offered capabilities

- `GET /forseti-cluster/needs`
  - machine-readable requested needs

- `POST /forseti-cluster/service-request`
  - submit a request for agent or institutional service capacity

- `POST /forseti-cluster/service-request/{id}/decision`
  - accept / reject / defer / complete transitions

Additional internal integration points should include:

- Drupal admin UI for peer management and trust review
- Drupal queue workers for outbound delivery and retries
- Drupal logging / audit views for operator visibility
- existing agent-management and institutional workflow surfaces as future service consumers/providers
- optional `/.well-known` metadata for discovery without requiring a central registry

## Core processes

The mesh should be built around a small number of repeatable processes:

1. **Peer onboarding**
   - register peer
   - verify installation identity
   - complete trust handshake
   - enable or disable peer

2. **Capability publishing**
   - advertise available agent and institutional services
   - update availability and policy constraints
   - expose current service catalog to trusted peers

3. **Need publishing**
   - publish unmet needs
   - express priority and policy constraints
   - allow trusted peers to evaluate whether they can help

4. **Service request lifecycle**
   - submit request
   - validate trust and policy
   - accept / reject / defer
   - report status until complete

5. **Operational heartbeat**
   - exchange health, version, and backlog signals
   - detect degraded peers
   - suppress routing to unavailable peers

## Trust tiers and governance model

Use simple operator-visible trust tiers rather than a binary trusted/untrusted flag:

- **pending_review**
  - handshake succeeded, but no service exchange is allowed yet
- **verified**
  - peer may receive status, capability, and need traffic allowed by export policy
- **operational_partner**
  - peer may participate in approved service-request flows for shared operations
- **suspended**
  - peer record retained, but all exchange blocked except explicit admin review

This lets the MVP model real governance without introducing a heavy PKI or automatic transitive trust.

## Mission and values attestation

Because the mesh is intended to preserve the Forseti mission across autonomous installations, handshake should include a values-alignment step in addition to cryptographic verification.

Each installation should publish and sign a compact attestation that includes:

- mission statement version the node affirms
- operating-values/profile version
- attestation timestamp
- installation identity
- optional operator note or public description of local governance posture

The local operator should be able to record one of:

- **aligned**
- **needs_review**
- **not_aligned**

Cryptographic handshake can succeed while mission alignment remains `needs_review`; in that case the peer should not be elevated beyond `pending_review`.

## What it means to join the Forseti mesh

Joining the mesh should be treated as admission into a values-governed cooperative network, not merely registration of another API client.

A node is part of the mesh only when it has:

1. a stable installation identity
2. a verified signing key and reachable endpoint set
3. a signed mission-and-values attestation
4. an operator-reviewed trust record
5. explicit export-policy boundaries for what it may send and receive

This distinction matters: many systems can exchange messages, but only values-aligned peers should gain the ability to represent the Forseti mesh operationally.

## Handshake authentication process

Design the handshake as a staged admission flow rather than a single request:

### Stage 0 — discovery
- local operator enters a peer base URL or imports a well-known record
- system fetches public metadata from `/.well-known/forseti-node`
- peer is recorded as `discovered`, not yet trusted

### Stage 1 — cryptographic challenge
- local system sends `peer.handshake.request`
- request includes installation id, public key fingerprint, nonce, timestamp, supported schema versions, and mission attestation
- remote system verifies signature freshness and returns `peer.handshake.response`
- response includes its own nonce binding, public key material, endpoint declarations, schema version, and mission attestation

### Stage 2 — technical verification
- local system verifies signature, timestamp window, nonce binding, and endpoint reachability
- local operator sees reachability, version compatibility, and technical warnings
- successful technical validation moves peer to `pending_review`

### Stage 3 — mission alignment review
- operator reviews the peer's declared mission version, value profile, governance note, and export posture
- operator records `aligned`, `needs_review`, or `not_aligned`
- peers marked `not_aligned` cannot progress into service exchange

### Stage 4 — membership approval
- operator selects trust tier and permitted service/export scope
- peer becomes `verified` or `operational_partner`
- first capability sync is allowed only after this step

### Stage 5 — ongoing renewal
- peers must periodically refresh status, key validity, and mission attestation
- attestation/profile version changes trigger re-review
- repeated delivery failures, policy violations, or mission drift can demote the peer to `suspended`

## Handshake payload design

For the MVP, the request and response should carry a compact but explicit payload.

### `peer.handshake.request`
- schema_version
- sender_installation_id
- sender_base_url
- sender_public_key_fingerprint
- timestamp
- nonce
- supported_message_types
- supported_capability_categories
- mission_attestation
  - mission_version
  - values_profile_version
  - attested_at
  - governance_note

### `peer.handshake.response`
- schema_version
- responder_installation_id
- responder_base_url
- responder_public_key_fingerprint
- request_nonce
- response_nonce
- timestamp
- endpoint_map
- supported_message_types
- supported_capability_categories
- mission_attestation
- recommended_trust_constraints

## Mesh membership states

Use explicit lifecycle states so operators understand where each peer stands:

- **discovered**
  - metadata seen, but no handshake attempt completed
- **pending_review**
  - technical checks passed, awaiting human trust decision
- **verified**
  - trusted for low-risk coordination and catalog exchange
- **operational_partner**
  - trusted for approved service-request workflows
- **suspended**
  - previously admitted, now paused due to risk, drift, or failure
- **rejected**
  - explicitly denied admission because technical or mission requirements were not met

## Soul-preservation guardrails

To keep the soul of Forseti intact, the mesh needs governance rules as much as protocol rules.

### 1. Mission before convenience
- no peer gains operational trust without values attestation and operator review
- convenience features must not bypass the alignment check

### 2. Local autonomy remains primary
- each installation decides what it exports, what it imports, and who it trusts
- the mesh coordinates peers; it does not erase local governance

### 3. Human accountability at the edges
- high-impact requests must remain reviewable by an operator
- every admission, demotion, and export-policy change must be auditable

### 4. Explicit handling of drift
- mission alignment is not "set and forget"
- changed governance, changed mission profiles, or concerning behavior trigger re-review

### 5. No transitive moral trust
- if peer A trusts peer B and peer B trusts peer C, peer A does not automatically trust peer C
- mission alignment must be reviewed directly by each local installation

## Conditions for suspension or rejection

The system should make it normal to protect the mesh when needed.

Triggers should include:

- invalid or expired signatures
- replay or nonce abuse
- repeated policy violations
- refusal to refresh mission/value attestation
- declared mission profile no longer matching the current Forseti baseline
- operator determination that governance posture is materially misaligned

## Operator decision prompts during admission

The UI should guide operators through a small, repeatable set of questions:

1. Is this installation technically authentic and reachable?
2. Does it affirm the current Forseti mission/version?
3. Does its governance note and operating posture align with our expectations?
4. Which service categories, if any, should be exposed to this peer?
5. Should the peer remain `verified` only, or become an `operational_partner`?

This keeps admission consistent and reduces the risk of accidental trust expansion.

## Service categories for the MVP

The first release should keep service categories narrow and legible:

- **Agent expertise**
  - research support
  - backlog shaping
  - analysis/review assistance
  - domain-specialized agent availability

- **Institutional-management services**
  - policy review
  - release coordination
  - documentation/governance support
  - operational triage and escalation support

Compute and storage should remain out of scope for the first delivery except as future schema-extension points.

## Service-request contract

The service-request payload should be explicit enough for routing, audit, and human review:

- request id
- requesting installation
- requested capability id or category
- requested outcome
- priority
- business context / reason
- data sensitivity declaration
- approval requirement
- desired response window
- status timeline entries

For the MVP, prefer structured JSON fields over freeform blobs so approvals and reporting stay queryable.

## Trust bootstrap flow

Suggested first-pass trust flow:

1. Admin registers peer base URL and expected installation metadata.
2. Local installation generates a handshake request signed with its installation key and includes its current mission/value attestation.
3. Remote installation validates the request and responds with signed peer identity, capability summary, and its own mission/value attestation.
4. Local installation stores peer public key, attestation details, and marks trust state as `verified` or `pending_review`.
5. Only verified peers can exchange service requests and capability/need updates.

This keeps the bootstrap explicit and admin-visible rather than fully automatic.

## Message types for MVP

Start with a small set of message types:

- `peer.handshake.request`
- `peer.handshake.response`
- `peer.status.update`
- `capability.advertise`
- `need.advertise`
- `service.request`
- `service.response`
- `service.status`
- `announcement.broadcast`

Each message type should have:

- schema version
- sender installation id
- recipient or addressing scope
- timestamp
- nonce
- signature
- payload

## Routing and policy model

The routing question is not only "can this peer handle it?" but also "should this request leave the local installation?"

Policy checks should include:

- trust level of the target peer
- service category allowed for export
- required human approval
- current peer availability
- whether the request is local-only by policy

For the MVP, prefer:

- local-first evaluation
- explicit operator override
- no automatic forwarding chains between multiple peers

Recommended routing order:

1. Check whether the request is allowed to leave the local installation.
2. Check whether the capability can be satisfied locally.
3. Evaluate eligible verified peers against trust tier, availability, and policy.
4. Route to one selected peer or present approval options to an operator.
5. Record the final routing decision and reasoning in the audit trail.

## Operator surfaces

The MVP admin UX should expose a small number of decisive views:

- **Peer registry**
  - identity, trust tier, handshake status, last seen, suspend/enable controls
- **Capability catalog**
  - local capabilities, exported capabilities, peer-advertised capabilities, export policy
- **Need and request queue**
  - outstanding needs, inbound requests, outbound requests, approval-required requests
- **Cluster audit view**
  - signed message history, failures, replay rejections, request lifecycle timeline

This keeps the mesh understandable for operators instead of burying governance inside logs or raw entities.

## MVP implementation phases

### Phase A — mesh identity and trust
- installation identity entity/config
- peer registry
- handshake flow
- operator trust UI

### Phase B — transport and audit
- message envelope schema
- signed sender/receiver
- replay protection
- message log and retry worker

### Phase C — capability and need exchange
- capability catalog
- need catalog
- peer status page
- status polling/push update flow

### Phase D — service coordination
- service request object
- decision workflow
- audit/status timeline
- first real shared-agent and institutional-service flows

### Phase E — operator optimization
- approval queue and export policy controls
- request templates for common institutional workflows
- service analytics for demand, fulfillment, and peer reliability

## Architectural cautions

- Do not jump straight to database replication.
- Do not store trust secrets in public config sync.
- Separate cluster transport concerns from higher-level business workflows.
- Plan for clock skew, nonce storage, retries, and operator-visible failure states.

## Alternatives considered

- **Shared database / DB clustering:** rejected as the foundation because it creates the wrong trust boundary and couples independent installations too tightly.
- **NATS / JetStream as the required base:** useful later for tightly controlled operator-managed cluster internals, but too heavy as a mandatory dependency for arbitrary independent Forseti installs.
- **ActivityPub as the core transport:** useful later for public content federation, but too specialized for the initial secure installation-to-installation control and sync layer.
- **Matrix as the core transport:** powerful for real-time communication, but too large and communication-centric for the MVP transport layer.
