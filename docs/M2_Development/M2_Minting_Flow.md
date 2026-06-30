# Valt — NFT Minting Flow (Milestone 2)

_How a song becomes an on-chain NFT on Valt. Sourced directly from the plugin code
(`valt-platform/includes/nmkr.php`, `ajax-handlers.php`, `cron.php`). Cardano testnet
(preprod), minted via NMKR Studio under policy `bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a`._

## Summary

Valt mints **on the fly** — there is **no pre-minted inventory**. A song NFT is created and
minted only at the moment a fan collects it (or an artist lists it for sale). Each action calls
NMKR's `UploadNft` to register a fresh NFT in the project, then mints it. Nothing is drawn from a
pre-staged pool, so the project's "uploaded but unsold" entries are simply the residue of upload
attempts that did not complete a mint — they are disposable.

There are two paths, both of which begin with `UploadNft`:

- **Path A — On-the-fly promo mint** (free / mint-and-send to a wallet). Upload **and** mint happen
  back-to-back, server-side.
- **Path B — Payment-gateway sale** (priced in ADA). We upload + price the NFT and hand the buyer
  an NMKR pay link; NMKR mints and delivers when the buyer pays.

---

## Path A — On-the-fly mint (mint-and-send)

Trigger: the **Collect / mint** button on a song.

1. **Frontend** (`assets/js/valt-platform.js:364`) POSTs `action=valt_mint_song_nft` with the
   `song_id` and recipient `wallet_address`.
2. **AJAX handler** (`includes/ajax-handlers.php:43`) verifies nonce + authorization, then calls
   `valt_schedule_nft_mint()`.
3. **Queue + schedule** (`nmkr.php:252` `valt_schedule_nft_mint`): validates the wallet, enforces
   `valt_nft_max_supply` vs `valt_mint_count`, marks status `pending`, adds the song to the
   `valt_nft_queue` option, and schedules the async worker `valt_mint_nft_async` (+5 s).
4. **Worker** (`nmkr.php:303` `valt_do_mint_nft`, wired in `cron.php:25`):
   1. Resolve cover art via fallback chain — song NFT image → song thumbnail → album thumbnail →
      artist thumbnail.
   2. Upload the cover to IPFS via Pinata (`valt_upload_to_ipfs`) and, optionally, the audio file.
   3. Build **CIP-25** metadata (`valt_build_cip25_metadata`) under the `721` label: name, image
      (`ipfs://…`), artist, album, genre, duration, track, platform = "Valt", and the audio file
      reference. The on-chain asset name is `valt` + the generated asset name (NMKR prepends the
      project slug).
   4. **`POST UploadNft/{projectUid}`** — registers the NFT in the NMKR project; NMKR returns an
      `nftUid`. Cover art is also attached as base64 so NMKR pins it on its own IPFS.
   5. **`GET MintAndSendSpecific/{projectUid}/{nftUid}/1/{wallet}`** — mints one copy and sends it
      directly to the fan's wallet. Status → `processing`.
5. **Status polling** (`nmkr.php:438` `valt_do_check_nft_status`, recurring every 5 min via
   `valt_do_poll_processing_nfts`): polls `GetNftDetailsById/{nftUid}` up to 20 times. On
   `sold|minted|finished` → status `minted`, store `txHash` + `assetId`, increment
   `valt_mint_count`, fire the `valt_nft_minted` action, and drop the song from the queue.

## Path B — Payment-gateway sale (priced)

Trigger: an artist/admin **lists a song for sale** (artist dashboard or NFT Monitor admin).

1. `valt_upload_song_to_nmkr()` (`nmkr.php:154`, called from `ajax-handlers.php:33` and
   `admin-nft-monitor.php:57`) builds the same CIP-25 metadata and **`POST UploadNft`** with
   `priceInLovelace` (default 5 ADA).
2. It stores `valt_nft_uid`, sets release status to "In NFT Collection", and constructs an NMKR
   pay URL: `https://pay.preprod.nmkr.io/?p={project}&n={nft}` (mainnet uses `pay.nmkr.io`).
3. The buyer pays ADA on NMKR's hosted page; **NMKR mints and delivers** the NFT automatically —
   no `MintAndSend` call from our side. The same status-polling path records the result.

---

## Gating (how the NFT unlocks content)

Once a fan holds a song NFT, `includes/gating.php` checks wallet holdings against the policy +
asset; The Valt (restricted artist page) renders its gated content only for holders. This is the
Milestone-2 acceptance criterion "users access a restricted artist page only if they hold the
artist's song NFT."

## NFT state in the NMKR project (why cleanup is safe)

Because minting is on the fly, **every collect/list action adds one `UploadNft` entry** to the
project. Entries that complete a mint become **sold/minted** (on-chain, permanent — these are the
Milestone-2 evidence). Entries whose mint never completed (test runs, abandoned collects, errors)
stay in the **free/unsold** state and accumulate.

- On the preprod project today: **~636 uploaded, 8 sold/minted.**
- The 8 sold are on-chain under policy `bf5a88ac…` and **cannot** be deleted (only burned).
- The ~628 unsold are local NMKR upload artifacts, **not** required inventory — the platform
  re-creates a fresh upload on each future collect. They are safe to delete.

**Cleanup endpoints (NMKR API):**
- `GET /v2/DeleteNft/{nftuid}` — delete a single NFT; refuses sold/reserved.
- `GET /v2/DeleteAllNftsFromProject/{projectuid}` — delete all removable NFTs; **automatically
  skips sold/reserved**, so the 8 minted NFTs are preserved.
