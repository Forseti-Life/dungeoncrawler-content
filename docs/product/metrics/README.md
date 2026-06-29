# Metrics

**Last Updated**: December 13, 2024  
**Status**: 🟡 Template Ready - Data Collection Needed

---

## Purpose

This directory contains all metrics frameworks, tracking dashboards, and analysis tools to measure product success and customer behavior. We follow the principle: "If you can't measure it, you can't improve it."

**Key Philosophy**: Focus on actionable metrics that drive decisions, not vanity metrics that only look good.

---

## Directory Contents

### 📊 [pirate-metrics.md](./pirate-metrics.md)
**Purpose**: AARRR framework for tracking customer lifecycle  
**Status**: 🟡 Template Ready  
**Framework**: Dave McClure's Pirate Metrics  
**Contains**:
- **Acquisition**: How users find you (traffic sources, CAC)
- **Activation**: First great experience (onboarding, aha moment)
- **Retention**: Users come back (DAU, WAU, MAU, retention curves)
- **Revenue**: Users pay (conversion rate, ARPU, LTV)
- **Referral**: Users tell others (viral coefficient, NPS)

**Why "Pirate"?**: AARRR = "Arrr!" 🏴‍☠️

**Use When**: 
- Measuring full customer lifecycle
- Identifying funnel bottlenecks
- Prioritizing growth initiatives
- Reporting to stakeholders

---

### 📈 [cohort-analysis.md](./cohort-analysis.md)
**Purpose**: Track user behavior over time by cohort  
**Status**: 🟡 Template Ready  
**Contains**:
- Acquisition cohort analysis (grouped by signup date)
- Behavioral cohort analysis (grouped by actions taken)
- Retention curve analysis
- Product-market fit signals (flattening retention curve)
- Cohort comparison tables

**Why It Matters**: Average metrics can hide declining retention. Cohort analysis reveals true trends.

**Example Insight**: 
- Overall: 100 active users (looks good!)
- Reality: January cohort 40% retained, February cohort 20% retained (declining!)

**Use When**:
- Measuring retention improvements
- Comparing feature impact across cohorts
- Identifying when users churn
- Testing product-market fit

---

### 🎯 [key-metrics-dashboard.md](./key-metrics-dashboard.md)
**Purpose**: Central dashboard for all critical metrics  
**Status**: 🟡 Template Ready  
**Contains**:
- North Star Metric (single most important metric)
- Executive summary snapshot
- Acquisition metrics (signups, traffic sources)
- Activation metrics (onboarding completion, aha moment rate)
- Retention metrics (DAU, WAU, MAU, churn)
- Revenue metrics (MRR, ARPU, LTV, CAC, LTV:CAC ratio)
- Referral metrics (NPS, viral coefficient)
- Product health metrics (performance, errors, uptime)
- Weekly/monthly trends

**North Star Metric** (To Be Defined):
- Should capture core value delivered to customers
- Options for Forseti: Active weekly users, Safety alerts sent, Areas monitored

**Use When**: 
- Daily/weekly performance reviews
- Monthly reporting
- Identifying issues quickly
- Celebrating wins

---

## Metrics Philosophy

### Actionable vs. Vanity Metrics

#### ❌ Vanity Metrics (Avoid These)
Metrics that look good but don't drive decisions:
- **Page views**: Who cares if they don't take action?
- **Total registered users**: What % are active?
- **Social media followers**: Are they customers?
- **Press mentions**: Did it drive usage?

#### ✅ Actionable Metrics (Focus Here)
Metrics that inform specific actions:
- **Activation rate**: 30% complete onboarding → improve onboarding flow
- **Day 7 retention**: 15% return → test engagement features
- **Conversion rate**: 2% free-to-paid → optimize trial experience
- **Churn rate**: 10%/month → improve retention features

---

## North Star Metric

### What is a North Star Metric?

The **single metric** that best captures the core value you deliver to customers.

**Characteristics of Good North Star Metric**:
- ✅ Measures value delivered to customers
- ✅ Reflects customer engagement with core product
- ✅ Leads to revenue (eventually)
- ✅ Simple and easy to communicate
- ✅ Actionable (can influence through product changes)

### Examples from Other Companies

| Company | North Star Metric |
|---------|-------------------|
| Facebook | Daily Active Users (DAU) |
| Airbnb | Nights Booked |
| Uber | Rides per Week |
| Slack | Messages Sent by Teams |
| Netflix | Hours Watched |
| Spotify | Time Spent Listening |

### Forseti Options (To Be Decided)

**Option 1: Weekly Active Users (WAU)**
- Pro: Clear engagement signal
- Pro: Industry standard
- Con: Doesn't capture value delivered

**Option 2: Safety Alerts Sent**
- Pro: Directly measures core value
- Pro: Leads to retention
- Con: Could optimize for spam

**Option 3: Protected Walking Hours**
- Pro: Captures actual usage
- Pro: Unique to our value prop
- Con: Hard to measure accurately

**Decision Point**: Choose after first 100 users

---

## Key Metric Targets (Beta Phase)

### Minimum Success Thresholds

| Metric | Minimum | Stretch | Current | Status |
|--------|---------|---------|---------|--------|
| **Activation Rate** | 40% | 60% | TBD | 🟡 |
| **Day 7 Retention** | 20% | 35% | TBD | 🟡 |
| **Day 30 Retention** | 10% | 20% | TBD | 🟡 |
| **Free-to-Paid** | 2% | 5% | TBD | 🟡 |
| **NPS Score** | 30 | 50 | TBD | 🟡 |
| **Churn Rate** | <10%/mo | <5%/mo | TBD | 🟡 |

**Decision Point**: March 2025 with 500 users
- ✅ **Persevere**: Metrics meet/exceed minimum → continue building
- 🔄 **Iterate**: Close but not quite → optimize features
- 🔀 **Pivot**: Well below minimum → change strategy
- ❌ **Stop**: No traction → explore different problem

---

## Analytics Stack

### Tools & Infrastructure

**Web Analytics**:
- Google Analytics (website traffic, behavior)
- Mixpanel or Amplitude (event tracking, funnels)

**Mobile Analytics**:
- Firebase Analytics (app usage, crashes)
- React Native Analytics (custom events)

**Product Analytics**:
- Mixpanel (user journeys, cohorts)
- Amplitude (retention, engagement)

**Business Metrics**:
- Stripe Dashboard (MRR, churn, revenue)
- Custom SQL queries (detailed analysis)

**Monitoring**:
- Sentry (error tracking)
- Datadog or New Relic (performance)
- UptimeRobot (uptime monitoring)

---

## Event Tracking Plan

### Critical Events to Track

**Acquisition Events**:
- `page_view` - User visits website
- `app_download` - User installs mobile app
- `signup_started` - User begins registration
- `signup_completed` - User completes registration
- `source` - Where user came from (organic, paid, referral)

**Activation Events**:
- `login_success` - User logs in
- `onboarding_started` - User begins onboarding
- `location_permission_granted` - User allows location access
- `background_permission_granted` - User allows "Always Allow"
- `trial_started` - User starts premium trial
- `map_interaction` - User clicks hexagon on map (aha moment)
- `onboarding_completed` - User finishes onboarding

**Retention Events**:
- `session_start` - User opens app
- `map_viewed` - User checks safety map
- `alert_received` - User gets notification
- `alert_interacted` - User taps notification
- `settings_changed` - User customizes preferences

**Revenue Events**:
- `trial_reminder_sent` - Trial end reminder sent
- `subscription_started` - User subscribes to paid
- `payment_success` - Payment processed
- `subscription_cancelled` - User cancels
- `subscription_reactivated` - User re-subscribes

**Referral Events**:
- `referral_link_shared` - User shares referral
- `referral_signup` - Friend signs up via referral
- `review_left` - User leaves app store review
- `nps_survey_completed` - User completes NPS survey

---

## Reporting Cadence

### Daily Reviews (5 minutes)
- Check North Star Metric
- Review new signups
- Check for errors/crashes
- Monitor API uptime

### Weekly Reviews (30 minutes)
- Review AARRR metrics
- Analyze cohort retention
- Check experiment results
- Identify blockers/opportunities

### Monthly Reviews (2 hours)
- Deep dive into trends
- Cohort analysis
- Revenue analysis
- Update forecasts
- Share with stakeholders

### Quarterly Reviews (Half day)
- Product-market fit assessment
- Pivot or persevere decision
- Strategic planning
- Goal setting for next quarter

---

## Product-Market Fit Signals

### Sean Ellis PMF Survey

**Question**: "How would you feel if you could no longer use [product]?"

**PMF Threshold**: 40%+ answer "Very disappointed"

**Scale**:
- Very disappointed (strong PMF signal)
- Somewhat disappointed (weak signal)
- Not disappointed (no PMF)

**When to Run**: After users have experienced core value (1-2 weeks of usage)

---

### Retention Curve Analysis

**PMF Signal**: Retention curve flattens (doesn't go to zero)

```
100% ●
     |  ●
 80% |    ●
     |      ● ←─── Curve flattens here
 60% |        ●────●────●───  (PMF!)
     |
 40% |
     |
 20% |
     |
  0% └────────────────────────
     D1  D7  D14  D21  D28  D60
```

**No PMF**: Curve keeps declining toward zero  
**PMF**: Curve flattens at some % (20%, 30%, 40%+)

---

### Other PMF Signals

- ✅ Organic growth (word of mouth, low CAC)
- ✅ High NPS score (30+)
- ✅ Users voluntarily pay (not just trial)
- ✅ Active engagement with core features
- ✅ Low churn rate (<5% per month)
- ✅ Users request more features (invested in success)

---

## Related Documentation

- [MVP Definition](../mvp/mvp-definition.md) - Success criteria for MVP
- [Experiments](../experiments/) - Test hypotheses, measure impact
- [Process Flow Validation](../process-flow-validation.md) - System health metrics
- [User Journey](../user-journey/sarah-urban-commuter.md) - Journey stage metrics

---

## Next Steps

### Immediate Actions (This Week)
1. Choose North Star Metric
2. Set up event tracking (Mixpanel/Amplitude)
3. Implement critical events in mobile app
4. Create Google Analytics dashboard for website

### Short-term Goals (Next Month)
- Establish baseline metrics with first 100 users
- Set up automated weekly reports
- Create cohort tracking spreadsheet
- Run first PMF survey

### Long-term Vision (6 Months)
- Clear PMF signals (40%+ "very disappointed", flattening retention)
- Data-driven product roadmap based on metrics
- Predictable unit economics (LTV > 3x CAC)
- Automated dashboards and alerts
