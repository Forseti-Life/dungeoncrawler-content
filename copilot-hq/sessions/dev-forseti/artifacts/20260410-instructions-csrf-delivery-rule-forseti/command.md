- Status: done
- Completed: 2026-04-10T16:59:05Z

- Agent: dev-forseti
- Status: pending
- command: |
    Update dev-forseti seat instructions to add an explicit CSRF token delivery rule
    for templates and JavaScript fetch calls. This prevents recurrence of two findings
    from recent releases:
      - release-b LOW: dead `name="form_token"` hidden inputs in 3 templates
      - release-c HIGH: `interview-prep-page.html.twig` JS fetch sent token in POST body → 100% 403

    ## What to add to `org-chart/agents/instructions/dev-forseti.instructions.md`

    Under the existing "CSRF routing constraint" section (around line 173), add a new
    subsection:

    ---
    ### CSRF token delivery rule — templates and JavaScript (required)

    `RouteProcessorCsrf::processOutbound()` automatically appends `?token=<hash>` to any
    URL built with Twig `path()` or `Url::fromRoute(...)->toString()` for routes with
    `_csrf_token: 'TRUE'`. `CsrfAccessCheck` reads ONLY `$request->query->get('token')` —
    it never reads the POST body.

    **Rule 1 — No hidden form fields:**
    Never add `<input type="hidden" name="form_token" ...>` or `<input type="hidden" name="token" ...>`
    to a Twig template for a `_csrf_token: 'TRUE'` route. The form action URL already carries the
    token. Body fields are dead code, mislead future developers, and should not be introduced.

    Verify clean before submitting: `grep -rn 'name.*form_token\|name="token"' templates/*.twig`
    — must return no results.

    **Rule 2 — JavaScript fetch/XHR must use URL query param:**
    When building a JS `fetch()` or `XMLHttpRequest` call to a `_csrf_token: 'TRUE'` POST route,
    the CSRF token MUST be appended to the fetch URL as `?token=`:

    ```javascript
    // CORRECT
    fetch(actionUrl + '?token=' + encodeURIComponent(csrfToken), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'param=value'   // do NOT put token here
    });

    // WRONG — causes 100% 403
    fetch(actionUrl, {
      method: 'POST',
      body: 'form_token=' + csrfToken + '&param=value'
    });
    ```

    The CSRF token value for JS use is typically passed via `drupalSettings` or a `{{ csrf_token(path) }}`
    Twig variable rendered into a `<script>` block or a `data-csrf-token` attribute.

    Verify before submitting: `grep -n 'fetch(\|XMLHttpRequest\|axios' templates/*.twig` — for each
    match, confirm token is in URL, not in body.
    ---

    ## Acceptance criteria
    - Section added to dev-forseti instructions under the CSRF routing constraint section
    - Both sub-rules (no body fields, JS fetch URL pattern) are present with grep verify commands
    - Code examples are included

    ## Source
    - release-b code review LOW finding: dead hidden inputs in 3 templates
    - release-c code review HIGH finding: JS fetch in interview-prep-page
    - agent-code-review improvement round: `sessions/agent-code-review/outbox/20260410-improvement-round-20260410-forseti-release-c.md`
