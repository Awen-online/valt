// Safety guards for key material: keys must never live inside a git work tree
// (this repo or any other) or inside a WordPress web root.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const TOOL_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

function norm(p) {
  const r = path.resolve(p);
  return process.platform === 'win32' ? r.toLowerCase() : r;
}

/** Resolve symlinks/junctions where the path (or its nearest existing parent) exists. */
function realish(p) {
  let cur = path.resolve(p);
  const tail = [];
  while (!fs.existsSync(cur)) {
    const parent = path.dirname(cur);
    if (parent === cur) break;
    tail.unshift(path.basename(cur));
    cur = parent;
  }
  let real = cur;
  try { real = fs.realpathSync.native(cur); } catch { /* keep */ }
  return path.join(real, ...tail);
}

/** The git work tree that contains this tool (the Valt repo), or null. */
export function toolRepoRoot() {
  let cur = TOOL_DIR;
  for (;;) {
    if (fs.existsSync(path.join(cur, '.git'))) return cur;
    const parent = path.dirname(cur);
    if (parent === cur) return null;
    cur = parent;
  }
}

/**
 * Throw if `dir` is inside the Valt repo, inside any git work tree, or inside a
 * WordPress install (an ancestor holding wp-config.php or wp-load.php).
 */
export function assertSafeKeyDir(dir) {
  if (!dir) throw new Error('A keys directory is required (--out / --keys).');
  const target = realish(dir);
  const t = norm(target);

  const repo = toolRepoRoot();
  const inside = (root) => {
    const r = norm(realish(root));
    return t === r || t.startsWith(r.endsWith(path.sep) ? r : r + path.sep);
  };
  if (repo && inside(repo)) {
    throw new Error(`Refusing to use key directory inside the repo tree (${repo}). Use a folder outside the repo, e.g. %USERPROFILE%\\valt-keys\\preprod.`);
  }
  if (inside(TOOL_DIR)) {
    throw new Error('Refusing to use key directory inside the tool directory.');
  }

  let cur = target;
  for (;;) {
    if (fs.existsSync(path.join(cur, '.git'))) {
      throw new Error(`Refusing to use key directory inside a git work tree (${cur}).`);
    }
    if (fs.existsSync(path.join(cur, 'wp-config.php')) || fs.existsSync(path.join(cur, 'wp-load.php'))) {
      throw new Error(`Refusing to use key directory inside a WordPress install / web root (${cur}).`);
    }
    const parent = path.dirname(cur);
    if (parent === cur) break;
    cur = parent;
  }
  return target;
}

/** Output paths for non-secret artefacts (signed tx CBOR) may be anywhere except the web root. */
export function assertNotWebRoot(file) {
  let cur = path.dirname(realish(file));
  for (;;) {
    if (fs.existsSync(path.join(cur, 'wp-config.php'))) {
      throw new Error(`Refusing to write inside a WordPress web root (${cur}).`);
    }
    const parent = path.dirname(cur);
    if (parent === cur) return;
    cur = parent;
  }
}
