/**
 * @file
 * Shared service adapter for campaign visited-location groups used by Action Rail navigation.
 */

export async function fetchVisitedNavigateLocationGroups(campaignId) {
  const numericCampaignId = Number(campaignId || 0);
  if (!numericCampaignId) {
    return [];
  }

  const response = await fetch(`/api/campaign/${numericCampaignId}/visited-locations`, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'include',
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.success) {
    throw new Error(data.error || 'Unable to load visited locations.');
  }

  return (Array.isArray(data.dungeons) ? data.dungeons : [])
    .map((group) => ({
      dungeonId: String(group?.dungeon_id || ''),
      dungeonName: String(group?.dungeon_name || group?.dungeon_id || 'Dungeon'),
      mapId: String(group?.map_id || group?.dungeon_id || ''),
      dungeonLevelId: String(group?.dungeon_level_id || ''),
      locations: Array.isArray(group?.locations)
        ? group.locations.map((location) => ({
          roomId: String(location?.room_id || ''),
          roomName: String(location?.room_name || location?.room_id || 'Room'),
          meta: String(location?.description || ''),
          lastVisitedLabel: Number(location?.last_visited || 0) > 0
            ? `Visited ${new Date(Number(location.last_visited) * 1000).toLocaleString()}`
            : 'Visited by party',
        })).filter((location) => location.roomId)
        : [],
    }))
    .filter((group) => group.locations.length > 0);
}
