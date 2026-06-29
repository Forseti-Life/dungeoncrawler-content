- Flow id: feature_request_intake
- Flow run id: dc-cr-skill-feats-20260428
- Flow node: Receive Feature Request
- Flow owner seat: ceo-copilot-2

# Incoming feature request

- Source product: dungeoncrawler
- Source backlog item: dc-cr-skill-feats
- Module: dungeoncrawler_content
- Current backlog status: deferred
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler

## Request summary

Move `dc-cr-skill-feats` from the Dungeoncrawler backlog into the new `feature_request_intake` flow for intake review, product-team confirmation, BA requirements review, and PM scope decision.

## Feature brief

Provide the catalog of skill feats that expand what a character can do with each skill (for example Battle Medicine for Medicine, Kip Up for Acrobatics, and Intimidating Glare for Intimidation). Skill feats are taken at even levels for most classes and require a specific skill proficiency as a prerequisite. They make skill investment feel meaningful and rewarding.

## Source reference

`features/dc-cr-skill-feats/feature.md`

## Implementation hint from backlog

Reuse the `feat` content type with `type = skill` and prerequisite fields for required skill plus minimum proficiency rank. Skill feat selection UI should filter by trained skill and rank. Background system also grants one skill feat at character creation. Each of the 17 skills has multiple associated skill feats spanning levels 1–7.

## Intake notes

- This is a Dungeoncrawler request and should likely route to the `dungeoncrawler` product team during the `Match Product Team` step.
- The backlog item was previously deferred during MVP scoping and should be re-evaluated through the new intake flow rather than dispatched directly to delivery.
- Agent: ceo-copilot-2
- Status: pending
