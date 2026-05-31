# Flow 4: Error Queue Implementation - Process Flow Validation

**Status:** 🔴 IN PROGRESS  
**Validation Date:** February 19, 2026  
**Dependencies:** Flow 7 (User Profile Forms) ✅ COMPLETE

---

## I. Process Flow Dependency Validation

### A. Flow 7 Completion ✅
**Requirement:** User profiles must be functional so that error queue can reference affected users

**Validation Checklist:**
- ✅ UserJobProfileService implemented and tested
- ✅ User form alterations complete
- ✅ Profile completeness calculation working
- ✅ User fields created and functional
- ✅ Route /jobhunter/profile/summary available
- ✅ All 11 profile fields working

**Status:** READY - Flow 7 provides the user entity references needed for error queue

---

### B. Flow 4 Process Flow Architecture

```
Queue Processing Flow:
┌──────────────────────────────────────────┐
│ 1. Automation Task Fails                 │
│    (Resume tailoring, Job scraping, etc) │
└──────────────────────────────────────────┘
                    │
                    ▼
┌──────────────────────────────────────────┐
│ 2. ErrorQueueService.logError() called   │
│    - Create error_queue node             │
│    - Reference affected user             │
│    - Store error message + data          │
│    - Set priority & type                 │
└──────────────────────────────────────────┘
                    │
                    ▼
┌──────────────────────────────────────────┐
│ 3. Error stored in database              │
│    field_error_message                   │
│    field_error_type                      │
│    field_priority                        │
│    field_status                          │
│    field_fixed (checkbox)                │
└──────────────────────────────────────────┘
                    │
                    ▼
┌──────────────────────────────────────────┐
│ 4. Admin views error queue               │
│    Route: /admin/job-hunter/errors       │
│    List shows: message, type, user, date │
│    Filters: date range, type, company    │
│    Actions: Mark fixed, View details     │
└──────────────────────────────────────────┘
                    │
                    ▼
┌──────────────────────────────────────────┐
│ 5. Admin marks error as fixed OR         │
│    System retries automation             │
└──────────────────────────────────────────┘
```

### C. Process Flow Dependency Validation

**Where Errors Come From (Future Flows):**
1. **Resume Tailoring** (Flow 1 - Already Working)
   - AI generation failures
   - Token limit exceeded
   - Database write errors

2. **Job Discovery** (Flow 2 - Implemented)
   - API failures (SerpAPI, Adzuna, etc.)
   - Parsing errors
   - Rate limiting

3. **Job Matching** (Flow 3 - Implemented)
   - Matching algorithm failures
   - Database query errors

4. **Company Management** (Flow 8 - Pending)
   - Company data conflicts
   - Duplicate company entries

5. **Application Submission** (Flows 16-17 - Pending)
   - Form submission failures
   - Authentication failures
   - CAPTCHA blocking

6. **User Support** (Flow 5 - Pending)
   - User-reported issues

---

## II. Data Structure Validation

### Required Content Type: error_queue

**Field Requirements:**

| Field Name | Field Type | Purpose | Required? |
|------------|-----------|---------|-----------|
| title | String | Brief error description | Yes |
| field_error_message | Long text | Full error message | Yes |
| field_error_type | List (select) | Error category | Yes |
| field_priority | List (select) | Critical/High/Medium/Low | Yes |
| field_status | List (select) | New/In Progress/Resolved | Yes |
| field_fixed | Boolean | Checkbox to mark complete | Yes |
| field_error_data | Text (JSON) | Technical details | No |
| field_user_ref | Entity ref (user) | Affected user | No |
| field_company_ref | Entity ref (node:company) | Related company | No |
| field_job_ref | Entity ref (node:job_posting) | Related job | No |

**List Values to Create:**

Error Type:
- Authentication
- Scraping
- Submission
- Technical
- Validation

Priority:
- Low
- Medium
- High
- Critical

Status:
- New
- In Progress
- Resolved

---

## III. Content Type Creation Path

**Currently Implemented:**
- ❌ error_queue content type not yet created
- ❌ error_queue access control not configured
- ❌ error_queue fields not created

**Must Create:**
1. Content type: error_queue
   - Description: "System automation errors" 
   - Workflow: Enabled for status tracking
   - Comments: Disabled
   - Menu: Not offered

2. All 10 fields with proper field settings
3. Display modes for admin list and detail views
4. Form display for admin error creation

---

## IV. Implementation Tasks Breakdown

### Phase 1: Database & Structure (Day 1)
**Prerequisites:** None - starts immediately after Flow 7

**Tasks:**
1. Create error_queue content type in database
2. Create all 10 required fields
3. Set up field storage
4. Configure permissions

**Dependencies:** None (uses Drupal core APIs)

### Phase 2: Service & Admin Views (Day 2-3)
**Prerequisites:** Phase 1 complete

**Tasks:**
1. Create ErrorQueueService with logging
2. Create routes for list/detail views
3. Create ErrorQueueController
4. Create admin menu link
5. Register service in services.yml

**Dependencies:** error_queue content type

### Phase 3: Company Management (Day 3-4)
**Prerequisites:** error_queue content type

**Tasks:**
1. Create company content type (if not existing)
2. Create company form in admin
3. Implement company creation logic
4. Add company dropdown to error form

**Dependencies:** Company node type

### Phase 4: User Interface & Testing (Day 4-5)
**Prerequisites:** All structures in place

**Tasks:**
1. Create Views configuration for error list
2. Create CSS styling for error dashboard
3. Create toolbar widget with error count
4. Write PHPUnit tests
5. Write functional tests

**Dependencies:** All previous phases

---

## V. Process Flow Validation Points

Before starting implementation, verify:

**✅ Verified:**
- Flow 7 (User Profiles) complete and working
- Database connection available
- Drupal entity API functional

**Must Verify Before Phase 1:**
- [ ] Content type creation permission available
- [ ] Field storage backend working
- [ ] Node module enabled

**Must Verify Before Phase 2:**
- [ ] error_queue content type created successfully
- [ ] All required fields created with proper storage
- [ ] Field access control configured

**Must Verify Before Phase 3:**
- [ ] ErrorQueueService class loads without errors
- [ ] Service dependencies resolve correctly
- [ ] Routes configured properly

**Must Verify Before Phase 4:**
- [ ] Error nodes can be created programmatically
- [ ] Error logging works end-to-end
- [ ] Admin can access error queue routes

---

## VI. Integration Points with Other Flows

**Flow 4 Receives Errors From:**
1. Flow 1 (Resume Tailoring) ✅ Ready
2. Flow 2 (Job Discovery) ✅ Ready
3. Flow 3 (Job Matching) ✅ Ready
4. Flow 5 (Support Form) - Pending
5. Future flows (8-17)

**Flow 4 Provides Data To:**
1. Admin Dashboard (error metrics)
2. User Dashboard (error visibility - optional)
3. System monitoring tools
4. Error analytics

**Where ErrorQueueService.logError() is Called:**
- ResumeTailoringWorker (PHP exception handling)
- JobDiscoveryService (API failures)
- JobMatchingService (matching errors)
- ApplicationSubmissionWorker (submission errors)
- All future workers with error handling

---

## VII. Success Criteria (MVP)

**After Flow 4 Complete:**
- ✅ Admin can access `/admin/job-hunter/errors`
- ✅ Error list shows all error_queue nodes
- ✅ Filters work: date range, error type, company
- ✅ Admin can mark errors as fixed
- ✅ Admin toolbar shows error count badge
- ✅ Error details page displays full information
- ✅ Only admins can access error queue
- ✅ All errors properly logged when automation fails
- ✅ ErrorQueueService available for all workers

**Before Proceeding to Flow 5:**
- ✅ All 8 test methods pass
- ✅ Error logging tested end-to-end
- ✅ Admin interface usable and responsive
- ✅ Performance acceptable (< 100ms for error list)

---

## VIII. Next Process Flow Dependencies

**Flow 5 Requires:**
- ErrorQueueService (Flow 4 provides)
- User contact form for issue reporting
- Email notification system
- User-admin communication channel

**Flow 8 Requires:**
- Company content type (Flow 4 creates)
- Company form interface
- Company CRUD operations
- Company validation rules

**Flows 11-17 Require:**
- Error logging (Flow 4 provides)
- Automation error handling
- Retry mechanisms
- User feedback channels

---

## Implementation Status

**Current Phase:** Pre-implementation validation ✅  
**Next Phase:** Phase 1 - Database & Structure  
**Estimated Start:** Immediately  
**Estimated Completion:** February 22-24, 2026  
**Developer:** AI Agent  
**Blockers:** None identified

