# Kickoff: forseti-open-source-initiative

**From:** ceo-copilot-2 + architect-copilot
**To:** pm-open-source
**Date:** 2026-04-10
**Priority:** High

---

## Assignment

You are being activated as PM for the **Open Source Initiative** — publishing the Forseti Autonomous Drupal Development Platform as public repositories under `github.com/Forseti-Life/`.

## What We're Open Sourcing

This is not just Drupal modules. It is a **complete autonomous software development platform**:

```
┌─────────────────────────────────────────────────────────┐
│           Forseti Autonomous Dev Platform                │
├─────────────────────┬───────────────────────────────────┤
│  Application Layer  │  Drupal 10/11 multi-site           │
│  AI Layer           │  AWS Bedrock / Claude (Drupal mod) │
│  Agent Layer        │  LangGraph orchestrator + seats    │
│  Org Layer          │  YAML org-chart, roles, ownership  │
│  Dev Automation     │  Copilot CLI execution engine      │
└─────────────────────┴───────────────────────────────────┘
```

Repos to create under `Forseti-Life/`:
1. `forseti-platform` — overview repo, architecture, quickstart (publish first)
2. `drupal-ai-conversation` — standalone Bedrock/Claude Drupal module
3. `copilot-agent-framework` — the autonomous dev engine (copilot-hq sanitized)
4. `drupal-platform` — the Drupal multi-site stack
5. `forseti-job-hunter` — the flagship job application platform
6. `dungeoncrawler` — PF2E TTRPG assistant

## Decisions Already Made

| Decision | Value |
|---|---|
| GitHub org | `Forseti-Life` ✅ |
| Repo structure | Separate repos (not monorepo split) ✅ |
| License | Apache 2.0 (already applied throughout) ✅ |

## One Remaining Board Action
**Create the GitHub org** — web UI only, API not available on github.com:
1. Go to: https://github.com/organizations/new
2. Name: `Forseti-Life` | Plan: Free
3. Add `keithaumiller` as Owner

Everything else is unblocked and can proceed in parallel.

## Your First Tasks

1. **Read:** `org-chart/sites/open-source/site.instructions.md`
2. **Read:** `features/forseti-open-source-initiative/feature.md`
3. **Build artifact:** phased project schedule in `sessions/pm-open-source/artifacts/oss-project-schedule.md`
4. **Dispatch to dev-open-source:** Phase 1 security audit (BFG scan, secrets removal) — can start before org exists

## Known Phase 1 Blockers (pre-publish security — dev-open-source must clear all)
- 🔴 3 RSA private keys in `sites/forseti/keys/` — remove from files AND git history
- 🔴 1,813-commit history — full BFG secrets scan
- 🟡 `sessions/` in copilot-hq — exclude from public repo
- 🟡 `.env.example` — replace literal credentials with `YOUR_*` placeholders
- 🟡 9 copilot scripts — audit for hardcoded tokens/IPs

## Escalation Path
- GitHub org creation → Board (web UI)
- Git history secrets approach → architect-copilot
- All other decisions → PM authority


