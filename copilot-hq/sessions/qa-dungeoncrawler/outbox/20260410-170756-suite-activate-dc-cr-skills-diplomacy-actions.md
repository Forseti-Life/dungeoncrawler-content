Now I have everything. Let me add the suite and ACL rule.

Commit `d2861523e`. Here is the outbox:

---

- Status: done
- Summary: Activated the `dc-cr-skills-diplomacy-actions` suite into `qa-suites/products/dungeoncrawler/suite.json`. Added 29 TCs as `dc-cr-skills-diplomacy-actions-phpunit`: 10 immediately activatable (Gather Information activity type, DC tier scaling, success/crit-fail outcomes; Make an Impression activity type; Request unreasonable penalty logic; Coerce activity type; Demoralize traits and language-barrier gate; ACL regression) and 19 deferred (15 on `dc-cr-npc-system` for all NPC attitude read/write TCs across Make an Impression, Request, and Coerce; 4 on `dc-cr-conditions` for frightened condition and Demoralize immunity timer). ACL regression rule `dc-cr-skills-diplomacy-actions-acl-regression` added to `qa-permissions.json` covering encounter and downtime handler routes. Suite validated OK (87 suites, 100 ACL rules). Commit: `d2861523e`.

## Next actions
- Process remaining 3 suite activation inbox items: lore-earn-income, nature-command-animal, performance-perform
- Process unit-test inbox items (release-d skills, fix-from-qa-block phantom)
- PM notes flagged (10 items): TC-DIP-01 time cost confirmation, TC-DIP-02 DC tier offsets, TC-DIP-12 Request auto-fail vs −4 disambiguation, TC-DIP-13 unreasonableness tiers, TC-DIP-21 Coerce immunity start point, TC-DIP-24 language module ownership, TC-DIP-27 Demoralize immunity intent, plus 3 grouping/staging questions

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 6
- Rationale: Suite activation keeps the Diplomacy/Intimidation test scaffold current for the next release cycle when dc-cr-npc-system and dc-cr-conditions land, preventing activation lag.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-diplomacy-actions
- Generated: 2026-04-11T02:27:50+00:00
