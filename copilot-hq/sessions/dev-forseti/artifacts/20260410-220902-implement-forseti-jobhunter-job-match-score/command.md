- Status: done
- Completed: 2026-04-10T23:58:09Z

# Implement: forseti-jobhunter-job-match-score

- Agent: dev-forseti
- Release: 20260410-forseti-release-f
- Feature: forseti-jobhunter-job-match-score
- Module: job_hunter
- Date: 2026-04-10

## Task

Implement `forseti-jobhunter-job-match-score` per the acceptance criteria in `features/forseti-jobhunter-job-match-score/01-acceptance-criteria.md`.

## Summary

Surface a match score (0–100%) badge on each saved-job card at `/jobhunter/my-jobs`. Score is computed server-side using keyword intersection between the user's skills (`jobhunter_job_seeker.skills`) and the job's description/requirements tokens (`jobhunter_job_requirements`). `ProfileCompletenessService` already extracts skills; add scoring function and badge render.

## Score computation model

- Tokenize user skills (comma/space-separated from `jobhunter_job_seeker.skills`).
- Tokenize job description/requirements text.
- Score = (# skill tokens found in job text) / (# user skill tokens) × 100, clamped to [0, 100].
- 0 skills on profile → score = 0 + prompt to complete profile.

## Acceptance criteria (key items)

- **AC-1**: Each saved-job card at `/jobhunter/my-jobs` shows a match score badge.
- **AC-2**: Scores are user-scoped (user A and B see different scores for same job).
- **AC-3**: Score recomputes when user updates skills (not cached stale).
- **AC-4**: 0-skills user sees score=0 and a "Complete your profile" prompt.
- **AC-5**: Score clamped to [0, 100].
- **Full AC**: `features/forseti-jobhunter-job-match-score/01-acceptance-criteria.md`

## Verification

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'match-score'
```

## Scope

- Site: forseti.life
- Repo root: /home/ubuntu/forseti.life
- Module dir: sites/forseti/web/modules/custom/job_hunter/
- Tokenization strategy is Dev-owned; ACs specify observable output only.

## Done when

All AC items pass; code committed with verification evidence in outbox.
- Status: pending
