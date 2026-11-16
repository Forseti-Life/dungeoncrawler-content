import assert from 'node:assert/strict';
import test from 'node:test';
import { buildStorylineFlowSource } from '../js/v2/editor/StorylineFlowGraph.js';

test('original flow nodes and directed relationships use internal graph identifiers', () => {
  const source = buildStorylineFlowSource({
    nodes: [
      { id: 'original-guide', type: 'actor', label: 'Village guide' },
      { id: 'original-task', type: 'quest', label: 'Recover the watch lantern' },
    ],
    edges: [{ source: 'original-guide', target: 'original-task', type: 'introduces_quest' }],
  });
  assert.match(source, /n0\["Village guide"\]:::actor/);
  assert.match(source, /n0 -->\|"introduces quest"\| n1/);
  assert.doesNotMatch(source, /original-guide/);
});

test('authored labels cannot inject diagram syntax or HTML', () => {
  const source = buildStorylineFlowSource({
    nodes: [{ id: 'original', label: 'Guide"] --> injected["<script> & #quot;' }],
  });
  assert.doesNotMatch(source, /Guide"\]/);
  assert.doesNotMatch(source, /<script>/);
  assert.match(source, /#34;/);
  assert.match(source, /#60;/);
  assert.match(source, /#35;quot;/);
});

test('dangling edges and duplicate identities fail explicitly', () => {
  assert.throws(() => buildStorylineFlowSource({
    nodes: [{ id: 'original' }], edges: [{ source: 'original', target: 'missing' }],
  }), /storyline_flow_edge_target_missing/);
  assert.throws(() => buildStorylineFlowSource({
    nodes: [{ id: 'original' }, { id: 'original' }],
  }), /storyline_flow_node_id_invalid/);
});
