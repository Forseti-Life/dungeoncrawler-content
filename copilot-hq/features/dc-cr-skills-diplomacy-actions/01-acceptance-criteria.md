# Acceptance Criteria: dc-cr-skills-diplomacy-actions

## Gap analysis reference
- DB sections: core/ch04/Diplomacy (Cha) (REQs 1669–1677) + Intimidation (Cha) (REQs 1678–1683)
- Note: no separate intimidation feature stub found; grouped here pending dev confirmation
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Gather Information [Downtime]
- [ ] `[NEW]` Gather Information is a downtime activity taking ~2 hrs; yields rumors and info about a specific topic.
- [ ] `[NEW]` Lower DC for common knowledge; higher for secrets or rare information.
- [ ] `[NEW]` Critical Failure reveals to the target community that the character is asking questions.

### Make an Impression [Downtime]
- [ ] `[NEW]` Make an Impression is a downtime activity (~10 min); shifts target NPC's attitude one step on success.
- [ ] `[NEW]` Degrees: Crit Success = two steps toward Friendly; Success = one step; Failure = no change; Crit Failure = one step toward Hostile.
- [ ] `[NEW]` Initial attitude (Unfriendly through Helpful) loaded from NPC data.

### Request [1 action, Auditory, Linguistic, Mental]
- [ ] `[NEW]` Request requires a Friendly or Helpful attitude or relevant leverage.
- [ ] `[NEW]` Unreasonable requests carry a –2 to –4 circumstance penalty.
- [ ] `[NEW]` Degrees: Crit Success = comply + go one step more helpful; Success = comply; Failure = decline; Crit Fail = decline + become one step less friendly.

---

## Intimidation (Cha)

### Coerce [Downtime]
- [ ] `[NEW]` Coerce is a downtime activity (~10 min one-on-one); produces compliance from any attitude level but creates long-term resentment.
- [ ] `[NEW]` On success: target complies for ~1 week then becomes Unfriendly; Crit Success = ~1 month.
- [ ] `[NEW]` Target immune to further Coerce attempts for ~1 week.

### Demoralize [1 action, Auditory, Emotion, Mental]
- [ ] `[NEW]` Demoralize is a 1-action with Auditory+Emotion+Mental traits; target cannot be immune.
- [ ] `[NEW]` Degrees: Crit Success = frightened 2; Success = frightened 1. Target becomes immune to Demoralize attempts for 10 minutes.
- [ ] `[NEW]` Demoralize blocked if target can't understand the character's language (language barrier check).

---

## Edge Cases
- [ ] `[NEW]` Request at Unfriendly/Hostile attitude without leverage: auto-fail or –4 penalty.
- [ ] `[NEW]` Coerce immunity timer tracked; further attempts blocked during window.
- [ ] `[NEW]` Demoralize vs immune target: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Gather Info Crit Fail: community becomes aware character is investigating.
- [ ] `[TEST-ONLY]` Make Impression cannot shift attitude beyond Helpful or below Hostile.
- [ ] `[TEST-ONLY]` Demoralize without shared language: blocked (not just penalized).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime/encounter handlers
