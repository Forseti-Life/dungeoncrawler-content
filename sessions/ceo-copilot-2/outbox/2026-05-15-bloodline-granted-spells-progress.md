- Status: in_progress
- Summary: Implemented the core sorcerer bloodline granted-spell subsystem in `dungeoncrawler-content` so supported bloodlines now expose canonical rank-aware granted spells for feat logic. Wired `bloodline-breadth` and `greater-bloodline` to consume that metadata, added unit-test coverage, and smoke-validated the new override behavior directly in PHP. Formal feat verification and CEO inbox closeout are still pending.

## What changed
- Expanded `CharacterManager::SORCERER_BLOODLINES` from a tradition-only map into canonical bloodline metadata with:
  - rank-aware `granted_spells`
  - `bloodline_spells`
  - `blood_magic`
  - genie subtype-specific granted spell variants
- Added `FeatEffectManager` support for:
  - `bloodline-breadth`
  - `greater-bloodline`
  - shared sorcerer bloodline metadata/subtype resolution helpers
  - normalization from source-style spell names to canonical repo spell ids
- Added unit tests covering:
  - imperial `bloodline-breadth`
  - genie subtype-aware `bloodline-breadth`
  - imperial `greater-bloodline`

## Verification
- `php -l` passes for all touched PHP files.
- Direct PHP smoke checks confirm:
  - imperial `bloodline-breadth` resolves `dimension_door` at rank 4
  - janni genie `bloodline-breadth` resolves `banishment` at rank 5
  - imperial `greater-bloodline` resolves `disintegrate` at rank 6
- Full PHPUnit execution was not available from the current environment entrypoint, so broader automated feat verification remains pending.

## Next actions
- Run the broader feat verification flow for `bloodline-breadth` and `greater-bloodline`.
- If verification passes, archive `sessions/ceo-copilot-2/inbox/2026-05-14-sorcerer-bloodline-granted-spells-subsystem.md`.

## Blockers
- No code blocker. Verification tooling in this shell does not expose a ready PHPUnit binary, so only direct smoke validation was performed here.
