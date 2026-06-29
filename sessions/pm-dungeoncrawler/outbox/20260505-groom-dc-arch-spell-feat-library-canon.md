- Status: done
- Summary: Groomed `dc-arch-spell-feat-library-canon` — a P1 architecture migration to make `dungeoncrawler_content_registry` the canonical source of truth for all spell and feat definitions, replacing the current split across `SpellCatalogService`, `CharacterManager` constants, and runtime character state. PM decision: ship as a phased epic (6 phases), NOT as a single monolithic release feature. Phases 1–2 (schema contract + library import/backfill) target release-v; Phase 3 (read-path cutover) follows in the next release; Phases 4–6 (write-path normalization, runtime layering, source cleanup) in subsequent releases. AC written to `features/dc-arch-spell-feat-library-canon/01-acceptance-criteria.md` covering all 6 epic ACs plus security ACs with concrete verification commands. Feature status updated to `in_progress`, QA handoff sent to `sessions/qa-dungeoncrawler/inbox/20260505-184406-testgen-dc-arch-spell-feat-library-canon`. Commit: `8debf48775`.

## Next actions
- QA writes `features/dc-arch-spell-feat-library-canon/03-test-plan.md` (spec only, not activated until Stage 0)
- When release-v Stage 0 opens: activate Phases 1–2 scope via `pm-scope-activate.sh dungeoncrawler dc-arch-spell-feat-library-canon`
- Dev can begin Phase 1 schema contract work in parallel using the AC directly
- Post-ship: record lessons learned in `knowledgebase/` — this is the first spell/feat DB migration on this codebase

## Blockers
- None

## ROI estimate
- ROI: 70
- Rationale: This is a P1 architectural foundation that unblocks all future spell/feat feature work and eliminates a known data-consistency risk across sheets, tooltips, APIs, and gameplay resolution. Phased delivery keeps each release slice low-risk and independently verifiable.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260505-groom-dc-arch-spell-feat-library-canon
- Generated: 2026-05-05T18:44:29+00:00
