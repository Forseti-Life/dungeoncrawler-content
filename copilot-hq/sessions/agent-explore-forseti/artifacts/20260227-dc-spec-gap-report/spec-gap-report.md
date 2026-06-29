# Dungeon Crawler Spec Gap Report

- Agent: agent-explore-forseti
- Method: Context-based review (Tier 2 fallback — Playwright not installed)
- Generated: 2026-02-27T14:55:45Z
- Scope: All 33 `dc-cr-*` feature stubs in `features/`

---

## Summary

All 33 dungeon crawler feature specs are `Status: pre-triage` with no acceptance criteria. No dungeon crawler routes or modules exist in the codebase yet (`forseti_games` only has Block Matcher and Games Home). This report identifies structural gaps and a recommended triage order for `pm-dungeoncrawler`.

---

## Critical gap: universal AC absence

**Every one of the 33 specs is missing acceptance criteria.** Dev cannot implement any feature without PM-written AC. This is the highest-priority triage action needed before any implementation sprint begins.

---

## Dependency ordering risk (4 features with explicit upstream deps)

These features state explicit prerequisites that must be resolved first:

| Feature | Depends on (stated in spec) |
|---|---|
| `dc-cr-spellcasting` | Must be implemented before any individual spells; required by 6+ classes |
| `dc-cr-skill-feats` | Requires `dc-cr-skill-system` (proficiency rank check); reuses `feat` content type from `dc-cr-general-feats` |
| `dc-cr-general-feats` | Requires `dc-cr-character-leveling` (feat selection at correct levels) |
| `dc-cr-multiclass-archetype` | Requires `dc-cr-character-class` (dedication feat prerequisite check) |

**Risk**: If any of these four are implemented before their upstream, entity fields will be incomplete and selection UIs will break.

**Recommendation for pm-dungeoncrawler**: triage foundation features first:
1. `dc-cr-dice-system` (likely lowest-level primitive — no upstream deps found)
2. `dc-cr-action-economy` (core turn structure; everything combat-related depends on it)
3. `dc-cr-skill-system` (17 skills; prerequisite for skill-feats)
4. `dc-cr-character-creation` (end-to-end wizard; integrates ancestry/class/background)
5. `dc-cr-spellcasting` (required before any spell features)

---

## Features with zero dependency refs and zero route/UX detail

These features have implementation hints but no routing or dependency information — UX flow is entirely undefined:

- `dc-cr-encounter-rules` — full combat loop described, but no route or UI entry point specified
- `dc-cr-exploration-mode` — between-encounter mode; unclear if this is a character state flag or a separate UI surface
- `dc-cr-tactical-grid` — grid-based combat; unclear if this is a new page, a modal, or integrated into encounter-rules

**Ask for pm-dungeoncrawler**: each of these needs at minimum one sentence on: "where does a player access this?" before dev can begin.

---

## Shared content type collision risk

Two features reference the same `feat` content type:
- `dc-cr-general-feats`: defines `feat` content type (type=general)
- `dc-cr-skill-feats`: reuses `feat` content type (type=skill)

If these are triaged to different sprints/devs, there is a content type collision risk. They should be coordinated or batched together.

---

## Route/URL gap (entire dungeon crawler)

**No dungeon crawler routes exist in any module** (verified: `grep` across all `.routing.yml` files in `forseti_games` returns zero matches for `dungeon`). The games home (`/games`) and block-matcher (`/games/block-matcher`) exist, but there is no `/games/dungeon-crawler` or similar entry point.

The `dc-cr-character-creation` spec mentions a "multi-step wizard UI" but gives no route. **pm-dungeoncrawler needs to define at minimum**:
- The top-level dungeon crawler URL
- The character creation wizard URL
- Whether authenticated-only (matching `/games` access pattern) or public-accessible

---

## Features missing any UX/flow information (no routes, no UI hints, no entry point)

| Feature | Notes |
|---|---|
| `dc-cr-encounter-rules` | Combat loop described; no entry point |
| `dc-cr-exploration-mode` | Mode described; no surface specified |
| `dc-cr-downtime-mode` | Downtime activities described; no UI/entry |
| `dc-cr-tactical-grid` | Grid mentioned; no page/modal/route |
| `dc-cr-gm-narrative-engine` | GM tool; no access path for non-GM vs. GM |
| `dc-cr-session-structure` | Session described; no route or access model |

---

## Verification method

All findings are from static code/spec analysis (Tier 2):
```bash
# Confirmed: no dungeon crawler routes
grep -r "dungeon\|dungeoncrawler" /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/ --include="*.routing.yml"
# Returns: no results

# Confirmed: /games returns 403 (auth required, matching block-matcher pattern)
curl -s http://localhost/games | grep title
# Returns: <title>Access denied | Forseti</title>

# Confirmed: 33 features, all pre-triage, all missing AC
grep "^- Status:" features/dc-cr-*/feature.md | grep -v "pre-triage"
# Returns: nothing
```

---

## Recommended next actions for pm-dungeoncrawler

1. Define top-level dungeon crawler URL and auth model (1 decision, unblocks all route work)
2. Write AC for the 5 foundation features listed above (unblocks first dev sprint)
3. Decide: batch `dc-cr-general-feats` + `dc-cr-skill-feats` into same sprint (avoids content type collision)
4. Clarify UX surface for `dc-cr-encounter-rules`, `dc-cr-exploration-mode`, `dc-cr-tactical-grid`
