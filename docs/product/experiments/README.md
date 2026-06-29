# Experiments

**Last Updated**: December 13, 2024  
**Status**: 🟡 Template Ready - Active Experimentation Needed

---

## Purpose

This directory tracks all experiments designed to test hypotheses about our business model, product features, customer behavior, and growth strategies. Every experiment follows the Build-Measure-Learn cycle of the Lean Startup methodology.

**Key Principle**: We measure progress through validated learning, not just shipping features.

---

## Directory Contents

### 🧪 [experiment-log.md](./experiment-log.md)
**Purpose**: Central repository for all experiments run to test business hypotheses  
**Status**: 🟡 Template Ready  
**Contains**:
- Experiment template with hypothesis structure
- Success criteria and metrics
- Experiment design (A/B tests, surveys, interviews, MVP tests)
- Results and learnings
- Decisions (Pivot, Persevere, Iterate)

**Experiment Types Supported**:
- Customer Interviews
- Surveys
- A/B Tests
- Landing Page Tests
- Smoke Tests
- MVP/Prototype Tests
- Concierge Tests (manual service)
- Wizard of Oz (fake automation)

**Use When**: Before building any feature, testing any assumption, or making strategic decisions

---

### 🔄 [pivot-decisions.md](./pivot-decisions.md)
**Purpose**: Document major strategy changes and pivot decisions  
**Status**: 🟡 Template Ready  
**Contains**:
- Definition of pivots vs. iterations
- When to pivot vs. persevere framework
- 10 types of pivots (zoom-in, zoom-out, customer segment, etc.)
- Innovation accounting milestones
- Pivot decision log template
- Post-pivot analysis

**Types of Pivots Documented**:
1. Zoom-in Pivot (single feature becomes whole product)
2. Zoom-out Pivot (whole product becomes single feature)
3. Customer Segment Pivot (same solution, different customers)
4. Customer Need Pivot (different problem for same customers)
5. Platform Pivot (app → platform or vice versa)
6. Business Architecture Pivot (B2C → B2B or vice versa)
7. Value Capture Pivot (change monetization model)
8. Engine of Growth Pivot (viral → paid → sticky)
9. Channel Pivot (different distribution channels)
10. Technology Pivot (same solution, different technology)

**Use When**: Metrics plateau, hypotheses repeatedly invalidated, or major strategic shift needed

---

## Experiment Framework

### The Build-Measure-Learn Loop

```
IDEA → BUILD → PRODUCT → MEASURE → DATA → LEARN → IDEA
         ↑                                            ↓
         └──────────────── FEEDBACK ─────────────────┘
```

Every experiment cycles through:
1. **Idea**: Form hypothesis about what will work
2. **Build**: Create minimum test (not always code)
3. **Measure**: Collect data from experiment
4. **Learn**: Analyze results, validate or invalidate hypothesis
5. **Decide**: Pivot, Persevere, or Iterate

---

## Experiment Template Structure

Each experiment should include:

```markdown
## Experiment [ID]: [Short Name]

**Hypothesis**: We believe that [doing this] for [these people] 
will achieve [outcome]. We'll know we're right when we see [signal].

**Why This Matters**: [Business model risk being tested]
**Risk Level**: 🔴 High | 🟡 Medium | 🟢 Low

**Experiment Design**:
- Type: [Interview | Survey | A/B Test | Landing Page | MVP]
- Duration: [X days/weeks]
- Resources: [Time, money, tools]

**Success Criteria**:
- Quantitative: [10% conversion, 20 signups, $500 revenue]
- Qualitative: [8/10 say "X"]
- Minimum Threshold: [What validates hypothesis?]

**Results**: [To be filled]
**Learning**: [What we learned]
**Decision**: [Pivot | Persevere | Iterate]
```

---

## Current Experiment Status

### Active Experiments
**Count**: 0  
**Focus Areas**: TBD

### Completed Experiments
**Count**: 0  
**Validated Hypotheses**: 0  
**Invalidated Hypotheses**: 0  
**Pivots Triggered**: 0

---

## Prioritizing Experiments

### Test Riskiest Assumptions First

**Categories of Risk** (in order of priority):

1. **🔴 Problem Risk**: Does the problem exist?
   - Test with: Customer interviews, surveys
   - Experiment: "10 problem validation interviews with Urban Commuters"

2. **🔴 Market Risk**: Is the market big enough?
   - Test with: Market research, TAM/SAM/SOM analysis
   - Experiment: "Quantify St. Louis market size for safety apps"

3. **🟡 Solution Risk**: Does our solution work?
   - Test with: Prototype tests, MVP, usability studies
   - Experiment: "Landing page test with map prototype"

4. **🟡 Customer Risk**: Will they adopt it?
   - Test with: Beta testing, activation metrics
   - Experiment: "Track activation rate for first 100 users"

5. **🟡 Price Risk**: Will they pay for it?
   - Test with: Pricing surveys, trial-to-paid conversion
   - Experiment: "Test $3.99 vs $4.99 vs $5.99 pricing"

6. **🟢 Scale Risk**: Can we grow efficiently?
   - Test with: Channel experiments, CAC analysis
   - Experiment: "Compare SEO vs. Reddit ads for user acquisition"

---

## Experiment Best Practices

### Do's
- ✅ **Test one variable at a time** (isolate what causes change)
- ✅ **Set clear success criteria upfront** (no moving goalposts)
- ✅ **Run experiments quickly** (fail fast, learn fast)
- ✅ **Document everything** (learnings compound over time)
- ✅ **Share results with team** (collective intelligence)
- ✅ **Act on results** (learning without action is waste)

### Don'ts
- ❌ **Don't test vanity metrics** (page views don't matter, actions do)
- ❌ **Don't run experiments without hypotheses** (random testing ≠ learning)
- ❌ **Don't ignore negative results** (invalidation is learning)
- ❌ **Don't cherry-pick data** (confirmation bias kills startups)
- ❌ **Don't run too many experiments** (focus on riskiest assumptions)

---

## Innovation Accounting

### Three Learning Milestones

#### 1. Establish Baseline (Current State)
**Goal**: Measure where you are today

**Metrics to Establish**:
- Acquisition: How many visitors/downloads per week?
- Activation: What % complete onboarding?
- Retention: What % return after 1 week?
- Revenue: What % convert to paid?
- Referral: What % invite friends?

**Example**: "5 signups/week, 20% activation, 10% D7 retention, 0% paid"

---

#### 2. Tune the Engine (Optimize Toward Ideal)
**Goal**: Small improvements through experimentation

**Activities**:
- A/B test onboarding flows
- Test different messaging
- Optimize pricing
- Improve core features

**Example**: "After 10 experiments, activation improved from 20% → 40%"

---

#### 3. Pivot or Persevere (Major Decision)
**Goal**: Decide if strategy is working

**Questions**:
- Are metrics moving toward targets?
- Is retention curve flattening (PMF signal)?
- Can we achieve unit economics (LTV > CAC)?
- Is growth sustainable?

**Decision Point**: March 2025 with 500 users

---

## Experiment Ideas (Backlog)

### Phase 1: Problem Validation (Now)
- [ ] Conduct 30 problem interviews with target segments
- [ ] Survey 100 St. Louis residents about safety concerns
- [ ] Analyze Google search volume for "St. Louis crime map"
- [ ] Test landing page headline variations

### Phase 2: Solution Validation (Next 3 Months)
- [ ] Prototype testing with 10 early adopters
- [ ] A/B test map visualization styles
- [ ] Test notification messaging variations
- [ ] Validate pricing with trial users

### Phase 3: Growth Experiments (3-6 Months)
- [ ] Compare acquisition channels (SEO, Reddit, Facebook)
- [ ] Test referral program incentives
- [ ] Experiment with onboarding flows
- [ ] Test different trial lengths (3-day vs 7-day)

### Phase 4: Retention Experiments (6+ Months)
- [ ] Test new features for engagement
- [ ] Experiment with push notification frequency
- [ ] A/B test premium feature set
- [ ] Test re-engagement campaigns for churned users

---

## Related Documentation

- [Customer Development](../customer-development/) - Interview findings feed experiment hypotheses
- [MVP Definition](../mvp/mvp-definition.md) - MVP is an experiment to test core value hypothesis
- [Metrics Dashboard](../metrics/key-metrics-dashboard.md) - Track experiment impact on key metrics
- [Process Flow Validation](../process-flow-validation.md) - System validation checkpoints

---

## Next Steps

### Immediate Actions (This Week)
1. Design first experiment: "Problem validation interviews with Urban Commuters"
2. Define hypothesis and success criteria
3. Create interview script
4. Recruit 5-10 participants

### Short-term Goals (Next Month)
- Run 3-5 experiments testing riskiest assumptions
- Document all results in experiment log
- Make first pivot/persevere decision based on findings
- Establish baseline metrics for innovation accounting

### Long-term Vision (6 Months)
- 50+ experiments run and documented
- Clear pattern of validated/invalidated hypotheses
- Data-driven product roadmap
- Systematic approach to learning and iteration
