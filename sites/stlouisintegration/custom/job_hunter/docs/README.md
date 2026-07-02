# Job Application Automation - Documentation Index

Welcome to the comprehensive documentation for the Job Application Automation module.

## 📚 Documentation Structure

This documentation is organized into several focused documents to help you understand, configure, and extend the module.

### Quick Start
Start here if you're new to the module:
1. Read the [Module README](../README.md) for a high-level overview
2. Follow the [Installation Guide](../INSTALL.md) to set up the module
3. Review the [FAQ](FAQ.md) for common questions

### Core Documentation

#### [Architecture Documentation](ARCHITECTURE.md) 🏗️
**Audience:** Developers, System Architects, Technical Leads

Comprehensive technical architecture covering:
- System overview and design principles
- Technology stack and dependencies
- Module structure and organization
- Data model and entity relationships
- Service layer architecture
- Security architecture
- Integration points (AWS Bedrock, Drupal Core)
- Deployment architecture
- Development guidelines

**When to read:** Before starting development, when planning extensions, or troubleshooting complex issues.

#### [Process Flow Documentation](PROCESS_FLOW.md) 🔄
**Audience:** Developers, Business Analysts, QA Engineers, Product Managers

Detailed process flows and workflows including:
- System architecture flows (installation, configuration)
- User workflows (job posting creation, resume tailoring)
- Administrative workflows (error queue management, user reviews)
- Technical process flows (AI service integration, job scraping)
- Error handling patterns
- Integration points and performance considerations

**When to read:** Understanding how the system works, designing new features, debugging workflows, or writing documentation.

#### [Frequently Asked Questions](FAQ.md) ❓
**Audience:** End Users, Administrators, Developers, Support Staff

Comprehensive Q&A covering:
- General questions about the module
- Getting started and installation
- Configuration and settings
- Resume tailoring functionality
- Job discovery features
- Troubleshooting common issues
- Security and privacy considerations
- Technical implementation questions

**When to read:** First stop for any questions, before contacting support, or when helping other users.

### Specialized Documentation

#### [Resume JSON Schema](RESUME_JSON_SCHEMA.md) 📄
**Audience:** Developers, Data Engineers

Complete specification for parsed resume data storage:
- JSON schema definition for `jobhunter_resume_parsed_data.parsed_data`
- Field-by-field documentation with examples
- Contact info, work history, education, skills structures
- Achievement extraction with metrics and keywords
- Consolidation logic for multiple resumes
- GenAI prompt requirements

#### [Profile Management Guide](../PROFILE_MANAGEMENT.md) 👤
**Audience:** Users, Administrators

Details about user profile extensions for job seekers:
- Profile fields and their purposes
- Profile completion requirements
- Resume upload and management
- Skills and experience tracking

#### [Job Discovery Technical Guide](../JOB_DISCOVERY_README.md) 🔍
**Audience:** Developers

Technical implementation details for job scraping:
- Job discovery framework
- Per-employer scraper implementation
- HTML parsing strategies
- Duplicate detection
- Error handling for scraping

#### [Installation Guide](../INSTALL.md) 🚀
**Audience:** System Administrators, DevOps Engineers

Step-by-step installation instructions:
- System requirements
- Module installation via Drush
- Initial configuration
- Post-installation verification
- Troubleshooting installation issues

---

## 🎯 Documentation by Role

### For End Users
1. [Module README](../README.md) - Overview
2. [FAQ](FAQ.md) - Questions about using the module
3. [Profile Management](../PROFILE_MANAGEMENT.md) - Setting up your profile

### For Administrators
1. [Installation Guide](../INSTALL.md) - Setting up the module
2. [FAQ](FAQ.md) - Configuration and troubleshooting
3. [Process Flow](PROCESS_FLOW.md) - Administrative workflows
4. [Profile Management](../PROFILE_MANAGEMENT.md) - Managing user profiles

### For Developers
1. [Architecture](ARCHITECTURE.md) - System design and structure
2. [Process Flow](PROCESS_FLOW.md) - Technical flows
3. [Job Discovery Guide](../JOB_DISCOVERY_README.md) - Scraping implementation
4. [FAQ](FAQ.md) - Technical questions

### For Business Analysts / Product Managers
1. [Module README](../README.md) - Feature overview
2. [Process Flow](PROCESS_FLOW.md) - User and business workflows
3. [FAQ](FAQ.md) - General questions about capabilities

---

## 🔑 Key Concepts

### AI-Powered Resume Tailoring
The module uses AWS Bedrock with Claude 3.5 Sonnet to automatically tailor resumes to specific job postings. When a job posting is created, the system:
1. Loads the configured "Original Resume"
2. Extracts job details (title, company, description)
3. Sends a prompt to Claude AI with context
4. Receives an optimized resume tailored to that job
5. Saves the tailored resume to the job posting node

**Learn more:** [Process Flow - AI Resume Tailoring](PROCESS_FLOW.md#ai-resume-tailoring-service-flow)

### Content Types and Data Storage
The module uses a hybrid approach:
- **Content Types** (nodes) for primary data: Company, Job Posting, Application, Issue, Tailored Resume
- **Custom Database Table** (`job_seeker`) for job seeker profiles - persists through module uninstall
- **Configuration** for module settings (Original Resume node ID, AI parameters)

**Learn more:** [Architecture - Data Model](ARCHITECTURE.md#data-model)

### Service Layer
Business logic is encapsulated in services:
- `ResumeTailoringService` - Handles AI integration for resume generation
- `JobSeekerService` - Manages CRUD operations for job seeker profiles
- `UserProfileService` - User profile management and statistics
- `AbbVieJobScrapingService` - Company-specific job scraping
- Future services for generic scraping and submission automation

**Learn more:** [Architecture - Service Layer](ARCHITECTURE.md#service-layer)

### Configuration Management
The module uses Drupal's configuration system for:
- Selecting the "Original Resume" node
- Configuring AI settings (region, model, tokens)
- Toggling automatic tailoring on/off

**Learn more:** [FAQ - Configuration](FAQ.md#configuration)

---

## 📖 Documentation Standards

### Keeping Documentation Updated
When making changes to the module:
1. Update relevant documentation files
2. Update the "Last Updated" date at the bottom of each file
3. Add entries to FAQ for new features or common questions
4. Update process flows if workflows change
5. Commit documentation changes with code changes

### Documentation Format
- All documentation is in Markdown format
- Use clear headings and table of contents
- Include code examples where appropriate
- Use diagrams (ASCII art) for complex flows
- Link between related documentation

### Writing Style
- **Be Clear:** Write for your audience's technical level
- **Be Concise:** Get to the point quickly
- **Be Complete:** Provide all necessary context
- **Be Current:** Keep documentation in sync with code

---

## 🔗 External Resources

### Drupal Resources
- [Drupal 11 Documentation](https://www.drupal.org/docs/drupal-apis)
- [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards)
- [Views Module Documentation](https://www.drupal.org/docs/8/core/modules/views)
- [Profile Module](https://www.drupal.org/project/profile)

### AWS Resources
- [AWS Bedrock Documentation](https://docs.aws.amazon.com/bedrock/)
- [Claude AI Model Details](https://docs.anthropic.com/claude/reference/)
- [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/)

### Development Tools
- [Drush Documentation](https://www.drush.org/)
- [Composer for Drupal](https://www.drupal.org/docs/develop/using-composer)
- [PHPUnit Testing](https://phpunit.de/documentation.html)

---

## 📝 Contributing to Documentation

We welcome documentation improvements! To contribute:

1. **Identify what needs updating:** Outdated info, missing details, unclear explanations
2. **Make your changes:** Edit the appropriate Markdown file
3. **Follow the style guide:** Match existing formatting and tone
4. **Test your changes:** Ensure links work and formatting renders correctly
5. **Submit changes:** Commit to version control with clear message

---

## 🆘 Getting Help

If you can't find what you need in the documentation:

1. **Search the FAQ:** [FAQ.md](FAQ.md) covers many common questions
2. **Check the logs:** Navigate to `/admin/reports/dblog` and filter by `job_hunter`
3. **Review the code:** The codebase is well-commented
4. **Ask your team:** Consult with other developers or administrators
5. **Open an issue:** Create a detailed issue report in your repository

---

## 📅 Document History

### Version 1.0 (January 2026)
- Initial comprehensive documentation structure
- Created Architecture, Process Flow, and FAQ documents
- Established documentation standards
- Added this README index

---

## 📂 Complete File List

### Main Documentation (This Directory)
- `README.md` (this file) - Documentation index
- `ARCHITECTURE.md` - Technical architecture documentation
- `PROCESS_FLOW.md` - Process flows and workflows
- `FAQ.md` - Frequently asked questions

### Module Root Documentation
- `../README.md` - Module overview and quick start
- `../INSTALL.md` - Installation instructions
- `../ARCHITECTURE.md` - Legacy architecture doc (consider archiving)
- `../PROFILE_MANAGEMENT.md` - User profile field documentation
- `../JOB_DISCOVERY_README.md` - Job scraping technical guide
- `../BRANDING_AUDIT.md` - Branding analysis
- `../FUNCTION_MAPPING.md` - Function mapping documentation
- `../IMPLEMENTATION_PROGRESS.md` - Development progress tracking

---

**Last Updated:** January 2026  
**Module Version:** 1.0-dev

**Happy coding! 🚀**
