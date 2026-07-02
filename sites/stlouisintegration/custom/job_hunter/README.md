# Job Application Automation Module

## Overview
A comprehensive AI-powered Drupal module that automates the entire job application process using Generative AI. This system analyzes user resumes, scrapes job postings from employer websites, tailors applications using AI, and automatically submits applications across multiple employer platforms.

## ⚠️ CRITICAL: Read Architecture First
**Before any development work begins, all developers MUST read and understand the complete [ARCHITECTURE.md](ARCHITECTURE.md) document.** This system involves complex AI integration, automated web scraping, credential management, and multi-platform submission automation that requires thorough understanding of the architecture before implementation.

## 🛡️ IMPORTANT: Data Preservation Policy

**This module is designed to NEVER delete user content or fields during uninstallation.**

All job applications, company data, user profiles, resume files, and custom content are preserved to prevent accidental data loss.

### Protected During Uninstall
- **Content Types**: company, job_posting, application, issue, tailored_resume (and all their content)
- **User Fields**: field_resume_file, field_primary_resume_text, field_profile_completeness, etc.
- **Profile Fields**: field_profile_completeness, field_resume_file (job_seeker profile)
- **Views**: Job Applications Dashboard, Company Management, Job Discovery
- **All User Data**: Resume uploads, extracted text, profile information, application history

### What Gets Removed
Only module configuration settings (`job_hunter.settings`) are removed during uninstall.

### Manual Cleanup (Optional)
To remove content types/fields after uninstall: Structure > Content types > Delete or Configuration > Account settings > Manage fields > Delete

## Configuration

### Initial Setup

1. **Set Original Resume Node** - Navigate to `/admin/config/job-application/settings`
2. **Select Resume Node** - Use autocomplete to select your master resume node
3. **Enable Automatic Tailoring** - Check the box to enable automatic resume generation when job postings are created
4. **Configure AI Settings** (optional) - AWS Bedrock region, model ID, and max tokens are preset but customizable

The module uses AWS Bedrock with Claude 3.5 Sonnet by default. Ensure your environment has proper AWS credentials configured.

### Original Resume Selection

The module requires a designated "Original Resume" node to generate tailored versions. Configure this at `/admin/config/job-application/settings`:

- **Configuration-based (Recommended)**: Admin selects the resume node via entity autocomplete in settings form
- **Fallback (Legacy)**: System searches for a resume node titled "Original Resume" if not configured
- **Warning Logging**: If no resume is found, warnings are logged to help with troubleshooting

## Current Working System **[✅ IMPLEMENTED]**

### **Resume Management & JSON Storage Workflow** **[✅ IMPLEMENTED]**

The module provides a streamlined 4-step workflow focused on JSON storage of resume data:

#### **Step 1: File Upload** **[✅ WORKING]**
- User uploads .docx resume files to `private://job_hunter/resumes/`
- System automatically registers files in `jobhunter_job_seeker_resumes` table
- File entities created automatically for each uploaded resume
- Status checklist initialized: ⬜ Text Extracted | ⬜ Individual JSON Stored | ⬜ Merged to Consolidated

#### **Step 2: Extract Text** **[✅ WORKING]**
- **Button Location**: Inline next to "Text Extracted" status line
- **Action**: Click "Extract Text" button to extract content from .docx file
- **Processing**: Uses `PhpOffice\PhpWord` to parse document structure
- **Storage**: Stores extracted text in `jobhunter_job_seeker_resumes.extracted_text` LONGTEXT field
- **Status Update**: ✅ Text Extracted (X chars) | ⬜ Individual JSON Stored | ⬜ Merged to Consolidated
- **Character Tracking**: Displays character count for verification (e.g., "22,987 chars")

#### **Step 3: Parse JSON** **[✅ WORKING]**
- **Button Location**: Inline next to "Individual JSON Stored" status line
- **Prerequisites**: Text must be extracted first
- **AI Processing**: Calls AWS Bedrock (Claude 3.5 Sonnet) to analyze resume structure
- **Fallback**: Mock data if AWS credentials not configured or timeout occurs
- **JSON Schema**: Structured data with arrays for job_history and education_history
- **Storage**: Stores parsed JSON in `jobhunter_resume_parsed_data.parsed_data` JSON field
- **Status Update**: ✅ Text Extracted | ✅ Individual JSON Stored | ⬜ Merged to Consolidated

**Individual Resume JSON Structure**:
```json
{
  "professional_summary": "string",
  "skills": ["skill1", "skill2", "skill3"],
  "experience_years": 15,
  "education_level": "Bachelor's",
  "certifications": ["cert1", "cert2"],
  "job_titles": ["title1", "title2"],
  "job_history": [
    {
      "company": "Company Name",
      "title": "Job Title",
      "start_date": "2020-01",
      "end_date": "2023-12",
      "description": "Roles and responsibilities..."
    }
  ],
  "education_history": [
    {
      "institution": "University Name",
      "degree": "Bachelor of Science",
      "field": "Computer Science",
      "graduation_date": "2010-05"
    }
  ],
  "contact_info": {
    "email": "user@example.com",
    "phone": "555-1234",
    "location": "City, State"
  }
}
```

#### **Step 4: Consolidate** **[✅ WORKING]**
- **Button Location**: Inline next to "Merged to Consolidated" status line
- **Prerequisites**: Individual JSON must exist for the resume
- **Deduplication Logic**: Smart merging of data from multiple resumes
  - Skills: Unique values only, comma-separated parsing
  - Professional Summary: Array of unique summaries from all resumes
  - Experience Years: Maximum value across all resumes
  - Education Level: Highest level across all resumes
  - Job History: Deduplicated by company + title + dates, tracks source resumes
  - Education History: Deduplicated by institution + degree, tracks source resumes
- **Storage**: Updates `jobhunter_job_seeker.consolidated_profile_json` JSON field
- **Status Update**: ✅ Text Extracted | ✅ Individual JSON Stored | ✅ Merged to Consolidated

**Consolidated Profile JSON Structure**:
```json
{
  "professional_summary": ["summary from resume 1", "summary from resume 2"],
  "skills": ["skill1", "skill2", "skill3", "skill4"],
  "experience_years": 15,
  "education_level": "Master's",
  "certifications": ["cert1", "cert2", "cert3"],
  "job_titles": ["title1", "title2", "title3"],
  "job_history": [
    {
      "company": "Company A",
      "title": "Senior Engineer",
      "start_date": "2020-01",
      "end_date": "2023-12",
      "description": "Combined roles...",
      "source_resumes": [20, 21]
    }
  ],
  "education_history": [
    {
      "institution": "University",
      "degree": "Master's",
      "field": "CS",
      "graduation_date": "2015-05",
      "source_resumes": [20]
    }
  ]
}
```

#### **Current Scope: JSON Storage ONLY** **[🎯 FOCUSED]**

The workflow is currently simplified to focus exclusively on JSON storage in two fields:
1. **Individual Resume Data**: `jobhunter_resume_parsed_data.parsed_data` (per resume file)
2. **Consolidated Profile Data**: `jobhunter_job_seeker.consolidated_profile_json` (merged from all resumes)

**Features Deferred (Commented Out)**:
- ❌ Profile text field population (professional_summary, skills, certifications columns)
- ❌ Job history relational table inserts (`jobhunter_job_history` table unused)
- ❌ Education history relational table inserts (`jobhunter_education_history` table unused)

These features exist in the codebase but are commented out per development priorities. Future implementation will populate relational tables and profile text fields from the consolidated JSON data.

### **Automatic Resume Tailoring** (Consolidated from resume_tailoring module):
1. **Create Job Posting** - Add new job_posting node with company, title, and description **[✅ WORKING]**
2. **Automatic AI Tailoring** - System automatically generates tailored resume on save **[✅ WORKING]**
3. **Configuration Check** - Uses configured Original Resume node ID (or title fallback) **[✅ WORKING]**
4. **AI Processing** - AWS Bedrock Claude 3.5 Sonnet analyzes job and tailors resume **[✅ WORKING]**
5. **Resume Saved** - Tailored content saved to field_tailored_resume on job posting **[✅ WORKING]**
6. **Logging & Monitoring** - Full logging for debugging and monitoring **[✅ WORKING]**

### **Development Environment Features** **[✅ IMPLEMENTED]**:
- **Environment Detection** - Automatic dev/prod environment detection **[✅ WORKING]**
- **Configuration Management** - Config entity for Original Resume selection **[✅ WORKING]**
- **Error Handling** - Comprehensive error handling with detailed logging **[✅ WORKING]**
- **Cache Management** - Drupal cache integration with library loading **[✅ WORKING]**
- **Database Integration** - Proper node creation and entity references **[✅ WORKING]**
- **AI Service Integration** - AWS Bedrock Runtime with configurable settings **[✅ WORKING]**

## Planned System Workflow (Future Development)

### For Users (Full System):
1. **Upload Comprehensive Resume** - AI analyzes skills, experience, and expertise levels **[🔄 TODO]**
2. **Add Employers & Credentials** - Manage login credentials for target employer websites **[🔄 TODO]**
3. **Set Job Preferences** - Define keywords and types of positions of interest **[🔄 TODO]**
4. **Automated Discovery** - System continuously scrapes employer sites for matching jobs **[🔄 TODO]**
5. **AI-Powered Application** - Click "Apply" to have AI tailor resume and submit automatically **[🔄 TODO]**
6. **Manual Completion** - When automation fails, receive tailored resume to finish manually **[🔄 TODO]**

### For Administrators:
1. **Error Queue Management** - Review and resolve failed automation workflows
2. **Automation Improvement** - Update scripts based on employer website changes
3. **User Support** - Help users with credential issues and manual completions
4. **System Monitoring** - Track success rates and identify improvement opportunities

## Key Features

### 🤖 **AI-Powered Resume Analysis & Tailoring** **[✅ IMPLEMENTED - CONSOLIDATED]**
- **AJAX-Based Manual Tailoring** - Complete frontend/backend integration at `/user/{user}/tailor-resume/{job}`
- **Automatic Tailoring on Job Creation** - Generates tailored resume when job_posting node is created
- **Unified AI Service** - Single ResumeTailoringService handles all tailoring operations
- **Environment-Aware Processing** - Mock responses in development, AWS Bedrock Claude in production
- **Dynamic Content Generation** - Creates tailored_resume nodes with personalized content
- **Real-Time User Feedback** - JavaScript messaging system with success/error handling
- **Professional UI/UX** - Bootstrap-styled interface with loading states and content preview
- **NOTE**: Consolidated from separate resume_tailoring module into core functionality

### 🏢 **Employer Management & Job Discovery**
- Add and manage target employers with website scraping configuration
- Automated job posting discovery from employer career pages
- Keyword matching against user preferences
- Real-time job posting monitoring and change detection

### 🔐 **Secure Credential Management**
- Encrypted storage of user login credentials for each employer
- Credential validation and testing capabilities
- Multi-factor authentication support
- Secure credential sharing and session management

### 🚀 **Automated Application Submission**
- Browser automation for complex multi-step application processes
- Intelligent form field recognition and completion
- File upload automation (resumes, cover letters, portfolios)
- CAPTCHA detection and error recovery mechanisms

### 📊 **User Dashboard & Management**
- Real-time application status tracking
- Job match recommendations with AI-powered relevance scoring
- One-click application submission with preview capabilities
- Profile completeness indicators and improvement suggestions

### 🛠️ **Advanced Error Handling**
- Comprehensive error detection and categorization
- Admin queue for failed automation workflows
- User notifications for manual completion requirements
- Automation improvement tracking and implementation

## Critical User Requirements

### Resume Completeness
**The system requires comprehensive, detailed resumes to function properly.** Users will be notified that:
- Incomplete resumes will result in poor AI analysis and job matching
- The system cannot effectively tailor applications without sufficient resume detail
- Regular resume updates improve AI accuracy and job match quality
- Resume completeness scoring helps users understand improvement areas

### Employer Credentials
**Users must manage their own login credentials for each target employer.** This includes:
- Secure storage and regular validation of login credentials
- Management of multi-factor authentication requirements
- Credential updates when passwords change or expire
- Understanding of security implications and best practices

## Installation & Setup

### Prerequisites
- Drupal 11 with PHP 8.3+
- MySQL 8.0+ database
- Redis for caching and queue management
- OpenAI API access for GenAI services
- Selenium/Puppeteer for browser automation

### Installation Steps **[✅ WORKING]**
1. **Module Installation** - Place module in `drupal/web/modules/custom/job_hunter/` **[✅ COMPLETED]**
2. **Dependencies** - Install required dependencies: `composer install` **[✅ COMPLETED]**
3. **Module Enable** - Enable the module: `drush en job_hunter` **[✅ COMPLETED]**
4. **Environment Setup** - Run `./scripts/setup-environment.sh` for complete environment **[✅ COMPLETED]**
5. **Content Types** - All required content types created automatically **[✅ COMPLETED]**
6. **AI Tailoring Ready** - Access `/user/{user}/tailor-resume/{job}` for resume tailoring **[✅ COMPLETED]**
7. **Permissions Configuration** - Configure permissions: `/admin/people/permissions` **[🔄 TODO]**

### Configuration Requirements
- **AI Service Configuration** - OpenAI API keys and model settings
- **Scraping Configuration** - Rate limits and respectful scraping policies
- **Security Settings** - Credential encryption and storage policies
- **Queue Management** - Background processing and error handling
- **Admin Notifications** - Error queue alerts and assignment rules

## Usage & Access Points

### User Interface **[✅ PARTIALLY IMPLEMENTED]**
- **Resume Management:** `/job-application/profile` - **[✅ WORKING]** - Upload, extract, parse, and consolidate resume data
  - Step 1: Upload .docx files to private directory
  - Step 2: Extract text from uploaded files
  - Step 3: Parse JSON with AWS Bedrock AI (or mock fallback)
  - Step 4: Consolidate multiple resumes into unified JSON profile
  - Auto-registration of files upon upload
  - Inline action buttons with real-time status indicators
  - Delete functionality for individual resume files
- **AI Resume Tailoring:** `/jobhunter/tailor-resume/{job_id}` - **[✅ WORKING]** - Complete AJAX-powered resume tailoring interface (see detailed flow below)
- **Resume Dashboard:** `/resume-tailoring/dashboard` - **[✅ WORKING]** - Resume tailoring dashboard with job postings

### Resume Tailoring Process Flow **[✅ IMPLEMENTED]**

The resume tailoring page (`/jobhunter/tailor-resume/{job_id}`) provides a comprehensive workflow for tailoring resumes to specific job postings.

#### Status Lifecycle

| Status | Database Value | UI Label | Description |
|--------|----------------|----------|-------------|
| **Pending** | `pending` (or no record) | "Ready to Tailor" | Initial state - user hasn't requested tailoring |
| **Queued** | `queued` | "In Queue" | Request submitted, waiting for queue worker |
| **Processing** | `processing` | "Tailoring in Progress" | AI is generating tailored resume (30-60 sec) |
| **Completed** | `completed` | "Tailored & Ready" | Resume ready for review and PDF generation |
| **Failed** | `failed` | "Tailoring Failed" | Error occurred, retry available |

#### Status Determination Logic

```
Page Load → Query job_hunter_tailored_resumes for (uid, job_id)
         ↓
    Record exists?
         ↓
    NO → status = 'pending'
    YES → status = record.tailoring_status
```

#### Skills Gap Analysis

The page automatically calculates which job-required skills are missing from the user's profile:

**Data Sources:**
- Job skills from `job_hunter_job_requirements.skills_required_json` (must_have, nice_to_have, tech_stack)
- User skills from `jobhunter_job_seeker.consolidated_profile_json` (technical_expertise, skills, certifications)

**Matching Logic:**
- Case-insensitive comparison
- Fuzzy matching (substring containment both directions)
- Results split into "Must Have (Missing)" and "Nice to Have (Missing)"

#### Add Skill to Profile Feature

Users can add missing skills directly from the tailoring page:
- **Individual:** Click "+ Add to Profile" on any missing skill
- **Bulk:** "Add All Must-Have Skills" or "Add All Missing Skills" buttons
- **Storage:** Skills added to `consolidated_profile_json.technical_expertise`
- **Default Proficiency:** 'intermediate'
- **Refresh:** Click "🔄 Refresh Skills Gap" after adding to re-calculate

#### Process Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         USER VISITS PAGE                                 │
└─────────────────────────────────────────────────────────────────────────┘
                                 │
                ┌────────────────┼────────────────┐
                ▼                ▼                ▼
         ┌──────────┐    ┌──────────────┐   ┌────────────┐
         │ SKILLS   │    │ TAILORING    │   │ COMPLETED  │
         │ GAP      │    │ STATUS       │   │ PREVIEW    │
         │          │    │              │   │            │
         │ Missing  │    │ Progress bar │   │ Tailored   │
         │ skills + │    │ showing step │   │ resume +   │
         │ Add btns │    │ 1→2→3→4      │   │ PDF opts   │
         └──────────┘    └──────────────┘   └────────────┘
```

#### Queue Processing

**Queue Worker:** `job_hunter_resume_tailoring`
**Location:** `ResumeTailoringWorker.php`

```
User clicks "Generate" → tailorResumeAjax()
                              │
                              ▼
                    Insert into Drupal queue
                    Set status = 'queued'
                              │
                              ▼
                    Queue worker picks up job
                    Set status = 'processing'
                              │
                              ▼
                    Call AWS Bedrock AI
                              │
              ┌───────────────┼───────────────┐
              ▼                               ▼
         Success                           Failure
    status = 'completed'              status = 'failed'
    Store tailored_resume_json        Log error
```

#### Database Tables

| Table | Purpose |
|-------|---------|
| `job_hunter_job_requirements` | Job posting data (extracted_json, skills_required_json) |
| `jobhunter_job_seeker` | User profile with `consolidated_profile_json` |
| `job_hunter_tailored_resumes` | Tailored results and `tailoring_status` |
| `job_hunter_pdf_history` | Tracks generated PDF files per job |

#### Available Actions by Status

| Status | Generate | Skills Gap | Add Skills | View Resume | PDF | Regenerate |
|--------|----------|------------|------------|-------------|-----|------------|
| pending | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| queued | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| processing | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| completed | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| failed | ✅ (Retry) | ✅ | ✅ | ❌ | ❌ | ❌ |
- **Content Management:** `/node/add/{type}` - **[✅ WORKING]** - Standard Drupal content creation forms
- **Employer Management:** `/job-application/employers` - **[🔄 TODO]** - Add employers and manage credentials  
- **Application History:** `/job-application/history` - **[🔄 TODO]** - View all applications and their status

### Administrative Interface
- **Admin Dashboard:** `/admin/job-applications` - System overview and analytics
- **Error Queue:** `/admin/job-applications/queue` - Failed workflow management
- **User Management:** `/admin/job-applications/users` - User profile and credential oversight
- **System Settings:** `/admin/config/services/job-application-automation` - Module configuration
- **Scraping Management:** `/admin/job-applications/scraping` - Job discovery configuration

## Permissions & Security

### User Permissions
- `access job application dashboard` - View personal dashboard and job matches
- `manage job application profile` - Upload resume and manage personal profile
- `manage employer credentials` - Add employers and store login credentials
- `view job application history` - Access personal application history and status
- `use automated job application` - Use AI-powered application submission

### Administrative Permissions
- `administer job application automation` - Full system administration access
- `manage job application queue` - Process failed automation workflows
- `view all job applications` - Access all user applications and data
- `configure job scraping` - Manage employer scraping and discovery settings
- `manage system credentials` - Oversee user credential security and validation

### Security Considerations
- **Credential Encryption:** All user credentials encrypted with industry-standard algorithms
- **Access Logging:** Complete audit trail for all credential access and system usage
- **Rate Limiting:** Protection against abuse and excessive API usage
- **Data Privacy:** GDPR compliance with user data deletion and export capabilities
- **AI Security:** Input validation and prompt injection prevention for GenAI services

## Development Guidelines

### Required Reading
1. **[ARCHITECTURE.md](ARCHITECTURE.md)** - Complete system architecture and design patterns
2. **Entity Relationships** - Understanding of complex entity relationships and dependencies
3. **AI Integration Patterns** - GenAI service integration and error handling
4. **Security Protocols** - Credential management and encryption requirements
5. **Automation Frameworks** - Browser automation and form submission handling

### CSS and Styling Standards
**CENTRALIZED STYLING POLICY**: This module follows strict centralized CSS architecture:
- **NO INLINE STYLES**: Never use inline `style=""` attributes in templates
- **NO STYLE TAGS**: Never embed `<style>` tags in template files
- **CSS LIBRARIES ONLY**: All styling must be defined in separate CSS files in the `css/` directory
- **CLASS-BASED STYLING**: Use semantic CSS classes and apply styles through registered Drupal libraries
- **THEME CONSISTENCY**: Follow established design patterns and the project style guide
- **RESPONSIVE DESIGN**: Use CSS media queries in stylesheets, not inline styles
- **LIBRARY REGISTRATION**: All CSS files must be registered in `job_hunter.libraries.yml`

**Module CSS Files**:
- `css/job-hunter-home.css` - Dashboard and home page styling
- `css/job-hunter-navigation.css` - Navigation block styling
- `css/job-discovery.css` - Job discovery workflow styling
- `css/tailor-resume.css` - Resume tailoring interface styling
- `css/company-profile.css` - Company management styling
- `css/user-profile-custom.css` - User profile styling
- `css/documentation.css` - Documentation page styling
- `css/job_hunter.css` - Global module styling

### Development Phases
- **Phase 1:** Core infrastructure and entity definitions (Current)
- **Phase 2:** AI integration and job discovery systems
- **Phase 3:** Automated submission and error handling
- **Phase 4:** Production optimization and advanced features

### Critical Development Notes
- **No Direct Coding Without Architecture Review** - All development must follow documented architecture
- **Security-First Approach** - Credential handling and user data protection is paramount
- **AI Service Integration** - Proper error handling and fallback mechanisms required
- **Respectful Automation** - Rate limiting and ethical scraping practices mandatory
- **Comprehensive Testing** - Automated testing for all AI and automation workflows

## System Requirements

### Minimum Requirements
- **Drupal 11** with PHP 8.3+
- **MySQL 8.0+** for primary data storage
- **Redis** for caching and queue management
- **OpenAI API Access** for GenAI resume analysis and tailoring
- **Sufficient Server Resources** for browser automation and background processing

### Recommended Infrastructure
- **Load Balancing** for high-volume job scraping and application submission
- **Dedicated Queue Workers** for background AI processing
- **CDN Integration** for fast resume and document delivery
- **Monitoring Systems** for error tracking and performance optimization
- **Backup Systems** for critical user data and credential protection

### Crontab Configuration (Production)

The following cron jobs are required for background queue processing on the production server:

```bash
# Resume GenAI parsing queue (text extraction and JSON parsing)
*/5 * * * * /var/www/html/stlouisintegration/scripts/run_job_hunter_queue.sh

# Job posting parsing queue (extracts job details, skills, keywords via AI)
*/5 * * * * cd /var/www/html/stlouisintegration && vendor/bin/drush queue:run job_hunter_job_posting_parsing --time-limit=240 2>&1 | logger -t job_hunter_queue

# Resume tailoring queue (generates tailored resumes via AI)
*/5 * * * * cd /var/www/html/stlouisintegration && flock -n /tmp/jh_tailoring.lock vendor/bin/drush queue:run job_hunter_resume_tailoring --time-limit=240 >> /var/log/drupal/tailoring_queue.log 2>&1
```

**Queue Workers:**
- `job_hunter_genai_parsing` - Resume text extraction and JSON parsing
- `job_hunter_job_posting_parsing` - Job posting AI analysis (skills, keywords, company extraction)
- `job_hunter_resume_tailoring` - AI-powered resume tailoring for specific job postings

## Monitoring & Analytics

### Success Metrics
- **Application Success Rate** - Percentage of successful automated submissions
- **Resume Analysis Accuracy** - AI-powered skills and experience assessment quality
- **Job Match Relevance** - User satisfaction with AI-recommended job matches
- **Error Resolution Time** - Average time to resolve failed automation workflows
- **User Engagement** - Dashboard usage and feature adoption rates

### Alert Systems
- **Failed Submission Alerts** - Immediate notification of automation failures
- **Credential Validation Errors** - Alerts for expired or invalid user credentials
- **AI Service Outages** - Monitoring and fallback for GenAI service disruptions
- **Scraping Failures** - Detection of employer website changes affecting job discovery
- **Security Incidents** - Monitoring for unauthorized access or credential breaches

## Support & Documentation

### User Support Resources
- **Getting Started Guide** - Step-by-step setup for new users
- **Resume Optimization Tips** - Best practices for AI-friendly resume formatting
- **Credential Management** - Security guidelines for employer login information
- **Troubleshooting Guide** - Common issues and resolution steps
- **Privacy & Security FAQ** - User data protection and privacy policies

### Developer Resources
- **Architecture Documentation** - [ARCHITECTURE.md](ARCHITECTURE.md) (Required Reading)
- **API Documentation** - Integration points and service interfaces
- **Testing Guidelines** - Automated testing requirements and best practices
- **Deployment Procedures** - Production deployment and configuration
- **Security Protocols** - Credential handling and encryption standards

### Getting Help
For technical issues, feature requests, and development questions:
- **Project Repository:** https://github.com/keithaumiller/stlouisintegration.com
- **Issue Tracking:** GitHub Issues for bug reports and feature requests
- **Development Discussions:** GitHub Discussions for architecture and implementation questions

## License & Compliance
- **License:** GPL v2 or later
- **Data Privacy:** GDPR and CCPA compliant
- **Security Standards:** SOC 2 Type II framework alignment
- **Ethical AI:** Responsible AI usage guidelines and bias mitigation
- **Web Scraping:** Respectful scraping practices and robots.txt compliance