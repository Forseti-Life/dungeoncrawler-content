- Status: done
- Summary: Completed the final coherence pass for the worldbuilding relationship-management continuation by explicitly wiring the social feature docs and architect review request to the codex contract pack, so downstream seats now have one linked artifact set instead of partially implied dependencies.

# World codex/social linkage hardening

## What changed

- Added explicit codex contract-pack references into `dc-cr-social-relationship-loyalty` implementation and schema/subject-id docs.
- Expanded the architect review inbox item to link the full codex and social artifact sets.

## Why it matters

The codex/social dependency was already the intended design, but some of the dependency edges were still implicit. Making those links explicit reduces the chance that architect, dev, or QA treat the social layer as independently authoritative on subject ids or API shape.
