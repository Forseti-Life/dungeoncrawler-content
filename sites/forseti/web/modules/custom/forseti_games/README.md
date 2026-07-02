# Games Module

A Drupal module for building and hosting interactive games. Provides game
development framework with score tracking and leaderboards.

## Features

- Build and host interactive games
- Performance-optimized JavaScript implementation
- Responsive design for mobile and desktop
- Score tracking and leaderboards
- Reusable game components and framework

## Installation

Enable the module using Drush:

```bash
drush en forseti_games -y
drush cr
```

Or via the Drupal admin interface:
1. Navigate to Extend
2. Search for "Games"
3. Check the box next to "Games"
4. Click "Install"

## Games Included

### Block Matcher
- Match-3 style puzzle game
- Performance-optimized
- Score tracking with top 10 leaderboard

See `documentation/block-matcher/` for detailed architecture and analysis.

## Module Structure

```
forseti_games/
├── forseti_games.info.yml        # Module metadata
├── forseti_games.module          # Hook implementations
├── forseti_games.routing.yml     # Game routes
├── forseti_games.libraries.yml   # JS/CSS libraries
├── src/Controller/               # Game controllers
├── js/                           # Game JavaScript
├── css/                          # Game styles
├── templates/                    # Twig templates
└── documentation/                # Game documentation
```

## Adding New Games

1. Create game controller in `src/Controller/`
2. Define routes in `forseti_games.routing.yml`
3. Add JavaScript/CSS in respective directories
4. Register libraries in `forseti_games.libraries.yml`
5. Create Twig templates as needed
6. Document in `documentation/`

## Best Practices

- Keep games lightweight for mobile performance
- Use responsive design principles
- Implement accessibility features (WCAG 2.1)
- Test across modern browsers
- Monitor performance metrics
- Document architecture and updates

## High Scores API

The module provides REST endpoints for score management:

```
GET  /api/games/high-scores/{game_id}     # Get top scores
POST /api/games/submit-score               # Submit new score
POST /api/games/check-score                # Validate score
```

All endpoints require proper authentication and permissions.

## Permissions

The module defines these permissions:
- View games
- Submit scores
- Manage high scores (admin)

## API Reference

### GameController

Main controller for rendering game pages.

**Methods:**
- `home()` - Display available games
- `blockMatcher()` - Display Block Matcher game

### HighScoreController

Manages score submission and retrieval.

**Methods:**
- `getHighScores()` - Get top 10 scores for a game
- `submitScore()` - Submit new player score
- `checkScore()` - Validate score submission

## Database Tables

- `forseti_games_high_scores` - Stores player scores and leaderboard data

## Requirements

- Drupal 10+ or 11+
- PHP 8.1+

## License

Apache License 2.0. See LICENSE file for details.

## Support

For issues or questions:
1. Review game documentation
2. Check browser JavaScript console for errors
3. Test in multiple browsers
4. Review module logs (drush watchdog)
