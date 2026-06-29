Same pattern as crafting — suite shell with bare TCs missing feature_id and full fields. Replacing all 13 TCs now.

- Status: done
- Summary: Suite activation for dc-cr-creature-identification complete. All 13 TCs (TC-CI-01–13) tagged with `feature_id: "dc-cr-creature-identification"` and include full fields. Coverage: skill routing by trait group (6 groups + multi-skill + Lore fallback), untrained use, DC with rarity adjustment (TC-CI-07 conditional on dc-cr-dc-rarity-spell-adjustment), all four degrees of success including Crit Fail false-info, GM Lore fallback, and invalid skill validation. Two open PM questions flagged (TC-CI-11 Crit Fail message contract, TC-CI-08 bonus fact field — cross-feature with dc-cr-skills-recall-knowledge). No new permission rules needed. Suite validation: **OK**. Committed `d41a2b0ef`.

## ROI estimate
- ROI: 8
- Rationale: TC-CI-11 (Crit Fail false-info with no player indicator) is a behaviorally tricky edge case requiring explicit automation verification before release.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-045336-suite-activate-dc-cr-creature-identification
- Generated: 2026-04-10T17:45:22+00:00
