Now create the AC files for each:

Committed as `6601ac6a`.

- Status: done
- Summary: Groomed 3 new features from the BA refactor inventory for `20260405-forseti-release-b` (the next release after release-c). `suggestion-intake.sh` returned no new community suggestions, so grooming sourced the remaining untracked BA inventory items: `forseti-jobhunter-schema-fix` (JH-R5, field_age_18_or_older db_column mismatch, ROI 6), `forseti-ai-service-refactor` (AI-R1, AIApiService DB query extraction, ROI 8), and `forseti-jobhunter-controller-refactor` (JH-R2, JobApplicationController Phase 1 repository extraction, ROI 15). Feature briefs and AC documents were created for all three; QA testgen inbox items were dispatched via `pm-qa-handoff.sh`. Commit: `6601ac6a`.

## Next actions
- qa-forseti: write `03-test-plan.md` for all 3 new features (inbox items dispatched)
- When QA signals testgen complete (`qa-pm-testgen-complete.sh`), features move to `ready` pool and become eligible for Stage 0 of `20260405-forseti-release-b`
- Remaining BA items deferred: JH-R3 (UserProfileForm extraction, ROI 12) — scoped for a future grooming cycle to avoid overloading the next release
- Monitor release-c auto-close trigger (≥10 features or ≥24 hours since 2026-04-05T18:26 UTC)

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: JobApplicationController refactor (JH-R2, ROI 15) is the highest-value item in this grooming batch; it unblocks testability of the job hunter module's most complex file and reduces ongoing defect risk at 4177 lines.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260405-groom-20260405-forseti-release-b
- Generated: 2026-04-05T21:07:50+00:00
