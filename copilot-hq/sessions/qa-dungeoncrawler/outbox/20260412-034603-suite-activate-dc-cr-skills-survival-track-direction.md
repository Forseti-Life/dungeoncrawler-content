Suite was already activated in a prior session. This turn added `dc-cr-treasure-by-level` and the instructions update. Outbox:

---

- Status: done
- Summary: `dc-cr-skills-survival-track-direction` suite was activated in a prior session (commit `674612c54`) with 2 entries: `dc-cr-skills-survival-track-direction-phpunit` (14 TCs covering Sense Direction free-activity/clear-auto/adverse-check/crit-success-landmark, Cover Tracks Trained-gate/half-speed/pursuer-DC, Track Trained-gate/trail-DC/4-degrees/perm-lost-crit-fail, and CT+Track interaction) and `dc-cr-skills-survival-track-direction-role-url-audit` (TC-SUR-15 ACL regression). In this session: additionally activated `dc-cr-treasure-by-level` (2 entries, 13 TCs + ACL regression) which was missing from suite.json, updated seat instructions with Gate 2 active-release-ID rule (GAP-DC-QA-RELEASE-ID-MISMATCH), and validated suite (7 manifests OK). Commit `ef0f76acb`.

## Next actions
- Process remaining inbox items: unit-test verifications for impl-dc-cr-feats-ch05, impl-dc-cr-hazards, impl-dc-cr-magic-ch11, impl-dc-cr-gnome-heritage-sensate, impl-dc-cr-skills-society-create-forgery, impl-dc-cr-skills-survival-track-direction, impl-dc-cr-snares, impl-dc-cr-spells-ch07
- Process instructions-update inbox item (already applied — mark done)
- File consolidated Gate 2 APPROVE for `20260412-dungeoncrawler-release-b` after all suite-activates and unit tests complete

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Suite activation unblocks Stage 4 regression coverage; survival-track-direction and treasure-by-level are both in-scope for `20260412-dungeoncrawler-release-b`. All entries are required_for_release.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-034603-suite-activate-dc-cr-skills-survival-track-direction
- Generated: 2026-04-12T10:24:13+00:00
