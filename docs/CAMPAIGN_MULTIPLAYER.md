# Campaign multiplayer

## Membership

- `dc_campaign_members` is the one authority for who belongs to a campaign;
  `CampaignMembershipService` is the only writer.
- All members are equal. Any member can play, use Game Master mode and the
  campaign tools, and add or remove members. A campaign keeps at least one.
- `dc_campaigns.uid` records who created the campaign. Only the creator can
  delete it; that is the creator's one extra right. The creator is enrolled
  as the first member when the campaign is created.
- Each member's row holds the library character they play in the campaign
  (`default_character_id`). There is no campaign-wide active character.
- `CampaignAuthorizationService` and the route access checks all resolve to
  membership. Site administrators pass the route checks but must be members to
  change a campaign's members.

## Settings (hex map → Settings tab)

- **Players**: the member list, with Remove / Leave, and a filterable list of
  active site users to add (names only; 25 at a time).
- **Discord**: the server invite link, and the campaign's voice channel — a
  Create button until it exists, then a link to it.

API, all under `/api/campaign/{campaign_id}` and member-only:

- `GET /members/candidates?q=`
- `POST /members` with `{"uid": <int>}`
- `POST /members/{uid}/remove`
- `POST /discord/voice-channel`

Refusal codes: `campaign_member_required`, `campaign_member_exists`,
`campaign_member_user_unknown`, `campaign_member_unknown`,
`campaign_member_last`, `campaign_discord_voice_channel_exists`,
`discord_not_configured:<missing>`, `discord_channel_create_failed:<detail>`.

## Discord setup (site administrator)

1. Create a bot in the Discord developer portal, invite it to the server with
   the **Manage Channels** permission.
2. Put its token in `settings.php`:
   `$settings['dungeoncrawler_discord_bot_token'] = '…';`
3. At `/admin/config/content/dungeoncrawler` → Discord, set the server invite
   link, the server (guild) id, and optionally the category id that campaign
   voice channels are created under.

`CampaignDiscordService` creates one voice channel per campaign, named after
the campaign, and records it in `dc_campaign_discord`.
