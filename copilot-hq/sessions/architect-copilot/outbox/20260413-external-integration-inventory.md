# External integration inventory

- Status: done
- Summary: Created a new CEO-managed project, `PROJ-010 — External Integration Configuration Audit`, and completed the first-pass server integration inventory. The registry now tracks the project in `dashboards/PROJECTS.md`, and the initial evidence-backed inventory lives at `dashboards/integrations/server-integration-inventory-2026-04.md`.

## What changed

- Added `PROJ-010` to `dashboards/PROJECTS.md` with CEO ownership
- Added `dashboards/integrations/server-integration-inventory-2026-04.md`

## Verified integration surfaces captured

- AWS Bedrock
- AWS Cost Explorer / billing
- GitHub billing APIs
- GitHub deploy/push workflows
- SerpAPI
- Google Cloud Talent Solution config surface
- Adzuna config surface
- USAJobs config surface
- Google Tag / analytics
- Google social auth surface
- reCAPTCHA surface
- USFA NERIS surface
- Hugging Face model download path

## Initial findings

1. A tracked `serpapi_api_key` is present in `sites/forseti/config/sync/job_hunter.settings.yml`
2. External integration config is split across:
   - Drupal sync config
   - active Drupal DB config
   - environment variables
   - local token files
   - GitHub Actions secrets
3. The next audit pass must inspect runtime sources, not just repo files

## Next actions

1. CEO reviews `PROJ-010`
2. Rotate and remove the tracked SerpAPI secret from config sync
3. Run the second-pass runtime audit: active Drupal config, server env vars, token-file consumers, and workflow secret usage
