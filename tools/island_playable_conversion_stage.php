<?php

/**
 * Stage a reviewed original Island adaptation without publishing the release.
 *
 * Source images are illustrations in the room gallery, not tactical overlays.
 * Run with the site-local Drush and a 2 GB PHP limit.
 */

function islandReviewedScene(string $name, string $description, string $role, string $notes, string $objective, string $next): array {
  return [
    'name' => $name,
    'description' => $description,
    'map_review' => [
      'role' => $role,
      'terrain_basis' => 'original_canonical',
      'notes' => $notes,
    ],
    'objective' => [
      'description' => $objective,
      'next_step' => $next,
    ],
  ];
}

$island_revision = 'island-survey-v1';
$island_reviewed_quest = [
  'template_id' => 'island-survey-v1',
  'name' => 'Trace the Island Waymarks',
  'description' => 'Chart ten connected places across the island, recording a route through roads, caverns, and old stone passages.',
  'rights_status' => 'project_original',
];
$island_reviewed_scenes = [
  'GoblinLair3.png' => islandReviewedScene(
    'The Slate Ward', 'A narrow original passage bends between weathered stone alcoves. An old survey marker points onward.',
    'interpretive_gallery', 'The illustration contains black voids and drawn doors; the small canonical room is a separate traversable adaptation, not a trace of those walls.',
    'Chart the slate passage and its survey marker.', 'Explore the slate ward and mark its position on the island route.'
  ),
  'GoblinLair2.png' => islandReviewedScene(
    'The Ember Ward', 'Loose red stone and a faint mineral glow distinguish this original chamber from the outer passages.',
    'interpretive_gallery', 'The illustrated lair has many chambers and doorways beyond this room footprint; none are claimed as canonical walls or doors.',
    'Survey the ember-lit passage.', 'Follow the connected route into the ember ward.'
  ),
  'The Island.jpg' => islandReviewedScene(
    'Island Lookout', 'A sheltered overlook offers a view of the island route. The map in the gallery shows the wider land, not the floor beneath the party.',
    'overland_overview', 'The island painting is an overview at a different scale; it cannot determine tactical passability or the generated chamber footprint.',
    'Study the island from the lookout.', 'Reach the lookout and record the larger route shown in the gallery.'
  ),
  'GoblinLair1.png' => islandReviewedScene(
    'The Outer Ward', 'The first stone crossing opens onto a quiet original exploration room with a clear route through it.',
    'interpretive_gallery', 'The source image depicts black voids and a larger network of doors; the generated room is independent original terrain.',
    'Find the safe route through the outer ward.', 'Explore the outer ward and locate its onward passage.'
  ),
  'MonsterLairEncounter.png' => islandReviewedScene(
    'Stone Hollow', 'A broad original cavern provides a resting point beneath a rough arch of stone.',
    'interpretive_gallery', 'Rock boundaries in the illustration are not raster-derived canonical obstacles; this small room has its own validated walkable hexes.',
    'Chart the stone hollow.', 'Enter the hollow and add its passage to the survey.'
  ),
  'MountainRoadEncounter.png' => islandReviewedScene(
    'Highland Bend', 'The original trail turns through a sheltered rise where travelers can orient themselves.',
    'interpretive_gallery', 'Cliffs and path curves in the road painting are illustrative; the canonical route and passability are authored separately.',
    'Record the highland bend.', 'Cross the rise and trace the next section of the route.'
  ),
  'ForestRoadEncounter.png' => islandReviewedScene(
    'Greenway Crossing', 'A quiet original woodland crossing links the inland passage with the open trail.',
    'interpretive_gallery', 'Illustrated tree positions and the curved road are not obstacle or terrain data in this generated hex room.',
    'Locate the greenway crossing.', 'Explore the woodland crossing and continue the survey.'
  ),
  'PlainsRoadEncounter.png' => islandReviewedScene(
    'Meadow Track', 'Low grasses surround an original open-air waypoint along the island trail.',
    'interpretive_gallery', 'The road line and vegetation shown in the image do not align with the small canonical hex footprint.',
    'Mark the meadow track.', 'Visit the meadow waypoint and record its position.'
  ),
  'MountainRiverRoadEncounter.png' => islandReviewedScene(
    'Riverside Rise', 'The original path climbs above moving water, offering another landmark for the survey.',
    'interpretive_gallery', 'Water and cliff faces in the background are not canonical river or impassable hexes; this is a separate playable adaptation.',
    'Survey the riverside rise.', 'Reach the rise and record the crossing above the water.'
  ),
  'GoblinLairEntranceCartoon.png' => islandReviewedScene(
    'Moss Gate', 'The original route ends at a mossy stone threshold where the party can complete its island survey.',
    'interpretive_gallery', 'The illustrated cave entrance and vegetation are gallery art; canonical entry, terrain and the terminal route are validated independently.',
    'Complete the survey at the moss gate.', 'Reach the last threshold and finish the island route.'
  ),
];

require __DIR__ . '/island_map_conversion_stage.php';
