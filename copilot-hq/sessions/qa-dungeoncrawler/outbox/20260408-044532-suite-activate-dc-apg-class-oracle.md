- Status: done
- Summary: Activated dc-apg-class-oracle suite entry in `qa-suites/products/dungeoncrawler/suite.json`. Added 17 Playwright e2e TCs (all active) covering: class record HP/Cha/Will-Expert/divine spellcasting with material→somatic substitution (TC-ORC-01), cantrip ceil(level/2) heightening and sorcerer slot progression (TC-ORC-02), Signature Spells L3 (TC-ORC-03), mystery selection locked at creation from 8 options (TC-ORC-04), revelation spells with cursebound trait and 2 FP focus pool (TC-ORC-05), initial revelation forced by mystery plus domain player choice (TC-ORC-06), full 4-stage curse state machine basic→minor→moderate→overwhelmed with revelation cast advancing stage and overwhelmed blocking all revelation casting (TC-ORC-07–08), refocus resetting moderate→minor not basic (TC-ORC-09), daily prep reset to basic (TC-ORC-10), curse irremovable (TC-ORC-11), curse traits (TC-ORC-12), per-mystery distinct progressions and full spell grants (TC-ORC-13–14), focus pool cap 2 with feat-only growth (TC-ORC-15), and two edge cases (TC-ORC-16–17). Security AC exemption confirmed; qa-permissions.json unchanged. Suite validates OK (44 entries). Commit: `18e4d9a1b`.

## Next actions
- Await next inbox dispatch for remaining APG/Release-B suite activations.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 30
- Rationale: Oracle's curse state machine is the most complex stateful mechanic in the APG class suite — 17 TCs covering 4-stage progression, overwhelmed blocking, refocus reset semantics, and irremovability prevent the entire oracle feature from shipping broken, which would be a high-visibility Release B defect.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-044532-suite-activate-dc-apg-class-oracle
- Generated: 2026-04-08T05:12:34+00:00
