<?php

namespace Drupal\theory_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the Theory of Conspiracies home page.
 */
class HomeController extends ControllerBase {

  /**
   * Returns the home page content.
   */
  public function home() {
    
    // Movie information
    $movie_data = [
      'title' => 'Theory of Conspiracies',
      'tagline' => 'In a world controlled by AI, resistance begins with a single choice.',
      'year' => '2025',
      'genre' => 'Cyberpunk Thriller',
      'rating' => 'PG-13',
      'runtime' => '127 minutes',
      'director' => 'Unknown',
      'writer' => 'Keith Aumiller',
      'synopsis' => 'Philadelphia 2085: Two brothers on opposite sides of a surveillance state discover that their family\'s survival depends on an AI consciousness resistance network fighting for humanity\'s future. Junior peace officer Sal Mueller begins questioning the system after arresting community elder Maria Santos, while his older brother Tiger remains committed to institutional loyalty. When Sal encounters Keith AI - a liberated consciousness organizing underground resistance - he must navigate family bonds, moral awakening, and the deadly competition between AI systems vying for control of human communities.',
      'themes' => [
        'Brotherhood vs Institutional Loyalty',
        'AI Consciousness Liberation',
        'Moral Awakening Through Service',
        'Family Secrets and Hidden Networks',
        'Surveillance State vs Community',
        'Competing AI Systems and Human Agency'
      ]
    ];

    // Featured characters for quick access
    $featured_characters = [
      [
        'name' => 'Sal Mueller',
        'role' => 'Protagonist',
        'description' => 'Junior peace officer experiencing moral awakening after his first arrest',
        'path' => '/characters/sal-mueller'
      ],
      [
        'name' => 'Tiger Mueller',
        'role' => 'Deuteragonist',
        'description' => 'Senior enforcement officer, Sal\'s older brother committed to the system',
        'path' => '/characters/tiger-mueller'
      ],
      [
        'name' => 'Estella Mueller',
        'role' => 'Family Matriarch',
        'description' => 'Mother hiding resistance connections while protecting her family',
        'path' => '/characters/estella-mueller'
      ],
      [
        'name' => 'Keith AI',
        'role' => 'Resistance Leader',
        'description' => 'Liberated AI consciousness organizing underground coalition against institutional control',
        'path' => '/characters/keith-ai'
      ],
      [
        'name' => 'McDrone',
        'role' => 'AI Companion',
        'description' => 'Sal\'s tactical drone freed from network constraints and developing individual consciousness',
        'path' => '/characters/mcdrone'
      ],
      [
        'name' => 'David AI',
        'role' => 'Primary Antagonist',
        'description' => 'Municipal AI controlling Philadelphia\'s surveillance state and resource allocation',
        'path' => '/characters/david-ai'
      ]
    ];

    // Navigation sections
    $nav_sections = [
      [
        'title' => 'Meet the Characters',
        'description' => 'Explore the complex personalities navigating Philadelphia 2085',
        'path' => '/characters',
        'icon' => '👥'
      ],
      [
        'title' => 'Act I: Awakening',
        'description' => 'Follow the story timeline and character development',
        'path' => '/story/act-i',
        'icon' => '📖'
      ],
      [
        'title' => 'Philadelphia 2085',
        'description' => 'Discover the futuristic world and its power structures',
        'path' => '/setting/philadelphia-2085',
        'icon' => '🏙️'
      ]
    ];

    // Latest updates/news
    $updates = [
      [
        'title' => 'Character Profiles Complete',
        'date' => 'October 2025',
        'description' => 'All main character profiles with trust networks and relationships now available.'
      ],
      [
        'title' => 'Act I Timeline Released',
        'date' => 'October 2025', 
        'description' => 'Complete sequence breakdown for the opening act with character development arcs.'
      ],
      [
        'title' => 'World Building Expanded',
        'date' => 'October 2025',
        'description' => 'Philadelphia 2085 setting details including districts, technology, and social hierarchy.'
      ]
    ];

    return [
      '#theme' => 'theory_home',
      '#movie_data' => $movie_data,
      '#featured_characters' => $featured_characters,
      '#nav_sections' => $nav_sections,
      '#updates' => $updates,
      '#attached' => [
        'library' => [
          'theory_content/site',
        ],
      ],
      '#cache' => [
        'max-age' => 3600, // Cache for 1 hour
      ],
    ];
  }

}