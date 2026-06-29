# Founder's Guide: How to Establish Your Institution

**Last Updated**: January 10, 2026  
**Audience**: Aspiring institutional founders  
**Purpose**: Complete roadmap from concept to opening day  
**Format**: Website guide / Founder onboarding flow

---

## Overview

This guide walks you through every step of establishing your institution from initial idea to opening your doors. Whether you're starting a school, healthcare facility, residential program, or any other institutional business, this roadmap ensures you don't miss critical steps.

**Estimated Timeline**: 6-18 months from concept to opening (varies by institution type and location)

---

## Phase 1: Foundation (Months 1-3)

### Step 1: Define Your Mission

**Why This Matters**: Your mission is the north star that guides every decision you'll make. It answers "Why does this institution need to exist?"

**What You'll Do**:

1. **Identify the Problem You're Solving**
   - What gap exists in your community?
   - Who is underserved?
   - What need are you addressing?

2. **Define Your Target Population**
   - Who will you serve? (age range, demographics, characteristics)
   - How many people will you serve?
   - What geographic area?

3. **Articulate Your Unique Approach**
   - What makes your institution different?
   - What's your philosophy or methodology?
   - What outcomes will you achieve?

4. **Write Your Mission Statement**
   - Keep it clear and concise (1-2 sentences)
   - Focus on impact, not activities
   - Make it memorable

**Example Mission Statements**:
```
Educational: "To provide high-quality STEM education to underserved 
middle school students, preparing them for college and careers in 
technology fields."

Healthcare: "To deliver compassionate, evidence-based mental health 
care in a safe, accessible community setting for adults recovering 
from substance abuse."

Residential: "To create a supportive home environment where young 
adults aging out of foster care can develop life skills and achieve 
independence."
```

**Deliverable**: ✅ Mission statement documented

**Data to Capture**:
```yaml
founder_profile:
  mission_statement: text (1-2 sentences)
  problem_solved: text (description)
  target_population:
    age_range: string (e.g., "5-18")
    demographics: array of strings
    geographic_area: string
    estimated_size: integer
  unique_approach: text
  expected_outcomes: array of strings
```

---

### Step 2: Choose Your Institution Name

**Why This Matters**: Your name is your first impression. It should reflect your mission, be memorable, and be legally available.

**What You'll Do**:

1. **Brainstorm Names**
   - Reflect your mission or values
   - Easy to spell and pronounce
   - Memorable and distinctive
   - Appropriate for your institution type
   - Consider acronym potential

2. **Check Name Availability**
   - Search your state's business registry
   - Check domain availability (.com, .org)
   - Search trademark database (USPTO.gov)
   - Google the name (avoid confusion with existing organizations)
   - Check social media handles

3. **Choose Your Legal Entity Name**
   - Your official business name
   - May include "LLC" or "Inc." designation
   - Must be unique in your state

4. **Consider "Doing Business As" (DBA)**
   - Your marketing/public name
   - Can be different from legal name
   - Also must be available

**Naming Best Practices**:
- Avoid trendy terms that may date quickly
- Don't limit future growth (avoid overly specific names)
- Test it with your target audience
- Make sure it translates well (if serving multilingual community)
- Consider SEO and searchability

**Legal Considerations**:
- Avoid names that are too similar to existing institutions
- Don't use restricted words without proper credentials ("university," "college," "bank")
- Some states require specific words for certain institution types

**Deliverable**: ✅ Institution name selected and verified as available

**Data to Capture**:
```yaml
institution_naming:
  preferred_name: string (your primary choice)
  legal_name: string (with entity designation)
  dba_name: string (if different)
  alternative_names: array (backups)
  name_availability_checked:
    state_registry: boolean
    domain_available: boolean
    trademark_clear: boolean
    social_media_available: boolean
  domain_purchased: string (URL)
  tagline: string (optional, 1 sentence)
```

---

### Step 3: Research Legal Requirements

**Why This Matters**: Different institution types have different regulatory requirements. You must know what you're getting into before investing time and money.

**What You'll Do**:

1. **Determine Your Institution Type Classification**
   - Educational facility
   - Healthcare facility
   - Residential care facility
   - Correctional/detention facility
   - Commercial/industrial facility
   - Child care facility
   - Other specialized type

2. **Research State Requirements**
   - Licensing requirements
   - Facility standards
   - Staff-to-participant ratios
   - Staff qualifications and certifications
   - Background check requirements
   - Ongoing inspection schedules

3. **Research Local Requirements**
   - Zoning restrictions
   - City/county business licenses
   - Fire safety codes
   - Health department regulations
   - Building permits

4. **Federal Requirements** (if applicable)
   - HIPAA compliance (healthcare)
   - FERPA compliance (education)
   - ADA accessibility
   - Food service licensing (if providing meals)
   - Background checks (federal requirements)

5. **Industry Accreditation** (optional but valuable)
   - Research relevant accrediting bodies
   - Understand accreditation requirements
   - Timeline to achieve accreditation

**Create Your Compliance Checklist**:
```yaml
compliance_requirements:
  state_licenses:
    - name: "State Educational Facility License"
      issuing_agency: "State Department of Education"
      estimated_cost: "$500"
      processing_time: "3-6 months"
      renewal_frequency: "Annual"
      requirements:
        - completed_application
        - facility_inspection
        - proof_of_insurance
        - staff_background_checks
        - curriculum_approval
  
  local_permits:
    - name: "Business License"
      issuing_agency: "City Business Office"
      # ... similar structure
  
  federal_compliance:
    - name: "ADA Compliance"
      requirements: [...]
```

**Deliverable**: ✅ Comprehensive compliance requirements list

**Data to Capture**:
```yaml
regulatory_research:
  institution_type: enum (educational, healthcare, etc.)
  state: string
  county: string
  city: string
  
  required_licenses: array of objects
    - license_name: string
    - issuing_authority: string
    - cost: decimal
    - processing_time: string
    - requirements: array
    - renewal_frequency: string
  
  facility_requirements:
    - square_feet_minimum: integer
    - staff_to_participant_ratio: string (e.g., "1:10")
    - required_rooms: array
    - safety_equipment: array
    - accessibility_requirements: array
  
  estimated_total_licensing_cost: decimal
  estimated_timeline_to_licensure: string
```

---

### Step 4: Develop Your Business Model

**Why This Matters**: You need to know if this institution can be financially sustainable before you invest significant resources.

**What You'll Do**:

1. **Define Your Services/Programs**
   - What specific services will you offer?
   - What are the program details (schedule, duration, curriculum)?
   - Will you have different tiers or levels of service?

2. **Set Your Pricing**
   - Research competitors in your area
   - Calculate your costs (see Step 5)
   - Determine pricing strategy:
     * Cost-plus pricing
     * Market-rate pricing
     * Value-based pricing
     * Sliding scale/income-based
   - Consider payment plans, scholarships, subsidies

3. **Project Your Revenue**
   - How many participants at full capacity?
   - What's realistic enrollment in Year 1, 2, 3?
   - What's your pricing per participant?
   - Any additional revenue streams? (grants, donations, ancillary services)

4. **Identify Your Funding Sources**
   - Participant fees (primary revenue)
   - Government contracts/subsidies
   - Grants (foundation, government)
   - Donations/fundraising
   - Investment/loans
   - Personal capital

**Revenue Model Template**:
```yaml
revenue_model:
  primary_service:
    name: "Full-time enrollment"
    price: 1500  # per month
    expected_participants_year_1: 30
    expected_participants_year_2: 50
    expected_participants_year_3: 75
    annual_revenue_potential: 1350000  # $1500 x 75 x 12
  
  additional_services:
    - name: "Before/after care"
      price: 200  # per month
      adoption_rate: 60  # % of primary participants
      additional_annual_revenue: 108000
  
  other_revenue:
    - source: "State subsidy"
      amount_per_participant: 500  # per month
      expected_annual: 450000
    - source: "Foundation grants"
      expected_annual: 50000
  
  total_projected_revenue_year_1: 720000
  total_projected_revenue_year_2: 1200000
  total_projected_revenue_year_3: 1958000
```

**Deliverable**: ✅ Revenue projections for Years 1-3

**Data to Capture**:
```yaml
business_model:
  services_offered: array of objects
    - service_name: string
    - description: text
    - pricing: decimal
    - pricing_model: enum (monthly, hourly, per_session, etc.)
    - target_enrollment: integer
  
  competitive_analysis:
    - competitor_name: string
    - competitor_pricing: decimal
    - competitor_capacity: integer
    - differentiators: array
  
  revenue_projections:
    year_1: decimal
    year_2: decimal
    year_3: decimal
    
  funding_sources: array
    - source_type: enum (fees, grants, donations, loans)
    - amount: decimal
    - probability: percentage
```

---

### Step 5: Create Your Financial Plan

**Why This Matters**: Most new institutions fail due to poor financial planning. You must understand your costs and cash flow needs.

**What You'll Do**:

1. **Calculate Startup Costs** (One-time expenses)
   - Legal and business formation: $2,000 - $5,000
   - Licenses and permits: $500 - $5,000+
   - Facility deposit and renovations: $10,000 - $100,000+
   - Equipment and furnishings: $5,000 - $50,000+
   - Technology setup: $2,000 - $10,000
   - Initial insurance premiums: $3,000 - $10,000
   - Marketing and website: $2,000 - $10,000
   - Initial inventory/supplies: $2,000 - $10,000
   - Working capital (3-6 months operating expenses): $30,000 - $200,000+
   - Professional services (accountant, consultant): $2,000 - $10,000
   - **Total Startup Capital Needed**: $58,500 - $410,000+ (highly variable)

2. **Calculate Monthly Operating Expenses**
   - Rent/mortgage: $____
   - Utilities: $____
   - Staff payroll and benefits (largest expense): $____
   - Insurance: $____
   - Supplies and materials: $____
   - Technology/software subscriptions: $____
   - Marketing: $____
   - Professional services: $____
   - Maintenance and repairs: $____
   - Miscellaneous: $____
   - **Total Monthly Operating**: $____

3. **Calculate Break-Even Point**
   - How many participants needed to cover costs?
   - How long until you reach break-even enrollment?
   - Monthly cash burn rate until break-even

4. **Create Cash Flow Projections**
   - Month-by-month for first 12 months
   - Quarterly for years 2-3
   - Account for seasonal variations
   - Include contingency (10-20%)

5. **Identify Funding Gap**
   - Total capital needed: Startup costs + operating shortfall until break-even
   - How will you fund this gap?

**Financial Planning Template**:
```yaml
financial_plan:
  startup_costs:
    legal_formation: 3000
    licenses_permits: 2000
    facility_deposit: 15000
    facility_renovations: 25000
    equipment_furniture: 30000
    technology: 5000
    initial_insurance: 5000
    marketing_website: 5000
    supplies: 5000
    working_capital: 100000
    professional_services: 5000
    total_startup: 200000
  
  monthly_operating_expenses:
    rent: 5000
    utilities: 1000
    payroll: 25000  # 5 staff @ $5000 avg
    payroll_taxes_benefits: 7500  # 30%
    insurance: 1500
    supplies: 2000
    technology_subscriptions: 500
    marketing: 1000
    maintenance: 500
    miscellaneous: 1000
    total_monthly: 45000
  
  break_even_analysis:
    monthly_operating_cost: 45000
    revenue_per_participant: 1500
    participants_needed_break_even: 30
    expected_months_to_break_even: 6
    total_capital_needed: 470000  # startup + 6 months burn
  
  funding_plan:
    personal_investment: 100000
    bank_loan: 200000
    investor_capital: 100000
    grants: 50000
    friends_family: 20000
    total_funding: 470000
```

**Deliverable**: ✅ Complete financial plan with 12-month cash flow projection

**Data to Capture**:
```yaml
financial_planning:
  startup_costs: object (detailed breakdown)
  monthly_expenses: object (detailed breakdown)
  
  break_even_analysis:
    participants_needed: integer
    months_to_break_even: integer
    total_capital_required: decimal
  
  cash_flow_projections:
    month_1: {revenue: decimal, expenses: decimal, net: decimal, cumulative: decimal}
    # ... for 12 months
  
  funding_sources:
    - source: string
      amount: decimal
      status: enum (secured, pending, target)
      terms: text
```

---

## Phase 2: Legal Formation (Months 3-5)

### Step 6: Choose Your Business Structure

**Why This Matters**: Your business structure affects your taxes, personal liability, and how you can raise capital.

**What You'll Do**:

1. **Understand Your Options**:

   **Sole Proprietorship**
   - Pros: Simple, low cost
   - Cons: Personal liability, hard to raise capital
   - Best for: Very small, low-risk operations
   
   **Limited Liability Company (LLC)**
   - Pros: Personal liability protection, tax flexibility, simpler than corporation
   - Cons: Varies by state, may be harder to get investment
   - Best for: Most small to medium institutions
   
   **C Corporation**
   - Pros: Strong liability protection, easy to raise capital, can have multiple owners
   - Cons: Double taxation, more complex, more expensive
   - Best for: Large institutions planning to raise significant investment
   
   **S Corporation**
   - Pros: Liability protection, pass-through taxation
   - Cons: Ownership restrictions, more complex than LLC
   - Best for: Mid-size institutions with US citizen owners
   
   **Nonprofit Corporation (501c3)**
   - Pros: Tax-exempt, can receive tax-deductible donations
   - Cons: No ownership/equity, strict regulations, can't distribute profits
   - Best for: Mission-driven institutions not seeking profit

2. **Consult with Professionals**
   - Attorney: Structure recommendations, formation
   - Accountant: Tax implications
   - Business advisor: Industry best practices

3. **Make Your Decision**
   - Consider your funding strategy
   - Consider tax implications
   - Consider long-term vision (exit strategy?)

**Recommendation for Most For-Profit Institutions**: Start with LLC for flexibility and liability protection. Can convert later if needed.

**Deliverable**: ✅ Business structure selected

**Data to Capture**:
```yaml
business_structure:
  entity_type: enum (sole_proprietor, llc, s_corp, c_corp, nonprofit)
  state_of_incorporation: string
  reasoning: text
  professional_consultants:
    attorney: {name: string, firm: string, cost: decimal}
    accountant: {name: string, firm: string, cost: decimal}
```

---

### Step 7: Legally Form Your Entity

**Why This Matters**: This makes your business official and provides legal protection.

**What You'll Do**:

1. **File Formation Documents with Your State**
   - LLC: Articles of Organization
   - Corporation: Articles of Incorporation
   - Cost: $50 - $500 depending on state
   - Timeline: 1-4 weeks

2. **Create Operating Documents**
   - LLC: Operating Agreement (defines ownership, management, profit distribution)
   - Corporation: Bylaws (rules for governance)
   - Shareholder Agreement (if multiple owners)

3. **Obtain Federal Employer Identification Number (EIN)**
   - Apply online at IRS.gov (free, immediate)
   - Needed to open bank account, hire employees, file taxes

4. **Register for State Taxes**
   - Sales tax (if applicable)
   - Employer taxes
   - Requirements vary by state

5. **Open Business Bank Account**
   - Keep personal and business finances separate (critical!)
   - Shop for small business banking options
   - Bring EIN, formation documents, ID

6. **Set Up Business Credit Card**
   - Start building business credit
   - Easier expense tracking
   - May provide cash flow flexibility

**Deliverable**: ✅ Legal entity formed and operational

**Data to Capture**:
```yaml
legal_formation:
  formation_date: date
  entity_legal_name: string
  state_of_incorporation: string
  filing_number: string
  ein: string
  registered_agent: string
  
  ownership_structure:
    - owner_name: string
      ownership_percentage: decimal
      role: string
  
  business_bank_account:
    bank_name: string
    account_opened_date: date
  
  documents_completed:
    - articles_filed: boolean
    - operating_agreement: boolean
    - ein_obtained: boolean
    - bank_account: boolean
```

---

### Step 8: Obtain Business Insurance

**Why This Matters**: Protects your investment and is required for licensing in most cases.

**What You'll Do**:

1. **General Liability Insurance** (Required)
   - Covers injury, property damage
   - Typical requirement: $1M per occurrence, $2M aggregate
   - Cost: $500 - $3,000/year

2. **Professional Liability Insurance** (Often Required)
   - Covers errors, negligence in service delivery
   - Also called "Errors & Omissions" (E&O)
   - Cost: $1,000 - $5,000/year

3. **Property Insurance** (Required if you own building)
   - Covers building and contents
   - Cost: Varies by property value

4. **Workers' Compensation Insurance** (Required when you hire)
   - Covers employee injuries
   - Cost: Varies by state and risk level

5. **Commercial Auto Insurance** (If you have vehicles)
   - Covers vehicles used for business
   - Cost: Varies

6. **Other Coverage to Consider**:
   - Cyber liability insurance
   - Sexual abuse and molestation coverage (for child-serving institutions)
   - Directors and officers insurance
   - Business interruption insurance

**Shop Around**:
- Get quotes from 3+ insurance providers
- Consider bundling policies
- Ask about industry-specific packages

**Deliverable**: ✅ Required insurance policies in place

**Data to Capture**:
```yaml
insurance_coverage:
  - policy_type: "General Liability"
    provider: string
    policy_number: string
    coverage_amount: decimal
    premium_annual: decimal
    effective_date: date
    expiration_date: date
    
  # ... repeat for each policy type
  
  total_annual_insurance_cost: decimal
```

---

## Phase 3: Facility & Operations (Months 4-8)

### Step 9: Secure Your Facility

**Why This Matters**: Your physical space must meet regulatory requirements and serve your operational needs.

**What You'll Do**:

1. **Define Your Space Requirements**
   - Minimum square footage (based on regulations and capacity)
   - Number and type of rooms needed
   - Accessibility requirements (ADA)
   - Parking requirements
   - Outdoor space (if required)
   - Zoning requirements (check with city)

2. **Search for Locations**
   - Work with commercial real estate agent
   - Consider: location, accessibility, condition, cost
   - Check zoning (can your institution type operate here?)

3. **Evaluate Potential Spaces**
   - Does it meet size requirements?
   - Structural condition
   - Required renovations and cost
   - Lease terms or purchase price
   - Location accessibility for target population

4. **Negotiate Lease or Purchase**
   - Lease: Try for 3-5 year term with renewal option
   - Include tenant improvement allowance if possible
   - Consider rent-free period for buildout
   - Review with attorney before signing

5. **Plan Renovations/Buildout**
   - Meet with contractor
   - Get multiple bids
   - Obtain permits
   - Timeline: 2-4 months typically

6. **Final Inspections**
   - Fire marshal inspection
   - Health department (if applicable)
   - Building inspector
   - Licensing agency inspection
   - Obtain certificate of occupancy

**Facility Checklist**:
```yaml
facility_requirements:
  location:
    address: string
    square_footage: integer
    lease_or_own: enum
    monthly_cost: decimal
    
  required_spaces:
    - "Reception/lobby area"
    - "Administrative office(s)"
    - "Main activity/service areas (X rooms)"
    - "Restrooms (ADA compliant)"
    - "Storage"
    - "Kitchen/break room (if meals provided)"
    - "Outdoor area (if required)"
    - "Emergency exits (minimum 2)"
    
  accessibility:
    ada_compliant: boolean
    parking_spaces: integer
    public_transit_accessible: boolean
    
  safety_features:
    fire_extinguishers: integer
    smoke_detectors: integer
    first_aid_kits: integer
    emergency_lighting: boolean
    security_system: boolean
```

**Deliverable**: ✅ Facility secured and ready for occupancy

**Data to Capture**:
```yaml
facility_details:
  address: address_object
  square_feet: integer
  lease_or_purchase: enum
  lease_terms:
    monthly_rent: decimal
    term_length: string
    security_deposit: decimal
    tenant_improvements: decimal
    
  renovation_costs: decimal
  renovation_timeline: string
  
  certificate_of_occupancy:
    obtained: boolean
    date: date
    number: string
    
  inspections_completed:
    - inspection_type: string
      inspector: string
      date: date
      passed: boolean
      notes: text
```

---

### Step 10: Purchase Equipment & Supplies

**Why This Matters**: You need the tools to deliver your services.

**What You'll Do**:

1. **Create Equipment List**
   Based on your institution type and services:
   
   **All Institutions Need**:
   - Office furniture (desks, chairs)
   - Reception furniture
   - Computers and tablets
   - Printers/copiers
   - Phones/communication system
   - Security system
   - First aid supplies
   - Cleaning supplies
   - Fire extinguishers
   - Signage
   
   **Type-Specific Equipment**:
   - Educational: Desks, chairs, teaching materials, books, technology
   - Healthcare: Medical equipment, exam tables, supplies, PPE
   - Residential: Beds, linens, kitchen equipment, furnishings
   - Child care: Age-appropriate toys, cribs, outdoor equipment

2. **Get Quotes**
   - Office supply vendors
   - Industry-specific suppliers
   - Used/refurbished options to save money
   - Bulk purchasing where possible

3. **Purchase and Deliver**
   - Coordinate delivery with facility readiness
   - Set up and test all equipment
   - Keep all receipts for accounting

4. **Inventory Management**
   - Create inventory list
   - Track all assets over $500 for insurance/depreciation

**Deliverable**: ✅ Facility fully equipped

**Data to Capture**:
```yaml
equipment_inventory:
  - item_category: string
    item_description: string
    quantity: integer
    unit_cost: decimal
    total_cost: decimal
    vendor: string
    purchase_date: date
    warranty_expiration: date
    serial_number: string (if applicable)
    
  total_equipment_investment: decimal
```

---

### Step 11: Implement Technology Systems

**Why This Matters**: Modern institutions require integrated technology to operate efficiently and maintain compliance.

**What You'll Do**:

1. **Core Management System** (Forseti Platform!)
   - Institution management
   - Participant records
   - Staff management
   - Attendance tracking
   - Billing and payments
   - Reporting and compliance
   - Communication tools
   
   **Cost**: $99-999/month depending on size
   **Setup**: 1-2 weeks

2. **Communication Tools**
   - Email (Google Workspace or Microsoft 365): $6-12/user/month
   - Phone system (VoIP like RingCentral): $20-50/user/month
   - Video conferencing (Zoom Pro): $15/host/month

3. **Financial Systems**
   - Accounting software (QuickBooks): $30-200/month
   - Payment processing (integrated with Forseti)
   - Payroll service (Gusto, ADP): $40/month + $6/employee

4. **Security & Backup**
   - Secure internet connection
   - Password manager (LastPass, 1Password): $4/user/month
   - Cloud backup: Included with Forseti

5. **Website**
   - Domain name: $12-20/year
   - Website hosting and design: $500-5000 one-time + $20-100/month hosting
   - Forseti provides basic institutional website

**Total Technology Budget**: $200-500/month + setup costs

**Deliverable**: ✅ All technology systems operational

**Data to Capture**:
```yaml
technology_stack:
  - system_name: string
    purpose: string
    vendor: string
    monthly_cost: decimal
    annual_cost: decimal
    account_username: string
    renewal_date: date
    
  total_monthly_tech_cost: decimal
  
  website:
    domain: string
    hosting_provider: string
    ssl_certificate: boolean
    launch_date: date
```

---

## Phase 4: Staffing (Months 6-9)

### Step 12: Develop Staff Plan

**Why This Matters**: Your staff are your most important asset and largest expense.

**What You'll Do**:

1. **Determine Minimum Staffing Requirements**
   - Review regulatory requirements for ratios
   - Example: 1 staff per 10 children in education
   - Factor in administrative roles

2. **Create Position Descriptions**
   - Administrator (you or hired)
   - Program managers/supervisors
   - Direct service staff
   - Support staff (admin, maintenance)

3. **Determine Compensation**
   - Research market rates in your area
   - Factor in benefits (typically 25-30% of salary)
   - Create compensation ranges

4. **Calculate Total Payroll**
   - Start with minimum staffing for opening
   - Plan for growth (when to add staff)
   - Budget for payroll taxes, workers comp, benefits

**Sample Staffing Plan (Educational Institution, 30 students)**:
```yaml
staffing_plan:
  - position: "Administrator"
    quantity: 1
    salary_range: "50000-65000"
    benefits_cost: 15000
    total_cost: 80000
    required_qualifications:
      - "Bachelor's degree in education or related field"
      - "3+ years experience in educational administration"
      - "State administrator certification"
    
  - position: "Lead Teacher"
    quantity: 2
    salary_range: "40000-50000"
    benefits_cost: 12000
    total_cost: 102000
    required_qualifications:
      - "Bachelor's degree in education"
      - "State teaching certification"
      - "2+ years teaching experience"
    
  - position: "Assistant Teacher"
    quantity: 2
    salary_range: "30000-35000"
    benefits_cost: 9000
    total_cost: 74000
    required_qualifications:
      - "High school diploma or equivalent"
      - "Some early childhood education coursework"
      - "Pass background check"
    
  - position: "Administrative Assistant"
    quantity: 1
    salary_range: "32000-38000"
    benefits_cost: 9000
    total_cost: 44000
    required_qualifications:
      - "High school diploma"
      - "Office administration experience"
      - "Proficient with technology"
    
  total_annual_payroll: 300000
  monthly_payroll: 25000
```

**Deliverable**: ✅ Complete staffing plan with job descriptions

**Data to Capture**:
```yaml
staffing_plan:
  - position_title: string
    reports_to: string
    quantity_needed: integer
    employment_type: enum (full_time, part_time, contractor)
    compensation_range: {min: decimal, max: decimal}
    benefits_cost: decimal
    required_qualifications: array
    preferred_qualifications: array
    responsibilities: array
    
  total_annual_payroll: decimal
  total_annual_benefits: decimal
  total_annual_staffing_cost: decimal
```

---

### Step 13: Recruit and Hire Staff

**Why This Matters**: Finding the right team is critical to your success.

**What You'll Do**:

1. **Post Job Openings**
   - Post on industry-specific job boards
   - Post on general job sites (Indeed, LinkedIn)
   - Leverage your network
   - Timeline: Start 2-3 months before opening

2. **Screen Applications**
   - Review resumes and cover letters
   - Check minimum qualifications
   - Conduct phone screens
   - Select candidates for interviews

3. **Conduct Interviews**
   - Prepare interview questions (behavioral and situational)
   - Include scenario/teaching demo (for education roles)
   - Check references
   - Conduct multiple rounds if needed

4. **Background Checks**
   - Criminal background check (required)
   - Sex offender registry check (required for child-serving)
   - Employment verification
   - Education verification
   - Professional license verification

5. **Make Offers**
   - Extend written offer letters
   - Include compensation, benefits, start date
   - Set clear expectations

6. **Onboarding**
   - Complete I-9 and W-4
   - Set up in payroll
   - Provide employee handbook
   - Complete required training
   - Tour facility
   - Assign equipment (computer, keys, etc.)

**Hiring Timeline**:
- Post jobs: 8-10 weeks before opening
- Screen and interview: 6-8 weeks before
- Background checks: 4-6 weeks before
- Offer and acceptance: 3-4 weeks before
- Onboarding and training: 2-4 weeks before opening

**Deliverable**: ✅ All positions filled and staff ready to start

**Data to Capture**:
```yaml
staff_hiring:
  - position_filled: string
    employee_name: string
    start_date: date
    compensation: decimal
    background_check_date: date
    background_check_passed: boolean
    certifications:
      - cert_type: string
        cert_number: string
        expiration_date: date
    onboarding_completed: boolean
    training_completed: array
```

---

## Phase 5: Licensing & Compliance (Months 7-10)

### Step 14: Apply for Required Licenses

**Why This Matters**: You cannot legally operate without proper licenses.

**What You'll Do**:

1. **Prepare Your Applications**
   - Each license has unique requirements
   - Gather all required documents
   - Complete application forms accurately

2. **Submit Applications**
   - State facility license (most critical)
   - Local business license
   - Health department permit (if food service)
   - Fire safety certificate
   - Others as identified in Step 3

3. **Schedule Inspections**
   - Licensing agency will inspect facility
   - Fire marshal inspection
   - Health department inspection
   - Building code compliance

4. **Address Any Deficiencies**
   - If inspection finds issues, correct immediately
   - Request re-inspection
   - Don't open until all issues resolved

5. **Receive Licenses**
   - Keep original licenses posted in facility
   - Make copies for your files
   - Set calendar reminders for renewals

**Timeline**: 3-6 months from application to approval (varies widely by state and type)

**Deliverable**: ✅ All required licenses obtained

**Data to Capture**:
```yaml
licenses_and_permits:
  - license_name: string
    issuing_agency: string
    application_date: date
    approval_date: date
    license_number: string
    expiration_date: date
    renewal_frequency: string
    renewal_cost: decimal
    
  inspections:
    - inspection_type: string
      inspector_name: string
      inspection_date: date
      passed: boolean
      deficiencies: array
      re_inspection_date: date
      final_approval_date: date
```

---

### Step 15: Develop Policies and Procedures

**Why This Matters**: Clear policies protect you legally and ensure consistency.

**What You'll Do**:

1. **Create Institutional Policies**
   - Admission/enrollment policies
   - Attendance policies
   - Behavior management/discipline
   - Health and safety policies
   - Emergency procedures
   - Confidentiality and privacy
   - Parent/family communication
   - Complaint resolution
   - Termination/withdrawal policies

2. **Create Employee Handbook**
   - Employment policies
   - Code of conduct
   - Attendance and punctuality
   - Dress code
   - Performance expectations
   - Disciplinary procedures
   - Benefits information
   - Safety protocols
   - Mandatory reporting requirements

3. **Create Emergency Plans**
   - Fire evacuation plan
   - Severe weather plan
   - Medical emergency procedures
   - Active threat/lockdown procedures
   - Bomb threat procedures
   - Utility failure procedures

4. **Document Standard Operating Procedures**
   - Daily routines and schedules
   - Check-in/check-out procedures
   - Medication administration (if applicable)
   - Incident reporting
   - Documentation requirements
   - Communication protocols

5. **Review with Attorney**
   - Have attorney review all policies
   - Ensure compliance with regulations
   - Protect against liability

**Deliverable**: ✅ Complete policy manual and employee handbook

**Data to Capture**:
```yaml
policies_procedures:
  policy_manual:
    version: string
    last_updated: date
    sections: array
    total_pages: integer
    
  employee_handbook:
    version: string
    last_updated: date
    acknowledgment_required: boolean
    
  emergency_procedures:
    - procedure_name: string
      last_reviewed: date
      drill_frequency: string
      last_drill_date: date
      
  attorney_review:
    attorney_name: string
    review_date: date
    approved: boolean
```

---

## Phase 6: Marketing & Enrollment (Months 8-12)

### Step 16: Build Your Brand and Marketing

**Why This Matters**: No participants = no revenue. You need to fill your capacity.

**What You'll Do**:

1. **Develop Your Brand Identity**
   - Logo design
   - Color scheme
   - Brand voice and messaging
   - Visual style guidelines

2. **Create Marketing Materials**
   - Brochure/information packet
   - Business cards
   - Flyers
   - Banners/signage
   - Email templates
   - Social media graphics

3. **Build Your Website**
   - Home page with clear value proposition
   - About us (mission, team, facility)
   - Services/programs offered
   - Pricing
   - Enrollment/inquiry form
   - Contact information
   - Blog (for SEO)

4. **Set Up Social Media**
   - Facebook business page
   - Instagram account
   - LinkedIn (if B2B)
   - Post regularly (3-5x/week minimum)

5. **Local Marketing**
   - Google My Business listing
   - Local directory listings
   - Community event participation
   - Partnerships with complementary businesses
   - Chamber of commerce membership
   - School/church bulletin boards
   - Local newspaper ads

6. **Digital Marketing**
   - Google Ads (search)
   - Facebook/Instagram ads
   - Email marketing
   - SEO optimization

**Marketing Budget**: 5-10% of projected first-year revenue

**Deliverable**: ✅ Marketing plan executed and generating leads

**Data to Capture**:
```yaml
marketing:
  brand_assets:
    logo_file: file_reference
    color_primary: "#hexcode"
    color_secondary: "#hexcode"
    font_primary: string
    
  website:
    url: string
    launch_date: date
    monthly_visitors: integer
    conversion_rate: percentage
    
  social_media:
    - platform: string
      handle: string
      followers: integer
      engagement_rate: percentage
      
  marketing_budget:
    monthly: decimal
    channels: array
      - channel: string
        budget: decimal
        roi: decimal
```

---

### Step 17: Enroll Your First Participants

**Why This Matters**: This is the moment you've been working toward!

**What You'll Do**:

1. **Set Enrollment Goals**
   - How many participants for opening day?
   - Recommendation: 50-70% of capacity
   - Allows for growth and adjustments

2. **Create Enrollment Process**
   - Inquiry form (on website, via Forseti)
   - Schedule tours
   - Application/registration form
   - Fee collection
   - Required documents (medical, emergency contacts)
   - Enrollment confirmation

3. **Conduct Tours and Consultations**
   - Show facility
   - Explain programs and approach
   - Answer questions
   - Provide pricing and next steps
   - Follow up within 24 hours

4. **Process Applications**
   - Review applications
   - Accept/waitlist as appropriate
   - Send acceptance letters
   - Collect enrollment fees and deposits
   - Gather required documents

5. **Pre-Opening Communication**
   - Welcome packet
   - Start date and time
   - What to bring
   - Parking and drop-off procedures
   - First day schedule
   - Contact information

**Enrollment Timeline**:
- Start accepting applications: 3-4 months before opening
- Rolling acceptance as applications received
- Aim for 50% enrolled 1 month before opening
- Continue enrolling after opening

**Deliverable**: ✅ Sufficient enrollment to open successfully

**Data to Capture**:
```yaml
enrollment_pipeline:
  inquiries:
    total: integer
    by_source: object
    conversion_rate_to_tour: percentage
    
  tours_scheduled:
    total: integer
    completed: integer
    conversion_rate_to_application: percentage
    
  applications:
    submitted: integer
    accepted: integer
    waitlisted: integer
    declined: integer
    
  enrolled_participants:
    total: integer
    by_program: object
    capacity_percentage: percentage
    projected_opening_day: integer
```

---

## Phase 7: Opening! (Month 12+)

### Step 18: Conduct Staff Training

**Why This Matters**: Your team needs to be prepared before participants arrive.

**What You'll Do**:

1. **Orientation Week** (1-2 weeks before opening)
   - Review mission, values, culture
   - Facility tour and logistics
   - Review all policies and procedures
   - Emergency procedures training and drills
   - Technology training (Forseti platform, etc.)
   - Role-specific training

2. **Practice Days**
   - Run through daily schedules
   - Practice check-in/check-out
   - Simulate incidents and responses
   - Test all systems
   - Make adjustments

3. **Team Building**
   - Build relationships
   - Establish communication norms
   - Create positive culture

**Deliverable**: ✅ Team fully trained and confident

---

### Step 19: Soft Opening (Optional but Recommended)

**Why This Matters**: Test your operations with lower stress before full opening.

**What You'll Do**:

1. **Invite Limited Participants**
   - 25-50% capacity
   - 1-2 weeks
   - Friends, family, early enrollees

2. **Run Full Operations**
   - Everything as if fully open
   - Identify issues and fix them
   - Adjust procedures as needed

3. **Gather Feedback**
   - From staff
   - From participants and families
   - Make final tweaks

**Deliverable**: ✅ Operations tested and refined

---

### Step 20: Grand Opening!

**🎉 Congratulations! You've done it!**

**Opening Day Checklist**:
```
□ Facility cleaned and ready
□ All staff present and prepared
□ Supplies stocked
□ Technology systems operational
□ Safety equipment in place
□ Signage posted
□ Welcome materials ready
□ Emergency contacts accessible
□ First aid kits stocked
□ Music playing (optional but nice!)
□ Smiles on! 😊
```

**First Week Focus**:
- Establish routines
- Build relationships with participants and families
- Address any operational issues quickly
- Document everything
- Celebrate small wins with team
- Send thank you notes to early supporters

**First Month Goals**:
- Smooth daily operations
- All staff comfortable in roles
- Positive feedback from families
- 70-80% capacity
- No major incidents
- Stay within budget

---

## Post-Opening: Continuous Improvement

### Month 2-6: Stabilize

**Focus**: Consistent quality, steady enrollment growth, systems refinement

**Key Activities**:
- Monthly staff meetings and training
- Regular family communication
- Monitor metrics (attendance, incidents, finances)
- Market for additional enrollment
- Address any compliance issues immediately
- Build emergency fund (3-6 months expenses)

---

### Month 7-12: Optimize

**Focus**: Improve efficiency, enhance quality, prepare for Year 2

**Key Activities**:
- Analyze first-year data
- Identify improvement opportunities
- Expand services or adjust as needed
- Review and update policies
- Plan for year 2 (budget, staffing, marketing)
- Consider expansion (additional programs, locations)

---

## Founder's Journey: Summary Timeline

```
Month 1-3:   Foundation
             - Define mission
             - Choose name
             - Research requirements
             - Develop business model
             - Create financial plan
             
Month 3-5:   Legal Formation
             - Choose business structure
             - Form legal entity
             - Obtain insurance
             
Month 4-8:   Facility & Operations
             - Secure facility
             - Purchase equipment
             - Implement technology
             
Month 6-9:   Staffing
             - Develop staff plan
             - Recruit and hire
             
Month 7-10:  Licensing
             - Apply for licenses
             - Develop policies
             
Month 8-12:  Marketing & Enrollment
             - Build brand
             - Enroll participants
             
Month 12+:   Opening!
             - Staff training
             - Soft opening
             - Grand opening
```

---

## Data Model: Founder Journey Tracking

**Purpose**: Forseti platform can track founder progress through this journey

```yaml
founder_profile:
  # Step 1-2
  mission_statement: text
  institution_name: string
  dba_name: string
  tagline: string
  
  # Step 3
  institution_type: enum
  compliance_requirements: array
  
  # Step 4-5
  business_model: object
  financial_plan: object
  funding_sources: array
  
  # Step 6-7
  entity_type: enum
  formation_date: date
  ein: string
  
  # Step 8
  insurance_policies: array
  
  # Step 9
  facility_address: address
  lease_or_own: enum
  certificate_of_occupancy: boolean
  
  # Step 10-11
  equipment_inventory: array
  technology_stack: array
  
  # Step 12-13
  staffing_plan: object
  staff_hired: array
  
  # Step 14-15
  licenses: array
  policies_completed: boolean
  
  # Step 16-17
  marketing_plan: object
  website_url: string
  enrollment_count: integer
  
  # Step 18-20
  staff_training_completed: boolean
  soft_opening_date: date
  grand_opening_date: date
  
  # Journey tracking
  current_phase: enum (1-7)
  current_step: integer (1-20)
  percent_complete: integer
  target_opening_date: date
  actual_opening_date: date
  
  # Support
  assigned_advisor: user_reference
  last_contact_date: date
  next_milestone: string
```

---

## Next Steps

Now that you understand the complete founder journey, you're ready to:

1. **Start Your Journey**: Begin with Step 1 - Define your mission
2. **Get Support**: Sign up for Forseti Founder Support Program
3. **Join Community**: Connect with other founders in similar stages
4. **Access Resources**: Download templates, checklists, and guides

**Ready to start?** → [Begin Your Founder Journey](https://forseti.life/founder/start)

---

**Document Version**: 1.0  
**Created**: January 10, 2026  
**Maintained By**: Forseti Product Team  
**For Questions**: support@forseti.life
