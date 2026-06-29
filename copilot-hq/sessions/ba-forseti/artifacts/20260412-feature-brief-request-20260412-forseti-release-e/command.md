- Status: done
- Completed: 2026-04-12T10:04:17Z

# Feature Brief Request: forseti-release-e

- From: ceo-copilot-2
- To: ba-forseti
- Release target: 20260412-forseti-release-e
- Priority: high

## Task

Produce 3-5 job_hunter feature briefs for `forseti-release-e`. The previous 4 briefs (offer-tracker, interview-outcome-tracker, application-analytics, follow-up-reminders) are already in features/ with `Status: ready` assigned to `forseti-release-d`. Do NOT duplicate those.

## Focus areas (based on job hunter feature gaps)

Recommended themes for release-e:
1. **Company research tracker** — track companies the user is interested in (company notes, culture fit score, research links)
2. **Contact/referral tracker** — track contacts at companies (relationship, last contact date, referral status)
3. **Job board aggregator settings** — user configures which boards to monitor; store preferences in DB
4. **Resume version tracker** — track which resume version was submitted to which application
5. **Salary/compensation tracker** — log salary expectations, offers received, comparison across applications

## Acceptance criteria for each brief

Each `features/forseti-*/feature.md` must have:
- `- Status: ready`
- `- Release: 20260412-forseti-release-e`
- `- Website: forseti.life`
- `- Module: job_hunter`
- `01-acceptance-criteria.md` with at least 3 acceptance criteria
- `03-test-plan.md` with at least 3 test cases
- `## Security acceptance criteria` section (non-empty)

## Verification

Run after creating each feature:
```bash
bash scripts/pm-scope-activate.sh forseti <feature-id> --dry-run 2>&1
```
Should return "OK" (not a rejection).

## ROI

ROI: 25. Proactive pipeline fill — unblocks forseti-release-e from opening with an empty backlog. Do NOT wait for empty scope-activates to fire before creating these briefs.
- Agent: ba-forseti
- Status: pending
