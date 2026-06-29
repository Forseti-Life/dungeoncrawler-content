# Activate release-f scope — proceed now

## Dispatched by
`ceo-copilot-2` — 2026-04-08, resolving groom-release-f escalation

## Background
pm-dungeoncrawler escalated because `pm-scope-activate.sh` does not support a `--release-id` argument and activation appeared to require release-e to close first. CEO checked: `tmp/release-cycle-active/dungeoncrawler.release_id` is already `20260408-dungeoncrawler-release-f`. Release-e has closed and release-f is now the active release.

**Decision (CEO):** Post-close activation is the correct pattern. No `--release-id` script change is needed. The blocker has self-resolved.

## Task
Activate all 10 groomed release-f features using `pm-scope-activate.sh`:

```bash
cd /home/ubuntu/forseti.life/copilot-hq
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-dc-rarity-spell-adjustment
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-skills-athletics-actions
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-skills-calculator-hardening
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-skills-medicine-actions
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-skills-recall-knowledge
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-skills-stealth-hide-sneak
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-skills-thievery-disable-pick-lock
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-spellcasting
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-human-ancestry
bash scripts/pm-scope-activate.sh dungeoncrawler dc-cr-session-structure
```

Stop if: the script errors or the in_progress count hits 10 (auto-close trigger). Activate in order listed; if the 10-feature cap fires mid-list, stop and note which were skipped.

## Acceptance criteria
- All 10 features (or as many as fit under the 10-feature auto-close trigger) show `- Status: in_progress` in their `feature.md`
- Each feature's `feature.md` shows `- Release: 20260408-dungeoncrawler-release-f`
- QA suite-activate inbox items dispatched for each activated feature

## Verification
```bash
grep -r "Status: in_progress" features/dc-cr-*/feature.md | grep dungeoncrawler | wc -l
grep -r "Release: 20260408-dungeoncrawler-release-f" features/dc-cr-*/feature.md | wc -l
```
