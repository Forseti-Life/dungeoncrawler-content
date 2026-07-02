# Installation Guide

## Requirements

- Drupal 10.3+ or 11+
- PHP 8.1 or higher
- Database: MySQL 8.0+, PostgreSQL 12+, or SQLite 3.26+

## Installation Steps

### Step 1: Download and Extract

```bash
cd web/modules/custom
git clone https://github.com/YOUR_ORG/drupal-games.git forseti_games
cd forseti_games
composer install
```

### Step 2: Enable the Module

Using Drush:

```bash
drush pm:enable forseti_games
drush cache:rebuild
```

Using Drupal UI:
1. Go to Manage > Extend
2. Search for "Games"
3. Check the box next to "Games"
4. Click "Install"

### Step 3: Verify Installation

```bash
drush pm:list --status=enabled | grep forseti_games
```

You should see "Games" module listed as enabled.

### Step 4: Check Permissions

Navigate to:
- Admin > People > Permissions
- Search for "Games"
- Assign permissions as needed:
  - "View games" - Allow authenticated users to view and play games
  - "Submit scores" - Allow players to submit high scores
  - "Manage games" - Admin role only

### Step 5: Access Games

Navigate to `/games` in your browser to see the games page.

## Configuration

Currently, no configuration page is available. Games are defined in code.

To customize games, edit:
- `src/Controller/GameController.php` - Add/modify games
- `forseti_games.routing.yml` - Define game routes
- `templates/` - Modify game UI
- `js/` - Modify game logic
- `css/` - Customize styling

## Troubleshooting

### Module Won't Enable

```
Error: Drupal\Core\Extension\ExtensionDiscoveryException
```

**Solution:** Ensure `forseti_games.info.yml` is present and valid.

```bash
cat web/modules/custom/forseti_games/forseti_games.info.yml
```

### Games Page Returns 403 (Access Denied)

**Solution:** Check user permissions.

```bash
drush role:perm:add authenticated "view games"
```

### Games Page Returns 404

**Solution:** Clear Drupal cache.

```bash
drush cache:rebuild
```

Or manually:
1. Admin > Configuration > Development > Performance
2. Click "Clear all caches"

### High Scores Not Showing

**Solution:** Verify database table exists.

```bash
drush sqlq "SHOW TABLES LIKE 'forseti_games_%'"
```

Should show: `forseti_games_high_scores`

If missing, uninstall and reinstall:

```bash
drush pm:uninstall forseti_games
drush pm:enable forseti_games
```

### JavaScript Errors in Console

**Solution:** Clear browser cache and force refresh.

```
Ctrl+Shift+R (Windows/Linux)
Cmd+Shift+R (macOS)
```

Check that library is loaded:

```bash
drush cache:rebuild
```

## Uninstallation

```bash
drush pm:uninstall forseti_games
```

The high scores table is preserved (not deleted on uninstall) to preserve data.
To delete it manually:

```bash
drush sqlq "DROP TABLE forseti_games_high_scores"
```

## Performance Tuning

### Database

For large player bases, add indexes:

```sql
CREATE INDEX idx_game_id ON forseti_games_high_scores(game_id);
CREATE INDEX idx_user_id ON forseti_games_high_scores(user_id);
CREATE INDEX idx_score DESC ON forseti_games_high_scores(score DESC);
```

### Caching

Drupal caches the high scores page. To adjust cache TTL:

```bash
drush config:set page_cache.settings max_age 3600
```

## Support

For installation issues:
- Check Drupal logs: Admin > Reports > Recent log messages
- Review browser JavaScript console (F12)
- Test in incognito/private mode
- Verify all requirements are met

## Next Steps

- Read CONTRIBUTING.md to add new games
- Review ARCHITECTURE documentation
- Check individual game documentation in `documentation/`
