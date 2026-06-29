# Test Plan — Production Master to Dev Worker Sync

**Feature:** `forseti-dev-master-worker-sync`

## Objective

Prove that production can assign work to the dev worker node through the git-backed HQ command flow without the worker inventing its own backlog and without the HQ orchestrator consuming worker-targeted commands.

## Test stages

### Stage 1 — Command envelope validation

1. Create a command with `target: dev-laptop`
2. Include required routing fields
3. Confirm the file lands in `inbox/commands/`

Expected:

- Envelope is readable and routable

### Stage 2 — Orchestrator ignore behavior

1. Place a worker-targeted command in `inbox/commands/`
2. Run/observe HQ orchestrator processing

Expected:

- Worker-targeted command is not consumed by HQ orchestrator

### Stage 3 — Worker claim path

1. Run `scripts/dev-sync-once.sh`
2. Confirm one matching command is claimed
3. Confirm a standard inbox item is created under the target seat
4. Confirm original command moves into `inbox/processed/`

Expected:

- Exactly one inbox packet is created
- Command is not left in queued state after successful claim

### Stage 4 — Execution compatibility

1. Run `scripts/agent-exec-next.sh <target-seat>` for the claimed item
2. Confirm normal seat execution path works
3. Confirm outbox entry is produced

Expected:

- Worker-targeted flow is compatible with standard agent execution

### Stage 5 — Smoke script

1. Run `scripts/local-master-worker-smoke.sh`
2. Capture output and resulting inbox/processed state

Expected:

- Smoke path succeeds end-to-end for the documented initial target case

### Stage 6 — Service durability

1. Install/start `copilot-sessions-hq-dev-sync.service`
2. Confirm loop stays running
3. Drop a new worker-targeted command and confirm it is claimed on schedule

Expected:

- Service keeps the sync loop alive without manual restarts

## PASS criteria

- Worker-targeted command ignored by orchestrator
- Worker claim path succeeds
- Inbox packet shape is correct
- Processed ledger movement occurs
- Seat execution path works
- Service path is installable and durable

## BLOCK criteria

- Orchestrator consumes worker-targeted commands
- Worker cannot claim or dispatch commands
- Duplicate claims occur
- Inbox packet is incompatible with seat executor
- Service path fails to keep the loop running
