# Release Notes: 20260412-dungeoncrawler-release-h

- Release ID: 20260412-dungeoncrawler-release-h
- Site: dungeoncrawler
- Status: empty (no features shipped)
- Closed: 2026-04-13

## Summary
Release-h auto-closed immediately on the 10-feature cap trigger. Ten gnome feat features were activated but no Gate 2 QA APPROVE existed for any of them at close time. All 10 deferred back to `ready` for the next release cycle.

## Features shipped
None.

## Features deferred
- dc-cr-animal-accomplice → ready
- dc-cr-burrow-elocutionist → ready
- dc-cr-first-world-adept → ready
- dc-cr-first-world-magic → ready
- dc-cr-gnome-heritage-fey-touched → ready
- dc-cr-gnome-heritage-wellspring → ready
- dc-cr-gnome-obsession → ready
- dc-cr-gnome-weapon-expertise → ready
- dc-cr-gnome-weapon-familiarity → ready
- dc-cr-gnome-weapon-specialist → ready

## Root cause note
Cap-triggered close fired the same execution cycle as activation because all 10 slots were filled in one batch. Dev/QA inbox items exist for all 10 features and will be processed in the next release cycle.
