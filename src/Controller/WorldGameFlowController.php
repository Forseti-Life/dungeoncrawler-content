<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Documents the end-to-end gameplay process flow.
 */
class WorldGameFlowController extends ControllerBase {

  /**
   * Render the public game-flow documentation page.
   */
  public function index(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container', 'py-4', 'py-lg-5', 'world-game-flow'],
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/world-game-flow',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['world-game-flow__hero', 'mb-4'],
      ],
      'card' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['card', 'border-0', 'text-light', 'world-game-flow__hero-card'],
        ],
        'body' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['card-body', 'p-4', 'p-lg-5'],
          ],
          'eyebrow' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => [
              'class' => ['text-uppercase', 'small', 'fw-bold', 'mb-3', 'world-game-flow__eyebrow'],
            ],
            '#value' => 'World / Game Flow',
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h1',
            '#attributes' => ['class' => ['display-5', 'mb-3']],
            '#value' => 'How a campaign run moves from tavern entry to exploration, chat, combat, and back again.',
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#attributes' => ['class' => ['lead', 'mb-4']],
            '#value' => 'This page documents the live runtime loop: a campaign launches through the tavern, the hexmap enters exploration, room chat stays active inside that loop, combat interrupts through a phase transition, and resolved encounters return the run to exploration with persistent state still intact.',
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['d-grid', 'gap-3', 'd-sm-flex']],
            'campaigns' => [
              '#type' => 'link',
              '#title' => $this->t('View Campaigns'),
              '#url' => Url::fromRoute('dungeoncrawler_content.campaigns'),
              '#attributes' => ['class' => ['btn', 'btn-warning', 'btn-lg', 'px-4']],
            ],
            'world' => [
              '#type' => 'link',
              '#title' => $this->t('Back to World'),
              '#url' => Url::fromRoute('dungeoncrawler_content.world'),
              '#attributes' => ['class' => ['btn', 'btn-outline-light', 'btn-lg', 'px-4']],
            ],
          ],
        ],
      ],
    ];

    $build['overview'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row', 'g-4', 'mb-4']],
      'player_view' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-lg-6']],
        'card' => $this->buildTextCard(
          'Player-facing loop',
          'Think of the run as one persistent cycle. Tavern entry sets the stage, exploration handles movement and discovery, chat covers in-room conversation, encounter mode takes over when danger commits, and the run returns to exploration when the fight resolves.',
          [
            'A campaign and selected character define the launch context.',
            'Startup narration and room state are delivered before free exploration begins.',
            'Chat is part of exploration, not a separate world map or menu mode.',
            'Combat is a temporary phase shift, not a separate campaign instance.',
          ]
        ),
      ],
      'system_view' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-lg-6']],
        'card' => $this->buildTextCard(
          'System-facing loop',
          'The runtime stays server-authoritative. Hexmap bootstraps the launch payload, GameCoordinator loads the current state and unseen events, phase handlers route intents, and the server returns canonical game state, available actions, events, and narration after each meaningful step.',
          [
            'HexMapController hydrates launch context and dungeon payload.',
            'GameCoordinatorService ensures game_state, campaign_clock, and initial startup events exist.',
            'PhaseManager merges server state so exploration and encounter data stay in sync.',
            'Narration, chat, world mutations, and combat state all flow back through the same runtime shell.',
          ]
        ),
      ],
    ];

    $build['diagrams'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['world-game-flow__diagram-stack']],
    ];

    $build['diagrams']['campaign_loop'] = $this->buildDiagramCard(
      'Primary campaign loop',
      'End-to-end run lifecycle',
      'This is the top-level player journey for an active campaign run.',
      <<<'MERMAID'
flowchart TD
  A[Campaign selected] --> B[Tavern entrance]
  B --> C[Choose active character]
  C --> D[Launch hexmap runtime]
  D --> E[Load canonical game state]
  E --> F[Startup room_entered event and narration]
  F --> G[Exploration phase]
  G --> H[Movement, search, rest, inspect]
  G --> I[Room chat with NPCs or GM]
  H --> J{Threat committed or hostile action?}
  I --> G
  J -- No --> G
  J -- Yes --> K[Transition to encounter]
  K --> L[Combat loop]
  L --> M{Encounter resolved?}
  M -- No --> L
  M -- Yes --> N[Return to exploration]
  N --> O{Continue run or leave session?}
  O -- Continue --> G
  O -- Leave --> P[Persist campaign state for next session]
MERMAID,
      [
        'The tavern is the first in-world location for a campaign run.',
        'Exploration is the default phase after startup state has been applied.',
        'Combat returns to the same persistent campaign state instead of forking a new run.',
      ]
    );

    $build['diagrams']['startup_flow'] = $this->buildDiagramCard(
      'Launch and tavern startup',
      'How the run boots into the first room',
      'The launch path starts before the player can move: route selection, state hydration, startup narration, and the first exploration-ready room view.',
      <<<'MERMAID'
flowchart LR
  A[/campaigns/{campaign_id}/tavernentrance/] --> B[Select character]
  B --> C[/hexmap with campaign and character context/]
  C --> D[HexMapController hydrates launch context]
  D --> E[Load dungeon payload and room entities]
  E --> F[GameCoordinatorService getFullState]
  F --> G[Ensure game_state and campaign_clock]
  G --> H[Bootstrap startup room_entered event if missing]
  H --> I[Return initial events, actions, and phase]
  I --> J[GameCoordinator applies initial state]
  J --> K[Narration overlay and MP3 playback]
  K --> L[Exploration begins in the tavern]
MERMAID,
      [
        'Startup narration is delivered as a real room_entered event.',
        'Campaign clock and current phase are part of the canonical state payload.',
        'The client processes initial events before normal polling continues.',
      ]
    );

    $build['diagrams']['exploration_flow'] = $this->buildDiagramCard(
      'Exploration loop',
      'Movement, investigation, and room-state updates',
      'Exploration is the default runtime loop on the hexmap. Most non-combat actions stay here.',
      <<<'MERMAID'
flowchart TD
  A[Exploration phase active] --> B[Player chooses an intent]
  B --> C{Intent type}
  C -->|Move| D[Pathfind and move on the hexmap]
  C -->|Search| E[Run exploration search]
  C -->|Rest| F[Run exploration rest action]
  C -->|Talk| G[Open room chat or direct chat]
  D --> H[Server updates game_state and event log]
  E --> H
  F --> H
  G --> I[Chat reply and transcript update]
  H --> J{Entered a new room?}
  J -- Yes --> K[Emit room_entered narration and MP3]
  J -- No --> L[Stay in current room loop]
  I --> M[Exploration remains active]
  K --> M
  L --> M
  M --> N{Encounter trigger?}
  N -- No --> A
  N -- Yes --> O[Transition into encounter]
MERMAID,
      [
        'Movement, search, and rest stay in exploration unless a transition trigger is hit.',
        'Room narration is first-visit gated and emitted through the event pipeline.',
        'Chat can update world context without forcing a phase change.',
      ]
    );

    $build['diagrams']['chat_flow'] = $this->buildDiagramCard(
      'Chat loop',
      'Conversation inside the current room',
      'Room chat runs inside the live hexmap shell so players can converse without leaving the run. The GM path can be deterministic, cached, or LLM-backed; NPC room reactions and private channels each have their own model operations.',
      <<<'MERMAID'
flowchart TD
  A[Player sends room message] --> B[RoomChatService persists player message]
  B --> C{Channel type}
  C -->|Room| D[Resolve room context, intent, cache, deterministic shortcuts]
  C -->|Private whisper or ability channel| E[LLM call: channel_npc_reply]
  D --> F{Deterministic or cache hit?}
  F -- Yes --> G[Return GM reply without LLM]
  F -- No --> H[LLM call: room_chat_gm_reply]
  H --> I{Mechanical actions invalid?}
  I -- Yes --> J[LLM call: room_chat_gm_retry]
  I -- No --> K[Accept parsed GM reply]
  J --> K
  G --> L{Room NPC interjections enabled?}
  K --> L
  E --> M[Return private NPC reply]
  L -- No --> N[Send final chat payload to UI]
  L -- Yes --> O[Per candidate NPC: LLM call npc_interjection_eval_single]
  O --> P{NPC should speak?}
  P -- No --> Q[Skip NPC turn]
  P -- Yes --> R[LLM call: npc_room_dialogue]
  Q --> N
  R --> N
  M --> N
  N --> S[UI updates transcript inside exploration]
MERMAID,
      [
        'Room channel GM narration uses operation `room_chat_gm_reply`, with optional `room_chat_gm_retry` if authoritative action validation fails.',
        'Private channels bypass the GM layer and go straight to `channel_npc_reply` for in-character NPC speech.',
        'Room interjections are two-stage: `npc_interjection_eval_single` decides whether an NPC speaks, then `npc_room_dialogue` generates the actual line for NPCs that passed.',
        'Deterministic shortcuts and GM response cache hits can skip some or all LLM calls for low-variance turns.',
      ]
    );

    $build['diagrams']['chat_llm_calls'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'world-game-flow__diagram-card']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
        'eyebrow' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-uppercase', 'small', 'fw-bold', 'mb-2', 'world-game-flow__eyebrow']],
          '#value' => 'Chat workflow detail',
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h3', 'mb-3']],
          '#value' => 'Every LLM call in the chat pipeline',
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-4', 'world-game-flow__summary']],
          '#value' => 'These are the concrete model operations used by RoomChatService. They do not all fire on every turn; the pipeline branches based on channel type, deterministic shortcuts, response cache hits, and whether NPC interjections are even eligible.',
        ],
        'table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['world-game-flow__table']],
          '#header' => ['Order', 'Operation', 'When it runs', 'Purpose'],
          '#rows' => [
            [
              '1',
              'room_chat_gm_reply',
              'Room channel only, after deterministic handling and cache lookup both miss.',
              'Primary GM narration/action generation for the current player turn.',
            ],
            [
              '2',
              'room_chat_gm_retry',
              'Only after the primary GM reply proposed mechanical actions that failed authoritative validation.',
              'Regenerates the GM reply using a reality snapshot and validation errors.',
            ],
            [
              '3',
              'npc_interjection_eval_single',
              'Room channel only, once per candidate NPC after the GM reply, excluding directly addressed NPCs that are already forced into consideration.',
              'Binary SPEAK/PASS gate to decide whether a specific NPC should take a turn this round.',
            ],
            [
              '4',
              'npc_room_dialogue',
              'Only for NPCs that passed the interjection gate, unless a deterministic NPC response already handled them.',
              'Produces the actual in-room spoken line for that NPC.',
            ],
            [
              'A',
              'channel_npc_reply',
              'Private whisper/ability channels instead of the room GM path.',
              'Generates the direct in-character NPC reply for that private channel conversation.',
            ],
          ],
        ],
        'notes_heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h5', 'mt-4', 'mb-2']],
          '#value' => 'Branching rules',
        ],
        'notes' => $this->buildBulletList([
          'If the turn is handled by deterministic room logic, the chat response can complete with zero LLM calls.',
          'If a low-variance GM narration turn hits the response cache, the GM reply also completes with zero GM LLM calls.',
          'Private channels use channel_npc_reply instead of room_chat_gm_reply.',
          'Each candidate interjecting NPC is evaluated separately, so crowded rooms can trigger multiple npc_interjection_eval_single calls and multiple npc_room_dialogue calls in one player turn.',
        ]),
      ],
    ];

    $build['diagrams']['combat_flow'] = $this->buildDiagramCard(
      'Combat loop',
      'Encounter phase from initiation through resolution',
      'Combat takes over when the run commits to a hostile encounter, then hands control back after the encounter ends.',
      <<<'MERMAID'
flowchart TD
  A[Encounter phase starts] --> B[Create or sync combat encounter]
  B --> C[Roll initiative and build turn order]
  C --> D[Active combatant turn]
  D --> E{Whose turn?}
  E -->|Player| F[Choose strike, stride, spell, skill, interact, end turn]
  E -->|NPC| G[Auto-play AI or fallback turn]
  F --> H[Combat API validates and resolves action]
  G --> H
  H --> I[Update HP, conditions, positions, logs, and world delta]
  I --> J{Encounter over?}
  J -- No --> K[Advance turn and round]
  K --> D
  J -- Yes --> L[End encounter and emit narration]
  L --> M[Transition back to exploration]
MERMAID,
      [
        'Encounter state stays server-authoritative through combat APIs and services.',
        'NPC turns can auto-play through AI or fallback logic.',
        'Resolved encounters transition back to exploration instead of trapping the run in combat mode.',
      ]
    );

    $build['diagrams']['authority_flow'] = $this->buildDiagramCard(
      'Authority and transition flow',
      'How client actions become canonical state',
      'The client proposes intent; the server returns the state that actually counts.',
      <<<'MERMAID'
flowchart LR
  A[Player click or action] --> B[GameCoordinatorApi]
  B --> C[GameCoordinatorController]
  C --> D[Phase-specific handler]
  D --> E[Update game_state, campaign_clock, events, and world state]
  E --> F[Return canonical state and available actions]
  F --> G[PhaseManager merges server state]
  G --> H[Hexmap UI, action rail, chat, and narration refresh]
MERMAID,
      [
        'Phase handlers own the rules for exploration, encounter, and downtime transitions.',
        'The action rail, narration overlay, and current phase all refresh from returned state.',
        'This is why campaign time, room narration, and combat phase changes must stay aligned with server responses.',
      ]
    );

    return $build;
  }

  /**
   * Build a text explainer card.
   */
  protected function buildTextCard(string $title, string $summary, array $bullets): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['card', 'h-100', 'world-game-flow__text-card'],
      ],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body', 'p-4']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h4', 'mb-3']],
          '#value' => $title,
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-3']],
          '#value' => $summary,
        ],
        'list' => $this->buildBulletList($bullets),
      ],
    ];
  }

  /**
   * Build one Mermaid diagram card.
   */
  protected function buildDiagramCard(string $eyebrow, string $title, string $summary, string $diagram, array $notes): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'world-game-flow__diagram-card']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body', 'p-4', 'p-lg-5']],
        'eyebrow' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-uppercase', 'small', 'fw-bold', 'mb-2', 'world-game-flow__eyebrow']],
          '#value' => $eyebrow,
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h3', 'mb-3']],
          '#value' => $title,
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-4', 'world-game-flow__summary']],
          '#value' => $summary,
        ],
        'diagram' => [
          '#markup' => '<div class="world-game-flow__mermaid-shell"><div class="world-game-flow__mermaid" data-mermaid-diagram>'
            . Html::escape(trim($diagram))
            . '</div></div>',
        ],
        'notes_heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h5', 'mt-4', 'mb-2']],
          '#value' => 'Key points',
        ],
        'notes' => $this->buildBulletList($notes),
      ],
    ];
  }

  /**
   * Build a plain bullet list.
   */
  protected function buildBulletList(array $items): array {
    $build = [
      '#theme' => 'item_list',
      '#items' => [],
      '#attributes' => ['class' => ['world-game-flow__list', 'mb-0']],
    ];

    foreach ($items as $item) {
      $build['#items'][] = ['#markup' => Html::escape($item)];
    }

    return $build;
  }

}
