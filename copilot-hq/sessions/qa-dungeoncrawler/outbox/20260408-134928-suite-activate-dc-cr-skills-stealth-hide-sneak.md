- Status: done
- Summary: Suite activation complete for dc-cr-skills-stealth-hide-sneak. Added 20 TCs to qa-suites/products/dungeoncrawler/suite.json covering Conceal Object (Stealth vs. Seek DC), Hide (become Hidden, breaks on attack/spell), Sneak (move while Hidden, Undetected vs. Observed outcomes), Avoid Notice (exploration-mode Hidden start), and edge cases (already Undetected attempt, Stealth vs. Perception passive DC, bright-light penalty flag). No new HTTP routes; qa-permissions.json not updated. Validation confirmed OK (5 manifests). Committed 5059b9c0e.

## Next actions
- Write outbox for thievery; then unit test items

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 34
- Rationale: Stealth underpins Rogue and Scout gameplay; 20 TCs covering all four stealth actions and detection-state transitions de-risk visibility-state bugs before combat suites run.
