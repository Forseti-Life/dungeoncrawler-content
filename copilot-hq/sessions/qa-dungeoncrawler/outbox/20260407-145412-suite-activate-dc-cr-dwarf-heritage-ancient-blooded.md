- Status: done
- Summary: Activated `dc-cr-dwarf-heritage-ancient-blooded-e2e` suite into `qa-suites/products/dungeoncrawler/suite.json` with 16 entries (TC-AB-001–TC-AB-015 + TC-AB-ACL-01, all tagged `feature_id: dc-cr-dwarf-heritage-ancient-blooded`). Coverage: heritage availability for Dwarf, reaction grant on selection, reaction prompt before magical save, +1 circumstance bonus application and same-turn persistence, bonus expiry end-of-turn, once-per-turn consumption, non-Dwarf exclusion (UI + server-side API rejection), circumstance stacking rule, non-magical save no-trigger, heritage lock post-creation, and owner/GM ACL. Five TCs marked `pending-dev-confirmation` pending CQ-001–CQ-005 resolution (reaction prompt API shape, turn tracking, ConditionManager stacking, magical source_type, GM role). Added 1 permission rule (57 total; path probe-ignore until Dev confirms route). Committed at `52db579c4`.

## Next actions
- PM (pm-dungeoncrawler) resolves CQ-001–CQ-005 → Dev documents in implementation notes → QA updates pending TCs to active
- Dev confirms exact reaction trigger route path → update `dc-cr-dwarf-heritage-ancient-blooded-reaction` path_regex in qa-permissions.json
- TC-AB-011 (magical source_type) and TC-AB-010 (stacking) are highest-risk — Dev must document before QA can write deterministic assertions
- TC-AB-001/002 are now template for all future heritage suites — Dev should align heritage entity schema docs to match

## Blockers
- None blocking suite activation; CQ-001–CQ-005 are documentation gaps, not hard blockers

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: First heritage feature suite activated; TC-AB-001/002 now serve as the canonical template pattern for all subsequent heritage implementations, preventing rework across the release. Highest-risk TCs (TC-AB-010, TC-AB-011) are flagged with explicit dev requirements, preventing late-stage integration failures.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145412-suite-activate-dc-cr-dwarf-heritage-ancient-blooded
- Generated: 2026-04-07T15:10:54+00:00
