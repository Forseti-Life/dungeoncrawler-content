# Configuration Differences Inventory - Forseti Production vs Development

## PRODUCTION-ONLY FILES (103 total)

### Backup & Migrate Module (5 files) - ✅ EXPECTED
Only in ../../prod-config: backup_migrate.backup_migrate_destination.private_files.yml
Only in ../../prod-config: backup_migrate.backup_migrate_schedule.daily_schedule.yml
Only in ../../prod-config: backup_migrate.backup_migrate_source.default_db.yml
Only in ../../prod-config: backup_migrate.backup_migrate_source.entire_site.yml
Only in ../../prod-config: backup_migrate.backup_migrate_source.private_files.yml
Only in ../../prod-config: backup_migrate.backup_migrate_source.public_files.yml

**Decision:** Keep in production only (expected difference)
**Reason:** Scheduled backups only needed in production


### Admin Toolbar (2 files) - ✅ EXPECTED  
Only in ../../prod-config: admin_toolbar.settings.yml
Only in ../../prod-config: admin_toolbar_tools.settings.yml

**Decision:** Should sync to dev
**Reason:** Admin toolbar should be consistent


### Block Configurations (Production has different blocks)
Only in ../../prod-config: block.block.forseti_sitebranding.yml
Only in ../../prod-config: block.block.forseti_socialauthlogin_2.yml
Only in ../../prod-config: block.block.forseti_useraccountmenu.yml

**Decision:** Need to sync to dev
**Reason:** Production blocks are the live configuration


### Core Entity/View Modes (Token module views)
Only in ../../prod-config: core.entity_view_mode.block_content.token.yml
Only in ../../prod-config: core.entity_view_mode.block.token.yml
Only in ../../prod-config: core.entity_view_mode.comment.token.yml
Only in ../../prod-config: core.entity_view_mode.file.token.yml
Only in ../../prod-config: core.entity_view_mode.menu_link_content.token.yml
Only in ../../prod-config: core.entity_view_mode.node.token.yml
Only in ../../prod-config: core.entity_view_mode.path_alias.token.yml
Only in ../../prod-config: core.entity_view_mode.shortcut.token.yml
Only in ../../prod-config: core.entity_view_mode.taxonomy_term.token.yml
Only in ../../prod-config: core.entity_view_mode.user.token.yml

**Decision:** Should sync to dev
**Reason:** Token module configurations should be consistent


### All Other Production-Only Files:
Only in ../../prod-config: agent_evaluation.settings.yml
Only in ../../prod-config: core.entity_form_display.node.evaluated_entity.default.yml
Only in ../../prod-config: editor.editor.webform_default.yml
Only in ../../prod-config: field.field.node.evaluated_entity.field_entity_category.yml
Only in ../../prod-config: field.storage.node.field_entity_category.yml
Only in ../../prod-config: filter.format.webform_default.yml
Only in ../../prod-config: google_tag.container.default.yml
Only in ../../prod-config: google_tag.container.G-41QXRELBPB.69650668a77d32.48447702.yml
Only in ../../prod-config: google_tag.settings.yml
Only in ../../prod-config: metatag.metatag_defaults.403.yml
Only in ../../prod-config: metatag.metatag_defaults.404.yml
Only in ../../prod-config: metatag.metatag_defaults.front.yml
Only in ../../prod-config: metatag.metatag_defaults.global.yml
Only in ../../prod-config: metatag.metatag_defaults.node.yml
Only in ../../prod-config: metatag.metatag_defaults.taxonomy_term.yml
Only in ../../prod-config: metatag.metatag_defaults.user.yml
Only in ../../prod-config: metatag.settings.yml
Only in ../../prod-config: pathauto.settings.yml
Only in ../../prod-config: social_auth_google.settings.yml
Only in ../../prod-config: social_auth.settings.yml
Only in ../../prod-config: system.action.pathauto_update_alias_node.yml
Only in ../../prod-config: system.action.pathauto_update_alias_user.yml
Only in ../../prod-config: system.action.user_add_role_action.fire_dept_admin.yml
Only in ../../prod-config: system.action.user_add_role_action.firefighter.yml
Only in ../../prod-config: system.action.user_add_role_action.nfr_administrator.yml
Only in ../../prod-config: system.action.user_add_role_action.nfr_researcher.yml
Only in ../../prod-config: system.action.user_remove_role_action.fire_dept_admin.yml
Only in ../../prod-config: system.action.user_remove_role_action.firefighter.yml
Only in ../../prod-config: system.action.user_remove_role_action.nfr_administrator.yml
Only in ../../prod-config: system.action.user_remove_role_action.nfr_researcher.yml
Only in ../../prod-config: system.action.webform_archive_action.yml
Only in ../../prod-config: system.action.webform_close_action.yml
Only in ../../prod-config: system.action.webform_delete_action.yml
Only in ../../prod-config: system.action.webform_open_action.yml
Only in ../../prod-config: system.action.webform_submission_delete_action.yml
Only in ../../prod-config: system.action.webform_submission_make_lock_action.yml
Only in ../../prod-config: system.action.webform_submission_make_sticky_action.yml
Only in ../../prod-config: system.action.webform_submission_make_unlock_action.yml
Only in ../../prod-config: system.action.webform_submission_make_unsticky_action.yml
Only in ../../prod-config: system.action.webform_unarchive_action.yml
Only in ../../prod-config: user.role.fire_dept_admin.yml
Only in ../../prod-config: user.role.firefighter.yml
Only in ../../prod-config: user.role.nfr_administrator.yml
Only in ../../prod-config: user.role.nfr_researcher.yml
Only in ../../prod-config: views.view.social_auth_profiles.yml
Only in ../../prod-config: views.view.webform_submissions.yml
Only in ../../prod-config: webform.settings.yml
Only in ../../prod-config: webform.webform.contact_forseti.yml
Only in ../../prod-config: webform.webform.contact.yml
Only in ../../prod-config: webform.webform_options.country_codes.yml
Only in ../../prod-config: webform.webform_options.country_names.yml
Only in ../../prod-config: webform.webform_options.days.yml
Only in ../../prod-config: webform.webform_options.education.yml
Only in ../../prod-config: webform.webform_options.employment_status.yml
Only in ../../prod-config: webform.webform_options.ethnicity.yml
Only in ../../prod-config: webform.webform_options.gender.yml
Only in ../../prod-config: webform.webform_options.industry.yml
Only in ../../prod-config: webform.webform_options.languages.yml
Only in ../../prod-config: webform.webform_options.likert_agreement.yml
Only in ../../prod-config: webform.webform_options.likert_comparison.yml
Only in ../../prod-config: webform.webform_options.likert_importance.yml
Only in ../../prod-config: webform.webform_options.likert_quality.yml
Only in ../../prod-config: webform.webform_options.likert_satisfaction.yml
Only in ../../prod-config: webform.webform_options.likert_ten_scale.yml
Only in ../../prod-config: webform.webform_options.likert_would_you.yml
Only in ../../prod-config: webform.webform_options.marital_status.yml
Only in ../../prod-config: webform.webform_options.months.yml
Only in ../../prod-config: webform.webform_options.phone_types.yml
Only in ../../prod-config: webform.webform_options.province_codes.yml
Only in ../../prod-config: webform.webform_options.province_names.yml
Only in ../../prod-config: webform.webform_options.relationship.yml
Only in ../../prod-config: webform.webform_options.sex_icao.yml
Only in ../../prod-config: webform.webform_options.sex.yml
Only in ../../prod-config: webform.webform_options.size.yml
Only in ../../prod-config: webform.webform_options.state_codes.yml
Only in ../../prod-config: webform.webform_options.state_names.yml
Only in ../../prod-config: webform.webform_options.state_province_codes.yml
Only in ../../prod-config: webform.webform_options.state_province_names.yml
Only in ../../prod-config: webform.webform_options.time_zones.yml
Only in ../../prod-config: webform.webform_options.titles.yml
Only in ../../prod-config: webform.webform_options.translations.yml
Only in ../../prod-config: webform.webform_options.yes_no.yml


---

## DEVELOPMENT-ONLY FILES (40 total)

### Radix Theme Blocks (20 files) - ⚠️ INVESTIGATE
Only in ../../sites/forseti/config/sync: block.block.forseti_branding.yml
Only in ../../sites/forseti/config/sync: block.block.forseti_breadcrumbs.yml
Only in ../../sites/forseti/config/sync: block.block.forseti_content.yml
Only in ../../sites/forseti/config/sync: block.block.forseti_footer.yml
Only in ../../sites/forseti/config/sync: block.block.forseti_messages.yml
Only in ../../sites/forseti/config/sync: block.block.radix_account_menu.yml
Only in ../../sites/forseti/config/sync: block.block.radix_breadcrumbs.yml
Only in ../../sites/forseti/config/sync: block.block.radix_content.yml
Only in ../../sites/forseti/config/sync: block.block.radix_forseti_forsetifootermenu.yml
Only in ../../sites/forseti/config/sync: block.block.radix_help.yml
Only in ../../sites/forseti/config/sync: block.block.radix_main_menu.yml
Only in ../../sites/forseti/config/sync: block.block.radix_messages.yml
Only in ../../sites/forseti/config/sync: block.block.radix_page_title.yml
Only in ../../sites/forseti/config/sync: block.block.radix_powered.yml
Only in ../../sites/forseti/config/sync: block.block.radix_primary_admin_actions.yml
Only in ../../sites/forseti/config/sync: block.block.radix_primary_local_tasks.yml
Only in ../../sites/forseti/config/sync: block.block.radix_search_form_narrow.yml
Only in ../../sites/forseti/config/sync: block.block.radix_search_form_wide.yml
Only in ../../sites/forseti/config/sync: block.block.radix_secondary_local_tasks.yml
Only in ../../sites/forseti/config/sync: block.block.radix_site_branding.yml

**Question:** Are these blocks actually in use on production?
**Decision Options:**
- A) Delete from dev (prod doesn't have them, they're unused)
- B) Add to prod (dev has new blocks that should go live)


### Contact Module (3 files) - ⚠️ INVESTIGATE
Only in ../../sites/forseti/config/sync: contact.form.feedback.yml
Only in ../../sites/forseti/config/sync: contact.form.personal.yml
Only in ../../sites/forseti/config/sync: contact.settings.yml

**Question:** Is Contact module installed in production?
**Decision Options:**
- A) Delete from dev (contact module not used)
- B) Install contact module in prod and sync these


### Group Module Configs (13 files) - ⚠️ INVESTIGATE
Only in ../../sites/forseti/config/sync: core.entity_form_display.group_relationship.family-group_membership.default.yml
Only in ../../sites/forseti/config/sync: core.entity_form_display.group_relationship.institution-group_membership.default.yml
Only in ../../sites/forseti/config/sync: core.entity_view_display.group_relationship.family-group_membership.default.yml
Only in ../../sites/forseti/config/sync: core.entity_view_display.group_relationship.institution-group_membership.default.yml
Only in ../../sites/forseti/config/sync: field.field.group_relationship.family-group_membership.group_roles.yml
Only in ../../sites/forseti/config/sync: field.field.group_relationship.institution-group_membership.group_roles.yml
Only in ../../sites/forseti/config/sync: field.storage.group_relationship.group_roles.yml
Only in ../../sites/forseti/config/sync: group.relationship_type.family-group_membership.yml
Only in ../../sites/forseti/config/sync: group.relationship_type.institution-group_membership.yml
Only in ../../sites/forseti/config/sync: group.role.family-family-admin.yml
Only in ../../sites/forseti/config/sync: group.role.family-family-member.yml
Only in ../../sites/forseti/config/sync: group.role.institution-institution-admin.yml
Only in ../../sites/forseti/config/sync: group.role.institution-institution-member.yml
Only in ../../sites/forseti/config/sync: group.settings.yml
Only in ../../sites/forseti/config/sync: group.type.family.yml
Only in ../../sites/forseti/config/sync: group.type.institution.yml
Only in ../../sites/forseti/config/sync: views.view.group_members.yml

**Question:** Is Group module (families/institutions) actually in production?
**Decision Options:**
- A) Delete from dev (group module not installed in prod)
- B) Install group module in prod with these configs


### Other Dev-Only Files:


---

## MODIFIED FILES (317 total) - Files exist in both but differ

### Sample of Modified Files:
Files ../../sites/forseti/config/sync/ai_conversation.settings.yml and ../../prod-config/ai_conversation.settings.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_breadcrumbs.yml and ../../prod-config/block.block.claro_breadcrumbs.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_content.yml and ../../prod-config/block.block.claro_content.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_help_search.yml and ../../prod-config/block.block.claro_help_search.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_help.yml and ../../prod-config/block.block.claro_help.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_local_actions.yml and ../../prod-config/block.block.claro_local_actions.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_messages.yml and ../../prod-config/block.block.claro_messages.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_page_title.yml and ../../prod-config/block.block.claro_page_title.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_primary_local_tasks.yml and ../../prod-config/block.block.claro_primary_local_tasks.yml differ
Files ../../sites/forseti/config/sync/block.block.claro_secondary_local_tasks.yml and ../../prod-config/block.block.claro_secondary_local_tasks.yml differ
Files ../../sites/forseti/config/sync/block.block.forseti_forsetifootermenu.yml and ../../prod-config/block.block.forseti_forsetifootermenu.yml differ
Files ../../sites/forseti/config/sync/block.block.forseti_local_actions.yml and ../../prod-config/block.block.forseti_local_actions.yml differ
Files ../../sites/forseti/config/sync/block.block.forseti_main_menu.yml and ../../prod-config/block.block.forseti_main_menu.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_account_menu.yml and ../../prod-config/block.block.olivero_account_menu.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_breadcrumbs.yml and ../../prod-config/block.block.olivero_breadcrumbs.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_content.yml and ../../prod-config/block.block.olivero_content.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_help.yml and ../../prod-config/block.block.olivero_help.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_main_menu.yml and ../../prod-config/block.block.olivero_main_menu.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_messages.yml and ../../prod-config/block.block.olivero_messages.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_page_title.yml and ../../prod-config/block.block.olivero_page_title.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_powered.yml and ../../prod-config/block.block.olivero_powered.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_primary_admin_actions.yml and ../../prod-config/block.block.olivero_primary_admin_actions.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_primary_local_tasks.yml and ../../prod-config/block.block.olivero_primary_local_tasks.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_search_form_narrow.yml and ../../prod-config/block.block.olivero_search_form_narrow.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_search_form_wide.yml and ../../prod-config/block.block.olivero_search_form_wide.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_secondary_local_tasks.yml and ../../prod-config/block.block.olivero_secondary_local_tasks.yml differ
Files ../../sites/forseti/config/sync/block.block.olivero_site_branding.yml and ../../prod-config/block.block.olivero_site_branding.yml differ
Files ../../sites/forseti/config/sync/block_content.type.basic.yml and ../../prod-config/block_content.type.basic.yml differ
Files ../../sites/forseti/config/sync/comment.type.comment.yml and ../../prod-config/comment.type.comment.yml differ
Files ../../sites/forseti/config/sync/core.base_field_override.node.page.promote.yml and ../../prod-config/core.base_field_override.node.page.promote.yml differ

**Analysis Needed:** Need to check WHY these differ
**Common Reasons:**
- UUID/timestamp differences
- Field configuration changes
- Module version differences
- Content that was added in prod but not in dev


---

## KEY QUESTIONS TO ANSWER:

1. **Group Module** - Is this installed/active in production?
   - Check: Does production have family/institution functionality?
   - Found: 13 group configs only in dev

2. **Contact Module** - Is this installed/active in production?
   - Check: Does production have contact forms?
   - Found: 3 contact configs only in dev

3. **Theme Blocks** - Which blocks are actually live?
   - Production has: forseti_sitebranding, forseti_useraccountmenu
   - Dev has: radix_* blocks (14 total), forseti_branding, etc.
   - Question: Did production theme change from Radix to something else?

4. **Agent Evaluation** - Production has agent_evaluation.settings.yml
   - This module appears active in prod but not in dev config

5. **Social Auth** - Production has block.block.forseti_socialauthlogin_2.yml
   - Social login appears configured in prod


---

## RECOMMENDED RECONCILIATION STRATEGY:

### Phase 1: Sync Core Production Config to Dev
Run: `./reconcile-config.sh ../../prod-config ../../sites/forseti/config/sync`
- Use "use-prod" strategy
- This establishes production as the baseline

### Phase 2: Selectively Restore Dev-Only Files
After sync, manually evaluate each of the 40 dev-only files:
- Test if Contact module is needed → install in prod or remove from dev
- Test if Group module is needed → install in prod or remove from dev  
- Remove unused Radix block configs if prod doesn't use them

### Phase 3: Check Module Installations
Compare installed modules between environments:
```bash
# On production:
drush pm:list --status=enabled --format=list

# On dev:
cd sites/forseti && drush pm:list --status=enabled --format=list
```

Then reconcile module configs accordingly.


---

## IMMEDIATE NEXT STEPS:

1. Answer: Is Group module (families/institutions) actually used in production?
2. Answer: Is Contact module actually used in production?
3. Check: What theme is production using? (Radix blocks suggest theme migration)
4. Run: Module comparison between prod and dev
5. Then: Execute reconciliation based on answers


## DETAILED ANALYSIS OF SAMPLE MODIFIED FILES


### Checking core.extension.yml differences:

--- ../../prod-config/core.extension.yml	2026-02-10 10:39:15.000000000 -0500
+++ ../../sites/forseti/config/sync/core.extension.yml	2026-02-10 10:57:39.130259501 -0500
@@ -1,14 +1,10 @@
 _core:
   default_config_hash: 4GIX5Esnc_umpXUBj4IIocRX7Mt5fPhm4AgXfE3E56E
 module:
-  admin_toolbar: 0
-  admin_toolbar_tools: 0
-  agent_evaluation: 0
   ai_conversation: 0
   amisafe: 0
   announcements_feed: 0
   automated_cron: 0
-  backup_migrate: 0
   big_pipe: 0
   block: 0
   block_content: 0
@@ -16,52 +12,43 @@
   ckeditor5: 0
   comment: 0
   config: 0
+  contact: 0
   contextual: 0
   datetime: 0
   dblog: 0
   dynamic_page_cache: 0
   editor: 0
+  entity: 0
   field: 0
   field_ui: 0
   file: 0
   filter: 0
-  forseti_content: 0
-  forseti_games: 0
-  google_tag: 0
+  flexible_permissions: 0
+  forseti_safety_content: 0
+  group: 0
   help: 0
   history: 0
   image: 0
+  institutional_management: 0
   job_hunter: 0
-  jobhunter_tester: 0
   link: 0
   menu_link_content: 0
   menu_ui: 0
-  metatag: 0
   mysql: 0
-  nfr: 0

### Module Comparison - What's installed where?

#### Production has these modules that dev doesn't:

  admin_toolbar: 0
  admin_toolbar_tools: 0
  agent_evaluation: 0
  backup_migrate: 0
  forseti_content: 0
  forseti_games: 0
  google_tag: 0
  jobhunter_tester: 0
  metatag: 0
  nfr: 0
  pathauto: 0
  safety_calculator: 0
  social_api: 0
  social_auth: 0
  social_auth_google: 0
  token: 0
  twig_tweak: 0
  webform: 0
  webform_ui: 0

#### Dev has these modules that production doesn't:

  contact: 0
  entity: 0
  flexible_permissions: 0
  forseti_safety_content: 0
  group: 0
  institutional_management: 0
