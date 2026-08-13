<?php

namespace Drupal\dungeoncrawler_content\Access;

/**
 * Explicit quest-management access boundary for campaign GMs.
 *
 * This keeps quest-management routes explicitly pinned to campaign GM
 * capabilities (owner_gm/gm) instead of generic campaign access.
 */
class CampaignQuestManageAccessCheck extends CampaignAccessCheck {

}
