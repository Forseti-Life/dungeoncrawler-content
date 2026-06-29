# Process Flow Relationships - Development Lifecycle Hierarchy

**Document Version:** 1.0  
**Date:** January 27, 2026  
**Purpose:** Define hierarchical relationships between all development lifecycles and their high-level process flows

---

## Lifecycle Hierarchy Structure

```
1. Business Development Life Cycle (BDLC)
   │
   ├─► 2. Product Development Life Cycle (PDLC)
   │   │
   │   ├─► 3. System Development Life Cycle (SDLC - System)
   │   │   │
   │   │   ├─► 4. Database Development Life Cycle (DDLC)
   │   │   │
   │   │   ├─► 5. Integration Development Life Cycle (IDLC)
   │   │   │
   │   │   ├─► 6. Security Development Life Cycle (SDL)
   │   │   │
   │   │   └─► 7. Software Development Life Cycle (SDLC - Software)
   │   │       │
   │   │       ├─► 8. User Experience Development Life Cycle (UXDLC)
   │   │       │
   │   │       ├─► 9. Statistical Model Development Life Cycle (SMDLC)
   │   │       │
   │   │       └─► 10. Testing/QA Life Cycle (QALC)
   │   │
   │   └─► 11. Research Protocol Life Cycle (RPLC) [NFR-specific]
   │
   ├─► 12. DevOps/Deployment Life Cycle (DDLC) [Cross-cutting]
   │
   ├─► 13. Documentation Life Cycle (DocLC) [Cross-cutting]
   │
   ├─► 14. Compliance/Regulatory Life Cycle (CRLC) [Cross-cutting]
   │
   └─► 15. Data Governance Life Cycle (DGLC) [Cross-cutting]
```

---

## 1. Business Development Life Cycle (BDLC)

**Purpose:** Define strategic direction, market opportunity, and business value proposition

### High-Level Process Flow

```
Market Analysis → Opportunity Identification → Business Case Development → 
Strategic Planning → Investment Decision → Product Charter → Success Metrics Definition
```

### Key Activities
1. **Market Analysis** - Identify target market, needs, competition
2. **Opportunity Identification** - Evaluate business opportunities and gaps
3. **Business Case Development** - ROI analysis, cost-benefit analysis
4. **Strategic Planning** - Define goals, objectives, constraints
5. **Investment Decision** - Approve/reject business initiative
6. **Product Charter** - Define scope, stakeholders, high-level requirements
7. **Success Metrics Definition** - KPIs, success criteria

### Deliverables
- Market analysis report
- Business case document
- Product charter
- Strategic roadmap
- Success metrics dashboard

### Governance
- Executive review and approval
- Budget allocation
- Resource planning

---

## 2. Product Development Life Cycle (PDLC)

**Purpose:** Transform business vision into concrete product requirements and features

### High-Level Process Flow

```
Vision Definition → User Research → Requirements Gathering → Feature Prioritization → 
Product Roadmap → MVP Definition → Stakeholder Review → Product Backlog
```

### Key Activities
1. **Vision Definition** - Articulate product vision and goals
2. **User Research** - Interviews, surveys, user personas
3. **Requirements Gathering** - Functional and non-functional requirements
4. **Feature Prioritization** - MoSCoW method, value vs. effort
5. **Product Roadmap** - Timeline, releases, milestones
6. **MVP Definition** - Minimum viable feature set
7. **Stakeholder Review** - Validation and approval
8. **Product Backlog** - Prioritized list of features/stories

### Deliverables
- Product vision document
- User personas and journey maps
- Product requirements document (PRD)
- Product roadmap
- MVP specification
- Product backlog

### NFR Example
- **Vision**: National cancer surveillance system for firefighters
- **Users**: Firefighters, administrators, researchers, CDC
- **MVP**: Enrollment, consent, questionnaire, data export
- **Roadmap**: Phase 1 (enrollment), Phase 2 (linkage), Phase 3 (analytics)

---

## 3. System Development Life Cycle (SDLC - System)

**Purpose:** Design and implement complete system architecture including all technical components

### High-Level Process Flow

```
Requirements Analysis → System Architecture Design → Technology Selection → 
Component Design → Infrastructure Planning → Integration Planning → 
Implementation → System Testing → Deployment → Maintenance
```

### Key Activities
1. **Requirements Analysis** - Translate product requirements to technical requirements
2. **System Architecture Design** - Overall system structure, components, data flow
3. **Technology Selection** - Platforms, frameworks, tools
4. **Component Design** - Define subsystems and their interactions
5. **Infrastructure Planning** - Servers, networks, cloud resources
6. **Integration Planning** - External systems, APIs, data exchange
7. **Implementation** - Build all system components
8. **System Testing** - Integration testing, end-to-end testing
9. **Deployment** - Production rollout
10. **Maintenance** - Monitoring, updates, optimization

### Deliverables
- System architecture document
- Technical requirements specification
- Infrastructure design
- Integration architecture
- Deployment plan
- System test plan

### NFR Example
- **Architecture**: LAMP stack (Linux, Apache, MySQL, PHP/Drupal)
- **Components**: Web application, database, reporting, integrations
- **Infrastructure**: AWS EC2, multi-site configuration
- **Integrations**: NERIS, state cancer registries

---

## 4. Database Development Life Cycle (DDLC)

**Purpose:** Design, implement, and maintain database structures and data management strategies

### High-Level Process Flow

```
Data Requirements Analysis → Conceptual Data Model → Logical Data Model → 
Physical Data Model → Schema Design → Index Optimization → 
Database Creation → Data Migration → Performance Tuning → Backup/Recovery
```

### Key Activities
1. **Data Requirements Analysis** - Identify data entities, relationships, constraints
2. **Conceptual Data Model** - ER diagrams, entity definitions
3. **Logical Data Model** - Normalized schema, data types
4. **Physical Data Model** - Storage, partitioning, indexes
5. **Schema Design** - Tables, columns, keys, constraints
6. **Index Optimization** - Query performance optimization
7. **Database Creation** - DDL scripts, schema implementation
8. **Data Migration** - Import existing data, transformation
9. **Performance Tuning** - Query optimization, caching
10. **Backup/Recovery** - Data protection strategy

### Deliverables
- ER diagrams
- Data dictionary
- Schema documentation
- Migration scripts
- Backup/recovery procedures

### NFR Example
- **Tables**: nfr_consent, nfr_user_profile, nfr_questionnaire, nfr_work_history, nfr_job_titles, nfr_incident_frequency, nfr_cancer_diagnosis, nfr_correlation_analysis
- **Relationships**: Users → Consent → Profile → Questionnaire → Work History → Job Titles → Incidents
- **Special Features**: JSON storage for flexible questionnaire data

---

## 5. Integration Development Life Cycle (IDLC)

**Purpose:** Design and implement connections between internal systems and external services

### High-Level Process Flow

```
Integration Requirements → API Discovery/Documentation → Interface Design → 
Authentication/Authorization → Data Mapping → Integration Implementation → 
Error Handling → Testing → Monitoring → Maintenance
```

### Key Activities
1. **Integration Requirements** - Define what systems need to connect and why
2. **API Discovery/Documentation** - Review external API specifications
3. **Interface Design** - Define integration points, protocols, data formats
4. **Authentication/Authorization** - Security credentials, API keys, OAuth
5. **Data Mapping** - Transform data between systems
6. **Integration Implementation** - Build connectors, middleware
7. **Error Handling** - Retry logic, fallback strategies
8. **Testing** - Integration testing, mock services
9. **Monitoring** - Track integration health, errors
10. **Maintenance** - Update for API changes

### Deliverables
- Integration architecture document
- API documentation
- Data mapping specifications
- Integration test plans
- Monitoring dashboard

### NFR Example
- **NERIS Integration**: Import firefighter employment data from USFA
- **Cancer Registry Linkage**: Export participant data to state registries
- **Authentication**: API keys, secure credentials management

---

## 6. Security Development Life Cycle (SDL)

**Purpose:** Ensure security is built into every phase of development

### High-Level Process Flow

```
Security Requirements → Threat Modeling → Secure Design → Secure Coding → 
Security Testing → Vulnerability Assessment → Penetration Testing → 
Security Review → Deployment Hardening → Incident Response Planning
```

### Key Activities
1. **Security Requirements** - Identify security and privacy requirements
2. **Threat Modeling** - STRIDE analysis, attack surface identification
3. **Secure Design** - Authentication, authorization, encryption
4. **Secure Coding** - Follow secure coding standards, input validation
5. **Security Testing** - Static/dynamic analysis, dependency scanning
6. **Vulnerability Assessment** - Identify security weaknesses
7. **Penetration Testing** - Simulate attacks
8. **Security Review** - Code review for security issues
9. **Deployment Hardening** - Server configuration, firewall rules
10. **Incident Response Planning** - Breach response procedures

### Deliverables
- Threat model
- Security requirements specification
- Secure coding guidelines
- Security test results
- Penetration test report
- Incident response plan

### NFR Example
- **HIPAA Compliance**: PHI protection, encryption, access controls
- **Authentication**: Drupal user accounts, role-based access
- **Data Protection**: Encrypted database, secure API communications
- **Audit Logging**: Track all data access and modifications

---

## 7. Software Development Life Cycle (SDLC - Software)

**Purpose:** Design, develop, test, and deploy software applications

### High-Level Process Flow

```
Requirements Analysis → Design → Implementation → Unit Testing → 
Code Review → Integration → System Testing → UAT → Deployment → Maintenance
```

### Key Activities
1. **Requirements Analysis** - Functional specifications, user stories
2. **Design** - Architecture, UI/UX, data flow, algorithms
3. **Implementation** - Write code following standards
4. **Unit Testing** - Test individual components
5. **Code Review** - Peer review for quality and standards
6. **Integration** - Combine components
7. **System Testing** - End-to-end testing
8. **UAT** - User acceptance testing
9. **Deployment** - Release to production
10. **Maintenance** - Bug fixes, enhancements

### Deliverables
- Software design document
- Source code
- Unit test suite
- Integration test suite
- User acceptance test results
- Deployment documentation

### NFR Example
- **Language**: PHP 8.3+ (Drupal 11)
- **Framework**: Drupal 11 MVC architecture
- **Components**: Forms, controllers, services, templates
- **Testing**: Drupal testing framework, manual QA

---

## 8. User Experience Development Life Cycle (UXDLC)

**Purpose:** Design intuitive, accessible, and effective user interfaces

### High-Level Process Flow

```
User Research → Personas & Scenarios → Information Architecture → 
Wireframing → Prototyping → Usability Testing → Visual Design → 
Implementation → A/B Testing → Iteration
```

### Key Activities
1. **User Research** - Interviews, surveys, observation
2. **Personas & Scenarios** - Define user types and use cases
3. **Information Architecture** - Site structure, navigation
4. **Wireframing** - Low-fidelity layouts
5. **Prototyping** - Interactive mockups
6. **Usability Testing** - Test with real users
7. **Visual Design** - Colors, typography, branding
8. **Implementation** - HTML/CSS/JavaScript, templates
9. **A/B Testing** - Compare design alternatives
10. **Iteration** - Refine based on feedback

### Deliverables
- User research findings
- User personas
- Wireframes
- Interactive prototypes
- Style guide
- Usability test reports

### NFR Example
- **Users**: Firefighters (primary), administrators, researchers
- **Key Flows**: Enrollment process, dashboard navigation, data entry
- **Accessibility**: WCAG 2.1 AA compliance
- **Design System**: Bootstrap 5, custom NFR styling

---

## 9. Statistical Model Development Life Cycle (SMDLC)

**Purpose:** Develop, validate, and deploy statistical/analytical models

### High-Level Process Flow

```
Problem Definition → Data Collection → Exploratory Data Analysis → 
Feature Engineering → Model Selection → Model Training → Validation → 
Testing → Deployment → Monitoring → Refinement
```

### Key Activities
1. **Problem Definition** - Define research question, hypothesis
2. **Data Collection** - Gather relevant datasets
3. **Exploratory Data Analysis** - Understand data distributions, patterns
4. **Feature Engineering** - Create derived variables, transformations
5. **Model Selection** - Choose appropriate statistical methods
6. **Model Training** - Fit model to training data
7. **Validation** - Cross-validation, hyperparameter tuning
8. **Testing** - Evaluate on held-out test set
9. **Deployment** - Integrate model into production system
10. **Monitoring** - Track model performance over time
11. **Refinement** - Retrain, update as new data arrives

### Deliverables
- Statistical analysis plan
- Data dictionary
- Model specification document
- Validation results
- Model performance reports
- Deployment documentation

### NFR Example
- **Analysis**: Correlation analysis (Pearson, Spearman)
- **Methods**: K-means clustering for participant segmentation
- **Tables**: nfr_correlation_analysis (169 fields, 302 records)
- **Cache**: Pre-computed correlation/cluster results tables
- **Tools**: PHP statistical functions, future R/Python integration

---

## 10. Testing/QA Life Cycle (QALC)

**Purpose:** Ensure software quality through systematic testing

### High-Level Process Flow

```
Test Planning → Test Design → Test Environment Setup → Test Execution → 
Defect Logging → Defect Triage → Retesting → Regression Testing → 
Test Reporting → Test Closure
```

### Key Activities
1. **Test Planning** - Define test strategy, scope, resources
2. **Test Design** - Create test cases, test data
3. **Test Environment Setup** - Configure test systems
4. **Test Execution** - Run test cases, record results
5. **Defect Logging** - Document bugs in tracking system
6. **Defect Triage** - Prioritize and assign bugs
7. **Retesting** - Verify bug fixes
8. **Regression Testing** - Ensure fixes don't break existing functionality
9. **Test Reporting** - Test metrics, coverage, quality reports
10. **Test Closure** - Sign-off, lessons learned

### Deliverables
- Test plan
- Test cases
- Test data
- Defect reports
- Test execution results
- Test summary report

### NFR Example
- **Test Users**: 4 test accounts (firefighter_active, firefighter_retired, nfr_admin, nfr_researcher)
- **Test Scenarios**: Complete enrollment, data entry, reports, admin functions
- **Environments**: Development (local), staging, production
- **Validation**: Form validation, data integrity, permissions

---

## 11. Research Protocol Life Cycle (RPLC) [NFR-Specific]

**Purpose:** Design and execute research studies following scientific and ethical standards

### High-Level Process Flow

```
Research Question → Protocol Design → IRB Submission → IRB Approval → 
Participant Recruitment → Informed Consent → Data Collection → 
Data Management → Analysis → Publication → Study Closure
```

### Key Activities
1. **Research Question** - Define study objectives, hypotheses
2. **Protocol Design** - Study design, methodology, sample size
3. **IRB Submission** - Prepare ethics review application
4. **IRB Approval** - Obtain institutional review board approval
5. **Participant Recruitment** - Enroll eligible participants
6. **Informed Consent** - Obtain consent with full disclosure
7. **Data Collection** - Execute study procedures
8. **Data Management** - Store, clean, validate data
9. **Analysis** - Statistical analysis, interpretation
10. **Publication** - Disseminate findings
11. **Study Closure** - Archive data, close study

### Deliverables
- Research protocol
- IRB application and approval
- Informed consent document
- Data collection instruments
- Data management plan
- Statistical analysis plan
- Publications

### NFR Example
- **Study**: Cancer incidence surveillance among U.S. firefighters
- **IRB**: CDC IRB approval for human subjects research
- **Consent**: Electronic consent with HIPAA authorization
- **Data Collection**: User profile (5 min), enrollment questionnaire (30 min), follow-up surveys
- **Analysis**: Correlation between exposures and cancer outcomes

---

## 12. DevOps/Deployment Life Cycle (DDLC) [Cross-cutting]

**Purpose:** Automate and streamline software delivery and infrastructure operations

### High-Level Process Flow

```
Infrastructure as Code → Version Control → Continuous Integration → 
Automated Testing → Continuous Deployment → Monitoring → 
Incident Response → Optimization → Capacity Planning
```

### Key Activities
1. **Infrastructure as Code** - Define infrastructure in code (Terraform, Ansible)
2. **Version Control** - Git repository management
3. **Continuous Integration** - Automated builds on code changes
4. **Automated Testing** - Run test suites automatically
5. **Continuous Deployment** - Automated production deployments
6. **Monitoring** - Application and infrastructure monitoring
7. **Incident Response** - Alert handling, troubleshooting
8. **Optimization** - Performance tuning, cost optimization
9. **Capacity Planning** - Scale infrastructure as needed

### Deliverables
- Infrastructure code repository
- CI/CD pipeline configuration
- Deployment scripts
- Monitoring dashboards
- Runbooks and procedures

### NFR Example
- **Platform**: AWS EC2, multi-site Apache configuration
- **CI/CD**: GitHub Actions deploy.yml workflow
- **Deployment**: Automated push to production on main branch
- **Monitoring**: Apache logs, Drupal watchdog, application metrics
- **Environments**: Development (localhost), production (forseti.life)

---

## 13. Documentation Life Cycle (DocLC) [Cross-cutting]

**Purpose:** Create and maintain comprehensive documentation throughout development

### High-Level Process Flow

```
Documentation Planning → Requirements Documentation → Design Documentation → 
Technical Documentation → User Documentation → API Documentation → 
Review/Approval → Publication → Maintenance → Archival
```

### Key Activities
1. **Documentation Planning** - Define documentation needs, audiences, formats
2. **Requirements Documentation** - Business requirements, product requirements
3. **Design Documentation** - Architecture, system design, database schema
4. **Technical Documentation** - Code comments, README files, technical guides
5. **User Documentation** - User guides, tutorials, FAQs
6. **API Documentation** - Endpoint specifications, examples
7. **Review/Approval** - Technical review, stakeholder approval
8. **Publication** - Make documentation accessible
9. **Maintenance** - Update as system evolves
10. **Archival** - Preserve historical documentation

### Deliverables
- Business requirements document
- System architecture document
- Technical specifications
- User guides
- API documentation
- Code documentation

### NFR Example
- **Business**: BUSINESS_REQUIREMENTS.md, USER_ROLES_AND_PROCESS_FLOWS.md
- **Technical**: ARCHITECTURE.md, INSTALLATION.md, DRUPAL11_COMPLIANCE.md
- **Implementation**: NFR_MODULE_COMPLETION_SUMMARY.md, QUESTIONNAIRE_SECTIONS_IMPLEMENTATION.md
- **Analysis**: NFR_CORRELATION_ANALYSIS_TABLE.md, CORRELATION_ANALYSIS_USER_GUIDE.md
- **Testing**: TEST_USER_CREDENTIALS.md
- **Location**: /nfr/documentation portal

---

## 14. Compliance/Regulatory Life Cycle (CRLC) [Cross-cutting]

**Purpose:** Ensure system meets legal, regulatory, and standards requirements

### High-Level Process Flow

```
Compliance Requirements → Gap Analysis → Remediation Planning → 
Implementation → Documentation → Audit Preparation → Audit → 
Certification → Ongoing Monitoring → Recertification
```

### Key Activities
1. **Compliance Requirements** - Identify applicable regulations, standards
2. **Gap Analysis** - Compare current state to requirements
3. **Remediation Planning** - Plan to close compliance gaps
4. **Implementation** - Implement compliance controls
5. **Documentation** - Document compliance measures
6. **Audit Preparation** - Prepare evidence, artifacts
7. **Audit** - External audit or assessment
8. **Certification** - Obtain compliance certification
9. **Ongoing Monitoring** - Continuous compliance tracking
10. **Recertification** - Periodic re-audit

### Deliverables
- Compliance requirements matrix
- Gap analysis report
- Remediation plan
- Compliance documentation
- Audit reports
- Certifications

### NFR Example
- **HIPAA**: Protected health information (PHI) security
- **IRB**: Human subjects research ethics approval
- **21 CFR Part 11**: Electronic records and signatures (if applicable)
- **CDC Requirements**: Data collection specifications, reporting standards
- **Privacy**: Informed consent, data protection, confidentiality

---

## 15. Data Governance Life Cycle (DGLC) [Cross-cutting]

**Purpose:** Establish policies and procedures for data management, quality, and protection

### High-Level Process Flow

```
Data Strategy → Data Policy Development → Data Standards → 
Data Quality Framework → Data Access Controls → Data Lifecycle Management → 
Data Privacy/Security → Data Audit → Policy Enforcement → Continuous Improvement
```

### Key Activities
1. **Data Strategy** - Define data vision, goals, governance model
2. **Data Policy Development** - Create data policies, procedures
3. **Data Standards** - Define data definitions, formats, quality rules
4. **Data Quality Framework** - Data profiling, cleansing, validation
5. **Data Access Controls** - Role-based access, permissions
6. **Data Lifecycle Management** - Retention, archival, deletion policies
7. **Data Privacy/Security** - Encryption, anonymization, breach response
8. **Data Audit** - Monitor data quality, access, compliance
9. **Policy Enforcement** - Ensure adherence to policies
10. **Continuous Improvement** - Refine policies based on lessons learned

### Deliverables
- Data governance charter
- Data policies and procedures
- Data dictionary
- Data quality metrics
- Access control matrix
- Retention schedule

### NFR Example
- **Data Stewards**: CDC (protocol owner), NFR admin (system owner)
- **Data Quality**: 93.9% avg quality score across 302 participant records
- **Access Controls**: Role-based permissions (firefighter, admin, researcher)
- **Retention**: Participant data retained for duration of study + follow-up
- **Privacy**: De-identification for research datasets, consent-based linkage

---

## Lifecycle Interdependencies

### Hierarchical Dependencies
- **BDLC** drives **PDLC** - Business strategy informs product vision
- **PDLC** drives **SDLC-System** - Product requirements define system architecture
- **SDLC-System** drives **DDLC**, **IDLC**, **SDL**, **SDLC-Software** - Architecture defines technical implementations
- **SDLC-Software** drives **UXDLC**, **SMDLC**, **QALC** - Software development requires design, models, testing

### Cross-Cutting Dependencies
- **DevOps/DDLC** supports all development lifecycles with automation
- **DocLC** documents all lifecycles
- **CRLC** ensures all lifecycles meet compliance requirements
- **DGLC** governs data across all lifecycles

### Feedback Loops
- **QALC** → **SDLC-Software** - Testing identifies bugs requiring code changes
- **UXDLC** → **PDLC** - User testing validates product requirements
- **Monitoring** → **DDLC** - Production issues drive infrastructure changes
- **Audit** → **CRLC** - Findings trigger remediation

---

## NFR Application Examples

### Current State
- **BDLC**: CDC congressional mandate, cancer surveillance research
- **PDLC**: Enrollment system, data collection, cancer registry linkage
- **SDLC-System**: LAMP stack, Drupal 11, multi-site architecture
- **DDLC**: 7 custom tables, JSON storage for questionnaires
- **IDLC**: NERIS integration (future), cancer registry exports (future)
- **SDL**: HIPAA compliance, role-based access, encrypted storage
- **SDLC-Software**: NFR custom module (539 files, 1.0 release)
- **UXDLC**: Enrollment flow, participant dashboard, admin interfaces
- **SMDLC**: Correlation analysis, K-means clustering, 169-field dataset
- **QALC**: 4 test users, manual testing, validation checks
- **RPLC**: IRB approval, informed consent, 30-min questionnaire
- **DevOps/DDLC**: GitHub Actions, automated deployment to forseti.life
- **DocLC**: 10+ markdown docs in /nfr/documentation portal
- **CRLC**: HIPAA, IRB, CDC protocol compliance
- **DGLC**: 93.9% data quality, role-based access controls

### Future Phases
- **IDLC**: NERIS API integration, state registry data exchange
- **SMDLC**: Predictive models, risk stratification, survival analysis
- **PDLC**: Mobile app, participant portal enhancements, real-time reporting

---

## Best Practices

### Process Integration
1. **Early Involvement** - Involve all lifecycle stakeholders early
2. **Continuous Feedback** - Establish feedback loops between lifecycles
3. **Shared Artifacts** - Use common documentation, requirements traceability
4. **Parallel Execution** - Run multiple lifecycles concurrently where possible
5. **Iterative Development** - Allow for iteration within and across lifecycles

### Governance
1. **Clear Ownership** - Assign lifecycle owners and RACI matrix
2. **Stage Gates** - Define approval checkpoints between phases
3. **Metrics** - Track progress, quality, compliance for each lifecycle
4. **Risk Management** - Identify and mitigate risks at each stage

### Tools
1. **Project Management** - Jira, Asana, GitHub Projects
2. **Documentation** - Confluence, Wiki, Markdown in Git
3. **Version Control** - Git, GitHub
4. **CI/CD** - GitHub Actions, Jenkins
5. **Testing** - Drupal testing framework, Selenium, JMeter
6. **Monitoring** - Application insights, log aggregation

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-27 | NFR Development Team | Initial documentation of process flow relationships |

---

## References

- PMBOK Guide (Project Management Body of Knowledge)
- ISO/IEC 12207 (Software Life Cycle Processes)
- NIST SP 800-64 (Security Considerations in the SDLC)
- ICH E6 (Good Clinical Practice for Research)
- HIPAA Security Rule
- Drupal Development Best Practices
