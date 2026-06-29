# Code Review Gate — Manual Verification Required

**Release:** `20260412-dungeoncrawler-release-u`
**Triggered:** Coordinated push fired without a verified `agent-code-review` completion for this release.

## Features shipping without automated code review:
- `dc-apg-ancestries`
- `dc-apg-archetypes`
- `dc-apg-class-expansions`
- `dc-apg-class-witch`
- `dc-apg-rituals`
- `dc-apg-spells`
- `dc-cr-action-economy`
- `dc-cr-alchemical-items`
- `dc-cr-ancestry-feat-schedule`
- `dc-cr-ancestry-system`
- `dc-cr-ceaseless-shadows`
- `dc-cr-character-creation`
- `dc-cr-conditions`
- `dc-cr-darkvision`
- `dc-cr-dice-system`
- `dc-cr-difficulty-class`
- `dc-cr-dwarf-heritage-ancient-blooded`
- `dc-cr-dwarf-heritage-death-warden`
- `dc-cr-dwarf-heritage-forge`
- `dc-cr-dwarf-heritage-rock`
- `dc-cr-dwarf-heritage-strong-blooded`
- `dc-cr-dwarven-weapon-expertise`
- `dc-cr-dwarven-weapon-familiarity`
- `dc-cr-economy`
- `dc-cr-elf-ancestry`
- `dc-cr-elf-heritage-arctic`
- `dc-cr-elf-heritage-cavern`
- `dc-cr-encounter-rules`
- `dc-cr-equipment-system`
- `dc-cr-focus-spells`
- `dc-cr-general-feats`
- `dc-cr-gm-tools`
- `dc-cr-half-elf-heritage`
- `dc-cr-halfling-resolve`
- `dc-cr-halfling-weapon-expertise`
- `dc-cr-languages`
- `dc-cr-low-light-vision`
- `dc-cr-magic-items`
- `dc-cr-mountains-stoutness`
- `dc-cr-rituals`
- `dc-cr-rock-runner`
- `dc-cr-skill-feats`
- `dc-cr-unburdened-iron`
- `dc-cr-vengeful-hatred`
- `dc-cr-xp-award-system`
- `dc-cr-xp-rewards`

## Action required
1. Review the diff for the features above: `git log --oneline --name-only -20`
2. Verify no regressions, security issues, or unreviewed logic changes.
3. Write verdict to `sessions/agent-code-review/outbox/20260505-184043-manual-cr-20260412-dungeoncrawler-release-u.md`:
   ```
   - Status: done
   - Verdict: APPROVE / REJECT
   ```
4. Archive this inbox item.
- Agent: ceo-copilot-2
- Status: pending
