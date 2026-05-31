# File Ownership Map (forseti.life repo)

## Purpose
Ensure every path in `/home/ubuntu/forseti.life` is covered by an owning role/seat.

This map is for the **target product repo**. HQ ownership is defined separately in `org-chart/ownership/file-ownership.md`.

## Core rules
- **Edit vs recommend**: any role may recommend improvements anywhere, but actual edits should be done by the owning seat/team.
- **Website scope**: this repo contains multiple workstreams (Drupal site, docs, scripts, mobile assets). Default owner is the Forseti CEO seat unless specified.

## Default ownership
- Everything not matched by a more specific rule is owned by the infrastructure team (`pm-infra` / `dev-infra` / `qa-infra` / `ba-infra`).

## Infrastructure scope rule (outside website directories)
- Any path outside the website directories (e.g., anything not under `/sites/**`) is infrastructure scope and owned by the infrastructure team.
- Website directories (e.g., `/sites/forseti/**`) remain owned by their product teams per module ownership.

## Product code ownership (Drupal)

### Custom modules (primary product surface)
- `/sites/forseti/web/modules/custom/job_hunter/**`
  - PM: `pm-forseti`
  - Dev implementation: `dev-forseti`
  - QA verification: `qa-forseti`
  - BA requirements support: `ba-forseti`
  - Security review: `sec-analyst-forseti`

- `/sites/forseti/web/modules/custom/copilot_agent_tracker/**`
  - PM: `pm-forseti-agent-tracker`
  - Dev implementation: `dev-forseti-agent-tracker`
  - QA verification: `qa-forseti-agent-tracker`
  - BA requirements support: `ba-forseti-agent-tracker`
  - Security review: `sec-analyst-forseti-agent-tracker`

- `/sites/forseti/web/modules/custom/ai_conversation/**`
  - PM: `pm-forseti`
  - Dev implementation: `dev-forseti`
  - QA verification: `qa-forseti`
  - BA requirements support: `ba-forseti`
  - Security review: `sec-analyst-forseti`

### Other custom modules
- Any other module under `/sites/forseti/web/modules/custom/<module>/**` not explicitly assigned above:
  - Default PM: `pm-forseti` (forseti.life default PM)
  - Dev: `dev-forseti`
  - QA: `qa-forseti`
  - BA: `ba-forseti`

### Contrib/vendor/core
- `/sites/forseti/web/modules/contrib/**` — owner: `ceo-copilot` (dependency management; changes only when necessary)
- `/sites/forseti/vendor/**` and `/sites/forseti/web/core/**` — owner: `ceo-copilot`

## Docs/scripts (repo-level)
- `/docs/**`, `/scripts/**`, `/script/**`, `/testing/**`, `/*.md` — owner: infrastructure team by default
- Security reviews and recommendations — lead: `sec-analyst-forseti` (recommend-only; no direct edits unless explicitly delegated)

## Notes
- Module ownership (who the PM is) is defined in HQ in `org-chart/ownership/module-ownership.yaml`.
- This file exists to make “everything is covered” explicit even when modules/docs proliferate.
