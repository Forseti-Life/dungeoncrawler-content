<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the About page.
 */
class AboutController extends ControllerBase {

  /**
   * Display the about page.
   *
   * @return array
   *   A render array for the about page.
   */
  public function index() {
    $build = [];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hero-section', 'mb-5', 'text-center']],
      'content' => [
        '#markup' => '<div class="card bg-gradient-dark text-light border-0">
          <div class="card-body p-5">
            <h1 class="display-3 mb-4">Dungeon Crawler Life</h1>
            <p class="lead fs-3">Where Forseti Guides Every Adventure</p>
            <p class="fs-5 text-muted">A living RPG home where your characters can adventure, evolve, and retire with purpose</p>
          </div>
        </div>',
      ],
    ];

    $build['story'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['about-story', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light">
          <div class="card-body p-4">
            <h2 class="card-title mb-4">The Vision</h2>
            <p class="lead">Dungeon Crawler Forseti Life is designed for former tabletop and classic RPG players who want a permanent home for their heroes.</p>
            <p>Instead of disposable runs, we focus on long-form continuity: campaigns persist, characters carry history, and retirement can become part of your account\'s living legacy.</p>
            <p>Using advanced AI technology, Dungeon Crawler Life generates:</p>
            <ul>
              <li><strong>Persistent Campaign Homes:</strong> Your campaign space remains available for repeat sessions and long arcs</li>
              <li><strong>Character-First Continuity:</strong> Build a roster that can evolve from first run to final retirement</li>
              <li><strong>Dynamic Rooms and Encounters:</strong> AI-assisted generation adds variety without losing campaign continuity</li>
              <li><strong>Living Items and Progression:</strong> Equipment choices reinforce your character\'s long-term identity</li>
              <li><strong>Evolving Universe Story:</strong> Your choices become part of the wider Dungeon Crawler timeline</li>
            </ul>
          </div>
        </div>',
      ],
    ];

    $build['features'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['features', 'row', 'g-4', 'mb-5']],
    ];

    $features = [
      [
        'icon' => '🤖',
        'title' => 'Forseti-Guided Generation',
        'description' => 'Forseti orchestrates dynamic content while preserving campaign continuity and player identity.',
      ],
      [
        'icon' => '🎲',
        'title' => 'Enduring Replayability',
        'description' => 'Return with the same heroes or successor characters as your world history expands.',
      ],
      [
        'icon' => '🧠',
        'title' => 'Campaign-Scale Challenge',
        'description' => 'Difficulty and pacing support both fresh starts and deep, returning campaign runs.',
      ],
      [
        'icon' => '🌍',
        'title' => 'Persistent Hex World',
        'description' => 'Explore and revisit regions with outcomes that stay meaningful across sessions.',
      ],
      [
        'icon' => '⚔️',
        'title' => 'Classic RPG Mechanics',
        'description' => 'Built on familiar tactical foundations that former tabletop players can immediately read.',
      ],
      [
        'icon' => '📱',
        'title' => 'Play Anywhere',
        'description' => 'Continue your campaign home from web or mobile without losing progression context.',
      ],
    ];

    foreach ($features as $feature) {
      $build['features'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-6', 'col-lg-4']],
        'card' => [
          '#markup' => '<div class="card h-100 bg-dark text-light border-primary">
            <div class="card-body text-center">
              <div class="display-1 mb-3">' . $feature['icon'] . '</div>
              <h3 class="card-title">' . $feature['title'] . '</h3>
              <p class="card-text">' . $feature['description'] . '</p>
            </div>
          </div>',
        ],
      ];
    }

    $build['technology'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['technology', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light border-info">
          <div class="card-body p-4">
            <h2 class="card-title mb-4">🔧 The Technology</h2>
            <div class="row">
              <div class="col-md-6">
                <h4>Built With:</h4>
                <ul>
                  <li>Drupal CMS for robust content management</li>
                  <li>React Native for mobile experiences</li>
                  <li>H3 geospatial indexing for hex-based world</li>
                  <li>Advanced AI models for content generation</li>
                  <li>Real-time game state management</li>
                </ul>
              </div>
              <div class="col-md-6">
                <h4>Powered By:</h4>
                <ul>
                  <li>Natural Language Processing for dynamic dialogue</li>
                  <li>Procedural generation algorithms</li>
                  <li>Machine learning for adaptive gameplay</li>
                  <li>RESTful API architecture</li>
                  <li>Modern web technologies (HTML5, CSS3, JavaScript)</li>
                </ul>
              </div>
            </div>
          </div>
        </div>',
      ],
    ];

    $build['team'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['team', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light">
          <div class="card-body p-4 text-center">
            <h2 class="card-title mb-4">The Team</h2>
            <p class="lead">Dungeon Crawler Life is built by a small studio team focused on living-world RPG systems and long-term character attachment.</p>
            <p>We believe RPG worlds should be: <strong>persistent</strong>, <strong>welcoming to returning players</strong>, and <strong>worth investing years into</strong>.</p>
          </div>
        </div>',
      ],
    ];

    $build['cta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cta', 'mt-5', 'text-center']],
      'content' => [
        '#markup' => '<div class="card bg-primary text-light border-0">
          <div class="card-body p-5">
            <h2 class="card-title mb-4">Ready to Build a Permanent Character Home?</h2>
            <p class="lead mb-4">Create your roster, launch your first campaign, and grow into the wider Forseti universe.</p>
            <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
              <a href="/characters/create" class="btn btn-light btn-lg px-5">Create Legacy Character</a>
              <a href="/how-to-play" class="btn btn-outline-light btn-lg px-5">Read Player Guide</a>
            </div>
          </div>
        </div>',
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

}
