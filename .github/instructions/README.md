# AI Instructions Directory

This directory contains comprehensive development instructions and guidelines for the Forseti.life project.

## Purpose

The `instructions.md` file serves as the primary reference for AI-assisted development, ensuring consistency, quality, and adherence to project standards across all development activities.

## Contents

### instructions.md

**Primary AI Development Guidelines** - Comprehensive reference document covering:

#### Core Development Policies
- **Development Process Policy**: No quick fixes mandate, proper diagnostic procedures
- **Testing Protocol (Mandatory)**: Field-by-field form mapping verification with expected vs actual outcomes and a thorough report
- **Playwright Validation (Mandatory)**: Browser-based form verification, console error checks, and evidence artifacts are required for website testing
- **AI Persona Guidelines**: Technical analytical approach with caring professionalism
- **Context Requirements**: Mandatory file inclusions for every interaction
- **Issues.md Mutation Policy**: PHP tester automation may remove confirmed-converted Open rows; Copilot/LLM issue-work agents must not directly edit `Issues.md`
- **Status Tracking Policy**: Do not create `Summary.md`/`status.md`; keep implementation status in GitHub Issues and use README/ARCHITECTURE for durable documentation

#### Technology Stack Documentation
- **LAMP Stack Configuration**: Complete Linux, Apache, MySQL, PHP environment details
- **Multi-Site Drupal Architecture**: Production and development environment specifications
- **Database Configuration**: MySQL setup for development and production
- **Server Configuration**: Apache virtual hosts, logging, and PHP runtime

#### Drupal Development Standards
- **Drupal-Native Implementation Mandate**: Content-centric architecture requirements
- **Module Development Requirements**: Field-based approach, no custom controllers
- **Coding Standards**: Drupal 11 best practices and conventions
- **Security & Performance**: Optimization patterns and access controls

#### Mobile Development Environment (React Native Android)
- **React Native Environment**: Version 0.72.6 setup and configuration
- **Android Build Environment**: Java 17, Android SDK, NDK configuration
- **Android Build Configuration**: AGP 8.0.2, Gradle 8.0.1, Kotlin 1.8.22
- **Version Compatibility Resolution**: Documented working configurations and known issues
- **React Native Library Fixes**: Namespace patches for 8 libraries (AGP 8+ compatibility)
- **Automated Setup**: Complete development environment setup script
- **Build Commands**: Debug and release APK build procedures
- **Troubleshooting Guide**: Common Android build issues and solutions

#### Development Workflows
- **Multi-Site Production**: Separate Drupal installations with isolated databases
- **Deployment Infrastructure**: GitHub Actions workflows and path configurations
- **Service Management**: Apache, MySQL, and development server commands
- **Logging System**: Site-specific Apache logs and Drupal watchdog integration

#### Business Website Requirements
- **Professional Presentation**: Design and content standards
- **Performance Optimization**: Loading times, caching, and mobile responsiveness
- **Content Management**: Easy updates and SEO optimization
- **Integration Patterns**: API handling and external service connections

## Usage

### For AI Assistants (Copilot, etc.)

**Critical Context Inclusion**:
1. **Always read** `instructions.md` at the start of each development session
2. **Re-read** after file edits to maintain guideline adherence
3. **Include ARCHITECTURE.md** when working on module development
4. **Read README.md** in target directories before file modifications
5. **Run `drush cr`** after edits to CSS/SCSS, Twig templates, theme assets, menu links, or routing definitions to surface changes.
6. **Include push commands** when providing VCS instructions (commit + push), omitting curl/testing commands.

### For Human Developers

**Reference Guide**:
- Review `instructions.md` when setting up new development environments
- Consult for technology stack specifications and version requirements
- Follow documented workflows for deployment and server management
- Use as checklist for code quality and standards compliance

## Key Principles

### Development Process
1. **NO QUICK FIXES**: Proper diagnostics before solutions
2. **Process Over Speed**: Understand systems thoroughly
3. **Documentation First**: Update README files after all changes
4. **Architecture Compliance**: Follow Drupal-native patterns

### Multi-Environment Architecture
- **Production**: `/var/www/html/[sitename]` - Multiple independent Drupal sites
- **Development**: `/home/keithaumiller/forseti.life/sites/forseti` - Single site focus
- **Mobile Development**: `/home/keithaumiller/forseti.life/forseti-mobile` - React Native Android

### Mobile Development Standards
- **React Native 0.72.6**: Stable LTS with documented compatibility constraints
- **Android Build Tools**: AGP 8.0.2 + Gradle 8.0.1 + Kotlin 1.8.22 (verified working configuration)
- **Patch Management**: Use patch-package for all node_modules modifications
- **Automated Setup**: Run `/script/setup-forseti-mobile-dev.sh` for complete environment

### Version Control
- **Working Configurations**: All tested version combinations documented
- **Known Issues**: Failed configurations documented with error details
- **Compatibility Matrix**: Kotlin, Gradle, AGP, and React Native version interactions

## Maintenance

### Regular Updates Required
- Technology stack version updates (when upgrading dependencies)
- New module development patterns (as standards evolve)
- Build configuration changes (when resolving compatibility issues)
- Production server changes (when adding sites or modifying infrastructure)
- Mobile development findings (version compatibility discoveries)

### Update Triggers
- After resolving complex technical issues
- When establishing new development patterns
- During infrastructure changes
- After major version upgrades
- When documenting new troubleshooting procedures

### Recent Updates
- **January 2026**: Added comprehensive React Native Android development environment documentation
  - Documented AGP 8.0.2 + Gradle 8.0.1 + Kotlin 1.8.22 working configuration
  - Added React Native library namespace patch requirements
  - Documented version compatibility testing results
  - Added automated setup script documentation
  - Included troubleshooting guide for common Android build issues

- **December 2025**: Forseti.life project restructuring
  - Updated workspace paths to `/home/keithaumiller/forseti.life`
  - Documented single-site development focus
  - Added forseti.life production preparation details

## Related Documentation

### Project Documentation
- `/home/keithaumiller/forseti.life/README.md` - Main project overview
- `/home/keithaumiller/forseti.life/forseti-mobile/README.md` - React Native mobile app
- `/home/keithaumiller/forseti.life/script/README.md` - Setup scripts documentation
- `/home/keithaumiller/forseti.life/sites/forseti/ARCHITECTURE.md` - Drupal architecture

### Mobile Development
- `/home/keithaumiller/forseti.life/forseti-mobile/patches/` - React Native library fixes
- `/home/keithaumiller/forseti.life/script/setup-forseti-mobile-dev.sh` - Automated environment setup

### Server Configuration
- Apache virtual host configurations (site-specific)
- MySQL database schemas (per-site)
- PHP configuration files (`/etc/php/8.3/apache2/php.ini`)

## Contact

For questions about these guidelines or suggested improvements, create an issue in the project repository or discuss during development sessions.

---

**Last Updated**: January 9, 2026
**Next Review**: When upgrading React Native version or resolving new Android build compatibility issues
