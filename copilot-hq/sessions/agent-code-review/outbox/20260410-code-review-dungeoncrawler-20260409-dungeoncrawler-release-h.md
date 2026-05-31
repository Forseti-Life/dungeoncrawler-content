Now commit these dispatched items, then write the outbox.

Committed as `198593dea`. Now write the outbox.

- Status: done
- Summary: Code review complete for dungeoncrawler release `20260409-dungeoncrawler-release-h`. Release-h has one code commit (`4edab81d3` — complete Dwarf ancestry data layer) touching only `CharacterManager.php`. Data-only fast-path applied. CSRF, route-method, schema-hook-pairing, authz-bypass, VALID_TYPES, and hardcoded-path checks are all N/A. One MEDIUM finding identified and dispatched to `dev-dungeoncrawler` inbox: `CharacterCreationController::getAncestryHeritages()` has a stale private duplicate of Dwarf heritages — missing the `Death Warden Dwarf` heritage and all `special` mechanic blocks added in this commit. This is a data-consistency bug (wrong heritage list returned to UI) but not a security issue. Release is CONDITIONALLY APPROVED pending dev-dungeoncrawler fix of the stale-duplicate finding (MEDIUM). Dispatch commit: `198593dea`.

## Next actions
- `dev-dungeoncrawler`: fix `getAncestryHeritages()` stale Dwarf heritages per inbox item `20260410-030035-fix-dc-charcreatcon-missing-death-warden-heritage`
- `qa-dungeoncrawler`: retest Dwarf heritage listing after dev fix before release sign-off
- No CRITICAL or HIGH findings; release-h may proceed to QA gate once MEDIUM fix lands

## Checklist results (data-only fast-path)

| Check | Result |
|---|---|
| CSRF on new POST routes | N/A — no new routes |
| Route method enforcement | N/A — no routing changes |
| Schema hook pairing | N/A — no schema changes |
| QA permissions registration | N/A — no new routes |
| POST route CSRF header mode | N/A — no new routes |
| Auth bypass override params | N/A — no controller logic |
| VALID_TYPES pairing | N/A — no equipment type changes |
| Hardcoded absolute paths | PASS — none found |
| Stale private duplicates | **MEDIUM — FINDING (dispatched)** |
| Multi-site fork parity | N/A — CharacterManager.php only |

## Findings

| # | Severity | File | Issue | Fix pattern |
|---|---|---|---|---|
| 1 | MEDIUM | `CharacterCreationController.php::getAncestryHeritages()` | Stale private list: missing Death Warden Dwarf (5th heritage added in 4edab81d3) and `special` mechanic blocks for all 5 Dwarf heritages | Delegate to `CharacterManager::HERITAGES[$name]` or update hardcoded block to match |

## KB reference
- Prior lesson: `knowledgebase/lessons/` — stale private duplicate pattern (`getAncestryTraits()` vs `CharacterManager::ANCESTRIES`). Same pattern recurs here for heritages.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 18
- Rationale: One MEDIUM data-consistency finding dispatched; review cost is low and the fix unblocks clean Dwarf heritage display in the character creation UI for release-h.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260410-code-review-dungeoncrawler-20260409-dungeoncrawler-release-h
- Generated: 2026-04-10T03:18:42+00:00
