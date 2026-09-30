# valt-mint

A small Node tool (Mesh SDK) for minting Valt song NFTs directly on the **Cardano pre-production
testnet**, without a hosted minting service. It was built during the M4 rollout as a fallback when the
minting partner's testnet processing stalled (bug log O-04), and it also holds `bin/collect-test.js`,
the script behind the disclosed multi-wallet checkout test (bug log, "Checkout test").

- **Preprod only.** Addresses must be `addr_test1...`; the tool refuses anything else.
- **Dry run by default.** `mint` builds and signs a transaction and writes it to a file. Nothing is
  broadcast unless you add `--submit`.
- **Keys never live in the repo.** `src/guard.js` refuses any keys folder inside a git work tree or
  under a folder holding `wp-config.php`, and the tool never prints secret keys.

## Setup

```bash
cd code/valt-mint
npm install
npm test                 # offline tests; VALT_MINT_ONLINE=1 also runs the on-chain collision check
node bin/valt-mint.js help
```

Chain data comes from the public Koios preprod API by default; set `BLOCKFROST_PROJECT_ID` (a preprod
project) to use Blockfrost instead.

## Commands

```
keygen --out <keys-dir> [--before-slot <slot>]
    New self-owned single-signature policy plus a fee wallet, written to <keys-dir>.
import-nmkr --zip <export.zip|dir> --out <keys-dir> [--expect-policy <id>] [--allow-mismatch]
    Convert a minting-service "Export Policy Keys" bundle to this tool's format and verify the policy id.
metadata --song <song.json> [--edition <n>] [--policy <id>] [--suffix e]
    Build and validate the CIP-25 metadata for one edition.
mint --keys <keys-dir> --song <song.json> --edition <n> --to <addr_test1...> [--dry-run | --submit]
     [--nmkr-cleared <assetName>] [--suffix e] [--out-tx <file>]
    Build and sign one mint transaction (policy key + fee key).
check --policy <id> [--asset <name>]   Is a name minted under the policy? (no --asset: list all names)
status --tx <hash>                     Confirmation status of a transaction.
tip                                    Current preprod slot.
```

`<keys-dir>` is any folder **outside** the repository and the web root, for example
`<home>/valt-keys/<name>`. It holds the policy key and script, the fee wallet, a non-secret
`keys.json` summary, a `mint-ledger.jsonl` of what was built or submitted, and `tx/`.

## Minting one edition

1. **Song file.** Copy `examples/london-300.example.json` and fill in the song. `artist` must match the
   artist's post title exactly (the Valt gate matches on policy plus artist), and `image_cid` must be the
   real cover CID.
2. **Check the metadata:** `node bin/valt-mint.js metadata --song <song.json> --edition 1`.
3. **Fund the fee wallet** (its address is in `fee.addr`) with test ADA from the Cardano testnet faucet.
   Each mint costs about 0.2 tADA in fees plus about 1.2 tADA that travels with the token.
4. **Dry run** with `mint`, and read the summary (one mint of quantity 1, token to the recipient, change
   to the fee wallet, fee covers the signed size, two signatures).
5. **Submit** by repeating the command with `--submit`. The name is re-checked on-chain first and
   recorded in `mint-ledger.jsonl`, so the same name cannot be submitted twice from that folder.
6. **Verify** with `status --tx <hash>` and `check --policy <id> --asset <name>`.

### Avoiding duplicate names

When a policy is shared with a minting service, that service can still mint names it has uploaded. If
both mint the same name, the asset's supply becomes 2 and it is no longer a unique NFT. So:

- The tool never mints a name that already exists on-chain under the policy (burned names count as
  taken), checking before building and again before submitting.
- Under a shared policy it also refuses any name unless you repeat it in `--nmkr-cleared <name>`,
  confirming the service can no longer sell or mint it.
- Safest: use a suffix the service never used (`--suffix s` gives `...s01`). Gating does not depend on
  the token name.

## Multi-wallet checkout test (`bin/collect-test.js`)

Drives the live site's in-page collect flow headlessly with **test wallets you create and fund**, using
the same REST calls as the Collect button (build, wallet signature, submit, on-chain confirmation).
Use it only with disclosed test wallets: its collects are test transactions, never collectors or sales.

```bash
node bin/collect-test.js wallets  --dir <keys-dir> --n 5
node bin/collect-test.js balances --dir <keys-dir>
node bin/collect-test.js collect  --dir <keys-dir> --wallet <i> --song <id> --qty <n> [--site <url>]
node bin/collect-test.js run      --dir <keys-dir> --song <id> --plan 1,2,3,1,2 [--concurrent]
```

`run --concurrent` fires the collects at the same moment to exercise the edition ledger. Results are
saved as `run-<timestamp>.json` in the keys folder.

## Toward a buyer-signed checkout

The core of this tool (reserve a name, build from the buyer's UTxOs, add only the policy signature,
let the buyer's wallet sign, confirm on-chain) is what the plugin's in-page checkout now does through
the Anvil API. See `code/valt-platform/includes/anvil.php` and the plugin README.
