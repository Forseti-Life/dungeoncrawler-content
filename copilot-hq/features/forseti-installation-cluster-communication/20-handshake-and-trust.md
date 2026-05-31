# Handshake and Trust — Forseti Community Resource Mesh

This document defines the MVP admission and trust model for a multi-node Forseti mesh.

## MVP objective

Allow two independent Forseti installations to:

1. discover each other
2. perform a signed handshake
3. exchange mission/value attestations
4. complete operator review
5. enter a bounded trust relationship for capability sync and service requests

## Requirement set

- `CRM-HS-001` — Each installation must have a stable `installation_id`.
- `CRM-HS-002` — Each installation must hold signing key material outside public synced config.
- `CRM-HS-003` — Handshake requests and responses must be signed and time-bounded.
- `CRM-HS-004` — Handshake must include a mission-and-values attestation.
- `CRM-HS-005` — A technically valid handshake is insufficient for operational trust without operator review.
- `CRM-HS-006` — Peer admission must move through explicit membership states.
- `CRM-HS-007` — Mission/value attestations must be versioned and renewable.
- `CRM-HS-008` — A peer may be suspended or rejected after admission if trust or mission alignment degrades.
- `CRM-HS-009` — Only admitted peers may receive capability sync and service-request traffic.
- `CRM-HS-010` — Trust must be local and non-transitive.

## Endpoints

- `GET /.well-known/forseti-node`
  - public discovery metadata
- `POST /forseti-cluster/handshake`
  - signed request/response handshake exchange
- `GET /forseti-cluster/status`
  - post-admission status and health surface for trusted peers

## Membership states

- `discovered`
- `pending_review`
- `verified`
- `operational_partner`
- `suspended`
- `rejected`

## Admission flow

### Step 1 — discovery

The operator or local system records a candidate peer and fetches its discovery metadata.

Required fields:

- `installation_id`
- `base_url`
- `display_name`
- supported schema version
- cluster endpoints
- public key fingerprint

Result:

- peer is recorded as `discovered`

### Step 2 — signed handshake request

The local installation sends a signed handshake request containing:

- `schema_version`
- `sender_installation_id`
- `sender_base_url`
- `sender_public_key_fingerprint`
- `timestamp`
- `nonce`
- `supported_message_types`
- `supported_capability_categories`
- `mission_attestation`

`mission_attestation` includes:

- `mission_version`
- `values_profile_version`
- `attested_at`
- `governance_note`

### Step 3 — signed handshake response

The remote installation validates the request and returns:

- `responder_installation_id`
- `responder_base_url`
- `responder_public_key_fingerprint`
- `request_nonce`
- `response_nonce`
- `timestamp`
- `endpoint_map`
- `supported_message_types`
- `supported_capability_categories`
- `mission_attestation`
- `recommended_trust_constraints`

### Step 4 — technical verification

The local installation validates:

- request/response signatures
- timestamp freshness
- nonce binding
- endpoint reachability
- schema compatibility

If technical validation passes:

- peer moves to `pending_review`

### Step 5 — mission and governance review

The operator reviews:

- mission version affirmed
- values profile version
- governance note
- service/export posture
- any compatibility warnings

Operator outcomes:

- `aligned`
- `needs_review`
- `not_aligned`

### Step 6 — bounded admission

The operator chooses a trust tier and export scope:

- `verified`
  - capability sync allowed
  - need publication allowed
  - low-risk coordination allowed
- `operational_partner`
  - service requests allowed within policy

### Step 7 — renewal and drift review

Peers must refresh:

- current status
- reachable endpoints
- key validity
- mission/value attestation

Re-review is triggered by:

- attestation/profile version change
- policy violation
- repeated delivery failures
- operator concern about governance drift

## Admission records to store

- peer identity
- peer public key fingerprint
- trust tier
- mission attestation version
- mission alignment status
- operator review note
- admission decision timestamp
- last successful handshake timestamp
- suspension/rejection reason if present

## What trust means in the MVP

### `verified`

The peer is allowed to:

- exchange status messages
- advertise capabilities
- publish needs
- appear in routing candidates where policy allows

The peer is not automatically allowed to:

- receive high-impact service exports
- bypass approval-required workflows

### `operational_partner`

The peer is allowed to:

- participate in approved service-request workflows
- receive routed requests for permitted categories
- return structured service decisions and status updates

## Non-goals for the MVP

- federated voting or constitutional amendment
- public/open registration with no operator review
- transitive trust expansion across peers
- automatic admission of peers based only on mission version match
