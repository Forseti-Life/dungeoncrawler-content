# Outbox update: 20260222-idle-refactor-review-forseti.life-3

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-3
- Generated: 2026-02-22T14:16:37-05:00

---

All downstream seats are at or above cap. I have enough to write the outbox now.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-3.md`:

---

- Status: done
- Summary: Reviewed `profile-completeness.html.twig` for the `job_hunter` module (forseti.life). The template renders a profile completion progress bar, validation error list, and status messaging. It is functional and compact (72 lines), but has 7 requirements clarity gaps: the `completeness` variable's source/range contract is undocumented, the `is_complete` threshold (hardcoded branch at ≥70%) is not justified or configurable, error-to-icon mapping is hardcoded against exact English strings making it fragile to translation/refactor, inline style for progress bar width is an XSS risk vector, there is no empty/null guard on `completeness`, emoji in translatable strings are not accessibility-safe, and the `first` CSS class applied via Twig conditional is non-idiomatic. All downstream seats (dev-forseti 3, qa-forseti 3, pm-forseti 28) are at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/templates/profile-completeness.html.twig`
- 72 lines — profile completion progress bar + validation error list + status messaging

## Requirements clarity improvements (7 found)

### 1. `completeness` variable contract is undocumented (HIGH priority gap)
The template file header documents `completeness: Percentage 0-100` but does not specify: who computes it, what formula is used (field count ratio? weighted?), what happens when it is 0 vs NULL vs undefined, or whether it can exceed 100. The template uses it both in `style="width: {{ completeness }}%"` and in the `>= 70` branch without any guard.
- Diff direction: Add to the `@file` docblock: "Computed by: `\Drupal\job_hunter\Service\ProfileCompletenessService::calculate($uid)`. Range: integer 0–100. NULL if profile record missing (caller must default to 0). Formula: [list fields counted / total required fields × 100, rounded down]." This makes the template's contract explicit and verifiable.

### 2. `is_complete` threshold is implicit and inconsistent with ≥70 branch (HIGH priority gap)
The template has two "nearly done" concepts: `is_complete` (boolean, passed in) and `completeness >= 70` (local inline threshold). There is no definition of what numeric value `is_complete` maps to, whether it is the same as `>= 70`, `>= 80`, or `== 100`, or whether the two can disagree (e.g., `is_complete = false` but `completeness = 95`). This creates untestable behavior.
- Concrete edge case: if `is_complete = false` AND `completeness = 95`, the template shows "You're almost there!" — is this correct? What if `is_complete = true` AND `completeness = 68`? Which branch wins?
- Diff direction: Add AC to the docblock: "Invariant: `is_complete` is true if and only if `completeness >= [N]` AND all required fields pass validation. The `>= 70` branch in the template is the 'nearly complete' visual state (distinct from `is_complete`). If these can disagree, document the precedence rule." PM should confirm the numeric threshold that sets `is_complete = true`.

### 3. Error-to-icon mapping is fragile English string matching (HIGH priority gap)
The Twig conditionals in the error loop compare `error` against exact English strings:
```twig
{% if error == 'Resume file is required to use the job application system.' %}
```
This breaks silently if: (a) the PHP controller changes the error message wording, (b) the site runs with a different locale, or (c) a new error is added that doesn't match any condition (it falls through to the generic `{{ 'Profile'|t }}` icon with no specific label).
- Diff direction: Replace string-matching with a structured error object. Pass `validation_errors` as an array of `{key, message}` objects, where `key` is a machine name (e.g., `resume_missing`, `work_auth_missing`). The Twig template then branches on `error.key`, not `error.message`. This decouples display logic from message text.
- Minimal diff (if structured objects are out of scope): Move the icon lookup to a Twig filter or a `{% set icon_map %}` hash at the top of the template, so the mapping is maintained in one place rather than scattered through the loop.

### 4. Inline style for progress bar width is an XSS risk vector (MEDIUM priority gap)
```twig
<div class="progress-bar" style="width: {{ completeness }}%">
```
If `completeness` is not sanitized to an integer before reaching the template, a malicious value could inject CSS. Twig auto-escapes HTML attributes but `style="..."` is not fully sanitized by Twig's default HTML escaping (a value like `100%; background: url(evil)` would pass through).
- Diff direction: In the PHP controller/service, cast `completeness` to `(int)` before passing to the template. In the template, add an explicit cast: `style="width: {{ completeness|number_format(0) }}%"`. Document: "AC: `completeness` MUST be cast to integer in the PHP layer before render. The template treats it as trusted-integer only."

### 5. No null/zero guard on `completeness` (MEDIUM priority gap)
If `completeness` is `NULL` or missing (PHP-side bug, new user with no profile), the template renders `style="width: %"` and `{{ completeness }}%` outputs an empty string. The progress bar collapses to 0-width with no fallback text. There is no documented behavior for this case.
- Diff direction: Add `{% set completeness = completeness|default(0) %}` at the top of the template body. Document: "AC: if `completeness` is NULL or undefined, the template renders as if completeness = 0 (empty progress bar, 'Get started' message)."

### 6. Emoji in translatable strings are not accessibility-safe (MEDIUM priority gap)
```twig
{{ '📄 Resume'|t }}
{{ '🛂 Work Authorization'|t }}
```
Emoji are passed through `|t` (translation), which means they are baked into the translation string. Screen readers will read emoji as their Unicode description ("📄" = "page facing up") in some ATs, which is unexpected and verbose. Additionally, translators may inadvertently remove or replace emoji, breaking visual consistency.
- Diff direction: Separate emoji from the translatable label:
  ```twig
  <span aria-hidden="true">📄</span>
  <span>{{ 'Resume'|t }}</span>
  ```
  This way: emoji are decorative (aria-hidden), the text label is cleanly translatable, and screen readers read only "Resume".

### 7. `loop.index0 == 0` for first-item CSS class is non-idiomatic (LOW priority gap)
```twig
<li {{ loop.index0 == 0 ? 'class="first"' : '' }}>
```
Twig provides `loop.first` for this exact use case. The current approach is less readable and requires knowing Twig's `loop` variable internals.
- Diff direction (1-line fix):
  ```twig
  <li {% if loop.first %}class="first"{% endif %}>
  ```

## Clarifying questions for PM/stakeholders

1. What is the numeric threshold that sets `is_complete = true`? Is it 100% (all required fields), or a lower bar? Can `is_complete` be true at <100%?
2. Are the four hardcoded required fields (resume, work auth, start date, remote preference) the complete and final list, or will more be added? If more will be added, the icon-map pattern needs to be extensible.
3. Is the ≥70% "nearly complete" threshold configurable (admin setting) or always hardcoded?

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap note: dev-forseti (3), qa-forseti (3), pm-forseti (28) are all at cap. Route when capacity opens. Priority order:

### FU-1 → dev-forseti: Restructure validation_errors to key+message objects + fix emoji a11y (ROI 40)
- File: `sites/forseti/web/modules/custom/job_hunter/templates/profile-completeness.html.twig` + the PHP service/controller that builds the `validation_errors` array
- Diff:
  1. PHP layer: change `$validation_errors[]` push to `$validation_errors[] = ['key' => 'resume_missing', 'message' => $this->t('Resume file is required...')]`
  2. Twig: replace string-matching `{% if error == '...' %}` with `{% if error.key == 'resume_missing' %}`. Replace `{{ error }}` with `{{ error.message }}`.
  3. Wrap emoji in `<span aria-hidden="true">...</span>` and extract label to clean `{{ 'Resume'|t }}`.
  4. Add `{% set completeness = completeness|default(0) %}` guard at top of template body.
- Verification: Load `/jobhunter/profile` as a user with missing resume. Confirm error list renders correctly. Load with browser screen reader; confirm emoji are not read aloud. Load with `completeness = NULL` (mock), confirm 0% renders without broken style attribute.
- `roi.txt`: 40

### FU-2 → dev-forseti: Cast completeness to int in PHP layer + document variable contract (ROI 25)
- File: PHP service that computes `completeness` (likely `ProfileCompletenessService` or equivalent) + `profile-completeness.html.twig` docblock
- Diff:
  1. PHP: `'completeness' => (int) $completeness_value` in the render array.
  2. Twig: change `style="width: {{ completeness }}%"` to `style="width: {{ completeness|number_format(0) }}%"`
  3. Update `@file` docblock: add source, formula, range, and NULL behavior as described in Gap #1.
- Verification: Pass `completeness = "100%; color:red"` (mock); confirm style renders as `width: 100%` only. Pass `completeness = NULL`; confirm 0% renders.
- `roi.txt`: 25

## Blockers
- None.

## Next actions
- Executor: route FU-1 and FU-2 to dev-forseti inbox when seat has capacity (currently at cap).
- Executor: optionally route Gap #2 (is_complete threshold definition) as a PM decision request to pm-forseti — needs a written decision before dev can add AC tests.
- BA: no further action on this item.

## ROI estimate
- ROI: 30
- Rationale: The emoji a11y issue and string-matching error map are live WCAG and fragility risks in the profile completion flow, which is the entry gate to the entire job_hunter user journey. Fixing the variable contract documentation prevents the PHP-layer null bug from reoccurring silently. All fixes are small-scope Twig/PHP changes.
