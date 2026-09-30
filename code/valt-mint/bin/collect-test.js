#!/usr/bin/env node
// Disclosed multi-wallet checkout test for the live Anvil collect flow (Cardano PREPROD only).
//
// These are Awen test wallets. Their collects are recorded as a checkout test, never as collectors
// or sales.
//
//   node bin/collect-test.js wallets --dir <keysdir> [--n 5]
//       Create N enterprise test wallets (walletN.skey/.vkey/.addr). Prints addresses only.
//   node bin/collect-test.js balances --dir <keysdir>
//   node bin/collect-test.js collect --dir <keysdir> --wallet <i> --song <id> --qty <n> [--site URL]
//       Runs the same REST calls as the site's Collect button: build -> sign -> submit -> confirm.
//   node bin/collect-test.js run --dir <keysdir> --song <id> --plan 1,2,3,1,2 [--concurrent]
//       One collect per wallet with the given quantities (optionally all at once).
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import {
  Transaction, TxCBOR, VkeyWitness, TransactionWitnessSet, CborSet, HexBlob,
  Ed25519PublicKeyHex, Ed25519SignatureHex, toTxUnspentOutput,
} from '@meshsdk/core-cst';
import {
  cryptoReady, signerFromCborHex, textEnvelope, enterpriseAddress, ensureKeyDir, lockDownDir,
} from '../src/keys.js';
import { meshProvider, KOIOS } from '../src/chain.js';

const args = process.argv.slice(2);
const cmd = args[0];
const opt = (k, d) => { const i = args.indexOf('--' + k); return i >= 0 ? (args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : true) : d; };
const SITE = String(opt('site', 'https://www.valt.digital')).replace(/\/$/, '');

function walletFiles(dir) {
  return fs.readdirSync(dir).filter((f) => /^wallet\d+\.skey$/.test(f))
    .map((f) => Number(f.match(/\d+/)[0])).sort((a, b) => a - b);
}

function loadWallet(dir, i) {
  const env = JSON.parse(fs.readFileSync(path.join(dir, `wallet${i}.skey`), 'utf8'));
  const s = signerFromCborHex(env.cborHex);
  return { i, signer: s, address: enterpriseAddress(s.keyHash) };
}

async function createWallets(dir, n) {
  const safe = ensureKeyDir(dir);
  lockDownDir(safe);
  const have = new Set(walletFiles(safe));
  for (let i = 1; i <= n; i++) {
    if (have.has(i)) { console.log(`wallet${i} ${loadWallet(safe, i).address} (existing)`); continue; }
    const cbor = '5820' + crypto.randomBytes(32).toString('hex');
    const s = signerFromCborHex(cbor);
    const addr = enterpriseAddress(s.keyHash);
    const d = `Valt checkout test wallet ${i} (Awen, preprod)`;
    fs.writeFileSync(path.join(safe, `wallet${i}.skey`), textEnvelope('PaymentSigningKeyShelley_ed25519', d + ' Signing Key', cbor), { mode: 0o600, flag: 'wx' });
    fs.writeFileSync(path.join(safe, `wallet${i}.vkey`), textEnvelope('PaymentVerificationKeyShelley_ed25519', d + ' Verification Key', '5820' + s.publicKeyHex), { mode: 0o600, flag: 'wx' });
    fs.writeFileSync(path.join(safe, `wallet${i}.addr`), addr + '\n', { mode: 0o600, flag: 'wx' });
    console.log(`wallet${i} ${addr}`);
  }
}

async function lovelaceOf(addr) {
  const r = await fetch(`${KOIOS}/address_info`, { method: 'POST', headers: { 'content-type': 'application/json', accept: 'application/json' }, body: JSON.stringify({ _addresses: [addr] }) });
  const d = await r.json();
  return Number(d?.[0]?.balance || 0);
}

async function collectToken() {
  const html = await (await fetch(`${SITE}/song/london/?ct=${Date.now()}`)).text();
  const m = html.match(/"collectToken":"([0-9a-f]+)"/);
  if (!m) throw new Error('Could not read the collect token from the song page.');
  return m[1];
}

async function api(pathname, body) {
  const r = await fetch(`${SITE}/wp-json/valt/v1/${pathname}`, { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(body) });
  const d = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(`${pathname}: HTTP ${r.status} ${d.message || ''}`);
  return d;
}

/** Witness set holding only this wallet's vkey witness, like CIP-30 signTx(tx, true). */
function walletWitness(txHex, signer) {
  const tx = Transaction.fromCbor(TxCBOR(txHex));
  const sig = signer.key.sign(HexBlob(tx.getId()));
  const ws = new TransactionWitnessSet();
  const w = new VkeyWitness(Ed25519PublicKeyHex(signer.publicKeyHex), Ed25519SignatureHex(sig.hex()));
  ws.setVkeys(CborSet.fromCore([w.toCore()], VkeyWitness.fromCore));
  return ws.toCbor();
}

async function collect(dir, i, song, qty) {
  const w = loadWallet(dir, i);
  const t0 = Date.now();
  const utxos = (await meshProvider().fetchAddressUTxOs(w.address)).map((u) => toTxUnspentOutput(u).toCbor());
  if (!utxos.length) throw new Error(`wallet${i} has no funds.`);
  const nonce = await collectToken();
  const b = await api('anvil/build', { song_id: song, qty, address: w.address, utxos, nonce });
  const witness = walletWitness(b.tx, w.signer);
  const s = await api('anvil/submit', { build_id: b.build_id, witness, nonce });
  let conf = false;
  for (let k = 0; k < 60 && !conf; k++) {
    await new Promise((r) => setTimeout(r, 5000));
    const st = await (await fetch(`${SITE}/wp-json/valt/v1/anvil/status?tx=${s.tx_hash}`)).json().catch(() => ({}));
    conf = !!st.confirmed;
  }
  const secs = Math.round((Date.now() - t0) / 1000);
  console.log(`wallet${i} qty=${qty} editions=${s.editions} tx=${s.tx_hash} ${conf ? 'confirmed' : 'NOT confirmed yet'} in ${secs}s`);
  return { wallet: i, address: w.address, qty, tx: s.tx_hash, confirmed: conf, secs };
}

await cryptoReady();
const dir = opt('dir');
if (!dir || dir === true) throw new Error('--dir <keysdir> is required.');

if (cmd === 'wallets') {
  await createWallets(dir, Number(opt('n', 5)));
} else if (cmd === 'balances') {
  for (const i of walletFiles(dir)) { const w = loadWallet(dir, i); console.log(`wallet${i} ${w.address} ${(await lovelaceOf(w.address)) / 1e6} tADA`); }
} else if (cmd === 'collect') {
  await collect(dir, Number(opt('wallet')), Number(opt('song')), Number(opt('qty', 1)));
} else if (cmd === 'run') {
  const plan = String(opt('plan', '1,2,3,1,2')).split(',').map(Number);
  const song = Number(opt('song'));
  const ids = walletFiles(dir).slice(0, plan.length);
  const jobs = ids.map((i, k) => () => collect(dir, i, song, plan[k]).catch((e) => ({ wallet: i, qty: plan[k], error: e.message })));
  const res = opt('concurrent', false) ? await Promise.all(jobs.map((j) => j())) : await jobs.reduce((p, j) => p.then(async (a) => [...a, await j()]), Promise.resolve([]));
  const out = path.join(dir, `run-${new Date().toISOString().replace(/[:.]/g, '-')}.json`);
  fs.writeFileSync(out, JSON.stringify({ site: SITE, song, plan, results: res }, null, 2));
  console.log(JSON.stringify(res, null, 2));
  console.log('saved', out);
} else {
  console.log('usage: wallets | balances | collect | run (see header)');
}
