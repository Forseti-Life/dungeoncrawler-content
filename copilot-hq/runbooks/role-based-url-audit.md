# Role-based URL audit (pre-release localhost, post-release production)

This runbook defines a minimal protocol for **URL validation by role** (access verification + error checking) using the existing audit scripts.

## Scope

- Default target: **local environments only** (e.g. `http://localhost`) for pre-release.
- Post-release target: production is allowed **only** when explicitly enabled (see below).
## Production post-release audit (explicit)

Production audits are blocked by default. To run post-release QA against production, you must explicitly set `ALLOW_PROD_QA=1`.

Forseti:

```bash
ALLOW_PROD_QA=1 FORSETI_BASE_URL=https://forseti.life ./scripts/site-audit-run.sh forseti-life
```

Dungeoncrawler:

```bash
ALLOW_PROD_QA=1 DUNGEONCRAWLER_BASE_URL=https://dungeoncrawler.forseti.life ./scripts/site-audit-run.sh dungeoncrawler
```

If issues are found, normal dispatch rules apply (Dev inbox items are created). CEO should be notified as FYI; this is not a blocker to continuing fixes.

- What we validate:
  - Broken links / missing assets: 404/410/etc.
  - Access control: 401/403/405 differences by role.
  - Unexpected redirects (e.g. role should land on page but gets redirected).

## Roles

Recommended role matrix (adjust per site):

- `anon` (no auth)
- `authenticated`
- `editor`
- `admin`

## How to supply auth

Both scripts accept repeatable `--header` values. For cookie-based sessions, pass a `Cookie:` header.

Examples of obtaining a cookie locally:

- Use your browser to log into the local site as the intended role.
- Copy the session cookie value from DevTools (Application/Storage → Cookies).

Drupal-only (local) helper (admin example, no browser):

```bash
cd /home/keithaumiller/forseti.life/sites/forseti
uli=$(vendor/bin/drush -r web --uri=http://localhost uli --uid=1 --no-browser)
curl -sS -L -c /tmp/forseti_admin.cookies "$uli" -o /dev/null

# Extract the first Drupal session cookie as a Cookie header value.
export FORSETI_COOKIE_ADMIN=$(awk 'NF>=7 && $6 ~ /^(SESS|SSESS)/ {print $6"="$7; exit}' /tmp/forseti_admin.cookies)
```

**Do not commit cookies or paste them into tracked files.** Prefer exporting them in your shell for a single command.

### Automated role matrix (recommended)

The scheduled QA audit runner can execute a **role matrix** for a site and validate results against the site’s expected permissions.

Site-owned config files:
- `org-chart/sites/forseti.life/qa-permissions.json`
- `org-chart/sites/dungeoncrawler/qa-permissions.json`

Cookie env var convention (examples):
- Forseti: `FORSETI_COOKIE_AUTHENTICATED`, `FORSETI_COOKIE_EDITOR`, `FORSETI_COOKIE_ADMIN`
- Dungeoncrawler: `DUNGEONCRAWLER_COOKIE_AUTHENTICATED`, `DUNGEONCRAWLER_COOKIE_EDITOR`, `DUNGEONCRAWLER_COOKIE_ADMIN`

Example (Forseti):

```bash
export FORSETI_COOKIE_AUTHENTICATED='SSESS...=...'
export FORSETI_COOKIE_EDITOR='SSESS...=...'
export FORSETI_COOKIE_ADMIN='SSESS...=...'

FORSETI_BASE_URL=http://localhost ./scripts/site-audit-run.sh forseti-life
```

Outputs:
- Per-role artifacts: `sessions/qa-*/artifacts/auto-site-audit/<ts>/roles/<role>/...`
- Permission validation report: `permissions-validation.md` / `permissions-validation.json`

Additional validation behavior:
- The QA runner also builds a **union** of all discovered URLs (crawl + custom routes, across roles)
  and probes that full set as each configured role. This ensures any newly discovered URL is
  included in the per-role permission expectation validation.

## Canonical issues (GitHub) — prevent inbox floods

HQ can optionally use **GitHub Issues as the canonical issue registry** for recurring QA findings.

- Script: `scripts/github-issues-upsert.py`
- Behavior (Option B):
  - Create/upsert a stable GitHub Issue keyed by a deterministic HQ key (no timestamps).
  - Create an agent inbox item only when the GitHub issue is newly created.
  - Subsequent audit runs update the existing GitHub issue and do not create new inbox items.

Required environment variables:
- `GITHUB_TOKEN`
- `GITHUB_REPO` (format: `owner/repo`) — optional when using `forseti.life` as canonical authority (auto-derived from `/home/keithaumiller/forseti.life` origin)
- Optional: `GITHUB_API_URL` (default `https://api.github.com`)

Token fallback:
- If `GITHUB_TOKEN` is not set, HQ will attempt to reuse the GitHub token already configured in Drupal via the dungeoncrawler tester module (`dungeoncrawler_tester.settings` → `github_token`) using `forseti.life/sites/dungeoncrawler/vendor/bin/drush`.

## Crawl (linked URLs)

Anonymous:

```bash
python3 scripts/site-full-audit.py \
  --base-url http://localhost \
  --out-prefix tmp/site-audit/forseti/anon/crawl
```

Authenticated (example cookie):

```bash
python3 scripts/site-full-audit.py \
  --base-url http://localhost \
  --header 'Cookie: SSESSxxxxxxxxxxxxxxxx=yyyyyyyyyyyyyyyy' \
  --out-prefix tmp/site-audit/forseti/authenticated/crawl
```

Outputs:

- `*.json` raw results
- `*.csv` spreadsheet-friendly
- `*.md` summary with error rows

## Drupal custom routes audit

This probes routes defined in `modules/custom/**.routing.yml`.

Anonymous:

```bash
python3 scripts/drupal-custom-routes-audit.py \
  --drupal-web-root /var/www/html/web \
  --base-url http://localhost \
  --out tmp/site-audit/forseti/anon/routes.json
```

Authenticated:

```bash
python3 scripts/drupal-custom-routes-audit.py \
  --drupal-web-root /var/www/html/web \
  --base-url http://localhost \
  --header 'Cookie: SSESSxxxxxxxxxxxxxxxx=yyyyyyyyyyyyyyyy' \
  --out tmp/site-audit/forseti/authenticated/routes.json
```

## Interpreting results (dispatch rules)

- **404/410** on pages/assets expected to exist → dev defect.
- **500/502/503** → dev defect (server error).
- **401/403**:
  - If it’s supposed to be public, dev defect.
  - If it’s supposed to be restricted, compare across roles:
    - `anon` gets 403 but `authenticated` gets 200 → expected ACL.
    - `admin` gets 403 on an admin route → likely defect or environment misconfig.

## Next step (optional)

Once role runs exist, the QA seat can dispatch **deltas**:

- routes/pages that are 2xx for one role and 4xx for another
- routes/pages that unexpectedly change status between runs
