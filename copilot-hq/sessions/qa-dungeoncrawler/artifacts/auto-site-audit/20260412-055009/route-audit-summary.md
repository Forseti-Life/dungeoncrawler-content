# Route audit summary

- Base URL: https://dungeoncrawler.forseti.life
- Routes checked: 80

## Admin routes returning 200 (potential ACL bug)
- None

## API routes with errors (>=400)
- None

## Other non-admin route errors (>=400)
| Status | Module | Route | Path | URL |
|---:|---|---|---|---|
| 403 | ai_conversation | ai_conversation.get_stats | `/ai-conversation/stats` | https://dungeoncrawler.forseti.life/ai-conversation/stats |
| 403 | ai_conversation | ai_conversation.send_message | `/ai-conversation/send-message` | https://dungeoncrawler.forseti.life/ai-conversation/send-message |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_archive | `/campaigns/{campaign_id}/archive` | https://dungeoncrawler.forseti.life/campaigns/1/archive |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_create | `/campaigns/create` | https://dungeoncrawler.forseti.life/campaigns/create |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_delete | `/campaigns/{campaign_id}/delete` | https://dungeoncrawler.forseti.life/campaigns/1/delete |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_dungeons | `/campaigns/{campaign_id}/dungeons` | https://dungeoncrawler.forseti.life/campaigns/1/dungeons |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_select_character | `/campaigns/{campaign_id}/select-character/{character_id}` | https://dungeoncrawler.forseti.life/campaigns/1/select-character/1 |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_tavernentrance | `/campaigns/{campaign_id}/tavernentrance` | https://dungeoncrawler.forseti.life/campaigns/1/tavernentrance |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaign_unarchive | `/campaigns/{campaign_id}/unarchive` | https://dungeoncrawler.forseti.life/campaigns/1/unarchive |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaigns | `/campaigns` | https://dungeoncrawler.forseti.life/campaigns |
| 403 | dungeoncrawler_content | dungeoncrawler_content.campaigns_archived | `/campaigns/archived` | https://dungeoncrawler.forseti.life/campaigns/archived |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_archive | `/characters/{character_id}/archive` | https://dungeoncrawler.forseti.life/characters/1/archive |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_creation_wizard | `/characters/create` | https://dungeoncrawler.forseti.life/characters/create |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_delete | `/characters/{character_id}/delete` | https://dungeoncrawler.forseti.life/characters/1/delete |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_edit | `/characters/{character_id}/edit` | https://dungeoncrawler.forseti.life/characters/1/edit |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_step | `/characters/create/step/{step}` | https://dungeoncrawler.forseti.life/characters/create/step/1 |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_view | `/characters/{character_id}` | https://dungeoncrawler.forseti.life/characters/1 |
| 403 | dungeoncrawler_content | dungeoncrawler_content.character_view_embed | `/characters/{character_id}/embed` | https://dungeoncrawler.forseti.life/characters/1/embed |
| 403 | dungeoncrawler_content | dungeoncrawler_content.characters | `/campaigns/{campaign_id}/characters` | https://dungeoncrawler.forseti.life/campaigns/1/characters |
| 403 | dungeoncrawler_content | dungeoncrawler_content.game_objects | `/dungeoncrawler/objects` | https://dungeoncrawler.forseti.life/dungeoncrawler/objects |
| 403 | dungeoncrawler_content | dungeoncrawler_content.traits.catalog | `/dungeoncrawler/traits` | https://dungeoncrawler.forseti.life/dungeoncrawler/traits |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.dashboard | `/dungeoncrawler/testing` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_execution_playbook | `/dungeoncrawler/testing/documentation/execution-playbook` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/execution-playbook |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_failure_triage | `/dungeoncrawler/testing/documentation/failure-triage` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/failure-triage |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_getting_started | `/dungeoncrawler/testing/documentation/getting-started` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/getting-started |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_issue_automation | `/dungeoncrawler/testing/documentation/issue-automation` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/issue-automation |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_issues_directory | `/dungeoncrawler/testing/documentation/issues-directory` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/issues-directory |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_module_readme | `/dungeoncrawler/testing/documentation/module-readme` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/module-readme |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_process_flow | `/dungeoncrawler/testing/documentation/process-flow` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/process-flow |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_quick_start | `/dungeoncrawler/testing/documentation/quick-start` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/quick-start |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_release_process_flow | `/dungeoncrawler/testing/documentation/release-process-flow` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/release-process-flow |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_sdlc_process_flow | `/dungeoncrawler/testing/documentation/sdlc-process-flow` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/sdlc-process-flow |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_strategy_design | `/dungeoncrawler/testing/documentation/strategy-design` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/strategy-design |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_testing_module_readme | `/dungeoncrawler/testing/documentation/testing-module-readme` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/testing-module-readme |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.docs_tests_readme | `/dungeoncrawler/testing/documentation/tests-readme` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation/tests-readme |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.documentation_home | `/dungeoncrawler/testing/documentation-home` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/documentation-home |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.import_open_issues | `/dungeoncrawler/testing/import-open-issues` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.import_open_issues_reconcile_logs | `/dungeoncrawler/testing/import-open-issues/reconcile/logs` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues/reconcile/logs |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.import_open_issues_reconcile_status | `/dungeoncrawler/testing/import-open-issues/reconcile/status` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues/reconcile/status |
| 403 | dungeoncrawler_tester | dungeoncrawler_tester.issue_pr_report | `/dungeoncrawler/testing/import-open-issues/issue-pr-report` | https://dungeoncrawler.forseti.life/dungeoncrawler/testing/import-open-issues/issue-pr-report |

(Truncated: 43 rows)
