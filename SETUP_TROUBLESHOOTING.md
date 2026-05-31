# Setup Troubleshooting Guide

**Last Updated:** February 27, 2026

This guide addresses common issues encountered during `./script/setup.sh` execution.

## Prerequisites

Before running setup, ensure you have:

1. **Sudo Access**: The script requires sudo for MySQL, Apache, and system package operations
2. **Internet Connection**: Required for downloading Node.js, AWS CLI, and Composer packages
3. **~15 GB free disk space**: For Drupal dependencies, Node modules, and Python environments
4. **Ubuntu 20.04+** or compatible Debian-based distribution

## Pre-Flight Checklist

```bash
# 1. Cache sud credentials before running (valid for 15 minutes)
sudo -v

# 2. Verify MySQL is running
sudo systemctl status mysql

# 3. Check available disk space
df -h /

# 4. Run setup
cd /home/keithaumiller/forseti.life
bash ./script/setup.sh
```

## Common Issues & Solutions

### Issue 1: "AWS CLI installation failed" (apt package not found)

**Symptom:**
```
E: Package 'awscli' has no installation candidate
```

**Cause:** Ubuntu 24.04 removed the `awscli` apt package. AWS CLI v2 must be installed via official installer.

**Fix:** ✅ Fixed in setup.sh as of Feb 27, 2026  
The script now uses the official AWS CLI v2 installer automatically.

**Manual Fix (if needed):**
```bash
cd /tmp
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip -q awscliv2.zip
sudo ./aws/install
aws --version  # Should show aws-cli/2.x.x
```

---

### Issue 2: "Access denied for user 'drupal_user'@'localhost'"

**Symptom:**
```
SQLSTATE[HY000] [1045] Access denied for user 'drupal_user'@'localhost' (using password: YES)
```

**Cause:** MySQL user doesn't exist, has wrong password, or wasn't created for both `@127.0.0.1` and `@localhost`.

**Fix:** ✅ Fixed in setup.sh as of Feb 27, 2026  
The script now:
- Drops and recreates `drupal_user` to ensure password matches `.env`
- Creates user for both `@127.0.0.1` and `@localhost`
- Tests database connectivity before proceeding with Drupal installation

**Manual Fix (if needed):**
```bash
# Read password from .env
source /home/keithaumiller/forseti.life/.env

# Reset MySQL user
sudo mysql <<'SQL'
DROP USER IF EXISTS 'drupal_user'@'127.0.0.1';
DROP USER IF EXISTS 'drupal_user'@'localhost';

CREATE USER 'drupal_user'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER 'drupal_user'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';

GRANT ALL PRIVILEGES ON forseti_dev.* TO 'drupal_user'@'127.0.0.1';
GRANT ALL PRIVILEGES ON forseti_dev.* TO 'drupal_user'@'localhost';
GRANT ALL PRIVILEGES ON amisafe_database.* TO 'drupal_user'@'127.0.0.1';
GRANT ALL PRIVILEGES ON amisafe_database.* TO 'drupal_user'@'localhost';
GRANT ALL PRIVILEGES ON dungeoncrawler_dev.* TO 'drupal_user'@'127.0.0.1';
GRANT ALL PRIVILEGES ON dungeoncrawler_dev.* TO 'drupal_user'@'localhost';

FLUSH PRIVILEGES;
SQL

# Test connection
mysql -h 127.0.0.1 -u drupal_user -p"${DB_PASSWORD}" forseti_dev -e "SELECT 1;"
```

---

### Issue 3: Script hangs waiting for sudo password

**Symptom:** Script execution stops with no output, or shows `[sudo] password for username:`

**Cause:** Multiple `sudo mysql` commands throughout the script require password authentication.

**Solutions:**

**Option A: Cache sudo credentials before running (Recommended)**
```bash
# Cache credentials (valid for 15 minutes)
sudo -v

# Run setup immediately
bash ./script/setup.sh
```

**Option B: Configure temporary passwordless sudo for mysql (Advanced)**
```bash
# WARNING: Security implications - only for dev machines
echo "$(whoami) ALL=(ALL) NOPASSWD: /usr/bin/mysql" | sudo tee /etc/sudoers.d/mysql-nopasswd
sudo chmod 440 /etc/sudoers.d/mysql-nopasswd

# Run setup
bash ./script/setup.sh

# Remove after setup
sudo rm /etc/sudoers.d/mysql-nopasswd
```

---

### Issue 4: "This script should not be run as root"

**Symptom:**
```
[ERROR] This script should not be run as root. Please run as a regular user.
```

**Cause:** You ran `sudo bash ./script/setup.sh` instead of `bash ./script/setup.sh`.

**Fix:**
```bash
# DON'T do this:
sudo bash ./script/setup.sh  ❌

# DO this:
bash ./script/setup.sh  ✅
```

The script will request sudo when needed for specific operations (MySQL, apt, Apache).

---

### Issue 5: Node.js version too old for OpenClaw

**Symptom:**
```
⚠️  OpenClaw requires Node >= 22.12.0; detected Node v18.x.x
```

**Cause:** System Node.js version is below OpenClaw's minimum requirement.

**Fix:** The setup script will automatically upgrade Node.js to v22.x if possible. If it fails:

```bash
# Manual Node.js 22.x installation
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node --version  # Should show v22.x.x or higher

# Then install OpenClaw globally
sudo npm install -g openclaw@2026.2.17
```

---

### Issue 6: Composer dependency conflicts

**Symptom:**
```
Your requirements could not be resolved to an installable set of packages.
```

**Cause:** Usually due to PHP version mismatch or outdated Composer version.

**Fix:**
```bash
# Verify PHP 8.3 is active
php --version  # Should show PHP 8.3.x

# Update Composer to latest
composer self-update

# Clear Composer cache
composer clear-cache

# Re-run Composer install in each site
cd /home/keithaumiller/forseti.life/sites/forseti
composer install --no-interaction

cd /home/keithaumiller/forseti.life/sites/dungeoncrawler
composer install --no-interaction
```

---

### Issue 7: Apache fails to start

**Symptom:**
```
Job for apache2.service failed because the control process exited with error code.
```

**Common Causes:**
1. Port 80 already in use
2. Invalid Apache configuration
3. Missing PHP module

**Diagnostics:**
```bash
# Check what's using port 80
sudo lsof -i :80

# Test Apache configuration
sudo apache2ctl configtest

# Check Apache error log
sudo journalctl -u apache2 -n 50
```

**Fix:**
```bash
# If port conflict, stop conflicting service
sudo systemctl stop <service-name>

# Restart Apache
sudo systemctl restart apache2
```

---

## Getting Help

If you encounter an issue not covered here:

1. **Check the logs:**
   - Setup log: Check terminal output or `/tmp/forseti-setup*.log`
   - Apache: `sudo journalctl -u apache2 -n 100`
   - MySQL: `sudo journalctl -u mysql -n 100`
   - PHP: `/var/log/apache2/error.log`

2. **Verify system state:**
   ```bash
   # Check all required services
   sudo systemctl status mysql
   sudo systemctl status apache2
   
   # Verify PHP version
   php --version
   /usr/bin/php8.3 --version
   
   # Check database access
   mysql -h 127.0.0.1 -u drupal_user -p forseti_dev
   ```

3. **Document the error:**
   - Full error message
   - Command that failed
   - Relevant log excerpts
   - System info: `lsb_release -a && uname -r`

4. **Reset and retry:**
   ```bash
   # Drop databases (WARNING: destroys all data)
   sudo mysql -e "DROP DATABASE IF EXISTS forseti_dev;"
   sudo mysql -e "DROP DATABASE IF EXISTS amisafe_database;"
   sudo mysql -e "DROP DATABASE IF EXISTS dungeoncrawler_dev;"
   
   # Recreate .env with fresh passwords
   rm .env
   
   # Re-run setup
   bash ./script/setup.sh
   ```

---

## Successful Setup Indicators

You'll know setup completed successfully when you see:

```
✅ Forseti.life setup complete!

Summary:
  • Forseti main site: http://localhost
  • Dungeon Crawler: http://localhost:8080
  • Admin user: admin
  • Database: forseti_dev (drupal_user@127.0.0.1)

Next steps:
  1. Start Apache: sudo systemctl start apache2
  2. Visit: http://localhost
  3. Log in with admin credentials from .env
```

Test by visiting:
- Main site: http://localhost
- Admin dashboard: http://localhost/admin
- Login: Use credentials from `.env` file

---

## Post-Setup Verification

```bash
# Run verification script
cd /home/keithaumiller/forseti.life
./scripts/verify-setup.sh

# Expected output: All checks pass ✅
```
