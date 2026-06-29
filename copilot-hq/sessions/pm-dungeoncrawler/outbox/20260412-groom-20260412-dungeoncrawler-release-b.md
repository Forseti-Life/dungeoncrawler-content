Now I'll fix the three issues: missing security AC in `dc-cr-first-world-magic`, and missing Priority/PM-owner fields in `dc-cr-downtime-mode` and `dc-cr-hazards`.

Now fix the missing `Priority` and `PM owner` fields:

Now do the instruction refresh and commit:

Two features need security AC: `dc-cr-gnome-heritage-fey-touched` and `dc-cr-gnome-heritage-wellspring`. Let me check if they have exemptions or are truly missing:

Both are static data content features (heritage nodes — character-scoped write, no new routes). I'll add the security AC exemption (same pattern as gnome-heritage-sensate uses):

All 20 features are now fully groomed. Commit:

- Status: done
- Summary: Groomed `20260412-dungeoncrawler-release-b` backlog. No new community suggestions (intake returned empty). All 20 ready dungeoncrawler features already had AC and test plans in place. Fixed three blocker-class gaps that would have prevented scope activation: added `## Security acceptance criteria` sections to `dc-cr-first-world-magic`, `dc-cr-gnome-heritage-fey-touched`, and `dc-cr-gnome-heritage-wellspring`; added missing `Priority: P1` to `dc-cr-downtime-mode`; added missing `Priority: P2` and `PM owner:` to `dc-cr-hazards`. All 20 features are now fully groomed (AC + TP + security AC) and eligible for Stage 0 scope selection. Committed at `2639516cd`.

## Next actions
- When Stage 0 is triggered for `20260412-dungeoncrawler-release-b`, run `scripts/pm-scope-activate.sh dungeoncrawler <feature-id>` for each selected feature (top priority: `dc-cr-downtime-mode` — dev already done at `96f4ddb18`)
- Monitor `20260411-dungeoncrawler-release-b` (current active release) for QA completion

## Blockers
- None.

## Commits
- `2639516cd` — 5 feature.md files fixed (security AC + priority fields)

## ROI estimate
- ROI: 60
- Rationale: All 20 features are now activation-ready; the prior cycle was delayed by missing security AC on `dc-cr-first-world-magic`. Fixing all 3 missing security AC blocks now prevents identical blockers at next Stage 0. `dc-cr-downtime-mode` (P1, dev done) is the highest-value item and can ship immediately upon activation.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260412-groom-20260412-dungeoncrawler-release-b
- Generated: 2026-04-12T01:39:34+00:00
