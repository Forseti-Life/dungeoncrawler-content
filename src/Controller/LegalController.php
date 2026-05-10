<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Controller for public legal-information pages.
 */
class LegalController extends ControllerBase {

  /**
   * Displays the privacy policy page.
   */
  public function privacyPolicy(): array {
    return $this->buildLegalPage(
      'Privacy Policy',
      'How Dungeon Crawler Life handles account, gameplay, and service data.',
      'This page explains what information the service stores, why it is stored, and the practical choices available to players who use the site.',
      [
        $this->buildSection(
          'What we collect',
          [
            'Account records needed to authenticate users and associate characters, campaigns, and in-game progress with the right player.',
            'Gameplay data such as campaign state, character sheets, inventory, quest progress, and related world-state information that keeps a persistent run playable over time.',
            'Operational logs and basic technical metadata used to debug failures, monitor abuse, and keep the live service stable.',
          ]
        ),
        $this->buildSection(
          'How we use it',
          [
            'To deliver the core game experience, including persistent campaigns, character progression, and account-linked world history.',
            'To improve reliability, investigate errors, and respond to misuse or security concerns that affect the service or other players.',
            'To support product development, including evaluating which systems are working, where friction exists, and what needs to be improved next.',
          ]
        ),
        $this->buildSection(
          'What we do not do',
          [
            'We do not present this service as an anonymous, stateless toy. Persistent gameplay requires persistent records.',
            'We do not claim that every page is free of logging or operational telemetry. Basic request and application logging is part of running the site responsibly.',
            'We do not treat legal pages as a substitute for good engineering judgment. Access to production systems and retained data is still handled by role, need, and operational controls.',
          ]
        ),
        $this->buildSection(
          'Player choices and questions',
          [
            'If you need account-related help, use the site contact or support path made available by the project maintainers.',
            'If you stop using the service, some records may still be retained where they are required for security, auditability, or preserving shared campaign integrity.',
            'If this policy changes materially, the updated page at this route becomes the current statement of how the service handles data.',
          ]
        ),
      ],
      [
        [
          'title' => 'Read the Terms of Service',
          'url' => Url::fromRoute('dungeoncrawler_content.terms_of_service'),
          'classes' => ['btn', 'btn-warning', 'btn-lg', 'px-4'],
        ],
        [
          'title' => 'Back to About',
          'url' => Url::fromRoute('dungeoncrawler_content.about'),
          'classes' => ['btn', 'btn-outline-light', 'btn-lg', 'px-4'],
        ],
      ]
    );
  }

  /**
   * Displays the terms of service page.
   */
  public function termsOfService(): array {
    return $this->buildLegalPage(
      'Terms of Service',
      'The practical rules for using Dungeon Crawler Life and its persistent campaign systems.',
      'Using the site means agreeing to use it responsibly, respect the service boundaries, and understand that access can change when security, abuse, or operational risk requires it.',
      [
        $this->buildSection(
          'Using the service',
          [
            'You are responsible for activity performed through your account and for keeping your login credentials reasonably secure.',
            'The service is offered as a live, evolving game platform. Features, rules, and availability may change as systems are improved or stabilized.',
            'Access to parts of the experience may depend on account state, progression, moderation decisions, or operational constraints.',
          ]
        ),
        $this->buildSection(
          'Acceptable behavior',
          [
            'Do not abuse the service, interfere with other users, scrape protected data, or intentionally probe for vulnerabilities.',
            'Do not use automation, exploits, or deceptive activity to bypass intended game, account, or site controls unless you are explicitly authorized to test those systems.',
            'Do not treat generated or shared content as permission to violate community standards, platform rules, or applicable law.',
          ]
        ),
        $this->buildSection(
          'Service boundaries',
          [
            'Dungeon Crawler Life is a managed online service, not an entitlement. The maintainers may suspend, restrict, or remove access when needed to protect the platform or community.',
            'Content, progression, and system behavior may be reset, rebalanced, or retired where the health of the product requires it.',
            'No uninterrupted-availability promise is made here. Maintenance, bugs, and active development can affect uptime or feature behavior.',
          ]
        ),
        $this->buildSection(
          'Questions and updates',
          [
            'If you do not agree with these terms, do not use the service.',
            'If the maintainers update these terms, the current version published at this route governs future use of the site.',
            'For a summary of how account and gameplay data are handled, review the Privacy Policy page.',
          ]
        ),
      ],
      [
        [
          'title' => 'Read the Privacy Policy',
          'url' => Url::fromRoute('dungeoncrawler_content.privacy_policy'),
          'classes' => ['btn', 'btn-warning', 'btn-lg', 'px-4'],
        ],
        [
          'title' => 'View How to Play',
          'url' => Url::fromRoute('dungeoncrawler_content.how_to_play'),
          'classes' => ['btn', 'btn-outline-light', 'btn-lg', 'px-4'],
        ],
      ]
    );
  }

  /**
   * Builds a shared legal-information page layout.
   *
   * @param string $eyebrow
   *   Eyebrow label.
   * @param string $title
   *   Hero title.
   * @param string $summary
   *   Hero summary copy.
   * @param array<int, array<string, mixed>> $sections
   *   Section definitions.
   * @param array<int, array<string, mixed>> $actions
   *   CTA button definitions.
   *
   * @return array
   *   Render array for the page.
   */
  private function buildLegalPage(string $eyebrow, string $title, string $summary, array $sections, array $actions): array {
    $build = [];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['legal-page-hero', 'mb-5']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'border-0', 'text-light', 'bg-dark']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
          'eyebrow' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['text-uppercase', 'small', 'fw-bold', 'mb-3', 'text-warning']],
            '#value' => $eyebrow,
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h1',
            '#attributes' => ['class' => ['display-5', 'mb-3']],
            '#value' => $title,
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'mb-0']],
            '#value' => $summary,
          ],
        ],
      ],
    ];

    $build['sections'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['legal-page-sections', 'row', 'g-4', 'mb-5']],
    ];

    foreach ($sections as $section) {
      $build['sections'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-lg-6']],
        'card' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'bg-dark', 'text-light', 'h-100', 'border-secondary']],
          'body' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card-body', 'p-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#attributes' => ['class' => ['h4', 'card-title', 'mb-3']],
              '#value' => $section['title'],
            ],
            'list' => [
              '#theme' => 'item_list',
              '#attributes' => ['class' => ['mb-0']],
              '#items' => $section['items'],
            ],
          ],
        ],
      ];
    }

    $build['cta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['legal-page-cta', 'mb-4']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'border-0', 'text-light', 'bg-dark']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5', 'text-center']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['mb-3']],
            '#value' => 'Need the related policy page?',
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex', 'justify-content-center']],
          ],
        ],
      ],
    ];

    foreach ($actions as $delta => $action) {
      $build['cta']['card']['body']['actions']['action_' . $delta] = [
        '#type' => 'link',
        '#title' => $this->t($action['title']),
        '#url' => $action['url'],
        '#attributes' => ['class' => $action['classes']],
      ];
    }

    return $build;
  }

  /**
   * Builds a legal-page section definition.
   *
   * @param string $title
   *   Section title.
   * @param array<int, string> $items
   *   Section bullet items.
   *
   * @return array<string, string|array<int, string>>
   *   Section structure.
   */
  private function buildSection(string $title, array $items): array {
    return [
      'title' => $title,
      'items' => array_map(
        static fn (string $item): TranslatableMarkup => new TranslatableMarkup($item),
        $items
      ),
    ];
  }

}
