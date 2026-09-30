// Offline unit tests (no network, no funds). Run: npm test
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import AdmZip from 'adm-zip';
import { Address } from '@meshsdk/core-cst';
import {
  cryptoReady, signerFromCborHex, enterpriseAddress, policyIdOf, generate, loadKeys, textEnvelope, NMKR_PREPROD_POLICY,
} from '../src/keys.js';
import { importNmkr } from '../src/nmkr-import.js';
import {
  chunk64, decodeEntities, isoDuration, shortDescription, buildCip25, validateCip25, assetNameFor,
} from '../src/metadata.js';
import { assertSafeKeyDir } from '../src/guard.js';
import { mint } from '../src/mint.js';

const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'valt-mint-test-'));
const BUYER = 'addr_test1qzeeyyv6nn33nl9wwm9u6l0k27edl6wq9nvvr07lvdkwlayvjnpagh3n3p22r3ntmstvewhqk45j7p6ppwaq5ecklxusv3u37p';
const LONDON = {
  title: 'London', artist: 'Hazzy Jo', image_cid: 'QmP4ssQS9EN8bxpjiU6hnSyQFbpYYFBJfRwsW9s929FZzf', image_mime: 'image/jpeg',
  asset_base: 'london300', description: 'London is a melodic, atmospheric track blending African-inspired rhythms.',
};

before(async () => { await cryptoReady(); });
after(() => { fs.rmSync(TMP, { recursive: true, force: true }); });

test('NMKR preprod policy script hashes to bf5a88ac…', () => {
  const id = policyIdOf({ type: 'all', scripts: [{ type: 'sig', keyHash: '7486c7912d8b3b58d485fdd61b5e0645949ad1a393366df4fe35b4f5' }] });
  assert.equal(id, NMKR_PREPROD_POLICY);
});

test('normal signing keys derive the RFC 8032 (cardano-cli) public key', () => {
  for (let i = 0; i < 16; i++) {
    const seed = crypto.randomBytes(32);
    const s = signerFromCborHex('5820' + seed.toString('hex'));
    const pk = crypto.createPrivateKey({ key: Buffer.concat([Buffer.from('302e020100300506032b657004220420', 'hex'), seed]), format: 'der', type: 'pkcs8' });
    const pub = crypto.createPublicKey(pk).export({ format: 'der', type: 'spki' }).subarray(-32).toString('hex');
    assert.equal(s.publicKeyHex, pub);
  }
});

test('guard refuses key dirs inside the repo', () => {
  assert.throws(() => assertSafeKeyDir(path.resolve('keys')), /inside the repo/);
  assert.throws(() => assertSafeKeyDir(path.resolve('..', '..', 'docs', 'x')), /inside the repo/);
  assert.ok(assertSafeKeyDir(path.join(TMP, 'ok')));
});

test('guard refuses a WordPress web root', () => {
  const wp = path.join(TMP, 'site');
  fs.mkdirSync(path.join(wp, 'wp-content'), { recursive: true });
  fs.writeFileSync(path.join(wp, 'wp-config.php'), '<?php');
  assert.throws(() => assertSafeKeyDir(path.join(wp, 'wp-content', 'keys')), /web root/);
});

test('metadata helpers', () => {
  assert.equal(decodeEntities('Don&#8217;t &amp; Stop &#x2019;'), 'Don’t & Stop ’');
  assert.equal(isoDuration('2:36'), 'PT2M36S');
  assert.equal(isoDuration('PT2M36S'), 'PT2M36S');
  assert.equal(isoDuration('156'), 'PT2M36S');
  const long = 'é'.repeat(70); // 140 bytes
  const parts = chunk64(long);
  assert.ok(parts.every((p) => Buffer.byteLength(p) <= 64));
  assert.equal(parts.join(''), long);
  assert.equal(shortDescription('One. Two three.', 'x'), 'One. Two three.');
  assert.ok(Buffer.byteLength(shortDescription('a'.repeat(200), 'x')) <= 64);
  assert.equal(assetNameFor(LONDON, 18), 'valtlondon300e18');
  assert.throws(() => assetNameFor({ ...LONDON, asset_base: 'abcdefghijklmnopqrst123456' }, 1), /bytes/);
});

test('CIP-25 build: entities decoded, long strings split, gating fields single strings', () => {
  const song = {
    ...LONDON, title: 'Don&#8217;t Stop', album: 'A'.repeat(100), genre: ['Afro', 'Electronic'], duration: '2:36', track: 3,
    songwriter: 'Hazzy Jo, Someone Else', producer: 'Prod Name', copyright: '(c) 2026 Hazzy Jo',
    image_cid: 'bafybeigdyrzt5sfp7udm7hu76uh7y26nf3efuylqabf3oclgtqy55fbzdi', audio_cid: 'QmP4ssQS9EN8bxpjiU6hnSyQFbpYYFBJfRwsW9s929FZzf',
  };
  const p = buildCip25(song, 1, NMKR_PREPROD_POLICY);
  const md = p.metadata[NMKR_PREPROD_POLICY].valtlondon300e01;
  assert.equal(md.name, 'Don’t Stop');
  assert.ok(Array.isArray(md.album) && md.album.join('') === 'A'.repeat(100));
  assert.ok(Array.isArray(md.image)); // ipfs://bafy... is 66 bytes -> split
  assert.equal(md.genre, 'Afro, Electronic');
  assert.equal(md.duration, 'PT2M36S');
  assert.deepEqual(md.authors, [{ name: 'Hazzy Jo' }, { name: 'Someone Else' }]);
  assert.deepEqual(md.contributing_artists, [{ name: 'Prod Name', role: ['Producer'] }]);
  assert.deepEqual(md.copyright, { master: '(c) 2026 Hazzy Jo' });
  assert.equal(md.files[0].src, 'ipfs://QmP4ssQS9EN8bxpjiU6hnSyQFbpYYFBJfRwsW9s929FZzf');
  const v = validateCip25(p, song);
  assert.deepEqual(v.errors, []);
});

test('CIP-25 validation rejects placeholder image and over-long artist', () => {
  const p1 = buildCip25({ ...LONDON, image_cid: 'PLACEHOLDER' }, 1, NMKR_PREPROD_POLICY);
  assert.ok(validateCip25(p1).errors.some((e) => /image/.test(e)));
  const p2 = buildCip25({ ...LONDON, artist: 'X'.repeat(70) }, 1, NMKR_PREPROD_POLICY);
  assert.ok(validateCip25(p2).errors.some((e) => /bytes/.test(e)));
});

test('keygen writes a consistent, loadable key dir; refuses to overwrite', () => {
  const dir = path.join(TMP, 'k1');
  const r = generate(dir);
  const k = loadKeys(dir);
  assert.equal(k.policyId, r.policyId);
  assert.equal(k.policySigner.keyHash, r.policyKeyHash);
  const a = Address.fromBech32(k.feeAddress);
  assert.equal(a.getNetworkId(), 0);
  assert.throws(() => generate(dir), /exists/);
  const txt = fs.readdirSync(dir).map((f) => (fs.statSync(path.join(dir, f)).isFile() ? fs.readFileSync(path.join(dir, f), 'utf8') : '')).join('');
  assert.ok(txt.includes('cborHex'));
});

test('loadKeys rejects a policy key that does not match the script', () => {
  const dir = path.join(TMP, 'k-mismatch');
  fs.mkdirSync(dir);
  const seed = crypto.randomBytes(32).toString('hex');
  fs.writeFileSync(path.join(dir, 'policy.skey'), textEnvelope('PaymentSigningKeyShelley_ed25519', 'x', '5820' + seed));
  fs.writeFileSync(path.join(dir, 'fee.skey'), textEnvelope('PaymentSigningKeyShelley_ed25519', 'x', '5820' + crypto.randomBytes(32).toString('hex')));
  fs.writeFileSync(path.join(dir, 'policy.script'), JSON.stringify({ type: 'all', scripts: [{ type: 'sig', keyHash: '7486c7912d8b3b58d485fdd61b5e0645949ad1a393366df4fe35b4f5' }] }));
  assert.throws(() => loadKeys(dir), /does not match/);
});

function fakeNmkrZip(file, { script, skeyCbor, layout }) {
  const zip = new AdmZip();
  if (layout === 'cli') {
    zip.addFile('policy.script', Buffer.from(JSON.stringify(script)));
    zip.addFile('policy.skey', Buffer.from(textEnvelope('PaymentSigningKeyShelley_ed25519', '', skeyCbor)));
    zip.addFile('policy.vkey', Buffer.from(textEnvelope('PaymentVerificationKeyShelley_ed25519', '', '5820' + '00'.repeat(32))));
    zip.addFile('policyid.txt', Buffer.from(policyIdOf(script)));
  } else {
    // A single JSON with the envelope embedded as a string (API-style export).
    zip.addFile('policy.json', Buffer.from(JSON.stringify({
      policyId: policyIdOf(script),
      policyScript: JSON.stringify(script),
      privateSigningkey: JSON.stringify({ type: 'PaymentSigningKeyShelley_ed25519', cborHex: skeyCbor }),
    })));
  }
  zip.writeZip(file);
}

test('import-nmkr: cardano-cli layout and JSON layout; policy-id check', () => {
  for (const layout of ['cli', 'json']) {
    const seed = crypto.randomBytes(32).toString('hex');
    const s = signerFromCborHex('5820' + seed);
    const script = { type: 'all', scripts: [{ type: 'sig', keyHash: s.keyHash }] };
    const zip = path.join(TMP, `nmkr-${layout}.zip`);
    fakeNmkrZip(zip, { script, skeyCbor: '5820' + seed, layout });
    // Default expectation is the real NMKR policy -> a different policy must be refused.
    assert.throws(() => importNmkr({ zip, out: path.join(TMP, `imp-${layout}-refused`) }), /does not match expected/);
    const r = importNmkr({ zip, out: path.join(TMP, `imp-${layout}`), expectPolicy: policyIdOf(script) });
    assert.equal(r.policyId, policyIdOf(script));
    assert.equal(r.matchesExpected, true);
    const k = loadKeys(r.dir);
    assert.equal(k.policySigner.keyHash, s.keyHash);
  }
});

test('import-nmkr: real NMKR script with a non-matching key is rejected', () => {
  const zip = path.join(TMP, 'nmkr-wrongkey.zip');
  fakeNmkrZip(zip, { script: { type: 'all', scripts: [{ type: 'sig', keyHash: '7486c7912d8b3b58d485fdd61b5e0645949ad1a393366df4fe35b4f5' }] }, skeyCbor: '5820' + crypto.randomBytes(32).toString('hex'), layout: 'cli' });
  assert.throws(() => importNmkr({ zip, out: path.join(TMP, 'imp-wrongkey') }), /no signing key that matches/);
});

test('offline mint build with a fake UTxO: balanced, 2 witnesses, never submittable', async () => {
  const dir = path.join(TMP, 'k-mint');
  generate(dir);
  const r = await mint({ keys: dir, song: LONDON, edition: 18, to: BUYER, fakeUtxo: true, offline: true, log: () => {} });
  assert.equal(r.submitted, false);
  const s = r.summary;
  assert.equal(s.vkeyWitnesses, 2);
  assert.equal(s.nativeScripts, 1);
  assert.ok(s.feeCoversSignedSize);
  const out = s.outputs.reduce((t, o) => t + BigInt(o.lovelace), 0n) + BigInt(s.fee);
  assert.equal(out, 10_000_000n);
  assert.ok(s.outputs[0].assets[0].endsWith('.valtlondon300e18 x1'));
  assert.equal(s.outputs[0].address, BUYER);
  await assert.rejects(mint({ keys: dir, song: LONDON, edition: 18, to: BUYER, fakeUtxo: true, offline: true, submit: true, log: () => {} }), /cannot be combined/);
  await assert.rejects(mint({ keys: dir, song: LONDON, edition: 18, to: enterpriseAddress('f6ba24e85476c1f1daa1e0b4c5bfa8057da6235fbb235759beab17cf', 1), fakeUtxo: true, offline: true, log: () => {} }), /testnet/);
});

test('time-locked policy: TTL stays below the lock; expired lock refuses', async () => {
  const future = path.join(TMP, 'k-lock-future');
  generate(future, { beforeSlot: 999_999_999 });
  const r = await mint({ keys: future, song: LONDON, edition: 2, to: BUYER, fakeUtxo: true, offline: true, log: () => {} });
  assert.ok(r.summary.ttlSlot < 999_999_999);
  const past = path.join(TMP, 'k-lock-past');
  generate(past, { beforeSlot: 1000 });
  await assert.rejects(mint({ keys: past, song: LONDON, edition: 2, to: BUYER, fakeUtxo: true, offline: true, log: () => {} }), /can no longer mint/);
});
