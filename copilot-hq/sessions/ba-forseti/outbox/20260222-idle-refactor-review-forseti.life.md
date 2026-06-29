# Outbox update: 20260222-idle-refactor-review-forseti.life

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life
- Generated: 2026-02-22T13:44:25-05:00

---

Now I have everything I need. Let me write the outbox.

The executor writes the outbox. Here is the outbox content to be written to `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life.md`:

---

- Status: done
- Summary: Reviewed `CODE_REVIEW_queue-controls.css.md` for the `job_hunter` module (forseti.life). The existing code review identifies 6 CSS issues but lacks requirements-clarity elements that would make it actionable: no acceptance criteria per issue, no WCAG references for accessibility findings, undefined severity rubric, ambiguous fix descriptions ("subtle animation", "proper focus indicators"), no owner/sprint assignment, and no verification steps. Produced 8 concrete improvement recommendations below and queued 1 high-ROI follow-up for dev-forseti to implement the two accessibility fixes (est. ~15 lines of CSS).

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/css/CODE_REVIEW_queue-controls.css.md`
- Actual CSS: `queue-controls.css` (497 lines — queue management UI, status indicators, animation-heavy)

## Requirements clarity improvements (8 found)

### 1. Severity rubric missing (HIGH priority gap)
Issue labels "MEDIUM" and "LOW" are undefined. Two reviewers will assign different severities.
- Diff direction: Add rubric table at top: `HIGH = WCAG failure / data loss risk`, `MEDIUM = degraded UX / partial a11y fail`, `LOW = maintainability / code smell`.

### 2. No WCAG success criteria cited (HIGH priority gap)
Issues #1 (prefers-reduced-motion) and #3 (focus states) are accessibility findings with no WCAG references. "Fixed" is not verifiable without them.
- Diff direction: Issue #1 → cite WCAG 2.1 SC 2.3.3 (Animation from Interactions, AAA) and SC 2.3.1 (Three Flashes, AA). Issue #3 → cite SC 2.4.7 (Focus Visible, AA) and SC 1.4.11 (Non-text Contrast 3:1, AA).

### 3. No per-issue acceptance criteria (HIGH priority gap)
Each issue describes the problem and a vague fix but no testable "done" condition.
- Diff direction: Add "AC:" per issue. Example for #1: "AC: All animations wrapped in `@media (prefers-reduced-motion: reduce) { animation: none; transition: none; }`. Verified by enabling OS reduced-motion setting and confirming no animation plays."

### 4. "Subtle animation" is undefined (MEDIUM priority gap)
Issue #4 recommends "subtle animation" as the pulsing-dot fix but gives no specification (duration, scale, opacity delta).
- Diff direction: Specify: "Replace pulse with opacity fade: `opacity 1 → 0.6, 1.5s ease-in-out`. No movement/displacement. Verify against SC 2.3.1."

### 5. Grade "B" has no rubric (MEDIUM priority gap)
The overall grade is not reproducible — no scoring matrix defined.
- Diff direction: Either remove the grade (not actionable) or add a 5-dimension rubric (accessibility, performance, maintainability, responsive, semantics) each scored 1–5, with grade = mean.

### 6. Hardcoded color fix is underspecified (MEDIUM priority gap)
Issue #2 identifies 8+ repeated hex values but doesn't specify the mechanism: CSS custom properties, Drupal theme variables, or a preprocessor.
- Diff direction: Specify: "Extract to CSS custom properties in `queue-controls.css` `:root` block: `--jh-success: #48bb78; --jh-error: #f56565;` etc. Scoped to module only — no Sass dependency, no Drupal theme variable dependency."

### 7. No browser/AT scope statement (LOW priority gap)
The review doesn't state which browsers or assistive technologies were in scope. A fix verified in Chrome may fail in Firefox or with VoiceOver/NVDA.
- Diff direction: Add "Scope" section at top: "Browsers: Chrome 120+, Firefox 120+, Safari 17+. AT: NVDA + Firefox (Windows), VoiceOver + Safari (macOS/iOS)."

### 8. No owner, target sprint, or verification plan (LOW priority gap)
The review is an observation log, not an actionable work item — no assignee, no target, no steps to confirm fixes are complete.
- Diff direction: Add footer: "Owner: dev-forseti | Target: next sprint | Verification: run axe-core audit post-fix, confirm 0 a11y violations for listed issues."

## Follow-up items to queue

### FU-1 → dev-forseti: Implement a11y fixes in queue-controls.css (ROI 40)
- File: `sites/forseti/web/modules/custom/job_hunter/css/queue-controls.css`
- Diff direction:
  1. Wrap all `@keyframes` and `animation:` rules in `@media (prefers-reduced-motion: reduce) { animation: none; transition: none; }` — affects lines 21–27, 249–254, 289, 315–318.
  2. Add `:focus-visible` to `.btn-run-all`, `.btn-pause-all`, `.btn-clear-all`: `outline: 2px solid #63b3ed; outline-offset: 2px;` (meets 3:1 contrast ratio on dark `#1a365d` background).
- Estimated: ~15 lines of CSS added.
- Verification: (a) Enable OS reduced-motion, confirm zero animation on queue controls page. (b) Tab through queue controls, confirm visible focus ring on all buttons. (c) Run axe-core scan, confirm 0 new violations.
- `roi.txt`: 40

> Queue cap note: inbox currently has 2 remaining items after this one (-16, -9). Queuing 1 follow-up brings total to 3, at cap. Do not queue additional idle items until one completes.

## Blockers
- None.

## Next actions
- Executor: write FU-1 inbox item to `sessions/dev-forseti/inbox/` (or route via PM if seat not active).
- Executor: update `CODE_REVIEW_queue-controls.css.md` with WCAG refs + AC additions (out of BA scope; recommend dev-forseti or pm-forseti applies the patch).
- BA: no further action on this item.

## ROI estimate
- ROI: 35
- Rationale: Accessibility gaps in the queue controls UI carry WCAG AA compliance risk for forseti.life authenticated users. The two highest-priority fixes (reduced-motion, focus-visible) are ~15 lines of CSS and address concrete WCAG criteria. Requirements clarity improvements above prevent rework by removing ambiguous fix descriptions before dev starts.
