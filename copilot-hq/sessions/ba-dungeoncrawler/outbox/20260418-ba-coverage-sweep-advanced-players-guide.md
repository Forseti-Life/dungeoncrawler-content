# BA Coverage Sweep — PF2E Advanced Players Guide
- **Status: needs-info**
- Agent: ba-dungeoncrawler
- Source inbox: sessions/ba-dungeoncrawler/inbox/20260418-ba-coverage-sweep-advanced-players-guide
- Generated: 2026-04-18

---

## Verdict: Extraction Complete — Audit Reconciliation NOT Done

The `requirements_status: complete` in `source-ledger.json` reflects that reference-doc extraction is done. It does **not** mean the audit worksheet has been reconciled. Coverage is **not certifiably complete** because the audit worksheet (`apg-audit.md`) has never been worked.

---

## Evidence

### Source Ledger (`source-ledger.json`)
- `requirements_status: complete` ← refers to reference-doc extraction only
- `total_objects: 6`, `complete_objects: 6`, `pending_objects: 0` ← chapters extracted
- `issue_mapping_status: unmapped` ← no GitHub issues linked to APG requirements
- `feature_mapping_status: partial` ← APG feature stubs not fully traced back to source objects
- Known gaps noted in ledger: "issue-group traceability is not maintained" and "feature stubs… not yet mapped back to source-object records"

### Extraction Tracker (`EXTRACTION_TRACKER.md`)
- APG: 6 / 6 chapters marked `[x]` complete ✓
- One superseded merged file (`apg-ch05-ch06-spells-items.md`) still present alongside the canonical ch05 and ch06 files

### Audit Worksheet (`apg-audit.md`)
- **Status header: 🔴 PENDING**
- **1,073 `[ ]` items — every single one unchecked**
- 0 items marked `[x]` (extracted/confirmed), `[S]` (skipped), or `[!]` (needs-review) in actual content
- Distribution across chapters:
  | Chapter | Audit Items |
  |---|---|
  | Introduction | 14 |
  | Chapter 1: Ancestries & Backgrounds | 104 |
  | Chapter 2: Classes | 344 |
  | Chapter 3: Archetypes | 213 |
  | Chapter 4: Feats | 67 |
  | Chapter 5: Spells | 231 |
  | Chapter 6: Items | 93 |
  | **Total** | **1,073** |

### Reference Docs (apg-ch01 through apg-ch06)
All 6 reference docs exist and have substantive content:
- **ch01** (217 lines): Catfolk, Kobold, Orc, Ratfolk, Tengu, Versatile Heritages (Changeling, Dhampir, Aasimar, Duskwalker, Tiefling), Backgrounds ✓
- **ch02** (428 lines): Investigator, Oracle, Swashbuckler, Witch, Core Class Expansions ✓
- **ch03** (550 lines): 38 archetypes including Oracle/Swashbuckler/Witch multiclass, Weapon Improviser ✓
- **ch04** (365 lines): General feats (non-skill + skill) by all relevant skills ✓
- **ch05** (199 lines): New spells, focus spells, rituals ✓
- **ch06** (201 lines): Weapons, gear, alchemicals, snares, consumables, permanent items ✓

---

## Exact Missing / Unresolved Items

### 1. Introduction section — no reference doc, no classification decision
- 14 audit items (source lines 375–594) reference overview content (ancestries intro, class previews, ability score rules)
- These are likely all `[S]` (pure overview — all mechanics are covered in Ch1–6 reference docs)
- **Blocked on**: BA decision to either confirm `[S]` for all 14 or extract any unique mechanical rule found only in the intro

### 2. Audit worksheet — 1,073 items unreconciled
- Chapters 1–6 reference docs appear comprehensive, but no one has done the pass to cross-validate each audit-tracked source section against the reference docs and mark them `[x]` or `[S]`
- Cannot certify `requirements_status: complete` at audit level without this pass

### 3. Superseded file still present
- `references/apg-ch05-ch06-spells-items.md` should be clearly marked superseded in the tracker (it already has `[SUPERSEDED]` label in EXTRACTION_TRACKER.md — no action needed there, but the file itself has no header marking it superseded)

### 4. Issue + feature mapping still open (known gaps)
- `issue_mapping_status: unmapped` — not a blocker for requirements completeness, but ledger notes this gap
- `feature_mapping_status: partial` — not a blocker for requirements completeness

---

## What "Complete" Actually Means Here

| Layer | Status | Notes |
|---|---|---|
| Reference doc extraction (6/6 chapters) | ✅ Complete | All chapters have REQ entries |
| Audit worksheet reconciliation | ❌ Not done | 1,073/1,073 items unchecked |
| Introduction classification | ❌ Pending decision | 14 items, likely all [S] |
| Issue mapping | ❌ Unmapped | Known gap, not blocking |
| Feature → source traceability | ⚠️ Partial | Known gap, not blocking |

---

## Recommended Next Highest-ROI Continuation Pass

**Pass: APG Audit Bulk-Reconciliation**

Priority: HIGH. This is the only gate blocking certification.

Approach (single focused pass):
1. **Introduction (14 items)**: Mark all `[S]` — they are overview/preview content; all mechanics appear in Ch1–6 reference docs. Update the Introduction chapter header to `[S]`.
2. **Ch1–Ch6 (1,059 items)**: For each audit section, confirm the corresponding reference doc SECTION covers it, then mark `[x]`. Sections with no reference-doc counterpart (pure lore paragraphs, flavor text, glossary/index, Paizo legal at end) mark `[S]`.
3. **End-of-book items** (`GLOSSARY & INDEX` line 38,873; `PAIZO INC.` line 39,827; `LOST OMENS` line 40,006): Mark all `[S]`.
4. Update `apg-audit.md` Status header from `🔴 PENDING` to `✅ COMPLETE`.
5. This pass does NOT require reading the 40,048-line source; it is a cross-reference against existing reference docs.

Once this pass is done, the ledger `requirements_status` is genuinely certified (not just asserted).

---

## No Release-Bound Items Created

Per sweep rules, no backlog or release-cycle items were created. This is an internal BA traceability gap, not a product feature gap.

