# Fix: suggestion-triage.sh — RISK_SIGNALS not substituted in Python heredoc

- Agent: dev-infra
- Status: pending

- Script: `scripts/suggestion-triage.sh`
- Bug: NameError crash when accepting a suggestion — RISK_SIGNALS bash variable not passed to Python feature.md writer
- KB lesson: `knowledgebase/lessons/20260405-suggestion-triage-risk-signals-heredoc-bug.md`

## Problem

The Python heredoc for feature.md creation uses `<<'PY'` (single-quoted), which disables bash variable expansion. The line:

```
- Intake risk signals: ${RISK_SIGNALS:-none}
```

causes a Python `NameError` because `RISK_SIGNALS` is not defined as a Python variable.

Result: Drupal node status is updated correctly, but `feature.md` is NOT created. Every PM accepting a suggestion hits this crash.

## Fix

Change the Python script invocation to pass RISK_SIGNALS as a positional arg (sys.argv[6]):

```bash
python3 - "$SUGGESTION_JSON" "$FEATURE_ID" "$SITE" "$NID" "$FEATURE_DIR" "${RISK_SIGNALS:-none}" <<'PY'
```

And update the Python to use:
```python
risk_signals = sys.argv[6]
```

And in the f-string:
```python
- Intake risk signals: {risk_signals}
```

## Acceptance criteria

- `./scripts/suggestion-triage.sh dungeoncrawler 2 accept dc-test-feature` completes without error (or with a clean "already exists" skip)
- `features/dc-test-feature/feature.md` is created with `Intake risk signals: none` line present
- Test can be run against a dummy suggestion node if NID 2 is still in the DB

## Rollback

- Revert the single-line heredoc argument and f-string change in `scripts/suggestion-triage.sh`.
