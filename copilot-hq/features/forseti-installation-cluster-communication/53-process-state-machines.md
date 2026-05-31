# Process State Machines — Forseti Community Resource Mesh

This document defines the daemon lifecycle rules for peers, requests, and delivery jobs.

## Peer membership state machine

### States

- `discovered`
- `pending_review`
- `verified`
- `operational_partner`
- `suspended`
- `rejected`

### Transitions

- `discovered -> pending_review`
  - successful technical handshake validation
- `pending_review -> verified`
  - operator approves limited trust
- `pending_review -> operational_partner`
  - operator approves higher trust and service exchange
- `pending_review -> rejected`
  - operator marks peer misaligned or unfit
- `verified -> operational_partner`
  - operator expands trust after experience
- `verified -> suspended`
  - failures, drift, or policy issues
- `operational_partner -> suspended`
  - failures, drift, or policy issues
- `suspended -> pending_review`
  - operator reopens after review
- `suspended -> rejected`
  - operator decides peer should not rejoin

## Service request state machine

### States

- `requested`
- `deferred`
- `accepted`
- `in_progress`
- `completed`
- `rejected`
- `cancelled`

### Transitions

- `requested -> deferred`
  - approval required
- `requested -> accepted`
  - auto-accepted or operator-approved
- `requested -> rejected`
  - policy or capability rejection
- `accepted -> in_progress`
  - local handling begins
- `in_progress -> completed`
  - work/result delivered
- `deferred -> accepted`
  - operator approves
- `deferred -> rejected`
  - operator denies
- `accepted -> cancelled`
  - requester cancels before completion
- `in_progress -> cancelled`
  - cancellation accepted after work starts

## Outbound delivery job state machine

### States

- `queued`
- `in_flight`
- `retry_wait`
- `delivered`
- `failed`

### Transitions

- `queued -> in_flight`
  - worker picks job
- `in_flight -> delivered`
  - remote accepts
- `in_flight -> retry_wait`
  - retryable transport/server failure
- `in_flight -> failed`
  - non-retryable or max-attempt failure
- `retry_wait -> queued`
  - next attempt time reached

## Renewal process

The renewal worker should run continuously and evaluate peers on a schedule.

### Renewal decision process

1. select peers with stale handshake or stale status
2. attempt renewal handshake
3. if renewal succeeds:
   - update timestamps
   - keep membership state unchanged
   - trigger review if mission/version changed
4. if renewal fails transiently:
   - mark peer degraded
   - retry later
5. if renewal fails repeatedly:
   - suspend peer

## Inbound message processing pipeline

For every inbound message:

1. parse JSON
2. validate envelope fields
3. locate peer by sender id
4. check peer not suspended/rejected
5. validate schema version
6. validate timestamp freshness
7. validate nonce uniqueness
8. validate signature
9. dispatch by message type
10. write audit record
11. return structured response

## Routing process

For every outbound service request:

1. check local-first handling
2. fetch candidate peers advertising requested capability
3. filter by membership state
4. filter by export policy
5. filter by approval requirements
6. rank remaining peers
7. persist request
8. queue outbound message or defer for approval

## Message dispatch table

| Message type | Handler |
| --- | --- |
| `peer.handshake.request` | `handle_handshake_request` |
| `peer.handshake.response` | `process_handshake_response` |
| `peer.status.update` | `handle_status_update` |
| `capability.advertise` | `handle_capability_advertise` |
| `need.advertise` | `handle_need_advertise` |
| `service.request` | `handle_service_request` |
| `service.response` | `handle_service_response` |
| `service.status` | `handle_service_status` |
| `announcement.broadcast` | `handle_announcement_broadcast` |

## Suggested timing defaults

- handshake timestamp freshness: 5 minutes
- renewal scan interval: 15 minutes
- status publish interval: 5 minutes
- retry backoff: 1m, 5m, 15m, 60m
- peer suspension threshold: 5 consecutive renewal failures
