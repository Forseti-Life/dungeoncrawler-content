# Feature: forseti-community-incident-report

- Group Order: 1
- Group: safety-tools
- Group Title: Safety Tools
- Group Sort: 1
- Status: done
- Website: forseti.life
- Module: community_incident_report (new)
- Release: 20260412-forseti-release-l
- Owner: pm-forseti
- Project: PROJ-006

## Summary

The AmISafe and Safety Calculator modules provide crime analytics and risk scoring, but all data comes from official crime databases (Philadelphia PD). Community members have no way to report safety observations (e.g., unsafe lighting, suspicious activity, blocked emergency access, neighborhood hazard) that may not appear in official crime records. This feature adds a community incident report form allowing authenticated users to submit safety observations. Submitted reports are stored in a new `community_incident` node type, are visible on a public `/community-reports` listing page, and are integrated into the AmISafe crime map as a distinct layer (toggleable, visually differentiated from official crime data). This closes the data-contribution gap and makes the safety platform community-managed, not just community-consumed — directly advancing the org mission.

## Goal

Allow authenticated Forseti users to submit safety observations so that community-sourced data supplements official crime data in the AmISafe platform.

## Acceptance criteria

- AC-1: New content type `community_incident` with fields: title, description (textarea), incident type (taxonomy: unsafe_lighting, suspicious_activity, hazard, other), location (address text + lat/lng), occurred_at (datetime), optional photo (image field), author (entity ref to user). Content type is created via `config/install/` in the new module.
- AC-2: An authenticated-only form at `/community/report` submits a new `community_incident` node; anonymous users are redirected to login. The form uses Drupal form API with CSRF protection (no custom controller needed — use `node_add` or a custom Form class).
- AC-3: A public listing page at `/community-reports` shows published `community_incident` nodes in reverse-chronological order; anonymous users may view but not create. Paged at 20 per page.
- AC-4: AmISafe crime map at `/amisafe/crime-map` gains a toggleable "Community Reports" layer showing community incident pins as a visually distinct marker type (different icon/color from official crime data). Layer is off by default but opt-in per session. No H3 aggregation required for community reports — pin-level display only.
- AC-5: Admin permission `submit community incident reports` controls access to the report form; `view community incident reports` controls listing page access (default: authenticated for submit, public for view).
- AC-6: Moderation — new community incidents are created with `status: 0` (unpublished); a simple admin view at `/admin/content/community-reports` lists pending and published reports with a one-click publish/unpublish toggle (Drupal core Views + bulk operations is acceptable).
- AC-7: A confirmation message is shown after successful submission: "Thank you — your report has been submitted and will appear after review." The report form clears after successful submit.

## Definition of done

- All 7 AC pass QA verification on production.
- `/community/report` returns 403 for anonymous, 200 for authenticated user.
- `/community-reports` returns 200 for anonymous.
- AmISafe map toggle for community layer works without JS errors (browser console clean).
- No regressions on existing `/amisafe` or `/safety-calculator` routes.

## Notes

- New module `community_incident_report` should live at `sites/forseti/web/modules/custom/community_incident_report/`.
- The AmISafe integration (AC-4) requires a small JS change in `amisafe/js/` to add a new layer source and toggle. BA should verify whether this is a passthrough request to the amisafe module owner or if community_incident_report can add the layer via a Drupal attach behavior.
- Do not store lat/lng as separate fields on first implementation — a plain address text field is sufficient; geocoding can be added in a later release.
- Photo field (AC-1) should be optional and have a max upload size of 5MB (configurable via module settings).

## Security acceptance criteria

- Authentication/permission surface: `/community/report` requires authenticated user with `submit community incident reports` permission; anonymous access returns 403. `/community-reports` is public read-only. Admin view `/admin/content/community-reports` requires `administer community incident reports` permission.
- CSRF expectations: Report submission form uses Drupal Form API with built-in CSRF token validation; no raw POST endpoints.
- Input validation requirements: All user-submitted fields are validated/sanitized via Drupal Form API validators; address text field is plain text (no HTML); description field is filtered_html or plain_text; image upload capped at 5MB with MIME type whitelist (jpg/png/gif/webp only).
- PII/logging constraints: Author uid is stored on node but not exposed in public listing beyond display name; IP addresses are not logged beyond Drupal watchdog defaults; photo EXIF metadata is stripped on upload.
