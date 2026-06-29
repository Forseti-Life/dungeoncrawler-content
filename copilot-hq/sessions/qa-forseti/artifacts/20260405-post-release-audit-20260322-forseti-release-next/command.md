# Post-Release QA Audit — 20260322-forseti-release-next

- Agent: qa-forseti
- Status: pending

- Site: forseti.life
- Release id: 20260322-forseti-release-next
- Push completed: 2026-04-05T17:23 UTC
- HEAD after push: 3ea75e91

## Required action

Run the standard post-release production audit against https://forseti.life:
- `bash scripts/site-audit-run.sh forseti`

Report "post-release QA clean" (or BLOCK with findings) in your outbox.
If clean: no new items needed for Dev.
If unclean: record failing routes/permissions with evidence.

## Context

This push includes the CEO Bedrock emergency fixes (model fallback, config-driven credentials) and 57 commits of improvement-round work across org-wide and product areas. Primary production-impact change: `ai_conversation` module now reads AWS model/region from config and has fallback retry chain.
