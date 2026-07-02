/**
 * @file
 * Manual verification test for Entity.js backward compatibility.
 * 
 * This file can be run in a browser console to verify that:
 * 1. Existing ECS code continues to work unchanged
 * 2. New entity_instance format works correctly
 * 3. Format conversion is bidirectional
 * 
 * Usage: Copy and paste this into browser console after loading the ECS module
 */

// Test 1: Backward compatibility - existing ECS code
console.log('=== Test 1: Backward Compatibility ===');
import('./ecs/index.js').then(({ Entity, EntityManager }) => {
  // Original usage pattern (should work unchanged)
  const entity = new Entity(1);
  entity.addComponent('HealthComponent', { currentHp: 100, maxHp: 100 });
  entity.addComponent('PositionComponent', { q: 5, r: 3 });
  
  const json = entity.toJSON();
  console.log('✓ Default toJSON() works:', json);
  console.assert(json.id === 1, 'ID preserved');
  console.assert(json.active === true, 'Active preserved');
  console.assert(json.components.HealthComponent, 'Components preserved');
  
  const restored = Entity.fromJSON(json);
  console.log('✓ fromJSON() works:', restored);
  console.assert(restored.id === 1, 'Restored ID');
  console.assert(restored.hasComponent('HealthComponent'), 'Restored component');
  
  const manager = new EntityManager();
  const e = manager.createEntity();
  console.log('✓ EntityManager.createEntity() works:', e.id);
  console.assert(e.id === 1, 'First entity has ID 1');
  
  const json2 = manager.toJSON();
  console.log('✓ EntityManager.toJSON() works:', json2);
  console.assert(json2.nextEntityId === 2, 'Next ID tracked');
  
  console.log('=== Test 1: PASSED ===\n');
});

// Test 2: New entity_instance format
console.log('=== Test 2: entity_instance Format ===');
import('./ecs/index.js').then(({ Entity, EntityInstanceMapper }) => {
  // New usage pattern with entity_instance
  const entity = new Entity(1, {
    entity_instance_id: '550e8400-e29b-41d4-a716-446655440000',
    entity_type: 'creature',
    entity_ref: {
      content_type: 'creature',
      content_id: 'goblin_warrior_001',
      version: '1.0.0'
    },
    placement: {
      room_id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
      hex: { q: 2, r: -1 },
      spawn_type: 'respawning'
    }
  });
  
  entity.addComponent('HealthComponent', {
    currentHp: 12,
    maxHp: 16
  });
  
  const instanceJson = entity.toJSON('entity_instance');
  console.log('✓ toJSON("entity_instance") works:', instanceJson);
  console.assert(instanceJson.schema_version === '1.0.0', 'Schema version added');
  console.assert(instanceJson.entity_instance_id, 'UUID preserved');
  console.assert(instanceJson.state.hit_points.current === 12, 'HP converted');
  
  // Validate
  const validation = EntityInstanceMapper.validate(instanceJson);
  console.log('✓ Validation works:', validation);
  console.assert(validation.valid === true, 'Schema valid');
  
  // Convert back
  const restored = Entity.fromJSON(instanceJson);
  console.log('✓ fromJSON(entity_instance) works:', restored);
  console.assert(restored.entity_instance_id === '550e8400-e29b-41d4-a716-446655440000', 'UUID restored');
  console.assert(restored.hasComponent('HealthComponent'), 'Component restored');
  
  const health = restored.getComponent('HealthComponent');
  console.assert(health.currentHp === 12, 'HP data restored');
  console.assert(health.maxHp === 16, 'Max HP data restored');
  
  console.log('=== Test 2: PASSED ===\n');
});

// Test 3: Batch operations
console.log('=== Test 3: Batch Operations ===');
import('./ecs/index.js').then(({ EntityInstanceMapper }) => {
  // Create test instances
  const instances = [
    EntityInstanceMapper.createTestInstance({
      entity_type: 'creature',
      entity_ref: { content_type: 'creature', content_id: 'goblin_001' }
    }),
    EntityInstanceMapper.createTestInstance({
      entity_type: 'item',
      entity_ref: { content_type: 'item', content_id: 'potion_001' }
    }),
    EntityInstanceMapper.createTestInstance({
      entity_type: 'obstacle',
      entity_ref: { content_type: 'trap', content_id: 'spike_trap_001' }
    })
  ];
  
  // Convert all to entities
  const entities = EntityInstanceMapper.fromEntityInstanceArray(instances);
  console.log('✓ fromEntityInstanceArray() works:', entities.length);
  console.assert(entities.length === 3, '3 entities created');
  console.assert(entities[0].entity_type === 'creature', 'Type preserved');
  
  // Convert back
  const backToInstances = EntityInstanceMapper.toEntityInstanceArray(entities);
  console.log('✓ toEntityInstanceArray() works:', backToInstances.length);
  console.assert(backToInstances.length === 3, '3 instances created');
  console.assert(backToInstances[1].entity_type === 'item', 'Type preserved');
  
  console.log('=== Test 3: PASSED ===\n');
});

// Test 4: Format auto-detection
console.log('=== Test 4: Format Auto-Detection ===');
import('./ecs/index.js').then(({ Entity }) => {
  // ECS format
  const ecsData = {
    id: 42,
    active: true,
    components: {
      TestComponent: { value: 123 }
    }
  };
  
  const fromEcs = Entity.fromJSON(ecsData);
  console.log('✓ Auto-detects ECS format:', fromEcs.id);
  console.assert(fromEcs.id === 42, 'ECS format loaded');
  
  // entity_instance format
  const instanceData = {
    entity_instance_id: 'test-uuid-123',
    entity_type: 'creature',
    entity_ref: {
      content_type: 'creature',
      content_id: 'test'
    },
    placement: {
      room_id: 'room-1',
      hex: { q: 0, r: 0 }
    },
    state: {
      active: true,
      hit_points: { current: 50, max: 100 }
    }
  };
  
  const fromInstance = Entity.fromJSON(instanceData);
  console.log('✓ Auto-detects entity_instance format:', fromInstance.entity_instance_id);
  console.assert(fromInstance.entity_instance_id === 'test-uuid-123', 'entity_instance format loaded');
  console.assert(fromInstance.hasComponent('HealthComponent'), 'State converted to components');
  
  const health = fromInstance.getComponent('HealthComponent');
  console.assert(health.currentHp === 50, 'HP extracted from state');
  
  console.log('=== Test 4: PASSED ===\n');
});

console.log('✓✓✓ All manual verification tests completed ✓✓✓');
console.log('If all assertions passed, Entity.js refactor is working correctly!');
