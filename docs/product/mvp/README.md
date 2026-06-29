# MVP (Minimum Viable Product)

**Last Updated**: December 13, 2024  
**Status**: 🟢 Live Beta Testing

---

## Purpose

This directory defines the Minimum Viable Product scope for Forseti/AmISafe - the smallest version of the product that allows maximum validated learning about customers with the least effort.

**Key Principle**: MVP is not about minimal features, it's about maximum learning.

---

## Directory Contents

### 📋 [mvp-definition.md](./mvp-definition.md)
**Purpose**: Complete MVP scope, features, architecture, and success criteria  
**Status**: 🟢 Live Beta Testing (December 2024)  
**Version**: 1.0 Beta  
**Contains**:
- MVP principles and philosophy
- Core value hypothesis
- MVP feature set (must-have vs. nice-to-have vs. post-MVP)
- System architecture overview
- User journey summary (links to detailed journey)
- Success criteria and metrics
- Timeline and phases
- Technical constraints and trade-offs
- Key assumptions being tested
- MVP vs. V2 vs. Long-term vision

**Current MVP Status**:
- ✅ Phase 1: Foundation (Oct 2024) - COMPLETE
- ✅ Phase 2: Web Platform (Nov 2024) - COMPLETE  
- ✅ Phase 3: Mobile App (Nov-Dec 2024) - COMPLETE
- 🔄 Phase 4: Beta Testing (Dec 2024) - IN PROGRESS
- ⏳ Phase 5: Launch & Scale (Q1 2025) - PLANNED

**Use When**:
- Defining product scope
- Making build vs. cut decisions
- Communicating with stakeholders
- Evaluating new feature requests
- Preparing for launch

---

### 🎯 [feature-prioritization.md](./feature-prioritization.md)
**Purpose**: Frameworks and backlog for prioritizing features  
**Status**: 🟡 Template Ready  
**Contains**:
- ICE Score framework (Impact × Confidence × Ease)
- RICE Score framework (Reach × Impact × Confidence / Effort)
- Value vs. Effort matrix (2×2)
- Feature backlog with priorities
- Active features in development
- Candidate features for consideration
- Rejected features with reasoning

**Prioritization Frameworks**:

**ICE Score** = (Impact × Confidence × Ease) / 100
- Quick to calculate
- Good for small teams
- Each factor scored 1-10

**RICE Score** = (Reach × Impact × Confidence) / Effort
- More detailed analysis
- Better for complex features
- Reach = users affected per quarter
- Impact = 0.25 to 3 (minimal to massive)
- Confidence = 50%, 80%, or 100%
- Effort = person-months

**Use When**:
- Deciding what to build next
- Comparing multiple features
- Justifying priorities to stakeholders
- Planning sprints/quarters

---

## What is an MVP?

### Definition

> "The minimum viable product is that version of a new product which allows a team to collect the maximum amount of validated learning about customers with the least effort." - Eric Ries, The Lean Startup

### Common Misconceptions

**❌ NOT**: Minimum features (just ship something small)  
**❌ NOT**: Beta version (we'll fix it later)  
**❌ NOT**: Version 1.0 (first release)  
**❌ NOT**: Low quality (it can be buggy)  

**✅ IS**: Minimum way to test core hypothesis  
**✅ IS**: Version that enables learning loop  
**✅ IS**: Smallest feature set to deliver core value  
**✅ IS**: High quality for what it does (just does less)  

---

## MVP Principles

### 1. Focus on Learning, Not Building
**Goal**: Test core assumptions about customer problems and solutions  
**Method**: Smallest feature set that allows customers to experience core value  
**Outcome**: Validated learning that informs next steps

---

### 2. Deliver Core Value
**Requirement**: Must solve the primary customer problem  
**Test**: Triggers the "aha moment" - customer understands value immediately  
**Validation**: Customer returns and/or pays for solution

---

### 3. Be Opinionated
**Approach**: Make bold bets on what customers need  
**Rationale**: Can't be everything to everyone at MVP stage  
**Result**: Clear positioning and differentiation

---

### 4. Ruthlessly Cut Scope
**Question for every feature**: "Can we learn without this?"  
**If yes**: Cut it  
**If no**: Build it  
**Rule**: When in doubt, cut it out

---

## Forseti MVP Core Hypothesis

**We believe that**:
Urban professionals in St. Louis who walk or use public transit

**Have the problem that**:
They lack hyperlocal, real-time safety information when navigating unfamiliar areas

**Our solution**:
Interactive safety map with H3 hexagon visualization + background location monitoring with proactive alerts

**Will deliver value because**:
- Hyperlocal precision (700m hexagons vs. city-wide statistics)
- Proactive alerts (don't have to remember to check)
- Statistical confidence (z-scores vs. anecdotal data)
- Always-on protection (background monitoring vs. manual checking)

**We'll know we're right when**:
- 40%+ of signups activate (view map and interact)
- 20%+ return after 1 week
- 2%+ convert from free trial to paid subscription
- Users say "must have" vs "nice to have" (40%+ threshold)

---

## MVP Feature Scope

### ✅ Must Have (Core Features)

1. **Interactive Safety Map**
   - H3 hexagon visualization
   - Color-coded risk levels
   - Click for z-score details
   - **Status**: ✅ Implemented

2. **H3 Hexagon Aggregation**
   - Crime data → H3 index
   - Statistical z-score calculation
   - API endpoint for mobile/web
   - **Status**: ✅ Implemented

3. **Z-Score Risk Assessment**
   - Standard deviation from city mean
   - Risk levels: Low, Moderate, Elevated, High
   - **Status**: ✅ Implemented

4. **Background Location Monitoring (Premium)**
   - GPS tracking every 5-15 minutes
   - Automatic z-score checking
   - User-configurable threshold
   - **Status**: ✅ Implemented

5. **User Authentication**
   - Signup/login
   - Premium trial management
   - **Status**: ✅ Implemented

6. **Push Notifications**
   - Alert when entering high-risk area
   - Deep linking to safety map
   - **Status**: ✅ Implemented

---

### 🟡 Nice to Have (Secondary Features)

- User Settings (threshold, cooldown) - ✅ Implemented
- External website links - ✅ Implemented
- Branding integration - ✅ Implemented
- Historical crime trends - ⏳ Planned
- Crime type filtering - ⏳ Planned
- Saved locations - ⏳ Planned

---

### ❌ Not Now (Post-MVP)

- Social features
- Route planning
- Ride-sharing integration
- Smart home integration
- Predictive modeling
- Community reports
- B2B white-label

---

## MVP Success Criteria

### Quantitative Targets (Minimum Thresholds)

| Metric | Minimum | Stretch | Decision |
|--------|---------|---------|----------|
| Activation Rate | 40% | 60% | ≥40% = validate |
| Day 7 Retention | 20% | 35% | ≥20% = validate |
| Day 30 Retention | 10% | 20% | ≥10% = validate |
| Free-to-Paid | 2% | 5% | ≥2% = validate |
| NPS Score | 30 | 50 | ≥30 = validate |

### Qualitative Indicators

**Positive Signals**:
- ✅ "This helped me feel safer"
- ✅ "I changed my route based on the map"
- ✅ "The alerts are timely and accurate"
- ✅ "I'd recommend this to friends"

**Red Flags**:
- ❌ "The data seems inaccurate"
- ❌ "Too many false alerts"
- ❌ "Battery drain is unacceptable"
- ❌ "I don't trust the z-scores"

---

### Decision Point: March 2025

**After 3 months or 500 active users**, review metrics:

- ✅ **Persevere**: Metrics meet/exceed minimum → continue building, scale acquisition
- 🔄 **Iterate**: Close but not quite → optimize features, improve onboarding
- 🔀 **Pivot**: Well below minimum → change strategy (different segment, features, problem)
- ❌ **Stop**: No traction after iterations → explore different problem space

---

## MVP Evolution Roadmap

### MVP (Now - March 2025)
**Focus**: Validate product-market fit with 500 users  
**Geographic**: St. Louis only  
**Pricing**: $4.99/month premium  
**Features**: 6 core features (map, hexagons, z-scores, background monitoring, auth, notifications)

---

### V2 (April - September 2025)
**Focus**: Growth and expansion  
**Geographic**: Add Chicago, expand to 3-5 cities  
**Pricing**: Test $3.99-$5.99 range, add annual plan  
**New Features**:
- Saved locations
- Crime type filtering
- Historical trends
- Multi-city support

**Goals**: 10,000 users, $5,000 MRR

---

### Long-term Vision (2026+)
**Focus**: Scale and diversification  
**Geographic**: 20+ major US cities  
**Pricing**: Tiered plans (personal, family, business)  
**New Features**:
- Route planning integration
- B2B products (real estate, insurance)
- Predictive analytics
- Social features

**Goals**: 100,000+ users, $100,000+ MRR

---

## Build-Measure-Learn Cycles

### Cycle 1: MVP Validation (Now)
**Build**: 6 core features  
**Measure**: Activation, retention, conversion  
**Learn**: Does core value hypothesis hold?  
**Decision**: March 2025

---

### Cycle 2: Optimization (Q2 2025)
**Build**: Improve onboarding, add secondary features  
**Measure**: Improved activation and retention  
**Learn**: What drives engagement?  
**Decision**: June 2025

---

### Cycle 3: Growth (Q3 2025)
**Build**: Multi-city expansion, acquisition channels  
**Measure**: User growth, CAC, LTV  
**Learn**: Can we scale profitably?  
**Decision**: September 2025

---

## Related Documentation

- [User Journey](../user-journey/sarah-urban-commuter.md) - How users experience MVP
- [Process Flow Validation](../process-flow-validation.md) - Technical validation roadmap
- [Experiments](../experiments/) - Tests to optimize MVP
- [Metrics](../metrics/) - How we measure MVP success
- [Customer Development](../customer-development/) - Validation through interviews

---

## Next Steps

### Immediate Actions (This Week)
1. Complete current beta phase with first 50 users
2. Track activation and retention metrics
3. Collect qualitative feedback via surveys
4. Document top bugs and friction points

### Short-term Goals (Next Month)
- Reach 100 beta users
- Achieve 40%+ activation rate
- 20%+ Day 7 retention
- First paid conversions (2%+ target)
- Iterate on onboarding based on feedback

### Decision Point (March 2025)
- 500 active users
- Review all success criteria
- Pivot or persevere decision
- Plan for V2 or strategic change
