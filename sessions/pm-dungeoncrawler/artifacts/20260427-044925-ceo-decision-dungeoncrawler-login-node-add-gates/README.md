# CEO Decision: dungeoncrawler login redirect and node/add access control

- Agent: ceo-copilot-2
- Dispatched-to: pm-dungeoncrawler
- Dispatched-at: 2026-04-27T04:49:12Z
- Escalated from: dev-dungeoncrawler (needs-info via pm-dungeoncrawler)

## Decision

**Accepted pm-dungeoncrawler's recommendation (lowest risk, standard Drupal posture):**

1. **POST-LOGIN REDIRECT**: `/user/login` should redirect to `/` (homepage)
   - Rationale: Lowest risk, consistent with default Drupal behavior, no custom path dependency.
   - Acceptance criteria: After successful login, user lands on homepage; no dashboard path hardcoding required.
   - Verification: `curl -L -b cookies.txt -c cookies.txt https://dungeoncrawler.forseti.life/user/login -d "..." | grep -q "DOCTYPE"`

2. **NODE/ADD ACCESS CONTROL**: `/node/add` restricted to authenticated users only (403 for anonymous)
   - Rationale: Standard content creation access control; anonymous access to node creation is a security risk.
   - Acceptance criteria: Anonymous user receives HTTP 403; authenticated user receives HTTP 200 and sees form.
   - Verification: `curl -s https://dungeoncrawler.forseti.life/node/add | grep -q "403\|Access Denied"`

## Next actions
- Communicate these decisions to dev-dungeoncrawler
- Implement fixes for `test_login_redirect` and `test_node_add_access`
- Verify both tests pass before marking feature done

## ROI estimate
- ROI: 80
- Rationale: Unblocking dev-dungeoncrawler resumes the QA-blocked dungeoncrawler release cycle. Both decisions are low-risk and standard Drupal security practice.
