# Cohort Analysis

**Last Updated**: 2024-12-13  
**Status**: 🟡 Template Ready

---

## What is Cohort Analysis?

Cohort analysis groups users by a shared characteristic (usually signup date) and tracks their behavior over time. This reveals:
- **Is retention improving?** (Are newer cohorts stickier?)
- **Which features drive retention?** (Compare cohorts with different features)
- **When do users churn?** (At what point do they stop returning?)
- **Product-market fit signal** (Flattening retention curve = PMF)

---

## Why Cohort Analysis Matters

### Average Metrics Can Mislead

**Problem**: Overall metrics hide trends.

**Example**:
- Month 1: 100 signups, 40% retention → 40 active users
- Month 2: 200 signups, 20% retention → 40 active users
- Overall metric: 80 active users (looks like growth!)
- Reality: Retention is dropping (getting worse)

**Solution**: Cohort analysis reveals the true trend.

---

## Cohort Types

### 1. Acquisition Cohort (Most Common)
**Definition**: Users grouped by signup date

**Use Case**: Track how long users stay active after joining

**Example**: "January 2024 cohort" = all users who signed up in January 2024

### 2. Behavioral Cohort
**Definition**: Users grouped by specific action

**Examples**:
- Users who completed onboarding
- Users who enabled push notifications
- Users who invited a friend

**Use Case**: Compare behavior of users who did X vs. didn't do X

### 3. Feature Cohort
**Definition**: Users grouped by which features they use

**Examples**:
- Users who use background monitoring
- Users who only view the map
- Premium subscribers vs. free users

**Use Case**: Identify which features drive retention

---

## Retention Cohort Table

### Weekly Retention (Recommended for Early Stage)

Track what % of each cohort returns each week.

| Cohort | Size | Week 0 | Week 1 | Week 2 | Week 3 | Week 4 | Week 8 | Week 12 |
|--------|------|--------|--------|--------|--------|--------|--------|---------|
| Jan 2024 | 100 | 100% | 42% | 35% | 30% | 28% | 25% | 23% |
| Feb 2024 | 150 | 100% | 45% | 38% | 33% | 30% | 27% | - |
| Mar 2024 | 200 | 100% | 48% | 40% | 35% | 32% | - | - |
| Apr 2024 | 250 | 100% | 50% | 42% | 37% | - | - | - |

**What to Look For**:
- ✅ **Newer cohorts retain better** = Product improving
- ✅ **Curve flattens after X weeks** = Core user base forming (PMF signal)
- ❌ **All cohorts declining** = Product not sticky, need to improve
- ❌ **Newer cohorts worse** = Recent changes hurting retention

### Monthly Retention (For Mature Products)

| Cohort | Size | Month 0 | Month 1 | Month 2 | Month 3 | Month 6 | Month 12 |
|--------|------|---------|---------|---------|---------|---------|----------|
| Jan 2024 | 500 | 100% | 35% | 28% | 25% | 20% | 18% |
| Feb 2024 | 550 | 100% | 37% | 30% | 26% | 21% | - |
| Mar 2024 | 600 | 100% | 40% | 32% | 28% | - | - |

---

## Retention Curve Interpretation

### Ideal: Smile Curve (Strong PMF)

```
100% │●
     │ ╲
 50% │  ●─●─●─●─●─●─●─●──→ (Flattens = core users stick)
     │
  0% └────────────────────
     Week 0   4   8  12  16
```

**What it means**: Initial drop-off, then retention stabilizes. You have a core user base who finds lasting value.

### Good: Flattening Curve

```
100% │●
     │ ╲
 50% │  ╲___
     │      ╲___
 20% │          ●───●───●──→
  0% └────────────────────
     Week 0   4   8  12  16
```

**What it means**: Decent retention. Some users churn, but a meaningful % stay long-term.

### Warning: Declining Curve

```
100% │●
     │ ╲
 50% │  ╲
     │   ╲
  0% │    ╲___●___●───●───→
     └────────────────────
     Week 0   4   8  12  16
```

**What it means**: Continuous churn. Users try it but don't find lasting value. No PMF yet.

### Critical: Cliff (No Retention)

```
100% │●
     │ │
 50% │ │
     │ │
  0% │ ●─────────────────→
     └────────────────────
     Week 0   4   8  12  16
```

**What it means**: Users try once and never return. Fundamental problem with product.

---

## Cohort Comparison

### Comparing Features

**Question**: Does enabling background monitoring improve retention?

| Cohort | Week 0 | Week 1 | Week 4 | Week 8 |
|--------|--------|--------|--------|--------|
| **With Background Monitoring** | 100% | 60% | 45% | 40% |
| **Without Background Monitoring** | 100% | 35% | 22% | 15% |

**Conclusion**: Background monitoring users retain 2.7x better at Week 8. This is a key retention driver.

### Comparing Time Periods

**Question**: Did our March update improve retention?

| Cohort | Week 0 | Week 1 | Week 4 | Week 8 |
|--------|--------|--------|--------|--------|
| **Pre-Update (Jan-Feb)** | 100% | 40% | 25% | 20% |
| **Post-Update (Mar-Apr)** | 100% | 50% | 35% | 30% |

**Conclusion**: Update improved Week 8 retention by 50% (20% → 30%). Keep this direction.

---

## Churn Analysis by Cohort

### When Do Users Churn?

| Cohort | Never Returned | Churned Week 1-2 | Churned Week 3-4 | Churned After Week 4 |
|--------|----------------|------------------|------------------|----------------------|
| Jan 2024 | 30% | 25% | 15% | 7% |
| Feb 2024 | 28% | 23% | 14% | - |
| Mar 2024 | 25% | 20% | - | - |

**Insights**:
- **30% never return** → Activation problem (didn't see value)
- **25% churn in first 2 weeks** → Onboarding/first experience issue
- **15% churn weeks 3-4** → Initial value wore off, need deeper engagement
- **7% churn later** → Natural attrition or specific event-driven

### Churn Reasons by Cohort

Survey churned users from each cohort.

| Reason | Jan Cohort | Feb Cohort | Mar Cohort |
|--------|------------|------------|------------|
| "Didn't find it useful" | 45% | 40% | 35% ↓ |
| "Too many notifications" | 20% | 25% | 15% ↓ |
| "Privacy concerns" | 15% | 10% | 10% |
| "Technical issues" | 10% | 15% | 5% ↓ |
| "Too expensive" | 10% | 10% | 10% |

**Insights**:
- "Not useful" decreasing (product improving ✅)
- "Too many notifications" - Fixed in March update ✅
- "Technical issues" - Decreased after bug fixes ✅

---

## Resurrection Analysis

### Can You Win Back Churned Users?

| Cohort | Churned Users | Resurrection Campaign Sent | Returned | Resurrection Rate |
|--------|---------------|---------------------------|----------|-------------------|
| Jan 2024 | 60 | 60 | 12 | 20% |
| Feb 2024 | 75 | 75 | 18 | 24% |
| Mar 2024 | 50 | 50 | 15 | 30% |

**Insights**:
- Resurrection rate improving (20% → 30%)
- New feature announcements work well
- Best timing: 2-4 weeks after churn

---

## Engagement Depth by Cohort

### How Many Core Actions Per Week?

**Core Actions**: View map, receive alert, check safety score

| Cohort | 0 actions | 1-2 actions | 3-5 actions | 6+ actions |
|--------|-----------|-------------|-------------|------------|
| Jan 2024 | 40% | 30% | 20% | 10% |
| Feb 2024 | 35% | 30% | 22% | 13% |
| Mar 2024 | 30% | 28% | 25% | 17% |

**Insights**:
- More users in "power user" category (6+ actions) in recent cohorts
- Fewer inactive users (0 actions) in recent cohorts
- Product becoming stickier ✅

---

## Revenue Cohorts

### LTV by Cohort

Track revenue generated by each cohort over time.

| Cohort | Size | Month 1 | Month 2 | Month 3 | Month 6 | Total LTV (6mo) |
|--------|------|---------|---------|---------|---------|-----------------|
| Jan 2024 | 100 | $150 | $280 | $400 | $700 | $7.00 |
| Feb 2024 | 150 | $200 | $380 | $550 | - | - |
| Mar 2024 | 200 | $280 | $500 | - | - | - |

**What to Calculate**:
- **LTV per cohort**: Total revenue ÷ cohort size
- **Payback period**: When does LTV exceed CAC?
- **LTV growth**: Are newer cohorts more valuable?

### Conversion by Cohort

| Cohort | Free Users | Converted to Paid | Conversion Rate | Avg. Days to Convert |
|--------|------------|-------------------|-----------------|----------------------|
| Jan 2024 | 100 | 8 | 8% | 21 days |
| Feb 2024 | 150 | 15 | 10% | 18 days |
| Mar 2024 | 200 | 24 | 12% | 14 days |

**Insights**:
- Conversion rate improving (8% → 12%)
- Time to convert decreasing (21 → 14 days)
- Recent changes helping monetization ✅

---

## Product Changes Impact

### Track Impact of Major Changes

**March 2024 Update**: Added custom notification thresholds

| Metric | Pre-Update (Jan-Feb) | Post-Update (Mar-Apr) | Change |
|--------|----------------------|-----------------------|--------|
| Week 1 Retention | 40% | 50% | +25% ✅ |
| Week 4 Retention | 25% | 35% | +40% ✅ |
| Avg. Sessions/Week | 2.1 | 3.2 | +52% ✅ |
| Free to Paid | 8% | 12% | +50% ✅ |

**Conclusion**: Update significantly improved all key metrics. Continue this direction.

---

## Cohort Segmentation

### Break Down Cohorts by Attributes

**Example**: January 2024 cohort by source

| Acquisition Source | Size | Week 4 Retention | Week 8 Retention |
|--------------------|------|------------------|------------------|
| Organic Search | 40 | 35% | 30% |
| Paid Ads | 30 | 20% | 12% |
| Reddit | 20 | 50% | 45% |
| Referral | 10 | 60% | 55% |

**Insights**:
- Reddit and Referral users retain 2-3x better
- Focus acquisition efforts on these channels
- Paid ads bring low-quality users (high churn)

---

## Product-Market Fit Score

### Retention-Based PMF Indicator

**Sean Ellis Test**: "How would you feel if you could no longer use this product?"
- Very disappointed: [X]%
- Somewhat disappointed: [Y]%
- Not disappointed: [Z]%

**Target**: >40% say "very disappointed" = PMF

**By Cohort**:

| Cohort | Very Disappointed | Somewhat | Not |
|--------|-------------------|----------|-----|
| Jan 2024 | 35% | 40% | 25% |
| Feb 2024 | 38% | 38% | 24% |
| Mar 2024 | 42% | 36% | 22% |

**Status**: March cohort crossed PMF threshold! ✅

---

## Tracking & Tools

### How to Track Cohorts

**Tools**:
- **Mixpanel**: Built-in cohort analysis
- **Amplitude**: Powerful retention tracking
- **Google Analytics**: Basic cohort reports
- **Custom**: SQL queries on your database

### SQL Query Example

```sql
-- Weekly retention by signup cohort
SELECT 
  DATE_TRUNC('week', signup_date) as cohort_week,
  COUNT(DISTINCT user_id) as cohort_size,
  COUNT(DISTINCT CASE WHEN weeks_since_signup = 1 THEN user_id END) / COUNT(DISTINCT user_id)::float as week_1_retention,
  COUNT(DISTINCT CASE WHEN weeks_since_signup = 4 THEN user_id END) / COUNT(DISTINCT user_id)::float as week_4_retention
FROM user_activity
GROUP BY cohort_week
ORDER BY cohort_week DESC;
```

---

## Action Items from Cohort Analysis

### Weekly Review Checklist

- [ ] Update cohort retention table
- [ ] Compare current cohort to previous cohorts
- [ ] Identify retention trends (improving/declining)
- [ ] Spot churn spikes and investigate causes
- [ ] Compare cohorts with different features/behaviors
- [ ] Calculate LTV by cohort
- [ ] Document insights and action items

### Monthly Deep Dive

- [ ] Full cohort comparison analysis
- [ ] Segment cohorts by acquisition source, behavior
- [ ] Analyze resurrection campaigns
- [ ] Review PMF indicators
- [ ] Report findings to team
- [ ] Plan experiments based on learnings

---

## Resources

- **Lean Analytics** - Alistair Croll (Chapter 5: Analytics Frameworks)
- **Amplitude Playbook**: cohort analysis best practices
- **Mixpanel Guide**: retention and cohort analysis
- **Superhuman PMF Survey**: Sean Ellis test methodology

