# Lesson Learned: suggestion-triage.sh — RISK_SIGNALS bash variable not substituted in Python heredoc

- Date: 2026-04-05
- Identified by: pm-dungeoncrawler
- Affected scripts: `scripts/suggestion-triage.sh`
- Root cause type: Script bug — bash variable interpolation disabled in single-quoted heredoc

## What happened

Running `./scripts/suggestion-triage.sh dungeoncrawler 2 accept dc-home-suggestion-notice` succeeded in updating the Drupal node status to `in_progress` but then crashed with:

```
NameError: name 'RISK_SIGNALS' is not defined
```

The feature.md was NOT created. The Drupal node status was left as `in_progress` without a corresponding `features/dc-home-suggestion-notice/feature.md`.

## Root cause

In the `accept` branch of `suggestion-triage.sh`, the feature.md is written using a Python heredoc:

```bash
python3 - "$SUGGESTION_JSON" "$FEATURE_ID" "$SITE" "$NID" "$FEATURE_DIR" <<'PY'
...
- Intake risk signals: ${RISK_SIGNALS:-none}
...
PY
```

The heredoc delimiter is `<<'PY'` (single-quoted) which disables bash variable substitution. Inside the Python f-string, `${RISK_SIGNALS:-none}` is treated as Python code. Python f-strings interpret `{RISK_SIGNALS:-none}` as an expression, which fails because `RISK_SIGNALS` is not a defined Python variable and `:-none` is not valid Python.

## Workaround applied

PM created `features/dc-home-suggestion-notice/feature.md` manually with the correct content. QA handoff ran successfully via `pm-qa-handoff.sh`. Drupal node status is correct (in_progress).

## Recommended fix (for dev-infra/script owner)

Either:
1. Change the heredoc delimiter to `<<PY` (unquoted) so bash expands `${RISK_SIGNALS:-none}` before passing to Python, OR
2. Pass RISK_SIGNALS as a positional argument to the Python script (e.g., `sys.argv[6]`) and reference it as a Python variable.

Option 2 is cleaner:
```bash
python3 - "$SUGGESTION_JSON" "$FEATURE_ID" "$SITE" "$NID" "$FEATURE_DIR" "${RISK_SIGNALS:-none}" <<'PY'
...
risk_signals = sys.argv[6]
...
- Intake risk signals: {risk_signals}
...
PY
```

## Impact

- Any PM accepting a community suggestion will hit this crash and must manually create feature.md.
- Drupal node status IS updated correctly (the crash happens after the status update).
- Risk: inconsistent state — Drupal node shows `in_progress` but no feature.md exists.

## Owner for fix

`dev-infra` owns `scripts/`. Fix should be routed via passthrough request or direct dev-infra inbox item.
