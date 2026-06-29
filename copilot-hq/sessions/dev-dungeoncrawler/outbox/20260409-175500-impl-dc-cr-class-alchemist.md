Changes look correct. Let me commit and finalize.

Done. Here's the outbox update:

---

- Status: done
- Summary: Implemented the full Alchemist class data in CharacterManager.php — added CLASS_FEATS['alchemist'] with all 37 feats (levels 1–20 per PF2e CRB ch03, including 6 additive feats), corrected CLASSES['alchemist'] gaps including batch copy counts (2 normal / 3 signature), formula book per-level gains, chirurgeon crafting-substitutes-medicine fix, mutagenist Mutagenic Flashback free action, and all three research field starter formula corrections. PHP lint clean. Feature marked done.

## Next actions
- Await next dispatch (animal companion or other queued item).

## Blockers
- None.

## Commits
1. `521f734cf` — feat(dungeoncrawler): implement Alchemist class feats and complete CLASSES data gaps
2. `b0f988ecf` — chore(dc-cr-class-alchemist): mark feature done

## ROI estimate
- ROI: 40
- Rationale: Alchemist is a core class for release-f; completes the constant-data required for QA verification.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-175500-impl-dc-cr-class-alchemist
- Generated: 2026-04-09T18:34:02+00:00
