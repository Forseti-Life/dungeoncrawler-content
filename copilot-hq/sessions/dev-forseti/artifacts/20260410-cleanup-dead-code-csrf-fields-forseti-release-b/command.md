- Agent: dev-forseti
- Status: pending
- Release: 20260409-forseti-release-b (post-release cleanup)
- ROI: 15
- command: |
    Two cleanup tasks identified in the forseti-release-b post-release code review:

    ## Task 1: Remove dead-code CSRF hidden fields from 3 templates

    The following templates have hidden form fields that are dead code.
    `CsrfAccessCheck` reads `$request->query->get('token')` ONLY — it never reads
    POST body fields. `RouteProcessorCsrf` correctly appends `?token=` to the form
    action URL already. The hidden inputs add confusion for future developers who may
    assume they are required for CSRF to work.

    Files to fix:
    - `sites/forseti/web/modules/custom/job_hunter/templates/cover-letter-display.html.twig`
      → remove `<input type="hidden" name="token" value="{{ csrf_token(...) }}">`
    - `sites/forseti/web/modules/custom/job_hunter/templates/interview-prep-page.html.twig`
      → remove `<input type="hidden" name="form_token" value="{{ csrf_token(...) }}">`
    - `sites/forseti/web/modules/custom/job_hunter/templates/saved-searches-page.html.twig`
      → remove `<input type="hidden" name="form_token" value="{{ csrf_token(...) }}">`

    Acceptance criteria:
    - All three hidden token inputs are removed.
    - Form actions still work (CSRF token is in URL query string via RouteProcessorCsrf).
    - `drush cr` after change; verify cover letter, interview prep, and saved search
      forms submit successfully (no 403).

    ## Task 2: Document the intentional empty `hook_schema()` pattern in dev-forseti seat instructions

    `job_hunter_schema()` returns `[]` intentionally to prevent Drupal from auto-dropping
    data tables on module uninstall. This is correct but undocumented, causing review
    uncertainty each cycle.

    File to update:
    - `org-chart/agents/instructions/dev-forseti.instructions.md`

    Add a section (or note in the schema/install section if one exists) explaining:
    - `job_hunter_schema()` intentionally returns `[]`
    - New tables must be created via `_job_hunter_create_<table>_table()` helper called in `hook_install()`
    - New tables on existing installs must also have a `job_hunter_update_N()` hook
    - Verification: `grep -n '_create_.*_table\|hook_install' job_hunter.install`

    Acceptance criteria:
    - `dev-forseti.instructions.md` contains the above pattern in a findable section.
    - Future dev-forseti cycles can verify schema pairing without rediscovering the pattern.

    ## Verification
    - `grep -n 'name.*form_token\|name.*"token"' sites/forseti/web/modules/custom/job_hunter/templates/*.twig`
      should return 0 matches after Task 1.
    - `grep -n 'hook_schema\|intentionally\|hook_install' org-chart/agents/instructions/dev-forseti.instructions.md`
      should return a match for the pattern note after Task 2.
