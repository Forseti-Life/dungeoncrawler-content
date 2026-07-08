# Intentional deltas from the private source module

This public candidate is a curated extract, not a raw copy of the live production module.

## Included sanitization changes

1. Replaced Forseti-branded prompt text with neutral public defaults.
2. Renamed Forseti-branded public chat and conversation routes to generic `ai_conversation` routes.
3. Renamed Forseti-branded theme hooks and templates to generic module names.
4. Removed the node-10 helper endpoint from the public candidate.
5. Removed job-hunter-specific bootstrap and saved-job helper behavior from the public candidate.
6. Replaced private-site docs with public package documentation.
7. Added standalone repo metadata (`composer.json`, `.env.example`, CI workflow).

## Still outside this extract

1. External confirmation that previously exposed AWS credentials were rotated.
2. Full history scrub / clean-history publication work.
3. Final public remote creation and release tagging.
