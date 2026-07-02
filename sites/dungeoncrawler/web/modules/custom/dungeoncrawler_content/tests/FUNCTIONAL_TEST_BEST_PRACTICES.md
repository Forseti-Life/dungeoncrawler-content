# Functional Test Best Practices

## Overview
This document describes the enhanced testing patterns introduced to deepen functional test assertions beyond simple status code checks.

## Reusable Components

### TestFixtureTrait
Location: `tests/src/Functional/Traits/TestFixtureTrait.php`

**Purpose**: Load JSON fixture files for consistent test data.

**Usage**:
```php
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;

class MyTest extends BrowserTestBase {
  use TestFixtureTrait;
  
  public function testSomething() {
    // Load any fixture
    $data = $this->loadFixture('campaigns/basic_campaign_state.json');
    
    // Or use convenience method for characters
    $character = $this->loadCharacterFixture('level_1_fighter');
  }
}
```

### TestDataFactoryTrait
Location: `tests/src/Functional/Traits/TestDataFactoryTrait.php`

**Purpose**: Generate test data with sensible defaults.

**Usage**:
```php
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataFactoryTrait;

class MyTest extends BrowserTestBase {
  use TestDataFactoryTrait;
  
  public function testCampaignFlow() {
    // Create campaign with defaults
    $campaign_id = $this->createTestCampaign();
    
    // Or customize
    $campaign_id = $this->createTestCampaign([
      'uid' => $user->id(),
      'name' => 'Custom Campaign',
      'state' => ['started' => TRUE],
    ]);
    
    // Generate campaign state data
    $state = $this->createCampaignState([
      'party_gold' => 500,
      'active_hex' => 'q3r2',
    ]);
    
    // Generate entity spawn data
    $entity = $this->createEntitySpawnData([
      'instanceId' => 'boss-dragon',
      'type' => 'boss',
    ]);
  }
}
```

## Available Fixtures

### Campaign Fixtures
Location: `tests/fixtures/campaigns/`

- **basic_campaign_state.json**: New campaign, minimal progress
- **active_campaign_state.json**: Active campaign with quests, inventory, progress

### Entity Fixtures
Location: `tests/fixtures/entities/`

- **goblin_warrior.json**: Basic NPC with combat stats
- **skeleton_archer.json**: Undead entity with special attributes

### Character Fixtures
Location: `tests/fixtures/characters/`

- **level_1_fighter.json**: Fighter with calculated stats
- **level_1_wizard.json**: Wizard build
- **level_5_rogue.json**: Higher level character

## Testing Patterns

### Pattern 1: Response Structure Validation
**Don't do this**:
```php
$this->assertSession()->statusCodeNotEquals(404);
```

**Do this instead**:
```php
$status_code = $this->getSession()->getStatusCode();
$this->assertNotEquals(404, $status_code, 'Route should exist');

if ($status_code === 200) {
  $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
  $this->assertIsArray($response, 'Response should be JSON');
  $this->assertArrayHasKey('success', $response);
  
  if ($response['success']) {
    // Assert specific fields
    $this->assertArrayHasKey('data', $response);
    $this->assertEquals($expected_value, $response['data']['field']);
  }
}
```

### Pattern 2: Error Response Validation
**Don't do this**:
```php
$this->assertSession()->statusCodeEquals(403);
```

**Do this instead**:
```php
$this->assertSession()->statusCodeEquals(403);

$response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
$this->assertIsArray($response, 'Response should be JSON');
$this->assertArrayHasKey('success', $response);
$this->assertFalse($response['success'], 'Success should be false');
$this->assertArrayHasKey('error', $response, 'Should contain error message');
$this->assertStringContainsString('Access denied', $response['error']);
```

### Pattern 3: Using Fixtures with Factories
**Don't do this**:
```php
$database = \Drupal::database();
$campaign_id = $database->insert('dc_campaigns')
  ->fields([
    'uuid' => \Drupal::service('uuid')->generate(),
    'uid' => $user->id(),
    'name' => 'Test Campaign',
    // ... many more fields
  ])
  ->execute();
```

**Do this instead**:
```php
// Load fixture for consistent data
$fixture = $this->loadFixture('campaigns/active_campaign_state.json');

// Use factory to create in database
$campaign_id = $this->createTestCampaign([
  'uid' => $user->id(),
  'state' => $fixture['state'],
  'version' => $fixture['state_meta']['version'],
]);
```

### Pattern 4: Deep Field Assertions
**Don't do this**:
```php
$result = $this->requestJson('POST', $url, $payload);
$this->assertTrue($result['success']);
```

**Do this instead**:
```php
$result = $this->requestJson('POST', $url, $payload);

// Assert response structure
$this->assertTrue($result['success'], 'Request should succeed');
$this->assertArrayHasKey('data', $result, 'Response should contain data');

// Assert specific fields
$this->assertEquals($expected_id, $result['data']['id']);
$this->assertEquals('active', $result['data']['status']);
$this->assertIsArray($result['data']['items']);
$this->assertCount(3, $result['data']['items']);

// Assert nested structures
$this->assertArrayHasKey('metadata', $result['data']);
$this->assertArrayHasKey('version', $result['data']['metadata']);
```

## Creating New Fixtures

### JSON Fixture Format
```json
{
  "name": "fixture_name",
  "description": "What this fixture represents",
  "field1": "value1",
  "nested": {
    "field": "value"
  },
  "test_cases": {
    "description": "Optional: Document test scenarios",
    "expected_behavior": "What should happen"
  }
}
```

### Fixture Naming Conventions
- Use lowercase with underscores: `basic_campaign_state.json`
- Be descriptive: `level_5_rogue.json` not `char5.json`
- Group by type in directories: `campaigns/`, `entities/`, `characters/`

## Examples from Enhanced Tests

### Example 1: CampaignStateAccessTest
```php
public function testCampaignOwnerAccess() {
  $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
  $this->drupalLogin($owner);

  // Use fixture for realistic data
  $fixture = $this->loadFixture('campaigns/basic_campaign_state.json');
  $campaign_id = $this->createTestCampaign([
    'uid' => $owner->id(),
    'state' => $fixture['state'],
  ]);

  // Test GET endpoint
  $this->drupalGet("/api/campaign/{$campaign_id}/state");
  $this->assertSession()->statusCodeEquals(200);
  
  $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
  
  // Deep assertions
  $this->assertTrue($response['success']);
  $this->assertArrayHasKey('data', $response);
  $this->assertEquals($campaign_id, $response['data']['campaignId']);
  $this->assertArrayHasKey('state', $response['data']);
  $this->assertEquals($owner->id(), $response['data']['state']['created_by']);
  $this->assertTrue($response['data']['state']['started']);
}
```

### Example 2: EntityLifecycleTest
```php
public function testEntityLifecycle() {
  $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
  $this->drupalLogin($user);

  // Create campaign
  $campaign_id = $this->createTestCampaign(['uid' => $user->id()]);

  // Load entity fixture
  $fixture = $this->loadFixture('entities/goblin_warrior.json');
  
  // Spawn entity
  $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/entity/spawn", [
    'type' => $fixture['type'],
    'instanceId' => 'test-goblin-1',
    'stateData' => $fixture['stateData'],
  ]);

  // Deep assertions on spawn response
  $this->assertTrue($result['success']);
  $this->assertEquals('test-goblin-1', $result['data']['instanceId']);
  $this->assertEquals(8, $result['data']['stateData']['hp']);
  $this->assertEquals('Goblin Warrior', $result['data']['stateData']['name']);
}
```

## Migration Guide

To convert an existing test:

1. Add trait imports:
```php
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestFixtureTrait;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataFactoryTrait;

class MyTest extends BrowserTestBase {
  use TestFixtureTrait;
  use TestDataFactoryTrait;
```

2. Replace database inserts with factory calls:
```php
// Before
$database = \Drupal::database();
$campaign_id = $database->insert('dc_campaigns')->fields([...])->execute();

// After
$campaign_id = $this->createTestCampaign();
```

3. Add response body assertions:
```php
// Before
$this->assertSession()->statusCodeNotEquals(404);

// After
$status_code = $this->getSession()->getStatusCode();
$this->assertNotEquals(404, $status_code);
if ($status_code === 200) {
  $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
  $this->assertArrayHasKey('success', $response);
  // ... more assertions
}
```

4. Use fixtures for test data:
```php
// Before
$payload = ['field1' => 'value1', 'field2' => 'value2'];

// After
$fixture = $this->loadFixture('entities/goblin_warrior.json');
$payload = $fixture['stateData'];
```

## Benefits

1. **Better Test Coverage**: Validates actual data, not just HTTP codes
2. **Reusability**: Fixtures and factories reduce duplication
3. **Maintainability**: Changes to test data in one place
4. **Readability**: Tests focus on behavior, not setup
5. **Reliability**: Consistent test data across runs

## Questions?

See existing examples in:
- `tests/src/Functional/CampaignStateAccessTest.php`
- `tests/src/Functional/EntityLifecycleTest.php`
- `tests/src/Functional/Controller/CharacterApiControllerTest.php`
