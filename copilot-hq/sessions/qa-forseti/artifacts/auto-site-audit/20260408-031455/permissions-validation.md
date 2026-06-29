# Permissions validation

- Label: forseti-life
- Base URL: https://forseti.life
- Roles run: anon
- Violations: 0
- Probe issues: 32
- Config: org-chart/sites/forseti.life/qa-permissions.json

## Result
- OK: no permission expectation violations detected.

## Probe issues (non-permission)

These are request errors/timeouts (`status=0`) where the probe could not determine allow/deny.

| Role | Source | Status | Path | URL |
|---|---|---:|---|---|
| anon | route | 0 | /jobhunter/queue/run | https://forseti.life/jobhunter/queue/run |
| anon | route | 0 | /jobhunter/queue/run-all | https://forseti.life/jobhunter/queue/run-all |
| anon | route | 0 | /jobhunter/opportunity/delete-job | https://forseti.life/jobhunter/opportunity/delete-job |
| anon | route | 0 | /jobhunter/opportunity/delete-search | https://forseti.life/jobhunter/opportunity/delete-search |
| anon | route | 0 | /jobhunter/opportunity/bulk-delete | https://forseti.life/jobhunter/opportunity/bulk-delete |
| anon | route | 0 | /jobhunter/queue/delete-item | https://forseti.life/jobhunter/queue/delete-item |
| anon | route | 0 | /jobhunter/queue/suspend-item | https://forseti.life/jobhunter/queue/suspend-item |
| anon | route | 0 | /jobhunter/queue/delete-file | https://forseti.life/jobhunter/queue/delete-file |
| anon | route | 0 | /jobhunter/queue/pause | https://forseti.life/jobhunter/queue/pause |
| anon | route | 0 | /jobhunter/queue/resume | https://forseti.life/jobhunter/queue/resume |
| anon | route | 0 | /jobhunter/queue/retry-suspended | https://forseti.life/jobhunter/queue/retry-suspended |
| anon | route | 0 | /jobhunter/queue/clear-genai-cache | https://forseti.life/jobhunter/queue/clear-genai-cache |
| anon | route | 0 | /jobhunter/job-discovery/search-ajax | https://forseti.life/jobhunter/job-discovery/search-ajax |
| anon | route | 0 | /jobhunter/job-discovery/save | https://forseti.life/jobhunter/job-discovery/save |
| anon | route | 0 | /jobhunter/my-jobs/1/applied | https://forseti.life/jobhunter/my-jobs/1/applied |
| anon | route | 0 | /jobhunter/application-submission/1/identify-auth-path | https://forseti.life/jobhunter/application-submission/1/identify-auth-path |
| anon | route | 0 | /jobhunter/application-submission/1/create-account | https://forseti.life/jobhunter/application-submission/1/create-account |
| anon | route | 0 | /jobhunter/application-submission/1/submit-application | https://forseti.life/jobhunter/application-submission/1/submit-application |
| anon | route | 0 | /jobhunter/application-submission/1/step/1 | https://forseti.life/jobhunter/application-submission/1/step/1 |
| anon | route | 0 | /jobhunter/jobs/1/apply | https://forseti.life/jobhunter/jobs/1/apply |
| anon | route | 0 | /jobhunter/resume/delete/1 | https://forseti.life/jobhunter/resume/delete/1 |
| anon | route | 0 | /jobhunter/tailor-resume/ajax | https://forseti.life/jobhunter/tailor-resume/ajax |
| anon | route | 0 | /jobhunter/profile/add-skill | https://forseti.life/jobhunter/profile/add-skill |
| anon | route | 0 | /jobhunter/tailor-resume/refresh-skills-gap | https://forseti.life/jobhunter/tailor-resume/refresh-skills-gap |
| anon | route | 0 | /jobhunter/resume/pdf/1/delete | https://forseti.life/jobhunter/resume/pdf/1/delete |
| anon | route | 0 | /jobhunter/googlejobsintegration/toggle-sync | https://forseti.life/jobhunter/googlejobsintegration/toggle-sync |
| anon | route | 0 | /jobhunter/googlejobsintegration/generate | https://forseti.life/jobhunter/googlejobsintegration/generate |
| anon | route | 0 | /jobhunter/googlejobsintegration/validate | https://forseti.life/jobhunter/googlejobsintegration/validate |
| anon | route | 0 | /jobhunter/api/googlejobs/import | https://forseti.life/jobhunter/api/googlejobs/import |
| anon | route | 0 | /jobhunter/api/googlejobs/batch-import | https://forseti.life/jobhunter/api/googlejobs/batch-import |
| anon | route | 0 | /jobhunter/settings/credentials/1/delete | https://forseti.life/jobhunter/settings/credentials/1/delete |
| anon | route | 0 | /jobhunter/settings/credentials/1/test | https://forseti.life/jobhunter/settings/credentials/1/test |
