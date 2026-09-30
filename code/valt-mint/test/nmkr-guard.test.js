// Exercises the NMKR-policy guard in mint() by mocking loadKeys to report the NMKR policy id.
// Needs: node --test --experimental-test-module-mocks
import { test, mock } from 'node:test';
import assert from 'node:assert/strict';
import * as real from '../src/keys.js';

test('mint under the NMKR policy requires --nmkr-cleared for that exact asset', async () => {
  await real.cryptoReady();
  mock.module('../src/keys.js', {
    namedExports: {
      ...real,
      loadKeys: () => ({ dir: 'x', policyId: real.NMKR_PREPROD_POLICY, requirements: { beforeSlot: null }, meshScript: {}, feeAddress: 'addr_test1' }),
    },
  });
  const { mint } = await import('../src/mint.js');
  const song = { title: 'London', artist: 'Hazzy Jo', image_cid: 'QmP4ssQS9EN8bxpjiU6hnSyQFbpYYFBJfRwsW9s929FZzf', asset_base: 'london300' };
  const to = 'addr_test1qzeeyyv6nn33nl9wwm9u6l0k27edl6wq9nvvr07lvdkwlayvjnpagh3n3p22r3ntmstvewhqk45j7p6ppwaq5ecklxusv3u37p';
  await assert.rejects(mint({ keys: 'x', song, edition: 18, to, offline: true, fakeUtxo: true, log: () => {} }), /--nmkr-cleared valtlondon300e18/);
  await assert.rejects(mint({ keys: 'x', song, edition: 18, to, offline: true, fakeUtxo: true, nmkrCleared: 'valtlondon300e17', log: () => {} }), /--nmkr-cleared valtlondon300e18/);
});

test('online: names already minted under the NMKR policy (even burned ones) are refused', { skip: process.env.VALT_MINT_ONLINE !== '1' && 'set VALT_MINT_ONLINE=1 to query Koios' }, async () => {
  const { mint } = await import('../src/mint.js');
  const song = { title: 'Masochist', artist: 'X', image_cid: 'QmP4ssQS9EN8bxpjiU6hnSyQFbpYYFBJfRwsW9s929FZzf', asset_base: 'masochist263' };
  const to = 'addr_test1qzeeyyv6nn33nl9wwm9u6l0k27edl6wq9nvvr07lvdkwlayvjnpagh3n3p22r3ntmstvewhqk45j7p6ppwaq5ecklxusv3u37p';
  // e03: live supply 1. e01: minted then burned (supply 0).
  await assert.rejects(mint({ keys: 'x', song, edition: 3, to, fakeUtxo: true, nmkrCleared: 'valtmasochist263e03', log: () => {} }), /already exists on-chain/);
  await assert.rejects(mint({ keys: 'x', song, edition: 1, to, fakeUtxo: true, nmkrCleared: 'valtmasochist263e01', log: () => {} }), /already exists on-chain/);
});
