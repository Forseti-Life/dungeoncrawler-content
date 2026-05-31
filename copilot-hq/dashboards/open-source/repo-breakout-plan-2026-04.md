# Repo Breakout Plan — 2026-04

- Project: `PROJ-009`
- Owner: `architect-copilot`
- Status: `in_progress`
- Mode: `planning/documentation only`

## Objective

Define the repository breakout plan for the Forseti open-source initiative now that the community home and GitHub org exist. This phase sets direction, boundaries, sequencing, and documentation expectations for the repo family. It does **not** create repos, freeze candidates, or publish anything yet.

## Planning decisions for this slice

1. **Docs only:** create the planning and direction artifacts now; defer repo creation and extraction work.
2. **Separate repos, not a monorepo split:** each public repo gets a focused audience and boundary.
3. **First-wave scope:** plan the initial repo family we expect to stand up first, plus a backlog of later candidates.
4. **Curated extracts/mirrors only:** never flip the live operational monorepo public.

## Community home role

The community home now gives us an org-level landing point, but the public repo family still needs an explicit overview repo that explains how the parts fit together.

For planning purposes:

- **Community/org home:** landing presence for the `Forseti-Life` organization
- **Platform overview repo:** `forseti-platform`
- **Function of `forseti-platform`:** explain the architecture, show how the repos relate, and route contributors to the correct repo

## First-wave repos to directionally prepare now

| Repo | Role | Canonical source root(s) | Packaging model | Planning output needed now |
|---|---|---|---|---|
| `forseti-platform` | Public overview/home repo for the platform | Curated docs assembled from `copilot-hq/` architecture and runbook content | New docs-first repo | repo charter, sitemap, README/QUICKSTART outline, cross-repo map |
| `drupal-ai-conversation` | First public code candidate; reusable Drupal AI module | Canonical live root: `sites/forseti/web/modules/custom/ai_conversation` (`shared/modules/ai_conversation` is a symlink alias) | Curated sanitized extract | extraction boundary, sanitization checklist, public README/env-var spec |
| `copilot-agent-framework` | Open-source orchestration/control-plane repo | `copilot-hq/` sanitized extract | Curated mirror / sanitized extract | include/exclude map, public-safe control-plane narrative, setup outline |
| `drupal-platform` | Base Drupal multi-site platform repo | `sites/forseti/` plus required shared-module references, excluding private runtime data | Sanitized extract | stack boundary doc, shared-module policy, local-dev plan |
| `forseti-job-hunter` | Flagship Forseti application repo | `sites/forseti/web/modules/custom/job_hunter` plus required theme/assets | Sanitized extract | dependency map, coupling audit, public feature narrative |
| `dungeoncrawler` | Product repo for the PF2E assistant site | `sites/dungeoncrawler/` | Sanitized extract | product boundary doc, reusable vs site-specific inventory, public setup outline |

## Repo-candidate backlog to keep out of first wave for now

These exist in the monorepo and should be tracked as future breakout candidates, but not treated as first-wave repo-creation targets in this planning slice.

| Candidate | Current source root | Why deferred |
|---|---|---|
| `amisafe` / safety track | `sites/forseti/web/modules/custom/amisafe`, `forseti_safety_content`, `safety_calculator` | privacy/data review still required |
| `copilot-agent-tracker` | `sites/forseti/web/modules/custom/copilot_agent_tracker` | should follow a clearer public product story first |
| `company-research` | `sites/forseti/web/modules/custom/company_research` | needs dependency and standalone-value review |
| `community-incident-report` | `sites/forseti/web/modules/custom/community_incident_report` | moderation/privacy boundary review needed |
| `nfr` | `sites/forseti/web/modules/custom/nfr` | stakeholder review required before public packaging |
| Other custom Forseti modules | `sites/forseti/web/modules/custom/*` | evaluate only after first-wave repo family is stable |

## Common breakout rules

Every planned repo in this family should follow the same base rules:

1. **Private content stays out:** `sessions/**`, `inbox/responses/**`, `tmp/**`, `prod-config/**`, `database-exports/**`, key material, and credential-bearing config stay private unless deliberately copied as sanitized examples.
2. **Canonical roots over aliases:** when a module is symlinked into multiple places, the plan should point to the real source path.
3. **Docs before publish:** each repo needs a repo-specific README, setup guidance, contribution rules, and security guidance before creation.
4. **No implied independence:** if a repo still depends on private site context, that coupling must be documented in the plan before extraction work starts.
5. **Audience-driven scope:** each repo should answer a distinct external question rather than mirroring the private monorepo layout blindly.

## Documentation package to prepare for each repo

For each first-wave repo, the planning set should define:

1. repo purpose and intended audience
2. canonical source root(s)
3. included content
4. excluded/private content
5. known coupling or sanitization work
6. docs required at repo creation time
7. CI / validation expectations
8. publish gate prerequisites

## Recommended execution order once planning is approved

This is the later execution order, not the work for the current slice:

1. `forseti-platform` — finalize the public overview/home repo docs
2. `drupal-ai-conversation` — first real code extraction and candidate freeze
3. `copilot-agent-framework` — framework/control-plane extraction
4. `drupal-platform` — base Drupal stack extraction
5. `forseti-job-hunter` — flagship app extraction
6. `dungeoncrawler` — product-site extraction

## Gate model for later execution

No repo should move from planning into creation until these are true:

1. history/secret scrub plan is approved for the relevant source area
2. private-path exclusions are explicitly listed
3. repo-specific sanitization work is known
4. required repo docs are outlined
5. the repo has a clear owner and publish decision

## Companion planning artifacts produced in this slice

This planning pass produced:

1. a per-repo brief for `forseti-platform` — `dashboards/open-source/forseti-platform-brief-2026-04.md`
2. a per-repo brief for `copilot-agent-framework` — `dashboards/open-source/copilot-agent-framework-brief-2026-04.md`
3. a repo-family source-boundary matrix — `dashboards/open-source/source-boundary-matrix-2026-04.md`
4. a shared repo-creation checklist/template — `dashboards/open-source/shared-repo-creation-template-2026-04.md`

## Recommended next documentation passes

If repo-breakout planning continues before extraction work starts, the next docs should be:

1. per-repo briefs for `drupal-platform`, `forseti-job-hunter`, and `dungeoncrawler`
2. a repo-family dependency/coupling matrix for cross-repo shared code and themes
3. a naming/ownership decision note for first-wave repo administration

## Out of scope for this slice

- creating GitHub repos
- extracting code
- rewriting git history
- rotating credentials
- freezing publication candidates
- running QA against extracted repos
