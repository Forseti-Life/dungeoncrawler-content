# Message Protocol — Forseti Community Resource Mesh

This document defines the message envelope and transport rules for the MVP.

## MVP protocol objective

Provide a small, auditable, signed message protocol that supports handshake, status, catalog exchange, and service requests between two installations.

## Requirement set

- `CRM-SEC-001` — Every inbound cluster message must be signed.
- `CRM-SEC-002` — Every message must include timestamp and nonce for replay protection.
- `CRM-SEC-003` — Message processing must be idempotent by message id.
- `CRM-OPS-001` — Failed delivery must be retryable.
- `CRM-OPS-002` — Delivery outcomes must be logged and queryable.
- `CRM-OPS-003` — The MVP must support request/response style flows over HTTPS.

## Standard envelope

Every cluster message should use the same top-level envelope:

- `message_id`
- `schema_version`
- `message_type`
- `sender_installation_id`
- `recipient_installation_id`
- `timestamp`
- `nonce`
- `signature`
- `payload`

## Supported MVP message types

- `peer.handshake.request`
- `peer.handshake.response`
- `peer.status.update`
- `capability.advertise`
- `need.advertise`
- `service.request`
- `service.response`
- `service.status`
- `announcement.broadcast`

## Delivery semantics

### Transport

- HTTPS only
- JSON payloads
- one logical message per request

### Acceptance rules

An inbound message is accepted only when:

- sender is known and not suspended
- signature validates against stored peer key
- timestamp is inside configured freshness window
- nonce has not been seen before for that sender
- schema version is supported

### Idempotency

- `message_id` must be unique
- duplicate `message_id` should not create duplicate state transitions
- duplicates should be logged as replays or duplicates depending on context

### Retry model

- transient delivery failures should enter a retry queue
- retry attempts should be bounded and logged
- permanently failed messages should surface in operator audit views

## Endpoint usage

| Endpoint | Purpose | Primary message types |
| --- | --- | --- |
| `POST /forseti-cluster/handshake` | admission handshake | `peer.handshake.request`, `peer.handshake.response` |
| `GET /forseti-cluster/status` | peer status/health | `peer.status.update` |
| `POST /forseti-cluster/messages` | generic inbound message intake | `capability.advertise`, `need.advertise`, `service.request`, `service.response`, `service.status`, `announcement.broadcast` |
| `POST /forseti-cluster/service-request` | convenience endpoint for service requests | `service.request` |
| `POST /forseti-cluster/service-request/{id}/decision` | convenience endpoint for request decisions | `service.response`, `service.status` |

## HTTP result expectations

- `200` — accepted and processed
- `202` — accepted for asynchronous processing
- `400` — malformed message
- `401` or `403` — authentication/trust failure
- `409` — duplicate or conflicting message state
- `422` — policy or validation failure
- `500` — unexpected local processing failure

## MVP operational rules

- keep message schemas compact and typed
- prefer explicit message types over generic action names
- do not add real-time websocket requirements to the MVP
- do not require a third-party broker

## Two-node MVP success condition

The message protocol is sufficient for the MVP when two installations can:

1. complete handshake
2. exchange status and capability messages
3. submit and process a service request
4. update service status through completion
5. reject replayed or invalid messages with explicit audit output

Detailed JSON examples and daemon lifecycle rules are defined in:

- `52-wire-schemas.md`
- `53-process-state-machines.md`
