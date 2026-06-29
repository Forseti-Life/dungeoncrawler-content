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
