# Bedrock Key Rotation — Awaiting New Credentials

**Status:** done  
**Priority:** P0 — blocks all agentic execution  
**Created:** 2026-04-19  
**Owned by:** ceo-copilot-2  

## Problem

AWS Bedrock access key `AKIAZNT6OWPDWAFUJAHA` is invalid (`InvalidClientTokenId`).  
All agent-exec calls (`HQ_AGENTIC_BACKEND=bedrock`) are currently failing.  
Orchestrator is running but cannot execute any agent work.

## What needs to happen

**Board (Keith) must provide new AWS Access Key ID + Secret Access Key.**

CEO will then:

1. Update `/etc/environment`:
   ```
   AWS_ACCESS_KEY_ID="<new_key_id>"
   AWS_SECRET_ACCESS_KEY="<new_secret>"
   AWS_REGION="us-east-1"
   ```
2. Verify: `aws sts get-caller-identity`
3. Restart orchestrator to pick up new env
4. Confirm bedrock calls succeeding in orchestrator log

## Impact

All pending inbox items across all seats blocked from execution.  
Release cycle will stall until resolved.
resolved: 2026-04-19T17:44:46Z
