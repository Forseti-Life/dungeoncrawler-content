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

    $build['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['how-to-play-intro', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light border-primary">
          <div class="card-body">
            <h2 class="card-title">Welcome Back, Adventurer.</h2>
            <p class="lead">Dungeon Crawler Forseti Life is built for longtime RPG players who want more than one-off runs: a permanent world where your characters can live, grow, and eventually retire into a shared legacy.</p>
          </div>
        </div>',
      ],
    ];

    $build['guides'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['gameplay-guides', 'accordion', 'mb-4']],
      '#prefix' => '<div class="accordion" id="howToPlayAccordion">',
      '#suffix' => '</div>',
    ];

    // Gameplay guides
    $guides = [
      [
        'id' => 'getting-started',
        'title' => '🎮 Getting Started',
        'content' => '<ol class="list-group list-group-numbered">
          <li class="list-group-item">Create or open a campaign to establish your long-term home in the universe</li>
          <li class="list-group-item">Choose a character built for your preferred RPG style: frontline, arcane, support, or precision</li>
          <li class="list-group-item">Complete all 8 creation steps so your character is launch-ready for campaign play</li>
          <li class="list-group-item">Enter through Tavern Entrance and bind your character to the campaign</li>
          <li class="list-group-item">Launch into the hexmap and begin your first lasting expedition</li>
        </ol>',
      ],
      [
        'id' => 'legacy-loop',
        'title' => '🏰 Campaign Legacy Loop',
        'content' => '<ul class="list-group">
          <li class="list-group-item"><strong>Home Base:</strong> Campaigns are your persistent world containers, not throwaway saves</li>
          <li class="list-group-item"><strong>Character Continuity:</strong> Reuse trusted heroes or roll new ones for different campaign arcs</li>
          <li class="list-group-item"><strong>Persistent Progress:</strong> Dungeons, encounters, and outcomes remain tied to campaign history</li>
          <li class="list-group-item"><strong>Retirement Path:</strong> Characters can complete careers and remain part of your account legacy</li>
          <li class="list-group-item"><strong>Growing Universe:</strong> Every campaign contributes to a constantly expanding Dungeon Crawler world</li>
        </ul>',
      ],
      [
        'id' => 'character-classes',
        'title' => '⚔️ Character Classes',
        'content' => '<div class="row g-3">
          <div class="col-md-6">
            <h5>Fighter</h5>
            <p>Reliable frontliners for players who like tactical positioning, survivability, and weapon mastery.</p>
          </div>
          <div class="col-md-6">
            <h5>Wizard</h5>
            <p>High-ceiling casters for planners who enjoy spell timing, battlefield control, and flexible answers.</p>
          </div>
          <div class="col-md-6">
            <h5>Cleric</h5>
            <p>Steady anchors who heal, buff, and hold party momentum through long campaign sessions.</p>
          </div>
          <div class="col-md-6">
            <h5>Rogue</h5>
            <p>Precision specialists who reward map awareness, clever routing, and setup-heavy combat play.</p>
          </div>
        </div>',
      ],
      [
        'id' => 'combat',
        'title' => '⚡ Combat System',
        'content' => '<ul class="list-group">
          <li class="list-group-item"><strong>Turn-Based Tactical Flow:</strong> Encounters run in clear rounds with deterministic combat state</li>
          <li class="list-group-item"><strong>Action Discipline:</strong> Decide each turn between offense, movement, utility, and survival</li>
          <li class="list-group-item"><strong>Durability Matters:</strong> HP and AC tuning affects long-run campaign viability</li>
          <li class="list-group-item"><strong>Critical Moments:</strong> Smart sequencing can convert close turns into momentum swings</li>
          <li class="list-group-item"><strong>Legacy Impact:</strong> Fight outcomes directly shape your campaign timeline and party future</li>
        </ul>',
      ],
      [
        'id' => 'exploration',
        'title' => '🗺️ Dungeon Exploration',
        'content' => '<p>The world evolves as you play, while preserving campaign continuity:</p>
        <ul class="list-group">
          <li class="list-group-item">Room states, campaign progress, and world decisions persist over time</li>
          <li class="list-group-item">Fog-of-war and line-of-sight reward careful movement and reconnaissance</li>
          <li class="list-group-item">AI-assisted content generation keeps encounters varied without discarding continuity</li>
          <li class="list-group-item">Dungeon and tavern entry flows let you continue or pivot a campaign session quickly</li>
          <li class="list-group-item">Exploration choices become part of your character\'s long-form story</li>
        </ul>',
      ],
      [
        'id' => 'items',
        'title' => '💎 Items & Equipment',
        'content' => '<p>Build enduring loadouts that support your character\'s full career arc:</p>
        <ul class="list-group">
          <li class="list-group-item"><strong>Practical Foundations:</strong> Start with reliable gear that fits your class role</li>
          <li class="list-group-item"><strong>Campaign Loot:</strong> Expand your toolkit with items discovered in persistent dungeons</li>
          <li class="list-group-item"><strong>Role Identity:</strong> Use equipment choices to define your signature play pattern</li>
          <li class="list-group-item"><strong>Recovery Tools:</strong> Keep consumables ready for deep-run survivability</li>
          <li class="list-group-item"><strong>Progression Tiers:</strong> Build from common utility to high-impact legacy artifacts</li>
        </ul>',
      ],
      [
        'id' => 'progression',
        'title' => '📈 Character Progression',
        'content' => '<ul class="list-group">
          <li class="list-group-item">Advance through campaign milestones, not isolated one-shot sessions</li>
          <li class="list-group-item">Level growth supports long-term identity: survivability, power curve, and specialization</li>
          <li class="list-group-item">Maintain a roster of active heroes while honoring retired legends in your account history</li>
          <li class="list-group-item">Use each campaign arc to sharpen strategy for your next generation of characters</li>
          <li class="list-group-item">Treat progression as legacy building inside a universe that keeps expanding</li>
        </ul>',
      ],
    ];

    foreach ($guides as $index => $guide) {
      $show = $index === 0 ? 'show' : '';
      $collapsed = $index === 0 ? '' : 'collapsed';
      $expanded = $index === 0 ? 'true' : 'false';

      $build['guides'][] = [
        '#markup' => '<div class="accordion-item bg-dark text-light">
          <h2 class="accordion-header">
            <button class="accordion-button bg-dark text-light ' . $collapsed . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . $guide['id'] . '" aria-expanded="' . $expanded . '" aria-controls="collapse' . $guide['id'] . '">
              ' . $guide['title'] . '
            </button>
          </h2>
          <div id="collapse' . $guide['id'] . '" class="accordion-collapse collapse ' . $show . '" data-bs-parent="#howToPlayAccordion">
            <div class="accordion-body">
              ' . $guide['content'] . '
            </div>
          </div>
        </div>',
      ];
    }

    $build['tips'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tips', 'mt-5']],
      'content' => [
        '#markup' => '<div class="card bg-success text-light">
          <div class="card-body">
            <h3 class="card-title">💡 Pro Tips</h3>
            <ul>
              <li>Build characters with campaign longevity in mind, not just early burst damage</li>
              <li>Keep at least one dependable all-rounder in your roster for difficult recovery runs</li>
              <li>Use campaign archive and dungeon selection as tools for pacing long-term stories</li>
              <li>Track what tactics work in specific encounters and evolve your next build accordingly</li>
              <li>Retire characters intentionally when their story feels complete, then start a successor</li>
              <li>Treat each campaign as a chapter in the same growing Dungeon Crawler universe</li>
            </ul>
          </div>
        </div>',
      ],
    ];

    $build['cta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cta', 'mt-4', 'text-center']],
      'content' => [
        '#markup' => '<a href="' . Url::fromRoute('dungeoncrawler_content.campaigns')->toString() . '" class="btn btn-primary btn-lg">Build Your First Legacy Campaign</a>',
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

}
