# Multi-Site Troubleshooting Guide

This document covers common issues and solutions when setting up the multi-site Drupal development environment with St. Louis Integration and Theory of Conspiracies websites.

## Multi-Site Specific Issues

### 1. Sites Not Accessible After Workspace Restart

**Issue**: One or both sites return 404 or connection refused
```
curl: (7) Failed to connect to localhost port 8080: Connection refused
```

**Solution**:
```bash
# Quick fix - run the startup script
/workspaces/stlouisintegration.com/scripts/quick-start.sh

# Manual troubleshooting
sudo service mysql start
sudo service apache2 start
sudo apache2ctl configtest
```

### 2. Apache Port Configuration Issues

**Issue**: Port 8080 not accessible
```
Apache is not listening on port 8080
```

**Solution**:
```bash
# Check if port 8080 is configured
sudo netstat -tlnp | grep :8080

# Add port 8080 to Apache configuration
echo "Listen 8080" | sudo tee -a /etc/apache2/ports.conf

# Enable Theory of Conspiracies site
sudo a2ensite theoryofconspiracies.conf
sudo service apache2 reload
```

### 3. Database Connection Issues for Multiple Sites

**Issue**: One site works but the other shows database connection error
```
SQLSTATE[HY000] [1049] Unknown database 'theoryofconspiracies_dev'
```

**Solution**:
```bash
# Check if both databases exist
mysql -u drupal_user -p"$DRUPAL_DB_PASSWORD" -e "SHOW DATABASES;"

# Create missing database
mysql -u root -e "CREATE DATABASE theoryofconspiracies_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "GRANT ALL PRIVILEGES ON theoryofconspiracies_dev.* TO 'drupal_user'@'127.0.0.1';"
```

### 4. Custom Module Issues on Primary Site

**Issue**: Custom modules show dependency errors
```
Module 'job_application_automation' has unmet dependencies
```

**Solution**:
```bash
# Navigate to primary site
cd /workspaces/stlouisintegration.com/sites/stlouisintegration

# Enable dependencies in correct order
./vendor/bin/drush en profile -y
./vendor/bin/drush en professional_website_content -y
./vendor/bin/drush en ai_conversation -y
./vendor/bin/drush en stli_site_customizations -y
./vendor/bin/drush en job_application_automation -y
./vendor/bin/drush en resume_tailoring -y
```

### 5. Directory Structure Issues

**Issue**: Legacy `/drupal/` directory conflicts with new multi-site structure
```
Apache DocumentRoot pointing to non-existent directory
```

**Solution**:
```bash
# Run complete setup to migrate structure
/workspaces/stlouisintegration.com/scripts/complete-setup.sh

# Or manually migrate
sudo mv /workspaces/stlouisintegration.com/drupal /workspaces/stlouisintegration.com/sites/stlouisintegration
```

### 6. Drush Context Issues in Multi-Site

**Issue**: Drush commands executed in wrong site context
```
Drush command affecting wrong site database
```

**Solution**:
```bash
# Always navigate to specific site directory first
cd /workspaces/stlouisintegration.com/sites/stlouisintegration
./vendor/bin/drush status  # Primary site

cd /workspaces/stlouisintegration.com/sites/theoryofconspiracies  
./vendor/bin/drush status  # Secondary site

# Verify you're in the correct site context
./vendor/bin/drush sql:query "SELECT name FROM config WHERE name = 'system.site'"
```

## Legacy Single-Site Issues

### 1. PHP Version Problems

**Issue**: PHP version is too old for Drupal 11
```
PHP 7.4 is installed but Drupal 11 requires PHP 8.1+
```

**Solution**:
```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3
sudo apt install php8.3 php8.3-cli php8.3-fpm

# Update alternatives
sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.3 83
```

### 2. Composer Installation Issues

**Issue**: Composer not found or outdated
```
bash: composer: command not found
```

**Solution**:
```bash
# Download and install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify installation
composer --version
```

### 3. MySQL/MariaDB Connection Problems

**Issue**: Can't connect to MySQL
```
ERROR 2002 (HY000): Can't connect to local MySQL server
```

**Solutions**:
```bash
# Start MySQL service
sudo systemctl start mysql
sudo systemctl enable mysql

# Check if MySQL is running
sudo systemctl status mysql

# Reset MySQL root password if needed
sudo mysql_secure_installation
```

**Issue**: Access denied for user
```
ERROR 1045 (28000): Access denied for user 'drupal_user'@'localhost'
```

**Solution**:
```bash
# Login as MySQL root and recreate user
sudo mysql -u root -p

# In MySQL prompt:
DROP USER IF EXISTS 'drupal_user'@'localhost';
CREATE USER 'drupal_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON stlouisintegration_dev.* TO 'drupal_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. File Permission Issues

**Issue**: Permission denied when writing files
```
The directory sites/default/files is not writable
```

**Solution**:
```bash
# Set proper permissions for Drupal
cd /path/to/drupal
chmod 755 web/sites/default
mkdir -p web/sites/default/files
chmod 775 web/sites/default/files
sudo chown -R www-data:www-data web/sites/default/files

# For settings files
chmod 644 web/sites/default/settings.php
```

### 5. Memory Limit Issues

**Issue**: PHP memory limit exceeded
```
Fatal error: Allowed memory size of X bytes exhausted
```

**Solution**:
```bash
# Edit PHP configuration
sudo nano /etc/php/8.3/cli/php.ini

# Find and update:
memory_limit = 256M

# Restart services
sudo systemctl restart apache2
sudo systemctl restart php8.3-fpm
```

### 6. Apache Configuration Issues

**Issue**: Apache not serving Drupal correctly
```
403 Forbidden or 404 Not Found errors
```

**Solution**:
```apache
# Create virtual host: /etc/apache2/sites-available/stlouisintegration.conf
<VirtualHost *:80>
    ServerName stlouisintegration.local
    DocumentRoot /path/to/drupal/web
    
    <Directory /path/to/drupal/web>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/stlouisintegration_error.log
    CustomLog ${APACHE_LOG_DIR}/stlouisintegration_access.log combined
</VirtualHost>
```

```bash
# Enable site and modules
sudo a2ensite stlouisintegration.conf
sudo a2enmod rewrite
sudo systemctl restart apache2

# Add to /etc/hosts
echo "127.0.0.1 stlouisintegration.local" | sudo tee -a /etc/hosts
```

### 7. Drupal Installation Issues

**Issue**: Drupal installation fails
```
Database connection failed during installation
```

**Solutions**:
1. Verify database credentials in settings.php
2. Check if database exists and user has proper permissions
3. Test database connection manually:
```bash
mysql -u drupal_user -p -h localhost stlouisintegration_dev
```

**Issue**: Settings file not writable
```
The settings file does not exist or is not writable
```

**Solution**:
```bash
# Copy and set permissions for settings file
cp web/sites/default/default.settings.php web/sites/default/settings.php
chmod 666 web/sites/default/settings.php

# After installation, secure it:
chmod 444 web/sites/default/settings.php
```

### 8. Drush Issues

**Issue**: Drush commands fail
```
Command 'drush' not found
```

**Solution**:
```bash
# Use vendor/bin/drush instead of global drush
./vendor/bin/drush status

# Or install globally:
composer global require drush/drush
echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

### 9. Module Development Issues

**Issue**: Custom module not recognized
```
Module 'my_module' not found
```

**Solution**:
1. Check module placement: `web/modules/custom/my_module/`
2. Verify `.info.yml` file exists and is valid
3. Clear cache: `drush cache:rebuild`
4. Check module naming conventions

### 10. Performance Issues

**Issue**: Site loads slowly in development
```
Pages take several seconds to load
```

**Solutions**:
1. Disable CSS/JS aggregation in development
2. Enable development services:
```php
// In settings.local.php
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
```

3. Disable caching:
```php
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
```

## Multi-Site Quick Reference

### Site URLs and Directories
```bash
# St. Louis Integration (Primary)
URL: http://localhost
Directory: /workspaces/stlouisintegration.com/sites/stlouisintegration/
Database: stlouisintegration_dev

# Theory of Conspiracies (Secondary)  
URL: http://localhost:8080
Directory: /workspaces/stlouisintegration.com/sites/theoryofconspiracies/
Database: theoryofconspiracies_dev
```

### Essential Commands for Each Site
```bash
# St. Louis Integration
cd /workspaces/stlouisintegration.com/sites/stlouisintegration
./vendor/bin/drush status
./vendor/bin/drush cr
./vendor/bin/drush uli

# Theory of Conspiracies
cd /workspaces/stlouisintegration.com/sites/theoryofconspiracies
./vendor/bin/drush status  
./vendor/bin/drush cr
./vendor/bin/drush uli
```

### Service Management
```bash
# Start all services
sudo service mysql start
sudo service apache2 start

# Restart services
sudo service apache2 reload
sudo service mysql restart

# Check service status
sudo service mysql status
sudo service apache2 status
```

### Quick Diagnostics
```bash
# Comprehensive verification
/workspaces/stlouisintegration.com/scripts/verify-setup.sh

# Quick startup after restart
/workspaces/stlouisintegration.com/scripts/quick-start.sh

# Full setup (if major issues)
/workspaces/stlouisintegration.com/scripts/complete-setup.sh
```

## Getting Help

### Log Files to Check
- Apache error log: `/var/log/apache2/error.log`
- MySQL error log: `/var/log/mysql/error.log`
- Drupal logs: Admin → Reports → Recent log messages
- PHP error log: Check `php.ini` for `log_errors` setting

### Useful Commands for Debugging
```bash
# Check services status
sudo systemctl status mysql apache2 php8.3-fpm

# Test PHP configuration
php -m | grep mysql
php --ini

# Check disk space
df -h

# Check memory usage
free -h

# Test database connection
mysql -u drupal_user -p -h localhost -e "SHOW DATABASES;"

# Check Drupal status
cd /path/to/drupal
./vendor/bin/drush status
./vendor/bin/drush config:status
```

### Recovery Commands
```bash
# Reset file permissions
find web/sites/default/files -type d -exec chmod 775 {} \;
find web/sites/default/files -type f -exec chmod 664 {} \;

# Clear all caches
./vendor/bin/drush cache:rebuild

# Reset database (WARNING: loses all data)
./vendor/bin/drush site:install standard --yes

# Update database
./vendor/bin/drush updatedb

# Import configuration
./vendor/bin/drush config:import
```

## When to Seek Additional Help

If you encounter issues not covered here:
1. Check the Drupal issue queue: https://www.drupal.org/project/issues
2. Search Drupal documentation: https://www.drupal.org/docs
3. Check server logs for specific error messages
4. Verify system requirements are met
5. Test with a fresh installation to isolate the issue