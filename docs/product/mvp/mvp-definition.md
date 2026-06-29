# MVP Definition

**Last Updated**: 2024-12-13  
**Version**: 1.0 Beta  
**Status**: 🟢 Live (Beta Testing)

---

## What is an MVP?

A **Minimum Viable Product** is the smallest version of your product that allows you to start the Build-Measure-Learn loop with the least amount of effort.

**Common Misconception**: MVP = "Minimal Features"  
**Reality**: MVP = "Maximum Learning with Minimum Effort"

> "The minimum viable product is that version of a new product which allows a team to collect the maximum amount of validated learning about customers with the least effort." - Eric Ries

---

## MVP Principles

### 1. Focus on Learning, Not Building
- Goal: Test core assumptions
- Method: Smallest feature set to test value hypothesis
- Outcome: Validated learning about customers

### 2. Deliver Core Value
- Must solve the primary customer problem
- Must demonstrate unique value proposition
- Should trigger the "aha moment"

### 3. Target Early Adopters
- Build for people who have the problem most acutely
- Not for mainstream market (yet)
- Forgiving of rough edges, want to be involved

### 4. Be Willing to Throw It Away
- MVP is an experiment, not a product
- Pivot frequently based on learning
- Don't over-engineer

---

## Forseti/AmISafe MVP Status

### Current Version: 1.0 Beta

**Release Date**: December 2024  
**Status**: 🟢 Live Beta Testing

### Core Value Hypothesis

**We believe that**:
> "Providing hyperlocal, real-time crime risk visualization combined with proactive background monitoring will help urban residents make safer route decisions and feel more confident in their daily activities."

**We will know we're right when**:
> "60% of users check the map before walking in unfamiliar areas AND 40% enable background monitoring AND report feeling significantly safer (NPS > 50)"

---

## MVP Feature Set

### ✅ Must Have (Core Features)
_Features absolutely required to deliver core value._

1. **Interactive Safety Map (Web & Mobile)**
   - **Why**: Core value prop - visualize hyperlocal crime risk with color-coded hexagons
   - **Status**: ✅ Implemented
   - **Implementation**: 
     - Website: Drupal page at forseti.life/safety-map with embedded Leaflet map
     - Mobile: External link opens browser to website map
     - H3 Resolution 11 hexagons (~700m edge length)
     - Color gradient: Green (safe) → Yellow → Orange → Red (high risk)

2. **H3 Hexagon Crime Aggregation**
   - **Why**: Unique approach providing ~700m precision vs. broad neighborhood stats
   - **Status**: ✅ Implemented
   - **Implementation**: 
     - Python scripts aggregate crime incidents into H3 hexagons
     - Daily ETL pipeline updates hexagon statistics
     - MySQL database stores aggregated counts per hexagon
     - API endpoint: `/api/amisafe/aggregated?lat=X&lng=Y`

3. **Z-Score Risk Assessment**
   - **Why**: Statistical confidence in safety scores - objective, data-driven
   - **Status**: ✅ Implemented
   - **Implementation**: 
     - Calculate standard deviations from city mean crime rate
     - Z-score formula: (hexagon_count - mean) / std_dev
     - Risk levels: z < 1.0 (low), 1.0-2.0 (moderate), 2.0-3.0 (elevated), >3.0 (high)
     - Color mapping based on z-score thresholds

4. **Background Location Monitoring (Premium)**
   - **Why**: Proactive safety alerts - users don't have to remember to check
   - **Status**: ✅ Implemented
   - **Implementation**: 
     - BackgroundLocationService.ts for iOS/Android
     - Monitors GPS location every 5-15 minutes
     - Fetches z-score for current hexagon via API
     - Triggers notification if z-score exceeds user threshold
     - User-configurable threshold (1.0-3.0) and cooldown (1-30 min)
     - Deep linking: tapping notification opens safety map at location

5. **User Authentication**
   - **Why**: Required for premium features, personalization, saved preferences
   - **Status**: ✅ Implemented
   - **Implementation**: 
     - Drupal user system for web
     - JWT/session tokens for API authentication
     - Login/Register screens in mobile app
     - AsyncStorage for persistent login state

6. **Push Notifications**
   - **Why**: Alert users to danger without requiring app to be open
   - **Status**: ✅ Implemented
   - **Implementation**:
     - react-native-push-notification library
     - Local notifications (no server-side push yet)
     - NotificationService.ts handles scheduling and delivery
     - Includes URL for deep linking to safety map

### 🟡 Nice to Have (Secondary Features)
_Features that enhance experience but aren't core to MVP._

- **User Settings/Preferences**: ✅ Implemented
  - Z-score threshold customization (1.0-3.0)
  - Notification cooldown period (1-30 minutes)
  - Enable/disable background monitoring

- **External Website Links**: ✅ Implemented
  - "Learn More" section in Settings with links to About, How It Works, Privacy, Contact
  - Quick Actions on Home screen
  - "About Forseti" educational content

- **Branding Integration**: ✅ Implemented
  - "AmISafe by Forseti" branding throughout app
  - Consistent links back to forseti.life website
  - Professional presentation for credibility

- **Historical Crime Trends**: ⏳ Planned
  - Time-series data showing crime trends over months/years
  - Hexagon history visualization

- **Crime Type Filtering**: ⏳ Planned
  - Filter by violent crime, property crime, drug offenses, etc.
  - Different risk scores for different crime categories

- **Saved Locations**: ⏳ Planned
  - Save home, work, frequent destinations
  - Quick-check safety for saved locations
  - Custom alerts for saved locations

### ❌ Not Now (Post-MVP)
_Features to consider after MVP validation._

- **Social Features**: Share locations with friends, family tracking
- **Route Planning**: Optimize walking/driving routes for safety
- **Ride-Sharing Integration**: Show safety info in Uber/Lyft
- **Smart Home Integration**: Alerts to smart speakers, watches
- **Predictive Modeling**: ML-based crime prediction (time-of-day patterns)
- **Community Reports**: User-submitted safety reports
- **Insurance Partnerships**: Discounts for users in safe areas
- **B2B White-Label**: Custom versions for real estate, property management

---

## MVP Architecture

### High-Level Components

```
┌─────────────────────────────────────────────────────┐
│                   Frontend Layer                     │
├─────────────────────────────────────────────────────┤
│  • Web App (React/Drupal)                           │
│  • Mobile App (React Native)                        │
│  • Interactive Map (Leaflet/H3 visualization)       │
└─────────────────────────────────────────────────────┘
                         ↕
┌─────────────────────────────────────────────────────┐
│                    API Layer                         │
├─────────────────────────────────────────────────────┤
│  • RESTful API (Drupal custom modules)              │
│  • Authentication (Drupal user system)              │
│  • Rate limiting & caching                          │
└─────────────────────────────────────────────────────┘
                         ↕
┌─────────────────────────────────────────────────────┐
│                Business Logic Layer                  │
├─────────────────────────────────────────────────────┤
│  • H3 Geospatial Processing (Python)                │
│  • Z-Score Risk Calculation                         │
│  • Crime Data Aggregation                           │
│  • Background Service (Mobile)                      │
└─────────────────────────────────────────────────────┘
                         ↕
┌─────────────────────────────────────────────────────┐
│                   Data Layer                         │
├─────────────────────────────────────────────────────┤
│  • MySQL Database (Crime incidents, user data)      │
│  • H3 Hexagon Index                                 │
│  • Crime Data ETL Pipeline                          │
└─────────────────────────────────────────────────────┘
```

**Detailed Architecture**: See `/docs/ARCHITECTURE.md`

---

## User Journey (MVP)

_How does a user discover, activate, and retain with the MVP?_

For detailed user journey documentation, see:

### 📖 [Sarah - Urban Commuter Journey Map](../user-journey/sarah-urban-commuter.md)

**Quick Summary**:
- **Persona**: Sarah, 28, Urban Professional in St. Louis
- **Discovery**: Feels unsafe walking at night → Googles "St. Louis crime map" → finds Forseti
- **Aha Moment**: Compares route z-scores (0.8 vs 2.3) → realizes she can make informed decisions
- **Activation**: Signs up, downloads mobile app, enables background alerts
- **Retention**: Receives proactive alerts, adjusts routes, builds trust over weeks
- **Conversion**: Converts to $4.99/month premium after 7-day trial
- **Advocacy**: Refers friends, leaves 5-star review, becomes power user

**Key Journey Stages**:
1. Problem Trigger → Discovery (Google search)
2. Landing Page → Aha Moment (route comparison)
3. Signup → Onboarding (location permissions, alert setup)
4. Week 1 → First Alert (validates value)
5. Week 2-4 → Habit Formation (background monitoring trusted)
6. Month 2+ → Paid Conversion & Advocacy

See [full journey documentation](../user-journey/sarah-urban-commuter.md) for:
- Detailed week-by-week usage patterns
- Emotional journey mapping
- Failure modes and risk mitigation
- Success metrics at each stage
- Iteration opportunities based on user behavior

---

## Success Criteria (MVP)

### Minimum Success Threshold
_What results would validate that we should persevere with this MVP?_

| Metric | Minimum Threshold | Stretch Goal | Current | Status |
|--------|-------------------|--------------|---------|--------|
| **Activation Rate** | 40% of signups view map | 60% | TBD | 🟡 Measuring |
| **Day 7 Retention** | 20% return after 1 week | 35% | TBD | 🟡 Measuring |
| **Day 30 Retention** | 10% return after 30 days | 20% | TBD | 🟡 Measuring |
| **NPS Score** | 30 (more promoters than detractors) | 50 | TBD | 🟡 Measuring |
| **Core Action Usage** | 50% use map at least once/week | 70% | TBD | 🟡 Measuring |
| **Premium Trial Start** | 25% enable background monitoring | 40% | TBD | 🟡 Measuring |
| **Free to Paid** | 2% convert to premium | 5% | TBD | 🟡 Measuring |
| **Alert Usefulness** | 60% say alerts are helpful | 80% | TBD | 🟡 Measuring |

### Qualitative Success Indicators

**User Feedback We're Looking For**:
- ✅ "This helped me feel safer"
- ✅ "I changed my route based on the map"
- ✅ "The alerts are timely and accurate"
- ✅ "I'd recommend this to friends"

**Red Flags**:
- ❌ "The data seems inaccurate"
- ❌ "Too many false alerts"
- ❌ "Battery drain is unacceptable"
- ❌ "I don't trust the z-scores"

### Decision Point: March 2025
After 3 months or 500 active users, review metrics and decide:

- ✅ **Persevere**: Metrics meet/exceed minimum thresholds → continue building, scale user acquisition
- 🔄 **Iterate**: Metrics close but not quite → optimize features, improve onboarding, fix friction points
- 🔀 **Pivot**: Metrics well below minimum → change strategy (different customer segment, different features, different problem)
- ❌ **Stop**: No signs of traction after iterations → explore different problem space

| Metric | Minimum Threshold | Stretch Goal | Status |
|--------|-------------------|--------------|--------|
| **Activation Rate** | 40% of signups view map | 60% | 🔴 |
| **Day 7 Retention** | 20% return after 1 week | 30% | 🔴 |
| **NPS Score** | 30 (more promoters than detractors) | 50 | 🔴 |
| **Core Action Usage** | 50% use map at least once/week | 70% | 🔴 |
| **Free to Paid** | 2% convert to premium | 5% | 🔴 |

**Decision Point**: After 3 months or 500 users, review metrics and decide:
- ✅ **Persevere**: Metrics meet/exceed minimum → continue building
- 🔄 **Iterate**: Metrics close but not quite → optimize features
- 🔀 **Pivot**: Metrics well below minimum → change strategy
- ❌ **Stop**: No signs of traction → explore different problem

---

## MVP Timeline

### Phase 1: Foundation ✅ COMPLETE (Oct-Nov 2024)
**Goal**: Core infrastructure and data pipeline

- [x] Set up St. Louis crime data ETL pipeline
- [x] Implement H3 hexagon indexing (Resolution 11)
- [x] Build z-score calculation logic
- [x] Create API endpoints (/api/amisafe/aggregated)
- [x] Deploy to production (forseti.life)

**Outcome**: Data pipeline operational, API serving hexagon crime data

---

### Phase 2: Web Interface ✅ COMPLETE (Nov 2024)
**Goal**: Interactive safety map on website

- [x] Design and build map interface (Leaflet.js)
- [x] Integrate H3 hexagon visualization
- [x] Add color-coded risk display
- [x] Create informational pages (About, How It Works, Privacy, Safety Factors)
- [x] Add user authentication (Drupal user system)
- [x] Launch public website (forseti.life)

**Outcome**: Public-facing safety map accessible to anyone

---

### Phase 3: Mobile App ✅ COMPLETE (Nov-Dec 2024)
**Goal**: Mobile app with background monitoring

- [x] Build React Native app (iOS/Android)
- [x] Implement background location service
- [x] Add push notification system
- [x] Create settings for customization (threshold, cooldown)
- [x] Integrate authentication with website
- [x] Add "AmISafe by Forseti" branding
- [x] Replace embedded map with external link to website
- [x] Add deep linking from notifications to safety map
- [x] Document background service completely
- [x] Internal testing with beta users

**Outcome**: Functional mobile app with background monitoring

---

### Phase 4: Launch & Iterate 🔄 IN PROGRESS (Dec 2024 - Feb 2025)
**Goal**: Get first users and start learning

- [x] Soft launch to beta testers (friends, family, local community)
- [ ] Submit app to App Store (iOS) - **IN REVIEW**
- [ ] Submit app to Google Play (Android) - **IN REVIEW**
- [ ] Collect feedback and usage data
- [ ] Run problem validation interviews (30 users)
- [ ] Iterate based on learning
- [ ] Public launch announcement

**Target**: 100 users by end of January 2025

---

### Phase 5: Validate & Scale ⏳ PLANNED (Feb-Apr 2025)
**Goal**: Validate product-market fit and begin scaling

- [ ] Reach 500 active users
- [ ] Analyze retention cohorts
- [ ] Conduct NPS surveys
- [ ] Run pricing experiments (A/B test $4.99 vs $9.99)
- [ ] Implement analytics dashboards
- [ ] Optimize onboarding flow based on data
- [ ] Launch referral program
- [ ] Begin paid acquisition experiments ($500 budget)

**Decision Point**: March 2025 - Pivot or Persevere?

---

## Technical Debt & Constraints

### Known Limitations (MVP)

**Data Quality**:
- Crime data sourced from St. Louis Metro Police Department public records
- Reporting delays: 24-72 hours typical (not truly real-time)
- Not all crime types included (focus on violent and property crimes)
- Data accuracy depends on police reporting practices
- No verification of data completeness

**Scalability**:
- H3 calculation currently not optimized for real-time at scale
- Database queries may slow with >10k concurrent users
- Background service drains battery (3-5% per hour on Android, less on iOS)
- No CDN for map tiles (all served from single server)
- Single database instance (no replication/sharding)

**Feature Gaps**:
- No route planning - only point location risk assessment
- No historical trends - only current snapshot (last 30-90 days)
- Limited crime type filtering (all crimes aggregated equally)
- No time-of-day analysis (crime patterns vary by hour)
- English only (no multi-language support)

**Platform Limitations**:
- iOS background monitoring limited by Apple policies:
  - Significant location changes only (not continuous)
  - Can be suspended during low battery mode
  - Background execution time limits
- Android battery optimization may kill background service:
  - Manufacturer-specific restrictions (Samsung, Xiaomi aggressive)
  - Users must whitelist app in battery settings
  - Doze mode pauses location updates
- No offline mode - requires internet connection
- No Apple Watch or Android Wear support

**Geographic Coverage**:
- Currently St. Louis metro area only
- No data for other cities (expansion planned)
- Hexagons near city boundaries may have incomplete data

### Acceptable Trade-offs for MVP

✅ **Okay for now** (acceptable limitations):
- Manual data updates daily (not streaming real-time)
- Simple z-score calculation (not ML/predictive)
- Basic map UI without polish (functional > beautiful)
- Web-only analytics (no mobile dashboard yet)
- Single city coverage (validate before expanding)
- Local notifications only (no push notification server infrastructure)
- Battery usage 3-5% per hour (acceptable for safety)

❌ **Not okay** (must fix before full launch):
- ✅ FIXED: Inaccurate z-scores (breaks trust) - validated against known high/low crime areas
- ✅ FIXED: Background service crashing - stable in testing
- ✅ FIXED: Data privacy issues - HTTPS, no selling/sharing of location data
- ✅ FIXED: Broken authentication - tested with 20+ users
- 🔄 NEEDS ATTENTION: App store approval (awaiting review)
- 🔄 NEEDS ATTENTION: Battery optimization education (in-app guide needed)

### Technical Debt to Address Post-MVP

**Priority 1 (Q1 2025)**:
- [ ] Implement server-side push notifications (Firebase Cloud Messaging)
- [ ] Add database read replicas for scaling
- [ ] Optimize H3 queries with spatial indexes
- [ ] Add CDN for static assets and map tiles
- [ ] Improve battery efficiency (reduce location check frequency in safe areas)

**Priority 2 (Q2 2025)**:
- [ ] Historical crime trend analysis
- [ ] Time-of-day risk patterns
- [ ] Crime type filtering and weighting
- [ ] Offline map caching
- [ ] Multi-city expansion infrastructure

**Priority 3 (Q3 2025)**:
- [ ] ML-based predictive modeling
- [ ] Real-time crime event streaming
- [ ] Route optimization algorithm
- [ ] Wearable device support

---

## Key Assumptions to Test

### Problem Assumptions

- [x] **VALIDATED** (via personal experience + community feedback): Users struggle to assess neighborhood safety when walking/traveling
- [x] **VALIDATED** (via Reddit discussions, Nextdoor posts): Current solutions (city crime maps, word of mouth) are insufficient
- [ ] **TESTING**: Users will change behavior based on better data (need to measure via surveys)
- [ ] **TESTING**: Safety concern is acute enough to drive daily/weekly usage

**Validation Method**: Customer interviews (target 30 users by Feb 2025)

---

### Solution Assumptions

- [x] **VALIDATED** (technical testing): H3 hexagon precision (~700m) is granular enough to be useful
  - Evidence: Users can distinguish between blocks, not just neighborhoods
- [ ] **TESTING**: Z-score statistical approach is understandable to average user
  - Risk: May be too technical, need simpler "low/med/high" labels
  - Test: A/B test z-score display vs. risk level labels
- [x] **VALIDATED** (battery testing): Background monitoring provides enough value to justify battery drain
  - Evidence: 3-5% per hour acceptable for safety-conscious users
  - Caveat: Need better user education about battery optimization
- [ ] **TESTING**: Alerts are timely and actionable (not annoying)
  - Risk: Too many alerts = users disable, too few = no value
  - Test: Monitor disable rates and user feedback

**Validation Method**: Beta testing + usage analytics

---

### Market Assumptions

- [x] **VALIDATED** (St. Louis data): Urban areas have sufficient crime data to aggregate
  - Evidence: St. Louis PD publishes comprehensive incident reports
- [ ] **TESTING**: Users care enough about safety to use dedicated app
  - Alternative: Might prefer integrated into Google Maps
  - Test: Retention metrics, NPS survey
- [ ] **TESTING**: Market size is large enough to build sustainable business
  - Need: TAM/SAM/SOM analysis (see docs/market/market-sizing.md)
  - Validate: Can we acquire users affordably?

**Validation Method**: Market research + user acquisition experiments

---

### Financial Assumptions

- [ ] **TESTING**: Users will pay $4.99/month for premium features
  - Baseline: Industry standard for safety/security apps
  - Risk: May need to prove value with longer free trial
  - Test: Conversion rate from trial to paid
- [x] **VALIDATED** (cost analysis): Cost per user is < $1/month (infrastructure)
  - Current: ~$0.15/user/month (server, API calls, storage)
  - Scales well to 10k users with current architecture
- [ ] **TESTING**: Can acquire users for < $10 CPA
  - Unknown: Haven't run paid acquisition yet
  - Test: Small budget experiments ($500) across channels
  - Hypothesis: Organic (SEO, Reddit, word-of-mouth) will be primary channel

**Validation Method**: Financial tracking + acquisition experiments

---

### Riskiest Assumptions (Priority Order)

1. 🔴 **Users will pay for premium** (existential risk)
   - If false: Pivot to B2B, advertising, or free with limited features
   - Test: Trial-to-paid conversion rate
   - Timeline: March 2025 (after 3-month trial periods expire)

2. 🔴 **Users retain after aha moment** (product-market fit)
   - If false: Core value prop isn't strong enough
   - Test: Day 7, Day 30 retention rates
   - Timeline: January 2025 (first cohorts mature)

3. 🟡 **Alerts are useful, not annoying** (feature risk)
   - If false: Users disable notifications → lose core value
   - Test: Notification disable rate, user feedback
   - Timeline: Ongoing, monthly reviews

4. 🟡 **Can acquire users affordably** (growth risk)
   - If false: Business model doesn't scale
   - Test: CPA across channels
   - Timeline: February 2025 (paid experiments)

5. 🟢 **Z-score is understandable** (UX risk)
   - If false: Can simplify to risk levels
   - Test: User comprehension surveys
   - Timeline: January 2025

**Testing Plan**: See `docs/product/experiments/experiment-log.md`

---

## MVP vs. Final Vision

### MVP (Now - Dec 2024)

**Core Offering**:
- Interactive safety map (web-based, H3 hexagons)
- Z-score risk assessment for St. Louis metro
- Background monitoring with proactive alerts (mobile)
- User-configurable alert settings
- Simple freemium model ($4.99/month premium)

**Target Market**:
- Urban residents in St. Louis
- Walking commuters, solo travelers
- Safety-conscious individuals
- Early adopters comfortable with new tech

**Distribution**:
- Direct-to-consumer (website + app stores)
- Organic growth (SEO, Reddit, word-of-mouth)
- Beta user referrals

**Goal**: Validate product-market fit with 500 users

---

### V2 Vision (6-12 months - 2025)

**Enhanced Features**:
- Multi-city expansion (Chicago, NYC, LA, SF)
- Historical crime trends and time-of-day analysis
- Crime type filtering (violent, property, drug)
- Saved locations and custom alerts
- Route planning with safety optimization
- Server-side push notifications (Firebase)
- In-app safety tips and resources

**Target Market**:
- Expanding to major US cities
- Travelers and tourists
- Parents monitoring children
- Real estate agents (early B2B pilots)

**Distribution**:
- Paid acquisition (Facebook, Google Ads)
- Content marketing (blog, safety guides)
- Partnerships (real estate, insurance)

**Goal**: 10,000 users, $5k MRR

---

### Long-Term Vision (2-3 years - 2026-2027)

**Platform Features**:
- AI-powered predictive crime modeling
- Real-time crime event streaming (police scanner integration)
- Social safety network (share with friends/family)
- Smart home integration (Alexa, Google Home alerts)
- Wearable support (Apple Watch, Fitbit)
- Offline maps and caching
- International expansion

**B2B Products**:
- White-label solutions for real estate platforms (Zillow, Redfin)
- API access for ride-sharing companies (Uber, Lyft)
- Insurance partnerships (discounts for users in safe areas)
- Corporate safety programs (enterprise licenses)

**Distribution**:
- Multi-channel (organic, paid, partnerships, B2B sales)
- App Store feature placements
- Press coverage (TechCrunch, Wired, WSJ)

**Goal**: 100,000+ users, $100k+ MRR, sustainable business

---

### Path from MVP to Vision

**Build-Measure-Learn Cycles**:

1. **MVP → V1.5** (Q1 2025)
   - Learn: Do users retain? Why/why not?
   - Build: Fix onboarding, improve alerts based on feedback
   - Measure: Retention, NPS, conversion rate

2. **V1.5 → V2** (Q2-Q3 2025)
   - Learn: What features do power users request most?
   - Build: Historical trends, crime filtering, multi-city
   - Measure: Feature usage, user growth rate

3. **V2 → Platform** (Q4 2025 - 2026)
   - Learn: Can we acquire users profitably at scale?
   - Build: Partnerships, B2B offerings, advanced features
   - Measure: CAC, LTV, revenue per user

**Key Decision Points**:
- **March 2025**: Pivot or Persevere based on retention/conversion
- **June 2025**: Expand to second city if metrics strong
- **December 2025**: Launch B2B pilot if user base proven
- **Mid 2026**: Seek outside funding if scaling successfully

**Guiding Principle**: 
Don't build Vision features until MVP is validated. Each step must prove value before moving forward.

---

## MVP Retrospective Template

### Date: [YYYY-MM-DD]

**Participants**: [Team members]

### What Went Well ✅
- _[Success 1]_
- _[Success 2]_

### What Didn't Go Well ❌
- _[Challenge 1]_
- _[Challenge 2]_

### What We Learned 📚
- _[Learning 1]_
- _[Learning 2]_

### What We'll Change 🔄
- _[Action 1]_
- _[Action 2]_

### Pivot or Persevere? 🎯
**Decision**: [Persevere | Pivot | Iterate]  
**Reasoning**: _[Explain based on data]_

---

## Resources

- **The Lean Startup** - Eric Ries (Chapter 6: Build-Measure-Learn)
- **Running Lean** - Ash Maurya (Part 2: Validate Your Product)
- **Inspired** - Marty Cagan (Product Discovery)
- **Sprint** - Jake Knapp (Rapid prototyping)

**Related Documents**:
- Technical Architecture: `/docs/ARCHITECTURE.md`
- Feature Prioritization: `docs/product/mvp/feature-prioritization.md`
- Experiment Log: `docs/product/experiments/experiment-log.md`

