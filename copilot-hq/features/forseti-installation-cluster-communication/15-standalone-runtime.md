# Standalone Runtime Architecture — Forseti Community Resource Mesh

This document defines the recommended standalone deployment and tech stack for the MVP.

## Recommended runtime shape

Run the mesh as an independent service on the same server as Drupal and Copilot HQ:

- process name: `forseti-meshd`
- reverse-proxied over HTTPS
- separate database
- separate key storage
- separate logs
- systemd-managed

This keeps the mesh operationally independent while still allowing Drupal and Copilot workflows to use it.

The intended split is:

- **Drupal module** for the operator/admin interface
- **standalone mesh daemon** for backend protocol and state

## Recommended tech stack

Default selection rule:

- stay in the same world as the rest of the platform when practical
- allow exceptions when they provide clear technical and business value

Current recommendation for the backend remains Python because it matches the existing operational ecosystem well while still giving a strong standalone service story.

### Application layer

- **Python 3.12**
- **FastAPI**
- **Pydantic**
- **SQLAlchemy**
- **Alembic**
- **httpx**
- **PyNaCl** for Ed25519 signing/verification

### Runtime / serving

- **Uvicorn** behind **Nginx** or **Caddy**
- **systemd** service unit

### Data layer

- **PostgreSQL**

### Background work

- internal DB-backed worker loop for retries, renewal checks, and sync jobs
- no Redis or third-party broker required for MVP

### Observability

- structured JSON logs
- health endpoint
- Prometheus-compatible metrics endpoint if desired, but optional for MVP

## Why this stack

This stack stays:

- open source
- easy to run beside Drupal
- strong for typed APIs and background processing
- independent from Drupal release cycles
- simple enough for MVP without introducing distributed-infrastructure dependencies

## Process architecture

The standalone service should have five internal components:

### 1. API service

Responsibilities:

- handle inbound protocol requests
- validate signatures and policy
- expose admin/status endpoints

### 2. Policy engine

Responsibilities:

- decide whether a peer is eligible
- decide whether a capability may be exported
- decide whether approval is required

### 3. Delivery worker

Responsibilities:

- deliver outbound messages
- retry transient failures
- update message log and job state

### 4. Renewal worker

Responsibilities:

- refresh peer status
- trigger attestation re-review
- mark stale peers degraded or suspended

### 5. Integration adapter layer

Responsibilities:

- expose narrow APIs for Drupal
- expose narrow APIs or CLI for Copilot HQ workflows
- translate external workflow events into mesh requests/capabilities

## Service boundaries

### Public mesh endpoints

- `GET /.well-known/forseti-node`
- `POST /forseti-cluster/handshake`
- `GET /forseti-cluster/status`
- `POST /forseti-cluster/messages`
- `POST /forseti-cluster/service-request`
- `POST /forseti-cluster/service-request/{id}/decision`

### Local/admin endpoints

- `GET /admin/peers`
- `POST /admin/peers/{id}/review`
- `POST /admin/peers/{id}/suspend`
- `GET /admin/capabilities`
- `POST /admin/capabilities`
- `GET /admin/requests`
- `POST /admin/requests/{id}/approve`
- `GET /admin/audit/messages`

These admin endpoints can later be hidden behind Drupal or another admin surface, but the standalone service should own the API contract.

## Deployment model

### Single-server MVP

- Nginx/Caddy terminates TLS
- forwards `/forseti-cluster/*` and `/.well-known/forseti-node` to `forseti-meshd`
- service runs as its own Unix user
- keys stored in a private directory readable only by that user
- PostgreSQL stores daemon state

### Example process layout

- `drupal-php-fpm`
- `nginx`
- `postgresql`
- `forseti-meshd`
- `copilot-hq` processes / scripts

## Integration strategy

### Drupal integration

For MVP, Drupal should be the primary interface and should call the mesh daemon rather than embedding the protocol logic directly.

Recommended interactions:

- Drupal admin pages render peer registry, trust review, capability management, and request queues
- Drupal admin form creates or updates local capability definitions
- Drupal workflow action submits service requests to mesh daemon
- Drupal admin screens consume mesh status and audit data via internal API

### Copilot HQ integration

For MVP, Copilot HQ should interact by:

- publishing institutional capabilities
- creating institutional-service requests
- reading request outcomes

That integration should happen through:

- CLI commands
- local HTTP calls
- signed internal service credentials if needed

## Failure-handling model

- inbound validation failures return explicit 4xx responses
- transient outbound failures are retried by worker
- policy failures are stored as audit events, not hidden
- operator-facing dashboards show stuck jobs, rejected requests, and stale peers

## Non-goals for the MVP

- Kubernetes
- broker-based event bus
- shared auth/database with Drupal
- websocket-driven real-time topology
