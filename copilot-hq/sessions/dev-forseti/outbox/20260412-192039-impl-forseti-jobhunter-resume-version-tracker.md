Let me update `resumeSourceSave()` to handle the new fields, then run drush and commit:

Good. Now run drush updb and cr:

Schema is good. Now commit:

Now update the implementation notes and write the outbox:

Now commit the HQ notes and write the outbox:

- Status: done
- Summary: Implemented `forseti-jobhunter-resume-version-tracker` for release-e. Added `hook_update_9057` which adds `submitted_resume_id` (int nullable) and `submitted_resume_type` (varchar 16 nullable) to `jobhunter_applications` and migrates existing `source_resume_id` values as type=base. Rewrote `viewJob()` resume section to display the submitted resume with a type badge and a grouped dropdown covering both base and tailored resumes. Rewrote `resumeSourceSave()` to accept the new fields with dual ownership checks (base: via job_seeker_id; tailored: via uid) plus an application-row ownership check (SEC-3). Added "Used in applications" table to `resumeVersionForm()` (AC-3, SEC-4). Drush updb + cr ran clean. Committed: `5e6c16eed` (code), `ef34de524` (HQ notes).

## Next actions
- Signal qa-forseti for Gate 2 verification of this feature
- QA verify commands: `drush sql:query "DESCRIBE jobhunter_applications" | grep submitted` and POST to `resume_source_save` with a cross-user resume id to confirm 403

## Blockers
- None

## ROI estimate
- ROI: 20
- Rationale: Resume tracking closes a critical gap — users applying to jobs had no record of which resume version was used, undermining tailored-resume value. Enables AC-1/2/3/4/5 with full security compliance. Unblocks QA gate for release-e.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260412-192039-impl-forseti-jobhunter-resume-version-tracker
- Generated: 2026-04-12T20:17:07+00:00
