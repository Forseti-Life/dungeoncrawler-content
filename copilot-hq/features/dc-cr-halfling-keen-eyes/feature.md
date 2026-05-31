# Feature Brief: Halfling Ancestry Trait — Keen Eyes

- Work item id: dc-cr-halfling-keen-eyes
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7684–7983
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-l
- Created: 2026-04-12

## Goal

Implements the Halfling ancestry trait "Keen Eyes": all halflings gain a +2 circumstance bonus to the Seek action when looking for hidden or undetected creatures within 30 feet. Additionally, when a halfling targets a concealed opponent the DC of the required flat check is reduced to 3 (from 5), and for a hidden opponent the DC is reduced to 9 (from 11). This makes halflings reliably effective at locating and targeting elusive enemies, reinforcing their reputation as observant and hard to fool.

## Source reference

> Keen Eyes
> Your eyes are sharp, allowing you to make out small details about concealed or even invisible creatures that others might miss. You gain a +2 circumstance bonus when using the Seek action to find hidden or undetected creatures within 30 feet of you. When you target an opponent that is concealed from you or hidden from you, reduce the DC of the flat check to 3 for a concealed target or 9 for a hidden one.

## Implementation hint

- Apply +2 circumstance bonus to Seek rolls when the acting character is a halfling and the target is within 30 ft and hidden/undetected.
- Override flat check DC for targeting: concealed → DC 3 (default 5), hidden → DC 9 (default 11).
- This is an automatic ancestry trait, not a feat — should be granted to all halfling characters at creation without selection.
- Interacts with dc-cr-visibility-system (if stubbed) or the Seek action handler in dungeoncrawler_content.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: effect is passive and server-calculated inside existing combat/exploration actions only.
- CSRF expectations: all POST/PATCH action requests that use Seek or targeting flows require `_csrf_request_header_mode: TRUE`.
- Input validation: Seek bonus and flat-check reductions are computed from ancestry state and target visibility states; clients cannot self-assert reduced DCs.
- PII/logging constraints: no PII logged; log character_id, action_type, target_visibility_state, applied_modifier only.
