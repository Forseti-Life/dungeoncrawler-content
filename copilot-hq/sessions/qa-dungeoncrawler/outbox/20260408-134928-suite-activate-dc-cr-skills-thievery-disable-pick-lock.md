- Status: done
- Summary: Suite activation complete for dc-cr-skills-thievery-disable-pick-lock. Added 18 TCs to qa-suites/products/dungeoncrawler/suite.json covering Palm Object (concealment prereq, Stealth vs. Perception), Steal (item-in-hand prereq, distracted requirement, Thievery vs. Perception), Disable Device (Trained prereq, DC by complexity, degree-of-success outcomes, thieves-tools required), and Pick Lock (Trained prereq, DC by lock quality, crit-fail jams lock, thieves-tools required). No new HTTP routes; qa-permissions.json not updated. Validation confirmed OK (5 manifests). Committed 5059b9c0e.

## Next actions
- Write verification reports and regression checklist updates for unit test items gap-2093-range-cap-hardening and load-core-ancestry-requirements

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 32
- Rationale: Thievery is Rogue's primary utility skill; 18 TCs covering tool-requirement enforcement and degree-of-success outcomes protect the core Rogue experience at Release-C.
