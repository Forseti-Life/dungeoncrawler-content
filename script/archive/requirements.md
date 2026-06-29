# Multi-Site System Requirements

## Minimum Requirements for Multi-Site Drupal 11 Environment

### PHP Requirements
- **PHP Version**: 8.1 or higher (8.3 recommended)
- **Required PHP Extensions**:
  - gd or imagick
  - json
  - mbstring
  - pdo_mysql or pdo_pgsql
  - xml
  - curl
  - openssl
  - tokenizer
  - ctype
  - fileinfo
  - zip

### Database Requirements
- **MySQL**: 5.7.8+ or 8.0+ (recommended)
- **MariaDB**: 10.3.7+ (alternative)
- **PostgreSQL**: 12+ (alternative)
- **Multi-Site**: Support for multiple databases on single MySQL instance

### Web Server Requirements
- **Apache**: 2.4+ with mod_rewrite
  - **Multi-Site**: Virtual host support for port-based routing
  - **Ports**: Must support binding to multiple ports (80, 8080)
- **Nginx**: 1.18+ (alternative, requires manual configuration)
- **PHP Built-in Server**: Not suitable for multi-site development

### Multi-Site Specific Requirements
- **Storage**: Additional disk space for multiple Drupal installations
- **Memory**: Increased PHP memory limit (512M+ recommended for development)
- **Port Availability**: Ports 80 and 8080 must be available
- **Apache Modules**: mod_rewrite, mod_php8.3 required

### Composer
- **Version**: 2.0+
- Required for dependency management and Drupal installation

### Development Tools
- **Drush**: Drupal command line tool
- **Git**: Version control
- **Node.js & npm**: For theme compilation (if using modern themes)

## Multi-Site Ubuntu/Debian Installation Commands

### Automated Installation (Recommended)
The complete setup script handles all requirements automatically:

```bash
cd /workspaces/stlouisintegration.com
./scripts/complete-setup.sh
```

### Manual Installation Commands

```bash
# Update package list
sudo apt update

# Install PHP 8.3 and required extensions
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath php8.3-tokenizer php8.3-json php8.3-fileinfo php8.3-intl php8.3-dom php8.3-opcache

# Install Apache with PHP 8.3 module
sudo apt install -y apache2 libapache2-mod-php8.3

# Install MySQL/MariaDB
sudo apt install -y mysql-server mysql-client

# Enable Apache modules for multi-site
sudo a2enmod rewrite
sudo a2enmod php8.3

# Configure Apache for multiple ports
echo "Listen 8080" | sudo tee -a /etc/apache2/ports.conf
sudo systemctl restart apache2

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Install Git (if not already installed)
sudo apt install -y git

# Install Node.js and npm (for theme development)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

## Multi-Site Memory and Performance Settings

### PHP Configuration for Multi-Site (php.ini)
```ini
# Increased limits for multiple sites
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M

# OPcache settings for better performance
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 4000
```

### MySQL Configuration for Multi-Site
```sql
-- Recommended MySQL settings for multiple Drupal databases
[mysqld]
innodb_buffer_pool_size = 512M
innodb_log_file_size = 64M
max_allowed_packet = 64M

-- Connection limits for multiple sites
max_connections = 200
max_user_connections = 50
```

### Apache Configuration for Multi-Site
```apache
# /etc/apache2/ports.conf
Listen 80
Listen 8080

# Virtual Host Example
<VirtualHost *:80>
    DocumentRoot /workspaces/stlouisintegration.com/sites/stlouisintegration/web
    ServerName localhost
    # Additional directives...
</VirtualHost>

<VirtualHost *:8080>
    DocumentRoot /workspaces/stlouisintegration.com/sites/theoryofconspiracies/web
    ServerName localhost
    # Additional directives...
</VirtualHost>
```

## Multi-Site Development Environment Specifics

### Directory Structure Requirements
```
/workspaces/stlouisintegration.com/
├── sites/
│   ├── stlouisintegration/          # Primary site
│   │   ├── web/                     # Drupal web root
│   │   ├── vendor/                  # Dependencies
│   │   └── config/                  # Configuration
│   └── theoryofconspiracies/        # Secondary site
│       ├── web/                     # Drupal web root
│       ├── vendor/                  # Dependencies
│       └── config/                  # Configuration
├── scripts/                         # Setup scripts
└── docs/                           # Documentation
```

### Multi-Site File Permissions
```bash
# St. Louis Integration site
chmod 755 /workspaces/stlouisintegration.com/sites/stlouisintegration/web/sites/default/files
chmod 644 /workspaces/stlouisintegration.com/sites/stlouisintegration/web/sites/default/settings.php

# Theory of Conspiracies site
chmod 755 /workspaces/stlouisintegration.com/sites/theoryofconspiracies/web/sites/default/files
chmod 644 /workspaces/stlouisintegration.com/sites/theoryofconspiracies/web/sites/default/settings.php

# Private files directories
sudo mkdir -p /var/private/stlouisintegration /var/private/theoryofconspiracies
sudo chown -R $USER:$USER /var/private/stlouisintegration /var/private/theoryofconspiracies
sudo chmod -R 775 /var/private/stlouisintegration /var/private/theoryofconspiracies
```

### Database Setup for Multi-Site
```sql
-- Create databases for both sites
CREATE DATABASE stlouisintegration_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE theoryofconspiracies_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user with access to both databases
CREATE USER 'drupal_user'@'127.0.0.1' IDENTIFIED BY '${DRUPAL_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON stlouisintegration_dev.* TO 'drupal_user'@'127.0.0.1';
GRANT ALL PRIVILEGES ON theoryofconspiracies_dev.* TO 'drupal_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### Network Requirements
- **Port 80**: Primary site (St. Louis Integration)
- **Port 8080**: Secondary site (Theory of Conspiracies)
- **Port 3306**: MySQL database server
- **Firewall**: Ensure development ports are accessible locally

### Resource Allocation
- **Disk Space**: ~2GB per Drupal installation (4GB+ total)
- **RAM**: 4GB+ recommended for development environment
- **CPU**: Multi-core recommended for concurrent site development

### Apache Virtual Host Example
```apache
<VirtualHost *:80>
    ServerName stlouisintegration.local
    DocumentRoot /path/to/drupal/web
    
    <Directory /path/to/drupal/web>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/stlouisintegration_error.log
    CustomLog ${APACHE_LOG_DIR}/stlouisintegration_access.log combined
</VirtualHost>
```

## Verification Commands

```bash
# Check PHP version and extensions
php --version
php -m | grep -E "(gd|mysql|mbstring|xml|curl|json|zip)"

# Check Composer
composer --version

# Check MySQL
mysql --version
sudo systemctl status mysql

# Check Apache
apache2 -v
sudo systemctl status apache2

# Test PHP with Apache
echo "<?php phpinfo(); ?>" | sudo tee /var/www/html/info.php
# Visit http://localhost/info.php
```

## Next Steps

After verifying all requirements are met:

1. Run `./setup-environment.sh` to install any missing dependencies
2. Run `./install-drupal.sh` to create the Drupal project
3. Run `./configure-development.sh` to set up development tools