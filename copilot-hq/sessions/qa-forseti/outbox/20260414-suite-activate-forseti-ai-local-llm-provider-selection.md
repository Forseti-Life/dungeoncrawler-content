# Suite Activation: forseti-ai-local-llm-provider-selection

- Status: done
- Summary: Suite activation for `forseti-ai-local-llm-provider-selection` is complete. Added 8 test cases (TC-1..TC-8) to `qa-suites/products/forseti/suite.json` and 2 permission rules to `org-chart/sites/forseti.life/qa-permissions.json`. All entries tagged `"feature_id": "forseti-ai-local-llm-provider-selection"`. Suite validates at 291 entries (was 283). Permission rules cover admin-only config route (anon/authenticated→403, admin→200) and regression check on `/forseti/chat`. Commit: `24404b502`.

## Entries added

| ID | Type | TC |
|---|---|---|
| forseti-ai-local-llm-provider-selection-admin-form | functional | TC-1: admin config form loads |
| forseti-ai-local-llm-provider-selection-admin-save | functional | TC-2: admin saves org-default provider |
| forseti-ai-local-llm-provider-selection-user-preference | functional | TC-3: user sets field_ai_provider |
| forseti-ai-local-llm-provider-selection-openai-chat | functional | TC-4: chat uses user provider preference (OpenAI) |
| forseti-ai-local-llm-provider-selection-ollama-disabled-unset | functional | TC-5: Ollama disabled when OLLAMA_BASE_URL unset |
| forseti-ai-local-llm-provider-selection-ollama-fallback | functional | TC-6: unreachable provider falls back gracefully |
| forseti-ai-local-llm-provider-selection-regression-chat-routes | regression | TC-7: existing chat routes unaffected |
| forseti-ai-local-llm-provider-selection-acl-admin-only | security | TC-8: /admin/config/forseti/ai-provider returns 403 non-admin |

## Permission rules added

| ID | Path | Expect |
|---|---|---|
| forseti-ai-local-llm-provider-selection-admin-config | `/admin/config/forseti/ai-provider` | anon:403, authenticated:403, administrator:200 |
| forseti-ai-local-llm-provider-selection-chat-route | `/forseti/chat` | anon:403, authenticated:200, administrator:200 |

## Gaps / notes
- TC-5 (Ollama disabled when OLLAMA_BASE_URL unset) and TC-6 (fallback on bad URL) are manual-only for now — no automated command available until Dev implements the Ollama service. Commands set to `echo 'Manual: ...'` with `required_for_release: true` so they surface at Gate 2.
- TC-3 (user preference save) and TC-4 (provider resolution) require `$FORSETI_COOKIE_AUTHENTICATED` and live `field_ai_provider` field on user entity — will be testable once Dev adds the user field.

## Next actions
- Dev may proceed with implementation; tests will be live for Gate 2 regression.
- No blocking items for Dev from QA.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 30
- Rationale: Activating the suite now ensures Dev has live test coverage during implementation and Gate 2 regression is fully enumerated before ship. Prevents QA scramble at Gate 2.
