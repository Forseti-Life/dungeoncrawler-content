- Agent: dev-forseti
- Status: pending
- Release: 20260410-forseti-release-c
- command: |
    Fix CSRF token delivery bug in interview-prep AI tips fetch.

    ## Problem
    `interview-prep-page.html.twig` sends the CSRF token in the POST body:
        body: 'form_token=' + encodeURIComponent(csrfToken)

    The route `job_hunter.interview_prep_ai_tips` (POST /jobhunter/interview-prep/{job_id}/ai-tips)
    uses `_csrf_token: 'TRUE'`. Drupal's `CsrfAccessCheck::access()` reads only
    `$request->query->get('token')` — it never inspects the POST body. Every
    AI tips fetch will return 403 Access Denied.

    ## File to fix
    sites/forseti/web/modules/custom/job_hunter/templates/interview-prep-page.html.twig
    (the JS fetch block, lines ~73–90)

    ## Fix pattern
    Option A (recommended — use Twig path() which auto-appends ?token= via RouteProcessorCsrf):

    In the PHP controller, pass the full CSRF-bearing URL to the template:
        $ai_tips_url = Url::fromRoute('job_hunter.interview_prep_ai_tips', ['job_id' => $job_id])->toString();
        // RouteProcessorCsrf will append ?token=<correct_token> automatically.
        // Pass $ai_tips_url to template as '#ai_tips_url'.

    In the Twig template, use it directly as the fetch URL:
        var aiTipsUrl = {{ ai_tips_url|json_encode|raw }};  // already has ?token=
        fetch(aiTipsUrl, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          // no body token — CSRF is in the URL
        })

    Option B (inline fix — keep current template structure, fix delivery channel):
    Change the fetch URL to include the token:
        var aiTipsUrl = {{ ai_tips_url|json_encode|raw }};
        var csrfToken = {{ csrf_token(ai_tips_url)|json_encode|raw }};
        var aiTipsUrlWithToken = aiTipsUrl + '?token=' + encodeURIComponent(csrfToken);
        fetch(aiTipsUrlWithToken, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          // REMOVE body: 'form_token=...' line
        })
    Note for Option B: ai_tips_url must be the bare path (e.g., /jobhunter/interview-prep/5/ai-tips)
    so the csrf_token() seed matches what CsrfAccessCheck validates against ($request->getRequestUri()).

    ## Acceptance criteria
    - AC-1: A logged-in user clicking "Get AI Interview Tips" on /jobhunter/interview-prep/{job_id}
      receives a 200 response (not 403) and sees AI tips rendered.
    - AC-2: The fetch URL in the browser network tab contains `?token=` as a query parameter.
    - AC-3: No `form_token` key appears in the POST body of the ai-tips request.

    ## Do NOT fix in this item
    - Finding 2 (dead form_token field in forseti-conversations.html.twig) — low priority, separate cleanup
    - Finding 3 (RELEASE_CYCLE_CONTROL_FILE_LEGACY) — low priority

    ## KB reference
    knowledgebase/lessons/20260227-jobhunter-e2e-csrf-token-empty-save-job.md
    Pattern: CsrfAccessCheck reads query string ?token= only. Never deliver via POST body.
