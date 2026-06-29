# Quest System Quick Reference

**Version**: 1.0.0  
**Last Updated**: 2026-02-19

## Quick Implementation Steps

### 1. Database Schema Update

Add to `dungeoncrawler_content.install`:

```php
/**
 * Create quest system tables.
 */
function dungeoncrawler_content_update_10011() {
  $schema = [];
  
  // Library table: Quest templates
  $schema['dungeoncrawler_content_quest_templates'] = [
    // See QUEST_TRACKER_GENERATOR_ARCHITECTURE.md for full schema
  ];
  
  // Library table: Quest chains
  $schema['dungeoncrawler_content_quest_chains'] = [
    // See QUEST_TRACKER_GENERATOR_ARCHITECTURE.md for full schema
  ];
  
  // Campaign table: Active quests
  $schema['dc_campaign_quests'] = [
    // See QUEST_TRACKER_GENERATOR_ARCHITECTURE.md for full schema
  ];
  
  // Runtime table: Quest progress
  $schema['dc_campaign_quest_progress'] = [
    // See QUEST_TRACKER_GENERATOR_ARCHITECTURE.md for full schema
  ];
  
  // Runtime table: Quest log
  $schema['dc_campaign_quest_log'] = [
    // See QUEST_TRACKER_GENERATOR_ARCHITECTURE.md for full schema
  ];
  
  // Runtime table: Claimed rewards
  $schema['dc_campaign_quest_rewards_claimed'] = [
    // See QUEST_TRACKER_GENERATOR_ARCHITECTURE.md for full schema
  ];
  
  foreach ($schema as $table_name => $table_schema) {
    \Drupal::database()->schema()->createTable($table_name, $table_schema);
  }
}
```

**Run update:**
```bash
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler/web
./vendor/bin/drush updatedb -y
```

---

## 2. Service Registration

Add to `dungeoncrawler_content.services.yml`:

```yaml
services:
  # Quest Generator Service
  dungeoncrawler_content.quest_generator:
    class: Drupal\dungeoncrawler_content\Service\QuestGeneratorService
    arguments: ['@database', '@logger.channel.dungeoncrawler_content']

  # Quest Tracker Service
  dungeoncrawler_content.quest_tracker:
    class: Drupal\dungeoncrawler_content\Service\QuestTrackerService
    arguments: ['@database', '@logger.channel.dungeoncrawler_content']

  # Quest Reward Service
  dungeoncrawler_content.quest_reward:
    class: Drupal\dungeoncrawler_content\Service\QuestRewardService
    arguments: ['@database', '@logger.channel.dungeoncrawler_content']

  # Quest Validator Service
  dungeoncrawler_content.quest_validator:
    class: Drupal\dungeoncrawler_content\Service\QuestValidatorService
    arguments: ['@database']
```

---

## 3. Create Service Classes

### File Structure

```
web/modules/custom/dungeoncrawler_content/src/
├── Controller/
│   ├── QuestGeneratorController.php
│   ├── QuestTrackerController.php
│   └── QuestRewardController.php
└── Service/
    ├── QuestGeneratorService.php
    ├── QuestTrackerService.php
    ├── QuestRewardService.php
    └── QuestValidatorService.php
```

---

## 4. API Routing

Add to `dungeoncrawler_content.routing.yml`:

```yaml
# Generate quest from template
dungeoncrawler_content.quest.generate:
  path: '/api/campaign/{campaign_id}/quests/generate'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\QuestGeneratorController::generate'
  methods: [POST]
  requirements:
    _permission: 'access content'
    campaign_id: '\d+'

# Get available quests
dungeoncrawler_content.quest.available:
  path: '/api/campaign/{campaign_id}/quests/available'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\QuestTrackerController::getAvailable'
  methods: [GET]
  requirements:
    _permission: 'access content'
    campaign_id: '\d+'

# Start quest
dungeoncrawler_content.quest.start:
  path: '/api/campaign/{campaign_id}/quests/{quest_id}/start'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\QuestTrackerController::start'
  methods: [POST]
  requirements:
    _permission: 'access content'
    campaign_id: '\d+'

# Update quest progress
dungeoncrawler_content.quest.progress:
  path: '/api/campaign/{campaign_id}/quests/{quest_id}/progress'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\QuestTrackerController::updateProgress'
  methods: [PUT]
  requirements:
    _permission: 'access content'
    campaign_id: '\d+'

# Complete quest
dungeoncrawler_content.quest.complete:
  path: '/api/campaign/{campaign_id}/quests/{quest_id}/complete'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\QuestTrackerController::complete'
  methods: [POST]
  requirements:
    _permission: 'access content'
    campaign_id: '\d+'

# Claim rewards
dungeoncrawler_content.quest.rewards_claim:
  path: '/api/campaign/{campaign_id}/quests/{quest_id}/rewards/claim'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\QuestRewardController::claimRewards'
  methods: [POST]
  requirements:
    _permission: 'access content'
    campaign_id: '\d+'

# Get quest journal
dungeoncrawler_content.quest.journal:
  path: '/api/campaign/{campaign_id}/character/{character_id}/quest-journal'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\QuestTrackerController::getJournal'
  methods: [GET]
  requirements:
    _permission: 'access content'
    campaign_id: '\d+'
    character_id: '\d+'
```

**Rebuild cache:**
```bash
./vendor/bin/drush cr
```

---

## 5. Example Quest Template (JSON)

Create example templates in `modules/custom/dungeoncrawler_content/templates/quests/`:

**File**: `rescue_merchant.json`

```json
{
  "template_id": "rescue_merchant",
  "name": "Rescue {npc} from {location}",
  "description": "{npc}, a {npc_role}, was captured by {target}s in {location}. Find and rescue them before it's too late!",
  "quest_type": "rescue",
  "level_min": 2,
  "level_max": 5,
  "tags": ["combat", "rescue", "time_limited"],
  "objectives_schema": [
    {
      "phase": 1,
      "objectives": [
        {
          "objective_id": "find_location",
          "type": "explore",
          "target": "{location}",
          "description": "Find the {location}"
        },
        {
          "objective_id": "defeat_captors",
          "type": "kill",
          "target": "{target}",
          "target_count_range": [4, 8],
          "description": "Defeat the {target}s holding {npc}"
        },
        {
          "objective_id": "rescue_npc",
          "type": "interact",
          "target": "{npc}",
          "description": "Free {npc} from captivity"
        }
      ]
    },
    {
      "phase": 2,
      "objectives": [
        {
          "objective_id": "escort_to_safety",
          "type": "escort",
          "target": "{npc}",
          "destination": "{safe_location}",
          "description": "Escort {npc} back to safety"
        }
      ]
    }
  ],
  "rewards_schema": {
    "xp": {
      "base": 120,
      "per_level": 40
    },
    "gold": {
      "base": 10,
      "per_level": 5,
      "randomize": true
    },
    "items": {
      "loot_table": "rescue_reward",
      "count": 1
    },
    "reputation": {
      "faction": "{npc_faction}",
      "amount": 25
    }
  },
  "prerequisites": {
    "level_min": 2
  },
  "time_limit_hours": 24
}
```

---

## 6. Load Templates into Database

Create Drush command: `LoadQuestTemplatesCommand.php`

```php
<?php

namespace Drupal\dungeoncrawler_content\Commands;

use Drush\Commands\DrushCommands;

class LoadQuestTemplatesCommand extends DrushCommands {

  /**
   * Load quest templates from JSON files.
   *
   * @command dungeoncrawler:load-quest-templates
   * @aliases dc-load-quests
   */
  public function loadQuestTemplates() {
    $module_path = \Drupal::service('extension.list.module')->getPath('dungeoncrawler_content');
    $templates_dir = $module_path . '/templates/quests';
    
    if (!is_dir($templates_dir)) {
      $this->logger()->error("Templates directory not found: $templates_dir");
      return;
    }
    
    $files = glob($templates_dir . '/*.json');
    $loaded = 0;
    
    foreach ($files as $file) {
      $json = file_get_contents($file);
      $template = json_decode($json, TRUE);
      
      if (!$template) {
        $this->logger()->warning("Failed to parse: $file");
        continue;
      }
      
      // Insert or update template
      \Drupal::database()->merge('dungeoncrawler_content_quest_templates')
        ->key(['template_id' => $template['template_id']])
        ->fields([
          'template_id' => $template['template_id'],
          'name' => $template['name'],
          'description' => $template['description'],
          'quest_type' => $template['quest_type'],
          'level_min' => $template['level_min'],
          'level_max' => $template['level_max'],
          'tags' => json_encode($template['tags'] ?? []),
          'objectives_schema' => json_encode($template['objectives_schema']),
          'rewards_schema' => json_encode($template['rewards_schema']),
          'prerequisites' => json_encode($template['prerequisites'] ?? []),
          'story_impact' => json_encode($template['story_impact'] ?? []),
          'estimated_duration_minutes' => $template['estimated_duration_minutes'] ?? NULL,
          'created' => \Drupal::time()->getRequestTime(),
          'updated' => \Drupal::time()->getRequestTime(),
          'version' => $template['version'] ?? '1.0.0',
        ])
        ->execute();
      
      $loaded++;
      $this->logger()->success("Loaded: {$template['template_id']}");
    }
    
    $this->logger()->success("Loaded $loaded quest templates.");
  }
}
```

**Run command:**
```bash
./vendor/bin/drush dc-load-quests
```

---

## 7. API Usage Examples

### Generate a Quest

```bash
curl -X POST http://localhost/api/campaign/1/quests/generate \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": "rescue_merchant",
    "context": {
      "party_level": 3,
      "location_id": "town_square",
      "npc_pool": [12, 15, 18],
      "difficulty": "moderate"
    }
  }'
```

### Get Available Quests

```bash
curl -X GET "http://localhost/api/campaign/1/quests/available?location_id=town_square&character_id=42"
```

### Start a Quest

```bash
curl -X POST http://localhost/api/campaign/1/quests/quest_12345/start \
  -H "Content-Type: application/json" \
  -d '{"character_id": 42}'
```

### Update Quest Progress

```bash
curl -X PUT http://localhost/api/campaign/1/quests/quest_12345/progress \
  -H "Content-Type: application/json" \
  -d '{
    "character_id": 42,
    "objective_id": "defeat_captors",
    "progress": 5
  }'
```

### Complete Quest

```bash
curl -X POST http://localhost/api/campaign/1/quests/quest_12345/complete \
  -H "Content-Type: application/json" \
  -d '{
    "character_id": 42,
    "outcome": "success"
  }'
```

### Claim Rewards

```bash
curl -X POST http://localhost/api/campaign/1/quests/quest_12345/rewards/claim \
  -H "Content-Type: application/json" \
  -d '{"character_id": 42}'
```

### Get Quest Journal

```bash
curl -X GET http://localhost/api/campaign/1/character/42/quest-journal
```

---

## 8. Testing Strategy

### Unit Tests

```php
<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;

class QuestGeneratorServiceTest extends UnitTestCase {

  public function testGenerateQuestFromTemplate() {
    $service = new QuestGeneratorService($this->mockDatabase(), $this->mockLogger());
    
    $quest = $service->generateQuestFromTemplate('rescue_merchant', 1, [
      'party_level' => 3,
      'npc' => 'Merchant Aldric',
    ]);
    
    $this->assertArrayHasKey('quest_id', $quest);
    $this->assertArrayHasKey('generated_objectives', $quest);
    $this->assertArrayHasKey('generated_rewards', $quest);
  }
}
```

### Integration Tests

```php
<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;

class QuestSystemTest extends BrowserTestBase {

  protected static $modules = ['dungeoncrawler_content'];

  public function testQuestLifecycle() {
    // 1. Generate quest
    $response = $this->post('/api/campaign/1/quests/generate', [
      'template_id' => 'rescue_merchant',
    ]);
    $this->assertEquals(200, $response->getStatusCode());
    
    // 2. Start quest
    // 3. Update progress
    // 4. Complete quest
    // 5. Claim rewards
  }
}
```

**Run tests:**
```bash
./vendor/bin/phpunit modules/custom/dungeoncrawler_content/tests/
```

---

## 9. Common Queries

### Get All Active Quests for Campaign

```sql
SELECT 
  cq.quest_id,
  cq.quest_name,
  cq.quest_type,
  cq.status,
  COUNT(qp.id) as active_characters
FROM dc_campaign_quests cq
LEFT JOIN dc_campaign_quest_progress qp 
  ON cq.campaign_id = qp.campaign_id 
  AND cq.quest_id = qp.quest_id
  AND qp.completed_at IS NULL
WHERE cq.campaign_id = 1
  AND cq.status IN ('available', 'active')
GROUP BY cq.quest_id;
```

### Get Character Quest Progress

```sql
SELECT 
  cq.quest_name,
  qp.objective_states,
  qp.current_phase,
  qp.started_at
FROM dc_campaign_quest_progress qp
JOIN dc_campaign_quests cq 
  ON qp.campaign_id = cq.campaign_id 
  AND qp.quest_id = cq.quest_id
WHERE qp.character_id = 42
  AND qp.completed_at IS NULL
ORDER BY qp.started_at DESC;
```

### Get Quest Completion Statistics

```sql
SELECT 
  cq.quest_type,
  COUNT(*) as total_completions,
  AVG(qp.completed_at - qp.started_at) as avg_duration_seconds,
  SUM(CASE WHEN qp.outcome = 'success' THEN 1 ELSE 0 END) as successes,
  SUM(CASE WHEN qp.outcome = 'failure' THEN 1 ELSE 0 END) as failures
FROM dc_campaign_quest_progress qp
JOIN dc_campaign_quests cq 
  ON qp.campaign_id = cq.campaign_id 
  AND qp.quest_id = cq.quest_id
WHERE qp.campaign_id = 1
  AND qp.completed_at IS NOT NULL
GROUP BY cq.quest_type;
```

---

## 10. Event Integration Examples

### Listen for Combat Victory

```php
// In QuestTrackerService or separate EventSubscriber

public function onCombatVictory(CombatEndEvent $event) {
  $defeated_creatures = $event->getDefeatedCreatures();
  
  foreach ($defeated_creatures as $creature) {
    // Find active quests with "kill" objectives for this creature
    $active_quests = $this->findQuestsWithKillObjective(
      $event->getCampaignId(),
      $creature['type'],
      $event->getCharacterIds()
    );
    
    foreach ($active_quests as $quest) {
      $this->updateObjectiveProgress(
        $event->getCampaignId(),
        $quest['quest_id'],
        $quest['objective_id'],
        1 // increment by 1
      );
    }
  }
}
```

### Listen for Item Collection

```php
public function onItemCollected(ItemCollectedEvent $event) {
  $active_quests = $this->findQuestsWithCollectObjective(
    $event->getCampaignId(),
    $event->getItemId(),
    $event->getCharacterId()
  );
  
  foreach ($active_quests as $quest) {
    $this->updateObjectiveProgress(
      $event->getCampaignId(),
      $quest['quest_id'],
      $quest['objective_id'],
      $event->getQuantity()
    );
  }
}
```

---

## 11. Performance Considerations

### Indexes for Common Queries

```sql
-- Fast lookup of active quests by status
CREATE INDEX idx_campaign_status 
  ON dc_campaign_quests(campaign_id, status);

-- Fast lookup of character progress
CREATE INDEX idx_character_active 
  ON dc_campaign_quest_progress(character_id, completed_at);

-- Fast lookup by location
CREATE INDEX idx_location 
  ON dc_campaign_quests(location_id);

-- Quest log chronological queries
CREATE INDEX idx_quest_log_time 
  ON dc_campaign_quest_log(campaign_id, quest_id, timestamp);
```

### Caching Strategy

```php
// Cache available quests per location for 5 minutes
$cache_key = "quest:available:{$campaign_id}:{$location_id}";
$cached = \Drupal::cache()->get($cache_key);

if ($cached) {
  return $cached->data;
}

$quests = $this->loadAvailableQuests($campaign_id, $location_id);

\Drupal::cache()->set($cache_key, $quests, time() + 300);
return $quests;
```

---

## 12. Troubleshooting

### Quest Not Appearing

1. Check quest status: `SELECT * FROM dc_campaign_quests WHERE quest_id = 'quest_123';`
2. Check prerequisites: Verify character meets level/reputation/quest completion requirements
3. Check location: Ensure character is in correct location
4. Check expiration: Verify quest hasn't expired

### Objective Not Updating

1. Check event integration: Verify combat/collection events are firing
2. Check objective state: `SELECT objective_states FROM dc_campaign_quest_progress WHERE quest_id = 'quest_123';`
3. Check logs: `SELECT * FROM dc_campaign_quest_log WHERE quest_id = 'quest_123' ORDER BY timestamp DESC;`

### Rewards Not Granted

1. Check completion status: `SELECT completed_at FROM dc_campaign_quest_progress WHERE quest_id = 'quest_123';`
2. Check reward claim: `SELECT * FROM dc_campaign_quest_rewards_claimed WHERE quest_id = 'quest_123';`
3. Check reward data: `SELECT generated_rewards FROM dc_campaign_quests WHERE quest_id = 'quest_123';`

---

## 13. Future Enhancements

- **Repeatable Quests**: Daily/weekly quest reset system
- **Quest Reputation**: Track quest giver relationships
- **Quest Abandonment**: Allow players to abandon quests with consequences
- **Quest Sharing**: Share quests between party members
- **Quest Timers**: Real-time countdown for time-limited quests
- **Quest Notifications**: Push notifications for quest updates
- **Quest Achievements**: Track quest completion milestones
- **Quest Leaderboards**: Compare completion times/outcomes

---

**End of Quick Reference**
