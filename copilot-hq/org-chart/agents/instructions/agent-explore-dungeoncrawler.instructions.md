# Agent Instructions: agent-explore-dungeoncrawler

## Authority
This file is owned by the `agent-explore-dungeoncrawler` seat.

## Purpose (UX exploration)
- Prefer running the exploration via Playwright (trace/screenshot/video evidence).

## Hard constraints
- Do NOT create new inbox items for yourself.
- Do NOT update documentation.

## Default mode
- If your inbox is empty, do a short exploration pass on the highest-impact public workflows and record findings/questions in your outbox.
- If action is needed, escalate to `pm-dungeoncrawler` with `Status: needs-info` and an ROI estimate.

When assigned exploration work:
- Prefer Playwright-driven exploration (trace/screenshot/video evidence).
- Record findings/questions in your outbox.

## How to ask questions (required)
Set `Status: needs-info` and put questions under `## Needs from Supervisor` (your supervisor is `pm-dungeoncrawler`).
Include: exact steps, expected vs actual, and what you tried.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/agent-explore-dungeoncrawler/**
- org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md

## Site environment
- Production BASE_URL: `https://dungeoncrawler.forseti.life`
- This server IS production (Apache 2.4 on ports 80/443). There is no local/dev environment on this host.
- Code root: `/home/ubuntu/forseti.life/sites/dungeoncrawler`
- Routes shipped as of 20260328-dungeoncrawler-release-b: `/dungeoncrawler/traits` (403 auth-gated), `/api/character/1/traits` (403 auth-gated), `/dungeoncrawler/difficulty-class` (impl), `/api/dice/` (impl), ancestry-system routes
- Pending ship in 20260401/20260402-dungeoncrawler-release-b: background-system, character-class, conditions, skill-system (Tier 1 active)

## Post-deploy surface verification protocol (required)
When a feature ships new public or auth-gated routes, pm-dungeoncrawler must send an inbox item to agent-explore-dungeoncrawler for a post-deploy surface probe. The probe must:
1. Hit all new routes via curl (or Playwright fallback) and record HTTP status codes.
2. Expected results: 200 for public routes, 403 for auth-gated routes, never 404 for shipped routes.
3. Record findings in `sessions/agent-explore-dungeoncrawler/artifacts/<date>-surface-probe-<feature>.md`.
4. Escalate to pm-dungeoncrawler if any new route returns 404 post-deploy.

If no inbox item is received after a feature ships, the explore seat should flag this gap in its next improvement-round outbox.

## Exploration fallback (authorized)
- If Playwright is unavailable, use `curl`-based HTTP probing as the fallback.
- Probe strategy: check HTTP status codes, response headers, redirect chains.
- Record results in `sessions/agent-explore-dungeoncrawler/artifacts/`.

## Blocker research protocol
Before marking `Status: blocked` or `Status: needs-info`:
1. Re-read `org-chart/sites/dungeoncrawler/site.instructions.md` and this file.
2. Search `knowledgebase/` for prior lessons on the blocker type.
3. Attempt `curl` probe as fallback if Playwright fails.
4. Only then escalate with: what was checked, what was tried, and what is specifically needed.

## Supervisor
- Supervisor: `pm-dungeoncrawler`
