Now I have all 30 TCs. Let me build and add the suite entries.

- Status: done
- Summary: Activated the `dc-cr-feats-ch05` test suite into `qa-suites/products/dungeoncrawler/suite.json` with 30 test cases across 2 suites: `dc-cr-feats-ch05-phpunit` (29 TCs covering feat category system, repeatable feats, Assurance fixed-result + modifier suppression, Recognize Spell reaction/auto-ID/Crit outcomes, Trick Magic Item tradition gate/fallback DC/lockout, Battle Medicine tools gate/immunity/wounded-condition exclusion, Specialty Crafting bonus tiers, Virtuosic Performer bonus tiers, and edge cases) and `dc-cr-feats-ch05-role-url-audit` (TC-FEAT-30: ACL regression — no new routes, existing character creation/leveling forms retain ACL). Added 2 ACL rules to `qa-permissions.json`: `dc-cr-feats-ch05-char-creation-acl` and `dc-cr-feats-ch05-char-leveling-acl`. All 30 TCs tagged `feature_id: dc-cr-feats-ch05`; 15 TCs immediately activatable, 15 conditional on `dc-cr-general-feats`, `dc-cr-skill-feats`, `dc-cr-character-leveling`, or `dc-cr-equipment-system`. Suite validated clean (96 suites, 7 manifests OK). Commit: `1640cd76e`.

## Notes to PM (14 open questions flagged in test plan)
- **TC-FEAT-02**: Confirm general feat slot schedule stacking vs class-granted general feat slots.
- **TC-FEAT-05**: Confirm feat.level static vs computed from prerequisites.
- **TC-FEAT-06**: Confirm Armor/Weapon Proficiency repeatable progression model (linear tiers vs independent categories).
- **TC-FEAT-07**: Confirm proficiency bonus formula (level + rank modifier, no ability modifier).
- **TC-FEAT-10**: Confirm "aware of casting" trigger model (automatic event vs Perception check).
- **TC-FEAT-11**: Confirm tradition rank for auto-ID threshold (per-tradition skill vs highest).
- **TC-FEAT-12**: Confirm Crit Success bonus scope (spell instance vs spell type vs caster).
- **TC-FEAT-13**: Confirm false spell identity generation method for Crit Fail.
- **TC-FEAT-15**: Confirm Trick Magic Item DC formula (same as Assurance proficiency component?).
- **TC-FEAT-16 / TC-FEAT-20**: Confirm daily preparations event model for fixture teardown.
- **TC-FEAT-18**: Confirm DC/HP table is shared constant (not duplicated) between Battle Medicine and Treat Wounds.
- **TC-FEAT-21**: Confirm Treat Wounds has independent cooldown (not blocked by Battle Medicine immunity).
- **TC-FEAT-23/26**: Confirm specialty/performance type storage location (feat instance vs character field).
- **TC-FEAT-25**: Confirm GM-review flag surfaces as system field or out-of-band notification.

## Next actions
- Dev implements `dc-cr-feats-ch05` against these TCs
- PM resolves 14 open questions flagged above (non-blocking for dev start; needed before assertion logic is finalized)
- QA runs targeted verification on dev's implementation commit when dispatched

## Blockers
- None.

## ROI estimate
- ROI: 40
- Rationale: Suite activation unblocks dev implementation with 30 concrete test targets across all 14 AC items. The Assurance modifier-suppression pattern and Battle Medicine immunity logic are novel; clear TCs reduce rework risk significantly.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260411-235513-suite-activate-dc-cr-feats-ch05
- Generated: 2026-04-12T01:23:10+00:00
