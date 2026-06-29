# Contributing Guide

We welcome contributions! This guide explains how to contribute to the Games
module.

## Development Setup

### Requirements

- PHP 8.1+
- Composer
- Drupal 10+ or 11+
- MySQL 8.0+ (or PostgreSQL 12+)

### Local Setup

```bash
# Clone the repository
git clone <repository-url> drupal-games
cd drupal-games

# Install dependencies
composer install

# Copy configuration
cp .env.example .env
# Edit .env with your database credentials

# Create a fresh Drupal installation
composer require drupal/recommended-project
cd drupal/recommended-project

# Link the module
ln -s ../drupal-games web/modules/custom/forseti_games

# Install Drupal
php core/scripts/drupal install minimal

# Enable the module
drush pm:enable forseti_games
```

## Code Standards

### PHP Code Style

We follow PSR-12 standards.

```bash
# Run PHP Code Sniffer
vendor/bin/phpcs --standard=PSR12 src/
```

### Automatic Fixes

```bash
vendor/bin/phpcbf --standard=PSR12 src/
```

### Drupal Coding Standards

```bash
vendor/bin/phpcs --standard=DrupalPractices src/
```

## Testing

### PHPUnit Tests

```bash
# Run all tests
vendor/bin/phpunit web/modules/custom/forseti_games/tests/

# Run specific test
vendor/bin/phpunit --filter GameControllerTest
```

### Manual Testing

1. Enable the module
2. Navigate to `/games`
3. Click on a game and play it
4. Submit a high score
5. Verify score appears in leaderboard
6. Disable and re-enable the module
7. Verify data persists

## Adding a New Game

### Step 1: Create Controller

Create `src/Controller/YourGameController.php`:

```php
<?php
namespace Drupal\forseti_games\Controller;

use Drupal\Core\Controller\ControllerBase;

class YourGameController extends ControllerBase {
  public function yourGame() {
    return [
      '#theme' => 'your_game_template',
      '#attached' => [
        'library' => [
          'forseti_games/your-game',
        ],
      ],
    ];
  }
}
```

### Step 2: Define Route

Add to `forseti_games.routing.yml`:

```yaml
forseti_games.your_game:
  path: '/games/your-game'
  defaults:
    _controller: '\Drupal\forseti_games\Controller\YourGameController::yourGame'
    _title: 'Your Game'
  requirements:
    _user_is_logged_in: 'TRUE'
```

### Step 3: Add Game Files

```
js/your-game.js          # Game logic
css/your-game.css        # Game styles
templates/your-game.html.twig  # Game UI
```

### Step 4: Register Libraries

Add to `forseti_games.libraries.yml`:

```yaml
your-game:
  version: 1.0.0
  js:
    js/your-game.js: {}
  css:
    theme:
      css/your-game.css: {}
  dependencies:
    - core/drupal
```

### Step 5: Create Template

Create `templates/your-game.html.twig`:

```twig
<div id="your-game" class="game-container">
  <h1>{{ title }}</h1>
  <div id="game-canvas"></div>
  <div id="score">Score: <span id="score-value">0</span></div>
</div>
```

### Step 6: Implement Score Tracking

In `js/your-game.js`:

```javascript
Drupal.behaviors.yourGame = {
  attach: function() {
    // Submit score when game ends
    submitScore({
      game_id: 'your_game',
      score: finalScore
    });
  }
};
```

### Step 7: Update Game List

Edit `src/Controller/GameController.php` and add your game to the array.

### Step 8: Document

Create `documentation/your-game/README.md` with:
- Game description
- Gameplay instructions
- Technical architecture
- Performance considerations

## Git Workflow

### Branch Naming

```
feature/add-new-game     # New feature
fix/scoring-bug          # Bug fix
docs/api-update          # Documentation
refactor/controller      # Code refactoring
```

### Commit Messages

```
type(scope): subject

body

footer
```

Examples:

```
feat(games): add puzzle game controller

- Add PuzzleGameController with game logic
- Add puzzle game route to routing.yml
- Add puzzle template and styles

Fixes #123
```

```
fix(scoring): correct high score calculation

Previously scores were not sorted correctly when equal.
Now uses secondary sort by time submitted.

Fixes #456
```

### Pull Request Process

1. Create feature branch
2. Make changes
3. Run tests and code standards checks
4. Commit with descriptive message
5. Push and create PR with:
   - Clear description of changes
   - Link to related issues
   - Test results
   - Screenshots (for UI changes)
6. Respond to review comments
7. Merge once approved

## Reporting Issues

### Bug Reports

Include:
- Drupal version
- PHP version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Error messages
- Browser/environment info

### Feature Requests

Include:
- Use case
- Proposed solution
- Alternative approaches
- Priority level

## Security

### Reporting Security Issues

**Do not** open public issues for security vulnerabilities.

Email security details to: security@example.com

### Security Best Practices

- Sanitize all user input: `Xss::filter()`
- Use CSRF tokens: `\Drupal::csrfToken()`
- Validate permissions: `user_access()`
- Escape output: `Html::escape()`
- Parameterize queries: `$connection->select()`

## Performance

### Before Submitting

- Run code standards check
- Run tests
- Test on Drupal 10 and 11
- Verify on PHP 8.1 and 8.3
- Profile JavaScript performance
- Check database query optimization

### Performance Checklist

- [ ] No unnecessary database queries
- [ ] JavaScript minified and optimized
- [ ] CSS optimized (no duplicate rules)
- [ ] Images optimized (< 100KB per game)
- [ ] Cache strategies implemented
- [ ] Accessibility (WCAG 2.1) tested

## Communication

- GitHub Issues: Bug reports and feature requests
- Pull Requests: Code review and discussion
- Discussions: General questions and ideas

## Code Review

We review all contributions for:
- Correctness and functionality
- Security vulnerabilities
- Performance implications
- Code style compliance
- Test coverage
- Documentation quality

## License

By contributing, you agree your code is licensed under Apache 2.0.

## Questions?

Open a GitHub Discussion or create an issue.

Thank you for contributing!
