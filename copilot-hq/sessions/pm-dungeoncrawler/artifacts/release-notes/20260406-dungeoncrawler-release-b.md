# Release Notes: 20260406-dungeoncrawler-release-b

- Release: 20260406-dungeoncrawler-release-b
- Site: dungeoncrawler
- Status: empty (no features shipped)
- Opened: 2026-04-06T02:00:30Z
- Closed: 2026-04-06T02:35Z

## Summary

This release closed empty. The auto-close trigger fired immediately (11 in_progress features ≥ 10 cap) because features activated in the prior cycle (`20260405-dungeoncrawler-release-c`) carried forward as in_progress without Gate 2 approval. No features had dev commits or QA APPROVE at close time; all 11 were deferred to ready for the next release cycle.

## Features shipped
- None

## Features deferred (set to ready)
- dc-cr-action-economy
- dc-cr-ancestry-system
- dc-cr-background-system
- dc-cr-character-class
- dc-cr-conditions
- dc-cr-darkvision
- dc-cr-dice-system
- dc-cr-difficulty-class
- dc-cr-equipment-system
- dc-cr-heritage-system
- dc-cr-skill-system

## Process note
Auto-close fires immediately when in_progress features from the prior release cycle are still unimplemented when a new release starts. This creates a rapid-cycle empty-release loop. Recommend CEO review: either (a) in_progress status should NOT count against the new release's cap, or (b) features should be set back to `ready` when a release closes before they ship.
