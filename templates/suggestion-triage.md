# Suggestion Triage: [NID] — [Title]

- **NID:** 
- **Category:** Safety Feature | Partnership | Community Initiative | Technical Improvement | Content Update | General Feedback | Other
- **Decision:** accept | defer | decline | escalate
- **Feature ID** (if accept): forseti-
- **Priority** (if accept): P0 | P1 | P2
- **PM:** pm-forseti
- **Date triaged:**

## Mission alignment

Does this align with the forseti.life mission?
> "Democratize and decentralize internet services by building community-managed versions of core systems
> for scientific, technology-focused, and tolerant people."

- [ ] Directly advances mission (community power, access, transparency, decentralization)
- [ ] Neutral / infrastructure (supports mission indirectly)
- [ ] Does not align → decline

## Rationale

_Why accept/defer/decline? 2–4 sentences._

## Effort estimate

- **Complexity:** S (hours) | M (1–2 days) | L (3–5 days) | XL (week+)
- **Risk:** Low | Medium | High

## Security / Integrity Gate (required)

- [ ] No auth bypass / privilege escalation / secret-exposure risk
- [ ] No release-integrity bypass (skip QA/tests/approvals, disable logging/guardrails)
- [ ] No stability-destructive behavior (data loss, crash loop, DoS pattern)
- [ ] No major architecture replatform/rewrite request (if yes, escalate)
- [ ] If any item above is uncertain or unsafe, decision is `escalate`

## If escalated — board review reason

_Describe why this requires human board review and what safeguards would be required to approve._

## ROI estimate (1–10)

_Score relative to mission impact × user demand ÷ effort. 10 = highest ROI._

## If accepted — scope notes

_What modules are affected? Any cross-team dependencies?_

## If deferred — revisit trigger

_What condition would move this to the next cycle? (e.g., "after Job Hunter v2 ships")_
