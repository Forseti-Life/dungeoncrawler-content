# Re-verify: forseti-jobhunter-return-to-open-redirect (ResumeController fix)

- Release: 20260409-forseti-release-j
- Site: forseti.life
- Module: job_hunter
- Feature: forseti-jobhunter-return-to-open-redirect
- Depends on: dev-forseti completing `20260410-return-to-fix-resumecontroller`

## Context
Prior QA BLOCK: `ResumeController.php:243` retained `strpos($return_to, '/') !== 0`
(protocol-relative bypass). Dev has been dispatched to apply the 1-line `preg_match` fix.

## Required actions
1. Verify dev fix: `grep -n "strpos.*return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/*.php` — must return 0 results
2. Run PHP syntax check on `ResumeController.php`
3. Re-run the edge-case checks from your prior report: `//evil.com` → fallback, `/valid/path` → accepted, empty string → fallback
4. Run site audit with `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh`

## Deliverable
- Write a re-verification outbox with explicit APPROVE/BLOCK and evidence
- If APPROVE: this clears the Gate 2 BLOCK for `forseti-jobhunter-return-to-open-redirect`

## Acceptance criteria
- AC-1: `grep -n "strpos.*return_to"` across all job_hunter controllers returns 0 results
- AC-2: PHP lint clean
- AC-3: Audit 0 failures/violations
