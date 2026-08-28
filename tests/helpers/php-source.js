/**
 * @file
 * Shared helpers for contract tests that assert against PHP source text.
 *
 * Several large services (most notably EncounterPhaseHandler) have been split
 * into composed traits over time. Contract tests that string-match a single
 * file break whenever an implementation moves between the parent class and one
 * of its traits, even though the behaviour is unchanged.
 *
 * `readComposedSource()` resolves a class/trait's `use <SomeTrait>;` graph and
 * returns the concatenated source, so assertions keep testing "this behaviour
 * exists in the composed implementation" rather than "this behaviour lives in
 * this exact file".
 */

const fs = require('fs');
const path = require('path');

const SERVICE_DIR = path.resolve(__dirname, '..', '..', 'src', 'Service');

/**
 * Collect the transitive trait composition for a PHP class or trait file.
 *
 * @param {string} entryFile
 *   File name relative to src/Service, e.g. 'EncounterPhaseHandler.php'.
 *
 * @return {string[]}
 *   Absolute paths in composition order, starting with the entry file.
 */
function resolveCompositionChain(entryFile) {
  const seen = new Set();
  const ordered = [];
  const queue = [entryFile];

  while (queue.length > 0) {
    const current = queue.shift();
    if (seen.has(current)) {
      continue;
    }
    const absolute = path.join(SERVICE_DIR, current);
    if (!fs.existsSync(absolute)) {
      continue;
    }
    seen.add(current);
    ordered.push(absolute);

    const source = fs.readFileSync(absolute, 'utf8');
    // Trait `use` statements inside a class/trait body are indented; top-level
    // `use Drupal\...;` imports are not, which keeps this from pulling in
    // unrelated namespace imports.
    const traitUses = source.match(/^[ \t]+use\s+([A-Za-z0-9_]+Trait)\s*;/gm) || [];
    traitUses.forEach((line) => {
      const name = line.trim().replace(/^use\s+/, '').replace(/\s*;$/, '');
      queue.push(`${name}.php`);
    });
  }

  return ordered;
}

/**
 * Read a PHP service plus every trait it composes, concatenated.
 *
 * @param {string} entryFile
 *   File name relative to src/Service.
 *
 * @return {string}
 *   Concatenated source text.
 */
function readComposedSource(entryFile) {
  const files = resolveCompositionChain(entryFile);
  if (files.length === 0) {
    throw new Error(`Could not resolve any source for ${entryFile}`);
  }
  return files.map((f) => fs.readFileSync(f, 'utf8')).join('\n\n');
}

/**
 * Convenience accessor for the composed EncounterPhaseHandler implementation.
 *
 * @return {string}
 *   Concatenated source text.
 */
function readEncounterPhaseHandlerSource() {
  return readComposedSource('EncounterPhaseHandler.php');
}

/**
 * Read the composed GM reply pipeline source.
 *
 * The GM turn pipeline was extracted out of RoomChatService into the
 * dedicated `src/Service/GmSubsystem` collaborators, with RoomChatService
 * delegating into them. Contract tests care that the pipeline behaviour exists
 * and is wired, not which of those files currently hosts it, so this returns
 * RoomChatService (plus its traits) concatenated with the whole subsystem.
 *
 * @return {string}
 *   Concatenated source text.
 */
function readGmPipelineSource() {
  const subsystemDir = path.join(SERVICE_DIR, 'GmSubsystem');
  const subsystemFiles = fs.existsSync(subsystemDir)
    ? fs
      .readdirSync(subsystemDir)
      .filter((f) => f.endsWith('.php'))
      .sort()
      .map((f) => fs.readFileSync(path.join(subsystemDir, f), 'utf8'))
    : [];

  return [readComposedSource('RoomChatService.php'), ...subsystemFiles].join('\n\n');
}

module.exports = {
  SERVICE_DIR,
  resolveCompositionChain,
  readComposedSource,
  readEncounterPhaseHandlerSource,
  readGmPipelineSource,
};
