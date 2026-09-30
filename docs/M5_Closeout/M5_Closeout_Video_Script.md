# Valt Project Close-out Video (PCV)

Project Catalyst Fund 11, project 1100019. Written to the Catalyst PCV rules
(https://docs.projectcatalyst.io/previous-funds/fund14-docs/general-information/project-close-out-report-and-project-close-out-video-pcr-and-pcv, applies from Fund 10).

## Rules checklist (all must be true before you submit)
- [ ] Length **2 to 5 minutes**. Treat 5:00 as a hard ceiling; target **about 4:30**.
- [ ] **720p or 1080p**, English audio commentary.
- [ ] **Public** YouTube or Vimeo link. Not unlisted, not a Drive link. Check it signed out (in a private window the video plays, and YouTube does not show "Unlisted").
- [ ] Covers the **four elements in this order**: (1) why this challenge and the funded approach; (2) progress, learnings, milestones/KPIs and results, gaps named; (3) demonstration of the outputs on the live site; (4) commercialisation plans and future funding intentions.
- [ ] Say aloud (Part 1 does this): **the project name, project 1100019, Project Catalyst Fund 11, Cardano Use Cases: Concept.**
- [ ] Every figure on screen equals the Project Close-out Report (`M5_Project_Closeout_Report.pdf`).
- [ ] Say "testnet preview", never "live on mainnet". No Afrocharts beyond the historical project name.

**Estimated runtime about 4 min 10 s** (about 510 spoken words). One take is fine; cut between parts if needed, and trim to stay under 5:00.

## Before you record
- Browser at 1280 px, not signed in as admin, notifications off. **No admin screens.** The fresh testnet wallet is installed, on Preprod, funded, and not yet connected to the site. If you have other wallet extensions (e.g. the Awen wallet), disconnect them from valt.digital first, or disable them for the recording, so Connect can't pick the wrong one.
- Browser tabs to open, in this order (signed out of wp-admin; a normal visitor view):

| Tab | Page | URL | Used in |
|---|---|---|---|
| 1 | Homepage | https://www.valt.digital/ | Shot 1, 3a, 4 |
| 2 | London by Hazzy Jo (collect page) | https://www.valt.digital/song/london/ | Shot 3b |
| 3 | How to collect | https://www.valt.digital/how-to-collect/ | Shot 3d |
| 4 | Hazzy Jo's Valt (opens after collecting) | https://www.valt.digital/artist/hazzy-jo/ | Shot 3c |
| 5 | Mie's page | https://www.valt.digital/artist/mie/ | Shot 4 |
| 6 | Public GitHub repo | https://github.com/Awen-online/valt | Shot 3e |
| 7 | Final Report PDF (section 3, the numbers) | https://github.com/Awen-online/valt/blob/main/docs/M5_Closeout/M5_Final_Report.pdf | Shot 2 |
| 8 | M4 Rollout Bug Log PDF | https://github.com/Awen-online/valt/blob/main/docs/M4_LaunchRollout/M4_Rollout_Bug_Log.pdf | Shot 3e |
| 9 | CardanoPress v1.36.1 release (credit) | https://github.com/CardanoPress/cardanopress/releases/tag/v1.36.1 | Shot 2 (optional) |

  Open them all at once in Brave (PowerShell):
  `$b="C:\Program Files\BraveSoftware\Brave-Browser\Application\brave.exe"; & $b --new-window https://www.valt.digital/ https://www.valt.digital/song/london/ https://www.valt.digital/how-to-collect/ https://www.valt.digital/artist/hazzy-jo/ https://www.valt.digital/artist/mie/ https://github.com/Awen-online/valt https://github.com/Awen-online/valt/blob/main/docs/M5_Closeout/M5_Final_Report.pdf https://github.com/Awen-online/valt/blob/main/docs/M4_LaunchRollout/M4_Rollout_Bug_Log.pdf https://github.com/CardanoPress/cardanopress/releases/tag/v1.36.1`
- **Collect on camera with a fresh testnet wallet only** (funded with about 25 tADA, set to Preprod, installed in this browser). Never use admin, and never a mainnet wallet. Cut the confirmation wait in the edit.
- Intro card (first 3 s): *Valt · Project Catalyst Fund 11 · Project 1100019 · Awen LLC*.

## Script

### Part 1: the challenge (0:00 to 0:35)
**Shot 1.** Intro card, then tab 1, the homepage. Scroll once, slowly.
> Hi, I'm Ian McCullough from Awen. This is the close-out video for the Afrocharts Web3 Artist Portal, which we built as Valt: project 1100019, funded in Project Catalyst Fund 11 under Cardano Use Cases: Concept.
>
> Here's the problem. Independent artists earn fractions of a cent per stream, and the fans who love their music have no simple way to support them directly.
>
> Valt is our answer: a Web3-native, token-gated music platform on Cardano. A fan connects a Cardano wallet and collects a limited edition of a song as an NFT. Owning it unlocks that artist's private Valt, with exclusive videos and content, and because ownership lives on-chain, it's verifiable and truly the fan's.

### Part 2: what we delivered and learned (0:35 to 1:15)
**Shot 2.** Tab 7, Final Report section 3 (the numbers grid). Hold.
> So, where did we land? All five milestones are delivered, with the evidence on GitHub. Valt launched on August 7th as a testnet preview: three artists, twelve songs, three holder-only videos.
>
> The early numbers are small but real: 78 visitors and a Net Promoter Score of plus 60. Our biggest lesson: artists are the distribution. One artist's post reached more people than all of ours combined.
>
> We also found and helped fix a critical wallet-login flaw in CardanoPress, used across Cardano.
>
> So far every collect has been our own testing, so the next step is real collectors, and, as Mie put it, without asking fans to become crypto users first.

On screen: *78 visitors · 6 wallet accounts · NPS +60 (n = 5) · 1,432 reach · 0 third-party purchases*

On screen: *Critical CardanoPress flaw found, fixed upstream v1.36.1, credited*

On screen (quote card): *"The most interesting part to me is the potential to make digital music feel valuable and ownable again without asking fans to become crypto users first." Mie, artist on Valt*

### Part 3: the demo (1:15 to 3:15)
**Shot 3a.** Tab 1, homepage. Press play on a song; the player stays at the bottom as you move.
> Let me show you. Every song streams in full, for free, and the player stays with you as you browse.

**Shot 3b.** Tab 2, London by Hazzy Jo. Connect the right wallet first, then collect ONE edition (never signed in as admin).
1. Press **Connect** (top right) and choose the **fresh testnet wallet** by name. Approve the connection in the wallet.
2. Check it's the right one before going on: the wallet shows **Preprod** and the fresh wallet's address (starts `addr_test1`), and the site shows you connected. If it picked another wallet, disconnect and choose again.
3. Scroll to Collect. Leave the picker at 1. Press **Collect**. The wallet pops up one approval; pause on it so the viewer sees the price and the edition.
4. Sign. The panel shows *Signed, Sent, Confirming on-chain*. **Cut the wait in the edit** (50 to 100 s).
5. When it reads *Collected* with **Open the Valt**, click **View transaction** briefly, then **Open the Valt**.
> This is London by Hazzy Jo, our featured release. First, I connect my Cardano wallet, a fresh testnet wallet. Then I pick one edition, and my wallet asks me to approve a single transaction: payment and minting happen together. Valt confirms it on-chain, and the edition lands straight in my wallet. You can check the transaction on Cardano.

On screen: *Collect 1 to 5 editions in one transaction · testnet preview*

**Shot 3c.** Hazzy Jo's Valt, opened from the Collected panel. The same wallet is already connected; if the Valt still looks locked, press **Sync Wallet** (dashboard) and refresh. Hold on the unlocked Valt and start the holder-only video for a few seconds.
> And now the Valt opens. The server checks my wallet before it shows anything exclusive, so this official London music video is only for people who own the song.

**Shot 3d.** Tab 3, How to collect. Scroll the steps.
> New collectors get a step-by-step guide, and it works on a phone too, inside a wallet app's browser.

**Shot 3e.** Tab 6, GitHub repo, then tab 8, the M4 Rollout Bug Log PDF.
> Under the hood, everything is open source, with 19 automated tests on every push. We documented all 38 fixes from the rollout and mapped each one to the public code. And when our minting partner's testnet stalled, we moved checkout from NMKR Pay to our own in-page checkout built on Anvil: one signature, no redirect.

On screen: *Open source · 19 tests · 38 fixes documented*

On screen: *From NMKR Pay to an in-page Anvil checkout · one signature pays and mints*

### Part 4: what comes next (3:15 to 4:10)
**Shot 4.** Tab 5, Mie's page, then back to the homepage. End card fades in for the last 3 s.
> So what's next? Valt continues past the grant as an Awen product. Through our sync.land platform, 82 artist profiles are already covered by signed terms that let them release on Valt. That's our pipeline.
>
> Next comes mainnet, starting with London: direct payouts to each artist, a card-style checkout so fans don't need to think about crypto, and an audited on-chain contract for edition limits, which we may bring back to Catalyst.
>
> And Valt is open source. The code is MIT-licensed on GitHub, for the community to use, fork and build on.
>
> Thank you to Cullah, Mie and Hazzy Jo, to the reviewers, and to the Cardano community.

On screen: *82 artist profiles under Valt-ready terms (sync.land)*

On screen: *Open source (MIT) · github.com/Awen-online/valt*

End card: *valt.digital · github.com/Awen-online/valt · Project Catalyst Fund 11, project 1100019*

## Shot list at a glance
| Shot | Part | Screen | Target |
|---|---|---|---|
| 1 | 1 | Intro card, homepage | 35 s |
| 2 | 2 | Final Report, results grid | 40 s |
| 3a | 3 | Homepage, play a song | 10 s |
| 3b | 3 | /song/london/, collect 1 edition (wait cut) | 40 s |
| 3c | 3 | Hazzy Jo's Valt, unlocked, video plays | 25 s |
| 3d | 3 | /how-to-collect/ | 10 s |
| 3e | 3 | GitHub repo, bug log | 40 s |
| 4 | 4 | Mie, homepage, end card | 55 s |
| | | **Total** | **about 4 min 10 s** (cut the confirmation wait) |

Pacing tip: about 510 spoken words. Speak at a relaxed pace (about 150 words a minute) and let the demo breathe; if you run long, trim the CardanoPress paragraph to one sentence and shorten the thank-yous.

## After recording
- Export 1080p (H.264). Upload to YouTube as **Public**. Title: "Valt: Project Catalyst Fund 11 close-out (project 1100019)".
- Optional polish: your own .srt captions; chapters in the description starting at 0:00 (0:00 The challenge, 0:35 What we delivered, 1:15 Demo, 3:15 What's next).
- Check it is public: open it signed out in a private window.
- Send the URL and runtime. They go into the PCR header, the Final Report (section 9) and the M5 letter.
