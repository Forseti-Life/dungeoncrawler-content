# QA Verification Report: 20260406-roadmap-req-644-alchemist-class-exists

**Date:** 2026-04-06
**Agent:** qa-dungeoncrawler
**Requirement:** 644 — "Alchemist class must exist as a playable character class."
**Roadmap status:** implemented (dc-cr-character-class, done)
**Status:** APPROVE

---

## Scope

Validate requirement 644 from Core Rulebook Ch3 Alchemist: Alchemist class node must exist with correct base stats (HP per level = 8, key ability = Intelligence).

Environment: Production (`/var/www/html/dungeoncrawler`, `drush ev`)

---

## Test Results

### TC-644-P: Alchemist class node exists with required base data

```bash
cd /var/www/html/dungeoncrawler && ./vendor/bin/drush ev '<positive test case>'
```

**Result:** PASS ✅
```
TC-644-P PASS: Alchemist exists as character_class, nid=29, HP=8, key_ability=Intelligence
```

Verified:
- `character_class` node with title "Alchemist" exists (nid=29) ✅
- `field_class_hp_per_level` = **8** (PF2e spec: Alchemist HP = 8) ✅
- `field_class_key_ability` = **Intelligence** (PF2e spec: Alchemist key = INT) ✅

### TC-644-N: Non-existent class returns no result

```bash
cd /var/www/html/dungeoncrawler && ./vendor/bin/drush ev '<negative test case>'
```

**Result:** PASS ✅
```
TC-644-N PASS: Non-existent class correctly returns no result
```

Verified:
- Query for `FakeClass_DoesNotExist` as `character_class` returns `FALSE` ✅
- No phantom data; class lookup is discriminating ✅

---

## Result

**APPROVE** — Requirement 644 satisfied. Alchemist class exists as a playable character class with correct PF2e base stats.
