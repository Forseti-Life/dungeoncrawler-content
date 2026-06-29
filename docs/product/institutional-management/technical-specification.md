# Institutional Management System
**Product**: Forseti Institutional Management  
**Last Updated**: January 11, 2026  
**Status**: 🟡 Design & Implementation Phase  
**Framework**: Drupal 11 Entity System

---

## Overview

The Institutional Management system enables for-profit businesses and organizations to register, manage, and operate institutional facilities within the Forseti safety intelligence platform. Each institution is a first-class entity with comprehensive data management, workflow controls, and integration with the broader Forseti ecosystem.

---

## Multi-Tenancy Architecture

### Group Module Implementation ⭐

**Core Module**: [Group module](https://drupal.org/project/group) (Drupal 11 compatible)

**Purpose**: Provides complete multi-tenancy where each institution operates as an isolated organization with its own members, roles, permissions, and content.

**Architecture Overview**:
```
Institution = Group Entity
├── Group Members (Users with group-specific roles)
│   ├── Institution Owner
│   ├── Institution Administrator  
│   ├── Department Manager
│   ├── Staff Member
│   └── Billing Contact
│
├── Group Content (Entities belonging to this group)
│   ├── Participants (children, students, residents)
│   ├── Staff profiles
│   ├── Departments
│   ├── Schedules
│   ├── Incidents
│   ├── Documents
│   └── Payments
│
└── Group Configuration
    ├── Permissions (role-based within group)
    ├── Settings
    └── Workflows
```

**Key Benefits**:
- ✅ **Complete Isolation**: Each institution's data is segregated
- ✅ **Flexible Membership**: Users can belong to multiple institutions with different roles
- ✅ **Role Inheritance**: Site-wide roles + group-specific roles
- ✅ **Granular Permissions**: Control what each role can do within their institution
- ✅ **Scalable**: Handles 1 to 10,000+ institutions efficiently

**Example Scenario**:
```yaml
User: Jane Doe
  
Institution: Sunshine Preschool
  Role: Institution Owner
  Permissions: Full control (manage staff, participants, billing, settings)
  
Institution: Happy Days Daycare  
  Role: Staff Member
  Permissions: View assigned participants, create incident reports
  
Institution: Rainbow Academy
  Role: Billing Contact
  Permissions: View/manage billing only
```

**Module Stack**:
```yaml
Required:
  - group (core multi-tenancy)
  - group_media (attach media/documents to groups)
  
Optional/Future:
  - subgroup (hierarchical organizations - departments)
  - ginvite (group invitations)
  - group_permissions (advanced permissions UI)
```

**Integration with Institution Entity**:
- Institution entity → Group entity (one-to-one relationship)
- Institution bundle → Group type
- When institution created → Group automatically created
- Group ID stored on institution entity
- Bidirectional relationship

---

### Workflow & State Management

**State Machine Module**: Entity lifecycle state management
- Institution onboarding states (registration → active)
- Participant enrollment states (inquiry → enrolled → active)
- Incident states (reported → investigating → resolved)
- Staff states (application → hired → active → terminated)

**ECA Module**: Event-Condition-Action automation
- Triggers actions on state transitions
- Example: When institution → approved, send welcome email + create admin user
- Example: When participant → enrolled, generate welcome packet + notify staff
- Example: When incident → reported, alert admins + create tasks

**Integration Pattern**:
```
State Machine: Defines what states exist and transition rules
     ↓
ECA: Automates what happens when transitions occur
     ↓
Group: Enforces who can perform transitions within their institution
```

---

## Entity Architecture

### Core Entity: Institution

**Entity Type**: `institution`  
**Bundle Support**: Yes (institution types)  
**Revisions**: Enabled  
**Translation**: Enabled

#### Base Fields

```php
// Core identification
- id (integer, auto-increment)
- uuid (string, auto-generated)
- bundle (string) // institution_type
- label (string) // Institution name
- status (boolean) // Published/Unpublished
- created (timestamp)
- changed (timestamp)
- owner_id (entity_reference → user)

// Business information
- legal_name (string)
- doing_business_as (string)
- tax_id (string, encrypted)
- business_type (list) // LLC, Corporation, Partnership, etc.
- incorporation_date (date)
- incorporation_state (string)

// Contact information
- primary_contact (entity_reference → user)
- billing_contact (entity_reference → user)
- phone (telephone)
- email (email)
- website (uri)

// Physical address
- address (address field)
  - street_address
  - street_address_2
  - city
  - state
  - postal_code
  - country

// Financial
- subscription_tier (entity_reference → subscription_plan)
- billing_status (list) // active, suspended, delinquent, cancelled
- payment_method (entity_reference → payment_method)

// Operations
- operational_status (list) // pending, onboarding, active, suspended, closed
- license_number (string)
- accreditation_status (list)
- capacity (integer) // max occupancy/participants
- current_occupancy (integer)

// Geospatial
- geolocation (geofield) // primary location
- h3_index (string) // H3 hex index for spatial queries
- service_area_radius (integer) // in meters

// Relationships
- parent_institution (entity_reference → institution) // for multi-location
- child_institutions (entity_reference, multiple → institution)
```

---

## Institution Types (Bundles)

```yaml
institution_types:
  - educational:
      label: "Educational Institution"
      subtypes:
        - school_k12
        - preschool
        - university
        - vocational_training
        - tutoring_center
  
  - healthcare:
      label: "Healthcare Facility"
      subtypes:
        - hospital
        - clinic
        - nursing_home
        - mental_health
        - rehabilitation
  
  - correctional:
      label: "Correctional Facility"
      subtypes:
        - prison
        - jail
        - detention_center
        - juvenile_facility
  
  - residential:
      label: "Residential Facility"
      subtypes:
        - group_home
        - assisted_living
        - halfway_house
        - shelter
  
  - commercial:
      label: "Commercial Institution"
      subtypes:
        - corporate_campus
        - retail_center
        - warehouse
        - manufacturing
```

---

## Process Flow: Institution Onboarding

### Phase 1: Registration & Account Creation

**Workflow State**: `registration_pending`

#### Step 1.1: Initial Registration Form
- **Endpoint**: `/institution/register`
- **Method**: POST
- **Access**: Anonymous or authenticated user

**Data Collection**:
```yaml
required_fields:
  - institution_name (label)
  - institution_type (bundle selection)
  - legal_name
  - business_type
  - tax_id
  - primary_contact_email
  - primary_contact_phone
  - address (full address)
  
optional_fields:
  - doing_business_as
  - website
  - description
  - estimated_capacity
```

**Validation Rules**:
- Tax ID must be unique in system
- Email must be valid and not already associated with active institution
- Address must geocode successfully
- Phone number must be valid format

**Actions on Submit**:
1. Create Institution entity (status: unpublished)
2. Set operational_status: `registration_pending`
3. Generate UUID for institution
4. Geocode address → populate geolocation field
5. Calculate H3 index at resolution 9
6. Create institutional admin user account (if new)
7. Assign role: `institution_owner`
8. Send verification email to primary_contact_email
9. Create notification: "Registration received - verification required"

#### Step 1.2: Email Verification
**Workflow State**: `email_verification_pending`

**Process**:
1. User receives email with verification token
2. User clicks link → redirects to `/institution/verify/{token}`
3. Token validated (24-hour expiration)
4. On success:
   - Update operational_status: `document_submission`
   - Send welcome email with next steps
   - Grant access to institution dashboard

**Failed Verification**:
- After 24 hours, send reminder email
- After 7 days, mark institution as `registration_expired`
- Allow re-registration with same data

---

### Phase 2: Document Submission & Verification

**Workflow State**: `document_submission`

#### Step 2.1: Required Documents Upload
**Dashboard**: `/institution/{id}/documents`

**Required Documents by Type**:
```yaml
all_institutions:
  - articles_of_incorporation (PDF)
  - ein_letter (PDF) // IRS EIN confirmation
  - business_license (PDF)
  - liability_insurance (PDF)
  - proof_of_address (PDF) // utility bill or lease

educational:
  - state_education_license (PDF)
  - accreditation_certificate (PDF, optional)
  - facility_inspection_certificate (PDF)

healthcare:
  - medical_facility_license (PDF)
  - hipaa_compliance_certificate (PDF)
  - state_health_inspection (PDF)
  - professional_liability_insurance (PDF)

correctional:
  - department_of_corrections_license (PDF)
  - facility_security_certification (PDF)
  - state_inspection_certificate (PDF)
```

**Document Entity Structure**:
```yaml
entity_type: institutional_document
fields:
  - institution_id (entity_reference → institution)
  - document_type (list, required)
  - file (file, required)
  - upload_date (timestamp)
  - verified (boolean)
  - verified_by (entity_reference → user)
  - verified_date (timestamp)
  - expiration_date (date, optional)
  - status (list) // pending, approved, rejected, expired
  - rejection_reason (text_long)
```

**Upload Process**:
1. User uploads document via file field
2. System validates file type (PDF only)
3. System scans for viruses (ClamAV integration)
4. Document entity created with status: `pending`
5. Notification sent to Forseti admin team
6. Update institution operational_status: `verification_in_progress`

#### Step 2.2: Document Review (Admin Process)
**Dashboard**: `/admin/institutions/pending-verification`

**Admin Workflow**:
1. Admin reviews each uploaded document
2. For each document:
   - Approve → document.status = `approved`, document.verified = TRUE
   - Reject → document.status = `rejected`, add rejection_reason
3. If document rejected:
   - Email institution owner with reason
   - Request resubmission
4. When all required documents approved:
   - Trigger Step 2.3

#### Step 2.3: Background Check & Compliance
**Workflow State**: `background_check`

**Automated Checks**:
```yaml
checks:
  - business_entity_verification:
      source: "Secretary of State API"
      verifies: legal_name, incorporation_state, status
  
  - tax_id_verification:
      source: "IRS EIN lookup (if available)"
      verifies: tax_id matches legal_name
  
  - address_verification:
      source: "USPS API"
      verifies: physical address exists
  
  - sex_offender_registry:
      source: "National Sex Offender Registry API"
      verifies: primary_contact and billing_contact
      
  - sanctions_screening:
      source: "OFAC SDN List"
      verifies: institution and contacts not sanctioned
```

**Manual Review Triggers**:
- Any automated check fails
- Institution type is high-risk (correctional, healthcare)
- Tax ID flagged in previous fraud cases
- Address in high-risk geographic area

---

### Phase 3: Subscription & Payment Setup

**Workflow State**: `payment_setup`

#### Step 3.1: Plan Selection
**Dashboard**: `/institution/{id}/subscription`

**Subscription Tiers**:
```yaml
tiers:
  - starter:
      price: $99/month
      features:
        - 1 location
        - Up to 50 participants
        - Basic safety monitoring
        - Email support
      
  - professional:
      price: $299/month
      features:
        - Up to 5 locations
        - Up to 500 participants
        - Advanced safety analytics
        - Incident management
        - Priority email support
        - Phone support
      
  - enterprise:
      price: $999/month
      features:
        - Unlimited locations
        - Unlimited participants
        - Full safety intelligence suite
        - Custom integrations
        - Dedicated account manager
        - 24/7 support
        - SLA guarantee
      
  - custom:
      price: Contact sales
      features:
        - Custom feature set
        - Contract negotiation
```

**Selection Process**:
1. Institution owner reviews plans
2. Selects appropriate tier
3. System calculates pro-rated first month charge
4. Redirect to payment setup

#### Step 3.2: Payment Method Setup
**Integration**: Stripe Payment Gateway

**Payment Method Entity**:
```yaml
entity_type: payment_method
fields:
  - institution_id (entity_reference → institution)
  - stripe_payment_method_id (string)
  - type (list) // card, ach, wire
  - last_four (string)
  - brand (string) // visa, mastercard, etc.
  - expiration_month (integer)
  - expiration_year (integer)
  - is_default (boolean)
  - billing_address (address)
  - created (timestamp)
```

**Setup Flow**:
1. User enters payment details (Stripe Elements)
2. Stripe validates and tokenizes payment method
3. System creates payment_method entity
4. System creates first invoice
5. Attempt initial charge
6. On success → proceed to Step 3.3
7. On failure → notify user, request different payment method

#### Step 3.3: Initial Payment Processing
**Workflow State**: `payment_processing`

**Invoice Entity**:
```yaml
entity_type: institutional_invoice
fields:
  - institution_id (entity_reference → institution)
  - invoice_number (string, unique)
  - amount (decimal)
  - tax (decimal)
  - total (decimal)
  - billing_period_start (date)
  - billing_period_end (date)
  - due_date (date)
  - status (list) // draft, pending, paid, failed, refunded
  - stripe_invoice_id (string)
  - paid_date (timestamp)
  - line_items (field_collection)
```

**Payment Processing**:
1. Create invoice for pro-rated period
2. Charge payment method via Stripe
3. On success:
   - invoice.status = `paid`
   - billing_status = `active`
   - operational_status = `onboarding_complete`
4. On failure:
   - invoice.status = `failed`
   - Send payment failed notification
   - Keep operational_status = `payment_setup`
   - Allow retry

---

### Phase 4: Initial Configuration & Training

**Workflow State**: `onboarding_active`

#### Step 4.1: Institutional Profile Setup
**Dashboard**: `/institution/{id}/setup`

**Configuration Wizard**:
```yaml
steps:
  1_profile_details:
    - Upload logo
    - Add description
    - Set operating hours
    - Define service offerings
    
  2_staff_setup:
    - Invite staff members
    - Assign roles (admin, manager, staff)
    - Set permissions
    
  3_facility_details:
    - Define zones/areas within institution
    - Set capacity limits per zone
    - Upload floor plans (optional)
    
  4_safety_preferences:
    - Set incident notification preferences
    - Define emergency contacts
    - Configure alert thresholds
    
  5_integration_setup:
    - Connect existing systems (optional)
    - API key generation
    - Webhook configuration
```

**Completion Tracking**:
- Track % completion of each step
- Show progress bar
- Send reminder emails if incomplete after 7 days

#### Step 4.2: Staff Onboarding
**Invitation System**:
1. Institution owner invites staff via email
2. Staff receives invite link
3. Staff creates account or links existing account
4. Staff completes training modules (if required)
5. Staff granted appropriate permissions

**Staff Roles**:
```yaml
roles:
  - institution_owner:
      permissions:
        - Full administrative access
        - Billing management
        - Staff management
        - All reporting access
        
  - institution_admin:
      permissions:
        - Staff management (except owner)
        - Participant management
        - Incident management
        - Reporting access
        
  - institution_manager:
      permissions:
        - Participant management
        - Incident reporting
        - Limited reporting access
        
  - institution_staff:
      permissions:
        - View participants
        - Report incidents
        - View assigned reports
```

#### Step 4.3: Training & Documentation
**Resources Provided**:
- Video tutorial library
- Documentation portal
- Sample workflows
- Best practices guide
- Live onboarding webinar (optional, Professional+ tier)

**Training Tracking**:
- Track which videos watched
- Quiz completion for key concepts
- Certification for institution_admin and institution_manager roles

---

### Phase 5: Go-Live & Activation

**Workflow State**: `active`

#### Step 5.1: Final Review Checklist
**System Verification**:
```yaml
required_for_activation:
  - ✓ All required documents approved
  - ✓ Background checks passed
  - ✓ Payment method validated
  - ✓ Initial payment processed
  - ✓ Profile setup 100% complete
  - ✓ At least 1 staff member (owner) configured
  - ✓ Training completion (owner must complete)
  - ✓ Terms of Service accepted
  - ✓ Privacy Policy accepted
```

#### Step 5.2: Activation
**Process**:
1. System verifies all checklist items completed
2. Institution admin or owner clicks "Activate Institution"
3. System performs final validation
4. Update fields:
   - status = TRUE (published)
   - operational_status = `active`
   - activation_date = now()
5. Send activation confirmation email
6. Grant access to full feature set based on subscription tier
7. Begin monitoring and analytics collection

#### Step 5.3: Post-Activation
**30-Day Onboarding Period**:
- Weekly check-in emails
- Usage analytics tracking
- Automated tips based on usage patterns
- Account manager outreach (Enterprise tier)

**Success Metrics**:
```yaml
metrics:
  - time_to_activation // from registration to active
  - profile_completion_percentage
  - staff_invitation_count
  - first_participant_added_time
  - first_incident_reported_time
  - login_frequency
  - feature_adoption_rate
```

---

## State Transition Diagram

```
registration_pending
  ↓ (email verified)
email_verification_pending
  ↓ (email confirmed)
document_submission
  ↓ (all docs uploaded)
verification_in_progress
  ↓ (docs approved, checks passed)
payment_setup
  ↓ (plan selected)
payment_processing
  ↓ (payment successful)
onboarding_active
  ↓ (setup complete, checklist passed)
active
  ⟷ (suspension for billing/compliance issues)
suspended
  → (voluntary closure or permanent suspension)
closed
```

---

## Related Entities

### Participant Entity
**Entity Type**: `institutional_participant`  
Represents individuals served by the institution (students, patients, residents, etc.)

### Staff Member Entity
**Entity Type**: `institutional_staff`  
Links users to institutions with role assignments

### Incident Entity
**Entity Type**: `institutional_incident`  
Tracks safety incidents, reports, and resolutions within institutions

### Zone Entity
**Entity Type**: `institutional_zone`  
Defines areas within an institution for granular monitoring and capacity management

---

## Technical Implementation Notes

### Drupal Modules Required
- Entity API (core)
- Address Field (contrib)
- Geofield (contrib)
- Telephone (core)
- File (core)
- Workflow (contrib) - for operational_status state machine
- Rules (contrib) - for automated transitions
- Stripe Payment Gateway (custom module)

### Custom Module: `forseti_institutional`
**Location**: `/web/modules/custom/forseti_institutional`

**Components**:
- Entity type definitions
- Form handlers for registration workflow
- Workflow state machine configuration
- Access control and permissions
- Email notification templates
- Background check integration services
- Payment processing integration

---

## Next Steps

1. Design database schema for supporting entities (participant, staff, incident, zone)
2. Define API endpoints for institutional data access
3. Create wireframes for onboarding workflow UI
4. Develop payment integration module
5. Build admin review interfaces
6. Implement notification system
7. Create training materials and documentation

---

**Document Version**: 1.0  
**Author**: Forseti Product Team  
**Review Status**: Draft - Pending Architecture Review
