<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Shared location-boundary contract for editor and storyline GM surfaces.
 */
final class CanonicalSiteTopologyContract {

  public static function describe(): array {
    return [
      'terminology' => [
        'room' => 'One canonical local space with hexes and boundary ports.',
        'dungeon' => 'One continuous navigable site or zone; it need not be underground.',
        'setting' => 'A narrative place or locality that may contain one or more dungeon sites.',
        'travel_hub' => 'A shared navigation room, such as Absalom Streets, through which separate sites connect.',
      ],
      'boundary_rule' => 'Rooms belong in the same dungeon only when the player can traverse a continuous local layout between them without leaving for a street, road, wilderness, or other travel hub.',
      'same_dungeon_process' => [
        'Place each room as an exact published room version in one dungeon.',
        'Link compatible room ports through the Dungeon Editor.',
        'Designate one level entrance.',
        'Validate that every placement is reachable from that entrance.',
      ],
      'separate_site_process' => [
        'Create or reuse one dungeon for each non-contiguous navigable site.',
        'Give each site an entrance or road-connector room.',
        'Connect each site independently to the shared city, road, or wilderness hub through ExitConnectorAuthorityService.',
        'Do not create a direct dungeon link between sites whose travel passes through the shared hub.',
      ],
      'validation_stages' => [
        'dungeon_publication' => [
          'Resolve every room to an exact published version and placement.',
          'Require one level entrance and internal reachability for every placement.',
        ],
        'storyline_publication' => [
          'Bind every authored room to its site dungeon.',
          'Reuse rooms in one dungeon only for a continuous site.',
          'Represent each non-contiguous site as a separate dungeon with an entrance or road-connector room.',
        ],
        'campaign_attachment' => [
          'Reuse or instantiate every published site dungeon.',
          'Map each separate site connector to the campaign city, road, or wilderness hub.',
          'Verify gateway endpoint, port, and travel cost through ExitConnectorAuthorityService.',
          'Reject direct links that bypass the authored hub and reject unresolved site transitions.',
        ],
      ],
      'example' => [
        'locality' => 'Absalom',
        'hub' => 'tpl_room_absalom_streets',
        'sites' => [
          'Grandma\'s House dungeon containing the parlor and connected house rooms.',
          'Sewer Cistern dungeon containing the cistern entrance and underground rooms.',
        ],
        'valid_route' => 'Grandma\'s parlor -> house exit -> Absalom Streets -> sewer entrance -> cistern.',
        'invalid_route' => 'Grandma\'s parlor -> cistern as a direct room or dungeon link.',
      ],
    ];
  }

}
