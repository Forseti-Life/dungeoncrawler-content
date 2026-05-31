# Job Hunter Module - Comprehensive Gap Analysis & Automation Plan

**Document Created:** February 2026  
**Last Updated:** February 2026  
**Status:** Active Development Planning  
**Priority:** PUBLIC RELEASE MVP BLOCKER

---

## Executive Summary

The Job Hunter module has **strong foundation** with AI Resume Tailoring fully working, but faces **critical gaps** in core user workflows and employer automation. The module is approximately **30% complete** for MVP scope.

### Current Status by Flow

| Flow | Name | Status | Priority | Blocker |
|------|------|--------|----------|---------|
| 1 | Module Initialization | ✅ COMPLETED | High | No |
| 2 | Configuration | ✅ COMPLETED | High | No |
| 3 | Job Discovery & Application | 🔄 PARTIAL | HIGH | **YES** |
| 4 | Admin Error Queue | ❌ TODO | HIGH | **YES** |
| 5 | User Support | ❌ TODO | MEDIUM | **YES** |
| 6 | Performance Monitoring | 📋 NOTED | LOW | No |
| 7 | User Profile Management | ❌ TODO | HIGH | **YES** |
| 8 | Employer Management | ❌ TODO | HIGH | **YES** |
| 9 | Application Tracking | ❌ TODO | MEDIUM | **YES** |
| 10 | AI Model Training | 🚫 SHELVED | LOW | No |
| 11 | Web Scraping (Diffbot) | ❌ TODO | HIGH | **YES** |
| 12 | Security & Compliance | 🟡 BASIC | LOW | No |
| 13 | Analytics & Reporting | 🚫 SHELVED | LOW | No |
| 14 | Market Intelligence | 🚫 SHELVED | LOW | No |
| 15 | Third-Party Integrations | 🚫 SHELVED | LOW | No |
| 16 | J&J Job Search | ❌ TODO | HIGH | **YES** |
| 17 | J&J Application | ❌ TODO | HIGH | **YES** |

**Total Blockers for MVP**: 7 critical flows  
**Estimated Timeline**: 6-8 weeks (concurrent development)

---

## PART 1: COMPLETED COMPONENTS (Ready for Production)

### Flow 1-2: Module Infrastructure ✅ COMPLETED
- ✅ Module installation and enablement system
- ✅ All content types created (Company, Job Posting, Application, Error Queue, Tailored Resume)
- ✅ User entity extended with 24 custom fields
- ✅ Database tables created and versioned
- ✅ Configuration management implemented
- ✅ Administrative views for content management

### Flow 3 - PARTIAL: Job Discovery & Application
**Working Components:**
- ✅ **AI Resume Tailoring** - FULLY WORKING
  - AJAX-powered `/tailor-resume/{job}` endpoint
  - AWS Bedrock Claude 3.5 Sonnet integration
  - Development environment with mock responses
  - Production environment with real AI processing
  - Creates `tailored_resume` nodes automatically
  - JavaScript frontend with loading states and error handling

- ✅ **Job Posting Creation**
  - Standard Drupal node creation at `/node/add/job_posting`
  - Fields for title, description, company ref, application link
  - Manual tailoring interface working at `/user/{uid}/tailor-resume/{job_id}`
  - Resume text extraction from PDF/DOCX files

**MISSING Components (Phase 3):**
- ❌ **Job Discovery** - Web scraping/Diffbot integration not started
- ❌ **Job Matching Algorithm** - User profile matching not implemented
- ❌ **Job Display Dashboard** - User-facing job browser not built
- ❌ **Automated Application Submission** - Browser automation not started
- ❌ **Error Handling & Fallback** - Manual fallback workflow not implemented
- ❌ **Application Tracking** - Application history dashboard missing

---

## PART 2: CRITICAL MVP GAPS (Immediate Implementation Needed)

### Gap 1: User Profile Management (Flow 7) ❌ TODO - MVP PRIORITY

**Current State:**
- User entity extended with 24 field definitions
- Fields created in database
- BUT: No user registration customization, no profile UI, no form integration

**Implementation Needed:**
```
User Registration & Profile Creation:
├── Extend user registration form with custom fields
├── Profile edit form at /user/{uid}/edit
├── Resume upload with file validation (PDF/DOCX)
├── Work authorization, salary, availability, keywords fields
├── Profile completeness percentage tracking
└── Validation rules for required fields
```

**Impact:** Users cannot complete their profiles → Cannot use job matching → System blocked

**Estimated Effort:** 3-4 days

---

### Gap 2: Admin Error Queue & Company Management (Flow 4) ❌ TODO - MVP PRIORITY

**Current State:**
- `error_queue` content type exists
- Company nodes can be created but no admin interface

**Implementation Needed:**
```
Admin Error Queue Dashboard:
├── Simple list view of all errors
├── Show: user, company, error_message, timestamp
├── Checkbox to mark errors as "fixed"
├── Basic filtering by date/company
├── Admin link to Error Queue from admin menu
└── Automatic error detection when jobs/applications fail

Admin Add Company Function:
├── "Add Company" button in admin panel
├── Form for company name, website, careers URL
├── Active/inactive toggle for job scraping
├── Admin notes field for scraping configuration
└── Auto-populate in error queue when issues occur
```

**Impact:** Admins have no visibility into system failures → Cannot manage automation issues

**Estimated Effort:** 4-5 days

---

### Gap 3: User Support Contact Form (Flow 5) ❌ TODO - MVP PRIORITY

**Current State:**
- No contact form implemented

**Implementation Needed:**
```
User Support Contact Form:
├── Accessible from user dashboard
├── Fields: Name, Email, Issue Type, Subject, Message
├── Issue Type dropdown: Technical, Account, General Question
├── Form validation and required fields
├── Email notification to admin@forseti.life
├── Optional: Webform integration for advanced features
└── Simple issue tracking (future enhancement)
```

**Impact:** Users have no way to report problems → Support requests lost

**Estimated Effort:** 1-2 days (using Drupal Contact or Webform module)

---

### Gap 4: Employer/Company Management (Flow 8) ❌ TODO - MVP PRIORITY

**Current State:**
- Company nodes exist but no user-facing interface
- No user-company relationship management

**Implementation Needed:**
```
Company Selection & Management:
├── Company list view at /companies
├── User can select target companies (entity reference field)
├── "My Companies" dashboard showing user's selected companies
├── Pause/resume job monitoring per company (boolean field)
├── Company details page showing:
│  ├── Company info (size, industry, website)
│  ├── Number of active job postings
│  ├── Recent job discovery activity
│  └── Application statistics for that company
└── Admin interface to add/edit companies
```

**Impact:** Users cannot select employers → No company focus → Cannot trigger scraping

**Estimated Effort:** 3-4 days

---

### Gap 5: Application Tracking (Flow 9) ❌ TODO - MVP PRIORITY

**Current State:**
- Application content type exists
- No user interface for tracking applications

**Implementation Needed:**
```
Application Tracking Dashboard:
├── List view of user's all applications
├── Show: Job Title, Company, Application Date, Status
├── Status values: Submitted, Under Review, Interview, Rejected, Offer, Archived
├── Archive/unarchive applications
├── Search/filter by company, date, status
├── Individual application detail page showing:
│  ├── Job details & requirements
│  ├── Resume used for application
│  ├── Application date/time
│  ├── Current status
│  └── User notes field
└── Ability to add notes to applications
```

**Impact:** Users cannot track their applications → No feedback on automation success

**Estimated Effort:** 3-4 days

---

### Gap 6: Web Scraping with Diffbot API (Flow 11) ❌ TODO - MVP PRIORITY

**Current State:**
- No web scraping infrastructure implemented
- No Diffbot integration started

**Implementation Needed:**
```
Diffbot Integration for Job Discovery:
├── Store Diffbot API key in environment variable (DIFFBOT_API_KEY)
├── Service: DiffbotScrapingService
│  ├── Method: scrapeJobsFromCareersPage(company_url)
│  ├── Returns: Array of structured job posting data
│  ├── Fields: title, description, requirements, salary, location, url, posting_date
│  └── Error handling for API failures
├── Webhook receiver for real-time job updates from Diffbot
├── Save scraped jobs as Job Posting nodes
├── Duplicate detection (don't create duplicate job postings)
├── Admin interface to trigger manual scraping
└── Scheduled scraping (daily, per company)

Initial Implementation - Johnson & Johnson Focus:
├── Configure Diffbot for careers.jnj.com
├── Test job discovery workflow  
├── Validate data quality and completeness
└── Prepare for multi-employer scaling
```

**Impact:** No job discovery → Users have no jobs to apply to → System non-functional

**Estimated Effort:** 5-7 days

---

### Gap 7: Automated Application Submission (Flow 17) ❌ TODO - MVP PRIORITY

**Current State:**
- No browser automation framework implemented
- No form completion logic started

**Implementation Needed:**
```
Browser Automation Framework (Playwright/Puppeteer):
├── Service: ApplicationSubmissionService
├── Capabilities:
│  ├── Launch browser with user context
│  ├── Navigate to job application URL
│  ├── Handle authentication (login, MFA)
│  ├── Fill application forms intelligently
│  ├── Upload resume/cover letter documents
│  ├── Submit applications
│  ├── Capture confirmation screens
│  └── Return success/failure status
├── Queue Worker: ApplicationSubmitterQueueWorker
│  ├── Processes queued applications
│  ├── Triggers browser automation
│  ├── Captures errors and stores in error_queue
│  └── Updates application status after submission
└── Error Handling:
   ├── Detect form changes/structure issues
   ├── Handle CAPTCHA challenges → fallback to manual
   ├── Manage rate limiting and retries
   └── Log detailed error info for admin review

For Johnson & Johnson MVP:
├── Test form field mapping for J&J application
├── Validate credential handling security
├── Implement manual fallback workflow
└── Test with sample applications
```

**Impact:** Cannot submit applications → Users must do everything manually → Defeats purpose

**Estimated Effort:** 8-10 days (complex)

---

### Gap 8: Job Search & Discovery Interface (Flows 3, 16) ❌ TODO - MVP PRIORITY

**Current State:**
- No job browser/discovery interface

**Implementation Needed:**
```
User Job Browser Dashboard:
├── List all available jobs from selected companies
├── Fields shown: Title, Company, Relevance Score, Salary (if available), Location
├── Filter/sort: Company, Title, Salary, Location, Date Posted, Relevance
├── Relevance scoring (comparing job to user profile)
├── Job detail view showing:
│  ├── Full description & requirements
│  ├── AI analysis of user fit
│  ├── Tailored resume preview
│  └── "Apply Now" or "Review First" buttons
├── Search functionality across job descriptions
└── Save/bookmark jobs for later review

For Johnson & Johnson MVP:
├── Show all J&J jobs discovered via Diffbot
├── Enable filtering by department/location
├── Test user matching algorithm
└── Validate job display and sorting
```

**Impact:** Users cannot see/browse available jobs → Cannot make informed application decisions

**Estimated Effort:** 4-5 days

---

## PART 3: DEVELOPMENT PRIORITY & SEQUENCING

### Phase 1: Foundation (Week 1-2) - MVP Minimum Viable Product
**Goal:** Get users through complete profile → job application workflow

#### Week 1: User Safety & Admin Visibility
1. **Flow 7: User Profile Forms** (Days 1-3)
   - Drupal user registration form customization
   - Profile edit form integration
   - Resume file upload with validation
   - All 24 profile fields properly validated

2. **Flow 4: Admin Error Queue** (Days 2-4)
   - Error Queue admin list view
   - Mark-as-fixed checkbox functionality
   - Company management add interface
   - Basic error filtering

3. **Flow 5: User Support Contact Form** (Day 5)
   - Drupal contact form or Webform setup
   - Email notification to admins
   - Basic submission tracking

#### Week 2: Employer Management & Job Display
4. **Flow 8: Company Management** (Days 6-7)
   - User company selection interface
   - "My Companies" dashboard
   - Pause/resume monitoring controls

5. **Flow 9: Application Tracking** (Days 8-10)
   - Application history dashboard
   - Archive/unarchive functionality
   - Basic filtering and search

### Phase 2: Job Discovery & Automation (Week 3-4) - Core Automation
6. **Flow 11: Web Scraping (Diffbot)** (Days 11-14)
   - Diffbot API integration (J&J focus)
   - Job posting node creation from scraped data
   - Duplicate detection
   - Real-time webhook processing

7. **Flow 3 (Phase 1): Job Discovery Interface** (Days 12-15)
   - Job browser dashboard
   - Relevance scoring algorithm
   - Job detail pages
   - Search and filtering

### Phase 3: Application Automation (Week 5-6) - Browser Automation
8. **Flow 17: Browser Automation** (Days 16-21)
   - Playwright framework setup
   - J&J application form automation
   - MFA handling, error capture
   - Manual fallback workflow

9. **Flow 3 (Phase 4-6): Application Submission & Tracking** (Days 18-25)
   - Automated submission queue
   - Status tracking and confirmations
   - Follow-up email management

---

## PART 4: TECHNICAL IMPLEMENTATION DETAILS

### Database Tables That Exist
These tables are already created and ready:
- `jobhunter_companies` - Company records
- `jobhunter_job_seeker` - User profiles
- `jobhunter_job_seeker_resumes` - Resume versions
- `jobhunter_tailored_resumes` - AI-generated resumes
- `jobhunter_job_requirements` - Parsed job requirements
- `jobhunter_google_jobs_sync` - Integration metadata

### Services Already Implemented
- `JobSeekerService` - User profile management
- `ResumeTailoringService` - AI resume generation
- `UserProfileService` - Profile operations
- `JobDiscoveryService` - Job matching (partial)
- `SearchAggregatorService` - Search operations
- `GoogleJobsService` - Integration ready

### Services Need Implementation
- **DiffbotScrapingService** - Web scraping via Diffbot API
- **ApplicationSubmissionService** - Browser automation
- **ApplicationFormFillerService** - Form field mapping
- **ErrorQueueService** - Error management and queuing
- **JobMatchingService** - User-job relevance scoring
- **NotificationService** - Email/alert generation

### Queue Workers Already Exist (Partial)
- `ResumeTailoringWorker` - Queue-based resume generation
- `ResumeTextExtractionWorker` - Resume text parsing
- `ResumeGenAiParsingWorker` - Resume data extraction
- `CoverLetterTailoringWorker` - Cover letter generation

### Queue Workers Need Implementation
- **ApplicationSubmitterQueueWorker** - Application submission automation
- **JobScrapingQueueWorker** - Scheduled job discovery
- **ErrorQueueProcessorWorker** - Error handling and notifications
- **JobMatchingQueueWorker** - Relevance scoring and notifications

### Environment Variables Required
```bash
# Already handled in public release
DB_PASSWORD=...
ADMIN_PASSWORD=...

# Job Hunter specific (NEW)
DIFFBOT_API_KEY=...               # Diffbot web scraping API
AWS_REGION=us-west-2              # For Claude/Bedrock
CLAUDE_MODEL_ID=claude-3-5-sonnet # AI model selection
```

---

## PART 5: FILE STRUCTURE REFERENCE

### Module Location
```
/sites/forseti/web/modules/custom/job_hunter/
├── job_hunter.module                    # Hook implementations
├── job_hunter.install                   # Installation hooks (update 8006, 8007, 8008, etc.)
├── job_hunter.info.yml                  # Module info
├── src/
│   ├── Service/                         # Services (30+ services)
│   ├── Plugin/
│   │   ├── QueueWorker/                 # Queue workers (4 exist, more needed)
│   │   ├── Block/                       # Navigation block
│   │   └── Field/                       # Custom field widgets
│   ├── Form/                            # Form classes
│   ├── Controller/                      # Route controllers
│   ├── Entity/                          # Custom entities
│   └── Traits/                          # Shared functionality
├── config/
│   ├── install/                         # Default config files
│   └── schema/                          # Config schema definitions
├── templates/
│   ├── tailor-resume.html.twig         # ✅ Working template
│   └── [other templates needed]
├── js/
│   └── tailor-resume.js                 # ✅ Working JavaScript
├── css/
│   └── tailor-resume.css                # ✅ Professional styling
├── docs/
│   ├── README.md                        # Module documentation
│   ├── ARCHITECTURE.md                  # This architecture (2229 lines)
│   ├── PROCESS_FLOW.md                  # Process flow diagrams
│   └── SUBMISSION_PROCESS.md            # Application submission details
└── tests/                               # PHPUnit tests (needed)
```

---

## PART 6: CRITICAL BLOCKER ANALYSIS

### Why MVP Cannot Ship Without These Gaps Filled:

**1. User Profile Gap (Flow 7)**
- Without profile management, users cannot enter their information
- Users cannot upload resumesUsers cannot set job preferences
- **Result:** System won't accept users → App unusable

**2. Job Discovery Gap (Flow 11)**
- Without web scraping, no jobs are discovered
- Diffbot API is the gateway to job data
- **Result:** Nothing to apply to → Core feature missing

**3. Browser Automation Gap (Flow 17)**
- Without automated application submission, users must apply manually
- Defeats the entire purpose of automation
- Manual fallback is backup, not primary flow
- **Result:** 45-minute manual process instead of 2-minute automated

**4. Admin Visibility Gap (Flow 4)**
- Without error queue, admins can't see failures
- Without company management, can't add/configure employers
- **Result:** No operational visibility → Can't diagnose issues

**5. Error Handling Gap (Flow 3, Phase 5)**
- Without error handling, first failure crashes the system
- Without manual fallback, users get stuck
- **Result:** Single point of failure → Untrustworthy system

---

## PART 7: RECOMMENDED ACCELERATION STRATEGY

### Parallel Development Path (Reduce Timeline)

**Team Size: 2 Developers**

**Developer A (Backend/Automation):**
- Day 1-4: Flow 11 - Diffbot Integration + Job Scraping Service
- Day 5-10: Flow 17 - Browser Automation Framework
- Day 11-14: Error handling + fallback workflow

**Developer B (Frontend/Admin):**
- Day 1-3: Flow 7 - User Profile Forms
- Day 4-5: Flow 4 - Error Queue Dashboard
- Day 5-6: Flow 5 - Support Contact Form
- Day 7-10: Flow 8 - Company Management UI + Flow 9 - Application Tracking
- Day 11-14: Job Browser Dashboard (Flow 3, Phase 2)

**Timeline: Compressed to 14 days concurrent (vs 25 days sequential)**

---

## PART 8: QUICK-REFERENCE IMPLEMENTATION CHECKLIST

### Phase 1: Foundation (Weeks 1-2)
- [ ] Flow 7: User profile forms & resume upload
- [ ] Flow 4: Error queue admin dashboard
- [ ] Flow 5: Support contact form
- [ ] Flow 8: Company selection interface
- [ ] Flow 9: Application history tracking
- [ ] Test: Complete user workflow from registration → application navigation

### Phase 2: Job Discovery (Weeks 3-4)
- [ ] Flow 11: Diffbot API integration
- [ ] Flow 11: Job scraping service (J&J focus)
- [ ] Flow 11: Webhook receiver for real-time updates
- [ ] Flow 3-P2: Job browser dashboard
- [ ] Flow 3-P3: Relevance scoring algorithm
- [ ] Test: Job discovery quality (95%+ of posted jobs found)

### Phase 3: Automation (Weeks 5-6)
- [ ] Flow 17: Browser automation framework
- [ ] Flow 17: J&J application form automation
- [ ] Flow 17: Error capture and fallback
- [ ] Flow 17: MFA and authentication handling
- [ ] Flow 3-P4-6: Application submission queue
- [ ] Test: 85%+ successful automated applications

### Phase 4: Polish & Launch (Week 7-8)
- [ ] Comprehensive testing across all flows
- [ ] Performance optimization
- [ ] Security audit for credential handling
- [ ] Documentation and user guide
- [ ] Staging environment validation
- [ ] Production deployment

---

## PART 9: SUCCESS METRICS & VALIDATION

After completing all implementation phases, validate against MVP success criteria:

### User Workflow Success
- [ ] New user can complete profile in <30 minutes
- [ ] User can select companies and see target jobs
- [ ] User can apply to jobs (automated or manual)
- [ ] User can track application status
- [ ] Failed applications fall back to manual with clear guidance

### Admin Workflow Success
- [ ] Admin can see all system errors in queue
- [ ] Admin can add/edit companies in <5 minutes
- [ ] Admin can access daily error reports
- [ ] Admin can respond to user support requests
- [ ] Admin can trigger manual scraping and application batches

### System Performance
- [ ] 95%+ of jobs discovered within 24 hours of posting
- [ ] 85%+ successful automated applications
- [ ] <5% error rate with proper handling
- [ ] System supports 50+ concurrent users
- [ ] Response times <2 seconds for all user workflows

### Data Quality
- [ ] 95%+ accuracy in job data extraction
- [ ] 90%+ completeness of job information
- [ ] Zero duplicate job postings
- [ ] Proper deduplication across employers

---

## PART 10: KNOWN TECHNICAL CHALLENGES & SOLUTIONS

### Challenge 1: Anti-Bot Detection on Career Portals
**Problem:** Many career portals (including J&J) have anti-scraping measures
**Solution Options:**
1. Diffbot API (Recommended) - Handles anti-bot measures transparently
2. Rotating proxies + User-Agent rotation (if using Playwright)
3. Rate limiting + Backoff strategies
4. Human-in-the-loop for occasional captcha challenges

**MVP Approach:** Use Diffbot API exclusively (handles all anti-bot)

### Challenge 2: MFA Authentication in Automated Submissions
**Problem:** Many job portals require multi-factor authentication
**Solution:**
1. Support One-Time Passwords (OTP) via email/SMS
2. Manual intervention option (send user confirmation)
3. Store authentication tokens when possible
4. Fallback to manual submission for MFA-heavy sites initially

**MVP Approach:** Manual confirmation for MFA; add automated OTP parsing later

### Challenge 3: Dynamic Application Form Fields
**Problem:** Job applications vary greatly between employers
**Solution:**
1. Employer-specific scraping rules stored in company nodes
2. AI-powered field detection (identify field purpose from label)
3. Graceful fallback when field mapping fails
4. Admin configuration interface for custom mapping

**MVP Approach:** Focus on J&J first; generalize mapping rules for Phase 2

### Challenge 4: Resume Format Compatibility
**Problem:** Different employers require different resume formats
**Solution:**
1. Generate multiple resume formats from single master resume
2. Multiple tailored resume versions (chronological, skills-based, etc.)
3. Dynamic PDF generation with formatting optimization
4. Format selection based on employer template

**MVP Approach:** PDF generation with optimization; expand formats in Phase 2

### Challenge 5: Job Posting Deduplication
**Problem:** Same job might be posted to multiple job boards
**Solution:**
1. Hash-based duplicate detection (similar title + company + location)
2. Fuzzy matching on job description content
3. URL-based deduplication (track posting URLs)
4. Manual review interface for ambiguous cases

**MVP Approach:** URL-based primary key + simple content hashing

---

## NEXT IMMEDIATE ACTIONS

1. **PREPARE DEVELOPMENT ENVIRONMENT**
   - Set up Diffbot API key (request free trial)
   - Configure environment variables
   - Review existing services for reusability

2. **IMPLEMENT FOUNDATION FIRST**
   - User profile forms (highest priority blocker)
   - Error queue dashboard (admin visibility)
   - These unblock parallel development

3. **SETUP TESTING FRAMEWORK**
   - PHPUnit for service tests
   - Playwright for integration tests
   - Continuous validation during development

4. **DOCUMENT PROGRESS**
   - Keep this Gap Analysis updated
   - Track which flows are in progress
   - Update Issues.md with development status

---

## Questions & Clarifications Needed

Before starting implementation, confirm:

1. **Diffbot API Subscription**: Is free tier sufficient, or need paid plan?
2. **Parallel Development**: Can we allocate 2 developers for 6-8 weeks?
3. **User Base**: Anyone currently using job_hunter module in production?
4. **Testing Requirements**: Full suite needed, or focused integration tests?
5. **Deployment Timeline**: Strict deadline for MVP launch?

---

**Document Status:** Ready for Implementation  
**Last Review:** February 2026  
**Next Update:** After Phase 1 completion
