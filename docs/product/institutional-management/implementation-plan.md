# Institutional Management: Implementation Plan

**Project**: Forseti Institutional Management Platform  
**Last Updated**: January 11, 2026  
**Status**: 🟢 Active Implementation  
**Sprint**: Phase 1 - Foundation

---

## Overview

This document tracks the implementation of the Institutional Management system using Drupal 11's Group module for multi-tenancy, State Machine for workflow management, and ECA for automation.

---

## Implementation Phases

### Phase 1: Foundation & Multi-Tenancy (Weeks 1-4)
**Goal**: Establish core multi-tenancy infrastructure

- [ ] **Week 1: Group Module Setup**
  - [x] Install Group module and dependencies
  - [ ] Configure Group types (38 institution types)
  - [ ] Define Group roles (5 core roles per type)
  - [ ] Set up basic permissions
  - [ ] Test group creation and membership

- [ ] **Week 2: Entity Architecture**
  - [ ] Create Institution entity type
  - [ ] Create 38 institution bundles
  - [ ] Configure institution fields (business info, address, etc.)
  - [ ] Integrate Institution ↔ Group relationship
  - [ ] Create Participant entity type
  - [ ] Create Staff entity type

- [ ] **Week 3: Group Content Integration**
  - [ ] Configure Participant as Group Content
  - [ ] Configure Staff as Group Content
  - [ ] Configure Documents/Media as Group Content
  - [ ] Test content isolation between groups
  - [ ] Create group-aware views

- [ ] **Week 4: Access Control & Testing**
  - [ ] Configure detailed permissions per role
  - [ ] Test multi-institution user scenarios
  - [ ] Test content access restrictions
  - [ ] Performance testing with multiple groups
  - [ ] Documentation

---

### Phase 2: Workflows & Automation (Weeks 5-8)
**Goal**: Implement state machines and automated workflows

- [ ] **Week 5: State Machine Setup**
  - [ ] Install State Machine module
  - [ ] Define Institution Onboarding states (7 phases)
  - [ ] Define Participant Enrollment states
  - [ ] Define Incident Management states
  - [ ] Define Staff Lifecycle states
  - [ ] Configure transition permissions

- [ ] **Week 6: ECA Automation**
  - [ ] Install ECA module
  - [ ] Create institution approval workflow automations
  - [ ] Create participant enrollment automations
  - [ ] Create incident reporting automations
  - [ ] Create notification rules
  - [ ] Test all automations

- [ ] **Week 7: Onboarding Workflow**
  - [ ] Build institution registration form
  - [ ] Implement 7-phase onboarding process
  - [ ] Email verification automation
  - [ ] Document submission system
  - [ ] Admin review interface
  - [ ] Payment integration

- [ ] **Week 8: Testing & Refinement**
  - [ ] End-to-end onboarding tests
  - [ ] State transition validation
  - [ ] Automation reliability tests
  - [ ] Performance optimization
  - [ ] Documentation updates

---

### Phase 3: Core Operations (Weeks 9-12)
**Goal**: Build essential operational features

- [ ] **Week 9: Staff Management**
  - [ ] Staff invitation system
  - [ ] Background check integration
  - [ ] Staff profiles and credentials
  - [ ] Schedule management
  - [ ] Staff-to-participant assignments

- [ ] **Week 10: Participant Management**
  - [ ] Participant enrollment forms
  - [ ] Family/guardian profiles
  - [ ] Check-in/check-out system
  - [ ] Attendance tracking
  - [ ] Participant profiles and records

- [ ] **Week 11: Safety & Incidents**
  - [ ] Incident reporting forms
  - [ ] Incident workflows
  - [ ] Staff-to-participant ratio monitoring
  - [ ] Safety alerts system
  - [ ] Compliance tracking

- [ ] **Week 12: Billing & Subscriptions**
  - [ ] Subscription plans (Starter/Pro/Enterprise)
  - [ ] Stripe payment integration
  - [ ] Billing dashboard
  - [ ] Invoice generation
  - [ ] Payment method management

---

### Phase 4: User Experience (Weeks 13-16)
**Goal**: Create intuitive interfaces and dashboards

- [ ] **Week 13: Institution Dashboard**
  - [ ] Dashboard overview
  - [ ] Quick stats and metrics
  - [ ] Recent activity feed
  - [ ] Alerts and notifications
  - [ ] Institution switcher (multi-institution users)

- [ ] **Week 14: Staff Portal**
  - [ ] Staff dashboard
  - [ ] My assigned participants
  - [ ] Schedule view
  - [ ] Quick incident reporting
  - [ ] Task management

- [ ] **Week 15: Family Portal**
  - [ ] Family login and dashboard
  - [ ] Participant information
  - [ ] Attendance history
  - [ ] Billing and payments
  - [ ] Communication with institution

- [ ] **Week 16: Admin Tools**
  - [ ] System admin dashboard
  - [ ] Institution review/approval interface
  - [ ] Reporting and analytics
  - [ ] Compliance monitoring
  - [ ] Support tools

---

### Phase 5: Advanced Features (Weeks 17-20)
**Goal**: Enhanced functionality and integrations

- [ ] **Week 17: Departments & Hierarchy**
  - [ ] Install Subgroup module
  - [ ] Configure department structure
  - [ ] Department-level permissions
  - [ ] Department manager role
  - [ ] Department reporting

- [ ] **Week 18: Communication**
  - [ ] Messaging system
  - [ ] Email notifications
  - [ ] SMS integration
  - [ ] Parent communication tools
  - [ ] Announcement system

- [ ] **Week 19: Documents & Compliance**
  - [ ] Document management
  - [ ] License tracking
  - [ ] Compliance checklists
  - [ ] Expiration alerts
  - [ ] Audit logs

- [ ] **Week 20: Reporting & Analytics**
  - [ ] Attendance reports
  - [ ] Financial reports
  - [ ] Incident reports
  - [ ] Compliance reports
  - [ ] Custom report builder

---

## Current Sprint: Week 1 - Group Module Setup

### Tasks

#### 1. Install Group Module ✅ COMPLETED
```bash
cd /home/keithaumiller/forseti.life/sites/forseti
composer require drupal/group
drush en group -y
drush cr
```

**Status**: ✅ Complete  
**Completed**: January 11, 2026

---

#### 2. Create Group Types (Institution Types)

**Task**: Create 38 group types matching institution types

**Configuration Needed**:
```yaml
Group Types to Create:
  Educational (6 types):
    - educational_k12: "Private K-12 School"
    - educational_preschool: "Preschool & Pre-K Program"
    - educational_tutoring: "Tutoring & Learning Center"
    - educational_vocational: "Vocational & Trade School"
    - educational_adult: "Adult Education Center"
    - educational_specialty: "Specialty School"
    
  Healthcare (6 types):
    - healthcare_clinic: "Outpatient Clinic"
    - healthcare_physical_therapy: "Physical Therapy Center"
    - healthcare_mental_health: "Mental Health & Counseling Center"
    - healthcare_rehabilitation: "Rehabilitation Center"
    - healthcare_dialysis: "Dialysis Center"
    - healthcare_dental: "Dental & Orthodontic Practice"
    
  Child Care (4 types):
    - childcare_infant_toddler: "Infant & Toddler Center"
    - childcare_full_day: "Full-Day Child Care Center"
    - childcare_afterschool: "After-School Program"
    - childcare_drop_in: "Drop-In Child Care"
    
  Residential (5 types):
    - residential_group_home: "Group Home (Children & Youth)"
    - residential_assisted_living: "Assisted Living Facility"
    - residential_sober_living: "Sober Living Home"
    - residential_transitional: "Transitional Housing"
    - residential_memory_care: "Memory Care Facility"
    
  Correctional (4 types):
    - correctional_juvenile_detention: "Juvenile Detention Center"
    - correctional_halfway_house: "Halfway House"
    - correctional_electronic_monitoring: "Electronic Monitoring Program"
    - correctional_day_reporting: "Day Reporting Center"
    
  Adult Day (4 types):
    - adult_day_senior: "Senior Day Center"
    - adult_day_disability: "Disability Day Services"
    - adult_day_healthcare: "Adult Day Health Care"
    - adult_day_vocational: "Vocational Day Program"
    
  Treatment (5 types):
    - treatment_substance_abuse_residential: "Substance Abuse Treatment (Residential)"
    - treatment_substance_abuse_outpatient: "Substance Abuse Treatment (Outpatient/IOP)"
    - treatment_eating_disorder: "Eating Disorder Treatment Center"
    - treatment_behavioral_health: "Behavioral Health Day Treatment"
    - treatment_autism_aba: "Autism Treatment Center (ABA)"
    
  Recreation (4 types):
    - recreation_youth: "Youth Recreation Center"
    - recreation_senior: "Senior Center"
    - recreation_community: "Community Center"
    - recreation_sports_training: "Sports & Fitness Training Facility"
```

**How to Create** (via Drush/Code):
```bash
# Create via Drush
drush generate group-type

# Or use Configuration Management
# Or create programmatically in custom module
```

**Status**: 🔲 Not Started  
**Assigned**: Next task  
**Estimated Time**: 4 hours

---

#### 3. Define Group Roles

**Task**: Create 5 standard roles per group type

**Roles Configuration**:
```yaml
Standard Group Roles (apply to all group types):
  
  1. Institution Owner:
     label: "Institution Owner"
     weight: -10
     admin: true
     permissions:
       - view group
       - update group
       - delete group
       - administer members
       - administer content
       - manage settings
       - manage billing
       - ALL group permissions
  
  2. Institution Administrator:
     label: "Institution Administrator"
     weight: -8
     admin: true
     permissions:
       - view group
       - update group
       - administer members (invite, remove, change roles)
       - create/edit/delete all group content
       - view reports
       - manage schedules
       - manage settings (limited)
  
  3. Department Manager:
     label: "Department Manager"
     weight: -5
     permissions:
       - view group
       - create/edit/delete participants in department
       - create/edit/delete staff in department
       - create incident reports
       - view department reports
       - manage department schedules
  
  4. Staff Member:
     label: "Staff Member"  
     weight: 0
     permissions:
       - view group
       - view assigned participants
       - create incident reports
       - update attendance
       - view schedules
       - create notes
  
  5. Billing Contact:
     label: "Billing Contact"
     weight: 5
     permissions:
       - view group (limited)
       - view billing information
       - update payment methods
       - view invoices
       - download receipts
```

**Implementation Method**:
```php
// In custom module: forseti_institutional.install

function forseti_institutional_install() {
  $group_types = \Drupal::entityTypeManager()
    ->getStorage('group_type')
    ->loadMultiple();
    
  foreach ($group_types as $group_type) {
    _forseti_create_group_roles($group_type);
  }
}

function _forseti_create_group_roles($group_type) {
  // Create 5 standard roles
  $roles = [
    'owner' => ['label' => 'Institution Owner', 'weight' => -10],
    'administrator' => ['label' => 'Institution Administrator', 'weight' => -8],
    'department_manager' => ['label' => 'Department Manager', 'weight' => -5],
    'staff_member' => ['label' => 'Staff Member', 'weight' => 0],
    'billing_contact' => ['label' => 'Billing Contact', 'weight' => 5],
  ];
  
  foreach ($roles as $id => $role) {
    GroupRole::create([
      'id' => $group_type->id() . '-' . $id,
      'label' => $role['label'],
      'weight' => $role['weight'],
      'group_type' => $group_type->id(),
    ])->save();
  }
}
```

**Status**: 🔲 Not Started  
**Estimated Time**: 2 hours

---

#### 4. Set Up Basic Permissions

**Task**: Configure permissions for each role

**Permission Categories**:
```yaml
Group Permissions:
  - View group
  - Update group
  - Delete group
  - Join group
  - Leave group
  
Member Permissions:
  - Administer members
  - View members
  - Add members
  - Remove members
  - Update member roles
  
Content Permissions (per content type):
  - Create [content_type]
  - View any [content_type]
  - View own [content_type]
  - Update any [content_type]
  - Update own [content_type]
  - Delete any [content_type]
  - Delete own [content_type]
```

**Status**: 🔲 Not Started  
**Estimated Time**: 3 hours

---

#### 5. Test Group Creation and Membership

**Test Scenarios**:
```yaml
Test 1: Create Group
  - Create a group type "Child Care Center"
  - Create a group instance "Sunshine Preschool"
  - Verify group exists
  - Verify owner assigned

Test 2: Add Members
  - Create test users
  - Add user as "Institution Administrator"
  - Add user as "Staff Member"
  - Verify roles assigned correctly

Test 3: Multi-Group Membership
  - Create second group "Happy Days Daycare"
  - Add same user to both groups with different roles
  - Verify user can switch between groups
  - Verify correct permissions in each group

Test 4: Permission Enforcement
  - Test what staff can/cannot see
  - Test cross-group access (should be blocked)
  - Test role-based actions

Test 5: Performance
  - Create 10 groups
  - Add 50 members total
  - Query performance tests
  - View rendering tests
```

**Status**: 🔲 Not Started  
**Estimated Time**: 4 hours

---

## Technical Architecture Decisions

### Decision Log

**1. Multi-Tenancy Approach**
- **Decision**: Use Group module
- **Alternatives Considered**: Organic Groups (outdated), Domain Access (overkill), Custom solution
- **Rationale**: Group module is actively maintained for D11, flexible, proven at scale
- **Date**: January 11, 2026

**2. Institution as Group vs Institution Entity with Group**
- **Decision**: Institution entity + Group (one-to-one)
- **Rationale**: Institution needs custom fields beyond Group capabilities, but Group handles access control
- **Implementation**: Institution entity stores business data, Group handles membership/access
- **Date**: January 11, 2026

**3. Workflow Management**
- **Decision**: State Machine + ECA
- **Alternatives**: Workflows module, custom
- **Rationale**: Clean separation between state logic and automation
- **Date**: January 11, 2026

**4. Role Structure**
- **Decision**: 5 standard roles per institution type
- **Alternatives**: Fewer roles (too restrictive), site-wide roles only (not group-aware)
- **Rationale**: Balance between flexibility and simplicity
- **Date**: January 11, 2026

---

## Development Environment

### Setup Commands

```bash
# Navigate to Drupal site
cd /home/keithaumiller/forseti.life/sites/forseti

# Install Group module
composer require drupal/group
composer require drupal/group_permissions

# Install State Machine and ECA (for Phase 2)
composer require drupal/state_machine
composer require drupal/eca

# Enable modules
drush en group group_permissions -y

# Clear cache
drush cr

# Check module status
drush pm:list --status=enabled | grep group
```

---

## Performance Targets

**Response Times**:
- Institution dashboard load: < 500ms
- Group member list: < 300ms
- Content queries: < 200ms
- Group switching: < 100ms

**Scalability**:
- Support 10,000+ institutions
- 100,000+ total users
- 50+ members per institution average
- 1,000,000+ pieces of group content

**Caching Strategy**:
- Redis for group membership cache
- Varnish for authenticated users
- BigPipe for progressive loading
- Views caching with group context

---

## Testing Strategy

### Unit Tests
- Group creation
- Member addition/removal
- Permission checks
- State transitions

### Integration Tests
- End-to-end onboarding
- Multi-group user workflows
- Content isolation
- Automation triggers

### Performance Tests
- Load testing (100+ concurrent users)
- Large group stress tests (500+ members)
- Query optimization validation
- Cache effectiveness

### User Acceptance Testing
- Founder onboarding flow
- Staff daily operations
- Multi-institution scenarios
- Mobile responsiveness

---

## Success Metrics

**Phase 1 Complete When**:
- ✅ All 38 group types created
- ✅ Roles and permissions configured
- ✅ Test institution created successfully
- ✅ Multi-group membership working
- ✅ Content isolation verified
- ✅ Performance targets met
- ✅ Documentation complete

---

## Resources

**Documentation**:
- [Group Module Docs](https://www.drupal.org/docs/contributed-modules/group)
- [State Machine](https://www.drupal.org/project/state_machine)
- [ECA Module](https://www.drupal.org/project/eca)

**Team**:
- Lead Developer: [Name]
- DevOps: [Name]
- QA: [Name]
- Product Owner: Keith Aumiller

**Communication**:
- Daily standups: 9:00 AM
- Sprint planning: Mondays
- Demo: Fridays
- Retrospective: End of each phase

---

**Document Version**: 1.0  
**Last Updated**: January 11, 2026  
**Next Review**: End of Week 1
