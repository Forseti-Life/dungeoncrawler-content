# Wire Schemas — Forseti Community Resource Mesh

This document defines the concrete JSON shapes for the `forseti-meshd` MVP.

## Standard envelope

All protocol messages share this envelope:

```json
{
  "message_id": "uuid",
  "schema_version": "v1",
  "message_type": "peer.handshake.request",
  "sender_installation_id": "forseti-node-a",
  "recipient_installation_id": "forseti-node-b",
  "timestamp": "2026-04-13T20:00:00Z",
  "nonce": "base64-random",
  "signature": "base64-signature",
  "payload": {}
}
```

## Discovery response

`GET /.well-known/forseti-node`

```json
{
  "installation_id": "forseti-node-a",
  "display_name": "Forseti Node A",
  "base_url": "https://node-a.example.org",
  "schema_version": "v1",
  "public_key_fingerprint": "ed25519:abc123",
  "endpoints": {
    "handshake": "https://node-a.example.org/forseti-cluster/handshake",
    "status": "https://node-a.example.org/forseti-cluster/status",
    "messages": "https://node-a.example.org/forseti-cluster/messages",
    "service_request": "https://node-a.example.org/forseti-cluster/service-request"
  }
}
```

## Handshake request payload

```json
{
  "schema_version": "v1",
  "sender_installation_id": "forseti-node-a",
  "sender_base_url": "https://node-a.example.org",
  "sender_public_key_fingerprint": "ed25519:abc123",
  "supported_message_types": [
    "peer.handshake.request",
    "peer.handshake.response",
    "peer.status.update",
    "capability.advertise",
    "need.advertise",
    "service.request",
    "service.response",
    "service.status"
  ],
  "supported_capability_categories": [
    "agent_expertise",
    "institutional_service"
  ],
  "mission_attestation": {
    "mission_version": "forseti-mission-v1",
    "values_profile_version": "forseti-values-v1",
    "attested_at": "2026-04-13T20:00:00Z",
    "governance_note": "Local operator affirms current Forseti baseline values."
  }
}
```

## Handshake response payload

```json
{
  "responder_installation_id": "forseti-node-b",
  "responder_base_url": "https://node-b.example.org",
  "responder_public_key_fingerprint": "ed25519:def456",
  "request_nonce": "base64-random",
  "response_nonce": "base64-random-2",
  "endpoint_map": {
    "handshake": "https://node-b.example.org/forseti-cluster/handshake",
    "status": "https://node-b.example.org/forseti-cluster/status",
    "messages": "https://node-b.example.org/forseti-cluster/messages",
    "service_request": "https://node-b.example.org/forseti-cluster/service-request"
  },
  "supported_message_types": [
    "peer.handshake.request",
    "peer.handshake.response",
    "peer.status.update",
    "capability.advertise",
    "need.advertise",
    "service.request",
    "service.response",
    "service.status"
  ],
  "supported_capability_categories": [
    "agent_expertise",
    "institutional_service"
  ],
  "mission_attestation": {
    "mission_version": "forseti-mission-v1",
    "values_profile_version": "forseti-values-v1",
    "attested_at": "2026-04-13T20:00:00Z",
    "governance_note": "Node B affirms current Forseti baseline values."
  },
  "recommended_trust_constraints": {
    "max_trust_tier": "verified",
    "suggested_categories": [
      "agent_expertise"
    ]
  }
}
```

## Status update payload

```json
{
  "installation_id": "forseti-node-a",
  "membership_state": "verified",
  "availability": "healthy",
  "queue_depth": 2,
  "last_successful_handshake_at": "2026-04-13T20:00:00Z",
  "software_version": "0.1.0",
  "mission_version": "forseti-mission-v1"
}
```

## Capability advertisement payload

```json
{
  "installation_id": "forseti-node-a",
  "generated_at": "2026-04-13T20:00:00Z",
  "capabilities": [
    {
      "capability_id": "cap-001",
      "category": "agent_expertise",
      "capability_key": "agent.research_support",
      "name": "Research Support",
      "description": "Structured research assistance.",
      "availability_status": "available",
      "export_policy": "trusted_peers",
      "approval_mode": "case_by_case",
      "expected_response_window_seconds": 86400,
      "constraints": {
        "max_priority": "high"
      }
    }
  ]
}
```

## Need advertisement payload

```json
{
  "installation_id": "forseti-node-b",
  "generated_at": "2026-04-13T20:00:00Z",
  "needs": [
    {
      "need_id": "need-001",
      "category": "institutional_service",
      "requested_capability": "institution.policy_review",
      "priority": "medium",
      "requested_until": "2026-04-20T20:00:00Z",
      "context_summary": "Review policy update before release signoff.",
      "policy_constraints": {
        "approval_required": true
      }
    }
  ]
}
```

## Service request payload

```json
{
  "request_id": "req-001",
  "requesting_installation_id": "forseti-node-a",
  "target_installation_id": "forseti-node-b",
  "requested_capability": "institution.policy_review",
  "requested_outcome": "Provide review comments and recommendation.",
  "priority": "high",
  "request_context": {
    "subject": "Mesh policy revision",
    "summary": "Need review before enabling operational partners."
  },
  "data_sensitivity": "internal",
  "approval_requirement": "required",
  "requested_response_window_seconds": 172800
}
```

## Service response payload

```json
{
  "request_id": "req-001",
  "decision": "accepted",
  "decision_reason": "Policy review team available.",
  "handled_by_installation_id": "forseti-node-b",
  "next_status": "in_progress"
}
```

## Service status payload

```json
{
  "request_id": "req-001",
  "status": "completed",
  "status_reason": "Review delivered.",
  "result_summary": "Recommendation: proceed with reviewed safeguards.",
  "updated_at": "2026-04-14T12:00:00Z"
}
```

## Error response shape

All error responses from `forseti-meshd` should be structured:

```json
{
  "error_code": "policy_blocked",
  "message": "Peer is not allowed to request this capability.",
  "request_id": "req-001",
  "message_id": "uuid"
}
```
