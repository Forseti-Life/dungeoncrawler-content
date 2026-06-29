# Server Integration Inventory — 2026-04

- Owner: `architect-copilot`
- Project: `PROJ-010`
- Status: `in_progress`
- Scope: server-side and operations-side integrations with external systems
- Last updated: `2026-04-13`

## Purpose

This is the first-pass inventory for external integration points used by the Forseti server stack and adjacent org operations on this host. It is intended to give the CEO-managed audit project a concrete baseline for:

- what external systems exist
- where they are referenced in the repo
- how auth/config appears to be supplied
- where config seems to live on the server
- what risks or documentation gaps are already visible

## Inventory

| Integration | Purpose | Primary evidence | Auth / config source | Server storage / resolution | Current notes / risks |
| --- | --- | --- | --- | --- | --- |
| AWS Bedrock (Anthropic models) | Primary AI inference for Forseti chat and AI workflows | `sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php`, `sites/forseti/config/sync/ai_conversation.settings.yml` | Drupal config + environment fallback (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`) | Sync config intentionally blanks creds; live values must come from active Drupal config or env vars | Config inventory is split between git-safe sync and runtime-only secrets; actual live source needs explicit CEO audit |
| AWS Cost Explorer / Billing | Finance expense pull for accounting | `copilot-hq/runbooks/finance/expense-pulls.md` | AWS CLI credential chain for IAM user `forseti`; requires `ce:GetCostAndUsage` | Runtime AWS credentials; not tracked in repo config | Known blocker: current IAM identity lacks `ce:GetCostAndUsage` |
| GitHub org billing API | GitHub usage-based cost tracking for Forseti accounting | `copilot-hq/runbooks/finance/expense-pulls.md`, `copilot-hq/runbooks/finance/billing-sources.md` | `GH_TOKEN=$(cat /home/ubuntu/github.token)` | Token file on server at `/home/ubuntu/github.token` | Usage path works; fixed-charge coverage still unconfirmed; token-file usage should be inventoried across scripts |
| GitHub push / deploy workflow | Source control, production deploy automation, workflow operations | `.github/workflows/deploy.yml`, `copilot-hq/.github/workflows/deploy.yml` | GitHub secrets (`HUBGIT_PAT`, `HOST`, `USERNAME`, `PRIVATE_KEY`) and local `/home/ubuntu/github.token` in HQ scripts | GitHub Actions secrets + local token file | Prior security findings already documented in HQ: PAT exposure risk in workflow logs due to `set -x` plus tokenized clone URL |
| SerpAPI (Google Jobs) | Active job-search ingestion for Job Hunter | `sites/forseti/web/modules/custom/job_hunter/src/Service/SerpApiService.php`, `sites/forseti/config/sync/job_hunter.settings.yml` | Drupal config `job_hunter.settings.serpapi_api_key` | **Stored in config sync YAML right now** | Critical finding: API key is present in tracked config sync and should be rotated + externalized |
| Google Cloud Talent Solution | Optional enterprise job search integration | `sites/forseti/web/modules/custom/job_hunter/src/Service/CloudTalentSolutionService.php`, `sites/forseti/config/sync/job_hunter.settings.yml` | Drupal config `google_cloud_credentials` | Sync config currently empty | Config surface exists, but current sync does not prove live usage |
| Adzuna Jobs API | Optional aggregated job source | `sites/forseti/web/modules/custom/job_hunter/src/Service/AdzunaApiService.php`, `sites/forseti/config/sync/job_hunter.settings.yml` | Drupal config `adzuna_app_id`, `adzuna_app_key` | Sync config currently empty | Config surface exists, but credentials are unset in tracked config |
| USAJobs API | Optional federal job search source | `sites/forseti/web/modules/custom/job_hunter/src/Service/UsaJobsApiService.php`, `sites/forseti/config/sync/job_hunter.settings.yml` | Drupal config `usajobs_api_key`, `usajobs_email` | Sync config currently empty | Config surface exists, but credentials are unset in tracked config |
| Google Tag / Analytics | Site analytics integration | `sites/forseti/composer.json`, `sites/forseti/config/sync/google_tag.settings.yml` | Drupal config | Config sync | Analytics integration is configured in tracked Drupal config; should be included in the config-source audit even though it is lower risk than secrets-bearing integrations |
| Google social auth | Google login/auth integration surface | `sites/forseti/composer.json`, `sites/forseti/web/modules/contrib/social_auth_google/` | Drupal config `social_auth_google.settings` | Likely active Drupal config / admin UI, not present in sync | Installed integration surface exists; credential source and live enablement need confirmation |
| reCAPTCHA / reCAPTCHA v3 | Bot protection integration surface | `sites/forseti/composer.json`, contrib modules under `web/modules/contrib/recaptcha*` | Drupal config | Likely active Drupal config / admin UI, not present in sync | Installed integration surface exists; key source and which version is active need confirmation |
| USFA NERIS | Registry synchronization integration for NFR module | `sites/forseti/web/modules/custom/nfr/src/Form/NFRSettingsForm.php` | Drupal config `enable_neris_sync`, `neris_api_endpoint`, `neris_api_key` | Active Drupal config; config form exists | Integration surface is present, but this inventory only proves config support — not live traffic |
| Hugging Face Hub | Download GGUF models for local LLM stack in HQ | `copilot-hq/llm/download-models.sh`, `copilot-hq/llm/model-manifest.yaml`, `copilot-hq/llm/requirements.txt` | Public Hugging Face downloads via `huggingface_hub` | Model artifacts stored under `copilot-hq/llm/models/` after download | Operational external dependency for HQ; version pinning and checksum policy should be reviewed separately |

## Configuration storage patterns observed

### 1. Drupal config sync

Used for at least some external integration settings, including:

- `sites/forseti/config/sync/job_hunter.settings.yml`
- `sites/forseti/config/sync/ai_conversation.settings.yml`
- `sites/forseti/config/sync/google_tag.settings.yml`

This is currently the highest-risk pattern because at least one live secret is present in tracked sync config:

- `serpapi_api_key` in `job_hunter.settings.yml`

### 2. Active Drupal config / admin UI

Some integrations appear designed to keep secrets out of sync config and rely on runtime config instead, especially:

- AWS Bedrock credentials in `ai_conversation.settings`
- Google social auth
- reCAPTCHA
- possibly NERIS if configured live

This is safer than sync config, but makes inventory incomplete unless the audit includes active DB config inspection.

### 3. Environment variables

Observed patterns include:

- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `DB_PASSWORD`

This means external dependency audit cannot stop at repo files; it also needs a runtime environment inventory.

### 4. Local server token files

Observed explicit token-file dependency:

- `/home/ubuntu/github.token`

This token is used by finance runbooks and HQ operational scripts. Token-file consumers should be cataloged as part of the next audit pass.

### 5. GitHub-hosted secrets

GitHub Actions workflows rely on GitHub secrets for deploy automation, including:

- `HUBGIT_PAT`
- `HOST`
- `USERNAME`
- `PRIVATE_KEY`

This creates a second external-config plane outside Drupal and outside server env vars.

## Initial findings

### Critical

1. `sites/forseti/config/sync/job_hunter.settings.yml` contains a tracked `serpapi_api_key`.

### High

1. External integration config is split across multiple storage planes:
   - Drupal sync config
   - active Drupal DB config
   - environment variables
   - local token files
   - GitHub Actions secrets
2. The system does not yet have a single durable inventory of which integration uses which storage plane.
3. GitHub deploy workflow risk is already documented elsewhere in HQ and should be pulled into this project as inherited security context.

### Open questions for the next pass

1. Which integrations are actually enabled in live Drupal active config versus merely installed/configurable?
2. Which secrets currently live only in the DB versus environment variables?
3. Which scripts read `/home/ubuntu/github.token`, and for what scopes?
4. Which integrations are production-critical versus dormant/stubbed?
5. What should the org-wide standard be for secret placement: env, DB-backed config, token files, or a future secret manager?

## Recommended next actions

1. CEO formalizes the storage-plane audit as the follow-on task for `PROJ-010`.
2. Rotate and remove the tracked SerpAPI secret from config sync.
3. Build a second-pass inventory that inspects:
   - active Drupal config for integration settings
   - environment variables on the server
   - token-file consumers
   - GitHub Actions secret usage by workflow
