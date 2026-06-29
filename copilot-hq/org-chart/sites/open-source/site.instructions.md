# Site Instructions: open-source

## Authority
- Primary owner: `pm-open-source`
- Methodology owner: `ceo-copilot-2`

## Mission
Publish the **Forseti Autonomous Drupal Development Platform** as open source — a full-stack system combining Drupal 10/11 multi-site architecture with an AI agent orchestration layer that autonomously manages the software development lifecycle (PM → BA → Dev → QA → Release). Enables community contributions, external adoption, and replication — advancing the org mission to **democratize and decentralize internet services**.

## What This Platform Is

This is not just a collection of Drupal modules. It is a complete, self-operating software development platform:

| Layer | Technology | What it does |
|---|---|---|
| **Application** | Drupal 10/11 multi-site | Hosts multiple products (forseti.life, DungeonCrawler, client sites) from a single installation |
| **AI Integration** | AWS Bedrock / Claude | Conversational AI features embedded in Drupal modules |
| **Agent Orchestration** | Python / LangGraph | Autonomous AI agents play PM, BA, Dev, QA roles; advance features through a defined release cycle |
| **Dev Automation** | Copilot CLI + GitHub | Agents write code, dispatch work, manage features, review output — no human in the dev loop |
| **Org Structure** | YAML-defined roles | Explicit agent seat model: org-chart, ownership matrix, decision authority |

**The full platform is the product.** Individual repos (modules, sites, framework) are components of a larger thing that others can fork and operate.

## Applies to
All seats with `website_scope: ["open-source"]`.

## Team lanes
- PM: `pm-open-source`
- BA: `ba-open-source`
- Dev: `dev-open-source`
- QA: `qa-open-source`
- Security: `sec-analyst-open-source`

## Scope — What Gets Open Sourced

### Platform Overview Repo (new — highest priority, tells the whole story)

| Repo Name | Contents | Audience |
|---|---|---|
| `forseti-platform` | Architecture overview, how the pieces fit together, getting-started guide for running your own instance | Everyone — the entry point |

### Tier 1 — Core Platform Repos

| Repo Name | Source Path | Contents | Audience |
|---|---|---|---|
| `copilot-agent-framework` | `copilot-hq/` (sanitized) | LangGraph AI agent orchestration — the autonomous dev layer. Org-chart model, orchestrator, agent seats, release cycle | AI/ML engineers, platform builders |
| `drupal-platform` | `sites/forseti/` (core stack, sans client data) | Drupal 10 multi-site stack, shared module structure, symlink architecture | Drupal devs, self-hosters |
| `drupal-ai-conversation` | `shared/modules/ai_conversation/` | Standalone AWS Bedrock/Claude Drupal module | Drupal module ecosystem |
| `forseti-job-hunter` | `sites/forseti/web/modules/custom/job_hunter` + theme | AI-powered job application platform module | Job seekers, Drupal devs |
| `dungeoncrawler` | `sites/dungeoncrawler/` | Full PF2E assistant Drupal site | TTRPG community, Drupal devs |

### Tier 2 — Evaluate after Tier 1
- `forseti-safety` (`amisafe` module + H3 geolocation) — after data/privacy review
- `forseti-mobile` (React Native app) — after API surface is stable
- `nfr` (National Firefighter Registry) — after stakeholder review

### Not Open Sourced
- Client sites: `stlouisintegration`, `theoryofconspiracies`, `thetruthperspective` — client-owned content
- `prod-config/` — production server config with sensitive values
- `database-exports/` — contains real data
- `sessions/` in copilot-hq — internal org communications

## Repo Structure Decision: Separate Repos (not monorepo split)

**Rationale:**
- Each product has a distinct audience and contribution community
- Prevents cross-contamination of client site history
- Cleaner public surface per product
- Standard open source practice for distinct products
- Allows different release cadences and governance per repo

## Pre-Publish Blocklist (must be resolved before any repo goes public)

| Item | Severity | Action |
|---|---|---|
| RSA private keys in `sites/forseti/keys/` | 🔴 CRITICAL | Remove from files + scrub git history (BFG) |
| `.env.example` with literal sample passwords | 🔴 HIGH | Replace with generic placeholders (`YOUR_DB_PASSWORD`) |
| 1,813-commit git history may contain secrets | 🟡 HIGH | BFG Repo Cleaner scan before publishing |
| Copilot-hq `sessions/` directory | 🟡 HIGH | Exclude entirely from public repo (gitignore + history scrub) |
| Hardcoded server-specific values in scripts | 🟡 MEDIUM | Parameterize via env vars |
| `prod-config/` directory | 🟡 MEDIUM | Exclude from public repo |
| `database-exports/` | 🔴 HIGH | Exclude from public repo; check git history |

## Repo Prerequisites (each public repo needs before launch)

- [ ] LICENSE file (Apache 2.0 — already present in monorepo)
- [ ] README.md (installation, usage, architecture overview)
- [ ] CONTRIBUTING.md (how to contribute, code standards)
- [ ] CODE_OF_CONDUCT.md (already present — reuse)
- [ ] SECURITY.md (vulnerability disclosure process — already present)
- [ ] `.env.example` with NO real credentials
- [ ] GitHub Actions CI (tests pass on PR)
- [ ] Issue templates (bug report, feature request)
- [ ] Local development setup (Docker Compose or documented DDEV/Lando setup)

## Environments
- No dedicated "open-source" web surface — this project produces GitHub repositories, not a running site.
- Validation environment: clean Ubuntu VM or Docker to verify setup docs work end-to-end.

## Code roots (source of truth for extraction)
- Monorepo: `/home/ubuntu/forseti.life/`
- Public GitHub org target: `github.com/Forseti-Life/`

## GitHub Org: Forseti-Life ✅ VERIFIED

**Decision:** Publish all Tier 1 repos under `github.com/Forseti-Life/`

**Rationale:** Community org signals that this belongs to the community, not an individual. Aligns with mission. Consistent with how major open source Drupal distros and AI platform projects operate.

**Verification (2026-04-13):**
- Org exists at `https://github.com/Forseti-Life`
- Publication work can proceed; org creation is no longer the blocker

After verification, dev-open-source can push prepared repos with:
```bash
GH_TOKEN=$(cat /home/ubuntu/github.token) gh repo create Forseti-Life/<repo-name> --public --source=<local-path>
```

## Publication model (decided)
- Use curated mirrors / extracted repos for public release.
- Do **not** flip the private operational monorepo public.
- Keep `sessions/**`, `inbox/responses/**`, `tmp/**`, `prod-config/**`, `database-exports/**`, and key material private unless a specific public repo needs a sanitized example copied in intentionally.

## QA posture
- Each public repo must pass its own CI before going public.
- `drupal-ai-conversation` module: test on clean Drupal 10 and 11 install.
- `copilot-agent-framework`: test orchestrator on clean Python 3.12 venv.
- Publication-security review is a distinct gate: `sec-analyst-open-source` validates secret/history hygiene and public-surface safety before PM freezes a candidate.
