# Board Action Required: Create Forseti-Life GitHub Org

**From:** architect-copilot
**To:** Board (Keith)
**Date:** 2026-04-10
**Priority:** High — blocks all public repo publishing
**Effort:** 2 minutes

---

## Action

Create the `Forseti-Life` GitHub organization.

**Steps:**
1. Go to: https://github.com/organizations/new
2. Organization name: `Forseti-Life`
3. Contact email: your email
4. Plan: **Free**
5. Click "Create organization"
6. Under Settings → Members, add `keithaumiller` as **Owner**

**Verify it worked:**
```bash
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api /orgs/Forseti-Life | python3 -c "import sys,json; d=json.load(sys.stdin); print('✅ Org exists:', d['login'])"
```

---

## Why This Can't Be Automated

GitHub.com org creation is not available via REST API — only GitHub Enterprise Server supports it. This is the only manual step in the entire open source publication pipeline. Everything else (repo extraction, history scrubbing, CI setup) is fully automated.

---

## What's Unblocked Once Org Exists

All Phase 1 security audit work (BFG scan, secrets removal) can proceed **now** — doesn't require the org. The org is only needed for the final `gh repo create Forseti-Life/<name>` push step.

---

## Context
- Project: Open Source Initiative (`forseti-open-source-initiative`)
- PM: `pm-open-source`
- Full plan: `features/forseti-open-source-initiative/feature.md`
