# Institutional Management MVP
**Product**: Forseti Institutional Management  
**Last Updated**: January 10, 2026  
**Status**: 🟡 MVP Definition  
**Methodology**: Lean Startup

---

## Executive Summary

This document defines the Minimum Viable Product (MVP) for what an institution needs to successfully operate using the Forseti Institutional Management platform. Focus is on core operational requirements, not technical implementation.

---

## MVP Philosophy

**Goal**: Enable an institution to open its doors and operate safely on day one.

**Core Value Proposition**: 
> "Everything you need to manage your institution's operations, safety, and compliance in one integrated platform."

---

## 1. Core Institution Profile

### What Every Institution Needs

#### 1.1 Basic Information
**Purpose**: Establish institutional identity and legal compliance

```yaml
essential_information:
  identity:
    - Institution legal name
    - "Doing Business As" name (if different)
    - Institution type/category
    - Logo and branding
    - Mission statement
    
  legal_compliance:
    - Business license number
    - Tax ID (EIN)
    - State incorporation details
    - Insurance certificates
    - Required permits by type
    
  contact_details:
    - Primary phone number
    - Emergency contact number
    - General email address
    - Emergency email distribution list
    - Physical address
    - Mailing address (if different)
    - Website URL
    
  operational_details:
    - Operating hours (daily schedule)
    - Seasonal schedules (if applicable)
    - Holiday closures
    - Emergency closure procedures
    - Maximum capacity
    - Age ranges served
    - Services provided
```

#### 1.2 Location & Facilities
**Purpose**: Define physical space and capacity constraints

```yaml
facility_essentials:
  primary_location:
    - Complete street address
    - Geocoordinates (for mapping)
    - Accessible entrance locations
    - Parking information
    - Public transit access
    
  facility_layout:
    - Total square footage
    - Number of rooms/areas
    - Room capacities
    - Emergency exits mapped
    - ADA accessibility features
    - Restricted areas defined
    
  zones_definition:
    # Minimum: Define logical areas for capacity management
    examples:
      - Main entrance/reception
      - Administrative offices
      - Activity/service areas
      - Restrooms
      - Kitchen/dining (if applicable)
      - Outdoor areas (if applicable)
      - Emergency assembly areas
```

---

## 2. Staff Management System

### What Every Institution Needs to Manage Staff

#### 2.1 Staff Roster & Roles
**Purpose**: Track who works here and what they do

```yaml
staff_essentials:
  basic_roster:
    per_staff_member:
      - Full legal name
      - Preferred name
      - Photo (for ID badge)
      - Role/title
      - Department
      - Start date
      - Employment status (full-time, part-time, contractor)
      - Contact information (phone, email, emergency)
      
  role_hierarchy:
    owner_level:
      - Full administrative control
      - Billing and subscription management
      - Can hire/terminate all staff
      
    administrator_level:
      - Day-to-day operations management
      - Staff scheduling
      - Participant management
      - Incident response authority
      - Reporting access
      
    manager_level:
      - Supervise specific departments/areas
      - Schedule their team
      - Report incidents
      - View their area's data
      
    staff_level:
      - Interact with participants
      - Report incidents
      - Check in/out participants
      - View their own schedules
      
  background_check_tracking:
    - Background check status
    - Date completed
    - Renewal date
    - Clearance level
    - Any restrictions/conditions
```

#### 2.2 Scheduling & Time Management
**Purpose**: Know who's working when

```yaml
scheduling_needs:
  shift_management:
    - Create shifts by role and zone
    - Assign staff to shifts
    - Track shift coverage gaps
    - Manage shift swaps
    - Emergency coverage protocol
    
  time_tracking:
    - Clock in/out system
    - Actual hours worked
    - Overtime tracking
    - PTO/sick leave balance
    - Break compliance
    
  minimum_staffing_rules:
    - Required staff-to-participant ratios by zone
    - Required supervisor presence
    - Certification requirements by shift
    - Emergency minimum staffing levels
```

#### 2.3 Staff Training & Certifications
**Purpose**: Ensure staff are qualified and compliant

```yaml
training_tracking:
  required_training:
    - Onboarding checklist completion
    - Safety procedures
    - Emergency response
    - Incident reporting
    - Privacy/confidentiality (HIPAA, FERPA, etc.)
    - Institution-specific protocols
    
  certifications:
    - Certification type
    - Issue date
    - Expiration date
    - Renewal requirements
    - Alert when expiring (30/60/90 days)
    
  ongoing_development:
    - Training hours required per year
    - Completed training log
    - Performance reviews
```

---

## 3. Participant Management System

### What Every Institution Needs to Track Participants

**Note**: "Participants" is the universal term. This could be students, patients, residents, clients, inmates, etc. depending on institution type.

#### 3.1 Participant Registry
**Purpose**: Know who you're serving

```yaml
participant_essentials:
  basic_profile:
    - Full legal name
    - Preferred name/pronouns
    - Photo (if age-appropriate and permitted)
    - Date of birth
    - Age/grade level
    - Gender identity
    - Emergency contacts (multiple)
    - Medical alerts (allergies, conditions)
    - Communication preferences
    - Language(s) spoken
    
  enrollment_information:
    - Enrollment date
    - Status (active, inactive, alumni, withdrawn)
    - Program/track enrolled in
    - Assigned staff/case manager
    - Expected completion date
    - Fee schedule/billing tier
    
  legal_guardianship:
    - Guardian/parent names
    - Custody arrangements
    - Authorized pickup persons
    - Restricted contacts
    - Court orders (if applicable)
    
  contact_information:
    - Home address
    - Phone numbers
    - Email addresses
    - Preferred contact method
    - Portal access credentials
```

#### 3.2 Attendance & Check-In System
**Purpose**: Know who's here right now

```yaml
attendance_tracking:
  daily_check_in_out:
    - Check-in time and method
    - Who checked them in
    - Expected departure time
    - Actual check-out time
    - Who picked them up (if minor)
    
  attendance_patterns:
    - Scheduled attendance (days/times)
    - Actual attendance percentage
    - Absence tracking
    - Tardy tracking
    - Early departure tracking
    
  real_time_roster:
    - Current occupancy count
    - By zone/room/area
    - Staff-to-participant ratios current status
    - Capacity alerts
    
  absence_management:
    - Excused vs unexcused
    - Absence reason
    - Documentation required
    - Notification to parents/guardians
    - Pattern alerts (chronic absence)
```

#### 3.3 Participant Records & Progress
**Purpose**: Track services delivered and progress made

```yaml
records_management:
  service_delivery:
    - Services/programs enrolled in
    - Schedule of services
    - Attendance at services
    - Staff providing service
    - Service notes/observations
    
  progress_tracking:
    - Goals set
    - Milestones defined
    - Progress assessments
    - Achievement tracking
    - Reports generated
    
  documentation:
    - Required forms on file
    - Signed permissions/waivers
    - Medical documentation
    - Educational records (if applicable)
    - Assessment results
    - Progress reports
    
  communication_log:
    - All communications with participant
    - Communications with family/guardians
    - Phone calls, emails, meetings
    - Notes and outcomes
```

---

## 4. Safety & Incident Management

### What Every Institution Needs for Safety

#### 4.1 Incident Reporting & Tracking
**Purpose**: Document and respond to safety events

```yaml
incident_system:
  incident_types:
    - Medical emergency
    - Injury (minor/major)
    - Behavioral incident
    - Property damage
    - Security breach
    - Unauthorized absence
    - Altercation/conflict
    - Accident
    - Near miss
    - Policy violation
    - Complaint
    - Emergency evacuation
    
  incident_report_essentials:
    - Date and time of incident
    - Location (zone/area)
    - Incident type and severity
    - Participants involved
    - Staff involved
    - Witnesses
    - Detailed description
    - Actions taken
    - Medical attention provided (if any)
    - Notifications made
    - Photos/evidence (if applicable)
    
  response_workflow:
    - Immediate response taken
    - Supervisor notification
    - Guardian notification (if required)
    - Authority notification (police, CPS, health dept)
    - Documentation completion
    - Follow-up required
    - Status tracking (open, investigating, resolved)
    
  incident_analytics:
    - Incident frequency by type
    - Incident patterns by time/location
    - Staff response time metrics
    - Resolution time tracking
    - Recurring participants in incidents
```

#### 4.2 Emergency Procedures
**Purpose**: Respond effectively to emergencies

```yaml
emergency_management:
  emergency_types:
    - Fire
    - Medical emergency
    - Severe weather
    - Active threat
    - Hazardous materials
    - Evacuation
    - Lockdown
    - Shelter in place
    - Utility failure
    
  emergency_contacts:
    - 911/local emergency services
    - Fire department direct line
    - Police department direct line
    - Poison control
    - Nearest hospital
    - Institution emergency team
    - Board of directors emergency contact
    - Insurance company hotline
    
  emergency_protocols:
    - Step-by-step procedures for each type
    - Staff assignments during emergency
    - Evacuation routes and assembly points
    - Communication tree
    - Accountability procedures
    - Reunion procedures (for minors)
    - Media response protocol
    
  drills_and_training:
    - Required drill frequency
    - Drill completion log
    - Drill evaluation notes
    - Staff training on procedures
    - Participant education
```

#### 4.3 Safety Monitoring & Compliance
**Purpose**: Maintain safe environment proactively

```yaml
safety_systems:
  facility_safety:
    - Daily safety inspection checklist
    - Equipment maintenance log
    - Hazard reporting system
    - Corrective action tracking
    - Safety audit results
    
  environmental_monitoring:
    - Temperature monitoring (HVAC)
    - Air quality (if required)
    - Water quality (if regulated)
    - Food safety (if applicable)
    - Pest control log
    
  security_measures:
    - Access control system
    - Visitor sign-in/out log
    - Security camera coverage (if applicable)
    - Key/access card management
    - After-hours access log
    
  compliance_tracking:
    - Regulatory agency (state, federal)
    - License/accreditation requirements
    - Inspection schedules
    - Inspection results
    - Corrective action plans
    - Compliance deadlines
```

---

## 5. Financial Operations

### What Every Institution Needs for Financial Management

#### 5.1 Billing & Revenue
**Purpose**: Get paid for services provided

```yaml
billing_essentials:
  fee_structure:
    - Service/program pricing
    - Payment plans available
    - Sibling/multi-service discounts
    - Scholarship/financial aid
    - Late payment policies
    
  participant_billing:
    - Invoicing system
    - Payment tracking by participant
    - Outstanding balance alerts
    - Payment method on file
    - Auto-pay enrollment
    - Payment history
    
  payment_processing:
    - Accept credit/debit cards
    - Accept ACH/bank transfers
    - Accept checks (with tracking)
    - Cash acceptance (with receipts)
    - Payment receipt generation
    - Refund processing
    
  revenue_tracking:
    - Revenue by service type
    - Revenue by payment method
    - Monthly/annual revenue trends
    - Outstanding receivables
    - Bad debt write-offs
```

#### 5.2 Expense Management
**Purpose**: Track money going out

```yaml
expense_tracking:
  expense_categories:
    - Payroll and benefits
    - Facility rent/mortgage
    - Utilities
    - Insurance
    - Supplies and materials
    - Equipment purchases
    - Maintenance and repairs
    - Professional services
    - Marketing and advertising
    - Licensing and permits
    - Technology/software subscriptions
    
  expense_management:
    - Expense entry/import
    - Receipt attachment
    - Approval workflow
    - Vendor management
    - Purchase order system
    - Budget vs actual tracking
```

#### 5.3 Financial Reporting
**Purpose**: Understand financial health

```yaml
financial_reports:
  essential_reports:
    - Profit & Loss statement
    - Cash flow statement
    - Balance sheet
    - Budget vs actual
    - Revenue by service
    - Expense by category
    - Accounts receivable aging
    - Participant payment status
    
  reporting_periods:
    - Monthly
    - Quarterly
    - Annually
    - Custom date ranges
    
  financial_health_indicators:
    - Current occupancy vs capacity
    - Revenue per participant
    - Staff cost as % of revenue
    - Operating margin
    - Cash reserves (months)
    - Receivables collection time
```

---

## 6. Communication Systems

### What Every Institution Needs to Communicate

#### 6.1 Internal Communication
**Purpose**: Keep staff informed and coordinated

```yaml
staff_communication:
  channels:
    - Announcement system (all-staff broadcasts)
    - Department/team channels
    - Direct messaging between staff
    - Emergency alerts
    - Shift handoff notes
    
  communication_types:
    - Daily updates/bulletins
    - Policy changes
    - Schedule changes
    - Participant alerts/updates
    - Safety notifications
    - Training reminders
    - Event coordination
    
  documentation:
    - Staff handbook access
    - Policy manual
    - Training materials
    - Forms library
    - Emergency procedures
```

#### 6.2 External Communication (Families/Guardians)
**Purpose**: Keep families informed and engaged

```yaml
family_communication:
  channels:
    - Email notifications
    - SMS text alerts
    - In-app messages
    - Parent portal
    - Newsletter
    - Phone calls
    
  communication_types:
    - Daily updates on participant
    - Attendance notifications
    - Incident reports
    - Progress reports
    - Billing statements
    - Event invitations
    - General announcements
    - Emergency alerts
    
  privacy_controls:
    - Custodial parent restrictions
    - Information sharing preferences
    - Communication preferences
    - Language translation needs
```

#### 6.3 Community & Marketing Communication
**Purpose**: Attract new participants and maintain reputation

```yaml
external_communication:
  marketing_channels:
    - Website
    - Social media
    - Email campaigns
    - Community events
    - Referral program
    
  inquiry_management:
    - Lead capture form
    - Tour scheduling
    - Information requests
    - Application tracking
    - Waitlist management
    
  reputation_management:
    - Review monitoring
    - Response to reviews
    - Testimonial collection
    - Success stories
    - Media relations
```

---

## 7. Reporting & Analytics

### What Every Institution Needs to Know

#### 7.1 Operational Dashboards
**Purpose**: Real-time operational awareness

```yaml
dashboard_essentials:
  real_time_metrics:
    - Current occupancy
    - Staff on duty
    - Check-in/check-out activity
    - Today's schedule adherence
    - Open incidents
    - Urgent tasks/alerts
    
  daily_summary:
    - Total participants today
    - Attendance percentage
    - Staff hours worked
    - Incidents reported
    - Revenue collected
    - Outstanding tasks
```

#### 7.2 Performance Reports
**Purpose**: Understand operational performance

```yaml
performance_tracking:
  participant_metrics:
    - Enrollment trends
    - Retention rates
    - Attendance averages
    - Progress outcomes
    - Satisfaction scores
    
  staff_metrics:
    - Staff retention
    - Training completion rates
    - Incident response times
    - Schedule adherence
    - Productivity metrics
    
  financial_metrics:
    - Revenue growth
    - Cost per participant
    - Payment collection rate
    - Budget variance
    - Profitability by service
    
  safety_metrics:
    - Incident frequency
    - Incident severity trends
    - Response time averages
    - Compliance audit scores
    - Drill completion rate
```

#### 7.3 Compliance Reporting
**Purpose**: Prove compliance to regulators

```yaml
compliance_reports:
  regulatory_reporting:
    - State licensing reports
    - Attendance records
    - Staff qualification reports
    - Safety inspection results
    - Incident summaries
    - Financial audits
    
  accreditation_evidence:
    - Program quality metrics
    - Outcome measurements
    - Staff development records
    - Participant satisfaction
    - Continuous improvement plans
    
  report_scheduling:
    - Automatic generation
    - Email delivery
    - Deadline reminders
    - Submission tracking
```

---

## 8. Administrative Functions

### What Every Institution Needs Operationally

#### 8.1 Forms & Documents Management
**Purpose**: Manage paperwork efficiently

```yaml
document_system:
  required_forms:
    - Enrollment application
    - Emergency contact form
    - Medical information form
    - Release/consent forms
    - Handbook acknowledgment
    - Photo/video consent
    - Pickup authorization
    
  form_management:
    - Digital form completion
    - Electronic signatures
    - Form completion tracking
    - Document storage
    - Version control
    - Expiration tracking
    
  reporting_templates:
    - Progress reports
    - Incident reports
    - Inspection reports
    - Financial reports
    - Custom templates
```

#### 8.2 Calendar & Events
**Purpose**: Schedule and coordinate activities

```yaml
calendar_system:
  calendar_types:
    - Master institutional calendar
    - Department/program calendars
    - Staff schedules
    - Participant schedules
    - Facility reservations
    
  event_management:
    - Create events
    - Invite participants/staff
    - RSVP tracking
    - Resource allocation
    - Event reminders
    - Event documentation
    
  scheduling_tools:
    - Recurring events
    - Conflict detection
    - Capacity management
    - Cancellation notifications
    - Weather-related changes
```

#### 8.3 Asset & Inventory Management
**Purpose**: Track institutional resources

```yaml
asset_tracking:
  physical_assets:
    - Equipment inventory
    - Furniture inventory
    - Technology inventory
    - Vehicle tracking (if applicable)
    
  asset_details:
    - Purchase date
    - Purchase cost
    - Serial number
    - Condition
    - Location
    - Assigned to
    - Maintenance schedule
    - Warranty information
    
  supplies_inventory:
    - Current stock levels
    - Reorder points
    - Supplier information
    - Purchase history
    - Usage tracking
    - Budget allocation
```

---

## 9. Integration & Data Exchange

### What Every Institution Needs to Connect

#### 9.1 Essential Integrations
**Purpose**: Connect with external systems

```yaml
integration_priorities:
  payment_processing:
    - Stripe/payment gateway
    - Bank account connection
    - Accounting software sync
    
  communication_tools:
    - Email service (SendGrid, etc.)
    - SMS service (Twilio, etc.)
    - Calendar sync (Google, Outlook)
    
  background_checks:
    - Background check provider API
    - Automated status updates
    
  geospatial_safety:
    - Forseti H3 crime data
    - Local incident mapping
    - Community safety alerts
```

#### 9.2 Data Import/Export
**Purpose**: Move data in and out efficiently

```yaml
data_exchange:
  import_capabilities:
    - Bulk participant upload (CSV)
    - Staff roster import
    - Historical attendance import
    - Financial data import
    
  export_capabilities:
    - Participant roster export
    - Attendance reports export
    - Financial data export
    - Compliance reports export
    - Custom report exports
    
  data_backup:
    - Automated daily backups
    - Manual backup on demand
    - Data restoration capability
    - Data retention policy
```

---

## 10. Support & Training

### What Every Institution Needs to Succeed

#### 10.1 Onboarding Support
**Purpose**: Get up and running quickly

```yaml
onboarding_essentials:
  initial_setup_assistance:
    - Setup wizard guidance
    - Data migration help
    - Configuration consultation
    - Staff training session
    
  documentation_provided:
    - Quick start guide
    - Video tutorials
    - Feature documentation
    - Best practices guide
    - FAQ database
    
  training_program:
    - Owner/admin training (required)
    - Staff training modules
    - Certification for key roles
    - Ongoing webinar series
```

#### 10.2 Ongoing Support
**Purpose**: Keep institution running smoothly

```yaml
support_structure:
  support_channels:
    - Help desk / ticketing system
    - Email support
    - Phone support (tier-dependent)
    - Live chat (tier-dependent)
    - Community forum
    
  response_times:
    starter_tier:
      - Email: 24-48 hours
    professional_tier:
      - Email: 12-24 hours
      - Phone: Business hours
    enterprise_tier:
      - Email: 4-8 hours
      - Phone: 24/7
      - Dedicated account manager
      
  resources:
    - Knowledge base
    - Video library
    - Downloadable guides
    - Template library
    - Webinar recordings
```

---

## MVP Feature Priority Matrix

### Phase 1: Must-Have (Day 1 Operations)
**Cannot operate without these**

1. ✅ Institution profile setup
2. ✅ Staff roster and basic scheduling
3. ✅ Participant registry
4. ✅ Check-in/check-out system
5. ✅ Basic incident reporting
6. ✅ Emergency contact information
7. ✅ Payment processing
8. ✅ Basic billing/invoicing

### Phase 2: Should-Have (Week 1 Operations)
**Significantly improves operations**

1. ⏳ Attendance tracking and reporting
2. ⏳ Staff schedule management
3. ⏳ Parent/guardian communication
4. ⏳ Document storage
5. ⏳ Basic reporting dashboards
6. ⏳ Calendar and events

### Phase 3: Could-Have (Month 1 Operations)
**Nice to have, enhances experience**

1. ⏳ Advanced scheduling (shift swaps, etc.)
2. ⏳ Progress tracking for participants
3. ⏳ Asset management
4. ⏳ Marketing/inquiry tools
5. ⏳ Advanced analytics
6. ⏳ Custom reporting

### Phase 4: Future Enhancements
**Valuable but not essential for MVP**

1. 🔮 Mobile app for staff
2. 🔮 Parent mobile app
3. 🔮 AI-powered insights
4. 🔮 Predictive analytics
5. 🔮 Advanced integrations
6. 🔮 Marketplace features

---

## Success Metrics for MVP

### Institution Success
```yaml
metrics:
  activation_success:
    - 90% of institutions go live within 7 days
    - 95% complete onboarding checklist
    - 80% invite at least 3 staff members
    
  operational_adoption:
    - 70% daily login rate in first month
    - 90% of participants entered in system
    - 80% of staff trained on core features
    
  retention:
    - 85% month-to-month retention
    - 70% still active after 6 months
    - NPS score above 40
```

### User Satisfaction
```yaml
satisfaction_metrics:
  ease_of_use:
    - "Easy to learn" rating: 4+/5
    - "Easy to use daily" rating: 4+/5
    - Time to first participant check-in: < 30 minutes
    
  value_delivered:
    - "Saves time" agreement: 80%
    - "Improves safety" agreement: 90%
    - "Worth the price" agreement: 75%
```

### Product Performance
```yaml
technical_metrics:
  reliability:
    - 99.5% uptime
    - Page load time < 2 seconds
    - Mobile responsive: 100% features
    
  support:
    - First response time: < 24 hours
    - Resolution time: < 48 hours
    - Support satisfaction: 4+/5
```

---

## Next Steps: MVP Development

### Immediate Actions

1. **Validate Assumptions**
   - Interview 10 institution operators
   - Confirm feature priorities
   - Test pricing assumptions

2. **Design UI/UX**
   - Wireframes for core workflows
   - Mobile-first approach
   - Accessibility compliance

3. **Build MVP**
   - Phase 1 features only
   - Simple, clean interface
   - Focus on reliability

4. **Beta Testing**
   - Recruit 5-10 pilot institutions
   - 30-day pilot period
   - Gather feedback intensively

5. **Iterate & Launch**
   - Fix critical issues
   - Add Phase 2 features based on feedback
   - Public launch

---

**Document Version**: 1.0  
**Author**: Forseti Product Team  
**Next Review**: February 2026  
**Status**: Ready for Stakeholder Review
