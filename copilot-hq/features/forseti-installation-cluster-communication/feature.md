# Feature: forseti-installation-cluster-communication

- Group Order: 1
- Group: cluster-comm
- Group Title: Cluster Communication
- Group Sort: 1
- Status: done
- Website: forseti.life
- Module: forseti_cluster (new)
- Release: 20260412-forseti-release-l
- Owner: pm-forseti
- Project: PROJ-011

## Summary

Forseti installations currently operate as isolated deployments. This feature introduces secure installation-to-installation cluster communication so one Forseti instance can discover, trust, and exchange signed messages with another Forseti instance. The goal is to establish the communication and governance layer first, so future federation features can build on a stable cross-installation contract instead of ad hoc integrations.

The larger vision is a **community resource mesh**: Forseti systems should be able to identify community needs and share commodity resources while preserving local autonomy. The initial focus is **agent expertise** and **institutional-management services**; compute and storage are future-state extensions. Trust in that mesh is not only technical. Peer installations should affirm adherence to the core Forseti mission and operating values as part of onboarding.

## Goal

Allow any Forseti installation to register trusted peer installations and exchange authenticated cluster messages through a standard message envelope and admin-managed trust model, with initial support for sharing agent capacity and institutional-service coordination.

## Acceptance criteria

- AC-1: Each installation has a stable installation identity and cluster key material that is not stored in public synced config.
- AC-2: Admins can register a peer installation with base URL, installation identifier, and trust material.
- AC-3: A signed health/status endpoint allows one trusted installation to verify reachability and basic peer metadata of another installation.
- AC-4: A signed cluster message endpoint accepts authenticated inbound messages, rejects unknown peers, and logs message receipt/result.
- AC-5: Outbound cluster messages use a standard message envelope with message type, sender installation id, timestamp, nonce, signature, and payload.
- AC-6: Admin UI shows configured peers, last successful handshake, and last message status.
- AC-7: Replay protection and signature validation are enforced so stale or tampered messages are rejected.
- AC-8: Trusted peers can publish a machine-readable catalog of offered agent and institutional-service capabilities.
- AC-9: Trusted peers can submit structured service requests and receive explicit accept, reject, defer, and completion status updates.
- AC-10: Export policy controls determine which service categories may leave the local installation and whether human approval is required.
- AC-11: Peer onboarding captures a signed mission-and-values attestation, and operators can review whether a peer remains aligned before elevating trust.

## Definition of done

- Core AC pass in a local two-installation test setup.
- Secrets and trust material are stored in active config, environment, or private files rather than public synced config.
- One end-to-end peer handshake and one signed message exchange are demonstrated successfully.
- One end-to-end capability advertisement and one service-request workflow are demonstrated successfully.

## Notes

- Start with communication and trust primitives, not full data replication.
- Favor Drupal-native HTTP client, route/controller patterns, and queue/logging primitives.
- The first practical use case can be health/status exchange, remote announcement, cross-installation suggestion relay, shared agent capacity, or institutional-service advertisement.
- The MVP should treat operator approval and export policy as first-class controls rather than assuming all trusted peers may request all services.
- Handshake trust should verify both technical legitimacy and declared adherence to the Forseti mission, so the mesh remains values-aligned as it grows.
- Documentation structure for long-term growth is defined in `00-documentation-framework.md`.
- The first dedicated MVP packet is split across `20-handshake-and-trust.md`, `30-policy-and-governance.md`, `40-service-catalog.md`, and `50-message-protocol.md`.
- Hard technical requirements should follow an open-source adopt-before-build strategy; custom code should focus on Forseti-specific governance and mesh behavior.
- Delivery sequencing for Forseti is defined in `90-roadmap.md`.

## Security acceptance criteria

- Authentication/permission surface: All peer handshake and inter-installation API endpoints require authenticated operator-level credentials (API keys or signed tokens); no anonymous access to inter-cluster routes. Peer trust elevation requires explicit admin approval action.
- CSRF expectations: Admin UI actions (peer approval, trust elevation, policy changes) use Drupal Form API or confirm_form() with CSRF token; REST/JSON API endpoints use token-based auth (not cookie-based), so CSRF is not applicable for those endpoints.
- Input validation requirements: All incoming peer messages validated against a strict schema before processing; untrusted peer-supplied data is sanitized before storage or display; capability advertisements are allowlisted, not open-ended.
- PII/logging constraints: Peer identity and handshake metadata stored in private config or environment variables, not public synced config; watchdog logs record handshake events but not raw key material; no user PII transmitted between installations unless explicitly operator-approved.
