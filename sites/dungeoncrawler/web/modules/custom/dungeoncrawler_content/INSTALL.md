# Installation Guide: DungeonCrawler Content Module

## Prerequisites

- Drupal 10.3+ or 11
- PHP 8.1 or higher
- Composer
- AI Conversation module
- Optional: Gemini API or Vertex AI account

## System Requirements

### Minimum
- RAM: 1 GB
- Disk: 500 MB
- Database: MySQL 8.0+ or PostgreSQL 12+

### Recommended
- RAM: 4 GB
- Disk: 2 GB
- Database: PostgreSQL 14+
- Dedicated AI provider account

## Step-by-Step Installation

### 1. Install Dependencies

```bash
cd {YOUR_DRUPAL_ROOT}

# Install AI Conversation module
./vendor/bin/drush module:install ai_conversation
```

### 2. Place Module

```bash
cp -r dungeoncrawler_content modules/custom/
```

### 3. Enable Module

```bash
./vendor/bin/drush module:install dungeoncrawler_content
```

### 4. Configure AI Provider

Create or update `.env`:

```bash
# Gemini Configuration
DUNGEONCRAWLER_AI_PROVIDER=gemini
DUNGEONCRAWLER_LLM_MODEL=gemini-pro
GEMINI_API_KEY=your_api_key_here

# Or Vertex AI
# DUNGEONCRAWLER_AI_PROVIDER=vertex
# VERTEX_API_PROJECT=your-project-id
# VERTEX_API_KEY=your_api_key_here
```

### 5. Set Up Configuration

Navigate to **Admin > Configuration > DungeonCrawler Content**

- Select AI provider
- Configure generation parameters
- Set difficulty scaling
- Review template options

### 6. Verify Installation

```bash
./vendor/bin/drush pm:list --filter=dungeoncrawler
./vendor/bin/drush runserver localhost:8000
# Visit http://localhost:8000/game to verify
```

## Configuration

### Database Setup

The module creates:
- `dungeoncrawler_campaign` table
- `dungeoncrawler_level` table
- `dungeoncrawler_creature` table
- `dungeoncrawler_encounter` table
- `dungeoncrawler_state` table

### Permissions

Set at **Admin > People > Permissions**:
- Create Game Campaign
- Edit own Game Campaign
- Delete own Game Campaign
- Access Game Dashboard
- View Campaign Maps
- Generate Dungeons

### Performance Tuning

For high player counts:

```php
// settings.php
$settings['dungeoncrawler_cache_ttl'] = 3600;
$settings['dungeoncrawler_state_persistence'] = TRUE;
$settings['dungeoncrawler_batch_generation'] = TRUE;
```

## Troubleshooting

### AI Generation Fails

```bash
# Test API connectivity
php -r "echo getenv('GEMINI_API_KEY') ? 'Key set' : 'Key missing';"

# Check module logs
./vendor/bin/drush watchdog:show --tail=20
```

### Slow Performance

```bash
# Clear caches
./vendor/bin/drush cache:rebuild

# Run cleanup
./vendor/bin/drush dungeoncrawler:cleanup-old-campaigns

# Check database size
./vendor/bin/drush php:eval "echo db_query('SELECT COUNT(*) FROM {dungeoncrawler_state}')->fetchField();"
```

### Module Won't Enable

```bash
# Check dependencies
./vendor/bin/drush pm:list | grep ai_conversation

# Verify requirements
./vendor/bin/drush status
```

## Upgrading

```bash
# Backup database
./vendor/bin/drush sql:dump > backup.sql

# Run updates
./vendor/bin/drush updatedb -y

# Clear cache
./vendor/bin/drush cache:rebuild
```

## Uninstallation

```bash
./vendor/bin/drush module:uninstall dungeoncrawler_content
```

## Next Steps

1. Read GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md for system design
2. Review COMBAT_ENGINE_ARCHITECTURE.md for combat mechanics
3. Check API_DOCUMENTATION.md for API reference
4. See CONTRIBUTING.md for development guidelines

## Support

Visit: https://www.drupal.org/project/dungeoncrawler_content/issues
