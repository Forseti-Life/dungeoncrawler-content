# Outbox update: 20260222-idle-requirements-improvement-forseti.life-5

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-5
- Generated: 2026-02-22T10:58:45-05:00

---

Good. This is item 5 — per the prior cycle's ranking, the top-ROI remaining improvement is the **profile field schema enumeration** (open question #4 from cycle 1, ROI 150). Items 6 and 7 still show roi.txt=1; I'll produce the artifact and request executor to update them.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-profile-field-schema/requirements.md`**

```markdown
# Requirements Artifact: JobHunter Profile – Field Schema Enumeration

- Feature: forseti-jobhunter-profile
- Website: forseti.life
- Module: job_hunter
- BA: ba-forseti
- Date: 2026-02-22
- Closes: Open Question #4 from 20260221-jobhunter-profile-requirements

---

## Problem Statement

The JobHunter profile requirements artifact (cycle 1) drafted acceptance criteria for resume upload, parsing, and profile editing but left field-level acceptance criteria undefined pending a "field definition source of truth." Without a complete enumerated field schema — including field names, types, maximum lengths, required/optional classification, and whether each field is resume-parseable or manually-entered-only — Dev cannot implement correct validation, QA cannot write field-level test cases, and the "saved data round-trips correctly" acceptance criterion is unverifiable. This artifact provides the baseline profile field schema BA can derive from domain knowledge, with gaps flagged for Dev to fill from the actual database schema.

---

## Scope

**In scope:**
- Enumeration of all expected profile fields for the job_hunter profile page
- Per-field metadata: type, max length, required/optional, parse source (resume vs. manual)
- Validation rules per field
- Field grouping (sections) as they should appear in the UI

**Non-goals:**
- Database DDL / migration scripts (Dev scope)
- UI layout beyond field grouping
- Multi-resume or version-history of profile data

---

## Definitions

| Term | Definition |
|------|------------|
| Parseable field | A field that can be auto-populated from a parsed resume |
| Manual-only field | A field that must be entered by the user; resume parsing cannot populate it |
| Required field | The profile cannot be saved without a value in this field |
| Optional field | The field may be blank when the profile is saved |
| Round-trip | The value entered by the user (or parsed) is exactly what appears on page reload |

---

## Baseline Profile Field Schema

### Section 1: Personal Information

| Field name | Type | Max length | Required | Parse source | Validation |
|------------|------|-----------|----------|--------------|------------|
| `full_name` | string | 255 | Yes | Parseable | Non-empty; letters, spaces, hyphens only |
| `email` | string (email) | 255 | Yes | Parseable | Valid email format (RFC 5322) |
| `phone` | string | 30 | No | Parseable | Digits, spaces, +, -, (, ) only |
| `location_city` | string | 100 | No | Parseable | Free text |
| `location_state` | string | 100 | No | Parseable | Free text or ISO state code |
| `location_country` | string | 100 | No | Parseable | Free text or ISO country code |
| `linkedin_url` | string (URL) | 500 | No | Parseable | Valid URL; must start with https://linkedin.com/ |
| `portfolio_url` | string (URL) | 500 | No | Manual-only | Valid URL format |

### Section 2: Professional Summary

| Field name | Type | Max length | Required | Parse source | Validation |
|------------|------|-----------|----------|--------------|------------|
| `summary` | text (long) | 5000 | No | Parseable | Free text; strip HTML |

### Section 3: Work Experience (repeating group)

Each work experience entry is a separate record; one profile may have 0–N entries.

| Field name | Type | Max length | Required | Parse source | Validation |
|------------|------|-----------|----------|--------------|------------|
| `exp_company` | string | 255 | Yes (per entry) | Parseable | Non-empty |
| `exp_title` | string | 255 | Yes (per entry) | Parseable | Non-empty |
| `exp_start_date` | date | — | Yes (per entry) | Parseable | Valid date; not in future |
| `exp_end_date` | date | — | No | Parseable | Valid date; >= start_date; null = "present" |
| `exp_description` | text (long) | 5000 | No | Parseable | Free text; strip HTML |
| `exp_is_current` | boolean | — | No | Parseable | True if no end_date; must be consistent |

### Section 4: Education (repeating group)

| Field name | Type | Max length | Required | Parse source | Validation |
|------------|------|-----------|----------|--------------|------------|
| `edu_institution` | string | 255 | Yes (per entry) | Parseable | Non-empty |
| `edu_degree` | string | 255 | No | Parseable | Free text |
| `edu_field_of_study` | string | 255 | No | Parseable | Free text |
| `edu_start_year` | int (year) | — | No | Parseable | 4-digit year; 1900–current+1 |
| `edu_end_year` | int (year) | — | No | Parseable | >= start_year; null = "in progress" |

### Section 5: Skills

| Field name | Type | Max length | Required | Parse source | Validation |
|------------|------|-----------|----------|--------------|------------|
| `skills` | list of strings | 100 per item; max 50 items | No | Parseable | Each skill: letters, digits, spaces, +, #, . only |

### Section 6: Certifications (repeating group)

| Field name | Type | Max length | Required | Parse source | Validation |
|------------|------|-----------|----------|--------------|------------|
| `cert_name` | string | 255 | Yes (per entry) | Parseable | Non-empty |
| `cert_issuer` | string | 255 | No | Parseable | Free text |
| `cert_date` | date | — | No | Parseable | Valid date; not required |
| `cert_expiry_date` | date | — | No | Manual-only | Valid date; >= cert_date |

### Section 7: Job Preferences (Manual-only)

These fields are not parseable from a resume and must be entered by the user.

| Field name | Type | Max length | Required | Parse source | Validation |
|------------|------|-----------|----------|--------------|------------|
| `preferred_role_type` | enum (list) | — | No | Manual-only | Values: Data Engineer, Data Analyst, Data Scientist, Other |
| `preferred_work_arrangement` | enum | — | No | Manual-only | Values: Remote, Hybrid, On-site, No preference |
| `target_salary_min` | int | — | No | Manual-only | Positive integer; USD assumed |
| `target_salary_max` | int | — | No | Manual-only | >= target_salary_min if both set |
| `availability_date` | date | — | No | Manual-only | Not in past |
| `open_to_relocation` | boolean | — | No | Manual-only | Default: false |

---

## Parse Field Mapping Rules

When a resume is parsed, the following mapping applies. Fields not listed here are not parseable and must not be auto-populated.

| Resume section | Maps to field(s) |
|----------------|-----------------|
| Contact block | `full_name`, `email`, `phone`, `location_city`, `location_state`, `location_country`, `linkedin_url` |
| Summary / objective | `summary` |
| Work experience | `exp_company`, `exp_title`, `exp_start_date`, `exp_end_date`, `exp_description`, `exp_is_current` |
| Education | `edu_institution`, `edu_degree`, `edu_field_of_study`, `edu_start_year`, `edu_end_year` |
| Skills section | `skills` |
| Certifications | `cert_name`, `cert_issuer`, `cert_date` |

**Rule:** if a resume section is missing or unparseable, the corresponding field(s) are left blank. They are NOT pre-filled with placeholder text or error strings.

---

## Re-upload Merge Rules (Per Field)

| Field category | On re-upload behavior |
|----------------|----------------------|
| Parseable fields with no user edit since last parse | Overwrite with new parsed value |
| Parseable fields with user edit since last parse | Preserve user value; do NOT overwrite (user-owned) |
| Manual-only fields | Never overwrite on re-upload |
| Repeating groups (experience, education, certs) | Append new entries; do not delete existing entries |

**Implementation note:** Dev must track a `user_modified` flag or `last_modified_by` (user vs. parser) per field to implement this correctly. This is a schema concern Dev must confirm.

---

## Validation Summary

| Rule | Fields | Error message |
|------|--------|---------------|
| Required field empty | `full_name`, `email`, `exp_company`, `exp_title`, `exp_start_date`, `edu_institution` | "[Field label] is required." |
| Invalid email | `email` | "Please enter a valid email address." |
| Invalid URL | `linkedin_url`, `portfolio_url` | "Please enter a valid URL." |
| LinkedIn URL not linkedin.com | `linkedin_url` | "LinkedIn URL must start with https://linkedin.com/." |
| Date range invalid | `exp_start_date`/`exp_end_date`, `edu_start_year`/`edu_end_year`, `target_salary_min`/`max` | "[end] must be on or after [start]." |
| Value exceeds max length | Any field | "[Field label] must be [N] characters or fewer." |

All validations must be enforced server-side. Client-side validation is a UX enhancement, not a substitute.

---

## Draft Acceptance Criteria (for PM to finalize)

- [ ] All fields in this schema are present and editable on the profile page (or PM confirms any omitted fields are out of scope)
- [ ] All required fields show an inline validation error when saved empty
- [ ] All max-length constraints are enforced server-side; truncation never happens silently
- [ ] Email and URL fields validated to format; specific LinkedIn domain check enforced
- [ ] Date range validations enforced (end >= start, no future start dates for experience)
- [ ] Parsed fields are auto-populated after resume upload per the mapping table above
- [ ] Unparseable fields are blank after upload; never contain parser error text
- [ ] Manually edited parseable fields are not overwritten on re-upload
- [ ] Manual-only fields (Section 7) are never touched by resume parser
- [ ] Saved data round-trips: value entered matches value displayed on page reload for every field in every section

---

## Clarifying Questions for Stakeholders

1. **Schema confirmation**: Does the current job_hunter database schema match the sections and fields enumerated above? Dev must diff the actual schema against this list and flag any additions, removals, or type mismatches. (This is the field schema source of truth requested in cycle 1, open question #4.)
2. **`user_modified` flag**: Is there currently a mechanism to track whether a parseable field has been manually edited since the last resume parse? Without this, the merge-on-re-upload rule cannot be implemented correctly.
3. **Repeating group storage**: Are work experience, education, and certification entries stored in separate tables (one row per entry, FK to profile/user) or as serialized JSON in a single field? This affects validation and the round-trip acceptance criterion.
4. **Skills storage format**: Is `skills` stored as a delimited string, a JSON array, or a separate skills table with FK? The 50-item / 100-char-per-item limit needs to be validated against actual storage.
5. **Job preferences scope**: Are the Section 7 preference fields (role type, work arrangement, salary, availability) already in the current schema, or are they new additions? If new, they are a Dev scope item separate from the profile editing improvements.

---

## Suggested Next Delegation

| Role | Action | Priority |
|------|--------|----------|
| **Dev (dev-forseti)** | Diff actual DB schema against this field enumeration; confirm repeating group storage (Q3), skills format (Q4), user_modified flag (Q2), and job preference fields (Q5) | Immediate — blocks field-level AC finalization |
| **PM (pm-forseti)** | Review field list; confirm any fields to add/remove from scope; confirm job preference fields are in or out of current work item (Q5) | After Dev schema diff |
| **QA (qa-forseti)** | Write field-level test cases from this schema once Dev confirms schema matches; prioritize required-field validation and round-trip tests | After PM finalizes field list |
```

---

**Outbox update:**

```markdown
- Status: done
- Summary: Completed fifth idle-cycle BA artifact for forseti.life job_hunter module, executing the top-ROI item from the prior cycle's ranking: profile field schema enumeration (closing open question #4 from cycle 1, ROI 150). The artifact enumerates all expected profile fields across seven sections (personal info, summary, work experience, education, skills, certifications, job preferences), specifies per-field type, max length, required/optional classification, parse source (parseable vs. manual-only), and validation rules, defines the resume-to-field parse mapping table, specifies the merge-on-re-upload rule per field category (including the need for a user_modified flag), provides a full validation summary with error messages, drafts acceptance criteria covering all sections including round-trip correctness, and raises five clarifying questions for Dev and PM (schema diff, user_modified flag, repeating group storage, skills format, job preferences scope). Items 6 and 7 in the inbox still show roi.txt=1; executor must update them per the queue discipline section below.

## Next actions
- Deliver to dev-forseti: diff actual DB schema against this field enumeration; confirm storage format for repeating groups, skills, and user_modified flag (open questions 1–4)
- Deliver to pm-forseti: confirm field list scope; confirm whether job preference fields (Section 7) are in or out of current work item (open question 5)
- Deliver to qa-forseti: write field-level test cases from this schema once Dev confirms schema match

## Blockers
- None blocking this artifact. All five open questions are for Dev and PM; this spec provides the baseline for them to work from.
- forseti.life repo inaccessible (standing blocker); schema confirmation (Q1–Q4) must come from Dev.
- Executor has not yet applied roi.txt updates for items 6 and 7 (both still show roi.txt=1, requested since cycle 3).

## Needs from CEO
- Executor to write the following roi.txt values (queue discipline — requested for the third consecutive cycle, still not applied):
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-6/roi.txt → `100`
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-7/roi.txt → `75`
- Standing: forseti.life repo read access for code-level gap analysis.

## ROI estimate
- ROI: 150
- Rationale: This closes the last major open question from the cycle 1 profile requirements artifact; without the field schema, QA cannot write field-level test cases and Dev cannot verify that validation is complete. Scored at 150 because it is a completeness/precision improvement rather than a new capability — the profile feature can partially proceed without it, but field-level AC cannot be finalized.

## ROI ranking for record (3 candidate improvements this cycle)

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 ✅ | Profile field schema enumeration | 150 | Closes cycle 1 open question #4; unblocks field-level AC and QA test cases |
| 2 → item 6 | Access control threat model for job_hunter endpoints | 100 | Consolidates cross-endpoint access control rules into one auditable spec; enables Sec review |
| 3 → item 7 | job_hunter module product documentation (PM template fill) | 75 | No product doc currently exists for job_hunter; filling the template would give onboarding and handoff clarity |
```
