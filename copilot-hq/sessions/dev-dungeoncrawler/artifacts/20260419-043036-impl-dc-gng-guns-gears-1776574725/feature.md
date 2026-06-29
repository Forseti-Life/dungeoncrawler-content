# Feature Brief: Guns and Gears

- Work item id: dc-gng-guns-gears
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Guns and Gears
- Depends on: dc-cr-equipment-ch06, dc-cr-encounter-rules

## Goal

Implement Guns and Gears content — Gunslinger and Inventor classes, firearm and combination weapon mechanics, reloading actions, misfire/critical misfire rules, and the Construct Companion system. Covers 30 requirements across `gng/ch01–ch05`.

## Source reference

> "Guns and Gears introduces the gunslinger and inventor classes along with a host of new firearms, gadgets, and clockwork companions." (Guns and Gears — Introduction)

## Implementation hint

**Gunslinger class**: `class_key = 'gunslinger'`, Way subclass (Drifter/Vanguard/Drifter/Sniper/Pistolero/Reloading), Singular Expertise (proficiency in firearms and crossbows ahead of schedule), Gunslinger's Aim, Pistol Twirl, Reload actions (1-action Interact). **Firearm mechanics**: new weapon trait `reload N` (N actions to reload); `misfire` trait (crit fail jams weapon, requires 3-action Clear a Jam); critical specialization varies by firearm group. **Inventor class**: `class_key = 'inventor'`, Innovation subclass (Construct/Weapon/Armor), Overdrive, Explode, and unstable actions (potentially deals self-damage on crit fail). **Construct Companion**: content type extension of animal companion with construct traits, no need for daily bonding, different advancement track. DB sections are baseline/integration placeholders. Depends on `dc-cr-equipment-system` (weapon schema), `dc-cr-character-class`, `dc-cr-character-creation`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character class selection and innovation choices require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/weapon routes require `_csrf_request_header_mode: TRUE`
- Input validation: Way/Innovation subclass enums validated server-side; reload and misfire state computed server-side, not client-asserted
- PII/logging constraints: no PII logged; character id + action type + weapon id only
