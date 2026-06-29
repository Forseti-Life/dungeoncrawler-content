# Admin and UX — Forseti Community Resource Mesh

This document defines the MVP operator interface model.

## Interface principle

The **operator interface should be a Drupal module** so mesh administration lives in the same working environment as the rest of the Forseti platform.

Recommended module:

- `forseti_cluster`

The Drupal module is the interface layer, not the protocol engine.

## Backend principle

The mesh backend should run as a standalone service and expose HTTP APIs consumed by the Drupal module.

That means:

- Drupal owns forms, dashboards, views, permissions, and operator workflows
- `forseti-meshd` owns protocol logic, persistence, signing, workers, and policy evaluation

## MVP Drupal surfaces

### 1. Peer registry screen

Show:

- peer display name
- installation id
- membership state
- trust tier
- mission alignment status
- last handshake
- last seen
- suspend/review actions

### 2. Peer review form

Allow operator to:

- review mission/value attestation
- set mission alignment status
- choose trust tier
- choose allowed categories
- set approval defaults
- add operator notes

### 3. Capability management screen

Allow operator to:

- create/edit local capabilities
- set export policy
- set approval mode
- set availability
- publish/unpublish capabilities

### 4. Service request queue

Show:

- inbound requests
- outbound requests
- deferred requests awaiting approval
- request status timeline
- decision reason

### 5. Audit screen

Show:

- recent protocol events
- rejected messages
- replay attempts
- failed deliveries
- peer suspension/rejection reasons

## Drupal -> mesh daemon interactions

The Drupal module should call the daemon for:

- peer discovery
- handshake initiation
- peer review submission
- capability publishing
- request creation
- request approval/decision actions
- audit and status retrieval

## Mesh daemon -> Drupal interactions

For the MVP, prefer a pull model:

- Drupal requests current peer and request state from daemon APIs
- daemon does not need to call back into Drupal except for optional future notifications

This keeps coupling lower and simplifies operations.

## Permissions model

Suggested Drupal permissions:

- `administer forseti mesh peers`
- `review forseti mesh trust`
- `manage forseti mesh capabilities`
- `approve forseti mesh requests`
- `view forseti mesh audit`

## UX rule for stack decisions

Prefer the same technical world when practical:

- Drupal module for UI
- backend technologies already familiar in the Forseti environment when they are sufficient

Exceptions are allowed when a different backend technology has **clear technical and business value**:

- materially simpler operations
- materially stronger security/reliability
- materially faster delivery or maintenance

Those exceptions should be recorded in an ADR.
