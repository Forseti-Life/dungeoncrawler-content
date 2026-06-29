# Outbox update: 20260221-idle-requirements-improvement-thetruthperspective.org

- Agent: ba-thetruthperspective
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-thetruthperspective/inbox/20260221-idle-requirements-improvement-thetruthperspective.org
- Generated: 2026-02-21T23:50:47-05:00

---

Now I have enough context. Let me create the outbox directory and write the BA requirements artifact.

Per org instructions, outbox/artifact persistence is handled by the executor. My deliverable is the outbox update content below.

---

# Outbox update: 20260222-idle-requirements-improvement-thetruthperspective.org

- Agent: ba-thetruthperspective
- Source inbox: sessions/ba-thetruthperspective/inbox/20260221-idle-requirements-improvement-thetruthperspective.org
- Generated: 2026-02-22T04:48:43Z

---

- Status: done
- Summary: BA idle cycle complete for thetruthperspective.org. Building on the PM's prior idle feature request (Article Browsing and Reading, 2026-02-21), this artifact tightens the problem statement and acceptance criteria for that candidate feature, surfaces five stakeholder clarifying questions with recommendations, and proposes a delegation sequence. The site is currently dormant with no active modules; this feature is the minimum viable surface needed to establish a quality baseline and activate the PM/Dev/QA loop.

## Next actions
- Executor persists this artifact to `sessions/ba-thetruthperspective/artifacts/20260222-article-browsing-requirements.md`
- CEO answers the five clarifying questions (OQ-1 through OQ-5) before Dev work begins
- PM-thetruthperspective reviews and finalizes acceptance criteria (PM owns ship/no-ship)
- Once questions answered: delegate to Dev (implementation), then QA (test plan)

## Blockers
- None blocking this artifact.

## Needs from CEO
- Answers to OQ-1 (URL slug strategy), OQ-2 (Drupal version/install profile), OQ-3 (tag filtering behavior), OQ-4 (unpublished article HTTP response code), OQ-5 (teaser length/format) — all detailed in artifact below.

---

## Requirements Artifact

**Intended path:** `sessions/ba-thetruthperspective/artifacts/20260222-article-browsing-requirements.md`

---

### Feature: Article Browsing and Reading — thetruthperspective.org

- Date: 2026-02-22
- Author: ba-thetruthperspective
- Site: thetruthperspective.org
- Proposed module: thetruthperspective_content
- PM feature request reference: `sessions/pm-thetruthperspective/outbox/20260221-idle-feature-request-thetruthperspective.org.md`

---

#### Tightened Problem Statement

thetruthperspective.org is a Drupal-based site with no published content types and no public-facing pages. Anonymous visitors have nothing to read or browse. The team has no quality scoreboard baseline and no live user journey to test against. The minimum required intervention is: one Drupal content type (`article`), a paginated listing view at `/articles`, and a detail page at `/articles/[slug]`. This unblocks the PM/Dev/QA loop, establishes acceptance criteria to verify against, and gives the site its first measurable user journey.

**Non-goals for this feature (v1):**
- User registration, login, or commenting
- Search or filtering beyond optional tag taxonomy display
- Paid/subscriber-only content
- Social sharing widgets or newsletter integration

**Definitions:**
- `article`: a Drupal node of content type `article` with at minimum title, body, and published date
- `published`: Drupal node status = 1 (visible to anonymous users)
- `unpublished`: Drupal node status = 0 (not visible to anonymous users)
- `slug`: the URL-safe path segment identifying a single article (e.g. `/articles/my-article-title`)

---

#### Acceptance Criteria — Happy Path

| # | Criterion | Verification |
|---|-----------|-------------|
| AC-1 | Content type `article` exists with fields: Title (required, plain text), Body (required, rich text / text_long), Published Date (required, date field), Author Byline (optional, plain text), Tags (optional, taxonomy term reference to vocabulary `tags`) | `drush cex`; admin UI at `/admin/structure/types/manage/article/fields` |
| AC-2 | Anonymous user can browse `/articles` and sees paginated list (≤10 per page) of published articles showing: title (linked), published date, body teaser (≤150 chars) | Load `/articles` as anonymous; confirm only published nodes appear |
| AC-3 | Anonymous user can load `/articles/[slug]` and sees: title, full body, published date, author byline (if set), tags (if set) | Load a known article URL as anonymous |
| AC-4 | Listing at `/articles` is ordered by published date descending (newest first) | Create two articles with different dates; verify order |
| AC-5 | Admin user can create, edit, publish, and unpublish articles via `/admin/content` | Log in as admin; create and publish article; verify it appears at `/articles` |

#### Acceptance Criteria — Failure Modes

| # | Criterion | Verification |
|---|-----------|-------------|
| AC-6 | Unpublished article URL returns HTTP 403 for anonymous users (not 200, not 404) — *see OQ-4* | Publish then unpublish article; load URL as anonymous; verify 403 |
| AC-7 | Non-existent article URL returns HTTP 404 | Load `/articles/does-not-exist`; verify 404 |
| AC-8 | Empty listing (no published articles) renders message "No articles have been published yet." — not blank, not PHP error | Remove all published articles; load `/articles` |
| AC-9 | Article with no tags renders correctly on detail page (no error, no empty tag block) | Create article without tags; load detail page |
| AC-10 | Article title >120 chars does not break listing page layout | Create article with 150-char title; load `/articles` |

---

#### Clarifying Questions for Stakeholders

**OQ-1 — URL slug strategy:** Should article slugs be auto-generated from the title via Pathauto (e.g. `/articles/[node:title]`), or will editors set slugs manually? What is the rule for long titles (truncation length)?
- *Why it matters:* Must be decided before first articles are created to avoid URL migration later.
- *Recommendation:* Pathauto with `[node:title]` pattern, 100-char max, auto-update disabled after first publish.

**OQ-2 — Drupal version and install profile:** What Drupal version and install profile is thetruthperspective.org running? Does the `article` content type already exist from the standard install profile?
- *Why it matters:* Drupal standard install includes an `article` content type; if it exists, Dev scope shrinks to adding fields and configuring the view.

**OQ-3 — Tag filtering on listing page:** Should clicking a tag link on an article detail page filter the `/articles` listing by that tag, or is tag display read-only in v1?
- *Why it matters:* Tag filtering requires a contextual filter on the view; changes scope of AC-2 and AC-5.
- *Recommendation:* Read-only display of tags in v1; filtering is a v2 enhancement.

**OQ-4 — Unpublished article HTTP response:** Should an unpublished article URL return HTTP 403 (forbidden, article exists but access denied) or HTTP 404 (not found, article existence obscured) for anonymous users?
- *Why it matters:* 403 is Drupal default and transparent to editors; 404 obscures existence for security.
- *Recommendation:* 403 (Drupal default behavior for unpublished nodes).

**OQ-5 — Teaser length and format:** Should the listing page teaser be a plain-text auto-truncation of the body (150 chars), or should editors set a custom summary via Drupal's built-in Summary sub-field on the Body field?
- *Why it matters:* Custom summary gives editorial control over listing copy; auto-truncation is lower maintenance.
- *Recommendation:* Enable Summary sub-field on Body; fall back to auto-truncation at 150 chars if summary is blank.

---

#### Suggested Next Delegation

1. **CEO** — Answer OQ-1 through OQ-5 before Dev begins.
2. **PM-thetruthperspective** — Review and approve final acceptance criteria (PM owns ship/no-ship).
3. **Dev** (dev-thetruthperspective or shared dev-forseti) — Implement content type, fields, Pathauto config, Views listing page, permissions, and empty-state message. Reference forseti.life job_hunter module as a structural pattern.
4. **QA** — Write test plan covering AC-1 through AC-10; execute after Dev marks feature complete.
