<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for the World/Lore page.
 */
class WorldController extends ControllerBase {

  /**
   * Display the world and lore information.
   *
   * @return array
   *   A render array for the world page.
   */
  public function index() {
    $build = [];

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['world-intro', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light border-warning">
          <div class="card-body">
            <h2 class="card-title">The Living Dungeon</h2>
            <p class="lead">A persistent universe where campaigns endure and characters build long-term history.</p>
          </div>
        </div>',
      ],
    ];

    $build['lore'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['world-lore', 'row', 'g-4']],
    ];

    // World sections
    $sections = [
      [
        'title' => 'The Endless Depths',
        'icon' => '🏛️',
        'content' => 'Deep beneath the surface world lies a dungeon that changes over time but remembers campaign outcomes. Every expedition adds to a persistent timeline, giving returning players familiar ground and fresh danger in the same world.',
      ],
      [
        'title' => 'AI-Born Creatures',
        'icon' => '🐉',
        'content' => 'The denizens of this dungeon are never generic filler. AI-assisted generation creates varied foes and encounter patterns while preserving campaign continuity, so each run feels new without feeling disconnected.',
      ],
      [
        'title' => 'Procedural Treasures',
        'icon' => '⚔️',
        'content' => 'Weapons, armor, and artifacts support long-form character identity. Your loadout choices matter over time, helping each hero evolve from first expedition to eventual retirement.',
      ],
      [
        'title' => 'Dynamic Quests',
        'icon' => '📜',
        'content' => 'Quest pressure emerges from world state, campaign choices, and tactical consequences. The result is a story loop that rewards returning players who want continuity, not just isolated one-shots.',
      ],
      [
        'title' => 'The Hex Realm',
        'icon' => '🗺️',
        'content' => 'The world above is divided into hexagonal regions with distinct pressure and opportunity. Campaigns can revisit, regroup, and branch, creating a home base rhythm that veteran RPG players expect.',
      ],
      [
        'title' => 'Living History',
        'icon' => '📚',
        'content' => 'Every action leaves a mark in your campaign history. Characters can complete arcs, retire, and give way to successors, allowing your account to grow into a true multi-generation RPG universe.',
      ],
    ];

    foreach ($sections as $section) {
      $build['lore'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-6', 'col-lg-4']],
        'card' => [
          '#markup' => '<div class="card h-100 bg-dark text-light border-secondary">
            <div class="card-body">
              <h3 class="card-title">
                <span class="display-4">' . $section['icon'] . '</span>
                ' . $section['title'] . '
              </h3>
              <p class="card-text">' . $section['content'] . '</p>
            </div>
          </div>',
        ],
      ];
    }

    $build['call_to_action'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cta', 'mt-5', 'text-center']],
      'content' => [
        '#markup' => '<div class="card bg-warning text-dark">
          <div class="card-body">
            <h3 class="card-title">Ready to Start Your Long-Term Campaign Home?</h3>
            <p class="card-text">Create a campaign, choose a character, and begin building your legacy in the Forseti universe.</p>
            <a href="' . Url::fromRoute('dungeoncrawler_content.campaigns')->toString() . '" class="btn btn-dark btn-lg">Open Campaign Hub</a>
          </div>
        </div>',
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

}
