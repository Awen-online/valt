// Read-only chain access (Koios preprod by default, Blockfrost when BLOCKFROST_PROJECT_ID is set)
// and the Mesh provider used for UTxOs / protocol parameters / submission.
import { KoiosProvider, BlockfrostProvider } from '@meshsdk/core';

export const KOIOS = process.env.KOIOS_URL || 'https://preprod.koios.rest/api/v1';
const BF_ID = process.env.BLOCKFROST_PROJECT_ID || '';
const BF_URL = 'https://cardano-preprod.blockfrost.io/api/v0';

export const providerName = () => (BF_ID ? 'blockfrost' : 'koios');

export function meshProvider() {
  if (BF_ID) {
    if (!BF_ID.startsWith('preprod')) throw new Error('BLOCKFROST_PROJECT_ID must be a preprod project id.');
    return new BlockfrostProvider(BF_ID);
  }
  return process.env.KOIOS_TOKEN ? new KoiosProvider('preprod', process.env.KOIOS_TOKEN) : new KoiosProvider('preprod');
}

async function koios(pathname, body) {
  const headers = { accept: 'application/json' };
  if (process.env.KOIOS_TOKEN) headers.authorization = `Bearer ${process.env.KOIOS_TOKEN}`;
  const init = body === undefined ? { headers } : { method: 'POST', headers: { ...headers, 'content-type': 'application/json' }, body: JSON.stringify(body) };
  const res = await fetch(`${KOIOS}/${pathname}`, init);
  const text = await res.text();
  if (!res.ok) throw new Error(`Koios ${pathname} HTTP ${res.status}: ${text.slice(0, 300)}`);
  return JSON.parse(text);
}

async function blockfrost(pathname) {
  const res = await fetch(`${BF_URL}/${pathname}`, { headers: { project_id: BF_ID } });
  if (res.status === 404) return null;
  const text = await res.text();
  if (!res.ok) throw new Error(`Blockfrost ${pathname} HTTP ${res.status}: ${text.slice(0, 300)}`);
  return JSON.parse(text);
}

export const toHex = (s) => Buffer.from(s, 'utf8').toString('hex');
export const fromHex = (h) => Buffer.from(h, 'hex').toString('utf8');

export async function getTip() {
  if (BF_ID) {
    const b = await blockfrost('blocks/latest');
    return { slot: b.slot, block: b.height, time: b.time };
  }
  const [t] = await koios('tip');
  return { slot: t.abs_slot, block: t.block_no, time: t.block_time, epoch: t.epoch_no };
}

/**
 * Has this asset EVER been minted under the policy? A total supply of 0 (minted then burned)
 * still counts as taken: never reuse a name.
 */
export async function assetStatus(policyId, assetName) {
  const hex = toHex(assetName);
  if (BF_ID) {
    const a = await blockfrost(`assets/${policyId}${hex}`);
    return a ? { exists: true, totalSupply: a.quantity, fingerprint: a.fingerprint, mintTx: a.initial_mint_tx_hash } : { exists: false };
  }
  const rows = await koios('asset_info', { _asset_list: [[policyId, hex]] });
  if (!rows.length) return { exists: false };
  const a = rows[0];
  return { exists: true, totalSupply: a.total_supply, fingerprint: a.fingerprint, mintTx: a.minting_tx_hash, mintCount: a.mint_cnt, burnCount: a.burn_cnt };
}

/** All asset names ever minted under a policy (ascii where printable). */
export async function policyAssets(policyId) {
  const out = [];
  if (BF_ID) {
    for (let page = 1; ; page++) {
      const rows = await blockfrost(`assets/policy/${policyId}?page=${page}`);
      if (!rows || !rows.length) break;
      for (const r of rows) out.push({ hex: r.asset.slice(56), name: fromHex(r.asset.slice(56)), totalSupply: r.quantity });
      if (rows.length < 100) break;
    }
    return out;
  }
  for (let offset = 0; ; offset += 1000) {
    const rows = await koios(`policy_asset_list?_asset_policy=${policyId}&offset=${offset}&limit=1000`);
    for (const r of rows) out.push({ hex: r.asset_name, name: fromHex(r.asset_name || ''), totalSupply: r.total_supply, fingerprint: r.fingerprint });
    if (rows.length < 1000) break;
  }
  return out;
}

export async function txStatus(txHash) {
  if (BF_ID) {
    const t = await blockfrost(`txs/${txHash}`);
    if (!t) return { found: false };
    return { found: true, block: t.block_height, slot: t.slot, fee: t.fees };
  }
  const [s] = await koios('tx_status', { _tx_hashes: [txHash] });
  if (!s || s.num_confirmations == null) return { found: false };
  const [info] = await koios('tx_info', { _tx_hashes: [txHash], _assets: true, _metadata: true, _inputs: false, _scripts: false, _bytecode: false, _withdrawals: false, _certs: false });
  return {
    found: true,
    confirmations: s.num_confirmations,
    block: info?.block_height,
    time: info?.tx_timestamp ? new Date(info.tx_timestamp * 1000).toISOString() : undefined,
    fee: info?.fee,
    minted: (info?.assets_minted || []).map((a) => ({ policy: a.policy_id, name: fromHex(a.asset_name || ''), quantity: a.quantity })),
    outputs: (info?.outputs || []).map((o) => ({ address: o.payment_addr?.bech32, lovelace: o.value, assets: (o.asset_list || []).map((a) => `${a.policy_id}.${fromHex(a.asset_name || '')} x${a.quantity}`) })),
    metadataLabels: info?.metadata ? Object.keys(info.metadata) : [],
  };
}
