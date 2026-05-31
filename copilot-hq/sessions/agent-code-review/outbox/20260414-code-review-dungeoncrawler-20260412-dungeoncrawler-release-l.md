# Code Review: dungeoncrawler 20260412-dungeoncrawler-release-l

- Status: done
- Summary: Pre-ship code review for dungeoncrawler `20260412-dungeoncrawler-release-l` is APPROVE. Release window: `2026-04-14T17:05:01+00:00` to `2026-04-14T18:16:15+00:00`. Five commits in scope across 3 modules: (1) roadmap "queued" badge display logic, (2) Goblin Free boost fix, (3) Very Sneaky feat, (4) Goblin Weapon Familiarity fix, (5) Halfling ancestry + auto-grant feats + keen-eyes effect. No new POST routes, no authz bypass, no schema changes, no VALID_TYPES additions. The path traversal risk in `RoadmapPipelineStatusResolver` previously reported as FINDING-01 MEDIUM (release-e) was fixed in `3be132b3c` before this window. No new findings. APPROVE.

## Verdict: APPROVE

**Product:** dungeoncrawler
**Release:** `20260412-dungeoncrawler-release-l`
**Release window start:** `2026-04-14T17:05:01+00:00`
**Release window end:** `2026-04-14T18:16:15+00:00`
**Commits touching DC custom modules:** 5

## Commits reviewed

| Commit | Description | Files changed |
|---|---|---|
| `2ea27f417` | Roadmap queued badge + cleanup 19 orphaned features | `RoadmapController.php`, `roadmap.css`, `dungeoncrawler-roadmap.html.twig` |
| `5cea90cd5` | Fix: add missing Free boost to Goblin ancestry | `CharacterManager.php` |
| `0b0e87998` | Feat: Goblin ancestry feat Very Sneaky | `CharacterManager.php`, `FeatEffectManager.php` |
| `880f3e20e` | Fix: Goblin Weapon Familiarity — add uncommon_access + proficiency_remap | `FeatEffectManager.php` |
| `f77b0b3fd` | Feat: Halfling ancestry — boosts, auto-grant feats, keen-eyes | `CharacterCreationStepController.php`, `CharacterManager.php`, `FeatEffectManager.php` |

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF | PASS | No new routes added |
| Authorization bypass | PASS | No write paths touched; roadmap is read-only display |
| Schema hook pairing | PASS | No schema changes |
| VALID_TYPES pairing | PASS | No new equipment/item types |
| Stale private duplicates | PASS | Ancestry data is product-sourced (PF2e CRB) — not duplicated from another internal canonical source |
| Hardcoded absolute paths | PASS | No new hardcoded paths |
| qa-permissions.json pairing | PASS | No new permissions |
| Path traversal (prior FINDING-01 MEDIUM) | PASS — FIXED | `RoadmapPipelineStatusResolver::getPipelineStatus()` now guards against `/`, `\`, `..` in feature_id (`3be132b3c`, 2026-04-12T18:24:32+00:00) |
| FeatEffectManager case coverage | PASS | `very-sneaky`, `goblin-weapon-familiarity`, `keen-eyes` follow established pattern exactly |
| Auto-grant feat pattern | PASS | `special.auto_grant_feats` correctly consumed by `buildFeatsArrayFromData`; `halfling-luck` correctly removed from selectable feats |
| Halfling flat DC values | PASS | `concealed flat DC=3`, `hidden flat DC=9` match PF2e CRB keen-eyes description |

## Findings
- None

## Outstanding items (from prior releases — not this release's scope)
- FINDING-01 HIGH from release-d: `gm_override` authz bypass in `InventoryManagementController::sellItem()` — dev inbox item exists, still unresolved.

## Next actions
- Release operator may proceed with dungeoncrawler release-l gate signoff.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Goblin/Halfling ancestry cluster is shippable; clean review enables gate signoff. Outstanding gm_override HIGH (release-d) remains the top security debt for dev-dungeoncrawler.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260414-code-review-dungeoncrawler-20260412-dungeoncrawler-release-l
- Generated: 2026-04-14T19:42:00+00:00
