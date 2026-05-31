# `forseti-platform` Repo Brief — 2026-04

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `planned`
- Mode: `planning/documentation only`

## Purpose

`forseti-platform` should be the **public overview repo** for the Forseti ecosystem.

It is not the control-plane repo and it is not the full Drupal runtime repo. Its job is to explain:

1. what the Forseti platform is,
2. how the public repos relate to one another,
3. where a new contributor or evaluator should start.

## Audience

- developers evaluating the platform architecture
- contributors deciding which repo to work in
- operators/self-hosters who need the high-level system map first
- readers landing from the `Forseti-Life` community home

## Repo type

**Docs-first repository** with diagrams, repo navigation, platform overview, and getting-started orientation.

This repo should not carry live operational history or broad runtime code copied from the monorepo.

## Content to include

1. platform overview README
2. architecture diagram and layer map
3. quickstart path for understanding the platform
4. repo catalog linking to first-wave public repos
5. explanation of the public/private repo split
6. contributor routing guidance:
   - where framework work belongs
   - where Drupal platform work belongs
   - where application/product work belongs

## Content to exclude

1. operational `sessions/**` content
2. release-cycle payloads and inbox runtime artifacts
3. live deploy configuration
4. copied product code that belongs in a repo-specific package
5. client-specific or server-specific content

## Primary source material

This repo will be assembled from curated documentation in `copilot-hq/`, especially:

- `README.md`
- `QUICKSTART.md`
- `runbooks/public-repo-positioning.md`
- `runbooks/private-public-dual-repo.md`
- `runbooks/technology-stack.md`
- `dashboards/open-source/repo-breakout-plan-2026-04.md`

## Recommended information architecture

1. `README.md`
   - what Forseti is
   - who it is for
   - first links into the repo family
2. `QUICKSTART.md`
   - shortest route to understanding the system
   - architecture-first, not deployment-first
3. `docs/architecture.md`
   - platform layers
   - control-plane vs runtime/application split
4. `docs/repositories.md`
   - repo family map
   - which repo to use for which type of work
5. `docs/open-source-boundaries.md`
   - what stays private
   - why curated extracts are required

## Relation to other first-wave repos

| Repo | Relationship to `forseti-platform` |
|---|---|
| `drupal-ai-conversation` | first concrete reusable code example linked from the overview repo |
| `copilot-agent-framework` | framework/control-plane repo described as the orchestration layer |
| `drupal-platform` | base Drupal runtime/platform repo described as the application host layer |
| `forseti-job-hunter` | flagship application repo linked as a product built on the platform |
| `dungeoncrawler` | separate product/community example linked as another platform consumer |

## Open questions to resolve before repo creation

1. whether diagrams live directly in this repo or are generated from source artifacts elsewhere
2. whether this repo should also own top-level community onboarding docs
3. whether release notes for the repo family roll up here or stay repo-local

## Creation gate for later execution

Do not create `forseti-platform` until:

1. the repo-family map is stable enough to link to named repos,
2. the public positioning language is approved,
3. the repo breakout sequence is approved,
4. the community home and repo naming are considered stable for first wave use.
