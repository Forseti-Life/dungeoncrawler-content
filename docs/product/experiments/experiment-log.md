# Experiment Log

**Last Updated**: 2024-12-13  
**Status**: 🟡 Template Ready

---

## Overview

This document tracks all experiments run to test hypotheses about our business model, product, and customers. Each experiment follows the Build-Measure-Learn cycle.

**Key Principle**: Fail fast, learn fast. The goal is validated learning, not being "right."

---

## Experiment Template

```markdown
## Experiment [ID]: [Short Name]

**Date Started**: [YYYY-MM-DD]  
**Date Completed**: [YYYY-MM-DD]  
**Status**: 🟡 In Progress | ✅ Complete | ❌ Cancelled  
**Owner**: [Name]

### Hypothesis
We believe that **[doing this action]**  
For **[these people]**  
Will result in **[this measurable outcome]**  
We'll know we're right when we see **[this specific signal]**

### Why This Matters
**Business Model Risk**: [Problem | Solution | Market | Channel | Price | etc.]  
**Risk Level**: 🔴 High | 🟡 Medium | 🟢 Low  
**If wrong**: [What happens if hypothesis is invalidated?]

### Experiment Design
**Type**: [Customer Interview | Survey | A/B Test | Landing Page | Smoke Test | MVP | Concierge | Wizard of Oz]  
**Duration**: [X days/weeks]  
**Resources Needed**: [Time, money, people, tools]

### Success Criteria
**Quantitative Target**: [e.g., 10% conversion rate, 20 signups, $500 revenue]  
**Qualitative Target**: [e.g., 8 out of 10 customers say "X"]  
**Minimum Success Threshold**: [What's the minimum result to validate hypothesis?]

### Metrics Tracked
1. **Primary Metric**: [What you're measuring]
2. **Secondary Metrics**: [Supporting data]
3. **Guardrail Metrics**: [Make sure you're not breaking something]

### Setup & Execution
**What we built/tested**: [Description]  
**Audience**: [Who saw this experiment]  
**Sample Size**: [How many people]  
**Test Conditions**: [Variables, controls, etc.]

### Results
**Date Completed**: [YYYY-MM-DD]

**Quantitative Results**:
- Primary Metric: [Actual result vs. target]
- Secondary Metrics: [Results]
- Statistical Significance: [p-value, confidence interval if applicable]

**Qualitative Results**:
- [Key findings from interviews, surveys, feedback]

**Unexpected Findings**:
- [Surprises, secondary discoveries]

### Learning
**Hypothesis Status**: ✅ Validated | ❌ Invalidated | 🟡 Partially Validated

**What We Learned**:
1. [Key learning 1]
2. [Key learning 2]
3. [Key learning 3]

**Confidence Level**: [High | Medium | Low]  
**Why**: [Explanation of confidence in results]

### Decision
**Next Action**: [Pivot | Persevere | Iterate | Build Next Feature | Run Follow-up Experiment]

**Reasoning**:
[Explain why you're making this decision based on data]

**Follow-up Experiments** (if any):
- [ ] Experiment [ID]: [Name]
- [ ] Experiment [ID]: [Name]

### Resources & Links
- Experiment setup: [Link]
- Raw data: [Link]
- Analysis: [Link]

---
```

---

## Active Experiments

### Currently Running

#### Experiment 001: [Name]
**Status**: 🟡 In Progress  
**Started**: [Date]  
**Hypothesis**: [Brief description]  
**Check-in Date**: [Next review date]

---

## Completed Experiments

### Experiment 001: [Example - Problem Validation Interviews]

**Date Started**: 2024-01-15  
**Date Completed**: 2024-02-01  
**Status**: ✅ Complete  
**Owner**: [Name]

### Hypothesis
We believe that **conducting in-depth problem interviews**  
For **30 urban professionals who walk daily**  
Will result in **80% confirming they have safety concerns**  
We'll know we're right when we see **specific stories of times they felt unsafe**

### Why This Matters
**Business Model Risk**: Problem Risk (Do customers actually have the problem?)  
**Risk Level**: 🔴 High - Without this, the entire business model fails  
**If wrong**: Pivot to different problem or customer segment

### Experiment Design
**Type**: Customer Interview (Problem Validation)  
**Duration**: 2 weeks  
**Resources Needed**: 
- 20 hours of interview time
- $0 cost (free interviews)
- Interview script (created)

### Success Criteria
**Quantitative Target**: 24 out of 30 (80%) confirm problem  
**Qualitative Target**: At least 20 specific stories of feeling unsafe  
**Minimum Success Threshold**: 20 out of 30 (67%)

### Metrics Tracked
1. **Primary Metric**: % who confirm problem exists
2. **Secondary Metrics**: 
   - Number of specific past examples given
   - Current solutions used
   - Willingness to pay
3. **Guardrail Metrics**: N/A

### Setup & Execution
**What we built/tested**: 15-minute customer interview script  
**Audience**: Urban professionals aged 25-45 who walk >10 min daily  
**Sample Size**: 30 interviews  
**Test Conditions**: 
- Recruited via Reddit, Nextdoor, personal network
- Phone/Zoom interviews
- Followed The Mom Test principles

### Results
**Date Completed**: 2024-02-01

**Quantitative Results**:
- Primary Metric: 27/30 (90%) confirmed problem - ✅ **EXCEEDED TARGET**
- Willing to pay: 18/30 (60%)
- Currently use workarounds: 25/30 (83%)

**Qualitative Results**:
- 28 specific stories of feeling unsafe in past 3 months
- Common themes: 
  - Walking at night in unfamiliar areas
  - Taking wrong turn into "sketchy" neighborhood
  - Not knowing if route is safe before committing
- Current solutions: 
  - Avoid certain areas (23/30)
  - Check Google Maps Street View (15/30)
  - Ask friends/Reddit (20/30)
  - Do nothing, accept risk (12/30)

**Unexpected Findings**:
- Many (15/30) said they'd use a safety map for **new destinations** even during daytime
- 8/30 mentioned wanting this for **travel** (new cities)
- 5/30 parents said they'd use it for their teenage children

### Learning
**Hypothesis Status**: ✅ **VALIDATED**

**What We Learned**:
1. Problem is VERY real - 90% confirmation with strong stories
2. Current solutions are unsatisfying - people actively seeking better alternatives
3. Willingness to pay (60%) is good, but may need to prove value first (freemium?)
4. Use case broader than expected - not just commuting, but travel & parents

**Confidence Level**: High  
**Why**: 
- Sample size sufficient (30 interviews)
- Consistent themes across interviews
- Specific, detailed stories (not vague concerns)
- High alignment across different recruitment sources

### Decision
**Next Action**: ✅ **PERSEVERE** - Proceed to Solution Validation

**Reasoning**:
Problem is validated with high confidence. 90% confirmation rate exceeds our 80% target. Strong qualitative evidence (28 specific stories) shows this is a real, painful problem. Current alternatives are insufficient, creating opportunity for better solution.

**Follow-up Experiments**:
- [x] Experiment 002: Solution Validation (MVP Prototype)
- [ ] Experiment 005: Willingness to Pay (Pricing Test)
- [ ] Experiment 008: Travel Use Case (Secondary Market)

### Resources & Links
- Interview notes: `docs/product/customer-development/customer-interviews-log.md`
- Analysis spreadsheet: [Link]

---

### Experiment 002: [Name]
_[Next completed experiment]_

---

## Experiment Backlog

Prioritized list of experiments to run next.

| ID | Name | Hypothesis | Risk Level | Effort | ICE Score | Status |
|----|------|------------|------------|--------|-----------|--------|
| 003 | [Name] | [Brief] | 🔴 High | Low | 8.5 | ⏳ Planned |
| 004 | [Name] | [Brief] | 🟡 Medium | Medium | 6.0 | 📝 Draft |
| 005 | [Name] | [Brief] | 🔴 High | High | 5.5 | 📝 Draft |

**ICE Score** = (Impact × Confidence × Ease) / 100

---

## Experiment Types Guide

### 1. Customer Interview
**Purpose**: Understand problems, needs, behaviors  
**When**: Problem discovery, solution validation  
**Duration**: 1-3 weeks  
**Cost**: Low (mostly time)

### 2. Survey
**Purpose**: Quantify trends, test assumptions at scale  
**When**: You have specific questions for many people  
**Duration**: 1-2 weeks  
**Cost**: Low to Medium ($0 - $500 for incentives)

### 3. Landing Page Test
**Purpose**: Test demand before building  
**When**: Validating if people want the product  
**Duration**: 1-4 weeks  
**Cost**: Low ($50-$500 for ads)

**How**: Create landing page describing product, drive traffic, measure signups/interest

### 4. Smoke Test
**Purpose**: Test demand for specific feature  
**When**: Before building expensive feature  
**Duration**: 1-2 weeks  
**Cost**: Very Low

**How**: Add button/feature that doesn't work yet, see if people click

### 5. A/B Test
**Purpose**: Compare two variations to optimize  
**When**: You have traffic and want to improve conversion  
**Duration**: 1-4 weeks (until statistical significance)  
**Cost**: Low (requires traffic)

### 6. Concierge MVP
**Purpose**: Deliver service manually to learn before automating  
**When**: Testing service before building tech  
**Duration**: 2-8 weeks  
**Cost**: High (labor intensive)

**How**: Do manually what the product will eventually do automatically

### 7. Wizard of Oz MVP
**Purpose**: Simulate automated product with manual backend  
**When**: Testing if product concept works before building  
**Duration**: 2-8 weeks  
**Cost**: High (labor intensive)

**How**: Build front-end, manually fulfill back-end (user thinks it's automated)

### 8. Prototype Test
**Purpose**: Test usability, value prop, and product-market fit  
**When**: Solution validation  
**Duration**: 2-4 weeks  
**Cost**: Medium (design + development)

---

## Experiment Review Cadence

### Weekly
- Review active experiments
- Check if experiments are on track
- Adjust if needed

### Bi-Weekly
- Review completed experiments
- Decide: Pivot or Persevere
- Plan next experiments

### Monthly
- Review experiment backlog
- Prioritize based on learnings
- Update business model assumptions

---

## Key Metrics Across All Experiments

### Learning Velocity
**Goal**: Increase speed of validated learning

- Experiments per month: [Target: X]
- Avg. experiment duration: [Target: < Y weeks]
- Hypothesis validation rate: [Track over time]

### Build-Measure-Learn Cycle Time
**Goal**: Minimize time through the loop

- Avg. time from idea → experiment → learning: [Track]
- Target: < 2 weeks for quick tests, < 6 weeks for MVPs

---

## Pivot History

Major strategic changes based on experiment results.

### Pivot 001: [Name]
**Date**: [YYYY-MM-DD]  
**Type**: [Zoom-in | Zoom-out | Customer Segment | Customer Need | Platform | Business Architecture | Value Capture | Engine of Growth | Channel | Technology]

**Before**: [Description of old strategy]  
**After**: [Description of new strategy]  
**Why**: [Experiments and data that led to this decision]  
**Impact**: [Results of the pivot]

---

## Resources

- **The Lean Startup** - Eric Ries (Chapter on Experiments)
- **Running Lean** - Ash Maurya (Experiment framework)
- **Testing Business Ideas** - David Bland (Experiment library)
- **Lean Analytics** - Alistair Croll (Metrics for experiments)

