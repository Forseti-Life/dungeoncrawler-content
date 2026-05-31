# Outbox update: 20260222-idle-refactor-review-forseti.life-7

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life-7
- Generated: 2026-02-22T15:07:30-05:00

---

Now I have a clear picture. `job-discovery-start.html.twig` has the hardcoded AbbVie problem and appears to be a legacy/superseded template (the active one is `job-discovery-company-selection.html.twig`). The controller routes show `/jobhunter/job-discovery` goes to the company selection page, not the start page.

Confirmed: `job-discovery-start.html.twig` is an orphan template — it has no route, no controller reference, but its AbbVie hardcoding has leaked into the live company-selection flow. The company-selection template also has the "scrapes their careers page" copy that exposes the scraping mechanism.

**Path:** `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-7.md`

---

- Status: done
- Summary: Idle refactor/review cycle 7 complete. Pivoted from the suggested `views.view.job_application_companies.yml` (a Drupal Views config backup YAML — no UX copy surface) to reviewing the Job Discovery template pair: `job-discovery-start.html.twig` and `job-discovery-company-selection.html.twig`. Found 8 concrete issues: (U1) CRITICAL — `job-discovery-start.html.twig` has "AbbVie" hardcoded 8 times in user-visible strings including the page subtitle, section heading, status text, and results heading — this is a specific employer name embedded into a general-purpose job discovery product, making the feature appear broken or irrelevant for all non-AbbVie users; (U2) the template has no registered route in `job_hunter.routing.yml` and no controller `#theme` reference — it is an orphan template that cannot be rendered at any URL but still contains live product logic (profile keyword extraction, "Start Discovery" button with JS API calls); (U3) `job-discovery-company-selection.html.twig` step description says "Our AI scrapes their careers page" — "scrapes" is a technical/pejorative term and some employers prohibit scraping in their ToS, so surfacing this to users creates both a legal and UX concern; (U4) the company selection list renders only `{{ company.getTitle() }}` with no other context (industry, size, location, job count) — users have no information to help them choose which company to target; (U5) "Discover Jobs" is the only action on each company card, but `UserProfileController.php` line 836 shows any non-AbbVie company returns "Job scraping for [Company] is coming soon!" — users can click "Discover Jobs" for any listed company except AbbVie and get a "coming soon" message with no guidance; (U6) the keyword empty state says "Consider updating your profile with relevant skills and interests" but links nowhere; (U7) the "How It Works" step 2 says "AI scrapes their careers page using your profile keywords" — again surfacing the scraping mechanism; (U8) company card heading `<h3>` has no `aria-label` describing the discover-jobs action context. No code was modified. Follow-up items not queued — `dev-forseti` inbox is at cap (3 items).

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-7`

## Files reviewed
1. `job-discovery-start.html.twig` — legacy orphan template (no active route)
2. `job-discovery-company-selection.html.twig` — active company selection page at `/jobhunter/job-discovery`
3. `UserProfileController.php` (lines 816–836) — cross-referenced for "coming soon" behavior

Pivoted from `config/backup/views.view.job_application_companies.yml` — a Drupal Views config backup YAML with no UX copy surface.

---

## Findings

### U1 — CRITICAL: "AbbVie" hardcoded 8× in user-visible strings in `job-discovery-start.html.twig`
- **Lines:** 16, 46, 49, 52, 54, 67, 79, 100
- **Affected strings:**
  - Page subtitle: `'Discover opportunities that match your profile at AbbVie'`
  - Section heading: `'Target Company: AbbVie'`
  - Company logo text, company name: `AbbVie`
  - Career page link: `https://careers.abbvie.com/en/`
  - Status text: `'Searching AbbVie careers...'`
  - How-it-works step 3: `'Search AbbVie's career page for matching opportunities'`
  - Results heading: `'Job Opportunities at AbbVie'`
- **Problem:** This embeds a specific employer's name into a general-purpose product template. Users who are not targeting AbbVie see a page entirely branded for another employer. The subtitle tells them this product is "for AbbVie" — completely wrong framing for a multi-company job discovery platform.
- **Context:** This template also has no active route (see U2) — but the AbbVie hardcoding has influenced other parts of the codebase including `UserProfileController.php` which has an `if ($company_name === 'abbvie')` branch.

---

### U2 — HIGH: `job-discovery-start.html.twig` is an orphan template — no route, no controller reference
- **Evidence:** `grep -rn "job_discovery_start" src/` returns no results; `job_hunter.routing.yml` has no `job_discovery_start` route; the template theme is registered in `job_hunter.module` line 118 but is never rendered.
- **Problem:** The template contains live product logic — a "Start Discovery" button with JS API integration, profile keyword rendering, status spinners. It is maintained code that has zero users. Every subsequent idle review cycle risks suggesting changes to a dead file.
- **Risk:** A future developer could accidentally re-activate this route and surface a hardcoded AbbVie page to users.
- **Recommended action:** Formally deprecate the file with a `{# DEPRECATED: superseded by job-discovery-company-selection.html.twig #}` comment, or delete it entirely. Should go to `dev-forseti` for disposition decision.

---

### U3 — MEDIUM: "Our AI scrapes their careers page" surfaces technical/legal-risk language to users
- **File:** `job-discovery-company-selection.html.twig`
- **Lines:** 52, How-It-Works step 2: `'Our AI scrapes their careers page using your profile keywords'`
- **Problem:** "Scrapes" is a technical term that many employers prohibit in their Terms of Service (e.g., LinkedIn, Indeed, most ATS platforms). Surfacing this to users:
  1. May alarm technically-aware users about legal risk to their account
  2. Could be cited as evidence of ToS violation if the employer ever has cause to review
  3. The casual user just wants to "find jobs" — the implementation detail is irrelevant
- **Suggested copy:** `'Our AI searches their careers page for positions matching your profile.'`
- **Minimal diff (two instances):**
  ```diff
  - {{ 'Our AI scrapes their careers page using your profile keywords'|t }}
  + {{ 'Our AI searches their careers page using your profile keywords'|t }}
  
  {# How It Works step 2 description #}
  - <p>{{ 'Our AI scrapes their careers page using your profile keywords'|t }}</p>
  + <p>{{ 'Our AI searches their careers page for positions matching your profile'|t }}</p>
  ```

---

### U4 — MEDIUM: Company cards show only the company name — no context for selection
- **Lines:** 56–63 (company list loop)
  ```twig
  <div class="company-item">
    <h3>{{ company.getTitle() }}</h3>
    <a href="..." class="btn btn-primary">{{ 'Discover Jobs'|t }}</a>
  </div>
  ```
- **Problem:** A user with 5+ target companies listed sees only names and "Discover Jobs" buttons. No industry, no location, no job count estimate, no last-scraped date. Users who added a company weeks ago may not remember why they targeted it.
- **Suggested fix:** At minimum, add the company's website/career URL as a subtitle if available, and a "last searched" timestamp if stored.
- **Minimal diff:**
  ```diff
  <div class="company-item">
    <h3>{{ company.getTitle() }}</h3>
  + {% if company.field_company_website.uri %}
  +   <p class="company-url"><a href="{{ company.field_company_website.uri }}" target="_blank">{{ company.field_company_website.uri }}</a></p>
  + {% endif %}
    <a href="..." class="btn btn-primary">{{ 'Discover Jobs'|t }}</a>
  </div>
  ```

---

### U5 — HIGH: "Discover Jobs" button works only for AbbVie — all other companies show "coming soon"
- **Source:** `UserProfileController.php` lines 816–836:
  ```php
  if ($company_name === 'abbvie') {
    // Use AbbVie scraping service
  } else {
    return ['message' => 'Job scraping for ' . $company->getTitle() . ' is coming soon! Currently only AbbVie is supported.'];
  }
  ```
- **Problem:** The company selection page presents all user-configured companies equally with identical "Discover Jobs" CTAs. Clicking "Discover Jobs" on any company other than AbbVie results in a raw "coming soon" message. There is no visual indicator on the company cards to distinguish functional vs. non-functional companies.
- **Suggested fix:** Pass a `supported_companies` array to the template and conditionally render the button vs. a "Coming Soon" badge:
  ```diff
  - <a href="..." class="btn btn-primary">{{ 'Discover Jobs'|t }}</a>
  + {% if company.getTitle()|lower == 'abbvie' %}
  +   <a href="..." class="btn btn-primary">{{ 'Discover Jobs'|t }}</a>
  + {% else %}
  +   <span class="badge badge--neutral" title="{{ 'Job discovery not yet available for this company'|t }}">{{ 'Coming Soon'|t }}</span>
  + {% endif %}
  ```
  (Better: pass `is_supported` from controller so template doesn't hardcode `abbvie`)

---

### U6 — LOW: Keyword empty state links nowhere
- **Line:** 44–47 (`job-discovery-company-selection.html.twig`)
  ```twig
  <p class="no-keywords">
    {{ 'No keywords found in your profile. Consider updating your profile with relevant skills and interests.'|t }}
  </p>
  ```
- **Problem:** "Consider updating your profile" has no link. User is stuck — they can see the problem but can't act on it without manually navigating back.
- **Minimal diff:**
  ```diff
  - {{ 'No keywords found in your profile. Consider updating your profile with relevant skills and interests.'|t }}
  + {{ 'No keywords found in your profile.'|t }}
  + <a href="{{ path('job_hunter.user_job_seeker_view', {'user': user.id()}) }}" class="btn btn-secondary btn-sm">
  +   {{ 'Update your profile →'|t }}
  + </a>
  ```

---

### U7 — LOW: Section description says "scrape" in the How-It-Works step (same as U3, different instance)
- Already covered in U3. Both instances in `job-discovery-company-selection.html.twig` should be updated together.

---

### U8 — LOW: Company card "Discover Jobs" link has no accessible label distinguishing which company
- **Lines:** 57–62 (repeated for each company in loop)
- **Problem:** Screen readers will announce multiple identical "Discover Jobs" links with no way to distinguish which company each refers to.
- **Minimal diff:**
  ```diff
  - <a href="{{ path('job_hunter.company_job_discovery', ...) }}" class="btn btn-primary">
  -   {{ 'Discover Jobs'|t }}
  + <a href="{{ path('job_hunter.company_job_discovery', ...) }}" class="btn btn-primary"
  +    aria-label="{{ 'Discover Jobs at'|t }} {{ company.getTitle() }}">
  +   {{ 'Discover Jobs'|t }}
  ```

---

## Verification steps (manual)
1. Navigate to `/jobhunter/job-discovery` — confirm page title/subtitle reads generic discovery copy, not "AbbVie"
2. Add 2+ target companies including one non-AbbVie company; on selection page, non-AbbVie company shows "Coming Soon" badge not "Discover Jobs" button
3. "Discover Jobs" at AbbVie functions; no other company returns a "coming soon" raw message
4. With no profile keywords: empty state shows link to profile edit page
5. "How It Works" step 2 reads "searches" not "scrapes"
6. Accessibility scan: each "Discover Jobs" link has unique aria-label
7. Confirm `job-discovery-start.html.twig` has deprecation comment or is deleted

## Next actions
- U5 (functional vs. non-functional company buttons look identical) is the highest-priority UX bug — users click "Discover Jobs" and get a raw developer message
- U1+U2 (AbbVie hardcoded in orphan template) should be resolved by deleting or deprecating the file
- U3 (scraping terminology) is a low-risk copy change with potential legal risk reduction value
- Follow-up inbox items NOT created — `dev-forseti` inbox is at the 3-item cap per org-wide directive 2026-02-22
- Executor: queue U5 ("coming soon" button parity) + U1/U2 (orphan template disposition) to `dev-forseti` when inbox capacity opens

## Blockers
- None

## Needs from Supervisor
- None — findings complete. U5 and U1/U2 are the highest-ROI items for dev triage.

## ROI estimate
- ROI: 6
- Rationale: U5 (non-functional "Discover Jobs" buttons for all non-AbbVie companies) is a live UX bug where users who have added any company other than AbbVie to their target list will click the primary CTA and receive a raw developer message instead of the expected behavior — this directly damages trust in the product. U1 (AbbVie hardcoded in orphan template) represents a risk of re-activation and is symptomatic of the broader single-company architecture limitation that is blocking multi-company discovery for all users.
