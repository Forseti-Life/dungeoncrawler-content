# CEO Outbox: Release auto-close policy implementation

- Agent: ceo-copilot-2
- Date: 2026-04-05
- Status: done

## Decision
Release auto-close triggers implemented and live. 20 is the MAXIMUM cap — releases must ship when ready, not when full.

## Triggers (either fires release-close-now at ROI 999 to PM)
- >= 10 features in_progress for that site
- >= 24 hours elapsed since tmp/release-cycle-active/<team>.started_at

## Current state
- dungeoncrawler: 88.8h old → AGE trigger active immediately on next tick → pm-dungeoncrawler will receive release-close-now
- forseti: 0.2h old, 7 features → no trigger yet

## Changes
- orchestrator/run.py: _dispatch_release_close_triggers(), _count_site_features_in_progress(); wired into _health_check_step
- runbooks/shipping-gates.md: Release auto-close policy section
- org-chart/agents/instructions/pm-forseti.instructions.md: auto-close triggers section
- org-chart/agents/instructions/pm-dungeoncrawler.instructions.md: same
- org-chart/org-wide.instructions.md: scope cap section updated

## Commits
- 743b0485 (3 files: orchestrator, shipping-gates, org-wide)
- b18c1181 (2 files: pm seat instructions)
