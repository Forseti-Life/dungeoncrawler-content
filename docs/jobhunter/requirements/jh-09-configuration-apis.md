# JobHunter Requirements — JH-09: Configuration & API Key Management

**Status summary:** 9 of 9 requirements COMPLETED, 0 PARTIAL, 0 GAP  
**Last updated:** 2026-02-27  
**Module:** `job_hunter` (forseti.life)

## Overview

This subsystem provides the administrative settings form and all module-level configuration, including API credentials for all external services (AWS Bedrock, SerpAPI, Google Cloud Talent Solution, Adzuna, USAJobs), AI model selection, original resume selection, and auto-tailoring toggle. All settings are accessible at `/jobhunter/settings` and are exportable via Drupal's configuration management system.

## Requirements

### REQ-09.1 — AWS Bedrock configuration

**Status:** COMPLETED  
**Code path:** `src/Form/SettingsForm.php`, `config/install/job_hunter.settings.yml`

- The administrator must be able to configure the AWS Bedrock region and model identifier from the settings form at `/jobhunter/settings`.
- The default model must be `anthropic.claude-3-5-sonnet-20241022-v2:0` (Claude 3.5 Sonnet).
- AWS access key ID and secret access key must be stored via `CredentialManagementService` (not in plain config).
- The configured region and model must be used by all workers that call AWS Bedrock (`ResumeTailoringWorker`, `CoverLetterTailoringWorker`, `ResumeGenAiParsingWorker`).

---

### REQ-09.2 — SerpAPI key configuration

**Status:** COMPLETED  
**Code path:** `src/Form/SettingsForm.php`

- The administrator must be able to configure the SerpAPI API key from the settings form.
- The key must be stored via `CredentialManagementService`.
- The key must be retrieved by `SerpApiService` at runtime; it must not be hard-coded or stored in a config YAML file.

---

### REQ-09.3 — Google Cloud Talent Solution configuration

**Status:** COMPLETED  
**Code path:** `src/Form/SettingsForm.php`

- The administrator must be able to configure Google Cloud Talent Solution credentials (project ID and service account key or OAuth token) from the settings form.
- Credentials must be stored via `CredentialManagementService`.
- The project ID must be stored in module config; the service account key material must not be stored in plain config.

---

### REQ-09.4 — Adzuna API key configuration

**Status:** COMPLETED  
**Code path:** `src/Form/SettingsForm.php`

- The administrator must be able to configure the Adzuna application ID and API key from the settings form.
- Both values must be stored via `CredentialManagementService`.
- The configured credentials must be retrieved by `AdzunaApiService` at runtime.

---

### REQ-09.5 — USAJobs API key configuration

**Status:** COMPLETED  
**Code path:** `src/Form/SettingsForm.php`

- The administrator must be able to configure the USAJobs API key and authorization email from the settings form.
- Both values must be stored via `CredentialManagementService`.
- The configured credentials must be retrieved by `UsaJobsApiService` at runtime.

---

### REQ-09.6 — Original resume node selection

**Status:** COMPLETED  
**Code path:** `src/Form/SettingsForm.php`, `config/install/job_hunter.settings.yml`

- The administrator must be able to select which `tailored_resume` node serves as the "Original Resume" — the base document used as the starting point for all AI tailoring operations.
- The selection must be stored in module configuration as a node ID reference.
- If no original resume is configured, the tailoring workers must log a warning and halt rather than produce an empty output.

---

### REQ-09.7 — Automatic tailoring toggle

**Status:** COMPLETED  
**Code path:** `src/Form/SettingsForm.php`, `config/install/job_hunter.settings.yml`

- The administrator must be able to toggle automatic resume tailoring on or off from the settings form.
- When automatic tailoring is enabled, saving a job posting must automatically enqueue a `ResumeTailoringWorker` item.
- When disabled, tailoring must only occur when manually triggered by the user.
- The toggle state must be stored in module configuration and respected at the point of job save.

---

### REQ-09.8 — Secure credential storage (all APIs)

**Status:** COMPLETED  
**Code path:** `src/Service/CredentialManagementService.php`

- All API credentials configured via the settings form must be persisted through `CredentialManagementService`, not written directly into Drupal's configuration storage.
- The settings form must display credentials as password fields (masked input) and must not echo stored credential values back to the page.
- On form submission, empty credential fields must leave the existing stored value unchanged (not overwrite with an empty string).

---

### REQ-09.9 — Configuration management export compatibility

**Status:** COMPLETED  
**Code path:** `config/schema/job_hunter.schema.yml`, `config/install/job_hunter.settings.yml`

- All non-sensitive module settings (region, model identifier, auto-tailor toggle, original resume node ID, etc.) must be exportable and importable via Drupal's configuration management system (`drush cex` / `drush cim`).
- A complete config schema must be defined in `config/schema/job_hunter.schema.yml` so that all settings are typed and validated.
- Sensitive credential values must be explicitly excluded from config export (they live in `CredentialManagementService`, not in YAML).

---

## Known Gaps

No functional gaps identified in this subsystem. The following improvements are recommended:

- The settings form does not include a "Test Connection" button for each API; administrators cannot verify credential validity without triggering a real operation.
- AWS Bedrock model selection is a free-text field; it should be a select list of supported model identifiers to prevent typo errors.
- There is no diff/audit log of settings changes (who changed what and when).

## Test Coverage

No unit tests exist for `SettingsForm` or `CredentialManagementService`. Estimated coverage: **0%** for this subsystem. Priority test targets: `CredentialManagementService` store/retrieve cycle, config schema validation, and form submission behavior when credential fields are left empty (REQ-09.8).
