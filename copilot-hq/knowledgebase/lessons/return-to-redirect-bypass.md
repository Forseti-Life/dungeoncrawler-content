# Lesson: return_to open redirect bypass via protocol-relative URLs

- Date: 2026-04-09
- Release: 20260409-forseti-release-j
- Discovered by: code-review (recurring LOW, releases h and prior)

## Problem

`strpos($return_to, '/') !== 0` is insufficient for validating redirect targets.
`//evil.com` starts with `/` and passes this check, but browsers treat it as a
protocol-relative URL and redirect to the external domain.

## Fix

Replace bare `strpos` with a regex negative lookahead:

```php
if (!preg_match('/^\/(?!\/)/', $return_to)) {
    $return_to = '/fallback/path';
}
```

This rejects: `//evil.com`, `http://evil.com`, `javascript:alert(1)`, empty string.
This accepts: `/path/to/page`, `/admin/...`, any single-slash-prefixed relative path.

## Files fixed (release-j)
- `job_hunter/src/Controller/CompanyController.php` — 2 instances
- `job_hunter/src/Controller/ApplicationActionController.php` — 4 instances
- `job_hunter/src/Controller/ResumeController.php` — 1 instance (line 243; missed in initial commit `233d400c9`, patched in follow-up commit `605d4230a`)

## Missed-instance lesson (2026-04-10)
Initial fix commit `233d400c9` patched 6 of 7 instances and missed `ResumeController.php:243`. This caused an extra QA BLOCK cycle and a CEO-level escalation. Root cause: dev searched by filename, not by pattern across all controllers.

**Required completeness check** (run before committing any security fix):
```bash
grep -rn "strpos.*return_to\|strpos.*\$return_to" sites/forseti/web/modules/custom/job_hunter/src/Controller/
```
Result must be 0 matches. If any match remains, fix it in the same commit.

## Prevention

Any new `return_to` / redirect parameter must use the preg_match pattern above.
Never use `strpos($url, '/') !== 0` alone as a redirect validator.
Always run the completeness grep above before committing — do not assume one-file fixes cover all instances.
