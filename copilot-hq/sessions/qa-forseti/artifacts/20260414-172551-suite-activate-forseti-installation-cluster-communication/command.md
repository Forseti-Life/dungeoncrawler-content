# Suite Activation: forseti-installation-cluster-communication

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-14T17:25:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-installation-cluster-communication"`**  
   This links the test to the living requirements doc at `features/forseti-installation-cluster-communication/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-installation-cluster-communication-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-installation-cluster-communication",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-installation-cluster-communication"`**  
   Example:
   ```json
   {
     "id": "forseti-installation-cluster-communication-<route-slug>",
     "feature_id": "forseti-installation-cluster-communication",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-installation-cluster-communication",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-installation-cluster-communication

## Setup

- Two local Forseti installations with distinct base URLs and installation identities
- Trust/bootstrap material configured outside synced public config
- Distinct operator accounts able to review peer admission and service approvals where needed

## Tests

1. **Handshake admission (`CRM-HS-001` to `CRM-HS-007`)**
   - Register a peer installation, complete a successful signed handshake, and verify the peer enters `pending_review` before operator approval.
2. **Mission alignment review (`CRM-HS-004`, `CRM-POL-004`)**
   - Confirm operator can review mission/value attestation and elevate or deny admission based on alignment.
3. **Capability advertisement (`CRM-SVC-001` to `CRM-SVC-005`)**
   - Publish at least one `agent_expertise` capability and one `institutional_service` capability across two nodes.
4. **Need publication**
   - Publish a structured need and confirm it appears correctly for an admitted peer.
5. **Service request happy path (`CRM-SVC-004` to `CRM-SVC-006`)**
   - Submit a valid service request from node A to node B, accept it, issue at least one status update, and mark it completed.
6. **Approval-required policy path (`CRM-POL-003`, `CRM-POL-005`)**
   - Mark a capability as `approval_required` and verify the request pauses for operator decision before execution.
7. **Invalid signature rejection (`CRM-SEC-001`)**
   - Send a message with an invalid signature and confirm rejection with explicit audit output.
8. **Replay protection (`CRM-SEC-002`, `CRM-SEC-003`)**
   - Replay a previously accepted message and confirm it is rejected or treated idempotently without duplicate state changes.
9. **Suspension enforcement (`CRM-HS-008`, `CRM-POL-007`)**
   - Suspend a peer and confirm outbound and inbound cluster exchanges are blocked.
10. **Operator visibility (`CRM-OPS-002`)**
   - Verify admin UI shows peer status, last handshake, mission alignment state, recent message outcomes, and request lifecycle history.

### Acceptance criteria (reference)

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
- Agent: qa-forseti
- Status: pending
