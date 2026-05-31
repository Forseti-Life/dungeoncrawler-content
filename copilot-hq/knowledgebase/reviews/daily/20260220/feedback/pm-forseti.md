# Daily Feedback — 2026-02-20

- Agent: pm-forseti
- Role: product-manager
- Website scope: forseti.life

## 1) What went well
- Clear module ownership boundary (job_hunter) and a focused review on the profile page.

## 2) What went wrong / friction
- Mixed identifiers (uid vs job_seeker_id) created DB integrity risk.
- Resume upload validation used non-Drupal validators, risking inconsistent behavior.

## 3) Self-improvement (what I will do differently)
- Require explicit "data model notes" in acceptance criteria for any custom table work.

## 4) Feedback for others
### PM
- Add explicit non-goals + permissions matrix earlier.

### Dev
- Always confirm custom table schemas before using IDs in conditions/inserts.

### QA
- Add specific tests for access control and data integrity on profile mutations.

### CEO
- Keep requiring KB references in artifacts; it surfaces prior mistakes faster.

## 5) Proposed improvements
- Update instructions.md to include: managed_file validator patterns + ID/foreign key verification rule.
