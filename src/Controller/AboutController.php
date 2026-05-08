<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

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

    $feature_pillars = [
      [
        'slug' => 'forseti-guided-generation',
        'title' => 'Forseti-Guided Generation',
        'description' => 'AI generation is directed toward campaign continuity, encounter readability, and a world that stays coherent over time.',
      ],
      [
        'slug' => 'enduring-replayability',
        'title' => 'Enduring Replayability',
        'description' => 'The game is built for repeat sessions with the same roster, not one-off disposable runs that reset your investment.',
      ],
      [
        'slug' => 'campaign-scale-challenge',
        'title' => 'Campaign-Scale Challenge',
        'description' => 'Pacing, difficulty, and progression are tuned for longer arcs where decisions compound and preparation matters.',
      ],
      [
        'slug' => 'persistent-hex-world',
        'title' => 'Persistent Hex World',
        'description' => 'Travel, regrouping, and strategic movement happen in a broader realm that gives the dungeon a larger context.',
      ],
      [
        'slug' => 'classic-rpg-mechanics',
        'title' => 'Classic RPG Mechanics',
        'description' => 'The foundation stays legible to tabletop and classic CRPG players who want systems with consequence and texture.',
      ],
      [
        'slug' => 'play-anywhere',
        'title' => 'Play Anywhere',
        'description' => 'Your campaign home is meant to stay accessible across web and future mobile surfaces without losing continuity.',
      ],
    ];

    $campaign_loop = [
      [
        'title' => 'Build a roster',
        'description' => 'Create heroes meant to persist, specialize, retire, and eventually be replaced by successors in the same account history.',
      ],
      [
        'title' => 'Push the world forward',
        'description' => 'Campaigns explore new territory, uncover threats, earn equipment, and create a living record of what your group changed.',
      ],
      [
        'title' => 'Return with consequences',
        'description' => 'The next session starts from the world you left behind, so victories, losses, and unfinished problems stay meaningful.',
      ],
    ];

    $audience_cards = [
      [
        'title' => 'For long-form RPG players',
        'description' => 'This is aimed at players who miss campaigns that lasted months or years and want digital systems that respect that cadence.',
      ],
      [
        'title' => 'For roster builders',
        'description' => 'Characters are meant to define playstyles, relationships, and account history instead of being consumed and discarded.',
      ],
      [
        'title' => 'For world-first progression',
        'description' => 'The setting matters as much as the character sheet: travel, location state, and campaign memory are part of advancement.',
      ],
    ];

    $technology_columns = [
      [
        'title' => 'Game systems',
        'items' => [
          'Persistent campaigns and character rosters',
          'Hex-realm travel layered above dungeon expeditions',
          'Procedural encounters and content with campaign continuity',
          'Equipment, quests, and world state designed for long arcs',
        ],
      ],
      [
        'title' => 'Platform stack',
        'items' => [
          'Drupal CMS for structured content, routing, and operations',
          'Modern web UI with room to extend into mobile experiences',
          'H3-style geospatial thinking for region-scale world navigation',
          'Generated-image and AI services integrated into gameplay systems',
        ],
      ],
      [
        'title' => 'Operating model',
        'items' => [
          'AI is used to expand content breadth, not replace game structure',
          'Systems are tuned for reliability, readability, and reuse',
          'The product is designed as a living service, not a static one-off campaign',
          'Every layer is meant to support continuity, clarity, and replay value',
        ],
      ],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['about-hero', 'mb-5']],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'g-4', 'align-items-stretch']],
        'main' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-8']],
          'card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'about-hero-card', 'h-100']],
            'body' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
              'eyebrow' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['text-uppercase', 'small', 'fw-bold', 'mb-3', 'about-eyebrow']],
                '#value' => 'About Dungeon Crawler Life',
              ],
              'title' => [
                '#type' => 'html_tag',
                '#tag' => 'h1',
                '#attributes' => ['class' => ['display-4', 'mb-3']],
                '#value' => 'A persistent RPG home for characters, campaigns, and shared history.',
              ],
              'summary' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['lead', 'mb-4']],
                '#value' => 'Dungeon Crawler Life is built around the idea that a campaign should accumulate meaning. Characters grow, worlds remember, and a player account becomes a legacy instead of a queue of disposable runs.',
              ],
              'details' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['mb-4', 'text-secondary']],
                '#value' => 'The product combines classic RPG structure, a persistent hex realm, AI-assisted content systems, and long-term campaign framing so returning to the same world feels rewarding instead of repetitive.',
              ],
              'actions' => [
                '#type' => 'container',
                '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex']],
                'primary' => [
                  '#type' => 'link',
                  '#title' => $this->t('Create Legacy Character'),
                  '#url' => Url::fromUri('internal:/characters/create'),
                  '#attributes' => ['class' => ['btn', 'btn-warning', 'btn-lg', 'px-4']],
                ],
                'secondary' => [
                  '#type' => 'link',
                  '#title' => $this->t('Read Player Guide'),
                  '#url' => Url::fromUri('internal:/how-to-play'),
                  '#attributes' => ['class' => ['btn', 'btn-outline-light', 'btn-lg', 'px-4']],
                ],
              ],
            ],
          ],
        ],
        'side' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-4']],
          'card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'about-section-card', 'h-100']],
            'body' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['card-body', 'p-4']],
              'title' => [
                '#type' => 'html_tag',
                '#tag' => 'h2',
                '#attributes' => ['class' => ['h4', 'card-title', 'mb-3']],
                '#value' => 'What the game is optimizing for',
              ],
              'list' => [
                '#theme' => 'item_list',
                '#attributes' => ['class' => ['about-bullet-list']],
                '#items' => [
                  'Persistent campaigns instead of disposable sessions',
                  'Character identity that survives many runs',
                  'A world model that remembers consequences',
                  'AI support that serves structure instead of replacing it',
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $build['vision'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['about-story', 'mb-5']],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'g-4']],
        'left' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-7']],
          'card' => $this->buildSectionCard(
            'Why this world exists',
            [
              'Most digital dungeon runs are built to be replayed, but not remembered. Dungeon Crawler Life takes the opposite approach: the goal is to build a world worth returning to because your earlier choices still matter.',
              'That means campaigns persist, locations become familiar, and a character can complete an arc without the whole account losing continuity. Retirement is part of the design, not a failure state.',
            ],
            [
              'Campaigns should feel authored by play, not erased by the next queue.',
              'Character progression should create identity, not just higher numbers.',
              'World systems should support planning, travel, and consequence across sessions.',
            ]
          ),
        ],
        'right' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-5']],
          'audience' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['row', 'g-4']],
          ],
        ],
      ],
    ];

    foreach ($audience_cards as $card) {
      $build['vision']['row']['right']['audience'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-12']],
        'card' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'about-section-card', 'h-100']],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body', 'p-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#attributes' => ['class' => ['h5', 'card-title']],
              '#value' => $card['title'],
            ],
            'description' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['card-text', 'mb-0']],
              '#value' => $card['description'],
            ],
          ],
        ],
      ];
    }

    $build['pillars_intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['about-pillars-intro', 'mb-4', 'text-center']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['class' => ['mb-3']],
        '#value' => 'The pillars behind the experience',
      ],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['lead', 'text-secondary', 'mb-0']],
        '#value' => 'These are the product-level promises that shape how content, systems, and progression are designed.',
      ],
    ];

    $build['features'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['features', 'row', 'g-4', 'mb-5']],
    ];

    foreach ($feature_pillars as $feature) {
      $build['features'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-6', 'col-xl-4']],
        'card' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'card',
              'h-100',
              'bg-dark',
              'text-light',
              'border-primary',
              'about-feature-card',
              'about-feature-card--' . $feature['slug'],
            ],
          ],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body', 'p-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#attributes' => ['class' => ['card-title', 'h4', 'mb-3']],
              '#value' => $feature['title'],
            ],
            'description' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['card-text', 'mb-0']],
              '#value' => $feature['description'],
            ],
          ],
        ],
      ];
    }

    $build['journey'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['about-journey', 'mb-5']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'about-section-card']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['mb-3']],
            '#value' => 'The campaign loop',
          ],
          'intro' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'text-secondary', 'mb-4']],
            '#value' => 'The game is designed to create momentum across sessions, not just inside one run.',
          ],
          'steps' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['row', 'g-4']],
          ],
        ],
      ],
    ];

    foreach ($campaign_loop as $step) {
      $build['journey']['card']['body']['steps'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-4']],
        'card' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['about-journey-step', 'h-100']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#attributes' => ['class' => ['h5', 'mb-3']],
            '#value' => $step['title'],
          ],
          'description' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['mb-0']],
            '#value' => $step['description'],
          ],
        ],
      ];
    }

    $build['technology'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['technology', 'mb-5', 'about-technology']],
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['text-center', 'mb-4']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['mb-3']],
          '#value' => 'The technology and service model',
        ],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['lead', 'text-secondary', 'mb-0']],
          '#value' => 'The stack exists to support a living RPG service with clear systems, durable data, and room for AI-assisted content expansion.',
        ],
      ],
      'columns' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'g-4']],
      ],
    ];

    foreach ($technology_columns as $column) {
      $build['technology']['columns'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-lg-4']],
        'card' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'about-section-card', 'h-100']],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body', 'p-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#attributes' => ['class' => ['h4', 'card-title', 'mb-3']],
              '#value' => $column['title'],
            ],
            'list' => [
              '#theme' => 'item_list',
              '#attributes' => ['class' => ['about-bullet-list']],
              '#items' => $column['items'],
            ],
          ],
        ],
      ];
    }

    $build['team'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['team', 'mb-5', 'about-team']],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'g-4']],
        'team' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-7']],
          'card' => $this->buildSectionCard(
            'The team and product posture',
            [
              'Dungeon Crawler Life is being built as a living-world RPG service, which means the work is not just about generating more content. It is about making the generated world legible, durable, and worth investing time into.',
              'The team is focused on aligning AI systems, game rules, and world structure so the product feels like a coherent campaign platform instead of a bag of disconnected features.',
            ]
          ),
        ],
        'principles' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-5']],
          'card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'about-section-card', 'h-100']],
            'body' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['card-body', 'p-4']],
              'title' => [
                '#type' => 'html_tag',
                '#tag' => 'h2',
                '#attributes' => ['class' => ['h4', 'card-title', 'mb-3']],
                '#value' => 'Product principles',
              ],
              'list' => [
                '#theme' => 'item_list',
                '#attributes' => ['class' => ['about-bullet-list']],
                '#items' => [
                  'Persistence over disposability',
                  'Readable systems over novelty for its own sake',
                  'Player legacy over isolated single runs',
                  'Operational reliability over flashy but brittle features',
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $build['cta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cta', 'mt-5', 'text-center', 'about-cta']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'about-cta-card']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['card-title', 'mb-3']],
            '#value' => 'Ready to start a character that can actually build history?',
          ],
          'text' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'mb-4']],
            '#value' => 'Create a roster, launch a campaign, and start shaping a world you can return to instead of resetting from scratch.',
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex', 'justify-content-sm-center']],
            'primary' => [
              '#type' => 'link',
              '#title' => $this->t('Create Legacy Character'),
              '#url' => Url::fromUri('internal:/characters/create'),
              '#attributes' => ['class' => ['btn', 'btn-light', 'btn-lg', 'px-5']],
            ],
            'secondary' => [
              '#type' => 'link',
              '#title' => $this->t('View Campaigns'),
              '#url' => Url::fromUri('internal:/campaigns'),
              '#attributes' => ['class' => ['btn', 'btn-outline-light', 'btn-lg', 'px-5']],
            ],
          ],
        ],
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

  /**
   * Builds a standard dark section card.
   *
   * @param string $title
   *   Card title.
   * @param array<int, string> $paragraphs
   *   Paragraph content.
   * @param array<int, string> $list_items
   *   Optional list items.
   *
   * @return array
   *   Render array for the card.
   */
  private function buildSectionCard(string $title, array $paragraphs, array $list_items = []): array {
    $card = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'about-section-card', 'h-100']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body', 'p-4']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['card-title', 'mb-3']],
          '#value' => $title,
        ],
      ],
    ];

    foreach ($paragraphs as $delta => $paragraph) {
      $attributes = [];
      if ($delta === count($paragraphs) - 1 && empty($list_items)) {
        $attributes['class'] = ['mb-0'];
      }

      $card['body']['paragraph_' . $delta] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => $attributes,
        '#value' => $paragraph,
      ];
    }

    if (!empty($list_items)) {
      $card['body']['list'] = [
        '#theme' => 'item_list',
        '#attributes' => ['class' => ['about-bullet-list', 'mb-0']],
        '#items' => $list_items,
      ];
    }

    return $card;
  }

}
