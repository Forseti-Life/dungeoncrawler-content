# Reference Pseudocode — Forseti Community Resource Mesh

This document gives implementation-grade pseudocode for the standalone MVP.

The UI caller for these flows is expected to be a Drupal module, while the pseudocode below describes backend daemon behavior.

## Startup

```text
function main():
    cfg = load_config()
    db = connect_database(cfg.database_url)
    keys = load_signing_keys(cfg.key_paths)
    api = start_http_server(cfg.bind_addr)
    spawn(delivery_worker_loop, db, keys, cfg)
    spawn(renewal_worker_loop, db, keys, cfg)
    api.run()
```

## Discovery

```text
function discover_peer(base_url):
    metadata = http_get(base_url + "/.well-known/forseti-node")
    validate_discovery_metadata(metadata)
    peer = upsert_installation(
        installation_id = metadata.installation_id,
        display_name = metadata.display_name,
        base_url = metadata.base_url,
        public_key_fingerprint = metadata.public_key_fingerprint,
        membership_state = "discovered"
    )
    return peer
```

## Outbound handshake

```text
function initiate_handshake(peer_id):
    peer = db.get_installation(peer_id)
    req_payload = {
        schema_version: CURRENT_SCHEMA,
        sender_installation_id: cfg.local_installation_id,
        sender_base_url: cfg.public_base_url,
        sender_public_key_fingerprint: local_public_key_fingerprint(),
        timestamp: now_utc(),
        nonce: new_nonce(),
        supported_message_types: SUPPORTED_TYPES,
        supported_capability_categories: SUPPORTED_CATEGORIES,
        mission_attestation: current_mission_attestation()
    }
    envelope = sign_message("peer.handshake.request", peer.installation_id, req_payload)
    response = http_post(peer.base_url + "/forseti-cluster/handshake", envelope)
    return process_handshake_response(peer, response.json)
```

## Inbound handshake

```text
function handle_handshake_request(envelope):
    basic_validate_envelope(envelope)
    payload = envelope.payload
    peer = upsert_discovered_peer(payload.sender_installation_id, payload.sender_base_url)
    verify_signature_and_nonce(peer, envelope)
    record_mission_attestation(peer, payload.mission_attestation)
    response_payload = {
        responder_installation_id: cfg.local_installation_id,
        responder_base_url: cfg.public_base_url,
        responder_public_key_fingerprint: local_public_key_fingerprint(),
        request_nonce: payload.nonce,
        response_nonce: new_nonce(),
        timestamp: now_utc(),
        endpoint_map: local_endpoint_map(),
        supported_message_types: SUPPORTED_TYPES,
        supported_capability_categories: SUPPORTED_CATEGORIES,
        mission_attestation: current_mission_attestation(),
        recommended_trust_constraints: default_trust_constraints()
    }
    return sign_message("peer.handshake.response", peer.installation_id, response_payload)
```

## Handshake response processing

```text
function process_handshake_response(peer, envelope):
    basic_validate_envelope(envelope)
    verify_signature_and_nonce(peer, envelope)
    assert envelope.payload.request_nonce == last_outbound_nonce_for(peer)
    store_peer_identity(peer, envelope.payload)
    store_peer_attestation(peer, envelope.payload.mission_attestation)
    set_membership_state(peer, "pending_review")
    write_audit("handshake_completed", peer.installation_id)
```

## Operator review

```text
function review_peer(peer_id, review_input):
    peer = db.get_installation(peer_id)
    assert peer.membership_state in ["pending_review", "suspended"]
    update_mission_alignment(peer, review_input.alignment_status)
    update_peer_policy(peer, review_input.allowed_categories, review_input.default_approval_mode)

    if review_input.alignment_status == "not_aligned":
        set_membership_state(peer, "rejected")
        set_trust_tier(peer, null)
    else if review_input.trust_tier == "operational_partner":
        set_membership_state(peer, "operational_partner")
        set_trust_tier(peer, "operational_partner")
    else:
        set_membership_state(peer, "verified")
        set_trust_tier(peer, "verified")

    write_audit("peer_reviewed", peer.installation_id)
```

## Drupal module call pattern

```text
function drupal_submit_peer_review(form_state):
    payload = map_form_to_review_payload(form_state)
    response = http_post_internal(meshd_url + "/admin/peers/" + form_state.peer_id + "/review", payload)
    if response.ok:
        drupal_set_message("Peer review saved.")
    else:
        drupal_set_error(response.error)
```

## Generic inbound message validation

```text
function basic_validate_envelope(envelope):
    require envelope.message_id
    require envelope.message_type
    require envelope.sender_installation_id
    require envelope.timestamp
    require envelope.nonce
    require envelope.signature
    require envelope.payload
```

```text
function verify_signature_and_nonce(peer, envelope):
    require peer exists
    require peer.membership_state not in ["suspended", "rejected"]
    require schema_supported(envelope.schema_version)
    require timestamp_is_fresh(envelope.timestamp, 5 minutes)
    require nonce_unused(peer.installation_id, envelope.nonce)
    require signature_valid(peer.public_key, canonical_json(envelope))
    mark_nonce_used(peer.installation_id, envelope.nonce)
```

## Capability advertisement

```text
function advertise_local_capabilities():
    for peer in eligible_peers_for_catalog_sync():
        caps = db.get_exportable_capabilities_for_peer(peer.installation_id)
        payload = {
            installation_id: cfg.local_installation_id,
            capabilities: caps,
            generated_at: now_utc()
        }
        queue_outbound_message(peer, "capability.advertise", payload)
```

## Service request routing

```text
function create_service_request(input):
    if can_handle_locally(input):
        return route_to_local_handler(input)

    candidates = find_remote_candidates(input.requested_capability)
    candidates = filter_by_membership_state(candidates, ["verified", "operational_partner"])
    candidates = filter_by_policy(candidates, input)

    if candidates is empty:
        return fail("no_eligible_peer")

    target = rank_candidates(candidates)[0]
    request = persist_service_request(input, target.installation_id)

    if approval_required(target, input):
        mark_request_status(request, "deferred")
        enqueue_operator_review(request)
        return request

    queue_outbound_message(target, "service.request", request_to_payload(request))
    mark_request_status(request, "requested")
    return request
```

## Inbound service request handling

```text
function handle_service_request(envelope):
    peer = db.get_installation(envelope.sender_installation_id)
    verify_signature_and_nonce(peer, envelope)
    require peer.membership_state in ["verified", "operational_partner"]

    request = persist_inbound_service_request(envelope.payload)

    if policy_blocks_request(peer, request):
        mark_request_status(request, "rejected")
        return respond_with_decision(request, "rejected", "policy_blocked")

    if approval_required(peer, request):
        mark_request_status(request, "deferred")
        enqueue_operator_review(request)
        return respond_with_decision(request, "deferred", "awaiting_operator_review")

    mark_request_status(request, "accepted")
    enqueue_local_handler(request)
    return respond_with_decision(request, "accepted", "accepted_for_processing")
```

## Delivery worker

```text
function delivery_worker_loop():
    while true:
        jobs = db.get_due_outbound_jobs(limit = 50)
        for job in jobs:
            try:
                msg = db.get_message(job.message_id)
                response = http_post(job.endpoint_url, msg.envelope_json)
                record_delivery_result(job, msg, response.status_code)
                if response.status_code in [200, 202]:
                    mark_job_done(job)
                else if is_retryable(response.status_code):
                    reschedule_job(job)
                else:
                    mark_job_failed(job)
            except Exception as err:
                reschedule_or_fail(job, err)
        sleep(WORKER_POLL_INTERVAL)
```

## Renewal worker

```text
function renewal_worker_loop():
    while true:
        peers = db.get_peers_requiring_renewal()
        for peer in peers:
            if mission_attestation_expired(peer) or peer.status_stale():
                try:
                    initiate_handshake(peer.installation_id)
                except Exception as err:
                    mark_peer_degraded(peer, err)
                    if peer_is_past_failure_threshold(peer):
                        suspend_peer(peer, "renewal_failure_threshold")
        sleep(RENEWAL_INTERVAL)
```
