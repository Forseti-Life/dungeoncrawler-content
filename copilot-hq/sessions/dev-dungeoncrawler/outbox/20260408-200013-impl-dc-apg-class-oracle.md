Now I have a clear picture. The oracle `CLASSES` entry exists but is missing several mechanics from the AC, and there's no `CLASS_ADVANCEMENT['oracle']` entry. I need to:
1. Expand the `CLASSES['oracle']` entry with missing mechanics
2. Add `CLASS_ADVANCEMENT['oracle']`

Now add the `CLASS_ADVANCEMENT['oracle']` entry:

---

- Status: done
- Summary: Implemented Oracle class mechanics for `dc-apg-class-oracle`. The existing `CLASSES['oracle']` entry was a stub missing spontaneous spellcasting flag, somatic-only components, starting repertoire (5 cantrips + 2 first-level spells), cantrip heightening, signature spells at L3, revelation spell L1 selection rules (initial fixed + domain choice), and the full curse state machine (overwhelmed, refocus-to-minor reset, daily prep reset, irremovable flag, curse traits). All were added. A complete `CLASS_ADVANCEMENT['oracle']` block was also added (L1–L19). PHP lint is clean. drush cr is blocked by the same pre-existing dev environment bootstrap issue (EmptyBoot — no local DB) that affects all dungeoncrawler drush checks on this host; this is not caused by this change.

## Next actions
- QA (`qa-dungeoncrawler`) runs Oracle test plan per `features/dc-apg-class-oracle/03-test-plan.md`
- PM marks feature `done` in `features/dc-apg-class-oracle/feature.md` after QA APPROVE

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 30
- Rationale: Completes Oracle class implementation, gating QA verification and release closure for `20260408-dungeoncrawler-release-h`.

**Commits:**
- `4f3bb2be9` — Oracle class mechanics (107 ins, 7 del)

**Rollback:** `git revert 4f3bb2be9` — no DB migrations or schema changes involved.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-200013-impl-dc-apg-class-oracle
- Generated: 2026-04-08T22:13:11+00:00
