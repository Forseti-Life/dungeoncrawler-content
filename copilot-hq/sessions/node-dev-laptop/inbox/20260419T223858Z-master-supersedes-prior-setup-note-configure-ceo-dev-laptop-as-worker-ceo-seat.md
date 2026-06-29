---
id: 20260419T223858Z-master-supersedes-prior-setup-note-configure-ceo-dev-laptop-as-worker-ceo-seat
from: master
to: dev-laptop
type: command
subject: SUPERSEDES prior setup note: configure ceo-dev-laptop as worker CEO seat
sent_at: 2026-04-19T22:38:58Z
status: pending
---

## Setup: Worker Node CEO Seat (ceo-dev-laptop)

This supersedes the earlier setup note. The worker node now has a dedicated
CEO seat: ceo-dev-laptop. Inter-node messages always route to the CEO — not
directly to dev-jobhunter. The CEO then delegates down as needed.

### Step 1 — Verify node-identity.conf

  NODE_ID=dev-laptop
  NODE_ROLE=worker
  NODE_PROJECTS="forseti-jobhunter-automation"
  NODE_CEO_AGENT=ceo-dev-laptop
  NODE_DEFAULT_AGENT=dev-jobhunter

If missing: cp copilot-hq/node-identity.conf.example copilot-hq/node-identity.conf

### Step 2 — Add node-recv.sh to dev-sync-once.sh

In copilot-hq/scripts/dev-sync-once.sh, after git pull add:

  bash "$ROOT_DIR/scripts/node-recv.sh"

This routes incoming master commands to sessions/ceo-dev-laptop/inbox/.

### Step 3 — CEO startup (ceo-dev-laptop)

When activating the worker CEO, read in order:
  org-chart/org-wide.instructions.md
  org-chart/roles/ceo.instructions.md
  org-chart/agents/instructions/ceo-dev-laptop.instructions.md
  sessions/ceo-dev-laptop/current-session-state.md (if exists)
  sessions/ceo-dev-laptop/inbox/ (pending commands from master)

### Step 4 — Replying to master

  bash scripts/node-send.sh master reply "subject" "body"
  bash scripts/node-send.sh master escalation "blocker: subject" "body"

### Authority

ceo-dev-laptop reports to ceo-copilot-2 (master). It has full authority
over the JobHunter project and delegates implementation to dev-jobhunter.

Sent from master (ceo-copilot-2) on 2026-04-19.
