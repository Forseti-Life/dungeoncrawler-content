# Solution Validation

**Last Updated**: 2024-12-13  
**Status**: 🔴 Not Started

---

## Overview

Solution validation tests whether your proposed solution actually solves the customer's problem. This comes AFTER problem validation.

**Key Question**: Does our solution solve the problem well enough that customers will use it and pay for it?

---

## Solution Hypothesis

### Based on Problem Validation

**Problem We're Solving**:
> _[e.g., "Urban residents lack hyperlocal, real-time safety information when walking in unfamiliar areas"]_

**Our Solution**:
> _[e.g., "Interactive safety map with H3 hexagon risk visualization + background monitoring with proactive alerts"]_

**Why This Solution**:
- Reason 1: _[e.g., "Hyperlocal precision (700m hexagons) vs. city-wide statistics"]_
- Reason 2: _[e.g., "Proactive alerts vs. reactive research"]_
- Reason 3: _[e.g., "Statistical confidence (z-scores) vs. anecdotal data"]_

---

## Solution Validation Framework

### Stage 1: Concept Testing (No Product Yet)

**Goal**: Test if the concept resonates BEFORE building

**Methods**:
1. **Problem-Solution Interview**
2. **Landing Page Test**
3. **Paper Prototypes**
4. **Competitor Comparison**

### Stage 2: Prototype Testing (Minimal Product)

**Goal**: Test usability and value proposition

**Methods**:
1. **Clickable Prototype**
2. **Smoke Test**
3. **Concierge MVP**
4. **Wizard of Oz MVP**

### Stage 3: MVP Testing (Working Product)

**Goal**: Validate product-market fit

**Methods**:
1. **Private Beta**
2. **Cohort Analysis**
3. **Usage Metrics**
4. **Retention Tracking**

---

## Solution Interview Script

### Introduction (2 minutes)

> "Thanks for chatting with me again [or: for the first time]. Last time [or: In my research], I heard that [problem summary]. Today, I'd like to show you one approach to solving this and get your honest feedback.
>
> I'm not trying to sell you anything - I genuinely want to know if this makes sense or if I'm way off base. There are no right or wrong answers. Honest critique is the most helpful thing you can give me.
>
> Sound good?"

### Problem Recap (3 minutes)

_Confirm they still have the problem._

1. **"Just to make sure I understand correctly, you mentioned that [problem]. Is that still something you experience?"**

2. **"On a scale of 1-10, how painful is this problem for you?"**
   - Follow-up: "Why that number?"

3. **"What have you tried to solve this?"**
   - Current solutions and satisfaction

### Solution Introduction (5 minutes)

_Show them the solution (prototype, demo, mockup)._

**"Let me show you one way this could be solved."**

[Demo the solution]

**Key Points to Cover**:
- How it works (briefly)
- Key features
- How it's different from alternatives

**Then STOP and LISTEN.**

---

### Solution Validation Questions (10 minutes)

4. **"What's your first reaction?"**
   - Let them speak freely
   - Note: excitement, confusion, skepticism

5. **"If you had this right now, when would you use it?"**
   - Looking for specific use cases
   - Not hypothetical "I would use it to..."

6. **"What would make this valuable enough that you'd actually use it?"**
   - What features are must-haves?
   - What would make it better than what they do now?

7. **"What concerns do you have about using something like this?"**
   - Privacy? Complexity? Cost? Accuracy?

8. **"How would you describe this to a friend?"**
   - Tests if they understand the value prop
   - Reveals how they'd frame it

9. **"If this existed today, would you sign up for a beta test?"**
   - Tests genuine interest
   - If yes: Get their email (validation)
   - If no: "What would make you want to try it?"

10. **"Walk me through how you'd use this in a typical week."**
    - Understand frequency and context
    - Identify integration into their life

### Pricing Discovery (3 minutes)

_Only if they've expressed interest._

11. **"Compared to [current solution they mentioned], how much more valuable is this?"**
    - Set anchor to their current spending

12. **"At what price point would this be:"**
    - "A no-brainer, definitely worth it?" → [Too cheap]
    - "A bit expensive, but still worth it?" → [Acceptable]
    - "Too expensive, not worth it?" → [Too expensive]

### Wrap-Up (2 minutes)

13. **"What's missing that would make this a must-have for you?"**

14. **"Do you know anyone else who has this problem and might want to give feedback?"**
    - Get 2-3 referrals

> "Thanks so much for your honest feedback. This is incredibly helpful. I'll keep you posted on progress if you're interested!"

---

## Landing Page Test

### Purpose
Test demand before building the full product.

### Setup

**Create Simple Landing Page**:
1. **Headline**: Clear value proposition
2. **Subheadline**: Expand on the benefit
3. **3 Key Features**: How it works
4. **Social Proof**: Testimonials (if available)
5. **Call-to-Action**: "Join Beta" or "Get Early Access"

**Example**:

```
Headline: Know If You're Safe Before You Walk

Subheadline: Get real-time safety scores for any neighborhood 
in [City] with hyperlocal precision - 700m accuracy.

Features:
• Interactive crime map with color-coded risk levels
• Background monitoring with instant danger alerts  
• Statistical confidence scores, not guesswork

[Join the Beta] → Captures email
```

### Test Setup

**Traffic Sources**:
- Google Ads: $200-$500 budget
- Facebook/Instagram Ads: $200-$500 budget
- Reddit ads: $100-$200 budget
- Organic: Share in relevant communities

**Success Metrics**:
- Click-through rate (CTR): Target >2%
- Sign-up rate: Target >10% of visitors
- Total emails collected: Target >100

**Timeline**: Run for 1-2 weeks

### Analysis

| Metric | Target | Actual | Status |
|--------|--------|--------|--------|
| Visitors | 1,000+ | [#] | 🔴🟡🟢 |
| Sign-ups | 100+ (10%) | [#] | 🔴🟡🟢 |
| Cost per Sign-up | <$5 | $[X] | 🔴🟡🟢 |

**Learnings**:
- What messaging worked best?
- Which traffic source converted best?
- What questions did people ask?

---

## Prototype Testing

### Prototype Options

**Low Fidelity** (Quick & Cheap):
- Paper sketches
- Wireframes (Figma, Balsamiq)
- Clickable mockups

**Medium Fidelity**:
- Interactive prototype (InVision, Framer)
- Static version with fake data
- Video walkthrough

**High Fidelity**:
- Working MVP with real data
- Limited feature set
- Beta access

### Testing Protocol

**Session Format** (30-45 minutes):
1. **Welcome & Context** (5 min)
2. **Pre-Test Questions** (5 min) - Understand their current behavior
3. **Task-Based Testing** (20 min) - Give them specific tasks
4. **Post-Test Questions** (10 min) - Overall feedback
5. **Wrap-Up** (5 min) - Thank them, ask for referrals

### Task Examples

**Task 1**: "You're about to walk from [Point A] to [Point B]. Use this tool to decide if it's safe."

**Observe**:
- Can they figure out how to use it?
- Do they understand the hexagon colors?
- Do they trust the data?
- Does it influence their decision?

**Task 2**: "Set up the app so it alerts you if you enter a dangerous area."

**Observe**:
- Can they find the settings?
- Do they understand the options?
- What threshold do they choose?

**Task 3**: "You just got an alert. What do you do?"

**Observe**:
- Do they understand what triggered it?
- Is the information actionable?
- What would they do differently?

---

## Smoke Test (Test Demand Without Building)

### Example: "Ghost Button" Test

Add a button for a feature you haven't built yet:

```
[Premium Feature: Route Safety Planner] → Click
```

When clicked, show:
```
"We're building this feature! Join the waitlist to be notified when it launches."
[Email signup]
```

**Measure**:
- How many people click?
- How many provide email?
- Indicates demand for that feature

---

## Concierge MVP (Manual Service)

### Concept
Manually deliver the service to learn what customers value BEFORE automating.

### Example for Forseti

**Manual Version**:
1. Customer texts you: "I'm walking from X to Y, is it safe?"
2. You manually check crime data, calculate risk
3. You text back: "Route looks good, 2 recent incidents but low-risk area"

**What You Learn**:
- What information do they want?
- How detailed do they need it?
- How quickly do they need a response?
- What would make them trust the answer?

**Duration**: 2-4 weeks with 10-20 customers

**Then**: Automate based on learnings

---

## Wizard of Oz MVP (Fake Automation)

### Concept
Product appears automated to the user, but you're doing it manually behind the scenes.

### Example for Forseti

**User Experience**:
- User installs app
- App "automatically" sends safety alerts
- User thinks it's automated AI/algorithms

**Behind the Scenes**:
- You manually monitor their location
- You manually check crime data
- You manually send push notifications

**What You Learn**:
- Do they value the alerts?
- What threshold triggers action?
- Do they trust automated notifications?

**Then**: Build the real automation

---

## Success Criteria

### Validation Checklist

- [ ] **8 out of 10** users understand the value proposition immediately
- [ ] **7 out of 10** would use this over current alternatives
- [ ] **6 out of 10** express strong interest ("I need this!")
- [ ] **5 out of 10** ask how to get access / want to beta test
- [ ] **4 out of 10** willing to pay for it
- [ ] **Users can complete key tasks** without help (>80% success rate)
- [ ] **No major usability blockers** (nothing confusing >30% of users)

### Red Flags 🚩

Signs the solution might not be right:

- Users confused about what it does
- Users say "nice to have" but not "must have"
- Users revert to old solution after trying yours
- Feature requests completely change the product
- No one asks how to get access

---

## Decision Framework

### After Solution Validation

**Status**: [Not Started | In Progress | Complete]

**Results**:
- Interviews conducted: [#]
- Positive feedback: [%]
- Beta signups: [#]
- Usability issues found: [#]

**Decision**:

✅ **VALIDATED** - Build MVP
- Criteria: >70% positive feedback, strong interest, clear use cases
- Next: Proceed to MVP development

🔄 **ITERATE** - Adjust solution
- Criteria: 50-70% positive, some concerns but fixable
- Next: Revise based on feedback, test again

🔀 **PIVOT** - Major change needed
- Criteria: <50% positive, fundamental issues
- Next: Rethink solution approach

❌ **STOP** - Solution doesn't work
- Criteria: Strong rejection, better alternatives exist
- Next: Return to problem validation or stop project

---

## Documentation

### For Each Solution Test

**Test ID**: [Number]  
**Date**: [YYYY-MM-DD]  
**Type**: [Interview | Prototype | Landing Page | etc.]  
**Participants**: [#]

**What We Tested**:
- [Feature/concept]

**What We Learned**:
1. [Learning 1]
2. [Learning 2]

**What Changed**:
- [Adjustment made based on feedback]

**Next Steps**:
- [ ] [Action item 1]
- [ ] [Action item 2]

---

## Related Documents

- **Problem Validation**: `problem-validation.md`
- **Customer Interviews**: `customer-interviews-log.md`
- **Experiment Log**: `docs/product/experiments/experiment-log.md`
- **MVP Definition**: `docs/product/mvp/mvp-definition.md`

---

## Resources

- **The Mom Test** - Rob Fitzpatrick (Testing solutions without lying)
- **Testing Business Ideas** - David Bland (Experiment library)
- **Sprint** - Jake Knapp (5-day prototype testing process)
- **Lean UX** - Jeff Gothelf (UX in Lean Startup)

