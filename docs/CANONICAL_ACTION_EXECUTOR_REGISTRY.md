# Canonical Action Authoritative Executor Registry

## Purpose

This registry defines how DM mechanical actions are:

1. stored canonically
2. validated authoritatively
3. executed by server-owned services
4. tracked for usage in campaign logs

## Source of Truth

The canonical registry is stored in the PHP service:

- [src/Service/CanonicalActionRegistryService.php](../src/Service/CanonicalActionRegistryService.php)

The registry uses a single in-code definition map keyed by canonical action type.

Each action definition stores:

- `label`
- `validator`
- `executor`
- `scope`
- `status`
- optional `notes`

## Current Tracking Model

Usage is tracked in `dc_campaign_log` with:

- `log_type = canonical_action`
- `message = canonical_action:{action_type}:{status}`
- `context` JSON containing:
  - action definition snapshot
  - status
  - room id / character id when available
  - action name
  - action details
  - timestamp
  - user id

## Lifecycle Statuses

Canonical actions are currently tracked through these statuses:

- `proposed`
- `proposed_retry`
- `validated`
- `executed`
- `rejected`

## Prompt Integration

The GM system prompt is augmented from the registry so the model is told which canonical actions have authoritative executors.

Current prompt guidance is built by:

- [src/Service/GameplayActionProcessor.php](../src/Service/GameplayActionProcessor.php)
- [src/Service/CanonicalActionRegistryService.php](../src/Service/CanonicalActionRegistryService.php)

## Inventory Transfer Rule

`inventory_add` and `inventory_remove` are legacy delta mechanics.

They should only be used for single-owner state adjustments.

For real custody changes between campaign storage owners, use:

- `transfer_inventory`

Authoritative path:

- validator: `InventoryManagementService::validateTransferTransaction`
- executor: `InventoryManagementService::transferItemTransaction`

## Room Chat Tracking Flow

Room chat currently tracks canonical actions in:

- [src/Service/RoomChatService.php](../src/Service/RoomChatService.php)

Flow:

1. GM proposes actions
2. actions are logged as `proposed`
3. retry actions are logged as `proposed_retry`
4. accepted actions are logged as `validated`
5. applied actions are logged as `executed`
6. validation failures are logged as `rejected`

## Design Rule

When a new DM mechanic is introduced, it should not be added only as prompt prose.

It should be added as a canonical registry entry with:

- canonical action name
- validator
- executor
- usage tracking
- prompt guidance
