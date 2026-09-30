// Import an NMKR Studio "Export Policy Keys" zip into the tool's keys-directory format.
//
// NMKR does not document the zip layout, so this reader is deliberately tolerant. It looks at every
// entry and recognises:
//   - cardano-cli TextEnvelope signing keys   {"type":"...SigningKey...","cborHex":"5820..."}   (policy.skey)
//   - cardano-cli simple scripts               {"type":"all","scripts":[{"type":"sig","keyHash":...}]} (policy.script)
//   - JSON objects that carry those as fields  (e.g. {"policyScript": "...", "privateSigningkey": "..."})
//   - bare cborHex text                         5820<64 hex>
//   - a policy id text file                     56 hex chars
// The signing key actually used is the one whose key hash appears in the script; the derived policy id is
// compared with the expected one (default: Valt's NMKR preprod policy).
import fs from 'node:fs';
import path from 'node:path';
import AdmZip from 'adm-zip';
import {
  signerFromCborHex, policyIdOf, scriptRequirements, textEnvelope,
  ensureKeyDir, writePolicy, writeFeeWallet, writeManifest, lockDownDir, enterpriseAddress,
  NMKR_PREPROD_POLICY,
} from './keys.js';

const SCRIPT_TYPES = new Set(['all', 'any', 'sig', 'atLeast', 'before', 'after']);

function looksLikeScript(o) {
  return o && typeof o === 'object' && SCRIPT_TYPES.has(o.type) && (Array.isArray(o.scripts) || typeof o.keyHash === 'string');
}
function looksLikeSkeyEnvelope(o) {
  return o && typeof o === 'object' && typeof o.cborHex === 'string' && /SigningKey/i.test(String(o.type || ''));
}
function tryJson(s) {
  if (typeof s !== 'string') return undefined;
  const t = s.trim();
  if (!t.startsWith('{') && !t.startsWith('[')) return undefined;
  try { return JSON.parse(t); } catch { return undefined; }
}

function collect(value, where, found, depth = 0) {
  if (depth > 6 || value == null) return;
  if (typeof value === 'string') {
    const j = tryJson(value);
    if (j !== undefined) return collect(j, where, found, depth + 1);
    const t = value.trim().toLowerCase();
    if (/^(5820[0-9a-f]{64}|5840[0-9a-f]{128}|5880[0-9a-f]{256})$/.test(t)) found.skeys.push({ where, cborHex: t });
    else if (/^[0-9a-f]{56}$/.test(t)) found.policyIds.push({ where, id: t });
    return;
  }
  if (Array.isArray(value)) { value.forEach((v, i) => collect(v, `${where}[${i}]`, found, depth + 1)); return; }
  if (typeof value === 'object') {
    if (looksLikeSkeyEnvelope(value)) { found.skeys.push({ where, cborHex: value.cborHex.toLowerCase(), type: value.type }); return; }
    if (looksLikeScript(value)) { found.scripts.push({ where, script: value }); return; }
    for (const [k, v] of Object.entries(value)) collect(v, `${where}.${k}`, found, depth + 1);
  }
}

/** Inspect a zip (or a directory of already-extracted files) and return candidates. */
export function scanExport(source) {
  const found = { skeys: [], scripts: [], policyIds: [], entries: [] };
  const handle = (name, text) => {
    found.entries.push(name);
    collect(text, name, found);
  };
  const stat = fs.statSync(source);
  if (stat.isDirectory()) {
    for (const f of fs.readdirSync(source)) {
      const p = path.join(source, f);
      if (fs.statSync(p).isFile() && fs.statSync(p).size < 1_000_000) handle(f, fs.readFileSync(p, 'utf8'));
    }
  } else {
    const zip = new AdmZip(source);
    for (const e of zip.getEntries()) {
      if (e.isDirectory || e.header.size > 1_000_000) continue;
      handle(e.entryName, e.getData().toString('utf8'));
    }
  }
  return found;
}

/**
 * Resolve the export into {cliScript, policyId, signer, skeyCbor}. Throws with a readable
 * explanation (listing entry names only, never key material) when it can't.
 */
export function resolveExport(found) {
  if (found.scripts.length === 0) {
    throw new Error(`No native policy script found in export. Entries: ${found.entries.join(', ') || '(none)'}`);
  }
  // Prefer the outermost script (dedupe by policy id).
  const byId = new Map();
  for (const s of found.scripts) {
    try { byId.set(policyIdOf(s.script), s); } catch { /* ignore malformed */ }
  }
  const signers = [];
  for (const k of found.skeys) {
    try { signers.push({ ...k, signer: signerFromCborHex(k.cborHex) }); } catch { /* ignore */ }
  }
  for (const [policyId, s] of byId) {
    const req = scriptRequirements(s.script);
    const match = signers.find((k) => req.keyHashes.includes(k.signer.keyHash));
    if (match) return { policyId, cliScript: s.script, requirements: req, signer: match.signer, skeyCbor: match.cborHex, skeyType: match.type, scriptEntry: s.where, skeyEntry: match.where };
  }
  const ids = [...byId.keys()];
  const hashes = signers.map((k) => k.signer.keyHash);
  throw new Error(
    `Export has policy script(s) ${ids.join(', ')} but no signing key that matches them ` +
    `(found ${signers.length} signing key(s) with key hash(es) ${hashes.join(', ') || 'none'}). ` +
    `Entries: ${found.entries.join(', ')}`
  );
}

export function importNmkr({ zip, out, expectPolicy = NMKR_PREPROD_POLICY, allowMismatch = false }) {
  if (!zip || !fs.existsSync(zip)) throw new Error(`Export not found: ${zip}`);
  const found = scanExport(zip);
  const r = resolveExport(found);
  if (expectPolicy && r.policyId !== expectPolicy && !allowMismatch) {
    throw new Error(`Derived policy id ${r.policyId} does not match expected ${expectPolicy}. Re-run with --expect-policy ${r.policyId} if this is intended.`);
  }
  if (r.requirements.keyHashes.length !== 1) {
    throw new Error('Policy needs more than one signature; this tool only supports single-signature policies.');
  }
  const dir = ensureKeyDir(out);
  if (fs.existsSync(path.join(dir, 'policy.skey'))) throw new Error(`${dir} already has a policy.skey; use an empty directory.`);
  const perms = lockDownDir(dir);

  const skeyType = r.signer.kind === 'normal' ? 'PaymentSigningKeyShelley_ed25519' : (r.skeyType || 'PaymentExtendedSigningKeyShelley_ed25519_bip32');
  const vkeyType = r.signer.kind === 'normal' ? 'PaymentVerificationKeyShelley_ed25519' : 'PaymentExtendedVerificationKeyShelley_ed25519_bip32';
  const skeyCbor = r.skeyCbor.length === 64 ? '5820' + r.skeyCbor : r.skeyCbor;
  const policyId = writePolicy(dir, {
    skeyEnvelope: textEnvelope(skeyType, 'Valt NMKR policy Signing Key (imported)', skeyCbor),
    vkeyEnvelope: r.signer.kind === 'normal' ? textEnvelope(vkeyType, 'Valt NMKR policy Verification Key (imported)', '5820' + r.signer.publicKeyHex) : null,
    cliScript: r.cliScript,
  });

  let fee;
  if (fs.existsSync(path.join(dir, 'fee.skey'))) {
    const env = JSON.parse(fs.readFileSync(path.join(dir, 'fee.skey'), 'utf8'));
    const s = signerFromCborHex(env.cborHex);
    fee = { address: enterpriseAddress(s.keyHash) };
  } else {
    fee = writeFeeWallet(dir);
  }
  const manifest = {
    network: 'preprod',
    source: 'nmkr-import',
    policyId,
    policyKeyHash: r.signer.keyHash,
    beforeSlot: r.requirements.beforeSlot,
    feeAddress: fee.address,
    importedFrom: path.basename(zip),
    scriptEntry: r.scriptEntry,
    skeyEntry: r.skeyEntry,
    createdAt: new Date().toISOString(),
  };
  writeManifest(dir, manifest);
  return { dir, ...manifest, matchesExpected: policyId === expectPolicy, perms };
}
