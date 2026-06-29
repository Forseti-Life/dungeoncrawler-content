# Implementation Notes — Production Master to Dev Worker Sync

**Feature:** `forseti-dev-master-worker-sync`  
**Module:** `copilot-hq`

## Purpose

Use the existing HQ inbox/executor model to let the production master assign work to the local development worker node while keeping production authoritative for prioritization and release decisions.

## Primary references

- `runbooks/production-master-dev-worker.md`
- `scripts/dev-dispatch-task.sh`
- `scripts/dev-sync-once.sh`
- `scripts/local-master-worker-smoke.sh`
- `scripts/install-systemd-dev-sync.sh`
- `scripts/systemd/copilot-sessions-hq-dev-sync.service`

## Core implementation pieces

### 1. Command creation

- Production writes markdown command envelopes into `inbox/commands/`.
- Required list fields are parsed from the envelope.
- Commands targeting `dev-laptop` are for the worker flow only.

### 2. Orchestrator behavior

- HQ orchestrator must ignore worker-targeted commands.
- Worker-targeted commands are claimed by the worker sync path instead.

### 3. Worker claim and dispatch

- `dev-sync-once.sh` should:
  1. pull/rebase safely,
  2. scan for `target: dev-laptop`,
  3. claim one eligible command,
  4. convert it into a standard agent inbox item,
  5. move the source command into `inbox/processed/`,
  6. optionally execute the assigned seat.

### 4. Worker loop / service

- `dev-sync-loop.sh` provides polling behavior.
- `install-systemd-dev-sync.sh` installs the user service.
- Service should be the durable always-on path after smoke validation.

### 5. Routing

- CEO should dispatch by project id/alias using the project routing registry.
- JobHunter is the initial/default use case.
- Production chooses the work; the worker only executes what is assigned.

### 6. Git protocol

- Pull policy:
  - ensure safe workspace state
  - pull/rebase before claiming
- Branch policy:
  - use supplied branch if present
  - otherwise create local branch from command topic/module
- Push policy:
  - disabled by default until loop is trusted

## Implementation constraints

- Do not let worker nodes independently create or reprioritize work.
- Do not let worker-targeted commands be double-consumed by orchestrator and worker.
- Keep processed command history for auditability.
- Preserve compatibility with `agent-exec-next.sh`.

## Validation focus

1. Worker-targeted commands are ignored by orchestrator
2. Worker can claim and convert a command into standard inbox structure
3. Smoke path works end-to-end for a JobHunter-targeted task
4. Service path can keep the loop alive without manual babysitting

## Risks to keep visible

- pull/rebase conflicts on dirty worker tree
- accidental auto-push before trust is established
- routing drift between roadmap registry and command emitters
- duplicate command claim if ledger/locking is wrong
