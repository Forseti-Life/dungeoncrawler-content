# Feature: forseti-open-source-initiative

- Work item id: forseti-open-source-initiative
- Website: forseti.life
- Group Order: 1
- Group: oss-initiative
- Group Title: Open Source Initiative
- Group Sort: 1
- Status: in_progress
- Owner: pm-open-source
- Dev owner: dev-open-source
- QA owner: qa-open-source
- Executive sponsor: ceo-copilot-2
- Priority: high
- Release: 20260414-proj-009-publication-candidate-drupal-ai-conversation
- Project: PROJ-009

## Summary
Publish the **Forseti Autonomous Drupal Development Platform** as open source under `github.com/Forseti-Life/`. This is a full-stack system: Drupal 10/11 multi-site + AWS Bedrock AI integration + LangGraph agent orchestration that autonomously manages the entire software development lifecycle (PM → BA → Dev → QA → Release). The platform itself is the product being open sourced — not just individual modules.

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

## GitHub Org: Forseti-Life ✅ VERIFIED
- Board decision: publish under `github.com/Forseti-Life/`
- Verified 2026-04-13 via `gh api /orgs/Forseti-Life`
- Current follow-through: confirm final repo ownership/admin model and use the live org for publication-candidate creation

## Current Readiness Snapshot (2026-04-13)

Already completed in `copilot-hq`:
- Public release checklist exists: `PUBLIC_REPO_PREP.md`
- Publication readiness note exists: `runbooks/publication-readiness-20260308.md`
- Public release gate report exists: `runbooks/public-release-gate-20260308.md`
- Public positioning doc exists: `runbooks/public-repo-positioning.md`
- Dual-repo strategy doc exists: `runbooks/private-public-dual-repo.md`
- Public mirror helper scripts exist: `scripts/setup-public-mirror.sh`, `scripts/export-public-mirror.sh`
- Community/legal files exist: `LICENSE`, `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `SECURITY.md`
- Publication scope is now explicit: use curated mirrors / extracted repos and keep `sessions/**`, `inbox/responses/**`, `tmp/**`, `prod-config/**`, `database-exports/**`, and key material private by default

Still blocking first public launch:
- Previously tracked AWS credentials have been stripped from the current-tree Drupal config sync files, but external rotation confirmation and history scrub are still required
- Full git history scrub/redaction is still required before any public push
- Public candidate branch or curated mirror has not yet been frozen
- Baseline validation run for publication candidate still needs to be recorded
- `drupal-ai-conversation` still has candidate-local NO-GO findings that must be removed before freeze

## Immediate Next Action

1. `dev-open-source` clears the candidate-local `drupal-ai-conversation` NO-GO findings and produces the sanitized extraction boundary for the first freeze candidate.
2. `ceo-copilot-2` confirms external AWS credential rotation while dev/PM finish the remaining history-scrub and private-path enforcement work required for safe publication.
3. `pm-open-source` freezes a curated sanitized extract for `drupal-ai-conversation` and hands QA the exact frozen packet defined by the candidate gate and validation plan.

Current planning reference for that path:
- `dashboards/open-source/drupal-ai-conversation-freeze-plan-2026-04.md`

## Success Criteria
1. `forseti-platform` overview repo published with architecture and quickstart
2. At least `copilot-agent-framework` and `drupal-ai-conversation` publicly accessible on GitHub under `Forseti-Life/`
3. Each public repo has complete setup documentation — fresh install on a clean machine works
4. No secrets, private keys, or client data present in any public repo or its git history
5. GitHub Actions CI passes on each public repo
6. Community files present in each repo (LICENSE, README, CONTRIBUTING, CODE_OF_CONDUCT, SECURITY, issue templates)

## Work Breakdown

### Phase 0 — Governance & Org Setup
- [x] Board creates `Forseti-Life` GitHub org
- [ ] Confirm final owners/admins for the org
- [x] Confirm org exists and token has access: `GH_TOKEN=$(cat /home/ubuntu/github.token) gh api /orgs/Forseti-Life`

### Phase 1 — Pre-Publish Security Audit
Owner: dev-open-source, reviewed by pm-open-source

- [ ] Run BFG Repo Cleaner against monorepo to identify all secrets in 1,813-commit history
- [ ] Remove RSA private keys from `sites/forseti/keys/` AND from full git history
- [ ] Scrub `sessions/` from copilot-hq history (or mark for exclusion in new repo)
- [ ] Sanitize `.env.example` — replace all literal credentials with `YOUR_<VAR>` placeholders
- [ ] Audit 9 copilot scripts for hardcoded tokens, IPs, or server-specific values
- [ ] Audit `prod-config/` — confirm it will not be included in any public repo
- [ ] Confirm `database-exports/` not in any extractable history segment

### Phase 2 — Repo Extraction (per-product, in sequence)

#### 2a — forseti-platform (new — overview repo, first published)
- [ ] Create empty repo in Forseti-Life: `forseti-platform`
- [ ] Write README: what the platform is, architecture diagram, how components relate
- [ ] Write QUICKSTART.md: how to deploy your own instance (Ubuntu 22/24, Drupal, agents)
- [ ] Document the agent model (org-chart, seats, orchestrator loop)
- [ ] Link to all component repos
- [ ] Tag v1.0.0, publish

#### 2b — drupal-ai-conversation (standalone module — lowest risk, highest Drupal ecosystem value)
- [ ] Extract `shared/modules/ai_conversation/` to new git repo under Forseti-Life
- [ ] Write README (AWS Bedrock integration, supported Claude models, config env vars)
- [ ] Add GitHub Actions: Drupal coding standards (phpcs) + basic install test
- [ ] Tag v1.0.0, publish to Drupal.org as contributed module

#### 2c — copilot-agent-framework (highest novelty — the autonomous dev engine)
- [ ] Extract `copilot-hq/` minus sessions/, minus prod secrets
- [ ] Write README (orchestrator architecture, agent seat model, LangGraph flow)
- [ ] Add GitHub Actions: Python lint + orchestrator import test
- [ ] Write QUICKSTART.md (local Python venv setup, first agent run end-to-end)
- [ ] Tag v1.0.0, publish

#### 2d — drupal-platform (the Drupal multi-site stack itself)
- [ ] Extract `sites/forseti/` core stack, sans client data and private keys
- [ ] Document symlink architecture for shared modules
- [ ] Write README (multi-site setup, shared modules, Apache vhost config)
- [ ] Add DDEV `.ddev/` config for local development
- [ ] Add GitHub Actions: composer validate + phpcs
- [ ] Tag v1.0.0, publish

#### 2e — forseti-job-hunter (flagship application)
- [ ] Extract `sites/forseti/web/modules/custom/job_hunter` + theme
- [ ] Write README (what it does, Drupal 10/11 requirements, install steps)
- [ ] Add GitHub Actions: Drupal coding standards + composer validate
- [ ] Write QUICKSTART.md (requires drupal-platform + drupal-ai-conversation)
- [ ] Document AWS Bedrock dependency (requires `.env` with keys)
- [ ] Tag v1.0.0, publish

#### 2f — dungeoncrawler (community TTRPG product)
- [ ] Extract `sites/dungeoncrawler/` modules + theme
- [ ] Write README (PF2E assistant, features, install)
- [ ] Add GitHub Actions: Drupal coding standards
- [ ] Tag v1.0.0, publish

### Phase 3 — Community Infrastructure
- [ ] Create GitHub issue templates per repo (bug report, feature request, module improvement)
- [ ] Write top-level CONTRIBUTING.md for each repo (code style, PR process, review policy)
- [ ] Create GitHub Discussions or Discord (TBD — pm-open-source to recommend)
- [ ] Add CODEOWNERS file to each repo
- [ ] Configure branch protection on `main` (require PR + CI before merge)

### Phase 4 — Launch
- [ ] Announce on relevant communities (Drupal.org, Hacker News, AI engineering forums, TTRPG communities)
- [ ] Drupal.org module listing for `drupal-ai-conversation`
- [ ] Post-launch KPI baseline: stars, forks, issues filed within 30 days

### Phase 5 — Tier 2 Evaluation (post-launch)
- [ ] `forseti-safety` / amisafe: privacy/data review before module extraction
- [ ] `forseti-mobile`: assess React Native app for standalone publishability
- [ ] `nfr` module: stakeholder review

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Secrets in git history | High | Critical | BFG scan before any push to public remote |
| Client data in extractable history | Medium | High | Audit before Phase 1 completes |
| No local dev path exists (no Docker) | High | Medium | Phase 2 includes QUICKSTART.md with DDEV/Lando |
| MySQL running --skip-grant-tables | Medium | Medium | Document as known issue; fix in separate infra ticket |
| Community issues overloading solo dev | Medium | Medium | Set clear issue triage policy in CONTRIBUTING.md |

## Dependencies
- GitHub org decision (Board)
- BFG Repo Cleaner available on server (`apt install bfg` or download jar)
- DDEV or Lando install documented for contributors (server doesn't have it — devs use locally)

## Notes
- All Tier 1 repos are Apache 2.0 — consistent with current root LICENSE
- forseti-mobile will need separate treatment (React Native build environment)
- `amisafe_database` (4.4 GB, 4 tables) is likely crime data — excluded from all open source repos; `amisafe` module code may be extractable separately after data review
