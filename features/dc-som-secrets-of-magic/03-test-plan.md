# Test Plan: dc-som-secrets-of-magic

## Coverage summary
- AC items: 15 (Magus, Spellstrike, Summoner, Eidolon, expanded spell systems, ownership and server authority)
- Test cases: 7 (TC-SOM-01-07)
- Suites: Playwright character/encounter flows + phpunit class/spell-state validation
- Security: character ownership enforced for class state, Spellstrike state, and Eidolon mutations

---

## TC-SOM-01 — Magus class selection and Hybrid Study persistence
- Description: Create or edit a character into Magus and select a Hybrid Study.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: Magus is selectable, Hybrid Study validates server-side, and Magus-specific stateful features become available to the owning character.
- AC: Magus Class 1-3

## TC-SOM-02 — Spellstrike charge, strike, and recharge flow
- Description: Execute Spellstrike, observe hit/miss behavior, then attempt a second Spellstrike before and after recharge.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: Spellstrike binds a valid spell to a strike, resolves hit/miss rules correctly, blocks reuse while uncharged, and reopens only after the documented recharge path.
- AC: Spellstrike and Recharge Flow 1-3, Edge Cases 1-2

## TC-SOM-03 — Arcane Cascade state is character-scoped
- Description: Enter Arcane Cascade after eligible Magus actions.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: Arcane Cascade is tracked on the owning character and enforced by server-side encounter logic.
- AC: Magus Class 3

## TC-SOM-04 — Summoner creates a valid Eidolon
- Description: Create a Summoner and select an Eidolon type.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: the Eidolon is created as a character-scoped entity tied only to the owning Summoner and valid Eidolon enums persist correctly.
- AC: Summoner and Eidolon 1-2, Failure Modes 1

## TC-SOM-05 — Summoner and Eidolon share HP and actions correctly
- Description: Use Summoner and Eidolon in encounter flow, including Act Together and incapacitation/dismissal edge cases.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: HP and shared action state stay synchronized, Eidolon cannot detach to another character, and encounter state remains coherent during absence/incapacitation cases.
- AC: Summoner and Eidolon 3-4, Edge Cases 3-4

## TC-SOM-06 — Expanded spell-system metadata integrates with the shared spell model
- Description: Load Secrets of Magic spell/tradition data into the shared spell infrastructure.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: new traditions/classifications are stored and queried through the shared spell model without breaking existing spell-list behavior.
- AC: Expanded Magic Systems 1-2

## TC-SOM-07 — Ownership and client-state tampering are rejected
- Description: Attempt invalid Hybrid Study/Eidolon submissions and client-side Spellstrike/shared-action tampering.
- Suite: `dc-som-secrets-of-magic-e2e`
- Expected: invalid enums produce explicit validation errors, non-owners receive 403, and conflicting client-submitted combat state is ignored or rejected.
- AC: Access and Rules Integrity 1-2, Failure Modes 2-3
