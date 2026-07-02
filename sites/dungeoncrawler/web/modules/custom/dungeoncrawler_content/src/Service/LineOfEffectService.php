<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\HexUtilityService;

/**
 * Checks Line of Effect (LoE) and Line of Sight (LoS) between hex positions.
 *
 * Uses HexUtilityService::getLine() (cube-coordinate linear interpolation) to
 * enumerate all intermediate hexes between two positions. Each intermediate hex
 * is checked against an array of terrain obstacles.
 *
 * Obstacle flag semantics (reqs 2130–2134):
 *   - is_solid: TRUE  → blocks both LoE and LoS.
 *   - is_semi_solid: TRUE → does NOT block either LoE or LoS (portcullises,
 *     grates, etc.).
 *   - If neither flag is set, the hex is open.
 *
 * Implements reqs 2130–2134.
 */
class LineOfEffectService {

  protected HexUtilityService $hexUtility;

  public function __construct(HexUtilityService $hex_utility) {
    $this->hexUtility = $hex_utility;
  }

  /**
   * Check whether an unblocked physical path exists between two hex positions.
   *
   * Semi-solid obstacles (portcullises, grates) do NOT block LoE (req 2131).
   * Only solid obstacles block LoE (req 2130).
   *
   * The origin and destination hexes themselves are not checked (a creature
   * standing in a solid wall is an edge case handled by room placement logic).
   * Only the intermediate hexes on the line are inspected.
   *
   * @param array $from
   *   Origin hex ['q' => int, 'r' => int].
   * @param array $to
   *   Target hex ['q' => int, 'r' => int].
   * @param array $terrain_obstacles
   *   Indexed array of obstacle records. Each must have:
   *   - 'q' (int), 'r' (int): hex position.
   *   - 'is_solid' (bool): blocks LoE/LoS.
   *   - 'is_semi_solid' (bool): does NOT block.
   *
   * @return bool
   *   TRUE if line of effect exists; FALSE if a solid obstacle intervenes.
   */
  public function hasLineOfEffect(array $from, array $to, array $terrain_obstacles): bool {
    $line = $this->hexUtility->getLine($from, $to);
    $solid_set = $this->buildSolidSet($terrain_obstacles);

    // Skip first and last hex (origin/destination positions).
    $intermediate = array_slice($line, 1, count($line) - 2);
    foreach ($intermediate as $hex) {
      $key = $hex['q'] . ',' . $hex['r'];
      if (isset($solid_set[$key])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Check whether an attacker can visually perceive a target.
   *
   * Rules (reqs 2132–2134):
   * - Solid obstacles block LoS.
   * - Semi-solid obstacles do NOT block LoS.
   * - In 'darkness' lighting, attacker cannot see unless they have darkvision.
   * - 'dim_light' and 'bright_light' do not impose a vision blocker here
   *   (concealed/hidden conditions handle that separately via flat checks).
   *
   * @param array $attacker
   *   Participant with 'position_q', 'position_r', and optionally
   *   'has_darkvision' (bool).
   * @param array $target
   *   Participant with 'position_q', 'position_r'.
   * @param string $lighting
   *   Lighting condition: 'bright_light'|'dim_light'|'darkness'.
   * @param array $terrain_obstacles
   *   Same format as in hasLineOfEffect().
   *
   * @return bool
   *   TRUE if line of sight exists.
   */
  public function hasLineOfSight(array $attacker, array $target, string $lighting, array $terrain_obstacles): bool {
    // Darkness blocks unless attacker has darkvision (req 2133–2134).
    if ($lighting === 'darkness' && empty($attacker['has_darkvision'])) {
      return FALSE;
    }

    $from = [
      'q' => (int) ($attacker['position_q'] ?? 0),
      'r' => (int) ($attacker['position_r'] ?? 0),
    ];
    $to = [
      'q' => (int) ($target['position_q'] ?? 0),
      'r' => (int) ($target['position_r'] ?? 0),
    ];

    // LoS uses the same solid-obstacle check as LoE (req 2132).
    return $this->hasLineOfEffect($from, $to, $terrain_obstacles);
  }

  /**
   * Build a quick lookup set of solid hex positions (keyed by 'q,r').
   *
   * Semi-solid obstacles are excluded — they never block LoE or LoS.
   */
  private function buildSolidSet(array $terrain_obstacles): array {
    $set = [];
    foreach ($terrain_obstacles as $obs) {
      if (!empty($obs['is_solid']) && empty($obs['is_semi_solid'])) {
        $key = ((int) $obs['q']) . ',' . ((int) $obs['r']);
        $set[$key] = TRUE;
      }
    }
    return $set;
  }

}
