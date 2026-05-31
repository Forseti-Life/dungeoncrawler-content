# Site URL Audit (QA)

## Goal
For each product/site, QA reviews **every in-scope page URL** and verifies:
- URL validity (no 404/500/unexpected redirects)
- Access control by role (anonymous vs authenticated vs admin)
- Expected UX (login redirects, access denied, admin-only areas)
- External link sanity (no obvious dead links)

## Inputs (must be provided or escalated)
- `BASE_URL` for the environment under test (local/staging/prod)
- Credentials or login method for each required role:
  - Anonymous (no login)
  - Authenticated basic user
  - Admin/editor (as applicable)

If any input is missing, file an escalation as `needs-info` to the supervising PM.

## URL inventory (what “every page” means)
Start with a concrete, reproducible inventory:
1. `GET $BASE_URL/robots.txt`
2. `GET $BASE_URL/sitemap.xml` (and/or Drupal sitemap module endpoints if enabled)
3. Click-through discovery from primary navigation (header/footer)
4. Product-specific entry points (document known routes in the report)

Include both:
- Public content URLs
- Authenticated/product UI routes
- Admin/report pages that are part of the product’s operating scope

## Verification checklist (per URL)
For each URL, record results for the required roles:
- **HTTP**: status, redirects, response time concerns
- **Access**: correct gating (redirect to login vs 403/Access denied)
- **Content**: page renders, no fatal errors, no obvious missing assets
- **Forms** (if present): submit/save does not crash; validation behaves plausibly

## Deliverable
Write a single report to the QA seat outbox using the template:
- `sessions/<qa-seat>/outbox/YYYYMMDD-<site>-url-audit.md`

The report must include:
- URL inventory method + count
- A prioritized issue list with reproducible steps
- A short “Recommendations” section (what to fix first and why)

## PM handoff
PM uses the QA report to:
1. Prioritize fixes by ROI
2. Create engineer inbox items with clear acceptance criteria
3. Track follow-ups until QA can re-verify
