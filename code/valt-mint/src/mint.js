// Build (and optionally submit) a one-edition mint transaction.
//
// The tx: inputs from the fee wallet, mints 1 token under the policy (native script witness),
// sends it plus min ADA to --to, returns change to the fee wallet, attaches CIP-25 metadata
// (label 721), and is signed by the policy key and the fee key.
import fs from 'node:fs';
import path from 'node:path';
import {
  MeshTxBuilder, ForgeScript, DEFAULT_PROTOCOL_PARAMETERS, resolveSlotNo,
} from '@meshsdk/core';
import {
  Transaction, TxCBOR, VkeyWitness, CborSet, HexBlob, Address, resolveTxHash,
  Ed25519PublicKeyHex, Ed25519SignatureHex,
} from '@meshsdk/core-cst';
import { loadKeys, NMKR_PREPROD_POLICY } from './keys.js';
import { buildCip25, validateCip25, validateSong } from './metadata.js';
import { meshProvider, assetStatus, getTip, toHex, providerName } from './chain.js';
import { assertNotWebRoot } from './guard.js';

export const FAKE_TX_HASH = 'fa4efa4efa4efa4efa4efa4efa4efa4efa4efa4efa4efa4efa4efa4efa4efa4e';

export function readSong(file) {
  const song = JSON.parse(fs.readFileSync(file, 'utf8').replace(/^﻿/, ''));
  const errs = validateSong(song);
  if (errs.length) throw new Error('Song JSON invalid:\n  - ' + errs.join('\n  - '));
  return song;
}

export function assertPreprodAddress(addr) {
  let a;
  try { a = Address.fromBech32(addr); } catch { throw new Error(`--to is not a valid bech32 address: ${addr}`); }
  if (a.getNetworkId() !== 0 || !addr.startsWith('addr_test1')) throw new Error('--to must be a testnet (preprod) address (addr_test1...).');
  return addr;
}

/** Sign a tx body hash with each signer (normal or extended keys) and merge the vkey witnesses. */
export function signTx(txHex, signers) {
  const tx = Transaction.fromCbor(TxCBOR(txHex));
  const txId = tx.getId();
  const ws = tx.witnessSet();
  const existing = ws.vkeys() ? [...ws.vkeys().values()] : [];
  const have = new Set(existing.map((w) => w.vkey()));
  for (const s of signers) {
    if (have.has(s.publicKeyHex)) continue;
    const sig = s.key.sign(HexBlob(txId));
    existing.push(new VkeyWitness(Ed25519PublicKeyHex(s.publicKeyHex), Ed25519SignatureHex(sig.hex())));
    have.add(s.publicKeyHex);
  }
  ws.setVkeys(CborSet.fromCore(existing.map((w) => w.toCore()), VkeyWitness.fromCore));
  tx.setWitnessSet(ws);
  return tx.toCbor();
}

/** Human-readable summary of a (signed) tx. */
export function summarize(txHex, params) {
  const tx = Transaction.fromCbor(TxCBOR(txHex));
  const body = tx.body();
  const aux = tx.auxiliaryData();
  const auxCbor = aux ? aux.toCbor() : '';
  const size = txHex.length / 2;
  const minFee = BigInt(params.minFeeA) * BigInt(size) + BigInt(params.minFeeB);
  const mint = [];
  const m = body.mint();
  if (m) for (const [assetId, qty] of m.entries()) mint.push({ policy: assetId.slice(0, 56), name: Buffer.from(assetId.slice(56), 'hex').toString('utf8'), quantity: qty.toString() });
  return {
    txHash: tx.getId(),
    sizeBytes: size,
    fee: body.fee().toString(),
    minFeeForThisSize: minFee.toString(),
    feeCoversSignedSize: body.fee() >= minFee,
    ttlSlot: body.ttl() != null ? Number(body.ttl()) : null,
    inputs: body.inputs().values().map((i) => `${i.transactionId()}#${i.index()}`),
    outputs: body.outputs().map((o) => {
      const v = o.amount();
      const assets = [];
      const ma = v.multiasset();
      if (ma) for (const [id, q] of ma.entries()) assets.push(`${id.slice(0, 56)}.${Buffer.from(id.slice(56), 'hex').toString('utf8')} x${q}`);
      return { address: o.address().toBech32(), lovelace: v.coin().toString(), assets };
    }),
    mint,
    metadataBytes: auxCbor.length / 2,
    vkeyWitnesses: tx.witnessSet().vkeys() ? tx.witnessSet().vkeys().size() : 0,
    nativeScripts: tx.witnessSet().nativeScripts() ? tx.witnessSet().nativeScripts().size() : 0,
  };
}

function ledgerFile(dir) { return path.join(dir, 'mint-ledger.jsonl'); }
function ledger(dir) {
  const f = ledgerFile(dir);
  if (!fs.existsSync(f)) return [];
  return fs.readFileSync(f, 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l));
}
function appendLedger(dir, row) { fs.appendFileSync(ledgerFile(dir), JSON.stringify(row) + '\n', { mode: 0o600 }); }

/**
 * @param {object} o
 *  keys, song, edition, to, submit(bool), fakeUtxo(bool), offline(bool), nmkrCleared(asset name),
 *  suffix, lovelace, ttlSeconds, outTx, log(fn)
 */
export async function mint(o) {
  const log = o.log || console.log;
  const keys = loadKeys(o.keys);
  const song = typeof o.song === 'string' ? readSong(o.song) : o.song;
  const to = assertPreprodAddress(o.to);
  if (o.submit && (o.fakeUtxo || o.offline)) throw new Error('--submit cannot be combined with --fake-utxo/--offline.');

  const payload = buildCip25(song, o.edition, keys.policyId, { suffix: o.suffix || 'e' });
  const { errors, warnings } = validateCip25(payload, song);
  if (errors.length) throw new Error('Metadata invalid:\n  - ' + errors.join('\n  - '));
  warnings.forEach((w) => log(`  warning: ${w}`));
  if (Array.isArray(song.artist_policy_ids) && !song.artist_policy_ids.includes(keys.policyId)) {
    log(`  warning: the artist's valt_policy_id (${song.artist_policy_ids.join(', ') || 'empty'}) does not include ${keys.policyId}; the Valt will NOT unlock for this token until it is added.`);
  }
  const assetName = payload.assetName;
  const assetHex = toHex(assetName);
  const unit = keys.policyId + assetHex;

  // NMKR still owns the minting schedule for names it has uploaded under its own policy.
  if (keys.policyId === NMKR_PREPROD_POLICY && o.nmkrCleared !== assetName) {
    throw new Error(
      `${assetName} is under the NMKR policy. NMKR may still sell or mint it (it is uploaded to the NMKR project). ` +
      `First delete it or confirm it is not free/reserved/sold in NMKR Studio, then re-run with --nmkr-cleared ${assetName}.`
    );
  }

  // Never mint a name already minted (even if later burned), nor one this tool already submitted.
  const prior = ledger(keys.dir).find((r) => r.assetName === assetName && r.policyId === keys.policyId && r.submitted);
  if (prior) throw new Error(`${assetName} was already submitted by this tool (tx ${prior.txHash}).`);
  let onChain = { exists: false, skipped: true };
  if (!o.offline) {
    onChain = await assetStatus(keys.policyId, assetName);
    if (onChain.exists) throw new Error(`${assetName} already exists on-chain under ${keys.policyId} (supply ${onChain.totalSupply}, fingerprint ${onChain.fingerprint}). Pick another edition.`);
  }
  log(`  on-chain name check (${o.offline ? 'skipped: --offline' : providerName()}): ${assetName} ${onChain.exists ? 'EXISTS' : o.offline ? 'not checked' : 'is free'}`);

  // Chain context.
  const provider = o.offline ? null : meshProvider();
  const params = o.offline ? { ...DEFAULT_PROTOCOL_PARAMETERS } : await provider.fetchProtocolParameters();
  const tipSlot = o.offline ? Number(resolveSlotNo('preprod', Date.now())) : (await getTip()).slot;
  let ttl = tipSlot + (o.ttlSeconds || 7200);
  if (keys.requirements.beforeSlot != null) {
    if (tipSlot >= keys.requirements.beforeSlot) throw new Error(`Policy is time-locked before slot ${keys.requirements.beforeSlot}; current slot ${tipSlot} is past it. This policy can no longer mint.`);
    ttl = Math.min(ttl, keys.requirements.beforeSlot - 1);
  }

  let utxos;
  if (o.fakeUtxo) {
    utxos = [{ input: { txHash: FAKE_TX_HASH, outputIndex: 0 }, output: { address: keys.feeAddress, amount: [{ unit: 'lovelace', quantity: String(o.fakeLovelace || 10_000_000) }] } }];
    log('  using a FAKE fee-wallet UTxO (test only: the result can never be submitted)');
  } else {
    utxos = await provider.fetchAddressUTxOs(keys.feeAddress);
    const total = utxos.reduce((s, u) => s + BigInt(u.output.amount.find((a) => a.unit === 'lovelace')?.quantity || 0), 0n);
    log(`  fee wallet ${keys.feeAddress}: ${utxos.length} UTxO(s), ${Number(total) / 1e6} tADA`);
    if (!utxos.length) throw new Error(`Fee wallet has no UTxOs. Fund ${keys.feeAddress} from the preprod faucet (https://docs.cardano.org/cardano-testnets/tools/faucet) and retry.`);
  }

  // No fetcher: every input is supplied via selectUtxosFrom, and a native-script mint needs no cost models.
  const txb = new MeshTxBuilder({ params, verbose: false });
  txb.setNetwork('preprod');
  const outAmount = [{ unit, quantity: '1' }];
  if (o.lovelace) outAmount.unshift({ unit: 'lovelace', quantity: String(o.lovelace) });
  txb
    .mint('1', keys.policyId, assetHex)
    .mintingScript(ForgeScript.fromNativeScript(keys.meshScript))
    .metadataValue(721, payload.metadata)
    .txOut(to, outAmount)
    .changeAddress(keys.feeAddress)
    .selectUtxosFrom(utxos)
    .invalidHereafter(ttl);
  const unsigned = await txb.complete();
  const signed = signTx(unsigned, [keys.policySigner, keys.feeSigner]);
  const summary = summarize(signed, params);
  if (!summary.feeCoversSignedSize) throw new Error(`Fee ${summary.fee} is below the minimum ${summary.minFeeForThisSize} for the signed size; refusing.`);
  if (resolveTxHash(signed) !== summary.txHash) throw new Error('tx hash mismatch after signing');

  const outDir = path.join(keys.dir, 'tx');
  fs.mkdirSync(outDir, { recursive: true });
  const outTx = o.outTx || path.join(outDir, `${assetName}${o.fakeUtxo ? '.FAKE' : ''}.signed.cbor`);
  assertNotWebRoot(outTx);
  fs.writeFileSync(outTx, signed + '\n', { mode: 0o600 });
  // cardano-cli compatible envelope too, so it can be submitted with `cardano-cli conway transaction submit`.
  fs.writeFileSync(outTx.replace(/\.cbor$/, '') + '.json', JSON.stringify({ type: 'Tx ConwayEra', description: `Valt mint ${assetName}`, cborHex: signed }, null, 4) + '\n', { mode: 0o600 });

  const result = { assetName, unit, policyId: keys.policyId, to, summary, signedTxFile: outTx, metadata: payload.metadata, submitted: false };

  if (o.submit) {
    // Re-check just before submitting.
    const again = await assetStatus(keys.policyId, assetName);
    if (again.exists) throw new Error(`${assetName} appeared on-chain while building; not submitting.`);
    const hash = await provider.submitTx(signed);
    result.submitted = true;
    result.submittedHash = hash;
    appendLedger(keys.dir, { at: new Date().toISOString(), policyId: keys.policyId, assetName, to, txHash: summary.txHash, submitted: true });
  } else {
    appendLedger(keys.dir, { at: new Date().toISOString(), policyId: keys.policyId, assetName, to, txHash: summary.txHash, submitted: false, fake: !!o.fakeUtxo });
  }
  return result;
}
