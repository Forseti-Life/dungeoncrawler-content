# Permissions validation

- Label: forseti-life
- Base URL: https://forseti.life
- Roles run: anon, authenticated, content_editor, administrator
- Violations: 1
- Probe issues: 220
- Config: org-chart/sites/forseti.life/qa-permissions.json

## Violations

| Rule | Role | Source | Status | Path | URL | Expected |
|---|---|---|---:|---|---|---|
| jobhunter-surface | authenticated | route | 500 | /jobhunter/analytics | https://forseti.life/jobhunter/analytics | allow |

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
| anon | route | 0 | /jobhunter/my-jobs/bulk-archive | https://forseti.life/jobhunter/my-jobs/bulk-archive |
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
| anon | route | 0 | /jobhunter/jobtailoring/1/save-resume | https://forseti.life/jobhunter/jobtailoring/1/save-resume |
| anon | route | 0 | /jobhunter/resume/pdf/1/delete | https://forseti.life/jobhunter/resume/pdf/1/delete |
| anon | route | 0 | /jobhunter/googlejobsintegration/toggle-sync | https://forseti.life/jobhunter/googlejobsintegration/toggle-sync |
| anon | route | 0 | /jobhunter/googlejobsintegration/generate | https://forseti.life/jobhunter/googlejobsintegration/generate |
| anon | route | 0 | /jobhunter/googlejobsintegration/validate | https://forseti.life/jobhunter/googlejobsintegration/validate |
| anon | route | 0 | /jobhunter/api/googlejobs/import | https://forseti.life/jobhunter/api/googlejobs/import |
| anon | route | 0 | /jobhunter/api/googlejobs/batch-import | https://forseti.life/jobhunter/api/googlejobs/batch-import |
| anon | route | 0 | /jobhunter/settings/credentials/1/delete | https://forseti.life/jobhunter/settings/credentials/1/delete |
| anon | route | 0 | /jobhunter/settings/credentials/1/test | https://forseti.life/jobhunter/settings/credentials/1/test |
| anon | route | 0 | /jobhunter/coverletter/1/generate | https://forseti.life/jobhunter/coverletter/1/generate |
| anon | route | 0 | /jobhunter/coverletter/1/save | https://forseti.life/jobhunter/coverletter/1/save |
| anon | route | 0 | /jobhunter/interview-prep/1/save | https://forseti.life/jobhunter/interview-prep/1/save |
| anon | route | 0 | /jobhunter/interview-rounds/1/save | https://forseti.life/jobhunter/interview-rounds/1/save |
| anon | route | 0 | /jobhunter/interview-prep/1/ai-tips | https://forseti.life/jobhunter/interview-prep/1/ai-tips |
| anon | route | 0 | /jobhunter/saved-search/save | https://forseti.life/jobhunter/saved-search/save |
| anon | route | 0 | /jobhunter/saved-search/1/delete | https://forseti.life/jobhunter/saved-search/1/delete |
| anon | route | 0 | /jobhunter/jobs/1/notes/save | https://forseti.life/jobhunter/jobs/1/notes/save |
| anon | route | 0 | /jobhunter/tailor-feedback | https://forseti.life/jobhunter/tailor-feedback |
| anon | route | 0 | /jobhunter/jobs/1/deadline/save | https://forseti.life/jobhunter/jobs/1/deadline/save |
| anon | route | 0 | /jobhunter/applications/bulk-update | https://forseti.life/jobhunter/applications/bulk-update |
| anon | route | 0 | /jobhunter/companies/1/interest/save | https://forseti.life/jobhunter/companies/1/interest/save |
| anon | route | 0 | /jobhunter/companies/1/research/save | https://forseti.life/jobhunter/companies/1/research/save |
| anon | route | 0 | /jobhunter/contacts/save | https://forseti.life/jobhunter/contacts/save |
| anon | route | 0 | /jobhunter/contacts/1/delete | https://forseti.life/jobhunter/contacts/1/delete |
| anon | route | 0 | /jobhunter/contacts/1/link-job | https://forseti.life/jobhunter/contacts/1/link-job |
| anon | route | 0 | /jobhunter/preferences/save | https://forseti.life/jobhunter/preferences/save |
| anon | route | 0 | /jobhunter/preferences/sources/save | https://forseti.life/jobhunter/preferences/sources/save |
| anon | route | 0 | /jobhunter/resume/1/edit/save | https://forseti.life/jobhunter/resume/1/edit/save |
| anon | route | 0 | /jobhunter/jobs/1/resume-source/save | https://forseti.life/jobhunter/jobs/1/resume-source/save |
| anon | route | 0 | /jobhunter/jobs/1/offer/save | https://forseti.life/jobhunter/jobs/1/offer/save |
| authenticated | route | 0 | /jobhunter/queue/run | https://forseti.life/jobhunter/queue/run |
| authenticated | route | 0 | /jobhunter/queue/run-all | https://forseti.life/jobhunter/queue/run-all |
| authenticated | route | 0 | /jobhunter/opportunity/delete-job | https://forseti.life/jobhunter/opportunity/delete-job |
| authenticated | route | 0 | /jobhunter/opportunity/delete-search | https://forseti.life/jobhunter/opportunity/delete-search |
| authenticated | route | 0 | /jobhunter/opportunity/bulk-delete | https://forseti.life/jobhunter/opportunity/bulk-delete |
| authenticated | route | 0 | /jobhunter/queue/delete-item | https://forseti.life/jobhunter/queue/delete-item |
| authenticated | route | 0 | /jobhunter/queue/suspend-item | https://forseti.life/jobhunter/queue/suspend-item |
| authenticated | route | 0 | /jobhunter/queue/delete-file | https://forseti.life/jobhunter/queue/delete-file |
| authenticated | route | 0 | /jobhunter/queue/pause | https://forseti.life/jobhunter/queue/pause |
| authenticated | route | 0 | /jobhunter/queue/resume | https://forseti.life/jobhunter/queue/resume |
| authenticated | route | 0 | /jobhunter/queue/retry-suspended | https://forseti.life/jobhunter/queue/retry-suspended |
| authenticated | route | 0 | /jobhunter/queue/clear-genai-cache | https://forseti.life/jobhunter/queue/clear-genai-cache |
| authenticated | route | 0 | /jobhunter/job-discovery/search-ajax | https://forseti.life/jobhunter/job-discovery/search-ajax |
| authenticated | route | 0 | /jobhunter/job-discovery/save | https://forseti.life/jobhunter/job-discovery/save |
| authenticated | route | 0 | /jobhunter/my-jobs/bulk-archive | https://forseti.life/jobhunter/my-jobs/bulk-archive |
| authenticated | route | 0 | /jobhunter/my-jobs/1/applied | https://forseti.life/jobhunter/my-jobs/1/applied |
| authenticated | route | 0 | /jobhunter/application-submission/1/identify-auth-path | https://forseti.life/jobhunter/application-submission/1/identify-auth-path |
| authenticated | route | 0 | /jobhunter/application-submission/1/create-account | https://forseti.life/jobhunter/application-submission/1/create-account |
| authenticated | route | 0 | /jobhunter/application-submission/1/submit-application | https://forseti.life/jobhunter/application-submission/1/submit-application |
| authenticated | route | 0 | /jobhunter/application-submission/1/step/1 | https://forseti.life/jobhunter/application-submission/1/step/1 |
| authenticated | route | 0 | /jobhunter/jobs/1/apply | https://forseti.life/jobhunter/jobs/1/apply |
| authenticated | route | 0 | /jobhunter/resume/delete/1 | https://forseti.life/jobhunter/resume/delete/1 |
| authenticated | route | 0 | /jobhunter/tailor-resume/ajax | https://forseti.life/jobhunter/tailor-resume/ajax |
| authenticated | route | 0 | /jobhunter/profile/add-skill | https://forseti.life/jobhunter/profile/add-skill |
| authenticated | route | 0 | /jobhunter/tailor-resume/refresh-skills-gap | https://forseti.life/jobhunter/tailor-resume/refresh-skills-gap |
| authenticated | route | 0 | /jobhunter/jobtailoring/1/save-resume | https://forseti.life/jobhunter/jobtailoring/1/save-resume |
| authenticated | route | 0 | /jobhunter/resume/pdf/1/delete | https://forseti.life/jobhunter/resume/pdf/1/delete |
| authenticated | route | 0 | /jobhunter/googlejobsintegration/toggle-sync | https://forseti.life/jobhunter/googlejobsintegration/toggle-sync |
| authenticated | route | 0 | /jobhunter/googlejobsintegration/generate | https://forseti.life/jobhunter/googlejobsintegration/generate |
| authenticated | route | 0 | /jobhunter/googlejobsintegration/validate | https://forseti.life/jobhunter/googlejobsintegration/validate |
| authenticated | route | 0 | /jobhunter/api/googlejobs/import | https://forseti.life/jobhunter/api/googlejobs/import |
| authenticated | route | 0 | /jobhunter/api/googlejobs/batch-import | https://forseti.life/jobhunter/api/googlejobs/batch-import |
| authenticated | route | 0 | /jobhunter/settings/credentials/1/delete | https://forseti.life/jobhunter/settings/credentials/1/delete |
| authenticated | route | 0 | /jobhunter/settings/credentials/1/test | https://forseti.life/jobhunter/settings/credentials/1/test |
| authenticated | route | 0 | /jobhunter/coverletter/1/generate | https://forseti.life/jobhunter/coverletter/1/generate |
| authenticated | route | 0 | /jobhunter/coverletter/1/save | https://forseti.life/jobhunter/coverletter/1/save |
| authenticated | route | 0 | /jobhunter/interview-prep/1/save | https://forseti.life/jobhunter/interview-prep/1/save |
| authenticated | route | 0 | /jobhunter/interview-rounds/1/save | https://forseti.life/jobhunter/interview-rounds/1/save |
| authenticated | route | 0 | /jobhunter/interview-prep/1/ai-tips | https://forseti.life/jobhunter/interview-prep/1/ai-tips |
| authenticated | route | 0 | /jobhunter/saved-search/save | https://forseti.life/jobhunter/saved-search/save |
| authenticated | route | 0 | /jobhunter/saved-search/1/delete | https://forseti.life/jobhunter/saved-search/1/delete |
| authenticated | route | 0 | /jobhunter/jobs/1/notes/save | https://forseti.life/jobhunter/jobs/1/notes/save |
| authenticated | route | 0 | /jobhunter/tailor-feedback | https://forseti.life/jobhunter/tailor-feedback |
| authenticated | route | 0 | /jobhunter/jobs/1/deadline/save | https://forseti.life/jobhunter/jobs/1/deadline/save |
| authenticated | route | 0 | /jobhunter/applications/bulk-update | https://forseti.life/jobhunter/applications/bulk-update |
| authenticated | route | 0 | /jobhunter/companies/1/interest/save | https://forseti.life/jobhunter/companies/1/interest/save |
| authenticated | route | 0 | /jobhunter/companies/1/research/save | https://forseti.life/jobhunter/companies/1/research/save |
| authenticated | route | 0 | /jobhunter/contacts/save | https://forseti.life/jobhunter/contacts/save |
| authenticated | route | 0 | /jobhunter/contacts/1/delete | https://forseti.life/jobhunter/contacts/1/delete |
| authenticated | route | 0 | /jobhunter/contacts/1/link-job | https://forseti.life/jobhunter/contacts/1/link-job |
| authenticated | route | 0 | /jobhunter/preferences/save | https://forseti.life/jobhunter/preferences/save |
| authenticated | route | 0 | /jobhunter/preferences/sources/save | https://forseti.life/jobhunter/preferences/sources/save |
| authenticated | route | 0 | /jobhunter/resume/1/edit/save | https://forseti.life/jobhunter/resume/1/edit/save |
| authenticated | route | 0 | /jobhunter/jobs/1/resume-source/save | https://forseti.life/jobhunter/jobs/1/resume-source/save |
| authenticated | route | 0 | /jobhunter/jobs/1/offer/save | https://forseti.life/jobhunter/jobs/1/offer/save |
| content_editor | route | 0 | /jobhunter/queue/run | https://forseti.life/jobhunter/queue/run |
| content_editor | route | 0 | /jobhunter/queue/run-all | https://forseti.life/jobhunter/queue/run-all |
| content_editor | route | 0 | /jobhunter/opportunity/delete-job | https://forseti.life/jobhunter/opportunity/delete-job |
| content_editor | route | 0 | /jobhunter/opportunity/delete-search | https://forseti.life/jobhunter/opportunity/delete-search |
| content_editor | route | 0 | /jobhunter/opportunity/bulk-delete | https://forseti.life/jobhunter/opportunity/bulk-delete |
| content_editor | route | 0 | /jobhunter/queue/delete-item | https://forseti.life/jobhunter/queue/delete-item |
| content_editor | route | 0 | /jobhunter/queue/suspend-item | https://forseti.life/jobhunter/queue/suspend-item |
| content_editor | route | 0 | /jobhunter/queue/delete-file | https://forseti.life/jobhunter/queue/delete-file |
| content_editor | route | 0 | /jobhunter/queue/pause | https://forseti.life/jobhunter/queue/pause |
| content_editor | route | 0 | /jobhunter/queue/resume | https://forseti.life/jobhunter/queue/resume |
| content_editor | route | 0 | /jobhunter/queue/retry-suspended | https://forseti.life/jobhunter/queue/retry-suspended |
| content_editor | route | 0 | /jobhunter/queue/clear-genai-cache | https://forseti.life/jobhunter/queue/clear-genai-cache |
| content_editor | route | 0 | /jobhunter/job-discovery/search-ajax | https://forseti.life/jobhunter/job-discovery/search-ajax |
| content_editor | route | 0 | /jobhunter/job-discovery/save | https://forseti.life/jobhunter/job-discovery/save |
| content_editor | route | 0 | /jobhunter/my-jobs/bulk-archive | https://forseti.life/jobhunter/my-jobs/bulk-archive |
| content_editor | route | 0 | /jobhunter/my-jobs/1/applied | https://forseti.life/jobhunter/my-jobs/1/applied |
| content_editor | route | 0 | /jobhunter/application-submission/1/identify-auth-path | https://forseti.life/jobhunter/application-submission/1/identify-auth-path |
| content_editor | route | 0 | /jobhunter/application-submission/1/create-account | https://forseti.life/jobhunter/application-submission/1/create-account |
| content_editor | route | 0 | /jobhunter/application-submission/1/submit-application | https://forseti.life/jobhunter/application-submission/1/submit-application |
| content_editor | route | 0 | /jobhunter/application-submission/1/step/1 | https://forseti.life/jobhunter/application-submission/1/step/1 |
| content_editor | route | 0 | /jobhunter/jobs/1/apply | https://forseti.life/jobhunter/jobs/1/apply |
| content_editor | route | 0 | /jobhunter/resume/delete/1 | https://forseti.life/jobhunter/resume/delete/1 |
| content_editor | route | 0 | /jobhunter/tailor-resume/ajax | https://forseti.life/jobhunter/tailor-resume/ajax |
| content_editor | route | 0 | /jobhunter/profile/add-skill | https://forseti.life/jobhunter/profile/add-skill |
| content_editor | route | 0 | /jobhunter/tailor-resume/refresh-skills-gap | https://forseti.life/jobhunter/tailor-resume/refresh-skills-gap |
| content_editor | route | 0 | /jobhunter/jobtailoring/1/save-resume | https://forseti.life/jobhunter/jobtailoring/1/save-resume |
| content_editor | route | 0 | /jobhunter/resume/pdf/1/delete | https://forseti.life/jobhunter/resume/pdf/1/delete |
| content_editor | route | 0 | /jobhunter/googlejobsintegration/toggle-sync | https://forseti.life/jobhunter/googlejobsintegration/toggle-sync |
| content_editor | route | 0 | /jobhunter/googlejobsintegration/generate | https://forseti.life/jobhunter/googlejobsintegration/generate |
| content_editor | route | 0 | /jobhunter/googlejobsintegration/validate | https://forseti.life/jobhunter/googlejobsintegration/validate |
| content_editor | route | 0 | /jobhunter/api/googlejobs/import | https://forseti.life/jobhunter/api/googlejobs/import |
| content_editor | route | 0 | /jobhunter/api/googlejobs/batch-import | https://forseti.life/jobhunter/api/googlejobs/batch-import |
| content_editor | route | 0 | /jobhunter/settings/credentials/1/delete | https://forseti.life/jobhunter/settings/credentials/1/delete |
| content_editor | route | 0 | /jobhunter/settings/credentials/1/test | https://forseti.life/jobhunter/settings/credentials/1/test |
| content_editor | route | 0 | /jobhunter/coverletter/1/generate | https://forseti.life/jobhunter/coverletter/1/generate |
| content_editor | route | 0 | /jobhunter/coverletter/1/save | https://forseti.life/jobhunter/coverletter/1/save |
| content_editor | route | 0 | /jobhunter/interview-prep/1/save | https://forseti.life/jobhunter/interview-prep/1/save |
| content_editor | route | 0 | /jobhunter/interview-rounds/1/save | https://forseti.life/jobhunter/interview-rounds/1/save |
| content_editor | route | 0 | /jobhunter/interview-prep/1/ai-tips | https://forseti.life/jobhunter/interview-prep/1/ai-tips |
| content_editor | route | 0 | /jobhunter/saved-search/save | https://forseti.life/jobhunter/saved-search/save |
| content_editor | route | 0 | /jobhunter/saved-search/1/delete | https://forseti.life/jobhunter/saved-search/1/delete |
| content_editor | route | 0 | /jobhunter/jobs/1/notes/save | https://forseti.life/jobhunter/jobs/1/notes/save |
| content_editor | route | 0 | /jobhunter/tailor-feedback | https://forseti.life/jobhunter/tailor-feedback |
| content_editor | route | 0 | /jobhunter/jobs/1/deadline/save | https://forseti.life/jobhunter/jobs/1/deadline/save |
| content_editor | route | 0 | /jobhunter/applications/bulk-update | https://forseti.life/jobhunter/applications/bulk-update |
| content_editor | route | 0 | /jobhunter/companies/1/interest/save | https://forseti.life/jobhunter/companies/1/interest/save |
| content_editor | route | 0 | /jobhunter/companies/1/research/save | https://forseti.life/jobhunter/companies/1/research/save |
| content_editor | route | 0 | /jobhunter/contacts/save | https://forseti.life/jobhunter/contacts/save |
| content_editor | route | 0 | /jobhunter/contacts/1/delete | https://forseti.life/jobhunter/contacts/1/delete |
| content_editor | route | 0 | /jobhunter/contacts/1/link-job | https://forseti.life/jobhunter/contacts/1/link-job |
| content_editor | route | 0 | /jobhunter/preferences/save | https://forseti.life/jobhunter/preferences/save |
| content_editor | route | 0 | /jobhunter/preferences/sources/save | https://forseti.life/jobhunter/preferences/sources/save |
| content_editor | route | 0 | /jobhunter/resume/1/edit/save | https://forseti.life/jobhunter/resume/1/edit/save |
| content_editor | route | 0 | /jobhunter/jobs/1/resume-source/save | https://forseti.life/jobhunter/jobs/1/resume-source/save |
| content_editor | route | 0 | /jobhunter/jobs/1/offer/save | https://forseti.life/jobhunter/jobs/1/offer/save |
| administrator | route | 0 | /jobhunter/queue/run | https://forseti.life/jobhunter/queue/run |
| administrator | route | 0 | /jobhunter/queue/run-all | https://forseti.life/jobhunter/queue/run-all |
| administrator | route | 0 | /jobhunter/opportunity/delete-job | https://forseti.life/jobhunter/opportunity/delete-job |
| administrator | route | 0 | /jobhunter/opportunity/delete-search | https://forseti.life/jobhunter/opportunity/delete-search |
| administrator | route | 0 | /jobhunter/opportunity/bulk-delete | https://forseti.life/jobhunter/opportunity/bulk-delete |
| administrator | route | 0 | /jobhunter/queue/delete-item | https://forseti.life/jobhunter/queue/delete-item |
| administrator | route | 0 | /jobhunter/queue/suspend-item | https://forseti.life/jobhunter/queue/suspend-item |
| administrator | route | 0 | /jobhunter/queue/delete-file | https://forseti.life/jobhunter/queue/delete-file |
| administrator | route | 0 | /jobhunter/queue/pause | https://forseti.life/jobhunter/queue/pause |
| administrator | route | 0 | /jobhunter/queue/resume | https://forseti.life/jobhunter/queue/resume |
| administrator | route | 0 | /jobhunter/queue/retry-suspended | https://forseti.life/jobhunter/queue/retry-suspended |
| administrator | route | 0 | /jobhunter/queue/clear-genai-cache | https://forseti.life/jobhunter/queue/clear-genai-cache |
| administrator | route | 0 | /jobhunter/job-discovery/search-ajax | https://forseti.life/jobhunter/job-discovery/search-ajax |
| administrator | route | 0 | /jobhunter/job-discovery/save | https://forseti.life/jobhunter/job-discovery/save |
| administrator | route | 0 | /jobhunter/my-jobs/bulk-archive | https://forseti.life/jobhunter/my-jobs/bulk-archive |
| administrator | route | 0 | /jobhunter/my-jobs/1/applied | https://forseti.life/jobhunter/my-jobs/1/applied |
| administrator | route | 0 | /jobhunter/application-submission/1/identify-auth-path | https://forseti.life/jobhunter/application-submission/1/identify-auth-path |
| administrator | route | 0 | /jobhunter/application-submission/1/create-account | https://forseti.life/jobhunter/application-submission/1/create-account |
| administrator | route | 0 | /jobhunter/application-submission/1/submit-application | https://forseti.life/jobhunter/application-submission/1/submit-application |
| administrator | route | 0 | /jobhunter/application-submission/1/step/1 | https://forseti.life/jobhunter/application-submission/1/step/1 |
| administrator | route | 0 | /jobhunter/jobs/1/apply | https://forseti.life/jobhunter/jobs/1/apply |
| administrator | route | 0 | /jobhunter/resume/delete/1 | https://forseti.life/jobhunter/resume/delete/1 |
| administrator | route | 0 | /jobhunter/tailor-resume/ajax | https://forseti.life/jobhunter/tailor-resume/ajax |
| administrator | route | 0 | /jobhunter/profile/add-skill | https://forseti.life/jobhunter/profile/add-skill |
| administrator | route | 0 | /jobhunter/tailor-resume/refresh-skills-gap | https://forseti.life/jobhunter/tailor-resume/refresh-skills-gap |
| administrator | route | 0 | /jobhunter/jobtailoring/1/save-resume | https://forseti.life/jobhunter/jobtailoring/1/save-resume |
| administrator | route | 0 | /jobhunter/resume/pdf/1/delete | https://forseti.life/jobhunter/resume/pdf/1/delete |
| administrator | route | 0 | /jobhunter/googlejobsintegration/toggle-sync | https://forseti.life/jobhunter/googlejobsintegration/toggle-sync |
| administrator | route | 0 | /jobhunter/googlejobsintegration/generate | https://forseti.life/jobhunter/googlejobsintegration/generate |
| administrator | route | 0 | /jobhunter/googlejobsintegration/validate | https://forseti.life/jobhunter/googlejobsintegration/validate |
| administrator | route | 0 | /jobhunter/api/googlejobs/import | https://forseti.life/jobhunter/api/googlejobs/import |
| administrator | route | 0 | /jobhunter/api/googlejobs/batch-import | https://forseti.life/jobhunter/api/googlejobs/batch-import |
| administrator | route | 0 | /jobhunter/settings/credentials/1/delete | https://forseti.life/jobhunter/settings/credentials/1/delete |
| administrator | route | 0 | /jobhunter/settings/credentials/1/test | https://forseti.life/jobhunter/settings/credentials/1/test |
| administrator | route | 0 | /jobhunter/coverletter/1/generate | https://forseti.life/jobhunter/coverletter/1/generate |

(Truncated: 220 total probe issues)
