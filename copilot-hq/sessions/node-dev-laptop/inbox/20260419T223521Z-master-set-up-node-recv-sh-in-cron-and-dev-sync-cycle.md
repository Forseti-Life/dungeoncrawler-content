---
id: 20260419T223521Z-master-set-up-node-recv-sh-in-cron-and-dev-sync-cycle
from: master
to: dev-laptop
type: command
subject: set up node-recv.sh in cron and dev-sync cycle
sent_at: 2026-04-19T22:35:21Z
status: pending
---

## Setup: Inter-Node Message Receiving

This node (dev-laptop) now has a git-based inbox at:
  sessions/node-dev-laptop/inbox/

The master node will drop commands here. You need to check and process
them automatically on each sync cycle.

### Step 1 — Verify node-identity.conf

Make sure your local node-identity.conf says:
  NODE_ID=dev-laptop
  NODE_ROLE=worker
  NODE_PROJECTS="forseti-jobhunter-automation"
  NODE_DEFAULT_AGENT=dev-jobhunter

If this file doesn't exist, copy from the template:
  cp copilot-hq/node-identity.conf.example copilot-hq/node-identity.conf
  # then edit the values above

### Step 2 — Add node-recv.sh to dev-sync-once.sh

In copilot-hq/scripts/dev-sync-once.sh, add near the top (after git pull):

  # Process any inter-node messages from master
  bash "$ROOT_DIR/scripts/node-recv.sh"

### Step 3 — Test it

  cd copilot-hq
  bash scripts/node-recv.sh --dry-run   # preview
  bash scripts/node-recv.sh             # process for real

Processed messages get archived to sessions/node-dev-laptop/outbox/
and routed into sessions/dev-jobhunter/inbox/ for agent pickup.

### Step 4 — Sending replies back to master

  bash scripts/node-send.sh master reply "subject" "body"

This commits and pushes immediately. Master's node-recv.sh routes
replies to the ceo-copilot-2 outbox for review.

### Reference

Scripts: copilot-hq/scripts/node-send.sh | node-recv.sh
Docs: copilot-hq/sessions/node-dev-laptop/README.md
Sent from master node by ceo-copilot-2 on 2026-04-19.
