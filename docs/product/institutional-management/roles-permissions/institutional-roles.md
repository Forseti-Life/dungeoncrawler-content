# Institutional Roles & Recurring Activities

**Last Updated**: January 10, 2026  
**Status**: 🟡 Draft  
**Purpose**: Define all operational roles and their recurring responsibilities

---

## Overview

This document defines the core roles required to operate any institution on the Forseti platform and maps out recurring activities each role must complete on daily, monthly, and yearly timeframes.

**Approach**: Role-based activity mapping drives data structure and workflow requirements.

---

## Role Hierarchy

```
Institution Owner (1)
├── Institution Administrator (1+)
│   ├── Department Manager (1+ per department)
│   │   ├── Staff Member (multiple)
│   │   └── Specialist Staff (multiple)
│   └── Support Staff (multiple)
└── Billing/Finance Contact (1)
```

---

## Core Roles

### Role 1: Institution Owner

**Role ID**: `institution_owner`  
**Quantity**: 1 (required)  
**Reports To**: Board of Directors / Shareholders (external)  
**Supervises**: All institutional staff

#### Role Definition
The legal owner or primary operator of the institution. Has ultimate authority over all operations, finances, and strategic decisions. Responsible for institutional viability and compliance.

#### Core Responsibilities
- Strategic planning and business development
- Financial oversight and profitability
- Regulatory compliance and licensing
- Major staffing decisions (hire/terminate administrators)
- Subscription and billing management
- Risk management and insurance
- Board/shareholder reporting (if applicable)

#### Access & Permissions
```yaml
permissions:
  institution_profile:
    - edit_all_settings: yes
    - manage_subscription: yes
    - view_billing: yes
    - manage_billing: yes
    
  staff_management:
    - view_all_staff: yes
    - add_staff: yes
    - edit_staff: yes
    - terminate_staff: yes
    - view_payroll: yes
    
  participant_management:
    - view_all_participants: yes
    - edit_participants: yes
    - export_data: yes
    
  financial:
    - view_all_financials: yes
    - manage_payment_methods: yes
    - view_invoices: yes
    - export_financial_data: yes
    
  reporting:
    - view_all_reports: yes
    - export_all_reports: yes
    - custom_reports: yes
    
  system_admin:
    - manage_integrations: yes
    - api_access: yes
    - audit_logs: yes
```

---

### Recurring Activities: Institution Owner

#### Daily Activities (10-30 minutes)

**Dashboard Review** (5 min)
- Check current occupancy vs capacity
- Review staff on duty
- Check open incidents requiring escalation
- Review urgent notifications

**Financial Monitoring** (5 min)
- Check daily revenue collected
- Review outstanding receivables
- Check payment failures

**Incident Review** (10 min, if applicable)
- Review any major incidents from previous day
- Ensure proper documentation and follow-up
- Escalate to authorities if required

**Quick Communications** (10 min)
- Respond to urgent staff questions
- Reply to parent/guardian escalations
- Handle VIP inquiries

**Data Required**:
```yaml
daily_owner_dashboard:
  real_time_metrics:
    - current_occupancy
    - current_staff_on_duty
    - open_incidents (major/critical only)
    - payment_failures_today
    
  daily_summary:
    - total_revenue_collected_today
    - new_participants_enrolled_today
    - staff_attendance_percentage
    - incident_count_by_severity
    
  alerts:
    - critical_incidents_requiring_attention
    - compliance_deadline_warnings
    - payment_method_expiring_soon
    - license_renewal_upcoming
```

---

#### Weekly Activities (1-2 hours)

**Financial Review** (30 min, Monday morning)
- Review previous week revenue vs projections
- Analyze outstanding receivables
- Review expense reports
- Check budget variance

**Staffing Review** (30 min)
- Review staff attendance and punctuality
- Check shift coverage for coming week
- Review any staff incidents or complaints
- Approve time-off requests

**Operations Meeting** (30 min)
- Meet with administrator(s)
- Review operational metrics
- Discuss any issues or improvements
- Set priorities for coming week

**Data Required**:
```yaml
weekly_owner_report:
  financial:
    - revenue_last_7_days
    - revenue_vs_projected
    - outstanding_receivables_aging
    - expenses_by_category
    - profit_margin
    
  staffing:
    - staff_attendance_percentage
    - overtime_hours
    - open_shifts
    - pending_time_off_requests
    
  operations:
    - average_occupancy_last_7_days
    - participant_attendance_rate
    - incident_summary
    - parent_satisfaction_scores
```

---

#### Monthly Activities (3-5 hours)

**Financial Close** (2 hours, first 5 days of month)
- Review complete P&L for previous month
- Analyze revenue by service/program
- Review all expenses by category
- Compare to budget and projections
- Approve invoices for payment
- Review aging receivables, pursue collections

**Board/Stakeholder Reporting** (1 hour)
- Prepare monthly board report
- Key metrics and trends
- Issues and action plans
- Financial statements

**Compliance Review** (1 hour)
- Verify all licenses current
- Check insurance certificates valid
- Review safety inspection schedules
- Ensure staff certifications current

**Strategic Planning** (1 hour)
- Review growth metrics
- Assess marketing effectiveness
- Plan improvements or expansions
- Budget adjustments if needed

**Staff Development** (1 hour)
- Review staff performance metrics
- Plan training initiatives
- Address performance issues
- Recognition and rewards

**Data Required**:
```yaml
monthly_owner_report:
  financial_summary:
    - revenue_total_last_month
    - revenue_by_service_type
    - revenue_vs_budget
    - expenses_by_category
    - net_profit_margin
    - cash_flow_statement
    - balance_sheet_snapshot
    
  operational_metrics:
    - average_daily_occupancy
    - participant_retention_rate
    - new_enrollments
    - withdrawals_and_reasons
    - average_length_of_stay
    
  staffing_metrics:
    - staff_retention_rate
    - average_staff_tenure
    - training_completion_rates
    - staff_satisfaction_score
    
  safety_compliance:
    - total_incidents_by_type
    - incident_response_times
    - licenses_expiring_soon
    - required_drills_completed
    
  growth_indicators:
    - inquiry_volume
    - conversion_rate
    - waitlist_size
    - capacity_utilization
```

---

#### Quarterly Activities (4-8 hours)

**Strategic Review** (2 hours)
- Review 90-day performance vs goals
- Analyze market trends
- Competitive analysis
- Adjust strategy as needed

**Deep Financial Analysis** (2 hours)
- Trend analysis (revenue, expenses, margins)
- Profitability by service line
- Customer lifetime value analysis
- Break-even analysis
- Cash flow projections

**Compliance Audit** (2 hours)
- Full compliance checklist review
- Prepare for regulatory inspections
- Review and update policies
- Emergency procedure drills

**Staff Reviews** (2 hours)
- Performance reviews for administrators
- Succession planning
- Compensation reviews

**Data Required**:
```yaml
quarterly_owner_report:
  90_day_trends:
    - revenue_trend_chart
    - occupancy_trend_chart
    - expense_trend_chart
    - profit_margin_trend
    
  comparative_analysis:
    - this_quarter_vs_last_quarter
    - this_quarter_vs_year_ago
    - actual_vs_budget
    
  strategic_metrics:
    - customer_acquisition_cost
    - customer_lifetime_value
    - churn_rate
    - net_promoter_score
    - market_share_estimate
```

---

#### Annual Activities (20-40 hours spread across year)

**Annual Planning** (8 hours, Nov-Dec)
- Set goals and objectives for next year
- Create annual budget
- Revenue projections by service
- Capital expenditure planning
- Staffing plan for next year

**License Renewals** (4 hours, varies by state)
- Renew business license
- Renew facility operating license
- Renew professional certifications
- Update insurance policies

**Compliance Reporting** (8 hours, varies by state)
- Annual report to state regulators
- Tax filings (with accountant)
- Annual safety inspections
- Audit preparation (if required)

**Staff Development** (8 hours throughout year)
- Annual all-staff training
- Emergency procedure training and drills
- Professional development planning
- Team building activities

**Facility Maintenance** (4 hours planning)
- Annual facility inspection
- Major maintenance scheduling
- Capital improvements
- Equipment replacement planning

**Marketing & Business Development** (8 hours)
- Annual marketing plan
- Update website and materials
- Community outreach events
- Partnership development

**Data Required**:
```yaml
annual_owner_report:
  full_year_performance:
    - total_revenue
    - total_expenses
    - net_profit
    - profit_margin_percentage
    - total_participants_served
    - average_occupancy_rate
    
  year_over_year_comparison:
    - revenue_growth_percentage
    - expense_growth_percentage
    - participant_growth
    - staff_growth
    
  compliance_summary:
    - licenses_renewed
    - inspections_passed
    - incidents_total
    - compliance_violations (if any)
    - corrective_actions_taken
    
  strategic_outcomes:
    - goals_achieved_vs_set
    - new_programs_launched
    - expansion_completed
    - awards_recognition_received
```

---

## Role 2: Institution Administrator

**Role ID**: `institution_admin`  
**Quantity**: 1+ (required, typically 1 per 100 participants)  
**Reports To**: Institution Owner  
**Supervises**: Department Managers, Staff

#### Role Definition
Day-to-day operational manager of the institution. Responsible for staff supervision, participant services, safety, and operational compliance. Acts as owner's representative in daily operations.

#### Core Responsibilities
- Daily operational management
- Staff scheduling and supervision
- Participant enrollment and services
- Incident response and management
- Parent/guardian communication
- Operational compliance
- Quality assurance

#### Access & Permissions
```yaml
permissions:
  institution_profile:
    - view_all_settings: yes
    - edit_operating_hours: yes
    - edit_programs_services: yes
    - manage_subscription: no
    
  staff_management:
    - view_all_staff: yes
    - add_staff: yes (except other admins)
    - edit_staff: yes (except owner/admins)
    - suspend_staff: yes
    - terminate_staff: no (must request from owner)
    - manage_schedules: yes
    
  participant_management:
    - view_all_participants: yes
    - add_participants: yes
    - edit_participants: yes
    - withdraw_participants: yes
    - manage_attendance: yes
    
  financial:
    - view_revenue_summary: yes
    - manage_participant_billing: yes
    - process_payments: yes
    - refund_payments: no (request from owner)
    
  reporting:
    - view_operational_reports: yes
    - export_operational_data: yes
    - custom_reports: limited
    
  incidents:
    - view_all_incidents: yes
    - create_incidents: yes
    - resolve_incidents: yes
    - delete_incidents: no
```

---

### Recurring Activities: Institution Administrator

#### Daily Activities (2-4 hours)

**Morning Setup** (30 min, 30 min before opening)
- Arrive before first participants
- Check facility walkthrough (safety, cleanliness)
- Review staff schedule for the day
- Check equipment and supplies
- Review expected attendance for day
- Prepare for any special activities

**Attendance Management** (30 min, throughout day)
- Monitor participant check-ins
- Follow up on absences
- Contact parents/guardians for no-shows
- Update attendance records
- Track late arrivals

**Staff Supervision** (1 hour, throughout day)
- Morning staff briefing (10 min)
- Monitor staff-to-participant ratios
- Address staff questions/issues
- Observe service delivery quality
- Provide real-time coaching

**Participant Services** (1 hour, as needed)
- Handle participant issues
- Address behavioral concerns
- Coordinate services delivery
- Communicate with parents/guardians
- Provide support to staff

**Incident Response** (30 min, as needed)
- Respond to incidents immediately
- Ensure proper documentation
- Notify parents/guardians
- Coordinate with emergency services if needed
- Follow up on incident resolution

**End of Day Close** (30 min)
- Ensure all participants checked out
- Review day's incidents and notes
- Prepare for next day
- Complete daily report
- Secure facility

**Data Required**:
```yaml
daily_admin_dashboard:
  morning_checklist:
    - staff_scheduled_today
    - staff_checked_in
    - participants_expected_today
    - special_activities_scheduled
    - supplies_needed_alerts
    
  real_time_monitoring:
    - current_participants_checked_in
    - current_staff_on_duty
    - staff_to_participant_ratios_by_zone
    - capacity_status_by_zone
    - open_incidents
    
  end_of_day_summary:
    - total_participants_today
    - attendance_percentage
    - late_arrivals
    - early_departures
    - incidents_today
    - notes_for_tomorrow
```

---

#### Weekly Activities (4-6 hours)

**Staff Scheduling** (2 hours, Friday for next week)
- Create next week's schedule
- Ensure adequate coverage for all shifts
- Balance staff workload
- Accommodate time-off requests
- Plan for anticipated attendance

**Participant Progress Review** (2 hours)
- Review attendance patterns
- Check progress on goals
- Update participant files
- Schedule parent conferences as needed
- Plan interventions for struggling participants

**Staff Development** (1 hour)
- Weekly staff meeting
- Training on specific topics
- Address performance issues
- Recognition of good work
- Team building

**Facility & Supplies** (1 hour)
- Inventory check
- Order supplies
- Schedule maintenance
- Report facility issues

**Data Required**:
```yaml
weekly_admin_report:
  attendance_summary:
    - daily_attendance_chart
    - absence_reasons
    - chronic_absence_alerts
    - new_enrollments_this_week
    
  staff_performance:
    - staff_attendance_percentage
    - staff_punctuality
    - incidents_by_staff_member
    - schedule_coverage_gaps
    
  operational_metrics:
    - average_occupancy
    - service_delivery_hours
    - parent_communications_count
    - supplies_ordered
```

---

#### Monthly Activities (4-6 hours)

**Performance Reviews** (2 hours)
- Review staff performance
- Conduct one-on-ones with direct reports
- Document performance issues
- Plan performance improvement

**Program Evaluation** (1 hour)
- Review program effectiveness
- Analyze participant outcomes
- Gather feedback from staff and families
- Plan program improvements

**Compliance Checks** (1 hour)
- Review staff certifications
- Check safety equipment
- Review incident patterns
- Ensure policy compliance

**Budget Management** (1 hour)
- Review operational expenses
- Identify cost savings opportunities
- Request budget adjustments
- Track spending against budget

**Parent/Family Engagement** (1 hour)
- Plan family events
- Send monthly newsletter
- Address parent concerns
- Gather feedback

**Data Required**:
```yaml
monthly_admin_report:
  operational_summary:
    - total_participant_days
    - average_daily_attendance
    - new_enrollments
    - withdrawals
    
  staff_summary:
    - total_staff_hours
    - overtime_hours
    - training_completed
    - certifications_expiring
    
  quality_metrics:
    - incident_frequency
    - parent_satisfaction_scores
    - program_completion_rates
    - staff_satisfaction_score
    
  compliance_status:
    - safety_inspections_completed
    - staff_certifications_current
    - policy_violations
    - corrective_actions
```

---

#### Quarterly Activities (6-8 hours)

**Program Planning** (3 hours)
- Evaluate current programs
- Plan new programs or activities
- Schedule special events
- Curriculum updates (if applicable)

**Staff Training** (2 hours)
- Quarterly training session
- Emergency procedure drills
- Policy updates
- Skills development

**Facility Assessment** (2 hours)
- Detailed facility inspection
- Identify maintenance needs
- Plan improvements
- Update emergency plans

**Stakeholder Communication** (1 hour)
- Family survey
- Community outreach
- Partnership meetings
- Advisory board (if applicable)

---

#### Annual Activities (10-20 hours)

**Annual Planning** (4 hours)
- Set operational goals
- Plan program calendar
- Staff development plan
- Operational improvements

**Compliance Preparation** (4 hours)
- Prepare for annual inspections
- Update all policies
- Review emergency procedures
- Staff re-certification

**Performance Reviews** (6 hours)
- Conduct annual reviews for all direct reports
- Goal setting for next year
- Compensation recommendations
- Succession planning

**Program Assessment** (4 hours)
- Annual outcomes evaluation
- Participant satisfaction survey
- Staff satisfaction survey
- Strategic recommendations

**Summer/Holiday Planning** (2 hours, if seasonal)
- Special schedule planning
- Staffing adjustments
- Program modifications

---

## Role 3: Department/Program Manager

**Role ID**: `department_manager`  
**Quantity**: 1+ per department/program (optional but recommended for 50+ participants)  
**Reports To**: Institution Administrator  
**Supervises**: Staff assigned to their department/program

#### Role Definition
Manages a specific department, program, or age group within the institution. Responsible for service delivery quality, staff coordination, and participant outcomes in their area.

#### Core Responsibilities
- Department/program operations
- Staff supervision and support
- Participant progress in their program
- Curriculum/service delivery
- Quality assurance
- Parent communication for their program

#### Access & Permissions
```yaml
permissions:
  institution_profile:
    - view_institution_info: yes
    - edit_settings: no
    
  staff_management:
    - view_department_staff: yes
    - edit_department_schedules: yes
    - request_staff_changes: yes
    - add_staff: no
    
  participant_management:
    - view_department_participants: yes
    - edit_department_participants: yes
    - manage_department_attendance: yes
    - view_other_participants: limited
    
  financial:
    - view_department_revenue: yes
    - process_payments: yes
    
  reporting:
    - view_department_reports: yes
    - export_department_data: yes
    
  incidents:
    - view_department_incidents: yes
    - create_incidents: yes
    - resolve_incidents: yes
```

---

### Recurring Activities: Department/Program Manager

#### Daily Activities (3-5 hours)

**Program Preparation** (30 min)
- Review day's schedule
- Prepare materials and activities
- Check staff assignments
- Review participant notes

**Service Delivery** (3-4 hours)
- Lead or oversee program activities
- Monitor service quality
- Support staff delivery
- Interact with participants
- Document observations

**Documentation** (30 min)
- Update participant progress notes
- Complete attendance
- Document incidents
- Communication with families

**Team Coordination** (30 min)
- Brief staff on schedule
- Address issues
- End-of-day debrief

**Data Required**:
```yaml
daily_manager_view:
  program_schedule:
    - activities_planned_today
    - staff_assigned
    - participants_scheduled
    - materials_needed
    
  real_time_tracking:
    - participants_present
    - activities_completed
    - incidents_in_program
    - staff_notes
    
  daily_documentation:
    - attendance_by_participant
    - progress_notes
    - behavioral_observations
    - parent_communications
```

---

#### Weekly Activities (3-4 hours)

**Program Planning** (1 hour)
- Plan next week's activities
- Prepare materials
- Coordinate with other departments

**Staff Meetings** (1 hour)
- Team coordination
- Training/coaching
- Address concerns

**Progress Review** (1 hour)
- Review participant progress
- Update plans for struggling participants
- Recognize achievements

**Administrative Tasks** (1 hour)
- Complete reports
- Order supplies
- Schedule adjustments

---

#### Monthly Activities (2-3 hours)

**Program Evaluation** (1 hour)
- Review program outcomes
- Analyze participation data
- Plan improvements

**Staff Development** (1 hour)
- Individual coaching sessions
- Skill development

**Family Communication** (1 hour)
- Send program updates
- Address concerns
- Schedule conferences

---

#### Quarterly/Annual Activities (4-8 hours per quarter)

**Curriculum/Program Review**
- Update program content
- Incorporate feedback
- Plan seasonal variations

**Assessment Administration**
- Conduct participant assessments
- Document outcomes
- Report results

---

## Role 4: Staff Member

**Role ID**: `institution_staff`  
**Quantity**: Multiple (ratio-dependent on institution type and regulations)  
**Reports To**: Department Manager or Administrator  
**Supervises**: None (direct participant interaction)

#### Role Definition
Front-line staff who directly deliver services to participants. May be teachers, counselors, aides, care providers, or similar depending on institution type.

#### Core Responsibilities
- Direct service delivery to participants
- Participant supervision and safety
- Activity implementation
- Basic documentation
- Following policies and procedures

#### Access & Permissions
```yaml
permissions:
  institution_profile:
    - view_basic_info: yes
    
  staff_management:
    - view_own_profile: yes
    - view_own_schedule: yes
    - request_time_off: yes
    
  participant_management:
    - view_assigned_participants: yes
    - check_in_check_out: yes
    - view_participant_notes: yes
    - add_simple_notes: yes
    
  financial:
    - no_access: true
    
  reporting:
    - view_own_reports: yes
    
  incidents:
    - report_incidents: yes
    - view_own_incident_reports: yes
```

---

### Recurring Activities: Staff Member

#### Daily Activities (Per Shift)

**Pre-Shift** (15 min)
- Clock in
- Review schedule
- Check assigned participants
- Review any special notes
- Prepare work area

**Participant Interaction** (most of shift)
- Check in participants
- Supervise activities
- Implement program/curriculum
- Ensure safety
- Document observations

**Documentation** (15 min)
- Update attendance
- Write brief notes
- Report incidents
- Check out participants

**End of Shift** (15 min)
- Clean up work area
- Handoff notes to next shift
- Complete daily checklist
- Clock out

**Data Required**:
```yaml
daily_staff_view:
  shift_info:
    - shift_start_end_times
    - assigned_zone_area
    - assigned_participants
    - activities_scheduled
    
  quick_actions:
    - check_in_participant
    - check_out_participant
    - report_incident
    - add_note
    
  participant_list:
    - name
    - photo
    - emergency_info
    - special_needs_alerts
    - pickup_authorization
```

---

#### Weekly Activities (30 min outside shift time)

**Training** (30 min)
- Attend weekly staff meeting
- Complete online training modules
- Review policy updates

---

#### Monthly Activities (1 hour)

**Professional Development** (1 hour)
- Attend training session
- Complete certifications
- One-on-one with supervisor

---

#### Annual Activities (4-8 hours)

**Certification Renewals**
- CPR/First Aid renewal
- Background check update
- Mandatory training completion

**Performance Review**
- Annual evaluation meeting
- Goal setting

---

## Role 5: Billing/Finance Contact

**Role ID**: `billing_contact`  
**Quantity**: 1 (may be owner or dedicated role)  
**Reports To**: Institution Owner  
**Supervises**: None

#### Role Definition
Manages all billing, payments, and financial transactions. May be the owner in small institutions or a dedicated bookkeeper/accountant in larger ones.

#### Core Responsibilities
- Participant billing and invoicing
- Payment processing
- Collections
- Financial recordkeeping
- Reconciliation
- Payroll support (may be external)

#### Access & Permissions
```yaml
permissions:
  financial:
    - view_all_financials: yes
    - create_invoices: yes
    - process_payments: yes
    - issue_refunds: yes
    - manage_payment_plans: yes
    - view_reports: yes
    - export_financial_data: yes
    
  participant_management:
    - view_billing_information: yes
    - view_participant_names: yes (limited)
    
  reporting:
    - view_financial_reports: yes
    - export_financial_reports: yes
```

---

### Recurring Activities: Billing/Finance Contact

#### Daily Activities (1-2 hours)

**Payment Processing** (30 min)
- Process incoming payments
- Post payments to accounts
- Send receipts
- Update balances

**Account Monitoring** (30 min)
- Check failed payments
- Review past due accounts
- Send payment reminders
- Answer billing questions

**Data Required**:
```yaml
daily_billing_dashboard:
  today_activity:
    - payments_received_today
    - total_amount_collected
    - failed_payments
    - new_invoices_sent
    
  accounts_requiring_attention:
    - past_due_over_30_days
    - failed_payment_retries
    - payment_plan_due_today
    - billing_questions_pending
```

---

#### Weekly Activities (2-3 hours)

**Invoicing** (1 hour)
- Generate weekly invoices (if applicable)
- Send invoice reminders
- Process recurring billing

**Collections** (1 hour)
- Follow up on overdue accounts
- Make collection calls
- Set up payment plans
- Escalate to owner if needed

**Reconciliation** (1 hour)
- Reconcile payments with bank deposits
- Match transactions
- Resolve discrepancies

---

#### Monthly Activities (4-6 hours)

**Month-End Close** (3 hours)
- Generate all monthly invoices
- Process all pending payments
- Complete reconciliation
- Prepare financial reports
- Calculate accounts receivable aging

**Financial Reporting** (2 hours)
- Create P&L statement
- Generate revenue reports
- Accounts receivable report
- Payment method analysis
- Provide reports to owner

**Collections Management** (1 hour)
- Review all past due accounts
- Execute collection strategy
- Update payment plans
- Write off bad debts (with approval)

---

#### Quarterly/Annual Activities (8+ hours per quarter)

**Quarterly Close** (4 hours)
- Comprehensive reconciliation
- Tax preparation support
- Audit preparation
- Financial analysis

**Annual Activities** (8 hours)
- Year-end close
- Tax documents (1099s, etc.)
- Annual financial statements
- Audit support (if required)
- Budget preparation for next year

---

## Summary: Activities by Frequency

### Daily Activities Across All Roles
```yaml
institution_owner:
  time: 10-30 minutes
  focus: High-level monitoring, critical decisions
  
institution_admin:
  time: 2-4 hours
  focus: Operations management, staff supervision, incident response
  
department_manager:
  time: 3-5 hours
  focus: Program delivery, staff support, participant progress
  
staff_member:
  time: Full shift
  focus: Direct participant services, documentation
  
billing_contact:
  time: 1-2 hours
  focus: Payment processing, account monitoring
```

### Data Access Requirements by Role
```yaml
owner:
  - Full access to all data
  - Financial: complete
  - Staff: complete
  - Participants: complete
  - System: complete
  
admin:
  - Operational data: complete
  - Financial: summary only
  - Staff: complete (except owner)
  - Participants: complete
  - System: limited
  
manager:
  - Department data: complete
  - Financial: department only
  - Staff: department only
  - Participants: department only
  
staff:
  - Own schedule: complete
  - Assigned participants: complete
  - Financial: none
  - System: none
  
billing:
  - Financial: complete
  - Participant names/billing: yes
  - Other participant data: no
  - Staff: no
```

---

## Derived Requirements

From this role analysis, we need:

### Entity Requirements
1. **Role** entity (or user role configuration)
2. **Activity Log** entity (track who does what when)
3. **Schedule** entity (staff schedules)
4. **Report** entity (recurring reports configuration)
5. **Task** entity (recurring tasks/checklists)

### Workflow Requirements
1. Daily checklist workflows for each role
2. Monthly close workflow (financial)
3. Compliance deadline tracking
4. Recurring task management
5. Report generation and distribution

### Feature Requirements
1. Role-based dashboards
2. Activity reminders and notifications
3. Automated report scheduling
4. Task assignment and tracking
5. Audit logging

---

**Next Steps**:
1. Define Role entity in data dictionary
2. Build Activity/Task Management workflow
3. Design role-based dashboards
4. Create recurring task system

---

**Document Version**: 1.0  
**Status**: Complete - Ready for Entity Mapping
