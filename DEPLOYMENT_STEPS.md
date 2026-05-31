# NFR Module Fix Deployment Steps

**Last Updated:** February 6, 2026

## Changes Made (Commit 15a32befd)

### 1. Added nfr_section_completion to hook_schema()
- **Problem**: Table only existed in update hook 9013 (for upgrades)
- **Impact**: Fresh installs never created this table
- **Fix**: Added complete table definition to hook_schema() for base installation
- **Location**: [nfr.install](sites/forseti/web/modules/custom/nfr/nfr.install) (around line 1863)

### 2. Fixed validation user creation UUID error
- **Problem**: Manual INSERT into users table didn't provide UUID field
- **Impact**: User creation failed with "Field 'uuid' doesn't have a default value"
- **Fix**: Removed manual INSERT, let Drupal User entity API handle all fields
- **Location**: [nfr.install](sites/forseti/web/modules/custom/nfr/nfr.install) lines 115-132

## Deployment Instructions

### 1. Wait for GitHub Actions to Complete
The code has been pushed to GitHub (commit 15a32befd). Wait for the deployment action to complete.

### 2. SSH to Production Server
```bash
ssh your-production-server
cd /var/www/html/forseti
```

### 3. Run Database Updates
```bash
# This will create the nfr_section_completion table from hook_schema()
./vendor/bin/drush updb -y
```

### 4. Verify Table Creation
```bash
# Check if the table exists
./vendor/bin/drush sqlq "SHOW TABLES LIKE 'nfr_section_completion'"

# Check table structure
./vendor/bin/drush sqlq "DESCRIBE nfr_section_completion"
```

Expected output:
```
Field           Type          Null  Key  Default  Extra
id              int(11)       NO    PRI  NULL     auto_increment
uid             int(11)       NO    MUL  NULL
section_number  tinyint(4)    NO    MUL  NULL
completed       tinyint(4)    NO         0
completed_at    int(11)       YES        NULL
updated         int(11)       NO         0
```

### 5. Check for Any Remaining Errors
```bash
# Check recent watchdog logs
./vendor/bin/drush watchdog:show --count=20

# Should see no more DatabaseExceptionWrapper errors about nfr_section_completion
```

### 6. Test Validation User Creation (Optional)
If you want to manually trigger user creation:
```bash
# Run the update hook that creates validation users
./vendor/bin/drush php:eval "_nfr_create_validation_users();"
```

### 7. Verify Users Have Roles
```bash
./vendor/bin/drush sqlq "SELECT u.uid, u.name, GROUP_CONCAT(ur.roles_target_id) as roles FROM users_field_data u LEFT JOIN user__roles ur ON u.uid = ur.entity_id WHERE u.uid IN (2,3,4,5,6) GROUP BY u.uid"
```

Expected: Each validation user should have their assigned role.

### 8. Test the Questionnaire
Navigate to:
- https://forseti.life/nfr/questionnaire/section/1
- https://forseti.life/admin/nfr/validation/fill-rates

Both should load without errors.

## Verification Checklist

- [ ] GitHub Actions deployment completed successfully
- [ ] `drush updb` executed without errors
- [ ] `nfr_section_completion` table exists in database
- [ ] No DatabaseExceptionWrapper errors in watchdog
- [ ] Validation users exist and have correct roles
- [ ] Questionnaire page loads without errors
- [ ] Fill-rates page shows tracked fields for sections 3-9

## Rollback Plan (If Needed)

If issues occur:
```bash
# Manually create the table using the schema from nfr_update_9013
./vendor/bin/drush sqlq "CREATE TABLE nfr_section_completion ..."

# Or revert to previous commit
git revert 15a32befd
git push origin main
# Wait for deployment, then run drush updb
```

## What Was Fixed

**Root Cause**: The nfr_section_completion table was only defined in an UPDATE hook (9013), which only runs when upgrading existing installations. Fresh installs only run hook_schema(), which didn't include this table definition.

**Solution**: Added the table definition to hook_schema() so it gets created during fresh installs, following proper Drupal best practices as outlined in instructions.md (NO QUICK FIXES policy).

**Additional Fix**: Removed manual SQL INSERT for user creation that was failing due to missing UUID field. Now using Drupal's User entity API properly, which handles all required fields including UUID.
