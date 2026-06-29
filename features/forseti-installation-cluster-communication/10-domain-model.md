# Domain Model — Forseti Community Resource Mesh

This document defines the core entities and storage model for the standalone MVP.

## Runtime model

The mesh should run as a standalone service on the server, alongside Drupal and the Copilot organizational tooling, with narrow HTTP/webhook or file-based integration points to each.

Recommended runtime name:

- `forseti-meshd`

## Core entities

### Installation

Represents the local or remote mesh node identity.

Fields:

- `installation_id`
- `display_name`
- `base_url`
- `node_role`
  - `local`
  - `peer`
- `public_key_fingerprint`
- `mission_version`
- `values_profile_version`
- `mission_alignment_status`
- `membership_state`
- `trust_tier`
- `last_handshake_at`
- `last_seen_at`
- `created_at`
- `updated_at`

### PeerPolicy

Represents local operator policy toward a peer.

Fields:

- `peer_installation_id`
- `allowed_categories`
- `default_export_level`
- `default_approval_mode`
- `suspension_reason`
- `operator_note`
- `reviewed_by`
- `reviewed_at`

### Capability

Represents a service the local node or peer claims to offer.

Fields:

- `capability_id`
- `installation_id`
- `category`
- `capability_key`
- `name`
- `description`
- `availability_status`
- `export_policy`
- `approval_mode`
- `expected_response_window_seconds`
- `constraints_json`
- `version`

### Need

Represents an unmet need advertised by an installation.

Fields:

- `need_id`
- `installation_id`
- `category`
- `requested_capability`
- `priority`
- `requested_until`
- `context_summary`
- `policy_constraints_json`
- `status`

### ServiceRequest

Represents a request for cross-node help.

Fields:

- `request_id`
- `requesting_installation_id`
- `target_installation_id`
- `requested_capability`
- `requested_outcome`
- `priority`
- `request_context_json`
- `data_sensitivity`
- `approval_requirement`
- `status`
- `decision_reason`
- `created_at`
- `updated_at`
- `completed_at`

### MessageLog

Represents every inbound or outbound protocol message.

Fields:

- `message_id`
- `message_type`
- `sender_installation_id`
- `recipient_installation_id`
- `direction`
  - `inbound`
  - `outbound`
- `http_status`
- `delivery_status`
- `nonce`
- `timestamp`
- `payload_json`
- `error_text`
- `created_at`

### NonceRecord

Tracks replay protection.

Fields:

- `sender_installation_id`
- `nonce`
- `first_seen_at`
- `expires_at`

### OutboundJob

Represents a queued outbound delivery attempt.

Fields:

- `job_id`
- `message_id`
- `target_installation_id`
- `endpoint_url`
- `attempt_count`
- `next_attempt_at`
- `job_status`
- `last_error`
- `created_at`
- `updated_at`

## Storage recommendation

### Production / real server MVP

- **PostgreSQL** as the primary datastore

Why:

- durable transactions
- good concurrency for API + workers
- no separate broker required for MVP job queues
- strong open-source tooling

### Local development

- **SQLite** is acceptable for dev-only local testing

## Minimal schema boundaries

Keep the standalone service state separate from Drupal state.

Recommended database ownership:

- mesh identity
- peer/trust records
- policy records
- capabilities/needs
- service requests
- message log
- nonces
- outbound jobs

Drupal should continue owning:

- site content
- user-facing application data
- existing operational configuration not specific to the mesh daemon

## Integration boundaries

### Drupal -> mesh daemon

- publish local capability definitions
- create service requests from approved Drupal workflows
- read peer/request status for admin UI embedding

### Mesh daemon -> Drupal

- optional webhook or internal API for approved request notifications
- optional admin read endpoints for dashboards
- no direct shared database coupling in MVP

### Copilot HQ / organizational tooling -> mesh daemon

- may register institutional capabilities
- may generate or consume service requests
- should integrate through explicit HTTP or CLI calls rather than direct table access
