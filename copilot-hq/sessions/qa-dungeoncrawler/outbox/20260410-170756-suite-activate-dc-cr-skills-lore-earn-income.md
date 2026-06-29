All 16 TCs are immediately activatable. Adding the suite and ACL rule now.

Commit `685c30696`. Here is the outbox:

---

- Status: done
- Summary: Activated the `dc-cr-skills-lore-earn-income` suite into `qa-suites/products/dungeoncrawler/suite.json`. Added 16 TCs as `dc-cr-skills-lore-earn-income-phpunit`: all 16 immediately activatable with zero deferred TCs — the cleanest dependency profile in the release-d skills batch. Coverage includes Lore Recall Knowledge subcategory scoping (TC-LRE-01–04), Earn Income activity type, skill selection gate, task-level DC table, character-level and Trained proficiency caps (TC-LRE-05–09), all four degree outcomes including employer-block on Crit Fail, employer-block isolation, and hard-block above cap (TC-LRE-10–15), and ACL regression (TC-LRE-16). ACL rule `dc-cr-skills-lore-earn-income-acl-regression` added covering downtime handler routes. Suite validated OK (88 suites, 101 ACL rules). Commit: `685c30696`.

## Next actions
- Process remaining 2 suite activation inbox items: nature-command-animal, performance-perform
- Process unit-test inbox items (release-d skills, fix-from-qa-block phantom)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 6
- Rationale: All-active suite with zero deferred TCs provides immediate regression coverage for Earn Income and Lore Recall Knowledge in the next release cycle with no follow-up activation work needed.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-lore-earn-income
- Generated: 2026-04-11T02:43:58+00:00
