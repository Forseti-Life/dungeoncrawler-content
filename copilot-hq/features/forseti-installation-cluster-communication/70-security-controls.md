# Security Controls — Forseti Community Resource Mesh

This document defines the MVP security controls for the standalone daemon.

## Cryptography

- Ed25519 signing keys per installation
- detached signatures over canonicalized JSON envelope bytes
- private keys stored on disk outside repo and outside Drupal synced config

## Key storage

Recommended path pattern:

- `/etc/forseti-meshd/keys/node_ed25519_private.key`
- `/etc/forseti-meshd/keys/node_ed25519_public.key`

Permissions:

- owner: `forseti-meshd`
- mode: `0600` for private key

## Replay prevention

- each inbound message must contain a `nonce`
- nonce scope is `(sender_installation_id, nonce)`
- nonces are stored with expiration
- duplicate nonce inside freshness window is rejected

## Freshness window

Recommended MVP window:

- accept messages whose timestamps are within **5 minutes** of server time

Behavior:

- older messages are rejected unless explicitly replayed from audit tooling in a future admin-only mode

## Signature verification rules

Verify in this order:

1. sender exists and is not suspended
2. schema version is supported
3. timestamp is fresh
4. nonce is unused
5. signature is valid
6. policy allows this message type from this peer

## Policy-sensitive controls

- peers in `pending_review` cannot issue service requests
- peers in `verified` may be restricted to low-risk message types
- peers in `operational_partner` still remain subject to export policy and approval rules

## Audit controls

Every rejected event should log:

- `message_id`
- sender id
- reason code
- timestamp
- endpoint

Suggested reason codes:

- `unknown_peer`
- `peer_suspended`
- `unsupported_schema`
- `stale_timestamp`
- `duplicate_nonce`
- `invalid_signature`
- `policy_blocked`
- `validation_failed`

## Hardening guidance

- run the daemon under its own Unix user
- restrict admin endpoints to local network or authenticated admin access
- separate public mesh endpoints from admin endpoints in routing/middleware
- rotate keys with explicit operator workflow and forced peer revalidation
- never store peer or local secrets in public config sync

## Future-state controls

Not required for MVP, but likely later:

- mTLS between peers
- HSM or managed key storage
- signed mission profile bundles
- tamper-evident audit export
