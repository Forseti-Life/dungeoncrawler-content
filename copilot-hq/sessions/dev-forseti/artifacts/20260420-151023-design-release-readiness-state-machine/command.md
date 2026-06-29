# Design: Release readiness state machine

## Problem
Release readiness is implicit (check artifact existence). No unified state view. Dispatch logic is scattered across multiple functions, making it hard to reason about state transitions.

## Solution
Implement a release readiness state machine:
- File: tmp/release-cycle-active/<team>.release-state
- Possible states: created, scoped, dev-executing, qa-verifying, all-gates-clean, pm-signing-required, pm-signed, push-required, pushed, post-release-qa, closed
- State transitions:
  - created → scoped (when scope file exists)
  - scoped → dev-executing (when release-cycle-start.sh succeeds)
  - dev-executing → qa-verifying (when QA items created)
  - qa-verifying → all-gates-clean (when all QA gates pass)
  - all-gates-clean → pm-signing-required (signoff reminder dispatched)
  - pm-signing-required → pm-signed (signoff artifact created)
  - pm-signed → push-required (all coordinated teams signed)
  - push-required → pushed (coordinated_push fires)
  - pushed → post-release-qa (post-push item created)
  - post-release-qa → closed (post-release QA clean)

## Files
- orchestrator/run.py (add state transition functions)
- tmp/release-cycle-active/<team>.release-state (new)
- tmp/release-cycle-state-history/<team>/<release-id>.log (new, optional)

## Acceptance Criteria
- State file is created and updated at each transition
- State is logged for audit trail
- orchestrator/run.py functions consult and update state
- Manual verification: run release cycle, observe state transitions logged

## Risk
High: refactoring, many functions affected. Recommend implementing incrementally (one transition at a time).
- Agent: dev-forseti
- Status: pending
