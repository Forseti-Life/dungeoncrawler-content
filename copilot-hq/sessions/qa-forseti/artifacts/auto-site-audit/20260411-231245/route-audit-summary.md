# Route audit summary

- Base URL: https://forseti.life
- Routes checked: 132

## Admin routes returning 200 (potential ACL bug)
- None

## API routes with errors (>=400)
| Status | Module | Route | Path | URL |
|---:|---|---|---|---|
| 403 | job_hunter | job_hunter.google_jobs_details | `/jobhunter/api/googlejobs/details/{job_name}` | https://forseti.life/jobhunter/api/googlejobs/details/test |
| 403 | job_hunter | job_hunter.google_jobs_search_api | `/jobhunter/api/googlejobs/search` | https://forseti.life/jobhunter/api/googlejobs/search |

## Other non-admin route errors (>=400)
| Status | Module | Route | Path | URL |
|---:|---|---|---|---|
| 403 | job_hunter | job_hunter.add | `/jobhunter/applications/add` | https://forseti.life/jobhunter/applications/add |
| 403 | job_hunter | job_hunter.addposting | `/jobhunter/addposting` | https://forseti.life/jobhunter/addposting |
| 403 | job_hunter | job_hunter.analytics | `/jobhunter/analytics` | https://forseti.life/jobhunter/analytics |
| 403 | job_hunter | job_hunter.application_notes_load | `/jobhunter/jobs/{job_id}/notes` | https://forseti.life/jobhunter/jobs/1/notes |
| 403 | job_hunter | job_hunter.application_status | `/jobhunter/jobs/{job_id}/application-status` | https://forseti.life/jobhunter/jobs/1/application-status |
| 403 | job_hunter | job_hunter.application_submission | `/jobhunter/application-submission` | https://forseti.life/jobhunter/application-submission |
| 403 | job_hunter | job_hunter.application_submission_job | `/jobhunter/application-submission/{job_id}` | https://forseti.life/jobhunter/application-submission/1 |
| 403 | job_hunter | job_hunter.application_submission_step2 | `/jobhunter/application-submission/{job_id}/resolve-redirect-chain` | https://forseti.life/jobhunter/application-submission/1/resolve-redirect-chain |
| 403 | job_hunter | job_hunter.application_submission_step3 | `/jobhunter/application-submission/{job_id}/identify-auth-path` | https://forseti.life/jobhunter/application-submission/1/identify-auth-path |
| 403 | job_hunter | job_hunter.application_submission_step4 | `/jobhunter/application-submission/{job_id}/create-account` | https://forseti.life/jobhunter/application-submission/1/create-account |
| 403 | job_hunter | job_hunter.application_submission_step5 | `/jobhunter/application-submission/{job_id}/submit-application` | https://forseti.life/jobhunter/application-submission/1/submit-application |
| 403 | job_hunter | job_hunter.application_submission_step5_screenshot | `/jobhunter/application-submission/{job_id}/screenshot/{filename}` | https://forseti.life/jobhunter/application-submission/1/screenshot/test |
| 403 | job_hunter | job_hunter.application_submission_step_stub | `/jobhunter/application-submission/{job_id}/step/{step}` | https://forseti.life/jobhunter/application-submission/1/step/1 |
| 403 | job_hunter | job_hunter.applications_dashboard | `/jobhunter/applications` | https://forseti.life/jobhunter/applications |
| 403 | job_hunter | job_hunter.bulk_actions | `/jobhunter/bulk-actions` | https://forseti.life/jobhunter/bulk-actions |
| 403 | job_hunter | job_hunter.bulk_import_companies | `/jobhunter/bulk-import-companies` | https://forseti.life/jobhunter/bulk-import-companies |
| 403 | job_hunter | job_hunter.companies_list | `/jobhunter/companies/list` | https://forseti.life/jobhunter/companies/list |
| 403 | job_hunter | job_hunter.company_add | `/jobhunter/companies/add` | https://forseti.life/jobhunter/companies/add |
| 403 | job_hunter | job_hunter.company_delete | `/jobhunter/companies/{company_id}/delete` | https://forseti.life/jobhunter/companies/1/delete |
| 403 | job_hunter | job_hunter.company_edit | `/jobhunter/companies/{company_id}/edit` | https://forseti.life/jobhunter/companies/1/edit |
| 403 | job_hunter | job_hunter.company_research | `/jobhunter/companyresearch` | https://forseti.life/jobhunter/companyresearch |
| 403 | job_hunter | job_hunter.cover_letter | `/jobhunter/coverletter/{job_id}` | https://forseti.life/jobhunter/coverletter/1 |
| 403 | job_hunter | job_hunter.credentials | `/jobhunter/settings/credentials` | https://forseti.life/jobhunter/settings/credentials |
| 403 | job_hunter | job_hunter.dashboard | `/jobhunter` | https://forseti.life/jobhunter |
| 403 | job_hunter | job_hunter.deadlines | `/jobhunter/deadlines` | https://forseti.life/jobhunter/deadlines |
| 403 | job_hunter | job_hunter.documentation | `/jobhunter/documentation` | https://forseti.life/jobhunter/documentation |
| 403 | job_hunter | job_hunter.documentation.architecture | `/jobhunter/documentation/architecture` | https://forseti.life/jobhunter/documentation/architecture |
| 403 | job_hunter | job_hunter.documentation.faq | `/jobhunter/documentation/faq` | https://forseti.life/jobhunter/documentation/faq |
| 403 | job_hunter | job_hunter.documentation.google_jobs | `/jobhunter/documentation/google-jobs-integration` | https://forseti.life/jobhunter/documentation/google-jobs-integration |
| 403 | job_hunter | job_hunter.documentation.process_flow | `/jobhunter/documentation/process-flow` | https://forseti.life/jobhunter/documentation/process-flow |
| 403 | job_hunter | job_hunter.documentation.readme | `/jobhunter/documentation/readme` | https://forseti.life/jobhunter/documentation/readme |
| 403 | job_hunter | job_hunter.download_base_resume_pdf | `/jobhunter/resume/pdf` | https://forseti.life/jobhunter/resume/pdf |
| 403 | job_hunter | job_hunter.download_pdf_by_id | `/jobhunter/resume/pdf/{pdf_id}` | https://forseti.life/jobhunter/resume/pdf/1 |
| 403 | job_hunter | job_hunter.download_tailored_resume_pdf | `/jobhunter/jobs/{job_id}/resume/pdf` | https://forseti.life/jobhunter/jobs/1/resume/pdf |
| 403 | job_hunter | job_hunter.edit | `/jobhunter/applications/{job_application}/edit` | https://forseti.life/jobhunter/applications/test/edit |
| 403 | job_hunter | job_hunter.generate_tailored_resume_pdf | `/jobhunter/jobs/{job_id}/resume/generate` | https://forseti.life/jobhunter/jobs/1/resume/generate |
| 403 | job_hunter | job_hunter.generate_tailored_resume_pdf_redirect | `/jobhunter/jobs/{job_id}/resume/generate/return` | https://forseti.life/jobhunter/jobs/1/resume/generate/return |
| 403 | job_hunter | job_hunter.google_jobs_home | `/jobhunter/googlejobsintegration` | https://forseti.life/jobhunter/googlejobsintegration |
| 403 | job_hunter | job_hunter.google_jobs_job_detail | `/jobhunter/googlejobsintegration/job/{job_id}` | https://forseti.life/jobhunter/googlejobsintegration/job/1 |
| 403 | job_hunter | job_hunter.google_jobs_list | `/jobhunter/googlejobsintegration/jobs-list` | https://forseti.life/jobhunter/googlejobsintegration/jobs-list |

(Truncated: 83 rows)
