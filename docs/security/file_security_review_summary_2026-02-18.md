# File-by-File Security Review Summary

Date: 2026-02-18T13:43:21.908744

- Total files listed: 162220
- Review rows written: 162220
- Errors: 0
- Files with >=1 heuristic concern: 18714

## Status Counts

- text: 138834
- unknown-textlike: 15356
- binary: 8018
- missing: 12
- error: 0

## Concern Hit Counts (heuristic)

- http_plaintext_url: 16187
- dangerous_exec: 1470
- innerhtml_injection: 507
- deserialize_risk: 424
- secret_token_key: 367
- unsafe_eval: 265
- hardcoded_password: 191
- superglobal_input: 79
- debug_verbose: 8
- csrf_disabled_route: 4

## Actionable First-Party Signal (dependency/framework paths excluded)

- Flagged files: 302
- Top concern categories:
	- http_plaintext_url: 139
	- hardcoded_password: 105
	- secret_token_key: 51
	- innerhtml_injection: 15
	- dangerous_exec: 8
	- deserialize_risk: 7
	- csrf_disabled_route: 4

## Highest-Priority Triage Targets

- `sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php`
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.module`
- `sites/forseti/web/modules/custom/jobhunter_tester/src/Controller/JobHunterTesterController.php`
- `sites/forseti/web/modules/custom/nfr/src/Controller/NFRValidationController.php`
- `sites/forseti/web/sites/default/settings.php`

Note: this scan is heuristic and intentionally high-recall; many `http_plaintext_url` matches are documentation/example URLs and need manual severity triage.

## Artifacts

- Full file list: `docs/security/full_file_list_2026-02-18.txt`
- Per-file findings CSV: `docs/security/file_security_review_2026-02-18.csv`
