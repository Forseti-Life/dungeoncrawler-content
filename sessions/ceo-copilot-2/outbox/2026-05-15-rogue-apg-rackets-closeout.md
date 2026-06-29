# Rogue APG rackets closeout

Closed the final two active CEO backlog items by shipping the shared rogue APG racket subsystem in `dungeoncrawler-content`.

## Shipped
- Expanded rogue racket metadata to include `eldritch-trickster-racket` and `mastermind-racket`.
- Updated rogue Step 4 creation flow so racket selection now drives rogue key-ability options, with AJAX rebuilds on class-feat changes.
- Added Eldritch Trickster's free spellcasting dedication chooser and persistence flow.
- Added Mastermind's extra knowledge-skill chooser and persistence flow.
- Mirrored Eldritch Trickster's selected dedication into canonical feat state for downstream consumers.
- Added runtime metadata in `FeatEffectManager` for both rackets, including Mastermind's Recall Knowledge combat-advantage rider.
- Added targeted unit coverage for both APG rackets.

## Verification
- Targeted live PHPUnit pass from `/var/www/html/dungeoncrawler`:
  - `EldritchTrickster`
  - `Mastermind`
  - `NimbleDodge`
  - `TrapFinder`
  - `TwinFeint`
  - `YoureNext`
- Result: **8 tests, 56 assertions**

## HQ updates
- Updated rogue class acceptance/test-plan docs for the new racket coverage.
- Updated APG class-expansion acceptance/test-plan docs to mark rogue racket work shipped.
- Updated `current-session-state.md` to show the May 14 feat-subsystem backlog fully closed.

## CEO outcome
- Archive:
  - `2026-05-14-eldritch-trickster-racket-subsystem.md`
  - `2026-05-14-mastermind-racket-subsystem.md`
- CEO inbox backlog from the May 14 Dungeoncrawler feat-subsystem sweep is now fully cleared.
