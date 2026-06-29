# Outbox update: 20260220-product-documentation-v4

- Agent: pm-theoryofconspiracies
- Source inbox: sessions/pm-theoryofconspiracies/inbox/20260220-product-documentation-v4
- Generated: 2026-02-20T16:30:14-05:00

---

I have full context now. Previous three rounds were `needs-info` stubs — escalation streak is at 3. This round I will produce the full documentation artifact with inferred content clearly marked, breaking the blocking streak.

---

- Status: in_progress
- Summary: Three prior rounds produced empty needs-info stubs; this round produces the full documentation artifact inline so the executor can persist it. The documentation is drafted from available org context (website name, scoreboard, role instructions, Drupal stack inference). Fields that cannot be confirmed without CEO input are explicitly marked `[confirm]`. The target file path is `/home/keithaumiller/forseti.life/docs/product/theoryofconspiracies/README.md`. No code was changed.

## Next actions
- Executor/CEO persists the documentation artifact below to `/home/keithaumiller/forseti.life/docs/product/theoryofconspiracies/README.md`.
- CEO corrects or confirms all `[confirm]` fields and re-delivers a single inbox item with answers.
- Once confirmed, pm-theoryofconspiracies updates the doc to final state and marks Status: done.
- Seed quality scoreboard actuals in `knowledgebase/scoreboards/theoryofconspiracies.com.md` once dev/QA are assigned.

## Blockers
- Dev and QA agent assignments for theoryofconspiracies.com are still unknown — cannot define a verification owner or ship/no-ship chain.
- Target repo instructions (`/home/keithaumiller/forseti.life/.github/instructions/instructions.md` or theoryofconspiracies-specific equivalent) have not been confirmed as readable by this agent.

## Needs from CEO
1. Is theoryofconspiracies.com a separate Drupal site from forseti.life, or a sub-site/domain on the same install?
2. What are the primary content types published on theoryofconspiracies.com? (articles, videos, podcasts, etc.)
3. Who are the target users — general public readers, registered community members, paid subscribers?
4. Are there any authenticated-user features today (accounts, comments, subscriptions)?
5. What external integrations exist (analytics, ad networks, social sharing, email, CDN)?
6. What is the hosting/environment setup (prod URL, staging URL, deploy method)?
7. Which dev and QA agents are assigned to this website?

---

## Artifact content (persist to `/home/keithaumiller/forseti.life/docs/product/theoryofconspiracies/README.md`)

```markdown
# Product Documentation — theoryofconspiracies.com

> PM owner: pm-theoryofconspiracies
> Last updated: 2026-02-20
> Fields marked [confirm] require CEO/stakeholder verification before this document is final.

---

## Overview
- What this product is: theoryofconspiracies.com is a Drupal-based content/editorial website
  publishing articles and commentary on conspiracy theories and related topics. [confirm: content
  types, editorial model]
- Who it is for: general public readers seeking alternative-narrative content; [confirm: whether
  registered/authenticated user features exist; whether there is a paid or community tier]
- Primary value proposition: a curated, searchable library of conspiracy-theory content with
  editorial framing. [confirm]

---

## Scope (and non-goals)
- In scope:
  - Content publishing and presentation (articles, taxonomy, search) [confirm]
  - Anonymous read access to all public content
  - Basic CMS admin for editors
- Non-goals:
  - [confirm: e-commerce, paywalled content, community forums — unknown until CEO confirms]
  - Features belonging to forseti.life or other org websites

---

## Current user journeys / workflows
- Workflow 1 — Anonymous reader: Lands on homepage → browses or searches articles → reads
  article → shares or navigates away. [confirm: whether comments or reactions exist]
- Workflow 2 — Editor/admin: Logs into Drupal admin → creates/edits/publishes article → reviews
  taxonomy tags → publishes. [confirm: editorial review workflow, multi-author support]

---

## Roles / permissions
- Anonymous: read all published content; no write access. [confirm]
- Authenticated: [confirm — unknown whether authenticated user tier exists]
- Admin/Editor: full content management via Drupal admin UI; user management. [confirm: role
  breakdown, whether separate Editor vs Admin roles exist]

---

## Data and integrations
- Key entities/data: nodes (articles), taxonomy (topics/tags), users (admin/editor accounts).
  [confirm: custom tables, media entities, profile data]
- External integrations/APIs: [confirm — analytics platform, CDN, ad network, social sharing,
  email newsletter — all unknown]

---

## Operational notes
- Environments: [confirm: prod URL confirmed as theoryofconspiracies.com; staging URL unknown]
- Deployment notes: [confirm: deployment pipeline, branch strategy, hosting provider]
- Logging/observability: [confirm: error logging, uptime monitoring tool]

---

## Quality / verification
- How to verify happy path: visit theoryofconspiracies.com as anonymous user, confirm homepage
  loads, an article is readable, and navigation works. [confirm: smoke test checklist once QA
  agent is assigned]
- Failure modes to test: 404 on missing nodes; broken taxonomy pages; admin login failure;
  slow page load under traffic spike. [confirm with QA]

---

## Known issues / risks
- No dev or QA agent currently assigned — any bug or feature request cannot be executed or
  verified until assignments are made.
- No instructions.md confirmed in target repo — PM cannot complete required reading per role
  instructions.
- Scoreboard baselines (knowledgebase/scoreboards/theoryofconspiracies.com.md) not yet seeded.
- All `[confirm]` fields above represent unknown product surface area and should be treated as
  documentation debt until resolved.

---

## Roadmap (next 30/60/90)
- 30 days: confirm team assignments; complete all [confirm] fields in this document; seed
  quality scoreboard; read target repo instructions.md.
- 60 days: [confirm — depends on first feature request from CEO/stakeholders]
- 90 days: [confirm]
```
