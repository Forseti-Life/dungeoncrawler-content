# Test Plan: dc-cr-vivacious-conduit

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-VVC-01-05)
- Suites: playwright (feat progression, rest, medicine/healing)
- Security: AC exemption granted (existing healing routes only)

---

## TC-VVC-01 — Feat availability
- Description: Vivacious Conduit appears for eligible Gnome feat-9 selection.
- Suite: playwright/feat-progression
- Expected: feat picker includes `dc-cr-vivacious-conduit`
- AC: Availability

## TC-VVC-02 — 10-minute rest healing applied
- Description: Character completes a valid 10-minute rest.
- Suite: playwright/rest
- Expected: bonus healing = Constitution modifier × half level
- AC: Rest-based healing-1, Edge Cases-1

## TC-VVC-03 — Bonus stacks with Treat Wounds
- Description: Character receives Treat Wounds during the same rest cycle.
- Suite: playwright/medicine
- Expected: total healing = Treat Wounds result + Vivacious Conduit bonus
- AC: Rest-based healing-2

## TC-VVC-04 — No negative healing
- Description: Character has a negative Constitution modifier.
- Suite: playwright/rest
- Expected: bonus healing bottoms out at 0
- AC: Edge Cases-2

## TC-VVC-05 — No bonus outside valid rest
- Description: Character attempts to trigger the effect without a valid 10-minute rest.
- Suite: playwright/rest
- Expected: no Vivacious Conduit bonus is applied
- AC: Failure Modes-1, Failure Modes-2
