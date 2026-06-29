# Institutional Management Documentation

**Product**: Forseti Institutional Management  
**Last Updated**: January 10, 2026  
**Status**: 🟡 Design & Planning Phase

---

## Overview

This directory contains all product and technical documentation for the Forseti Institutional Management platform - a comprehensive system for operating for-profit institutional businesses including educational facilities, healthcare centers, correctional facilities, residential programs, and commercial institutions.

---

## Documentation Structure

```
institutional-management/
├── README.md (this file)
├── mvp.md                          # MVP feature requirements
├── technical-specification.md      # Technical implementation details
├── user-workflows/                 # User journey documentation
├── api-documentation/              # API specs
└── compliance/                     # Regulatory compliance guides
```

---

## Quick Links

| Document | Description | Status |
|----------|-------------|--------|
| [MVP Requirements](./mvp.md) | Core operational features needed to run an institution | ✅ Complete |
| [Technical Specification](./technical-specification.md) | Drupal 11 entity architecture and implementation | ✅ Complete |
| [Founder Journey](./workflows/founder-journey.md) | Complete roadmap from concept to opening day | ✅ Complete |
| [Institution Onboarding](./workflows/institution-onboarding.md) | Platform registration and activation workflow | ✅ Complete |
| [Institutional Roles](./roles-permissions/institutional-roles.md) | Role definitions and recurring activities | ✅ Complete |
| [Institution Types](./institution-types.md) | Detailed taxonomy of supported institution types | 🟡 In Progress |
| User Workflows | Additional user journey documentation | 🔲 Pending |
| API Documentation | REST API endpoints and integration | 🔲 Pending |
| Compliance Guides | Regulatory requirements by institution type | 🔲 Pending |

---

## Product Vision

**Mission**: Enable institutions to focus on their core mission by handling all operational, safety, and compliance management in one integrated platform.

**Target Market**: 
- For-profit educational institutions (K-12 schools, tutoring centers, vocational training)
- Healthcare facilities (clinics, rehabilitation centers, mental health facilities)
- Correctional facilities (private prisons, detention centers)
- Residential programs (group homes, assisted living, halfway houses)
- Commercial institutions (corporate campuses, manufacturing facilities)

---

## Technical Architecture

### Core Modules

**State Machine Module**
- Purpose: Entity lifecycle state management
- Use: Defines institution onboarding states and allowed transitions
- Implementation: YAML configuration + custom transitions
- States:
  - Institution Onboarding: registration → email_verified → documents_submitted → admin_review → payment_received → configuration → active
  - Participant Enrollment: inquiry → application → accepted → enrolled → active → graduated/withdrawn
  - Incident Management: reported → investigating → resolved → closed
  - Staff Lifecycle: application → background_check → hired → active → terminated

**ECA (Event-Condition-Action) Module**
- Purpose: Business logic automation and workflow triggers
- Use: Automates actions when state transitions occur
- Implementation: Visual workflow builder + custom plugins
- Examples:
  - When institution → email_verified: Send welcome email, create admin user, notify support
  - When participant → enrolled: Generate welcome packet, create schedule, notify staff
  - When incident → reported: Alert administrators, create task assignments, log timestamp
  - When payment → received: Update subscription status, send receipt, activate features

**Integration Pattern**
- State Machine defines **what states exist** and **who can transition**
- ECA defines **what happens automatically** when transitions occur
- Result: Clean separation between state logic and business automation

---

## Core Value Propositions

### For Institution Owners
- Launch and operate faster with streamlined onboarding
- Maintain compliance with automated tracking and reporting
- Reduce administrative overhead with integrated systems
- Scale to multiple locations from one dashboard

### For Institution Staff
- Simple, intuitive daily operations
- Quick access to critical participant information
- Easy incident reporting and response
- Mobile-friendly for on-the-go access

### For Families/Guardians
- Real-time updates on participants
- Transparent communication
- Easy payment and billing management
- Peace of mind with safety monitoring

---

## Key Features

### Phase 1: Core Operations (MVP)
1. Institution profile and setup
2. Staff management and scheduling
3. Participant registry and attendance
4. Basic incident reporting
5. Payment processing and billing
6. Emergency contact management

### Phase 2: Enhanced Operations
1. Advanced scheduling (shift management, coverage)
2. Progress tracking and reporting
3. Document management
4. Parent/guardian communication portal
5. Operational dashboards
6. Calendar and event management

### Phase 3: Advanced Features
1. Compliance tracking and reporting
2. Asset and inventory management
3. Marketing and inquiry management
4. Advanced analytics and insights
5. Custom workflows
6. Multi-location management

### Phase 4: Enterprise Features
1. Mobile apps (staff and family)
2. AI-powered insights and predictions
3. Advanced integrations
4. White-label options
5. API marketplace
6. Custom development support

---

## Technical Stack

### Platform
- **CMS**: Drupal 11
- **Database**: MySQL
- **Caching**: Redis
- **Search**: Apache Solr (optional)

### Frontend
- **Web**: Drupal theme (responsive)
- **Mobile**: React Native (Phase 4)

### Integrations
- **Payment**: Stripe
- **Communication**: SendGrid (email), Twilio (SMS)
- **Background Checks**: Checkr or similar
- **Geospatial Safety**: Forseti H3 crime data

---

## Business Model

### Subscription Tiers

**Starter** - $99/month
- 1 location
- Up to 50 participants
- Basic features
- Email support

**Professional** - $299/month
- Up to 5 locations
- Up to 500 participants
- Advanced features
- Priority support

**Enterprise** - $999/month
- Unlimited locations
- Unlimited participants
- Full feature suite
- Dedicated support

**Custom** - Contact Sales
- Custom feature development
- White-label options
- SLA guarantees

---

## Development Roadmap

### Q1 2026: Foundation
- ✅ Product documentation complete
- 🔲 Technical architecture finalized
- 🔲 UI/UX design
- 🔲 Core entity development

### Q2 2026: MVP Development
- 🔲 Phase 1 features development
- 🔲 Payment integration
- 🔲 Basic reporting
- 🔲 Beta testing (5-10 institutions)

### Q3 2026: Beta Launch
- 🔲 Phase 2 features
- 🔲 Marketing website
- 🔲 Public beta launch
- 🔲 First 50 paying customers

### Q4 2026: Scale
- 🔲 Phase 3 features
- 🔲 Mobile app development
- 🔲 Advanced integrations
- 🔲 Target: 200+ institutions

---

## Success Metrics

### Product Adoption
- Time to first institution activated: < 7 days
- Onboarding completion rate: 95%
- Daily active users: 70%
- Feature adoption rate: 60% of core features

### Customer Success
- Customer retention: 85% month-over-month
- Net Promoter Score (NPS): 40+
- Support ticket resolution: < 48 hours
- Customer satisfaction: 4+/5

### Business Performance
- Monthly Recurring Revenue (MRR) growth: 15%
- Customer Acquisition Cost (CAC): < $500
- Lifetime Value (LTV): > $10,000
- LTV:CAC ratio: > 20:1

---

## Team & Ownership

**Product Owner**: TBD  
**Technical Lead**: TBD  
**UX Designer**: TBD  
**Development Team**: TBD

---

## Contributing to Documentation

When adding new documentation:
1. Follow the existing structure and formatting
2. Update this README with links to new documents
3. Keep technical and product documentation separate
4. Include diagrams and examples where helpful
5. Date all updates and track version changes

---

## Questions or Feedback?

Contact: [support@forseti.life](mailto:support@forseti.life)

---

**Last Updated**: January 10, 2026  
**Version**: 1.0  
**Status**: Active Development Planning
