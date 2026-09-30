#!/usr/bin/env node
// valt-mint: fallback self-mint tool for Valt song NFTs on Cardano PREPROD.
// See docs/M4_LaunchRollout/self-mint/RUNBOOK.md. Never prints secret keys.
import { generate } from '../src/keys.js';
import { importNmkr } from '../src/nmkr-import.js';
import { buildCip25, validateCip25 } from '../src/metadata.js';
import { mint, readSong } from '../src/mint.js';
import { assetStatus, policyAssets, txStatus, getTip, providerName } from '../src/chain.js';
import { NMKR_PREPROD_POLICY, cryptoReady } from '../src/keys.js';

const HELP = `valt-mint (Cardano PREPROD only)

  keygen --out <dir> [--before-slot <slot>]
      New self-owned single-sig policy + fee wallet, written to <dir> (must be outside the repo / web root).
  import-nmkr --zip <nmkr-export.zip|dir> --out <dir> [--expect-policy <id>] [--allow-mismatch]
      Convert an NMKR "Export Policy Keys" zip to this tool's format (adds a fee wallet). Verifies the policy id
      (default expected: ${NMKR_PREPROD_POLICY}).
  metadata --song <song.json> [--edition <n>] [--policy <id>] [--suffix e]
      Build + validate the CIP-25 metadata for one edition and print it.
  mint --keys <dir> --song <song.json> --edition <n> --to <addr_test1...> [--dry-run | --submit]
       [--nmkr-cleared <assetName>] [--suffix e] [--lovelace <n>] [--fake-utxo] [--offline] [--out-tx <file>]
      Build + sign the mint tx. Dry run is the DEFAULT (writes signed CBOR, does not submit).
      --submit actually submits. --fake-utxo/--offline are for testing without funds or network.
  check --policy <id> [--asset <name>]      Is <name> minted under <id>? (without --asset: list all names)
  status --tx <hash>                          Confirmation status of a tx.
  tip                                         Current preprod slot.

Env: BLOCKFROST_PROJECT_ID (optional, preprod...), KOIOS_TOKEN (optional), KOIOS_URL.`;

function parseArgs(argv) {
  const out = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      const [k, v] = a.slice(2).split('=', 2);
      if (v !== undefined) out[k] = v;
      else if (i + 1 < argv.length && !argv[i + 1].startsWith('--')) out[k] = argv[++i];
      else out[k] = true;
    } else out._.push(a);
  }
  return out;
}

const need = (a, ...names) => {
  for (const n of names) if (a[n] === undefined || a[n] === true) throw new Error(`--${n} is required. Run "valt-mint help".`);
};
const print = (o) => console.log(JSON.stringify(o, null, 2));

async function main() {
  await cryptoReady();
  const a = parseArgs(process.argv.slice(2));
  const cmd = a._[0];
  switch (cmd) {
    case 'keygen': {
      need(a, 'out');
      const r = generate(a.out, { beforeSlot: a['before-slot'] ? Number(a['before-slot']) : null });
      console.log(`Keys written to ${r.dir}  (${r.perms})`);
      console.log(`policy id        ${r.policyId}`);
      console.log(`policy key hash  ${r.policyKeyHash}`);
      if (r.beforeSlot) console.log(`time lock        mint only before slot ${r.beforeSlot}`);
      console.log(`fee wallet       ${r.feeAddress}`);
      console.log('Secret keys were NOT printed. Back up the directory somewhere safe (outside the repo and web root).');
      break;
    }
    case 'import-nmkr': {
      need(a, 'zip', 'out');
      const r = importNmkr({ zip: a.zip, out: a.out, expectPolicy: a['expect-policy'] || NMKR_PREPROD_POLICY, allowMismatch: !!a['allow-mismatch'] });
      console.log(`Imported to ${r.dir}  (${r.perms})`);
      console.log(`derived policy id  ${r.policyId}  ${r.matchesExpected ? '(matches expected)' : '(DOES NOT match expected)'}`);
      console.log(`policy key hash    ${r.policyKeyHash}`);
      console.log(`time lock          ${r.beforeSlot ?? 'none'}`);
      console.log(`fee wallet         ${r.feeAddress}`);
      console.log(`read from          ${r.scriptEntry} + ${r.skeyEntry}`);
      break;
    }
    case 'metadata': {
      need(a, 'song');
      const song = readSong(a.song);
      const policy = a.policy || NMKR_PREPROD_POLICY;
      const p = buildCip25(song, a.edition ? Number(a.edition) : 1, policy, { suffix: a.suffix || 'e' });
      const v = validateCip25(p, song);
      print({ 721: p.metadata });
      console.error(`asset name: ${p.assetName} (${Buffer.byteLength(p.assetName)} bytes)`);
      v.warnings.forEach((w) => console.error(`warning: ${w}`));
      if (v.errors.length) { v.errors.forEach((e) => console.error(`ERROR: ${e}`)); process.exitCode = 2; } else console.error('metadata: valid (all strings <= 64 bytes)');
      break;
    }
    case 'mint': {
      need(a, 'keys', 'song', 'edition', 'to');
      const submit = a.submit === true;
      if (submit && a['dry-run']) throw new Error('Use either --dry-run or --submit, not both.');
      const r = await mint({
        keys: a.keys, song: a.song, edition: Number(a.edition), to: a.to, submit,
        fakeUtxo: !!a['fake-utxo'], offline: !!a.offline, nmkrCleared: a['nmkr-cleared'],
        suffix: a.suffix, lovelace: a.lovelace, outTx: a['out-tx'], log: (m) => console.error(m),
      });
      print({ mode: r.submitted ? 'SUBMITTED' : 'DRY RUN (not submitted)', assetName: r.assetName, policyId: r.policyId, to: r.to, ...r.summary, signedTxFile: r.signedTxFile, submittedHash: r.submittedHash });
      if (!r.submitted) console.error('Not submitted. Re-run with --submit to broadcast (fake-UTxO builds can never be submitted).');
      break;
    }
    case 'check': {
      need(a, 'policy');
      if (a.asset) print({ policy: a.policy, asset: a.asset, provider: providerName(), ...(await assetStatus(a.policy, a.asset)) });
      else {
        const list = await policyAssets(a.policy);
        print({ policy: a.policy, count: list.length, assets: list.map((x) => `${x.name} (supply ${x.totalSupply})`) });
      }
      break;
    }
    case 'status': {
      need(a, 'tx');
      print({ tx: a.tx, provider: providerName(), ...(await txStatus(a.tx)) });
      break;
    }
    case 'tip': print(await getTip()); break;
    case undefined: case 'help': case '--help': console.log(HELP); break;
    default: throw new Error(`Unknown command "${cmd}".\n\n${HELP}`);
  }
}

main().catch((e) => { console.error(`error: ${e.message}`); process.exit(1); });
