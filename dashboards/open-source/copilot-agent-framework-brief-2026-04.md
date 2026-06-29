# `copilot-agent-framework` Repo Brief — 2026-04

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `planned`
- Mode: `planning/documentation only`

## Purpose

`copilot-agent-framework` should be the **public framework/control-plane repo** for the Forseti autonomous delivery system.

It is the extracted public-facing form of the current `copilot-hq/` control layer: orchestration, governance, runbooks, dashboards, and automation helpers.

## Audience

- AI platform builders
- operators adopting role-based agent orchestration
- engineering teams interested in reproducible AI-assisted delivery workflows
- contributors improving the orchestration/control-plane layer itself

## Canonical source root

- Source root: `/home/ubuntu/forseti.life/copilot-hq`

This should be treated as a **sanitized extract / curated mirror**, not a raw publication of the live operational repo.

## Core public value

The repo should expose:

1. the seat/role model,
2. orchestration flow and runtime loops,
3. governance and release guardrails,
4. reusable scripts and templates,
5. dashboards/runbooks that explain how the system works.

## Public-safe include map

Likely include:

- `README.md`
- `QUICKSTART.md`
- `runbooks/`
- `org-chart/` except any private-only internal payload references that require curation
- `orchestrator/`
- `scripts/` that are public-safe and parameterized
- `dashboards/` that are architectural/process oriented
- `templates/`
- selected `llm/` manifests/tooling that do not expose local/private artifacts

## Explicit exclude map

Must exclude by default:

1. `sessions/**`
2. `inbox/responses/**`
3. `tmp/**`
4. `.venv/**` and runtime-local caches
5. credential-bearing config or token files
6. any server-specific paths, runtime logs, or machine-local artifacts
7. any docs that accidentally expose private infrastructure details

Additional sensitive areas to review before creation:

1. deploy scripts with environment assumptions
2. audit scripts that hardcode production paths or private URLs
3. dashboards containing live operational state instead of reusable structure

## Packaging model

Recommended packaging model:

- curated mirror for public-safe docs/scripts/runbooks
- selective sanitization of setup/runtime references
- separate history scrub before public visibility if any sensitive history remains

## Required documentation before creation

1. concise public README
2. operator quickstart
3. architecture and orchestration overview
4. public/private boundary note
5. contributor guide for extending seats, scripts, and runbooks
6. setup instructions for local evaluation without production coupling

## Known planning constraints

1. current `copilot-hq` content mixes reusable framework material with live org operations
2. public export must preserve method while stripping operational payloads
3. this repo should describe the relationship to product/runtime repos rather than absorb their code
4. the final public name should prefer `copilot-agent-framework` over the older `copilot-sessions-hq` framing

## Recommended first-pass extraction rule

If a file explains the framework, it is a candidate for inclusion.

If a file records live operations, credentials, session payloads, runtime artifacts, or server-specific assumptions, it stays private unless rewritten as a sanitized example.

## Creation gate for later execution

Do not create `copilot-agent-framework` until:

1. the include/exclude boundary is reviewed path-by-path,
2. server-specific scripts have parameterization or public-safe framing,
3. history scrub scope is approved,
4. a minimal public README + quickstart + architecture set is ready.
