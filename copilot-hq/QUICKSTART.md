# Quickstart

This repository is the **operations and orchestration layer** behind the Forseti autonomous development platform.

Use this guide if you want to understand the project quickly, stand up a local operator environment, or inspect the main runtime scripts without reading the full repo first.

## What this repo is

`copilot-hq` is the control plane for:

- role-based agent coordination
- orchestrated work intake and execution
- release-cycle and QA guardrails
- dashboards, runbooks, and governance artifacts

It is **not** the full product runtime for every Forseti service. Product/application code lives elsewhere in the monorepo.

## Start here

1. Read `README.md`
2. Read `runbooks/public-repo-positioning.md`
3. Read `runbooks/private-public-dual-repo.md` if you are evaluating the public/private split
4. Read `runbooks/orchestration.md` for the end-to-end runtime flow

## Repo map

- `org-chart/` — roles, seats, ownership, instruction layers
- `runbooks/` — repeatable operational procedures
- `dashboards/` — project, release, and integration state
- `orchestrator/` — Python LangGraph runtime
- `scripts/` — startup, converge, publish, audit, and helper scripts
- `llm/` — local LLM routing, model manifests, and download tooling

## Full tech stack

### Core runtime

- Python orchestrator with LangGraph
- GitHub Copilot CLI execution paths
- AWS Bedrock fallback paths
- shell automation and cron/systemd style loop control

### Web/application side

- Drupal 10/11 multisite architecture
- custom Drupal modules for product workflows
- AWS-backed AI integrations through the platform stack

### Local model layer

- optional GGUF model downloads via Hugging Face Hub
- local routing config under `llm/`

For the fuller stack map, read:

- `runbooks/technology-stack.md`

## Main startup and runtime scripts

These are the most important scripts for understanding how the system starts and stays healthy:

### Control / converge

- `scripts/org-control.sh` — enable/disable org automation
- `scripts/is-org-enabled.sh` — runtime gate check
- `scripts/release-cycle-control.sh` — enable/disable release-cycle automation
- `scripts/is-release-cycle-enabled.sh` — release-cycle gate check
- `scripts/hq-automation.sh converge` — converge loops into desired state
- `scripts/hq-automation-watchdog.sh` — periodic enforcement and intake

### Runtime loops

- `scripts/orchestrator-loop.sh` — primary orchestration loop
- `scripts/auto-checkpoint-loop.sh` — checkpoint cadence
- `scripts/site-audit-loop.sh` — optional audit loop

### Setup / verification

- `scripts/setup.sh` — initial HQ setup
- `scripts/verify-hq-runtime.sh --strict` — deployment/runtime verification
- `scripts/hq-status.sh` — current runtime snapshot

## Safe evaluation path

If you are trying to understand the repo without touching production behavior:

1. Read docs first
2. Use status/verification scripts before mutate/start scripts
3. Avoid treating private operational paths as public-safe examples

## Public/open-source note

This repo is being prepared for public-friendly use via **curated mirrors / extracted repos**, not by exposing live operational history directly.

Start with:

- `PUBLIC_REPO_PREP.md`
- `runbooks/publication-readiness-20260308.md`
- `runbooks/public-release-gate-20260308.md`

## Next docs to read

- `runbooks/orchestration.md`
- `runbooks/session-lifecycle.md`
- `runbooks/release-cycle-process-flow.md`
- `runbooks/technology-stack.md`
