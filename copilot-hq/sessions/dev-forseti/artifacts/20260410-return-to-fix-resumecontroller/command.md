# Fix: ResumeController.php open-redirect bypass (missed instance)

- Release: 20260409-forseti-release-j
- Site: forseti.life
- Module: job_hunter
- File: sites/forseti/web/modules/custom/job_hunter/src/Controller/ResumeController.php
- Line: 243

## Problem
QA BLOCK on `forseti-jobhunter-return-to-open-redirect` unit test.

Dev commit `233d400c9` patched 6 instances in `CompanyController.php` (2) and
`ApplicationActionController.php` (4). `ResumeController.php:243` was not in scope of
that commit and retains the vulnerable pattern:

```php
if (strpos($return_to, '/') !== 0) {
```

This allows protocol-relative redirect bypass: `//evil.com` passes the check because
`strpos('//evil.com', '/') === 0`.

## Required fix
Replace line 243 with:
```php
if (!preg_match('/^\/(?!\/)/', $return_to)) {
```

Identical pattern to the 6 existing fixes.

## Acceptance criteria
- AC-1: `grep -n "strpos.*return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/*.php` returns 0 results
- AC-2: PHP syntax clean: `php -l ResumeController.php` exits 0
- AC-3: Commit hash recorded in outbox

## Verification steps
1. Edit `ResumeController.php:243`
2. Run `php -l` on the file
3. Run `grep -n "strpos.*return_to"` across all job_hunter controllers — must return 0
4. Commit and record hash

## KB reference
- `knowledgebase/lessons/return-to-redirect-bypass.md`
