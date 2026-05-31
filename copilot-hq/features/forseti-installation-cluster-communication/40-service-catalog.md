# Service Catalog — Forseti Community Resource Mesh

This document defines the initial multi-node service surface for the MVP.

## MVP service objective

Support a small, clear set of service types so two Forseti installations can prove the mesh works end to end without introducing compute/storage pooling yet.

## Requirement set

- `CRM-SVC-001` — Services must be grouped into explicit capability categories.
- `CRM-SVC-002` — The MVP must support agent-expertise sharing.
- `CRM-SVC-003` — The MVP must support institutional-management services.
- `CRM-SVC-004` — Service requests must target a declared capability or capability category.
- `CRM-SVC-005` — Service availability must be publishable and updateable.
- `CRM-SVC-006` — Service requests and responses must be auditable by request id.

## Capability categories

### `agent_expertise`

Use for bounded support from specialized Forseti agent workflows.

MVP capabilities:

- `agent.research_support`
  - help gather and summarize structured findings
- `agent.backlog_shaping`
  - help refine work items, acceptance criteria, and scope
- `agent.analysis_review`
  - help review architecture, docs, or implementation concerns

### `institutional_service`

Use for operational and governance coordination between installations.

MVP capabilities:

- `institution.policy_review`
  - request review of policy/governance language
- `institution.release_coordination`
  - request structured release-readiness or rollout coordination help
- `institution.documentation_governance`
  - request documentation structure, traceability, or governance support
- `institution.operational_triage`
  - request help triaging blockers, escalations, or operational workflow issues

## Capability record shape

Each advertised capability should include:

- `capability_id`
- `installation_id`
- `category`
- `name`
- `description`
- `availability_status`
  - `available`
  - `limited`
  - `unavailable`
- `export_policy`
- `approval_mode`
- `expected_response_window`
- `constraints`

## Need record shape

Each published need should include:

- `need_id`
- `installation_id`
- `category`
- `requested_capability`
- `priority`
- `requested_until`
- `policy_constraints`
- `context_summary`

## Service-request shape

Each request should include:

- `request_id`
- `requesting_installation_id`
- `target_installation_id`
- `requested_capability`
- `requested_outcome`
- `priority`
- `request_context`
- `data_sensitivity`
- `approval_requirement`
- `status`

Statuses:

- `requested`
- `accepted`
- `deferred`
- `rejected`
- `in_progress`
- `completed`
- `cancelled`

## What the MVP must demonstrate

Two installations should be able to demonstrate all of the following:

1. Installation A advertises at least one `agent_expertise` capability.
2. Installation B advertises at least one `institutional_service` capability.
3. Installation A requests a capability from Installation B and receives a structured decision.
4. Installation B requests a capability from Installation A and receives a structured decision.
5. Both installations can update request status to completion or rejection with audit history preserved.

## Suggested first demo flows

### Demo flow 1 — agent expertise

- Node A advertises `agent.research_support`
- Node B submits a request for research help on a local initiative
- Node A accepts, returns status updates, and marks the request completed

### Demo flow 2 — institutional service

- Node B advertises `institution.policy_review`
- Node A submits a request to review a governance or release policy change
- Node B accepts or defers with reasoning

## Non-goals for the MVP

- compute scheduling
- storage replication
- background job outsourcing
- arbitrary remote execution
