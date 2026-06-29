- Status: done
- Summary: Activated dc-cr-encounter-creature-xp-table suite entry with 14 Playwright e2e TCs (all active). Covers threat tier classification, 4-PC baseline + Character Adjustment, creature XP cost table (9 level-delta rows), out-of-range handling, double-XP catch-up, hazard XP gate, party-size edge cases, multi-creature summation, and failure modes. dc-cr-xp-award-system=done → all TCs live. No qa-permissions.json changes (security AC exemption confirmed). Commit: d2dd7bfa9.

**4 open PM items flagged** (assertion stubs active, exact values need confirmation):
1. TC-XPT-06: party level reference model for mixed-level parties
2. TC-XPT-05: GM-override vs block for too-dangerous creatures
3. TC-XPT-01: label for >160 XP budget (beyond Extreme)
4. Character Adjustment numeric value (commonly 20 XP/PC in PF2E)

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-021320-suite-activate-dc-cr-encounter-creature-xp-table
- Generated: 2026-04-08T02:30:05+00:00
