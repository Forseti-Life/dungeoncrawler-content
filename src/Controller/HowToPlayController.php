<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for the How to Play page.
 */
class HowToPlayController extends ControllerBase {

  /**
   * Display the how to play guide.
   *
   * @return array
   *   A render array for the how to play page.
   */
  public function index() {
    $build = [];

    $first_steps = [
      [
        'title' => 'Launch one shard campaign',
        'description' => 'Start by creating a campaign shard and treating it like a real universe branch. It keeps your progress, discoveries, setbacks, and continuity history together instead of resetting after one run.',
      ],
      [
        'title' => 'Finish one deployable character',
        'description' => 'Do not stop halfway through setup. Finish the full creation flow so the character is ready to enter the tavern, bind to a shard, and survive a first live expedition.',
      ],
      [
        'title' => 'Make a cautious first run',
        'description' => 'Use the tavern to get oriented, then head onto the hexmap to learn the loop. A good first session is about understanding pacing, continuity, and risk instead of proving mastery.',
      ],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['how-to-play-hero', 'mb-5']],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'g-4', 'align-items-stretch']],
        'main' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-8']],
          'card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'how-to-play-feature-card', 'how-to-play-feature-card--hero', 'h-100']],
            'body' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
              'eyebrow' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['text-uppercase', 'small', 'fw-bold', 'mb-3', 'how-to-play-eyebrow']],
                '#value' => 'How to Play',
              ],
              'title' => [
                '#type' => 'html_tag',
                '#tag' => 'h1',
                '#attributes' => ['class' => ['display-5', 'mb-3']],
                '#value' => 'Learn the shard loop before you commit to your first live run.',
              ],
              'summary' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['lead', 'mb-4']],
                '#value' => 'Dungeon Crawler Life is built around persistent shard campaigns. You are not just launching a disposable run; you are instantiating a character into a world that remembers route choices, relationships, and consequences over time.',
              ],
              'details' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['mb-4']],
                '#value' => 'If you are new, the basic loop is straightforward: create a shard, finish one character, enter through the tavern, then use the hexmap to explore. The important shift is that save/restore, repeat runs, and future setting-crossing all feed back into one larger continuity model, so steady learning matters more than rushing.',
              ],
              'actions' => [
                '#type' => 'container',
                '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex']],
                'primary' => [
                  '#type' => 'link',
                  '#title' => $this->t('Launch Your First Shard'),
                  '#url' => Url::fromRoute('dungeoncrawler_content.campaigns'),
                  '#attributes' => ['class' => ['btn', 'btn-warning', 'btn-lg', 'px-4']],
                ],
                'secondary' => [
                  '#type' => 'link',
                  '#title' => $this->t('Create a Character'),
                  '#url' => Url::fromUri('internal:/charactersetup'),
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
            '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'how-to-play-feature-card', 'how-to-play-feature-card--field-guide', 'h-100']],
            'body' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['card-body', 'p-4']],
              'title' => [
                '#type' => 'html_tag',
                '#tag' => 'h2',
                '#attributes' => ['class' => ['h4', 'card-title', 'mb-3']],
                '#value' => 'Field guide for your first hour',
              ],
              'intro' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#attributes' => ['class' => ['mb-4']],
                '#value' => 'Three words carry most of the page: shard, tavern, and hexmap. Once those click, the rest of the experience becomes much easier to read.',
              ],
              'terms' => [
                '#type' => 'container',
                '#attributes' => ['class' => ['how-to-play-terms']],
                'campaign' => [
                  '#type' => 'container',
                  '#attributes' => ['class' => ['how-to-play-term']],
                  'title' => [
                    '#type' => 'html_tag',
                    '#tag' => 'h3',
                    '#attributes' => ['class' => ['h5', 'mb-2']],
                    '#value' => 'Shard Campaign',
                  ],
                  'text' => [
                    '#type' => 'html_tag',
                    '#tag' => 'p',
                    '#attributes' => ['class' => ['mb-0']],
                    '#value' => 'Your persistent universe branch. It keeps the history of your party, world state, and continuity decisions between sessions.',
                  ],
                ],
                'tavern' => [
                  '#type' => 'container',
                  '#attributes' => ['class' => ['how-to-play-term']],
                  'title' => [
                    '#type' => 'html_tag',
                    '#tag' => 'h3',
                    '#attributes' => ['class' => ['h5', 'mb-2']],
                    '#value' => 'Tavern',
                  ],
                  'text' => [
                    '#type' => 'html_tag',
                    '#tag' => 'p',
                    '#attributes' => ['class' => ['mb-0']],
                    '#value' => 'Your staging area. This is where you prepare, attach a character to the shard, and decide what kind of run or return attempt to make next.',
                  ],
                ],
                'hexmap' => [
                  '#type' => 'container',
                  '#attributes' => ['class' => ['how-to-play-term']],
                  'title' => [
                    '#type' => 'html_tag',
                    '#tag' => 'h3',
                    '#attributes' => ['class' => ['h5', 'mb-2']],
                    '#value' => 'Hexmap',
                  ],
                  'text' => [
                    '#type' => 'html_tag',
                    '#tag' => 'p',
                    '#attributes' => ['class' => ['mb-0']],
                    '#value' => 'The exploration layer. This is where travel, scouting, route choice, and shard-level risk start to matter.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $build['journey'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['how-to-play-journey', 'mb-5']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'how-to-play-section-card']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['mb-3']],
            '#value' => 'Your first shard campaign, step by step',
          ],
          'intro' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'text-secondary', 'mb-4']],
            '#value' => 'A good first session is not about clearing the most content. It is about learning the flow well enough that your next return, restore, or follow-up run feels intentional instead of confusing.',
          ],
          'steps' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['row', 'g-4']],
          ],
        ],
      ],
    ];

    foreach ($first_steps as $step) {
      $build['journey']['card']['body']['steps'][] = $this->buildJourneyStep($step['title'], $step['description']);
    }

    $build['review'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['how-to-play-review', 'mb-5']],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'g-4']],
        'focus' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-6']],
          'card' => $this->buildSectionCard(
            'What to focus on early',
            [
              'Treat your first few sessions like reconnaissance. You are learning how pacing, preparation, and survivability work together inside a persistent shard.',
              'The fastest way to enjoy the game is to get one character and one shard fully working before you start experimenting with edge-case builds, restores, or aggressive routes.',
            ],
            [
              'Finish the full character setup before judging the flow.',
              'Use the tavern as a planning space, not just a button you click through.',
              'Take routes that teach you something instead of routes that only look dramatic.',
              'Track what actually made a run feel safer, stranger, or more dangerous.',
            ]
          ),
        ],
        'success' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-6']],
          'card' => $this->buildSectionCard(
            'What success looks like',
            [
              'A successful first session is one where the shard makes more sense by the end than it did at the beginning. Survival, clarity, and useful information are all real wins.',
              'If you end a run understanding your class better, knowing which route felt too risky, and having a clearer plan for the next expedition or restored attempt, the session did its job.',
            ],
            [
              'You know how shard, tavern, and hexmap fit together.',
              'You can get one character from creation into a real run.',
              'You have a better sense of what to improve next time.',
              'The next session feels easier to plan than the first one did.',
            ]
          ),
        ],
      ],
    ];

    $build['tips'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['how-to-play-tips', 'mb-5']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'how-to-play-tips-card']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['card-title', 'mb-3']],
            '#value' => 'Beginner Tips',
          ],
          'intro' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['mb-4']],
            '#value' => 'These are the habits that make the game feel better quickly, especially if you are still learning what matters from one session, restore point, or shard return to the next.',
          ],
          'list' => [
            '#theme' => 'item_list',
            '#attributes' => ['class' => ['how-to-play-bullet-list', 'mb-0']],
            '#items' => [
              'Finish setup before judging the game. Many early frustrations come from entering the loop with an unfinished character or a vague shard goal.',
              'Choose readability over ambition. Your first solid character should be easy to understand, even if it is not the most advanced build on paper.',
              'Respect survival. Leaving with information and a living character is often more valuable than forcing one more risky encounter.',
              'Reset your thinking between runs. Use the tavern to ask what the shard actually needs next: safety, gear, a different route, or a different role.',
              'Think in continuity terms. Steady progress and retained knowledge matter more than a single flashy win.',
            ],
          ],
        ],
      ],
    ];

    $build['cta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['how-to-play-cta', 'mb-4']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'text-light', 'border-0', 'how-to-play-cta-card']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5', 'text-center']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['mb-3']],
            '#value' => 'Ready to launch a shard that keeps your history?',
          ],
          'text' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'mb-4']],
            '#value' => 'Start with one shard, one character, and one deliberate first run. You can grow, restore, branch, and travel from there.',
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex', 'justify-content-center']],
            'primary' => [
              '#type' => 'link',
              '#title' => $this->t('Launch Your First Shard'),
              '#url' => Url::fromRoute('dungeoncrawler_content.campaigns'),
              '#attributes' => ['class' => ['btn', 'btn-warning', 'btn-lg', 'px-4']],
            ],
            'secondary' => [
              '#type' => 'link',
              '#title' => $this->t('View the World Model'),
              '#url' => Url::fromRoute('dungeoncrawler_content.world'),
              '#attributes' => ['class' => ['btn', 'btn-outline-light', 'btn-lg', 'px-4']],
            ],
          ],
        ],
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

  /**
   * Builds a standard how-to-play section card.
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
      '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'how-to-play-section-card', 'h-100']],
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
        '#attributes' => ['class' => ['how-to-play-bullet-list', 'mb-0']],
        '#items' => $list_items,
      ];
    }

    return $card;
  }

  /**
   * Builds a single journey step card.
   *
   * @param string $title
   *   Step title.
   * @param string $description
   *   Step description.
   *
   * @return array
   *   Render array for the step.
   */
  private function buildJourneyStep(string $title, string $description): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-md-4']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['how-to-play-journey-step', 'h-100']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => $title,
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => $description,
        ],
      ],
    ];
  }

}
