<?php

namespace Drupal\dungeoncrawler_content\Access;

/**
 * Explicit quest-management access boundary for campaign GMs.
 *
 * Until campaigns support separately assigned GM accounts, the campaign owner is
 * the authoritative GM identity for quest-management operations. This access
 * check keeps that rule explicit on quest mutation routes instead of relying on
 * the more generic campaign access requirement.
 */
class CampaignQuestManageAccessCheck extends CampaignAccessCheck {

}
