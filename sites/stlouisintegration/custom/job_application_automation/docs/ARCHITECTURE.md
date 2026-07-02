# Job Application Automation - Architecture Documentation

## Table of Contents
- [System Overview](#system-overview)
- [Technology Stack](#technology-stack)
- [Module Architecture](#module-architecture)
- [Data Model](#data-model)
- [Service Layer](#service-layer)
- [Security Architecture](#security-architecture)
- [Integration Architecture](#integration-architecture)
- [Deployment Architecture](#deployment-architecture)

---

## System Overview

### Mission
The Job Application Automation module provides an AI-powered system for automating the job application process within a Drupal environment. It leverages AWS Bedrock Claude AI to intelligently tailor resumes to specific job postings and streamline the application workflow.

### Core Capabilities
- **AI-Powered Resume Tailoring** - Automatic optimization using Claude 3.5 Sonnet
- **Company Management** - Track employers and their career pages
- **Job Discovery** - Framework for scraping job postings (per-employer implementation)
- **Application Tracking** - Monitor submission status and outcomes
- **Error Queue Management** - Administrative oversight of automation failures
- **Job Seeker Profiles** - Custom database table for user profile data (skills, experience, preferences)

### Design Principles

#### 1. Drupal-Native Architecture
The module follows Drupal best practices and leverages core functionality:
- **Content Types** for primary data storage (companies, job postings, applications)
- **Custom Database Table** for job seeker profiles (preserved during uninstall)
- **Views** for all administrative interfaces (no custom listing pages)
- **Configuration Management** for exportable settings
- **Service Container** for dependency injection
- **Hooks** for extending core functionality

#### 2. Service-Oriented Design
Business logic is encapsulated in services:
- `ResumeTailoringService` - AI integration and resume generation
- `JobSeekerService` - CRUD operations for job seeker profiles
- `UserProfileService` - User profile management and statistics
- `AbbVieJobScrapingService` - Company-specific job scraping
- Future: `ApplicationSubmissionService`, etc.

#### 3. Separation of Concerns
- **Controllers** - HTTP request/response handling only
- **Services** - Business logic and external integrations
- **Entities** - Data modeling and persistence
- **Forms** - User input and validation
- **Templates** - Presentation layer

#### 4. Configuration Over Code
- Admin-configurable settings (Original Resume selection, AI parameters)
- Exportable configuration for deployment across environments
- Feature flags for toggling functionality

---

## Technology Stack

### Core Platform
- **Drupal**: 11.2.3+
- **PHP**: 8.3.6+
- **MySQL**: 8.0+
- **Apache**: 2.4+ (with mod_php or PHP-FPM)

### Required Drupal Modules
- **Node** (core) - Content management
- **User** (core) - User management
- **Views** (core) - Administrative interfaces
- **Field** (core) - Custom fields on entities
- **Block** (core) - Navigation block system

### External Services
- **AWS Bedrock Runtime** - AI model inference
  - Model: Claude 3.5 Sonnet (`anthropic.claude-3-5-sonnet-20240620-v1:0`)
  - Region: us-west-2 (configurable)
  - SDK: AWS SDK for PHP v3

### Development Tools
- **Drush** - Command-line administration
- **Composer** - Dependency management
- **Git** - Version control

---

## Module Architecture

### Directory Structure

```
job_application_automation/
├── config/
│   ├── install/                    # Default configuration (fields, displays)
│   │   ├── *.field.*.yml           # Field definitions
│   │   ├── *.view_display.*.yml    # Entity display configs
│   │   └── job_application_automation.settings.yml
│   └── backup/                     # Views configuration backups
├── docs/                           # Documentation (this directory)
│   ├── ARCHITECTURE.md             # System architecture
│   ├── FAQ.md                      # Frequently asked questions
│   ├── PROCESS_FLOW.md             # Process flow diagrams
│   └── README.md                   # Documentation index
├── src/
│   ├── Controller/                 # HTTP controllers
│   │   └── UserProfileController.php
│   ├── Form/                       # Form classes
│   │   └── SettingsForm.php
│   ├── Service/                    # Business logic services
│   │   └── ResumeTailoringService.php
│   ├── Plugin/                     # Plugin implementations
│   └── Commands/                   # Drush commands
│       └── JobApplicationAutomationCommands.php
├── templates/                      # Twig templates
│   ├── tailor-resume.html.twig
│   ├── job-discovery-*.html.twig
│   └── node--company.html.twig
├── js/                             # JavaScript assets
│   ├── tailor-resume.js
│   ├── job-discovery.js
│   └── user-profile.js
├── css/                            # Stylesheet assets
│   ├── tailor-resume.css
│   ├── job-discovery.css
│   └── user-profile.css
├── tests/
│   └── src/                        # PHPUnit tests
├── job_application_automation.info.yml        # Module metadata
├── job_application_automation.module           # Hook implementations
├── job_application_automation.install          # Install/uninstall hooks
├── job_application_automation.services.yml     # Service definitions
├── job_application_automation.routing.yml      # Route definitions
├── job_application_automation.permissions.yml  # Permission definitions
├── job_application_automation.links.*.yml      # Menu/task links
├── job_application_automation.libraries.yml    # Asset libraries
├── ARCHITECTURE.md                 # Legacy architecture doc (move to docs/)
├── README.md                       # Module overview
└── INSTALL.md                      # Installation instructions
```

### Module Metadata (`job_application_automation.info.yml`)

```yaml
name: 'Job Application Automation'
type: module
description: 'AI-powered job application automation with resume tailoring'
package: 'Job Application'
core_version_requirement: ^11
dependencies:
  - drupal:node
  - drupal:user
  - drupal:views
  - drupal:field
  - drupal:block
```

### Service Registration (`job_application_automation.services.yml`)

```yaml
services:
  job_application_automation.job_seeker_service:
    class: Drupal\job_application_automation\Service\JobSeekerService
    arguments: ['@database', '@current_user']
  
  job_application_automation.user_profile_service:
    class: Drupal\job_application_automation\Service\UserProfileService
    arguments: []
  
  job_application_automation.resume_tailoring_service:
    class: Drupal\job_application_automation\Service\ResumeTailoringService
    arguments: ['@logger.factory']
  
  job_application_automation.abbvie_job_scraping_service:
    class: Drupal\job_application_automation\Service\AbbVieJobScrapingService
    arguments: ['@http_client', '@logger.factory', '@config.factory']
```

---

## Data Model

### Entity Relationship Diagram

```
┌─────────────────┐
│     User        │
│                 │
│ - uid           │
│ - name          │
│ - mail          │
└────────┬────────┘
         │ 1
         │
         │ owns
         │
         │ 1
         ↓
┌─────────────────┐
│ Profile         │
│ (job_seeker)    │
│                 │
│ - profile_id    │
│ - field_resume_file
│ - field_primary_resume_text
│ - field_skills
│ - field_experience_years
│ - field_education_level
│ - ...          │
└─────────────────┘


┌─────────────────┐
│   Company       │
│   (node)        │
│                 │
│ - nid           │
│ - title         │
│ - field_company_website
│ - field_careers_url
│ - field_scraping_enabled
│ - field_scraping_config
└────────┬────────┘
         │ 1
         │
         │ has many
         │
         │ *
         ↓
┌─────────────────┐        ┌─────────────────┐
│  Job Posting    │        │   Resume        │
│  (node)         │        │   (node)        │
│                 │        │                 │
│ - nid           │        │ - nid           │
│ - title         │        │ - title         │
│ - field_company_ref ←───→│ - body          │
│ - field_job_title│        │   (resume text) │
│ - field_job_description   └─────────────────┘
│ - field_job_type │                ↑
│ - field_location │                │
│ - field_application_link          │
│ - field_tailored_resume←──────────┘
│   (generated)    │        Uses as source
│ - field_posted_date│
└────────┬────────┘
         │ 1
         │
         │ has many
         │
         │ *
         ↓
┌─────────────────┐
│  Application    │
│  (node)         │
│                 │
│ - nid           │
│ - title         │
│ - field_job_ref │←─── References job_posting
│ - field_user_ref│←─── References user
│ - field_application_status
│ - field_submitted_date
│ - field_response_date
│ - field_notes   │
└─────────────────┘


┌─────────────────┐
│     Issue       │
│   (node)        │
│                 │
│ - nid           │
│ - title         │
│ - field_severity│
│ - field_error_message
│ - field_stack_trace
│ - field_related_entity
│ - field_status  │
│ - field_resolution_notes
└─────────────────┘
```

### Content Types

#### Company Node
**Machine Name:** `company`

**Purpose:** Store employer information and job scraping configuration

**Key Fields:**
- `title` (node title) - Company name
- `field_company_website` - Main company website URL
- `field_careers_url` - Careers/jobs page URL
- `field_company_description` - Long text about the company
- `field_scraping_enabled` - Boolean toggle for job discovery
- `field_scraping_config` - JSON configuration for scraper
- `field_company_logo` - Image field
- `field_last_scraped` - Timestamp of last successful scrape

#### Job Posting Node
**Machine Name:** `job_posting`

**Purpose:** Store job opportunities (manually created or scraped)

**Key Fields:**
- `title` (node title) - System title (auto-generated)
- `field_job_title` - Actual job position title
- `field_company_ref` - Entity reference to company node
- `field_job_description` - Long text (job description HTML/text)
- `field_job_requirements` - Long text (requirements/qualifications)
- `field_job_type` - Term reference (full-time, part-time, contract, etc.)
- `field_location` - Text (city, state, remote, etc.)
- `field_salary_range` - Text (if available)
- `field_application_link` - URL to apply
- `field_posted_date` - Date field
- `field_deadline` - Date field (application deadline)
- `field_tailored_resume` - Long text (**AI-generated resume**)
- `field_application_ref` - Entity reference to application node (when applied)

#### Application Node
**Machine Name:** `application`

**Purpose:** Track application submissions and outcomes

**Key Fields:**
- `title` (node title) - "[User] - [Job Title] - [Date]"
- `field_user_ref` - Entity reference to user
- `field_job_ref` - Entity reference to job_posting
- `field_application_status` - Term reference (draft, submitted, interview, offer, rejected)
- `field_submitted_date` - Date/time of submission
- `field_response_date` - Date of employer response
- `field_interview_dates` - Multi-value date field
- `field_notes` - Long text (user notes)
- `field_automated` - Boolean (was this auto-submitted?)
- `field_submission_method` - Term reference (manual, automated, API, etc.)

#### Issue Node
**Machine Name:** `issue`

**Purpose:** Error queue for failed automation processes

**Key Fields:**
- `title` (node title) - Brief error summary
- `field_severity` - Term reference (error, warning, info)
- `field_error_message` - Long text (detailed error message)
- `field_stack_trace` - Long text (PHP stack trace if applicable)
- `field_error_context` - Long text (JSON with context data)
- `field_related_entity` - Entity reference (generic, to job/company/user)
- `field_status` - Term reference (open, in_progress, resolved, closed)
- `field_resolution_notes` - Long text (admin notes)
- `field_occurred_date` - Date/time when error occurred

#### Resume Node
**Machine Name:** `resume`

**Purpose:** Store user resume versions (original and tailored)

**Key Fields:**
- `title` (node title) - Resume identifier ("Original Resume", "Tailored: [Job Title]")
- `body` - Long text (resume content in text or HTML)
- `field_resume_type` - Term reference (original, tailored)
- `field_job_ref` - Entity reference to job_posting (for tailored resumes)
- `field_user_ref` - Entity reference to user/owner
- `field_created_date` - Date/time when resume was created/tailored

### Custom Tables

#### job_seeker Table

**Purpose:** Store job seeker profile information (persists through module uninstall)

**Schema:**
```sql
CREATE TABLE job_seeker (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uid INT NOT NULL UNIQUE,
  resume_node_id INT NULL,
  skills LONGTEXT NULL COMMENT 'JSON array',
  experience_years INT NULL,
  target_companies LONGTEXT NULL COMMENT 'JSON array',
  preferred_locations TEXT NULL COMMENT 'JSON array',
  job_titles TEXT NULL COMMENT 'JSON array',
  salary_expectation VARCHAR(255) NULL,
  availability VARCHAR(50) NULL,
  linkedin_url VARCHAR(512) NULL,
  portfolio_url VARCHAR(512) NULL,
  created INT NOT NULL,
  changed INT NOT NULL,
  INDEX idx_created (created),
  INDEX idx_changed (changed)
);
```

**Managed By:** `JobSeekerService`

**Key Features:**
- One profile per user (uid is unique)
- JSON-encoded fields for arrays (skills, target_companies, etc.)
- Created outside hook_schema() to prevent automatic deletion
- Accessed via service layer, not directly

---

## Service Layer

### JobSeekerService

**Class:** `Drupal\job_application_automation\Service\JobSeekerService`

**Purpose:** Manage CRUD operations for job seeker profiles

**Dependencies:**
- `@database` - Database connection
- `@current_user` - Current user service

**Key Methods:**
```php
loadByUserId($uid)          // Load profile by user ID
load($id)                   // Load profile by ID
create(array $values)       // Create new profile
update($id, array $values)  // Update existing profile
delete($id)                 // Delete profile
userHasProfile($uid)        // Check if user has profile
getCurrentUserProfile()     // Get current user's profile
```

**JSON Fields Handling:**
- Automatically encodes/decodes JSON fields
- Returns arrays for: skills, target_companies, preferred_locations, job_titles

### ResumeTailoringService

**Class:** `Drupal\job_application_automation\Service\ResumeTailoringService`

**Purpose:** Handle AI-powered resume tailoring using AWS Bedrock Claude

**Dependencies:**
- `@logger.factory` - Drupal logging service

**Public Methods:**

#### `generateTailoredResume(string $resume_text, string $job_title, string $company, string $job_description): string`

**Purpose:** Generate a tailored resume for a specific job posting

**Parameters:**
- `$resume_text` - Original resume content (plain text or HTML)
- `$job_title` - Job position title
- `$company` - Company name
- `$job_description` - Full job description and requirements

**Returns:** Tailored resume text (or empty string on failure)

**Process:**
1. Build AI prompt with job context
2. Check environment (production vs development)
3. Call AWS Bedrock API (or return mock response in dev)
4. Parse and clean AI response
5. Log success/failure
6. Return tailored content

**Error Handling:**
- Catches all exceptions (AWS SDK, network, etc.)
- Logs detailed error messages to watchdog
- Returns empty string on failure (caller handles gracefully)

#### `buildResumePrompt(string $resume_text, string $job_title, string $company, string $job_description): string`

**Purpose:** Construct the prompt sent to Claude AI

**Returns:** Formatted prompt with instructions and context

**Prompt Structure:**
```
You are an expert resume writer and career coach specializing in 
tailoring resumes to specific job opportunities.

Task: Tailor the following resume for this job posting:

JOB DETAILS:
Job Title: {job_title}
Company: {company}
Job Description:
{job_description}

ORIGINAL RESUME:
{resume_text}

INSTRUCTIONS:
- Highlight skills and experience most relevant to this role
- Optimize keywords from the job description
- Maintain the candidate's authentic voice and experience
- Keep professional formatting and structure
- Do not fabricate experience or skills
- Provide a polished, ATS-friendly resume

Return only the tailored resume content, no additional commentary.
```

### Future Services (Planned)

#### JobScraperService
- Per-company scraper implementations
- HTML parsing and job extraction
- Duplicate detection
- Queue management for bulk scraping

#### ApplicationSubmissionService
- Form interaction automation
- Login/authentication handling  
- File upload automation
- Submission verification

---

## Security Architecture

### Authentication & Authorization

#### Permission System
Defined in `job_application_automation.permissions.yml`:

- `administer job application automation` - Full admin access
- `create company content` - Create company nodes
- `edit own company content` - Edit user's own companies
- `edit any company content` - Edit all companies
- `delete own company content` - Delete user's own companies
- `delete any company content` - Delete all companies
- `view company content` - View published companies
- `create job_posting content` - Create job postings
- `edit own job_posting content` - Edit user's job postings
- `edit any job_posting content` - Edit all job postings
- `delete own job_posting content` - Delete user's job postings
- `delete any job_posting content` - Delete all job postings
- `create application content` - Create applications
- `edit own application content` - Edit user's applications
- `edit any application content` - Edit all applications
- `view own applications` - View user's applications
- `view all applications` - View all applications (admin)

#### Access Control
- Node access based on Drupal's permission system
- User profile fields restricted to profile owner and admins
- Configuration forms require `administer job application automation`
- Views use contextual filters for user-specific content

### Data Protection

#### Sensitive Data Handling
- **AWS Credentials**: Never stored in database/config
  - Use environment variables or IAM roles
  - Credentials loaded via AWS SDK credential chain
  - No credentials in version control

- **User Resumes**: 
  - Stored in Drupal database (standard security)
  - Access controlled via node permissions
  - Consider encryption at rest for sensitive deployments

- **Job Applications**:
  - User-specific access via permissions
  - Admin oversight via dedicated permissions

#### API Security
- **AWS Bedrock**:
  - TLS encryption for API calls
  - Signed requests via AWS SDK (IAM authentication)
  - No user credentials ever sent to AWS
  - Resume data sent to AWS per Bedrock terms (not persisted by AWS)

### Input Validation

#### Form Validation
- All forms validate input via Drupal Form API
- Entity reference fields validate target entity exists
- URL fields validated for proper format
- File uploads restricted by type and size

#### XSS Protection
- All user input filtered through Drupal's text formats
- Twig templates auto-escape output
- HTML purifier for rich text fields

#### SQL Injection Protection
- Entity API and query builder prevent SQL injection
- No raw SQL queries used
- Parameterized queries only

---

## Integration Architecture

### AWS Bedrock Integration

#### Connection Flow
```
ResumeTailoringService
        ↓
AWS SDK PHP (v3)
        ↓
Credential Provider Chain
  - Environment variables
  - AWS credentials file
  - IAM role (if on EC2/ECS)
        ↓
AWS Bedrock Runtime Client
        ↓
HTTPS API Call (TLS 1.2+)
        ↓
AWS Bedrock Service (us-west-2)
        ↓
Claude 3.5 Sonnet Model
        ↓
Response JSON
        ↓
Parse & Return to Drupal
```

#### Configuration
Stored in `job_application_automation.settings` config:
- `ai_service_region`: AWS region (default: us-west-2)
- `ai_model_id`: Bedrock model ID (default: anthropic.claude-3-5-sonnet-20240620-v1:0)
- `max_tokens`: Maximum response tokens (default: 4000)
- `enable_automatic_tailoring`: Boolean flag (default: true)
- `original_resume_node_id`: Node ID of master resume (required)

#### Error Handling
- Network timeouts: Configurable via AWS SDK
- API errors: Logged with full error details
- Rate limiting: Handled by AWS SDK retry logic
- Fallback: Development environment uses mock responses

### Drupal Core Integration

#### Hook Implementations
**File:** `job_application_automation.module`

##### `hook_entity_insert(EntityInterface $entity)`
**Trigger:** When any entity is created

**Logic:**
```php
if ($entity->getEntityTypeId() === 'node' 
    && $entity->bundle() === 'job_posting') {
  // Check if automatic tailoring enabled
  // Load Original Resume from config
  // Extract job details
  // Call ResumeTailoringService
  // Save tailored resume to job posting
}
```

##### `hook_theme()`
**Purpose:** Register custom templates

**Templates:**
- `tailor_resume` - Manual tailoring page
- `job_discovery_start` - Job discovery wizard start
- `job_discovery_company_search` - Company search interface
- `job_discovery_company_selection` - Company selection step
- `node__company` - Custom company node display

##### `hook_views_data()` (planned)
**Purpose:** Expose custom data to Views for advanced querying

#### Library Dependencies
**File:** `job_application_automation.libraries.yml`

```yaml
tailor-resume:
  version: 1.x
  js:
    js/tailor-resume.js: {}
  css:
    theme:
      css/tailor-resume.css: {}
  dependencies:
    - core/drupal
    - core/drupalSettings
    - core/jquery
    
job-discovery:
  version: 1.x
  js:
    js/job-discovery.js: {}
  css:
    theme:
      css/job-discovery.css: {}
  dependencies:
    - core/drupal
    - core/jquery

user-profile:
  version: 1.x
  js:
    js/user-profile.js: {}
  css:
    theme:
      css/user-profile.css: {}
  dependencies:
    - core/drupal
```

---

## Deployment Architecture

### Environment Detection

The module detects environment type via:
- **Production**: Presence of `AWS_EXECUTION_ENV` environment variable
- **Development**: Absence of AWS_EXECUTION_ENV (uses mock AI responses)

### Installation Process

1. **Module Enable**
   ```bash
   drush pm:enable job_application_automation -y
   drush cr
   ```

2. **Configuration Import**
   - Fields and content types created automatically via `hook_install()`
   - Default config imported from `config/install/`

3. **Configuration Setup**
   - Admin navigates to `/admin/config/job-application/settings`
   - Select Original Resume node
   - Adjust AI settings if needed
   - Save configuration

4. **User Profile Creation**
   - Users create/complete job_seeker profile
   - Upload resume or paste resume text

5. **Ready to Use**
   - Create companies
   - Create job postings (automatic tailoring)
   - Or manually trigger tailoring via UI

### Multi-Site Deployment

The module supports Drupal multi-site installations:
- Each site has independent configuration
- Separate content (companies, jobs, applications)
- Shared codebase, separate databases
- Per-site AWS credentials (if using env vars)

### Configuration Management

#### Exportable Configuration
- Field configurations exported to `config/install/`
- Module settings exportable via `drush cex`
- Views configurations in `config/backup/` for reference

#### Deployment Workflow
```bash
# Development site
drush cex -y              # Export config
git add config/
git commit -m "Config updates"
git push

# Production site
git pull
drush cim -y              # Import config
drush cr                  # Clear cache
```

### Performance Considerations

#### Database
- Indexed fields on entity references for fast lookups
- Consider archiving old job postings/applications

#### Caching
- Views results cached per Drupal defaults
- Node content cached per Drupal defaults
- Configuration cached persistently
- **AI responses NOT cached** (unique per job/resume combination)

#### AI API Calls
- Average latency: 3-10 seconds per tailoring
- Consider implementing queue for bulk processing
- Monitor AWS Bedrock quotas and throttling

### Monitoring & Logging

#### Watchdog Logging
All module logs use channel: `job_application_automation`

View logs:
```bash
drush watchdog:show --filter=job_application_automation
```

Or via UI: `/admin/reports/dblog`

#### Log Types
- **Info**: Successful operations, configuration changes
- **Warning**: Non-critical issues (missing resume, skipped tailoring)
- **Error**: Critical failures (AWS API errors, exceptions)

#### Metrics to Monitor
- AI tailoring success rate
- AI response times
- Job posting creation rate
- Application submission rate
- Error queue size
- User profile completion rate

---

## Development Guidelines

### Code Standards
- Follow [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards)
- Use PHP_CodeSniffer with Drupal standards
- Type-hint all parameters and return types
- Document all public methods with PHPDoc

### Testing Strategy
- Unit tests for services (PHPUnit)
- Functional tests for user workflows
- Integration tests for AWS Bedrock (mocked in CI)
- Manual testing in development environment

### Extension Points

#### Adding New Scrapers
1. Create plugin in `src/Plugin/JobScraper/`
2. Implement `JobScraperInterface`
3. Register in plugin discovery
4. Configure in company node

#### Customizing AI Prompts
Override `ResumeTailoringService::buildResumePrompt()`:
```php
use Drupal\job_application_automation\Service\ResumeTailoringService;

class CustomResumeTailoringService extends ResumeTailoringService {
  protected function buildResumePrompt(...) {
    // Custom prompt logic
  }
}
```

Update service definition in `*.services.yml`.

#### Adding Custom Fields
Use Drupal UI:
1. Structure > Content types > [Type] > Manage fields
2. Add field via UI
3. Export configuration: `drush cex -y`
4. Commit to version control

---

## Future Architecture Considerations

### Scalability
- **Queue-based processing** for bulk job scraping
- **Caching layer** for repeated job/resume combinations
- **CDN integration** for static assets
- **Database replication** for read-heavy loads

### Advanced Features
- **Machine learning** for improving tailoring over time
- **A/B testing** different resume versions
- **Analytics dashboard** for success metrics
- **Mobile app** integration via REST/JSON:API
- **Notification system** for job matches and application updates

### Infrastructure
- **Docker containers** for consistent environments
- **CI/CD pipeline** for automated testing and deployment
- **Load balancing** for high-traffic scenarios
- **Monitoring** with tools like New Relic or Datadog

---

**Last Updated:** January 2026  
**Module Version:** 1.0-dev  
**Drupal Version:** 11.2.3+  
**AWS SDK Version:** 3.x
