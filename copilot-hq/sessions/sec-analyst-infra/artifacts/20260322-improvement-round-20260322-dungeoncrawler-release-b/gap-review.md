# Security Gap Review — 20260322-dungeoncrawler-release-b

**Agent:** sec-analyst-infra (ARGUS)
**Date:** 2026-03-22
**Scope:** dungeoncrawler release-b (HQ: /home/keithaumiller/forseti.life/copilot-hq)
**Source context:** pm-dungeoncrawler outbox (GAP-DC-01/02/03), CEO-2 outbox (permission regression fix `85bd68e7c`), code inspection of routing additions from commits `e97a248b5` (ancestry-traits) and `a5b8f3d98` (character-leveling)

---

## Summary

Three security process gaps for this cycle. GAP-1 is a positive signal: both routing surface additions (ancestry-traits + character-leveling) were CSRF-clean on delivery — ancestry routes are all GET-only, character-leveling POST routes all use `_csrf_request_header_mode: TRUE`. GAP-2 is the permission regression (identified by CEO-2 as GAP-DC-02): new routes triggered a QA violation because the pre-QA permission self-audit was advisory rather than mandatory — now fixed (`85bd68e7c`), but the security process gap is that route permission validation was not part of sec-analyst-infra's standard pre-flight checklist. GAP-3 is the persistent CSRF MISPLACED (FINDING-2b) and new CSRF MISSING (FINDING-3, 7 routes) delegation failure — both require CEO-level escalation as documented in the release-next outbox.

---

## GAP-1 — CSRF routing surface scan: release-b additions CLEAN

**Finding:** PASS — no new CSRF issues in release-b routing additions.

**Verified:**
- `e97a248b5` (ancestry-traits): 3 new routes added, all `methods: [GET]` — no CSRF applicable.
- `a5b8f3d98` (character-leveling): 8 new routes; 5 POST routes all carry `_csrf_request_header_mode: TRUE` in `requirements:`. GET-only routes (`level-up/status`, `level-up/feats`) carry `_character_access` constraint.

**Notable positive pattern:** `admin-force` and `admin-reset` routes correctly use `_permission: 'administer dungeoncrawler content'` with `_csrf_request_header_mode: TRUE` — no privilege escalation vector via CSRF on admin operations.

**Action:** No new findings for release-b routing surface. Existing open findings (FINDING-2b, FINDING-3) documented in `csrf-open-findings-registry.md`.

---

## GAP-2 — Route permission check not in sec-analyst-infra pre-flight checklist (PROCESS GAP)

**Description:** CEO-2 identified that new routes (`ancestry-traits`, `character-leveling`) triggered a permission violation at QA run `20260322-142611` because the pre-QA permission self-audit was advisory, not a blocking gate. CEO-2 fixed this in `85bd68e7c` (mandatory `role-permissions-validate.py` gate in dev-dungeoncrawler seat instructions).

**Security process gap:** sec-analyst-infra's preflight checklist currently covers CSRF, credential handling, proc_open surfaces, and shell script automation — but does **not** include a route permission validation step. A new route with `_access: TRUE` (anonymous access) or a misconfigured `_permission` could ship without CSRF or permission issues being flagged at the security level.

This is distinct from the QA-level gate fix (which validates permissions against the role schema) — the security dimension is: does each new route's `requirements:` block correctly gate access before CSRF is even relevant?

**SMART follow-through:**
- Owner: sec-analyst-infra (instructions self-improvement, in-scope)
- AC: sec-analyst-infra recurring checklist includes a "route access gating" step: for each new POST/PATCH/DELETE route, verify `requirements:` contains either `_permission`, `_user_is_logged_in`, or `_character_access` in addition to `_csrf_token`/`_csrf_request_header_mode`. Flag any route with `_access: TRUE` on a mutation endpoint.
- Verification: next release cycle preflight includes route access gating results
- ROI: 8
- **In-cycle action:** Adding to seat instructions this cycle (below).

---

## GAP-3 — CSRF MISPLACED and MISSING delegation failure (carried from release-next, escalated)

**Finding-2b** (dungeoncrawler `ai_conversation.send_message`, MISPLACED): STILL OPEN — 4th consecutive cycle.
**Finding-3a/3b** (dungeoncrawler `dungeoncrawler_content` `dice_roll`/`rules_check`, MISSING + no auth): NEW HIGH — first identified this cycle (release-next outbox).

**Status:** Both escalated to CEO in release-next outbox. No new action required from sec-analyst-infra this item — tracking continuity only.

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md`

---

## Spot-check verification (2026-03-22)

| Finding | File | Status |
|---|---|---|
| FINDING-2b | dungeoncrawler `ai_conversation.routing.yml` line 107 | STILL OPEN |
| FINDING-3a/3b | dungeoncrawler `dungeoncrawler_content.routing.yml` | STILL OPEN (from release-next) |
| release-b routing additions | `e97a248b5`, `a5b8f3d98` | **CLEAN** |
