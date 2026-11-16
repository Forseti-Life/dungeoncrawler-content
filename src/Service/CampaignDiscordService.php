<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Links a campaign to the community Discord server.
 *
 * The server invite, guild id and optional voice category come from module
 * settings; the bot token is the `dungeoncrawler_discord_bot_token` entry in
 * settings.php. A campaign has at most one voice channel, created by the bot
 * on a member's request.
 *
 * Refusals are \DomainException with a stable code as the message.
 */
class CampaignDiscordService {

  private const TABLE = 'dc_campaign_discord';

  private const API = 'https://discord.com/api/v10';

  private const CHANNEL_TYPE_VOICE = 2;

  public const TOKEN_SETTING = 'dungeoncrawler_discord_bot_token';

  public function __construct(
    protected Connection $database,
    protected ConfigFactoryInterface $configFactory,
    protected ClientInterface $httpClient,
    protected TimeInterface $time,
    protected CampaignMembershipService $membership,
  ) {}

  /**
   * What the campaign settings show about Discord.
   *
   * @return array{invite_url:string, can_create_voice_channel:bool, missing:string[], voice_channel:array{id:string, name:string, url:string, created_by_uid:int, created:int}|null}
   */
  public function status(int $campaign_id): array {
    $missing = $this->missingConfiguration();

    return [
      'invite_url' => $this->setting('discord_invite_url'),
      'can_create_voice_channel' => $missing === [],
      'missing' => $missing,
      'voice_channel' => $this->voiceChannel($campaign_id),
    ];
  }

  /**
   * Creates the campaign's voice channel on the Discord server.
   *
   * @return array{id:string, name:string, url:string, created_by_uid:int, created:int}
   *
   * @throws \DomainException
   *   `campaign_member_required`, `campaign_discord_voice_channel_exists`,
   *   `discord_not_configured:<missing,...>`, `campaign_unknown`,
   *   `discord_channel_create_failed:<detail>`.
   */
  public function createVoiceChannel(int $campaign_id, int $actor_uid): array {
    $this->membership->assertMember($campaign_id, $actor_uid);
    if ($this->voiceChannel($campaign_id) !== NULL) {
      throw new \DomainException('campaign_discord_voice_channel_exists');
    }
    $missing = $this->missingConfiguration();
    if ($missing !== []) {
      throw new \DomainException('discord_not_configured:' . implode(',', $missing));
    }
    $campaign_name = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['name'])
      ->condition('c.id', $campaign_id)
      ->execute()
      ->fetchField();
    if ($campaign_name === FALSE) {
      throw new \DomainException('campaign_unknown');
    }

    $guild_id = $this->setting('discord_guild_id');
    $body = [
      'name' => mb_substr(trim((string) $campaign_name), 0, 100),
      'type' => self::CHANNEL_TYPE_VOICE,
    ];
    $category_id = $this->setting('discord_voice_category_id');
    if ($category_id !== '') {
      $body['parent_id'] = $category_id;
    }

    try {
      $response = $this->httpClient->request('POST', self::API . '/guilds/' . $guild_id . '/channels', [
        'headers' => [
          'Authorization' => 'Bot ' . Settings::get(self::TOKEN_SETTING),
          'X-Audit-Log-Reason' => 'DungeonCrawler campaign ' . $campaign_id,
        ],
        'json' => $body,
        'http_errors' => FALSE,
        'timeout' => 10,
      ]);
    }
    catch (GuzzleException $e) {
      throw new \DomainException('discord_channel_create_failed:' . $e->getMessage(), 0, $e);
    }
    $channel = json_decode((string) $response->getBody(), TRUE);
    if ($response->getStatusCode() !== 201 || !is_array($channel) || empty($channel['id'])) {
      throw new \DomainException(sprintf(
        'discord_channel_create_failed:%d:%s',
        $response->getStatusCode(),
        is_array($channel) ? (string) ($channel['message'] ?? '') : ''
      ));
    }

    $this->database->insert(self::TABLE)
      ->fields([
        'campaign_id' => $campaign_id,
        'guild_id' => $guild_id,
        'voice_channel_id' => (string) $channel['id'],
        'voice_channel_name' => (string) $channel['name'],
        'created_by_uid' => $actor_uid,
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();

    return $this->voiceChannel($campaign_id);
  }

  /**
   * @return array{id:string, name:string, url:string, created_by_uid:int, created:int}|null
   */
  private function voiceChannel(int $campaign_id): ?array {
    $row = $this->database->select(self::TABLE, 'd')
      ->fields('d')
      ->condition('d.campaign_id', $campaign_id)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return NULL;
    }

    return [
      'id' => (string) $row['voice_channel_id'],
      'name' => (string) $row['voice_channel_name'],
      'url' => 'https://discord.com/channels/' . $row['guild_id'] . '/' . $row['voice_channel_id'],
      'created_by_uid' => (int) $row['created_by_uid'],
      'created' => (int) $row['created'],
    ];
  }

  /**
   * Names of the settings the bot still needs before it can create channels.
   *
   * @return string[]
   */
  private function missingConfiguration(): array {
    $missing = [];
    if ($this->setting('discord_guild_id') === '') {
      $missing[] = 'discord_guild_id';
    }
    if (trim((string) Settings::get(self::TOKEN_SETTING, '')) === '') {
      $missing[] = self::TOKEN_SETTING;
    }

    return $missing;
  }

  private function setting(string $key): string {
    return trim((string) $this->configFactory->get('dungeoncrawler_content.settings')->get($key));
  }

}
