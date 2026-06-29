# Source Boundary Matrix — First-Wave Open-Source Repos (2026-04)

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `done`

## Purpose

This matrix maps each first-wave planned repo to its canonical source roots, known aliases, likely included surfaces, and the most important private/sanitization boundaries to honor during later extraction work.

## First-wave matrix

| Planned repo | Canonical source root(s) | Alias / related roots | Primary contents | Key exclusions / cautions |
|---|---|---|---|---|
| `forseti-platform` | curated docs assembled from `copilot-hq/` | community home/org landing content is related but separate | platform overview, architecture, repo navigation, quickstart docs | no raw operational payloads, no product code dumps, no private infra details |
| `drupal-ai-conversation` | `sites/forseti/web/modules/custom/ai_conversation` | `shared/modules/ai_conversation` is a symlink alias to the same live path | reusable Drupal AI conversation module | sanitize Forseti-specific prompts/routes/docs; exclude site-specific context and secret-bearing config |
| `copilot-agent-framework` | `copilot-hq/` | older public framing references `copilot-sessions-hq` | control-plane docs, runbooks, orchestrator, scripts, governance model | exclude `sessions/**`, `tmp/**`, `inbox/responses/**`, local envs, tokens, runtime-local state |
| `drupal-platform` | `sites/forseti/` | depends on shared-module references and multisite architecture | base Drupal runtime/platform stack | exclude `keys/`, `database-exports/`, `prod-config/`, client/site-private config, live credentials |
| `forseti-job-hunter` | `sites/forseti/web/modules/custom/job_hunter` | may also require related theme/assets from `sites/forseti/themes` or adjacent docs | flagship job-seeker workflow module/application package | audit product-specific dependencies, external integrations, and any private/staging data assumptions |
| `dungeoncrawler` | `sites/dungeoncrawler/` | includes site-local custom modules such as `dungeoncrawler_content` and `dungeoncrawler_tester` | PF2E assistant site/product stack | exclude any secrets, private config, and non-reusable site deployment details |

## Deferred candidate backlog

| Candidate | Current source root(s) | Defer reason |
|---|---|---|
| `amisafe` / safety family | `sites/forseti/web/modules/custom/amisafe`, `forseti_safety_content`, `safety_calculator` | privacy/data sensitivity review needed |
| `copilot-agent-tracker` | `sites/forseti/web/modules/custom/copilot_agent_tracker` | public product positioning not yet defined |
| `company-research` | `sites/forseti/web/modules/custom/company_research` | standalone boundary/value needs review |
| `community-incident-report` | `sites/forseti/web/modules/custom/community_incident_report` | moderation/privacy boundary review needed |
| `nfr` | `sites/forseti/web/modules/custom/nfr` | stakeholder review needed |

## Shared private-path rules

These stay private unless intentionally copied as sanitized examples:

1. `sessions/**`
2. `inbox/responses/**`
3. `tmp/**`
4. `prod-config/**`
5. `database-exports/**`
6. `sites/*/keys/**`
7. credential-bearing config exports
8. machine-local virtualenv/model caches

## Canonical-root rules

1. Prefer the real source path over symlink aliases in planning docs.
2. If a repo spans multiple roots, name one primary root and explicitly list secondary dependencies.
3. If a planned repo is really an extracted package rather than a direct directory export, say so explicitly.

## Dependency notes to carry into later extraction work

1. `drupal-ai-conversation` is the cleanest first code candidate, but its live tree still needs public-package sanitization.
2. `copilot-agent-framework` is documentation-rich but has the strongest live/private boundary requirements.
3. `drupal-platform`, `forseti-job-hunter`, and `dungeoncrawler` are larger-surface extracts and should follow only after the first candidate path is proven.
