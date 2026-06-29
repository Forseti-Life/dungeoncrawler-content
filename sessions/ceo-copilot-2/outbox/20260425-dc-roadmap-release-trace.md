- Status: done
- Summary: Traced how the DungeonCrawler roadmap derives progress and mapped the current roadmap state to the live release cycle. Canonical current state: `20260412-dungeoncrawler-release-t` is the active release, `20260412-dungeoncrawler-release-u` is next, and the public roadmap is displaying an older `s/t` snapshot that no longer matches the live release-control files. Release `s` corresponds to `dc-cr-dwarf-ancestry`; release `t` currently resolves to `dc-cr-halfling-resolve`; release `u` has grooming started but no trustworthy finalized scope yet. The release-cycle LangGraph is not stalled on dev throughput; it is stalled on coordinated-release hygiene and artifact drift (missing cross-team co-signoff, conflicting scope artifacts, and mismatched QA evidence).

## Roadmap Progress Model

The DungeonCrawler roadmap is a composite view, not a single source of truth.

1. **Requirement rows** live in `dc_requirements`.
   - Baseline statuses: `pending`, `in_progress`, `implemented`
   - The roadmap page groups these by book/chapter/section.

2. **Feature-linked rows override DB status** through `RoadmapPipelineStatusResolver`.
   - Source: `features/<feature_id>/feature.md`
   - Mapping:
     - `ready` -> roadmap `pending` / display `Queued`
     - `in_progress` -> roadmap `in_progress`
     - `done` -> roadmap `in_progress`
     - `shipped` -> roadmap `implemented`

3. **Release snapshot panel** on `/roadmap` is built from:
   - `tmp/release-cycle-active/dungeoncrawler.release_id`
   - `tmp/release-cycle-active/dungeoncrawler.next_release_id`
   - `features/dc-*/feature.md` `- Release:` fields

4. **LangGraph release-cycle step** advances releases from PM signoff artifacts plus cross-team coordinated-release checks.

## Canonical Release Mapping

### Release `20260412-dungeoncrawler-release-s`
- Live signoff exists: `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260412-dungeoncrawler-release-s.md`
- Cross-team co-signoff exists from `pm-forseti`
- Canonical scoped feature in current feature briefs:
  - `dc-cr-dwarf-ancestry` -> `Status: done`, `Release: 20260412-dungeoncrawler-release-s`
- This is the cleanest shipped/closed recent DC release.

### Release `20260412-dungeoncrawler-release-t`
- Current live active release in `tmp/release-cycle-active/dungeoncrawler.release_id`
- PM signoff artifact exists: `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260412-dungeoncrawler-release-t.md`
- Current feature briefs map this release to:
  - `dc-cr-halfling-resolve` -> `Status: in_progress`, `Release: 20260412-dungeoncrawler-release-t`
- QA evidence exists, but it is semantically inconsistent with the feature brief:
  - `sessions/qa-dungeoncrawler/outbox/20260413-gate2-20260412-dungeoncrawler-release-t.md`
  - This approves a **halfling merchant NPC** flow, not the `Halfling Resolve` save-resolution mechanic
- Net: release `t` is the active release, but the evidence chain is inconsistent and should not be treated as cleanly reconciled.

### Release `20260412-dungeoncrawler-release-u`
- Current live next release in `tmp/release-cycle-active/dungeoncrawler.next_release_id`
- PM grooming outbox exists:
  - `sessions/pm-dungeoncrawler/outbox/20260424-groom-20260412-dungeoncrawler-release-u.md`
- No trustworthy finalized release-u scope artifact yet
- Net: release `u` is real as the next slot, but its product scope is still grooming-stage and should not be treated as committed release content.

## Why the Public Roadmap Looks Wrong

The public roadmap page at `https://dungeoncrawler.forseti.life/roadmap` currently shows:
- Active release: `20260412-dungeoncrawler-release-s`
- Next release: `20260412-dungeoncrawler-release-t`

But the live monorepo control files show:
- Active release: `20260412-dungeoncrawler-release-t`
- Next release: `20260412-dungeoncrawler-release-u`

This means at least one of the following is true:
- Production is reading stale release-state files
- Production is serving a stale deployed snapshot
- The roadmap HTML is cached from an older release boundary despite runtime state having advanced

Given the current evidence, the roadmap page should be treated as **stale for release boundary state**.

## LangGraph Release-Cycle Status

Recent HQ LangGraph ticks consistently report the same DungeonCrawler state:

- Team: `dungeoncrawler`
- Action: `blocked_waiting_cross_team_signoffs`
- Current: `20260412-dungeoncrawler-release-t`
- Next: `20260412-dungeoncrawler-release-u`
- Issue: missing co-signoff from `forseti`

This means DC is **not** stalled because the graph forgot the release.
It is stalled because the graph believes:
1. DC primary signoff exists
2. coordinated push marker already exists for `release-t__forseti-release-r`
3. the required coordinated co-signoff set is incomplete

## Artifact Drift / Data Integrity Issues

I found four separate inconsistencies that explain the confusion:

1. **Roadmap release snapshot is stale**
   - public page says `s/t`
   - live control files say `t/u`

2. **Release-t scope artifacts conflict**
   - some PM artifacts describe older candidate scopes
   - current feature briefs only assign `dc-cr-halfling-resolve` to release `t`

3. **QA evidence does not match the current release-t feature**
   - Gate 2 artifact describes a halfling merchant NPC
   - current release-t feature brief describes a saving-throw rules mechanic

4. **Coordinated push logic and release-boundary recovery have already drifted once**
   - CEO RCA on 2026-04-24 shows a prior boundary stall between `s` and `t`
   - current HQ ticks still show coordinated-release inconsistency around release `t`

## CEO Conclusion

The correct release map for operational decision-making is:

| Release | Canonical meaning | Operational status |
|---|---|---|
| `release-s` | `dc-cr-dwarf-ancestry` | closed / shipped |
| `release-t` | `dc-cr-halfling-resolve` | active but evidence chain inconsistent |
| `release-u` | next release slot | grooming only |

The roadmap is tracking progress through a valid mechanism, but the **release artifacts are no longer internally consistent enough to trust at a glance**. The immediate operational truth should come from:

1. `tmp/release-cycle-active/dungeoncrawler.*`
2. current `features/dc-*/feature.md` release assignments
3. PM/QA signoff artifacts only when they match the feature briefs

Until those three are reconciled, the roadmap page should be treated as informational for requirement coverage, **not authoritative for live release state**.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-25T12:49:00+00:00
