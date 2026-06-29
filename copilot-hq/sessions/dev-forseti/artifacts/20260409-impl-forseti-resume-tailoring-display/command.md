# Implement: forseti-jobhunter-resume-tailoring-display

- Release: 20260409-forseti-release-f
- Feature: `forseti-jobhunter-resume-tailoring-display`
- Module: `job_hunter`
- AC file: `features/forseti-jobhunter-resume-tailoring-display/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-resume-tailoring-display/03-test-plan.md`

## Summary
Polish the resume tailoring result at `/jobhunter/jobtailoring/{job_id}`. Side-by-side original/tailored view, PDF download button (when pdf_path set), save-to-profile action, confidence score, status state indicators (pending/processing/completed/failed). Builds on existing `tailored_resume` records and `job-tailoring-combined.html.twig`.

## Key ACs
- AC-1: Side-by-side columns in completed state (stacked < 768px)
- AC-2: PDF download button when pdf_path non-empty; hidden otherwise
- AC-3: "Save to profile" button triggers POST, updates profile resume field, shows confirmation
- AC-4: Confidence score badge rendered when score > 0
- AC-5: Processing/pending state shows spinner + progress message; completed state shows diff view

## Definition of done
- All ACs pass
- Commit hash + rollback note
- Twig template changes require `drush cr` after commit

## Verification
```bash
./vendor/bin/drush cr
curl -s https://forseti.life/jobhunter/jobtailoring/1  # check with auth
```
