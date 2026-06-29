# Workflow: Institution Onboarding

**Workflow ID**: WF-001  
**Version**: 1.0  
**Last Updated**: January 10, 2026  
**Status**: 🟡 Draft  
**Priority**: P0 (Critical - MVP)

---

## 1. Overview

**Purpose**: Enable a new for-profit institution to register, verify, configure, and activate their account on the Forseti platform.

**Business Goal**: Convert interested prospects into active, paying customers within 7 days.

**Scope**: Complete end-to-end onboarding from initial registration through first login as an active institution.

**Estimated Duration**: 3-7 days (with all documentation ready)

---

## 2. Actors

### Primary Actors
- 👤 **Institution Owner** - Person registering the institution (future owner role)
- 👤 **Forseti Admin** - Internal team member who reviews and approves
- 🤖 **Onboarding System** - Automated verification and setup processes

### Supporting Actors
- 📧 **Email Service** - Sends notifications and verification emails
- 📧 **Payment Gateway** (Stripe) - Processes subscription payments
- 📧 **Geocoding Service** - Validates and geocodes addresses
- 📧 **Background Check Service** - Verifies business and owner

---

## 3. Preconditions

**Required Before Starting**:
- Institution Owner has internet access
- Institution Owner has valid email address
- Institution exists as legal business entity
- Institution has required business documentation
- Institution has bank account/payment method

**Not Required**:
- Existing Forseti user account (will be created)
- Staff members (added after activation)
- Participants (added after activation)

---

## 4. Process Flow

### Phase 1: Registration

#### Step 1.1: Landing & Registration Form
**Actor**: 👤 Institution Owner

**Actions**:
1. Owner navigates to forseti.life/institution/register
2. Owner views registration form with fields:
   - Institution name (what you'll be called)
   - Institution type (dropdown: Educational, Healthcare, Correctional, Residential, Commercial)
   - Legal business name
   - Business type (LLC, Corporation, Partnership, Sole Proprietor)
   - Tax ID / EIN
   - Primary contact email
   - Primary contact phone
   - Physical address (street, city, state, zip)
   - Website (optional)
   - Estimated capacity (number of participants)

**Data Collected**:
```yaml
registration_data:
  institution_name: string (required, 3-100 chars)
  institution_type: enum (required)
  legal_name: string (required, 3-200 chars)
  business_type: enum (required)
  tax_id: string (required, format: XX-XXXXXXX)
  contact_email: email (required, valid format)
  contact_phone: phone (required, valid format)
  address:
    street: string (required)
    street2: string (optional)
    city: string (required)
    state: string (required, 2-char code)
    zip: string (required, 5 or 9 digits)
    country: string (default: US)
  website: url (optional)
  estimated_capacity: integer (optional)
```

**Validation Rules**:
- Tax ID must be unique (not already in system)
- Email must be valid format and not associated with active institution
- Phone must be valid US format (XXX) XXX-XXXX
- Address must be complete

#### Step 1.2: Form Submission & Initial Processing
**Actor**: 🤖 Onboarding System

**Process**:
1. System validates all required fields
   ✓ Valid → Continue to Step 2
   ✗ Invalid → Show errors, require correction

2. System checks Tax ID uniqueness
   ✓ Unique → Continue
   ✗ Duplicate → Show error: "This Tax ID is already registered. Contact support if you believe this is an error."

3. System geocodes address via Google Maps API or similar
   ✓ Valid address → Capture lat/lng coordinates
   ✗ Invalid address → Request address correction

4. System calculates H3 hexagon index (resolution 9) from coordinates

**Data Created**:
```yaml
institution_record:
  id: auto_increment
  uuid: auto_generated
  status: unpublished
  operational_status: registration_pending
  created_timestamp: now()
  
  # From form
  label: institution_name
  legal_name: legal_name
  doing_business_as: null
  business_type: business_type
  tax_id: tax_id (encrypted)
  phone: contact_phone
  email: contact_email
  website: website
  estimated_capacity: estimated_capacity
  
  # Calculated
  geolocation:
    lat: calculated_latitude
    lng: calculated_longitude
  h3_index: calculated_h3_hex
  address:
    street_address: address.street
    street_address_2: address.street2
    city: address.city
    state: address.state
    postal_code: address.zip
    country: address.country
  
  # Empty for now
  owner_id: null (will be set after user account created)
  subscription_tier: null
  billing_status: null
  current_occupancy: 0
  activation_date: null
```

5. System creates user account for Institution Owner
   - Email: contact_email
   - Username: contact_email
   - Password: Generate random secure password
   - Role: institution_owner (pending)
   - Status: blocked (pending email verification)

**Data Created**:
```yaml
user_account:
  uid: auto_increment
  name: contact_email
  mail: contact_email
  pass: hashed_random_password
  status: 0 (blocked)
  created: now()
  access: 0
  login: 0
  roles:
    - institution_owner_pending
```

6. System links institution to user account
   - institution.owner_id = user.uid

7. System generates email verification token
   - Token: random 64-char string
   - Expiration: 24 hours from now
   - Associated with: user.uid

**Data Created**:
```yaml
verification_token:
  token: random_64_char_string
  user_id: user.uid
  institution_id: institution.id
  created: now()
  expires: now() + 24 hours
  used: false
```

#### Step 1.3: Verification Email Sent
**Actor**: 📧 Email Service

**Process**:
1. System sends email to contact_email

**Email Content**:
```
Subject: Verify your email for Forseti Institutional Management

Hi [institution_name],

Thank you for registering with Forseti! 

Please verify your email address by clicking the link below:
https://forseti.life/institution/verify/{token}

This link expires in 24 hours.

What's next?
1. Click the link to verify your email
2. Set your password
3. Submit required documents
4. Choose your subscription plan
5. Go live!

Questions? Reply to this email or visit forseti.life/support

Welcome to Forseti!
The Forseti Team
```

**System State Update**:
```yaml
institution:
  operational_status: email_verification_pending
```

---

### Phase 2: Email Verification

#### Step 2.1: User Clicks Verification Link
**Actor**: 👤 Institution Owner

**Process**:
1. Owner clicks link in email
2. Browser navigates to: /institution/verify/{token}
3. System validates token:
   ✓ Valid & not expired → Continue to Step 2.2
   ✗ Invalid or expired → Show error page with option to resend

**Decision Point**:
```
Token Valid?
├─ ✓ Yes → Step 2.2 (Set Password)
└─ ✗ No → Show error
      ├─ Expired → Offer to resend verification email
      └─ Invalid → Contact support
```

#### Step 2.2: Set Password
**Actor**: 👤 Institution Owner

**Process**:
1. System shows password setup form:
   - New password (required, min 12 chars, complexity requirements)
   - Confirm password (must match)
   - Accept Terms of Service (checkbox, required)
   - Accept Privacy Policy (checkbox, required)

2. Owner enters password and accepts terms

3. System validates:
   ✓ Passwords match, meets complexity, terms accepted → Continue
   ✗ Validation fails → Show errors

4. System updates user account:

**Data Updated**:
```yaml
user_account:
  pass: hashed_user_password
  status: 1 (active)
  login: now() (first login)
  access: now()
  roles:
    - institution_owner (remove _pending)
  
verification_token:
  used: true
  used_timestamp: now()

institution:
  operational_status: document_submission
```

5. System logs user in automatically
6. Redirect to: /institution/{id}/dashboard (onboarding wizard)

**Success Notification**:
```
🎉 Email verified successfully! Let's get your institution set up.
```

---

### Phase 3: Document Submission

#### Step 3.1: Document Upload Dashboard
**Actor**: 👤 Institution Owner

**Process**:
1. Owner lands on document submission page
2. System displays required documents based on institution_type:

**Required Documents (All Types)**:
```yaml
required_documents:
  - articles_of_incorporation:
      label: "Articles of Incorporation"
      description: "Official documents from your state showing business formation"
      accepted_formats: [PDF]
      max_size: 10MB
      
  - ein_letter:
      label: "EIN Confirmation Letter (IRS CP 575)"
      description: "IRS letter confirming your Tax ID"
      accepted_formats: [PDF]
      max_size: 5MB
      
  - business_license:
      label: "Business License"
      description: "Current business license from your city/county"
      accepted_formats: [PDF]
      max_size: 10MB
      
  - liability_insurance:
      label: "General Liability Insurance Certificate"
      description: "Current certificate showing at least $1M coverage"
      accepted_formats: [PDF]
      max_size: 5MB
      
  - proof_of_address:
      label: "Proof of Physical Address"
      description: "Recent utility bill or lease agreement"
      accepted_formats: [PDF]
      max_size: 5MB
```

**Additional by Type**:
```yaml
educational_institutions:
  - state_education_license:
      label: "State Education License"
      description: "Current license to operate educational facility"
      
  - facility_inspection:
      label: "Facility Safety Inspection"
      description: "Recent inspection certificate from fire marshal or similar"

healthcare_institutions:
  - medical_facility_license:
      label: "Healthcare Facility License"
      
  - hipaa_compliance:
      label: "HIPAA Compliance Certificate"
      
  - state_health_inspection:
      label: "State Health Department Inspection"

# Similar for other types...
```

#### Step 3.2: Upload Each Document
**Actor**: 👤 Institution Owner

**Process** (per document):
1. Owner clicks "Upload" button for document type
2. File picker opens
3. Owner selects PDF file
4. System validates file:
   ✓ PDF format → Continue
   ✗ Wrong format → "Please upload PDF files only"
   
   ✓ Under size limit → Continue
   ✗ Too large → "File too large. Maximum size: {max_size}"

5. System uploads file to server
6. System scans file with antivirus (ClamAV or similar)
   ✓ Clean → Continue
   ✗ Virus detected → Delete file, show error

7. System creates document record

**Data Created** (per document):
```yaml
institutional_document:
  id: auto_increment
  institution_id: institution.id
  document_type: enum (articles_of_incorporation, ein_letter, etc.)
  file_id: reference to file entity
  file:
    uri: private://institutions/{institution_id}/documents/{filename}
    filename: sanitized original filename
    filesize: bytes
    filemime: application/pdf
  upload_date: now()
  uploaded_by: user.uid
  status: pending_review
  verified: false
  verified_by: null
  verified_date: null
  expiration_date: null (set during review if applicable)
  rejection_reason: null
```

8. System shows success message
9. System updates progress indicator (e.g., "3 of 5 documents uploaded")

#### Step 3.3: Submit for Review
**Actor**: 👤 Institution Owner

**Process**:
1. After all required documents uploaded, "Submit for Review" button appears
2. Owner clicks "Submit for Review"
3. System validates all required documents present:
   ✓ All present → Continue
   ✗ Missing documents → "Please upload all required documents"

4. System updates institution status

**Data Updated**:
```yaml
institution:
  operational_status: verification_in_progress
  documents_submitted_date: now()
```

5. System creates notification for Forseti Admin team

**Data Created**:
```yaml
admin_notification:
  type: institution_pending_verification
  institution_id: institution.id
  created: now()
  assigned_to: null
  status: unread
```

6. System sends email to institution owner

**Email Content**:
```
Subject: Documents received - Under review

Hi [institution_name],

We've received your documents and they're now under review by our team.

Documents received:
✓ Articles of Incorporation
✓ EIN Confirmation Letter
✓ Business License
✓ Liability Insurance
✓ Proof of Address
[... any additional based on type]

What's happening now:
Our compliance team is reviewing your documents. This typically takes 1-2 business days.

What's next:
Once approved, you'll receive an email to set up your subscription and payment.

Questions? Email support@forseti.life

Thank you,
The Forseti Team
```

7. Show success message on screen:
```
✓ Documents submitted successfully!

Your documents are now under review. We'll email you within 1-2 business days.

In the meantime, you can:
- Explore your dashboard
- Watch training videos
- Read our getting started guide
```

---

### Phase 4: Admin Review & Verification

#### Step 4.1: Admin Reviews Documents
**Actor**: 👤 Forseti Admin

**Process**:
1. Admin logs into admin dashboard
2. Admin navigates to /admin/institutions/pending-verification
3. System displays list of institutions awaiting review with:
   - Institution name
   - Type
   - Submitted date
   - Number of documents
   - Assigned admin (if any)

4. Admin clicks institution to review
5. System displays institution details and all uploaded documents

6. For each document, admin:
   - Downloads and reviews document
   - Verifies information matches institution profile
   - Checks for completeness and validity
   - Makes decision:
     ✓ Approve → Mark as approved
     ✗ Reject → Add rejection reason

**Document Review Interface**:
```yaml
per_document:
  actions:
    - approve_button: "Approve Document"
    - reject_button: "Reject Document"
    - rejection_reason_field: text area (required if rejecting)
    - expiration_date_field: date picker (optional, for licenses)
```

**Decision Tree**:
```
For Each Document:
├─ Document appears valid?
│  ├─ ✓ Yes → Click "Approve"
│  │        → document.status = approved
│  │        → document.verified = true
│  │        → document.verified_by = admin.uid
│  │        → document.verified_date = now()
│  │
│  └─ ✗ No → Click "Reject"
│           → Enter rejection reason
│           → document.status = rejected
│           → document.rejection_reason = entered text
│           → Send email to institution owner
```

**Data Updated** (per document):
```yaml
# If approved:
institutional_document:
  status: approved
  verified: true
  verified_by: admin_user.uid
  verified_date: now()
  expiration_date: entered_date (if applicable)

# If rejected:
institutional_document:
  status: rejected
  verified: false
  rejection_reason: admin_entered_text
```

#### Step 4.2: Automated Background Checks
**Actor**: 🤖 Onboarding System

**Trigger**: When all documents marked as approved

**Process**:
1. System initiates automated verification checks:

**Check 1: Business Entity Verification**
```yaml
check_business_entity:
  api: "Secretary of State API" (state-specific)
  verify:
    - legal_name exists in state records
    - incorporation_state matches
    - business_status is "Active" or "Good Standing"
  result:
    - pass → Continue
    - fail → Flag for manual review
```

**Check 2: Address Verification**
```yaml
check_address:
  api: "USPS Address Validation API"
  verify:
    - address is valid and deliverable
    - address matches business records
  result:
    - pass → Continue
    - warn → Address standardized, continue
    - fail → Flag for manual review
```

**Check 3: Sex Offender Registry Check**
```yaml
check_sex_offender_registry:
  api: "National Sex Offender Public Website API"
  verify:
    - owner_email/name not in registry
  scope: Owner only at this stage
  result:
    - pass → Continue
    - fail → BLOCK, notify admin immediately
```

**Check 4: Sanctions Screening**
```yaml
check_sanctions:
  api: "OFAC Specially Designated Nationals (SDN) List"
  verify:
    - legal_name not on sanctions list
    - owner_name not on sanctions list
  result:
    - pass → Continue
    - fail → BLOCK, notify admin and compliance team
```

**Data Created**:
```yaml
background_check_result:
  institution_id: institution.id
  check_type: enum (business_entity, address, sex_offender, sanctions)
  check_date: now()
  status: enum (pass, fail, manual_review)
  details: json (API response data)
  checked_by: system
```

**Decision Point**:
```
All Checks Passed?
├─ ✓ Yes → Continue to Step 4.3
└─ ✗ No → Update status, notify admin
      └─ institution.operational_status = background_check_failed
      └─ Create admin task for manual review
```

#### Step 4.3: Final Approval
**Actor**: 👤 Forseti Admin

**Process**:
1. Admin reviews background check results
2. Admin makes final decision:

**Data Updated**:
```yaml
institution:
  operational_status: payment_setup
  documents_approved_date: now()
  approved_by: admin_user.uid
```

3. System sends approval email to institution owner

**Email Content**:
```
Subject: Your institution has been approved! 🎉

Hi [institution_name],

Great news! Your institution has been approved and verified.

Next Steps:
1. Choose your subscription plan
2. Set up payment method
3. Complete your profile setup
4. Start inviting staff members

Click here to continue: [Link to /institution/{id}/subscription]

Welcome to the Forseti family!
The Forseti Team
```

---

### Phase 5: Subscription & Payment Setup

#### Step 5.1: Plan Selection
**Actor**: 👤 Institution Owner

**Process**:
1. Owner navigates to /institution/{id}/subscription
2. System displays subscription tiers:

**Tier Options**:
```yaml
subscription_tiers:
  - starter:
      name: "Starter"
      price: 99
      billing_period: monthly
      features:
        - "1 location"
        - "Up to 50 participants"
        - "Basic safety monitoring"
        - "Incident reporting"
        - "Email support"
      limits:
        max_locations: 1
        max_participants: 50
        support_level: email
        
  - professional:
      name: "Professional"
      price: 299
      billing_period: monthly
      features:
        - "Up to 5 locations"
        - "Up to 500 participants"
        - "Advanced safety analytics"
        - "Incident management"
        - "Priority email & phone support"
        - "API access"
      limits:
        max_locations: 5
        max_participants: 500
        support_level: priority
        
  - enterprise:
      name: "Enterprise"
      price: 999
      billing_period: monthly
      features:
        - "Unlimited locations"
        - "Unlimited participants"
        - "Full safety intelligence suite"
        - "Custom integrations"
        - "Dedicated account manager"
        - "24/7 support"
        - "SLA guarantee"
      limits:
        max_locations: unlimited
        max_participants: unlimited
        support_level: enterprise
```

3. Owner selects tier by clicking "Choose [Tier Name]"
4. System shows confirmation modal with:
   - Selected tier details
   - Price breakdown
   - Pro-rated first month calculation (if not starting on 1st)
   - "Confirm Selection" button

5. Owner clicks "Confirm Selection"

**Data Updated**:
```yaml
institution:
  subscription_tier: selected_tier_id
  subscription_selected_date: now()
```

6. System redirects to payment setup: /institution/{id}/payment

#### Step 5.2: Payment Method Setup
**Actor**: 👤 Institution Owner via 📧 Stripe

**Process**:
1. Owner lands on payment setup page
2. System displays Stripe payment form (Stripe Elements embedded)
   - Card number
   - Expiration date
   - CVC
   - Cardholder name
   - Billing address

3. Owner enters payment details
4. Stripe validates card in real-time (client-side)
5. Owner clicks "Add Payment Method"
6. Stripe tokenizes payment method → returns payment_method_id
7. System saves payment method

**Data Created**:
```yaml
payment_method:
  id: auto_increment
  institution_id: institution.id
  stripe_payment_method_id: stripe_pm_xxxxxx
  type: card
  card_brand: visa/mastercard/amex/discover
  card_last_four: 1234
  card_exp_month: 12
  card_exp_year: 2026
  billing_address:
    street: entered_street
    city: entered_city
    state: entered_state
    zip: entered_zip
    country: entered_country
  is_default: true
  created: now()
  status: active
```

8. System creates first invoice

**Data Created**:
```yaml
invoice:
  id: auto_increment
  invoice_number: INST-[institution.id]-[timestamp] (e.g., INST-123-20260110)
  institution_id: institution.id
  amount: calculated_base_price
  tax: calculated_tax (based on state)
  total: amount + tax
  billing_period_start: today
  billing_period_end: end_of_current_month (or +30 days)
  due_date: now() (immediate)
  status: draft
  stripe_invoice_id: null (will be set after creation)
  line_items:
    - description: "[Tier Name] Subscription (Pro-rated)"
      quantity: 1
      unit_price: calculated_prorated_amount
      amount: calculated_prorated_amount
```

**Calculation Example**:
```
If today is January 10, 2026:
- Selected tier: Professional ($299/month)
- Days in January: 31
- Days remaining: 22 (including today)
- Pro-rated amount: $299 * (22/31) = $212.26
- Tax (example CA): $212.26 * 0.0725 = $15.39
- Total first invoice: $227.65
```

#### Step 5.3: Process Initial Payment
**Actor**: 🤖 Onboarding System via 📧 Stripe

**Process**:
1. System creates invoice in Stripe
2. System attaches payment method to invoice
3. System attempts to charge payment method

**Decision Point**:
```
Payment Successful?
├─ ✓ Yes → Continue to Step 5.4
│         → invoice.status = paid
│         → invoice.paid_date = now()
│         → institution.billing_status = active
│
└─ ✗ No → Payment Failed
          → invoice.status = payment_failed
          → Show error to user
          → Allow retry or different payment method
```

**Data Updated** (if successful):
```yaml
invoice:
  status: paid
  paid_date: now()
  stripe_invoice_id: stripe_inv_xxxxxx
  stripe_charge_id: stripe_ch_xxxxxx
  payment_method_id: payment_method.id

institution:
  billing_status: active
  subscription_start_date: now()
  next_billing_date: beginning_of_next_month (or +30 days)
  operational_status: onboarding_active
```

#### Step 5.4: Payment Confirmation
**Actor**: 👤 Institution Owner

**Process**:
1. System shows success message:
```
✓ Payment successful!

Your [Tier Name] subscription is now active.

Receipt sent to: [email]
Next billing date: [date]
Amount: $[monthly_price]

Let's finish setting up your institution →
```

2. System sends receipt email (via Stripe)
3. System redirects to: /institution/{id}/setup

---

### Phase 6: Initial Configuration

#### Step 6.1: Setup Wizard Welcome
**Actor**: 👤 Institution Owner

**Process**:
1. Owner lands on setup wizard
2. System displays progress tracker:
```
Setup Progress: 0%

1. ⭕ Profile Details
2. ⭕ Staff Setup
3. ⭕ Facility Details
4. ⭕ Safety Preferences
5. ⭕ Review & Activate
```

3. System shows welcome message:
```
Welcome to Forseti! Let's get your institution set up.

This should take about 15-20 minutes.
You can save and come back anytime.

[Continue] [Save & Exit]
```

#### Step 6.2: Profile Details
**Actor**: 👤 Institution Owner

**Form Fields**:
```yaml
profile_setup:
  branding:
    - logo_upload: image (optional, JPG/PNG, max 2MB)
    - primary_color: color picker (optional)
    - secondary_color: color picker (optional)
    
  description:
    - mission_statement: textarea (optional, max 500 chars)
    - services_offered: checkboxes (multiple)
    - description: rich text (optional, max 2000 chars)
    
  operating_details:
    - operating_hours:
        monday: {open: time, close: time, closed: checkbox}
        tuesday: {open: time, close: time, closed: checkbox}
        # ... for each day
    - seasonal_schedule: yes/no toggle
    - holiday_closures: multiselect (federal holidays)
    
  capacity:
    - maximum_capacity: integer (required)
    - age_range_min: integer (optional)
    - age_range_max: integer (optional)
```

**Data Updated**:
```yaml
institution:
  logo: file_reference
  primary_color: #RRGGBB
  secondary_color: #RRGGBB
  mission_statement: text
  services_offered: array
  description: text
  operating_hours: json
  seasonal_schedule: boolean
  holiday_closures: array
  maximum_capacity: integer
  age_range_min: integer
  age_range_max: integer
```

**Progress Update**: 20%

#### Step 6.3: Staff Setup (Initial)
**Actor**: 👤 Institution Owner

**Process**:
1. System explains staff roles
2. Owner can:
   - Invite staff members (email invites)
   - Skip and add later

**Optional at this stage** - Can proceed without inviting staff

**If adding staff**:
```yaml
staff_invitation:
  email: email (required)
  first_name: string (required)
  last_name: string (required)
  role: enum (admin, manager, staff)
  message: text (optional, personal message)
```

**Data Created** (per invitation):
```yaml
staff_invitation:
  id: auto_increment
  institution_id: institution.id
  email: entered_email
  first_name: entered_first_name
  last_name: entered_last_name
  role: selected_role
  invited_by: user.uid
  invited_date: now()
  status: pending
  token: random_64_char_string
  expires: now() + 7_days
```

**Progress Update**: 40%

#### Step 6.4: Facility Details
**Actor**: 👤 Institution Owner

**Form Fields**:
```yaml
facility_details:
  size:
    - total_square_feet: integer (optional)
    - number_of_rooms: integer (optional)
    
  zones:
    - zone_name: string (e.g., "Main Room", "Classroom A")
    - zone_capacity: integer
    - zone_type: enum (classroom, office, restroom, kitchen, outdoor, etc.)
    # Can add multiple zones
    
  accessibility:
    - ada_accessible: yes/no
    - ada_features: checkboxes (ramps, elevators, accessible restrooms, etc.)
    
  emergency:
    - number_of_exits: integer
    - fire_extinguishers: integer
    - emergency_assembly_point: text (location description)
```

**Data Created** (per zone):
```yaml
institutional_zone:
  id: auto_increment
  institution_id: institution.id
  name: entered_zone_name
  capacity: entered_capacity
  zone_type: selected_type
  floor: integer (optional)
  description: text (optional)
  is_accessible: boolean
  created: now()
```

**Progress Update**: 60%

#### Step 6.5: Safety Preferences
**Actor**: 👤 Institution Owner

**Form Fields**:
```yaml
safety_setup:
  emergency_contacts:
    - contact_name: string
    - contact_relationship: string (e.g., "Board President")
    - contact_phone: phone
    - contact_email: email
    # Can add multiple
    
  notification_preferences:
    - incident_notifications: checkboxes (email, sms, both)
    - incident_severity_threshold: enum (all, major_only, critical_only)
    - daily_summary_email: yes/no
    - weekly_report_email: yes/no
    
  alert_thresholds:
    - occupancy_alert_percentage: integer (e.g., 90 = alert at 90% capacity)
    - staff_ratio_alert: yes/no
    - minimum_staff_alert: integer (min staff on duty)
```

**Data Updated**:
```yaml
institution:
  emergency_contacts: json array
  incident_notification_method: enum or array
  incident_severity_threshold: enum
  daily_summary_enabled: boolean
  weekly_report_enabled: boolean
  occupancy_alert_threshold: integer
  staff_ratio_alert_enabled: boolean
  minimum_staff_count: integer
```

**Progress Update**: 80%

#### Step 6.6: Review & Activate
**Actor**: 👤 Institution Owner

**Process**:
1. System displays summary of all configured settings:
   - Profile details ✓
   - Operating hours ✓
   - Zones configured: X
   - Staff invited: X
   - Emergency contacts: X

2. System shows final checklist:
```yaml
activation_checklist:
  required:
    - ✓ Profile information complete
    - ✓ Payment method configured
    - ✓ Terms of Service accepted
    - ✓ At least 1 zone defined
    
  recommended:
    - ⭕ Staff members invited (0 invited)
    - ⭕ Emergency contacts added
    - ✓ Operating hours set
```

3. Owner reviews and clicks "Activate My Institution"

4. System performs final validation:
   ✓ All required items complete → Activate
   ✗ Missing required items → Show errors

**Data Updated**:
```yaml
institution:
  status: true (published)
  operational_status: active
  activation_date: now()
  setup_completed_date: now()
```

**Progress Update**: 100%

---

### Phase 7: Activation & Onboarding Complete

#### Step 7.1: Activation Confirmation
**Actor**: 👤 Institution Owner

**Process**:
1. System shows success screen:
```
🎉 Congratulations! Your institution is now active!

You can now:
✓ Add participants
✓ Manage staff
✓ Track attendance
✓ Report incidents
✓ View analytics

[Go to Dashboard] [Watch Tutorial] [Read Documentation]
```

2. System sends activation email

**Email Content**:
```
Subject: 🎉 Your institution is live!

Hi [institution_name],

Congratulations! Your institution is now active on Forseti.

Your Dashboard: https://forseti.life/institution/{id}/dashboard

What you can do now:
• Add your first participant
• Invite staff members
• Explore your dashboard
• Set up daily routines

Getting Started Resources:
• Quick Start Guide
• Video tutorials
• Feature documentation
• Live support chat

Need help? We're here for you!
- Email: support@forseti.life
- Chat: Available in your dashboard
- Phone: [Professional & Enterprise tiers]

Welcome to Forseti!
The Forseti Team
```

3. System initiates 30-day onboarding support period

**Data Created**:
```yaml
onboarding_support_period:
  institution_id: institution.id
  start_date: now()
  end_date: now() + 30_days
  check_in_emails:
    - day_1: "Welcome & first steps"
    - day_3: "Adding your first participants"
    - day_7: "Week 1 check-in"
    - day_14: "Using incident reporting"
    - day_30: "How are we doing?"
  status: active
```

#### Step 7.2: Dashboard First Visit
**Actor**: 👤 Institution Owner

**Process**:
1. Owner clicks "Go to Dashboard"
2. System displays institution dashboard with:
   - Welcome message
   - Quick action cards:
     * "Add Your First Participant"
     * "Invite Staff Members"
     * "Schedule Your First Day"
     * "Watch Tutorial Video"
   - Real-time metrics (all zeros initially):
     * Current occupancy: 0
     * Staff on duty: 1 (owner)
     * Today's incidents: 0
   - Next steps checklist
   - Help resources

3. Onboarding workflow complete!

---

## 5. Data Requirements Summary

### Entities Created During Workflow

**Institution Entity**:
```yaml
institution:
  # Core identification
  - id, uuid, status, created, changed
  
  # Business information
  - label, legal_name, doing_business_as
  - business_type, tax_id, incorporation_date, incorporation_state
  
  # Contact
  - phone, email, website
  - address (street, city, state, zip, country)
  
  # Geospatial
  - geolocation (lat/lng), h3_index
  
  # Operations
  - operational_status, maximum_capacity, current_occupancy
  - operating_hours, seasonal_schedule, holiday_closures
  - age_range_min, age_range_max
  
  # Financial
  - subscription_tier, billing_status
  - subscription_start_date, next_billing_date
  
  # Ownership
  - owner_id (user reference)
  - approved_by (admin user reference)
  - activation_date, setup_completed_date
  
  # Branding
  - logo, primary_color, secondary_color
  - mission_statement, description, services_offered
  
  # Safety
  - emergency_contacts (json)
  - notification preferences
  - alert thresholds
```

**User Account** (Institution Owner):
```yaml
user:
  - uid, name, mail, pass, status
  - roles: [institution_owner]
  - created, access, login
```

**Institutional Document** (multiple):
```yaml
institutional_document:
  - id, institution_id, document_type
  - file (reference)
  - upload_date, uploaded_by
  - status, verified, verified_by, verified_date
  - expiration_date, rejection_reason
```

**Payment Method**:
```yaml
payment_method:
  - id, institution_id
  - stripe_payment_method_id
  - type, card_brand, card_last_four
  - card_exp_month, card_exp_year
  - billing_address
  - is_default, status, created
```

**Invoice**:
```yaml
invoice:
  - id, invoice_number, institution_id
  - amount, tax, total
  - billing_period_start, billing_period_end
  - due_date, paid_date
  - status, stripe_invoice_id, stripe_charge_id
  - payment_method_id
  - line_items (json)
```

**Institutional Zone** (multiple):
```yaml
institutional_zone:
  - id, institution_id
  - name, capacity, zone_type
  - floor, description
  - is_accessible, created
```

**Background Check Result** (multiple):
```yaml
background_check_result:
  - id, institution_id
  - check_type, check_date
  - status, details
  - checked_by
```

**Verification Token**:
```yaml
verification_token:
  - token, user_id, institution_id
  - created, expires, used
  - used_timestamp
```

**Staff Invitation** (optional, multiple):
```yaml
staff_invitation:
  - id, institution_id
  - email, first_name, last_name, role
  - invited_by, invited_date
  - status, token, expires
```

---

## 6. Validation Rules

### Business Rules

1. **Tax ID Uniqueness**: No two institutions can share the same Tax ID
2. **Email Uniqueness**: Institution contact email must be unique per active institution
3. **Address Validation**: Must geocode to valid coordinates
4. **Document Completeness**: All required documents for institution type must be approved
5. **Payment Validation**: Must have valid payment method before activation
6. **Minimum Setup**: Must have at least 1 zone defined before activation

### Data Validation

1. **Tax ID Format**: XX-XXXXXXX (where X is digit)
2. **Phone Format**: US phone numbers in E.164 format
3. **Email Format**: Valid email per RFC 5322
4. **ZIP Code**: 5 or 9 digits (XXXXX or XXXXX-XXXX)
5. **Password**: Minimum 12 characters, must include uppercase, lowercase, number, special char
6. **File Upload**: PDF only, maximum 10MB per document
7. **Capacity**: Must be positive integer

---

## 7. Success Criteria

### Workflow Success = Institution Activated

**Required Outcomes**:
- ✅ Institution entity created with status = active
- ✅ Owner account created and verified
- ✅ All required documents uploaded and approved
- ✅ Background checks passed
- ✅ Payment method configured
- ✅ First payment processed successfully
- ✅ At least 1 zone defined
- ✅ Operating hours configured
- ✅ Owner can log in to dashboard

### Key Metrics

1. **Time to Activation**: Average 3-7 days (target: < 5 days)
2. **Completion Rate**: % of started registrations that reach activation (target: 70%)
3. **Document Approval Rate**: % approved on first submission (target: 80%)
4. **Payment Success Rate**: % successful on first attempt (target: 95%)
5. **Support Ticket Rate**: Support tickets per activation (target: < 0.5)

---

## 8. Error Handling

### Common Errors & Recovery

**Email Verification Expired**:
- Action: Offer to resend verification email
- System: Generate new token, send new email
- User: Click new link to continue

**Document Rejection**:
- Action: Email owner with specific rejection reason
- System: Allow document replacement upload
- User: Upload corrected document, resubmit for review

**Payment Failure**:
- Action: Show clear error message
- System: Allow retry with same method or add different method
- User: Update payment details or try different card

**Background Check Failure**:
- Action: Admin manual review required
- System: Escalate to compliance team
- User: May need to provide additional information

**Incomplete Setup**:
- Action: Save progress, allow return later
- System: Send reminder emails at day 1, 3, 7
- User: Can resume from where they left off

---

## 9. Derived Entities (To Be Detailed)

From this workflow, we need detailed entity documentation for:

1. **Institution** ← Primary entity (see data requirements above)
2. **User** (Institution Owner role)
3. **Institutional Document**
4. **Payment Method**
5. **Invoice**
6. **Institutional Zone**
7. **Background Check Result**
8. **Verification Token**
9. **Staff Invitation**
10. **Subscription Tier** (configuration entity)

**Next**: Create detailed entity documentation for each in `/entities/` directory

---

**Workflow Complete**: ✅  
**Next Workflow**: Staff Onboarding  
**Entities to Document**: 10 entities identified
