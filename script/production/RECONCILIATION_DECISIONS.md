# Configuration Reconciliation Decision Matrix

## Executive Summary

**CRITICAL FINDING:** Production and dev have **significantly different module installations** causing 460 config differences.

### Key Module Differences:

**Production Has (19 modules):**
- `webform` + `webform_ui` - ⚠️ **CRITICAL** - Form system  
- `social_auth` + `social_auth_google` - ⚠️ **CRITICAL** - Google login
- `metatag` - SEO metadata
- `google_tag` - Analytics tracking  
- `pathauto` - URL aliases
- `token` - Token system (dependency for many)
- `backup_migrate` - ✅ Expected (prod only)
- `admin_toolbar` + `admin_toolbar_tools` - Admin UI
- `agent_evaluation`, `nfr`, `safety_calculator` - Custom features
- `forseti_content`, `forseti_games` - Content modules
- `jobhunter_tester` - Testing module

**Dev Has (6 modules):**
- `group` - Group/family/institution system
- `institutional_management` - Related to groups
- `flexible_permissions` - Permission system
- `entity` - Entity API
- `contact` - Core contact forms
- `forseti_safety_content` - Safety content (vs `forseti_content` in prod?)

---

## Decision Matrix

### ❌ **DO NOT** Deploy Dev Config to Production
**Risk:** Would uninstall 19 critical production modules including:
- Webform (all forms would break)
- Social auth (Google login would break)
- Metatag (SEO would break)

### ✅ **RECOMMENDED:** Sync Production to Dev

This establishes production as the source of truth.

---

## Module-by-Module Analysis

### Production Modules Missing from Dev

| Module | Critical? | Action | Notes |
|--------|-----------|--------|-------|
| `webform` | 🔴 YES | Install in dev | Forms system - absolutely needed |
| `webform_ui` | 🔴 YES | Install in dev | Webform admin UI |
| `social_auth` | 🔴 YES | Install in dev | Google login - critical feature |
| `social_auth_google` | 🔴 YES | Install in dev | Google OAuth integration |
| `metatag` | 🟡 Important | Install in dev | SEO - should match prod |
| `google_tag` | 🟡 Important | Install in dev | Analytics tracking |
| `pathauto` | 🟡 Important | Install in dev | URL management |
| `token` | 🟡 Important | Install in dev | Required by others |
| `backup_migrate` | ⚪ Optional | Keep prod only | Backups not needed in dev |
| `admin_toolbar` | ⚪ Optional | Install in dev | Better admin UX |
| `admin_toolbar_tools` | ⚪ Optional | Install in dev | Better admin UX |
| `agent_evaluation` | ⚪ Unknown | Research first | Custom module - check if active |
| `nfr` | ⚪ Unknown | Research first | Custom module - check if active |
| `safety_calculator` | ⚪ Unknown | Research first | Custom module - check if active |
| `forseti_content` | ⚪ Unknown | Research first | vs forseti_safety_content? |
| `forseti_games` | ⚪ Unknown | Research first | Games feature |
| `jobhunter_tester` | ⚪ Optional | Keep prod only | Testing module |
| `twig_tweak` | ⚪ Optional | Install in dev | Theme helper |

### Dev Modules Missing from Production

| Module | Active? | Action | Notes |
|--------|---------|--------|-------|
| `group` | ❓ | **Decision needed** | Family/institution system |
| `institutional_management` | ❓ | **Decision needed** | Related to groups |
| `flexible_permissions` | ❓ | **Decision needed** | Permission system |
| `entity` | ❓ | **Decision needed** | Entity API module |
| `contact` | ❓ | **Decision needed** | Core contact forms |
| `forseti_safety_content` | ❓ | **Decision needed** | vs forseti_content in prod? |

**Questions to Answer:**
1. Is the Group module (families/institutions) a **new feature** being developed?
   - If YES → Install in prod during next feature deployment
   - If NO → Uninstall from dev, remove configs

2. Is `forseti_safety_content` a **replacement** for `forseti_content`?
   - Check codebase to see if this is a rename
   - Check if both can coexist

---

## Configuration Files Analysis

### Production-Only Configs (103 files)

**Webform Configs (14 files):**
- `webform.settings.yml`
- `webform.webform.contact.yml`
- `webform.webform.contact_forseti.yml`
- `system.action.webform_*` (11 action files)

**Social Auth Configs (4 files):**
- `social_auth.settings.yml`
- `social_auth_google.settings.yml`
- `block.block.forseti_socialauthlogin_2.yml`
- `views.view.social_auth_profiles.yml`

**User Roles (4 roles with 8 config files):**
- `user.role.firefighter.yml`
- `user.role.fire_dept_admin.yml`
- `user.role.nfr_administrator.yml`
- `user.role.nfr_researcher.yml`
- Plus system actions for each role

**SEO/Marketing (18 files):**
- `metatag.metatag_defaults.*` (7 files)
- `google_tag.*` (3 files)
- `pathauto.settings.yml`

**Other:**
- `backup_migrate.*` (6 files) - ✅ Expected prod-only
- `admin_toolbar.*` (2 files)
- `token` entity view modes (10 files)
- `agent_evaluation.settings.yml`

### Dev-Only Configs (40 files)

**Group Module (13 files):**
- `group.type.family.yml`
- `group.type.institution.yml`
- `group.role.*` (4 files)
- `group.relationship_type.*` (2 files)
- `group.settings.yml`
- Related form/view displays (4 files)
- `views.view.group_members.yml`

**Theme Blocks (20 files):**
- `block.block.radix_*` (14 files) - Radix theme blocks
- `block.block.forseti_*` (6 files) - Forseti theme blocks

**Contact Module (3 files):**
- `contact.settings.yml`
- `contact.form.feedback.yml`
- `contact.form.personal.yml`

**Other (4 files):**
- Entity form/view displays

### Modified Files (317 files)

**Common modification reasons:**
1. Module version differences
2. UUID/timestamp differences
3. Field configurations added in prod
4. Content added in prod but not synced to dev
5. Module dependencies changing field configs

---

## Recommended Reconciliation Plan

### Step 1: Back Up Everything ✅

```bash
# Already done by reconcile script
```

### Step 2: Sync Production Config to Dev

```bash
cd /home/keithaumiller/forseti.life/script/production
./reconcile-config.sh ../../prod-config ../../sites/forseti/config/sync
```

Select: **Option 1: use-prod**

This will:
- ✅ Replace all dev configs with production configs
- ✅ Remove the 40 dev-only files
- ✅ Align all 317 modified files with production
- ✅ Create backup of dev configs in `/tmp/config-backup-*`

### Step 3: Install Missing Production Modules in Dev

```bash
cd ~/forseti.life/sites/forseti
composer require drupal/webform drupal/social_auth drupal/social_auth_google drupal/metatag drupal/google_tag drupal/pathauto drupal/token drupal/admin_toolbar drupal/twig_tweak

drush pm:enable webform webform_ui social_api social_auth social_auth_google metatag google_tag pathauto token admin_toolbar admin_toolbar_tools twig_tweak -y

drush config:export -y
```

### Step 4: Decision Points - Review Dev-Only Modules

**Before uninstalling Group module, answer:**
- Is family/institution functionality planned for production?
- Is this currently under development?
- Check: `ls -la ~/forseti.life/sites/forseti/web/modules/custom/ | grep -i group`

**If Group is NOT needed:**
```bash
cd ~/forseti.life/sites/forseti
drush pm:uninstall group flexible_permissions institutional_management -y
```

**If Group IS needed (future feature):**
```bash
# Keep it, but document it in .config-differences.yml
# Install it in production when ready to deploy the feature
```

**Contact module:**
```bash
# If not needed:
drush pm:uninstall contact -y

# If needed: Keep it, it's harmless
```

### Step 5: Commit the Synchronized Config

```bash
cd ~/forseti.life/sites/forseti
drush config:export -y

cd ~/forseti.life
git add sites/forseti/config/sync/
git commit -m "Sync dev config from production baseline - reconciled 460 differences"
git push
```

### Step 6: Update Expected Differences Documentation

Edit `sites/forseti/.config-differences.yml` to add:
- Webform configs (if test data differs)
- Any remaining intentional differences

### Step 7: Verify Dev Environment Works

```bash
cd ~/forseti.life/sites/forseti
drush cr
drush updatedb -y

# Test key functionality:
# - Google login works
# - Webforms display correctly
# - Job hunter module works
# - All custom modules load
```

---

## Questions That Need Answers

Before proceeding, please answer:

### 1. Group Module
- [ ] Are families/institutions a feature in production?
- [ ] Are they under development for future deployment?
- [ ] Should we keep or remove the group module?

### 2. Forseti Content Modules
- [ ] Is `forseti_safety_content` (dev) the same as `forseti_content` (prod)?
- [ ] Did this module get renamed?
- [ ] Check filesystem: Do both exist?

### 3. Custom Modules Status
These exist in prod but config not in dev:
- [ ] `agent_evaluation` - Is this actively used?
- [ ] `nfr` - Is this actively used?
- [ ] `safety_calculator` - Is this actively used?
- [ ] `forseti_games` - Is this actively used?

### 4. Theme Status
- [ ] Production has `forseti_sitebranding` block
- [ ] Dev has `radix_*` blocks (14 of them)
- [ ] Is production using a different theme than dev?
- [ ] Check: `drush config:get system.theme` in both environments

---

## Safety Checklist

Before running reconciliation:

- [x] Production config exported (451 files)
- [x] Dev config exported (428 files + 40 unique)
- [x] Differences analyzed (460 total)
- [ ] **DECISION MADE:** Group module - keep or remove?
- [ ] **DECISION MADE:** Ready to sync prod → dev?
- [ ] **BACKUP VERIFIED:** Can restore dev config if needed?

---

## Post-Reconciliation Testing

After syncing, test these critical areas:

1. **User Login:**
   - [ ] Google social login works
   - [ ] Regular login works

2. **Forms:**
   - [ ] Contact forms display
   - [ ] Webforms work

3. **Job Hunter:**
   - [ ] Job hunter pages load
   - [ ] Navigation works

4. **Custom Features:**
   - [ ] Any custom functionality works
   - [ ] Check for "module missing" errors

5. **Admin Interface:**
   - [ ] Admin toolbar displays
   - [ ] Configuration pages work

---

## Rollback Plan

If something breaks after reconciliation:

```bash
# Restore dev config from backup
cd ~/forseti.life/sites/forseti/config/sync
rm -f *.yml
cp /tmp/config-backup-TIMESTAMP/* .

# Import restored config
drush config:import -y
drush cr
```

---

## Summary

**The Problem:** Dev and prod have diverged by 460 config files due to different module installations.

**The Solution:** Sync production → dev to establish baseline, then install missing modules.

**The Risk:** Group module configs (13 files) will be deleted if we sync - decide if this is okay.

**The Benefit:** Dev will match production, allowing safe config testing before deployment.

**Next Action:** Answer the 4 questions above, then run reconciliation.
