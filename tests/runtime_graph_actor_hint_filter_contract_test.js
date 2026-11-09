/*
 * Contract test: runtime graph actor hints must not treat generic room props
 * as actor hydration requirements.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

(function run() {
  const runtimeGraphSource = fs.readFileSync(
    path.join(__dirname, '..', 'src', 'Service', 'RuntimeGraphAssemblerService.php'),
    'utf8'
  );

  assert(
    runtimeGraphSource.includes("foreach ((array) ($contents_data['npcs'] ?? []) as $npc)")
      && runtimeGraphSource.includes("foreach ((array) ($contents_data['entities'] ?? []) as $entity)")
      && runtimeGraphSource.includes("foreach ((array) ($contents_data['creatures'] ?? []) as $creature)"),
    'RuntimeGraphAssemblerService should evaluate npc/entity/creature buckets explicitly',
  );

  assert(
    runtimeGraphSource.includes("if (!in_array($content_type, ['npc', 'creature', 'monster', 'follower', 'companion'], TRUE))"),
    'RuntimeGraphAssemblerService should whitelist actor-bearing entity content types before adding runtime actor hints',
  );

  console.log('OK runtime graph actor hint filter contract');
})();

