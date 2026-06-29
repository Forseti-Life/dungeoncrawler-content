# Configuration Review: setup.sh and deploy.yml

## Purpose
After rebuilding dev and discovering 460 config differences between dev/prod,
this review ensures our setup scripts align with production requirements.

---

## CRITICAL FINDINGS

### ❌ **setup.sh is missing CRITICAL production modules**

Your setup.sh is installing development tools but **missing production features**:

**Missing from composer require (Line 790-803):**
```bash
# Currently installed:
drupal/devel              ✓ (dev only)
drupal/admin_toolbar      ✓ (has this)
drupal/pathauto           ✓ (has this)
drupal/metatag            ✓ (has this)
drupal/backup_migrate     ✓ (has this)
drupal/bootstrap5         ✓ (has this)
drupal/radix              ✓ (has this)
drupal/recaptcha          ✓ (has this)
drupal/recaptcha_v3       ✓ (has this)
drupal/profile            ✓ (has this)

# MISSING - These are in production:
drupal/webform            ❌ CRITICAL - Forms system (14 config files in prod)
drupal/social_api         ❌ CRITICAL - Google login base
drupal/social_auth        ❌ CRITICAL - Google login core
drupal/social_auth_google ❌ CRITICAL - Google OAuth
drupal/google_tag         ❌ Important - Analytics tracking
drupal/token              ❌ Important - Token system (dependency)
drupal/twig_tweak         ❌ Optional - Theme helper
```

**Missing from module enable (Line 911):**
```bash
# Currently enables:
devel, admin_toolbar, admin_toolbar_tools, pathauto, metatag

# MISSING:
webform, webform_ui, social_auth, social_auth_google, google_tag, token
```

**Wrong module name (Line 1485):**
```bash
# Current:
forseti_safety_content

# Should be:
forseti_content
```

---

## IMPACT ANALYSIS

### What happens if you rebuild dev with current setup.sh?

1. **Forms won't work** - No webform module
2. **Google login won't work** - No social_auth
3. **Dev won't match prod** - Missing 8 critical modules
4. **Config sync will fail** - 103 missing config files
5. **Old module enabled** - forseti_safety_content instead of forseti_content

### What this explains:

- ✅ **This is why you had 460 config differences!**
- ✅ Dev rebuild didn't install production modules
- ✅ Old module name wasn't updated in setup.sh

---

## RECOMMENDED FIXES

### Fix 1: Update composer require section (Line 790-803)

**Current:**
```bash
if [ ! -d "web/modules/contrib/devel" ]; then
    print_status "Installing development modules and packages..."
    /usr/bin/php8.3 /usr/local/bin/composer require \
        drupal/devel \
        drupal/admin_toolbar \
        drupal/pathauto \
        drupal/metatag \
        drupal/backup_migrate \
        drupal/bootstrap5 \
        drupal/radix \
        drupal/recaptcha \
        drupal/recaptcha_v3 \
        drupal/profile \
        aws/aws-sdk-php \
        defuse/php-encryption \
        --no-interaction
else
    print_status "Development modules already installed. Skipping to preserve existing setup."
fi
```

**Should be:**
```bash
if [ ! -d "web/modules/contrib/devel" ]; then
    print_status "Installing development modules and packages..."
    /usr/bin/php8.3 /usr/local/bin/composer require \
        drupal/devel \
        drupal/admin_toolbar \
        drupal/pathauto \
        drupal/metatag \
        drupal/webform \
        drupal/social_api \
        drupal/social_auth \
        drupal/social_auth_google \
        drupal/google_tag \
        drupal/token \
        drupal/twig_tweak \
        drupal/backup_migrate \
        drupal/bootstrap5 \
        drupal/radix \
        drupal/recaptcha \
        drupal/recaptcha_v3 \
        drupal/profile \
        aws/aws-sdk-php \
        defuse/php-encryption \
        --no-interaction
else
    print_status "Development modules already installed. Skipping to preserve existing setup."
fi
```

### Fix 2: Update module enable section (Line 909-913)

**Current:**
```bash
if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "devel"; then
    print_status "Enabling development and utility modules..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php en devel admin_toolbar admin_toolbar_tools pathauto metatag -y
else
    print_status "Development modules already enabled. Skipping to preserve existing configuration."
fi
```

**Should be:**
```bash
if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "devel"; then
    print_status "Enabling development and utility modules..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php en devel admin_toolbar admin_toolbar_tools pathauto metatag -y
    
    print_status "Enabling production feature modules..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php en webform webform_ui token twig_tweak -y
    
    print_status "Enabling social authentication..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php en social_api social_auth social_auth_google -y
    
    print_status "Enabling Google Tag Manager..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php en google_tag -y
else
    print_status "Development modules already enabled. Skipping to preserve existing configuration."
fi
```

### Fix 3: Update custom module enable (Line 1485)

**Current:**
```bash
[ -d "web/modules/custom/forseti_safety_content" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en forseti_safety_content -y 2>/dev/null && print_status "✅ forseti_safety_content enabled"
```

**Should be:**
```bash
[ -d "web/modules/custom/forseti_content" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en forseti_content -y 2>/dev/null && print_status "✅ forseti_content enabled"

# Also uninstall old module if it exists
if /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "forseti_safety_content"; then
    print_status "Uninstalling old forseti_safety_content module..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php pm:uninstall forseti_safety_content -y 2>/dev/null || true
fi
```

---

## deploy.yml Review

### ✅ **deploy.yml is CORRECT**

Your deploy.yml properly:
- ✅ Has config deployment DISABLED (lines 110-165)
- ✅ Includes extensive documentation explaining why
- ✅ References script/production/README.md
- ✅ Explains proper manual config deployment process
- ✅ Prevents automatic config overwrites

**No changes needed to deploy.yml** - it already reflects the proper workflow
we established with the config reconciliation system.

---

## RECOMMENDED ACTIONS

### Immediate (Before next dev rebuild):

1. **Update setup.sh** with the 3 fixes above
2. **Test the changes:**
   ```bash
   cd ~/forseti.life
   git checkout -b fix/setup-production-modules
   # Apply the 3 fixes to script/setup.sh
   git add script/setup.sh
   git commit -m "Add production modules to setup.sh
   
   - Add webform + webform_ui (forms system)
   - Add social_auth modules (Google login)  
   - Add google_tag (analytics), token, twig_tweak
   - Fix module name: forseti_safety_content → forseti_content
   - Align dev setup with production requirements
   
   This resolves the 460 config differences discovered during
   production config comparison."
   git push -u origin fix/setup-production-modules
   ```

3. **Run reconciliation workflow:**
   ```bash
   cd script/production
   ./PRE_SYNC_SETUP.sh  # Installs missing modules
   ./reconcile-config.sh ../../prod-config ../../sites/forseti/config/sync
   ```

4. **Commit synced config:**
   ```bash
   cd ~/forseti.life
   git add sites/forseti/config/sync/
   git commit -m "Sync dev config with production baseline"
   git push
   ```

5. **Merge the setup.sh fixes:**
   ```bash
   git checkout main
   git merge fix/setup-production-modules
   git push
   ```

### Future Prevention:

- ✅ **Next time dev is rebuilt**, setup.sh will install all production modules
- ✅ **Config differences will be minimal** (only expected environment differences)
- ✅ **Dev will match prod** from the start
- ✅ **No more 460-file drift!**

---

## TESTING CHECKLIST

After applying fixes, test that a fresh dev rebuild includes:

```bash
# Test the fixed setup.sh on a clean install:
cd ~/forseti.life/sites/forseti

# Check installed modules:
drush pm:list --status=enabled | grep -E "webform|social|google_tag|token"

# Should see:
# ✓ webform
# ✓ webform_ui  
# ✓ social_api
# ✓ social_auth
# ✓ social_auth_google
# ✓ google_tag
# ✓ token
# ✓ twig_tweak

# Check module count in config:
ls -1 sites/forseti/config/sync/*.yml | wc -l
# Should be close to production count (451)

# Run config comparison:
cd ~/forseti.life/script/production
./compare-config.sh ../../prod-config ../../sites/forseti/config/sync forseti
# Should show minimal differences (only expected environment-specific configs)
```

---

## SUMMARY

| File | Status | Action Needed |
|------|--------|---------------|
| **script/setup.sh** | ❌ NEEDS FIXES | Add 7 missing production modules, fix module name |
| **.github/workflows/deploy.yml** | ✅ CORRECT | No changes needed |

**Root Cause Identified:**
Your dev rebuild happened with an outdated setup.sh that didn't install
production modules (webform, social_auth, etc.), causing the 460 config
differences we discovered.

**Fix Impact:**
After updating setup.sh, future dev rebuilds will match production from
the start, preventing massive config drift.

**Next Step:**
Apply the 3 fixes to setup.sh and test.
