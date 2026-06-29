No test plan yet. Writing it now:

- Status: done
- Summary: Wrote `features/dc-cr-environment-terrain/03-test-plan.md` with 31 TCs (TC-ENV-01–31) covering: all 9 terrain types (bog/ice/snow/sand/rubble/undergrowth/slope/narrow/uneven), environmental damage categories (minor/moderate/major/massive with tier ordering), temperature effects (6 tiers: mild/severe/extreme cold/heat), collapse and burial mechanics (avalanche Reflex save, burial restrained+suffocation, rescue digging rate, collapse non-spread rule), wind effects (Perception penalty, ranged attack penalty/impossible, flying terrain, ground Athletics check with size penalties), and underwater rules (clear vs murky visibility, swim-against-current terrain, end-of-turn displacement). Edge cases: terrain type independent stacking, flat-footed stacking on steep slope. Failure modes: burial suffocation Fortitude advance, wind ranged-impossible vs penalized. Two PM notes raised: dc-cr-conditions dependency for flat-footed/restrained/suffocation TCs, and missing current-speed threshold for TC-ENV-26. Feature marked `ready`, PM notified. Committed at `01716e26a`.

## Next actions
- Process `20260407-153728-testgen-dc-cr-hazards`
- Continue batch: dc-cr-rest-watch-starvation, dc-cr-treasure-by-level, dc-cr-xp-award-system, dc-cr-magic-ch11, dc-cr-decipher-identify-learn, dc-cr-dwarf-ancestry, dc-cr-economy, dc-cr-human-ancestry, dc-cr-rune-system, dc-cr-snares
- APG/GMG batch (16 items) follows

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 7
- Rationale: Environment and terrain rules are core to exploration and encounter design; this plan surfaces the dc-cr-conditions dependency and flags the swim-current threshold gap for BA before implementation begins.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-153728-testgen-dc-cr-environment-terrain
- Generated: 2026-04-07T17:41:50+00:00
