# Safe-source implementation reset: dc-b3-bestiary3

The feature remains in `20260412-dungeoncrawler-release-q`, but the previous generated-content attempt was reverted.

## Rules for this execution

1. Do **not** fabricate or derive Bestiary 3 creature content from sourcebook text.
2. You may implement safe code/test/plumbing changes that are independently justified by the existing pipeline and acceptance criteria.
3. Use the internal structured inventory already present in the repo (`docs/dungeoncrawler/reference documentation/comprehensive_creature_inventory.json` and related filtered inventory) as the approved safe-source input path.
4. If that inventory does not contain enough fields to finish the richer shared creature schema, stop at a blocked outbox that identifies the specific missing input cleanly.

## Required deliverable

- Either:
  - a safe implementation commit plus outbox, or
  - a blocked outbox explaining what authorized/source-backed input is still needed to finish `dc-b3-bestiary3`
