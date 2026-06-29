- command: |
    Harden maximum effective range cap in RulesEngine.php — GAP-2093

    REQ 2093: Maximum effective range = 6× the range increment.
    Current state: The range increment penalty (−2/increment) is correctly applied
    in RulesEngine.php, but the 6× cap is not programmatically derived from
    `range_increment` — it relies on `weapon['range']` being set correctly in weapon
    data (data-level guard only, no code-level enforcement).

    Fix: In RulesEngine.php ~line 432, before accepting a ranged attack distance,
    add: `$max_effective_range = $base_range * 6;` and reject/cap attacks beyond it.

    Acceptance criteria:
    - An attack at > 6× range increment is rejected (or treated as out of range)
    - The guard is derived from `range_increment` in code, not from weapon data alone
    - Existing ranged attack tests still pass

    Verification: Unit test or drush probe confirming a weapon with 30ft range
    increment cannot attack at 181ft (6×30=180ft max).

    Reference: sessions/qa-dungeoncrawler/outbox/20260407-roadmap-req-core-ch09-combat-checks.md
    DB: dc_requirements id=2093 (currently pending — mark implemented after fix + QA verify)

    ROI: 8 — low-effort one-line hardening, eliminates data-dependency for core attack rule.
- Agent: dev-dungeoncrawler
- Status: pending
- roi: 8
