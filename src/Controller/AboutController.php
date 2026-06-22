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
        'slug' => 'shard-campaigns',
        'title' => 'Shard Campaigns',
        'description' => 'Campaigns are treated as real universe shards with their own local history, pressures, and consequences rather than disposable save slots.',
      ],
      [
        'slug' => 'continuity-anchors',
        'title' => 'Continuity Anchors',
        'description' => 'Save, restore, and replay are part of the fiction. A stored state is a continuity anchor that can be resumed, studied, or branched without pretending nothing happened.',
      ],
      [
        'slug' => 'forked-characters',
        'title' => 'Forked Characters',
        'description' => 'Characters are modeled as persistent agents who can evolve, diverge, retire, return, and carry identity across timelines or future setting shifts.',
      ],
      [
        'slug' => 'cross-setting-portability',
        'title' => 'Cross-Setting Portability',
        'description' => 'A character can move from classic fantasy into cyberpunk, planar, or stranger shards as part of the world model instead of as an out-of-band import trick.',
      ],
      [
        'slug' => 'authoritative-systems',
        'title' => 'Authoritative Systems',
        'description' => 'The product is designed around server-authoritative state, readable rules, and explicit world history so persistence stays trustworthy.',
      ],
      [
        'slug' => 'living-service',
        'title' => 'Living Service',
        'description' => 'The platform is meant to grow over time, with new shards, new rules families, and new interfaces added without breaking the continuity already earned.',
      ],
    ];

    $campaign_loop = [
      [
        'title' => 'Instantiate a roster',
        'description' => 'Create characters meant to persist, specialize, retire, restore, and eventually be replaced by successors or forked variants inside the same account history.',
      ],
      [
        'title' => 'Push a shard forward',
        'description' => 'Campaigns explore new territory, uncover threats, earn equipment, and create a living record of what your group changed inside that universe branch.',
      ],
      [
        'title' => 'Return, branch, or migrate',
        'description' => 'The next session can continue from the same world, restore from an earlier anchor, or carry the character into a different shard without losing the larger continuity story.',
      ],
    ];

    $audience_cards = [
      [
        'title' => 'For long-form RPG players',
        'description' => 'This is aimed at players who miss campaigns that lasted months or years and want digital systems that respect long arcs and persistent consequences.',
      ],
      [
        'title' => 'For roster builders',
        'description' => 'Characters are meant to define playstyles, relationships, and account history instead of being consumed and discarded after a single run.',
      ],
      [
        'title' => 'For multiverse tinkerers',
        'description' => 'The setting matters as much as the character sheet: travel, location state, branching continuity, and eventual cross-setting migration are part of advancement.',
      ],
    ];

    $technology_columns = [
      [
        'title' => 'Game systems',
        'items' => [
          'Persistent shard campaigns and character rosters',
          'Hex-realm travel layered above dungeon expeditions',
          'Authoritative encounter, chat, and continuity state',
          'Equipment, quests, and world history designed for long arcs',
        ],
      ],
      [
        'title' => 'Platform stack',
        'items' => [
          'Drupal CMS for structured content, routing, and operations',
          'Modern web UI with room to extend into mobile experiences',
          'H3-style geospatial thinking for region-scale world navigation',
          'Generated-image and algorithmic generation services integrated into gameplay systems',
        ],
      ],
      [
        'title' => 'Operating model',
        'items' => [
          'Algorithmic generation expands content breadth without replacing game structure',
          'Systems are tuned for reliability, readability, and reuse',
          'The product is designed as a living multiverse service, not a static one-off campaign',
          'Every layer is meant to support continuity, clarity, portability, and replay value',
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
                '#value' => 'About Montinuity',
              ],
              'title' => [
                '#type' => 'html_tag',
                '#tag' => 'h1',
                '#attributes' => ['class' => ['display-4', 'mb-3']],
                '#value' => 'A persistent multiverse RPG for shard campaigns, portable characters, and shared history.',
              ],
              'summary' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['lead', 'mb-4']],
                '#value' => 'Montinuity is built around the idea that a campaign shard should accumulate meaning. Characters grow, worlds remember, and an account becomes a continuity graph instead of a queue of disposable runs.',
              ],
              'details' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['mb-4', 'text-secondary']],
                '#value' => 'The product combines classic RPG structure, a persistent hex realm, authoritative gameplay systems, and a multiverse continuity model so returning to the same world or moving into a new shard still feels coherent.',
              ],
              'actions' => [
                '#type' => 'container',
                '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex']],
                'primary' => [
                  '#type' => 'link',
                  '#title' => $this->t('Create a Portable Character'),
                  '#url' => Url::fromUri('internal:/charactersetup'),
                  '#attributes' => ['class' => ['btn', 'btn-warning', 'btn-lg', 'px-4']],
                ],
                'secondary' => [
                  '#type' => 'link',
                  '#title' => $this->t('Read the Shard Loop'),
                  '#url' => Url::fromRoute('dungeoncrawler_content.how_to_play'),
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
                  'Shard campaigns instead of disposable sessions',
                  'Character identity that survives many runs and restores',
                  'A world model that remembers consequences across branches',
                  'Algorithmic support that serves structure instead of replacing it',
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
              'Most digital dungeon runs are built to be replayed, but not remembered. Montinuity takes the opposite approach: the goal is to build shard worlds worth returning to because your earlier choices still matter.',
              'That means campaigns persist, locations become familiar, and a character can complete an arc without the whole account losing continuity. Retirement, restoration, and forked variants are part of the design, not failure states.',
            ],
            [
              'Campaigns should feel authored by play, not erased by the next queue.',
              'Character progression should create identity, not just higher numbers.',
              'World systems should support planning, travel, continuity, and consequence across sessions.',
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
        '#value' => 'These are the product-level promises that shape how content, systems, and progression are designed across every shard the platform can host.',
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
            '#value' => 'The game is designed to create momentum across sessions, restores, and future shard jumps, not just inside one isolated run.',
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
          '#value' => 'The stack exists to support a living multiverse RPG service with clear systems, durable data, and room for algorithmic content expansion.',
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
              'Montinuity is being built as a living-world RPG service, which means the work is not just about generating more content. It is about making every shard legible, durable, and worth investing time into.',
              'The team is focused on aligning generation systems, game rules, and world structure so the product feels like a coherent multiverse platform instead of a bag of disconnected features.',
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
                  'Player continuity over isolated single runs',
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
            '#value' => 'Ready to start a character that can actually travel with its history?',
          ],
          'text' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'mb-4']],
            '#value' => 'Create a roster, launch a shard, and start shaping a world you can return to, restore, or carry forward instead of resetting from scratch.',
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex', 'justify-content-sm-center']],
            'primary' => [
              '#type' => 'link',
              '#title' => $this->t('Create a Portable Character'),
              '#url' => Url::fromUri('internal:/charactersetup'),
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
