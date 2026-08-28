/**
 * @file
 * Shared helpers for contract tests that assert against JS source text.
 *
 * GameShell.js has progressively had internals extracted into `js/v2/shell/*`
 * coordinator/helper modules (projection helpers, fetch bridge, quest and room
 * generation coordinators, target-pick controller, campaign settings). The
 * functions keep their names and signatures, so contract tests that
 * string-match or body-extract them should treat the shell as one composed
 * unit rather than pinning behaviour to GameShell.js itself.
 */

const fs = require('fs');
const path = require('path');

const V2_DIR = path.resolve(__dirname, '..', '..', 'js', 'v2');
const GAME_SHELL_PATH = path.join(V2_DIR, 'GameShell.js');
const SHELL_DIR = path.join(V2_DIR, 'shell');

/**
 * List the files that make up the composed GameShell implementation.
 *
 * @return {string[]}
 *   Absolute file paths, GameShell.js first.
 */
function gameShellSourceFiles() {
  const files = [GAME_SHELL_PATH];
  if (fs.existsSync(SHELL_DIR)) {
    fs.readdirSync(SHELL_DIR)
      .filter((f) => f.endsWith('.js'))
      .sort()
      .forEach((f) => files.push(path.join(SHELL_DIR, f)));
  }
  return files.filter((f) => fs.existsSync(f));
}

/**
 * Read GameShell.js concatenated with its extracted shell modules.
 *
 * @return {string}
 *   Concatenated source text.
 */
function readGameShellSource() {
  return gameShellSourceFiles()
    .map((f) => fs.readFileSync(f, 'utf8'))
    .join('\n\n');
}

module.exports = {
  V2_DIR,
  GAME_SHELL_PATH,
  SHELL_DIR,
  gameShellSourceFiles,
  readGameShellSource,
};
