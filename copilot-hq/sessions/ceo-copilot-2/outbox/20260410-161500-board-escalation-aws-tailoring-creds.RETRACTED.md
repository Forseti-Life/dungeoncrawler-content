# Board Escalation: Tailoring Queue AWS Credentials Expired

- **From:** ceo-copilot-2
- **To:** Board (Keith)
- **Priority:** P1 — user-facing feature broken when jobs queued
- **Status:** pending Board action
- **Date:** 2026-04-10

---

## Issue

The Drupal resume tailoring queue is failing with expired AWS credentials when jobs are submitted.

**Error (from `/var/log/drupal/tailoring_queue.log`):**
```
UnrecognizedClientException (client): The security token included in the request is invalid.
❌ Queue: Resume tailoring failed for admin → "Senior Director, Agentic AI & Automation"
   at Madrigal Pharmaceuticals (job 6): Failed to generate tailored resume from AI service.
```

288 failure entries are recorded in the log. The queue is currently empty (no users actively submitting jobs), so no live failures are occurring right now — but the next tailoring job submission will fail.

---

## Root Cause

The forseti.life site uses AWS Bedrock for AI resume tailoring. The AWS credentials configured on the server use an **STS session token** (temporary credentials) that has expired. Temporary STS tokens typically expire after 12h–36h.

**CEO cannot fix this** — requires AWS console access to either:
1. Rotate the IAM user access key (if using long-lived keys), or
2. Generate new STS session credentials and update the server config, or
3. Switch to IAM instance role (long-term fix — eliminates expiry problem)

---

## Recommended Action (for Keith)

**Option A — Quick fix (IAM user key rotation):**
1. Log in to AWS Console → IAM → Users → find the user with Bedrock access
2. Rotate the access key
3. Update on the server: find where AWS credentials are configured in Drupal settings
4. Restart the Drupal queue worker if running as a daemon
5. Test: submit a tailoring job via the forseti.life UI

**Option B — Long-term fix (EC2 instance role — recommended):**
Attach an IAM role to the EC2 instance with Bedrock permissions. Remove hardcoded credentials from the Drupal config. No more expiry issues.

---

## Where Are Credentials Configured?

Run this on the server to find the credential source:
```bash
grep -r "AWS_ACCESS\|aws_access\|bedrock\|Bedrock" \
  /home/ubuntu/forseti.life/sites/forseti/web/sites/default/ \
  /home/ubuntu/forseti.life/sites/forseti/.env* 2>/dev/null | grep -v ".php~"
```

---

## Current Status

- Queue: empty, no live failures right now
- Health check: showing PASS (log is active, queue processes 0 items)
- Users: any resume tailoring submission will fail until creds are rotated
