# Feature Prioritization

**Last Updated**: 2024-12-13  
**Status**: 🟡 Template Ready

---

## Overview

Feature prioritization ensures you're building what matters most. Use data-driven frameworks to decide what to build next.

**Key Principle**: Focus on features that drive your North Star Metric and core business goals.

---

## Prioritization Frameworks

### 1. ICE Score (Impact × Confidence × Ease)

**Formula**: ICE Score = (Impact × Confidence × Ease) / 100

**Impact** (1-10): How much will this move key metrics?  
**Confidence** (1-10): How sure are you of the impact?  
**Ease** (1-10): How easy is it to implement?

### 2. RICE Score (Reach × Impact × Confidence / Effort)

**Formula**: RICE Score = (Reach × Impact × Confidence) / Effort

**Reach** (#): How many users affected per quarter?  
**Impact** (0.25-3): Minimal / Low / Medium / High / Massive  
**Confidence** (%): 50% / 80% / 100%  
**Effort** (person-months): Engineering time required

### 3. Value vs. Effort Matrix

Simple 2×2 matrix:
- **High Value, Low Effort** = Do First (Quick Wins)
- **High Value, High Effort** = Do Second (Big Bets)
- **Low Value, Low Effort** = Do Later (Fill-ins)
- **Low Value, High Effort** = Don't Do (Money Pit)

---

## Feature Backlog

### Active Features (In Development)

| Feature | Status | Owner | Target Date | Priority |
|---------|--------|-------|-------------|----------|
| [Feature 1] | 🔄 In Progress | [Name] | [Date] | P0 |
| [Feature 2] | 🔄 In Progress | [Name] | [Date] | P1 |

---

### Prioritized Backlog (ICE Framework)

| Feature | Impact (1-10) | Confidence (1-10) | Ease (1-10) | ICE Score | Priority | Status |
|---------|---------------|-------------------|-------------|-----------|----------|--------|
| [Feature A] | 9 | 8 | 7 | 504 | P0 | ⏳ Next |
| [Feature B] | 8 | 7 | 8 | 448 | P1 | 📋 Backlog |
| [Feature C] | 7 | 6 | 9 | 378 | P2 | 📋 Backlog |
| [Feature D] | 9 | 5 | 4 | 180 | P3 | 💡 Idea |

**Priority Levels**:
- **P0**: Critical (do now)
- **P1**: Important (do next)
- **P2**: Nice to have (do later)
- **P3**: Ideas (research further)

---

## Feature Details Template

### Feature: [Name]

**Status**: 💡 Idea | 🔬 Research | 📋 Backlog | ⏳ Next | 🔄 In Progress | ✅ Complete

**Priority**: P0 | P1 | P2 | P3

**Owner**: [Name]

---

#### Problem Statement

**User Story**: As a [user type], I want [feature] so that [benefit].

**Example**:
> As an urban commuter, I want route planning with safety optimization so that I can choose the safest path to my destination.

**Jobs to Be Done**: When [situation], I want to [motivation], so I can [outcome].

**Example**:
> When I'm walking to a new restaurant at night, I want to see multiple route options with safety scores, so I can choose the route that makes me feel most comfortable.

---

#### Business Impact

**Metrics Expected to Move**:
- Primary: [Metric] → Increase by [X]%
- Secondary: [Metric] → Increase by [Y]%

**Alignment with North Star**: [How this drives North Star Metric]

**Revenue Impact**: [Direct/Indirect revenue impact]

---

#### User Research

**Customer Requests**: [X] users have requested this (from interviews, surveys, support tickets)

**Top Quotes**:
> "[Quote from customer interview]" - [Initials]

**Validation Status**:
- [ ] Problem validated (interviews confirm need)
- [ ] Solution validated (prototype tested)
- [ ] Willingness to pay (if premium feature)

---

#### Technical Specification

**High-Level Approach**: [Brief technical description]

**Dependencies**:
- [Dependency 1]
- [Dependency 2]

**Effort Estimate**: [X person-days/weeks]

**Technical Risk**: 🟢 Low | 🟡 Medium | 🔴 High

---

#### Design

**Mockups/Wireframes**: [Link to Figma/Sketch/etc.]

**User Flow**: [Link or brief description]

**Accessibility Considerations**: [How will this work for all users?]

---

#### Prioritization Score

**ICE Score**:
- Impact: [1-10]
- Confidence: [1-10]
- Ease: [1-10]
- **Total**: [Score]

**RICE Score**:
- Reach: [# users per quarter]
- Impact: [0.25-3]
- Confidence: [%]
- Effort: [person-months]
- **Total**: [Score]

---

#### Success Criteria

**How will we know this succeeded?**

- [ ] [Metric 1] increases by [X]% within [timeframe]
- [ ] [Metric 2] reaches [target]
- [ ] [X]% of users adopt feature within [timeframe]
- [ ] User satisfaction score of [X/10]

**Experiment Plan**: [How will we test this? See `docs/product/experiments/experiment-log.md`]

---

#### Timeline

- **Discovery/Research**: [Date range]
- **Design**: [Date range]
- **Development**: [Date range]
- **Testing**: [Date range]
- **Launch**: [Target date]

---

#### Post-Launch Review

**Launched**: [YYYY-MM-DD]

**Actual Results**:
- [Metric 1]: [Actual vs. Expected]
- [Metric 2]: [Actual vs. Expected]

**Learnings**:
- [What worked well]
- [What didn't work]
- [What to do differently next time]

---

## Current Sprint

### Sprint Goals

**Sprint**: [Sprint # or Date Range]

**Goal**: [High-level objective for this sprint]

**Features in Sprint**:
1. [ ] [Feature 1] - Owner: [Name] - Status: [%]
2. [ ] [Feature 2] - Owner: [Name] - Status: [%]
3. [ ] [Feature 3] - Owner: [Name] - Status: [%]

---

## MVP Feature Set

### ✅ Core Features (Must Have)

**Already Built**:
- [x] Interactive safety map
- [x] H3 hexagon aggregation
- [x] Z-score risk assessment
- [x] Background location monitoring
- [x] Push notifications

**In Development**:
- [ ] [Feature name]

---

### 🟡 Secondary Features (Nice to Have)

**Short-term** (Next 3 months):
- [ ] [Feature name]
- [ ] [Feature name]

**Medium-term** (3-6 months):
- [ ] [Feature name]
- [ ] [Feature name]

---

### ❌ Not Now (Future)

**Post-MVP** (6+ months):
- [ ] [Feature name]
- [ ] [Feature name]

---

## Feature Requests from Customers

### Top Requested Features

| Feature | Requests | Priority | Status | Notes |
|---------|----------|----------|--------|-------|
| [Feature 1] | 45 | P0 | 🔄 In Dev | High demand |
| [Feature 2] | 32 | P1 | 📋 Backlog | After MVP |
| [Feature 3] | 18 | P2 | 💡 Idea | Research needed |

**Source**: Customer interviews, support tickets, NPS surveys

---

## Feature Kill List

### Features to Remove or Deprioritize

Sometimes the best decision is NOT to build something.

| Feature | Why Kill It? | Date Killed |
|---------|--------------|-------------|
| [Feature X] | Low usage (<5%), maintenance burden | [Date] |
| [Feature Y] | Doesn't move North Star, confuses users | [Date] |

---

## Roadmap

### This Quarter (Q[X] 2024)

**Theme**: [Focus area - e.g., "Improve Retention"]

**Major Features**:
1. [Feature 1] - Launch [Month]
2. [Feature 2] - Launch [Month]
3. [Feature 3] - Launch [Month]

**Expected Impact**:
- [Key metric] improves from [X] to [Y]

---

### Next Quarter (Q[X] 2024)

**Theme**: [Focus area - e.g., "Scale Acquisition"]

**Major Features**:
1. [Feature 1]
2. [Feature 2]

---

### 6-12 Month Vision

**Major Bets**:
- [Big feature 1]
- [Big feature 2]

**Strategic Goals**:
- [Goal 1]
- [Goal 2]

---

## Feature Experimentation

### Before Building: Test First

**Options**:
1. **Smoke Test**: Add button, measure clicks
2. **Landing Page**: Describe feature, measure signups
3. **Prototype**: Build mockup, test with 10 users
4. **Wizard of Oz**: Fake automation, do manually

**See**: `docs/product/experiments/experiment-log.md`

---

## Decision Framework

### Should We Build This Feature?

Ask these questions:

1. **Does it drive our North Star Metric?**
   - [ ] Yes, directly
   - [ ] Yes, indirectly
   - [ ] No

2. **Do customers need it?**
   - [ ] Validated through research (>10 requests)
   - [ ] Assumed need (hypothesis)
   - [ ] Internal idea

3. **Does it align with our strategy?**
   - [ ] Core to our value prop
   - [ ] Nice enhancement
   - [ ] Off strategy

4. **Can we build it well?**
   - [ ] Have technical capability
   - [ ] Need to learn/hire
   - [ ] Too complex for now

5. **What's the opportunity cost?**
   - [ ] Worth delaying other features
   - [ ] Should prioritize something else

**Decision**:
- 4-5 Yes → Build Now
- 2-3 Yes → Build Later
- 0-1 Yes → Don't Build

---

## Resources

- **Inspired** - Marty Cagan (Product Discovery)
- **Continuous Discovery Habits** - Teresa Torres
- **Intercom on Product Management** - Intercom (Free eBook)
- **Prioritization Tools**: ProductPlan, Aha!, Productboard

