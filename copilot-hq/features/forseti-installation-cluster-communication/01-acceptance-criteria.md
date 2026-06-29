# Acceptance Criteria — forseti-installation-cluster-communication

## AC-1 — Installation identity
- Each Forseti installation exposes a stable installation identifier and keeps signing/authentication material outside public synced config.

## AC-2 — Peer registration
- Admins can add, edit, disable, and remove trusted peer installations from a single admin surface.

## AC-3 — Peer handshake
- A peer handshake verifies installation identity, trust material, reachability, and a signed attestation of adherence to the core Forseti mission and operating values.

## AC-4 — Signed inbound message handling
- Inbound cluster messages are accepted only from trusted peers with valid signatures and non-expired timestamps/nonces.

## AC-5 — Standard outbound message envelope
- Outbound messages use a documented envelope with sender id, recipient id or target, timestamp, nonce, signature, message type, and payload.

## AC-6 — Operator visibility
- Admin UI shows peer status, last handshake time, last message result, and recent failures.

## AC-7 — Security posture
- Replay attempts, invalid signatures, and disabled peers are rejected with explicit logging.

## AC-8 — Capability and need registry
- Each installation can publish and update a machine-readable set of offered capabilities and requested needs for agent expertise and institutional services.

## AC-9 — Service request lifecycle
- A trusted peer can submit a structured service request, receive an explicit accept/reject/defer response, and see the resulting status transition recorded.

## AC-10 — Auditability
- Cluster exchanges are queryable by operators as a chronological audit trail with request id, peer, message type, status, and timestamps.

## AC-11 — Export policy controls
- Operators can mark service categories and individual capabilities as `local_only`, `trusted_peers`, or `approval_required` before they are available to the mesh.

## AC-12 — Operator-governed routing
- For MVP service requests, the installation evaluates local handling first and does not auto-forward requests across multiple peers.

## AC-13 — Service-type coverage
- The initial mesh ships with explicit capability shapes for at least:
  - shared agent expertise
  - institutional-management advisory/service workflows

## AC-14 — Human-reviewable request context
- Service requests include sufficient business context for an operator to approve or deny export without inspecting external systems manually.

## AC-15 — Mission alignment review
- Operators can view the latest mission-and-values attestation for each peer and must be able to withhold or downgrade trust when a peer is not aligned.

## AC-16 — Staged mesh admission
- Becoming part of the mesh is a staged process with distinct states for discovered, pending review, verified, operational partner, and suspended membership.

## AC-17 — Ongoing alignment renewal
- Mission/value attestation is renewable, versioned, and re-reviewable so long-lived peers can be revalidated when the mission profile or governance expectations evolve.
