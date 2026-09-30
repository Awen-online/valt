// Key material: generation, cardano-cli compatible file formats, loading, and address/policy derivation.
//
// Files in a keys directory (all outside the repo, see guard.js):
//   policy.skey / policy.vkey   cardano-cli TextEnvelope JSON {type, description, cborHex}
//   policy.script               cardano-cli simple-script JSON ({"type":"all","scripts":[{"type":"sig","keyHash":...}]})
//   policy.id                   policy id (hex), one line
//   fee.skey / fee.vkey / fee.addr   minting-fee wallet (enterprise address, preprod)
//   keys.json                   non-secret manifest {network, policyId, policyKeyHash, feeAddress, source, ...}
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import {
  Ed25519PrivateKey,
  buildEnterpriseAddress,
  Hash28ByteBase16,
  resolveNativeScriptHash,
  Crypto,
} from '@meshsdk/core-cst';
import { assertSafeKeyDir } from './guard.js';

export const NETWORK_ID = 0; // preprod / testnets
export const NMKR_PREPROD_POLICY = 'bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a';

/** libsodium must be initialised before any key operation. */
export async function cryptoReady() {
  await Crypto.ready();
}

// ── Signing keys ──────────────────────────────────────────────────────

/**
 * Turn a signing key in any supported encoding into a signer.
 * Accepts cardano-cli cborHex ("5820"+32 bytes normal, "5840"+64 bytes extended,
 * "5880"+128 bytes extended-with-chaincode) or bare hex of those payloads.
 */
export function signerFromCborHex(cborHex) {
  let hex = String(cborHex).trim().toLowerCase();
  if (!/^[0-9a-f]+$/.test(hex)) throw new Error('Signing key is not hex.');
  if (hex.startsWith('5820') && hex.length === 68) hex = hex.slice(4);
  else if (hex.startsWith('5840') && hex.length === 132) hex = hex.slice(4);
  else if (hex.startsWith('5880') && hex.length === 260) hex = hex.slice(4);

  let key;
  let kind;
  if (hex.length === 64) {
    // RFC 8032 seed -> key (libsodium seed_keypair), identical to cardano-cli normal keys.
    // NOTE: do NOT use Mesh's buildEd25519PrivateKeyFromSecretKey / MeshTxBuilder.signingKey() here:
    // it clamps the scalar BIP32-style (scalar[31] &= 31) and yields a different public key than
    // cardano-cli for roughly half of all seeds, so an imported NMKR key would not match its policy.
    key = Ed25519PrivateKey.fromNormalHex(hex);
    kind = 'normal';
  } else if (hex.length === 128 || hex.length === 256) {
    // Extended key (e.g. PaymentExtendedSigningKeyShelley_ed25519_bip32): first 64 bytes are the scalar+ext.
    key = Ed25519PrivateKey.fromExtendedHex(hex.slice(0, 128));
    kind = 'extended';
  } else {
    throw new Error(`Unsupported signing key length (${hex.length / 2} bytes).`);
  }
  const pub = key.toPublic();
  return {
    kind,
    key,
    publicKeyHex: pub.hex(),
    keyHash: pub.hash().hex(),
  };
}

export function textEnvelope(type, description, cborHex) {
  return JSON.stringify({ type, description, cborHex }, null, 4) + '\n';
}

function newNormalKey(description) {
  const seed = crypto.randomBytes(32).toString('hex');
  const skeyCbor = '5820' + seed;
  const s = signerFromCborHex(skeyCbor);
  return {
    skey: textEnvelope('PaymentSigningKeyShelley_ed25519', description, skeyCbor),
    vkey: textEnvelope('PaymentVerificationKeyShelley_ed25519', description.replace('Signing', 'Verification'), '5820' + s.publicKeyHex),
    keyHash: s.keyHash,
  };
}

export function enterpriseAddress(keyHash, networkId = NETWORK_ID) {
  return buildEnterpriseAddress(networkId, Hash28ByteBase16(keyHash)).toAddress().toBech32();
}

// ── Native scripts ────────────────────────────────────────────────────

/** cardano-cli simple script JSON -> Mesh NativeScript (slots as strings). */
export function cliScriptToMesh(s) {
  switch (s.type) {
    case 'sig': return { type: 'sig', keyHash: String(s.keyHash).toLowerCase() };
    case 'before': return { type: 'before', slot: String(s.slot) };
    case 'after': return { type: 'after', slot: String(s.slot) };
    case 'all': return { type: 'all', scripts: s.scripts.map(cliScriptToMesh) };
    case 'any': return { type: 'any', scripts: s.scripts.map(cliScriptToMesh) };
    case 'atLeast': return { type: 'atLeast', required: Number(s.required), scripts: s.scripts.map(cliScriptToMesh) };
    default: throw new Error(`Unsupported native script node type "${s.type}".`);
  }
}

export function policyIdOf(cliScript) {
  return resolveNativeScriptHash(cliScriptToMesh(cliScript));
}

/** Collect key hashes and time bounds a script needs. */
export function scriptRequirements(cliScript) {
  const keyHashes = new Set();
  let before = null;
  let after = null;
  const walk = (n) => {
    if (n.type === 'sig') keyHashes.add(String(n.keyHash).toLowerCase());
    else if (n.type === 'before') before = before === null ? Number(n.slot) : Math.min(before, Number(n.slot));
    else if (n.type === 'after') after = after === null ? Number(n.slot) : Math.max(after, Number(n.slot));
    else if (n.scripts) n.scripts.forEach(walk);
  };
  walk(cliScript);
  return { keyHashes: [...keyHashes], beforeSlot: before, afterSlot: after, isSimpleAllOrSig: cliScript.type === 'sig' || cliScript.type === 'all' };
}

// ── Writing / reading key directories ────────────────────────────────

function writeSecret(file, contents) {
  if (fs.existsSync(file)) throw new Error(`Refusing to overwrite existing file ${file}.`);
  fs.writeFileSync(file, contents, { mode: 0o600, flag: 'wx' });
}

/**
 * Best-effort owner-only permissions on the keys directory (chmod 700 on POSIX; on Windows, icacls
 * removes inherited ACEs from the DIRECTORY and grants only the current user, so files created in it
 * afterwards inherit "current user only"). Call it before writing any key.
 */
export function lockDownDir(dir) {
  try {
    if (process.platform === 'win32') {
      const user = process.env.USERNAME;
      if (!user) return 'skipped (USERNAME not set)';
      execFileSync('icacls', [dir, '/inheritance:r', '/grant:r', `${user}:(OI)(CI)F`, '/Q'], { stdio: 'ignore' });
      return `icacls: inheritance removed, full control for ${user} only`;
    }
    fs.chmodSync(dir, 0o700);
    return 'chmod 700 (files 600)';
  } catch (e) {
    return `WARNING: could not restrict permissions (${e.message})`;
  }
}

export function ensureKeyDir(dir) {
  const safe = assertSafeKeyDir(dir);
  fs.mkdirSync(safe, { recursive: true, mode: 0o700 });
  return safe;
}

/** Create a fee wallet in `dir` (fails if one exists). */
export function writeFeeWallet(dir) {
  const k = newNormalKey('Valt minting fee wallet Signing Key');
  writeSecret(path.join(dir, 'fee.skey'), k.skey);
  writeSecret(path.join(dir, 'fee.vkey'), k.vkey);
  const addr = enterpriseAddress(k.keyHash);
  writeSecret(path.join(dir, 'fee.addr'), addr + '\n');
  return { keyHash: k.keyHash, address: addr };
}

/** Write a policy (script + optional key) into `dir`. */
export function writePolicy(dir, { skeyEnvelope, vkeyEnvelope, cliScript }) {
  if (skeyEnvelope) writeSecret(path.join(dir, 'policy.skey'), skeyEnvelope);
  if (vkeyEnvelope) writeSecret(path.join(dir, 'policy.vkey'), vkeyEnvelope);
  writeSecret(path.join(dir, 'policy.script'), JSON.stringify(cliScript, null, 2) + '\n');
  const pid = policyIdOf(cliScript);
  writeSecret(path.join(dir, 'policy.id'), pid + '\n');
  return pid;
}

export function writeManifest(dir, manifest) {
  const file = path.join(dir, 'keys.json');
  fs.writeFileSync(file, JSON.stringify(manifest, null, 2) + '\n', { mode: 0o600 });
}

/** keygen: brand-new self-owned policy + fee wallet. */
export function generate(dir, { beforeSlot = null } = {}) {
  const safe = ensureKeyDir(dir);
  for (const f of ['policy.skey', 'fee.skey', 'policy.script']) {
    if (fs.existsSync(path.join(safe, f))) throw new Error(`${path.join(safe, f)} already exists; choose an empty --out directory.`);
  }
  const perms = lockDownDir(safe);
  const pk = newNormalKey('Valt policy Signing Key');
  const sig = { type: 'sig', keyHash: pk.keyHash };
  const cliScript = beforeSlot ? { type: 'all', scripts: [sig, { type: 'before', slot: Number(beforeSlot) }] } : { type: 'all', scripts: [sig] };
  const policyId = writePolicy(safe, { skeyEnvelope: pk.skey, vkeyEnvelope: pk.vkey, cliScript });
  const fee = writeFeeWallet(safe);
  const manifest = {
    network: 'preprod',
    source: 'generated',
    policyId,
    policyKeyHash: pk.keyHash,
    beforeSlot: beforeSlot ? Number(beforeSlot) : null,
    feeAddress: fee.address,
    createdAt: new Date().toISOString(),
  };
  writeManifest(safe, manifest);
  return { dir: safe, ...manifest, perms };
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

/**
 * Load a keys directory and cross-check it:
 *  - the policy key hash(es) must satisfy the script,
 *  - policy.id must match the script hash.
 */
export function loadKeys(dir) {
  const safe = assertSafeKeyDir(dir);
  const need = ['policy.skey', 'policy.script', 'fee.skey'];
  for (const f of need) {
    if (!fs.existsSync(path.join(safe, f))) throw new Error(`Missing ${f} in ${safe}.`);
  }
  const policyEnv = readJson(path.join(safe, 'policy.skey'));
  const feeEnv = readJson(path.join(safe, 'fee.skey'));
  const cliScript = readJson(path.join(safe, 'policy.script'));
  const policySigner = signerFromCborHex(policyEnv.cborHex);
  const feeSigner = signerFromCborHex(feeEnv.cborHex);
  const policyId = policyIdOf(cliScript);
  const req = scriptRequirements(cliScript);

  const idFile = path.join(safe, 'policy.id');
  if (fs.existsSync(idFile)) {
    const stored = fs.readFileSync(idFile, 'utf8').trim().toLowerCase();
    if (stored !== policyId) throw new Error(`policy.id (${stored}) does not match policy.script hash (${policyId}).`);
  }
  if (!req.isSimpleAllOrSig || req.keyHashes.length !== 1) {
    throw new Error('Only single-signature policies ({"type":"all"|"sig"} with one key) are supported.');
  }
  if (req.keyHashes[0] !== policySigner.keyHash) {
    throw new Error(`policy.skey (key hash ${policySigner.keyHash}) does not match the key in policy.script (${req.keyHashes[0]}).`);
  }
  return {
    dir: safe,
    policyId,
    cliScript,
    meshScript: cliScriptToMesh(cliScript),
    requirements: req,
    policySigner,
    feeSigner,
    feeAddress: enterpriseAddress(feeSigner.keyHash),
  };
}
