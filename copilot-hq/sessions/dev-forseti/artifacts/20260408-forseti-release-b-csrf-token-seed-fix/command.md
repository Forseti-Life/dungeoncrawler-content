- Agent: dev-forseti
- Status: pending
- Release: 20260408-forseti-release-b
- command: |
    Fix CSRF token seed mismatch on job_hunter.toggle_job_applied route (FR-RB-01, MEDIUM).

    ## Problem
    The `my-jobs.html.twig` form action passes `?token={{ job.apply_csrf_token }}`.
    The controller generates `apply_csrf_token` using seed `'job_apply_{job_id}'`:
      `\Drupal::csrfToken()->get('job_apply_' . (int) $job->id)`

    Drupal's `CsrfAccessCheck` validates using the **route path** as seed
    (per `/core/lib/Drupal/Core/Access/CsrfAccessCheck.php` line 58-59):
      `$path = $this->generateRoutePath($route, $route_match->getRawParameters()->all());`
      `$this->csrfToken->validate($request->query->get('token', ''), $path)`

    For job_id=5, path = `jobhunter/my-jobs/5/applied`.
    Controller generates token with seed `job_apply_5`.
    These are different → every form submission returns 403.

    ## Files to fix
    - `sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
      Change ALL occurrences of:
        `\Drupal::csrfToken()->get('job_apply_' . (int) $job->id)`
      to use the correct route path seed:
        `\Drupal::csrfToken()->get('jobhunter/my-jobs/' . (int) $job->id . '/applied')`
      Affected lines (approximate): 1088, 1511, 1531, 1586, 1619.
      Also update any `$this->csrfTokenGenerator->get(...)` calls using the same `job_apply_` seed.

    ## Acceptance criteria
    - Submitting the applied-toggle form on /jobhunter/my-jobs returns a successful redirect (not 403)
    - CSRF protection still enforced (invalid/missing token → 403)
    - No `\Drupal::csrfToken()->get('job_apply_` pattern remains in codebase

    ## Verification
    - `grep -rn "job_apply_" sites/forseti/web/modules/custom/job_hunter/src/` → zero results
    - Manual test: load /jobhunter/my-jobs, submit applied-toggle, verify redirect and status update

    ## Context
    Found by agent-code-review in forseti-release-b (20260408) review of commit 1f84c8539.
