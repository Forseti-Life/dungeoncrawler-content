# Route audit summary

- Base URL: http://localhost:8080
- Routes checked: 85

## Admin routes returning 200 (potential ACL bug)
- None

## API routes with errors (>=400)
- None

## Other non-admin route errors (>=400)
| Status | Module | Route | Path | URL |
|---:|---|---|---|---|
| 403 | ai_conversation | ai_conversation.get_stats | `/ai-conversation/stats` | http://localhost:8080/ai-conversation/stats |
| 403 | ai_conversation | ai_conversation.send_message | `/ai-conversation/send-message` | http://localhost:8080/ai-conversation/send-message |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_archive | `/campaigns/{campaign_id}/archive` | http://localhost:8080/campaigns/1/archive |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_create | `/campaigns/create` | http://localhost:8080/campaigns/create |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_delete | `/campaigns/{campaign_id}/delete` | http://localhost:8080/campaigns/1/delete |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_dungeons | `/campaigns/{campaign_id}/dungeons` | http://localhost:8080/campaigns/1/dungeons |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_select_character | `/campaigns/{campaign_id}/select-character/{character_id}` | http://localhost:8080/campaigns/1/select-character/1 |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_tavernentrance | `/campaigns/{campaign_id}/tavernentrance` | http://localhost:8080/campaigns/1/tavernentrance |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_unarchive | `/campaigns/{campaign_id}/unarchive` | http://localhost:8080/campaigns/1/unarchive |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaigns | `/campaigns` | http://localhost:8080/campaigns |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaigns_archived | `/campaigns/archived` | http://localhost:8080/campaigns/archived |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_archive | `/characters/{character_id}/archive` | http://localhost:8080/characters/1/archive |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_creation_wizard | `/characters/create` | http://localhost:8080/characters/create |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_delete | `/characters/{character_id}/delete` | http://localhost:8080/characters/1/delete |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_edit | `/characters/{character_id}/edit` | http://localhost:8080/characters/1/edit |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_step | `/characters/create/step/{step}` | http://localhost:8080/characters/create/step/1 |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_view | `/characters/{character_id}` | http://localhost:8080/characters/1 |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_view_embed | `/characters/{character_id}/embed` | http://localhost:8080/characters/1/embed |
| 403 | dungeoncrawler_content | dungeoncrawler_content.characters | `/campaigns/{campaign_id}/characters` | http://localhost:8080/campaigns/1/characters |
| 403 | dungeoncrawler_content | dungeoncrawler_content.game_objects | `/dungeoncrawler/objects` | http://localhost:8080/dungeoncrawler/objects |
| 403 | dungeoncrawler_content | dungeoncrawler_content.traits.catalog | `/dungeoncrawler/traits` | http://localhost:8080/dungeoncrawler/traits |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.dashboard | `/dungeoncrawler/testing` | http://localhost:8080/dungeoncrawler/testing |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_execution_playbook | `/dungeoncrawler/testing/documentation/execution-playbook` | http://localhost:8080/dungeoncrawler/testing/documentation/execution-playbook |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_failure_triage | `/dungeoncrawler/testing/documentation/failure-triage` | http://localhost:8080/dungeoncrawler/testing/documentation/failure-triage |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_getting_started | `/dungeoncrawler/testing/documentation/getting-started` | http://localhost:8080/dungeoncrawler/testing/documentation/getting-started |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_issue_automation | `/dungeoncrawler/testing/documentation/issue-automation` | http://localhost:8080/dungeoncrawler/testing/documentation/issue-automation |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_issues_directory | `/dungeoncrawler/testing/documentation/issues-directory` | http://localhost:8080/dungeoncrawler/testing/documentation/issues-directory |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_module_readme | `/dungeoncrawler/testing/documentation/module-readme` | http://localhost:8080/dungeoncrawler/testing/documentation/module-readme |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_process_flow | `/dungeoncrawler/testing/documentation/process-flow` | http://localhost:8080/dungeoncrawler/testing/documentation/process-flow |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_quick_start | `/dungeoncrawler/testing/documentation/quick-start` | http://localhost:8080/dungeoncrawler/testing/documentation/quick-start |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_release_process_flow | `/dungeoncrawler/testing/documentation/release-process-flow` | http://localhost:8080/dungeoncrawler/testing/documentation/release-process-flow |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_sdlc_process_flow | `/dungeoncrawler/testing/documentation/sdlc-process-flow` | http://localhost:8080/dungeoncrawler/testing/documentation/sdlc-process-flow |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_strategy_design | `/dungeoncrawler/testing/documentation/strategy-design` | http://localhost:8080/dungeoncrawler/testing/documentation/strategy-design |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_testing_module_readme | `/dungeoncrawler/testing/documentation/testing-module-readme` | http://localhost:8080/dungeoncrawler/testing/documentation/testing-module-readme |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_tests_readme | `/dungeoncrawler/testing/documentation/tests-readme` | http://localhost:8080/dungeoncrawler/testing/documentation/tests-readme |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.documentation_home | `/dungeoncrawler/testing/documentation-home` | http://localhost:8080/dungeoncrawler/testing/documentation-home |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.import_open_issues | `/dungeoncrawler/testing/import-open-issues` | http://localhost:8080/dungeoncrawler/testing/import-open-issues |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.import_open_issues_reconcile_logs | `/dungeoncrawler/testing/import-open-issues/reconcile/logs` | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/logs |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.import_open_issues_reconcile_status | `/dungeoncrawler/testing/import-open-issues/reconcile/status` | http://localhost:8080/dungeoncrawler/testing/import-open-issues/reconcile/status |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.issue_pr_report | `/dungeoncrawler/testing/import-open-issues/issue-pr-report` | http://localhost:8080/dungeoncrawler/testing/import-open-issues/issue-pr-report |

(Truncated: 43 rows)
