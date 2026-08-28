/**
 * @file
 * Shared helper: load an ES module class/function into a plain Node context by
 * resolving its static import graph.
 *
 * Several browser-facing panels under `js/v2/` are ES modules that import
 * shared helpers (`escapeQuestHtml`, `escapeTooltipAttr`, inventory utils, …).
 * Test harnesses historically loaded a single file and stripped `import`
 * statements outright, which silently removed real dependencies. The module
 * then threw `ReferenceError: <helper> is not defined` the first time a code
 * path touched one — a harness artifact, not a product defect.
 *
 * `loadModuleScope()` instead walks the relative import graph depth-first,
 * concatenates each dependency ahead of the importing module, and evaluates
 * the whole thing once. Dependencies stay real, so the tests exercise the
 * genuine code path.
 *
 * Only relative specifiers are followed. Bare specifiers (npm packages) are
 * dropped, matching the previous behaviour for third-party code.
 */

const fs = require('fs');
const path = require('path');

const V2_DIR = path.resolve(__dirname, '..', '..', 'js', 'v2');

/**
 * Strips `import`/`export` syntax so a module body can run as a plain script.
 */
function stripModuleSyntax(source) {
  return source
    // `import … from '…';` and bare `import '…';`
    .replace(/^\s*import\s+[\s\S]*?from\s*['"][^'"]+['"]\s*;?\s*$/gm, '')
    .replace(/^\s*import\s*['"][^'"]+['"]\s*;?\s*$/gm, '')
    // `export default X;` has no meaning here.
    .replace(/^\s*export\s+default\s+/gm, '')
    // `export { a, b };` re-export lists.
    .replace(/^\s*export\s*\{[^}]*\}\s*;?\s*$/gm, '')
    // Leading `export` on declarations.
    .replace(/^\s*export\s+/gm, '');
}

/**
 * Collects relative import specifiers from a module body.
 */
function relativeImports(source) {
  const specifiers = [];
  const pattern = /^\s*import\s+(?:[\s\S]*?from\s*)?['"](\.[^'"]+)['"]\s*;?\s*$/gm;
  let match;
  while ((match = pattern.exec(source)) !== null) {
    specifiers.push(match[1]);
  }
  return specifiers;
}

/**
 * Depth-first walk of the import graph, dependencies first.
 *
 * @param {string} absPath
 *   Absolute path of the entry module.
 * @param {Set<string>} seen
 *   Guards against cycles and repeated inclusion.
 *
 * @return {string[]}
 *   Module bodies in evaluation order.
 */
function collectSources(absPath, seen) {
  if (seen.has(absPath)) {
    return [];
  }
  seen.add(absPath);

  const source = fs.readFileSync(absPath, 'utf8');
  const chunks = [];

  for (const specifier of relativeImports(source)) {
    // Browser modules carry `?v=<cache-bust>` query strings that are not part
    // of the on-disk path.
    const depPath = path.resolve(path.dirname(absPath), specifier.replace(/[?#].*$/, ''));
    if (!fs.existsSync(depPath)) {
      throw new Error(`Unresolved import '${specifier}' from ${absPath}`);
    }
    chunks.push(...collectSources(depPath, seen));
  }

  chunks.push(`/* ---- ${path.relative(V2_DIR, absPath)} ---- */\n${stripModuleSyntax(source)}`);
  return chunks;
}

/**
 * Evaluates a module (plus its relative dependencies) and returns named values.
 *
 * @param {string} relPathFromTests
 *   Module path relative to the `tests/` directory, e.g.
 *   `../js/v2/panels/MerchantPanel.js`.
 * @param {string[]} names
 *   Top-level binding names to return.
 *
 * @return {object}
 *   Map of requested name to value.
 */
function loadModuleScope(relPathFromTests, names) {
  const absPath = path.resolve(__dirname, '..', relPathFromTests);
  const body = collectSources(absPath, new Set()).join('\n\n');
  const returnList = names.map((name) => `${name}: typeof ${name} !== 'undefined' ? ${name} : undefined`).join(', ');
  // eslint-disable-next-line no-new-func
  const scope = new Function(`${body}\nreturn { ${returnList} };`)();

  for (const name of names) {
    if (scope[name] === undefined) {
      throw new Error(`'${name}' was not defined by ${relPathFromTests} or its imports`);
    }
  }
  return scope;
}

/**
 * Convenience wrapper for the common single-binding case.
 */
function loadModuleExport(relPathFromTests, name) {
  return loadModuleScope(relPathFromTests, [name])[name];
}

module.exports = {
  V2_DIR,
  stripModuleSyntax,
  relativeImports,
  loadModuleScope,
  loadModuleExport,
};
