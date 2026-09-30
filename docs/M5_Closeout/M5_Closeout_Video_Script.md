# Valt Project Close-out Video (PCV)

Project Catalyst Fund 11, project 1100019. Written to the Catalyst PCV rules
(https://docs.projectcatalyst.io/previous-funds/fund14-docs/general-information/project-close-out-report-and-project-close-out-video-pcr-and-pcv, applies from Fund 10).

## Rules checklist (all must be true before you submit)
- [ ] Length **2 to 5 minutes**. Treat 5:00 as a hard ceiling; target **about 4:30**.
- [ ] **720p or 1080p**, English audio commentary.
- [ ] **Public** YouTube or Vimeo link. Not unlisted, not a Drive link. Check it signed out (in a private window the video plays, and YouTube does not show "Unlisted").
- [ ] Covers the **four elements in this order**: (1) why this challenge and the funded approach; (2) progress, learnings, milestones/KPIs and results, gaps named; (3) demonstration of the outputs on the live site; (4) commercialisation plans and future funding intentions.
- [ ] Say aloud: **"Valt, Project 1100019, funded in Project Catalyst Fund 11 under Cardano Use Cases: Concept."**
- [ ] Every figure on screen equals the Project Close-out Report (`M5_Project_Closeout_Report.pdf`).
- [ ] Say "testnet preview", never "live on mainnet". No Afrocharts beyond the historical project name.

**Estimated runtime 4 min 30 s** (about 470 spoken words). One take is fine; cut between parts if needed, and trim to stay under 5:00.

## Before you record
- Browser at 1280 px, signed out, no wallet connected, notifications off. **No admin screens.**
- Browser tabs to open, in this order (signed out of wp-admin; a normal visitor view):

| Tab | Page | URL | Used in |
|---|---|---|---|
| 1 | Homepage | https://www.valt.digital/ | Shot 1, 3a, 4 |
| 2 | London by Hazzy Jo (collect page) | https://www.valt.digital/song/london/ | Shot 3b |
| 3 | How to collect | https://www.valt.digital/how-to-collect/ | Shot 3d |
| 4 | Hazzy Jo's Valt (locked view) | https://www.valt.digital/artist/hazzy-jo/ | Shot 3c |
| 5 | Mie's page | https://www.valt.digital/artist/mie/ | Shot 4 |
| 6 | Public GitHub repo | https://github.com/Awen-online/valt | Shot 3e |
| 7 | Final Report PDF (section 3, the numbers) | https://github.com/Awen-online/valt/blob/main/docs/M5_Closeout/M5_Final_Report.pdf | Shot 2 |
| 8 | M4 Rollout Bug Log PDF | https://github.com/Awen-online/valt/blob/main/docs/M4_LaunchRollout/M4_Rollout_Bug_Log.pdf | Shot 3e |
| 9 | CardanoPress v1.36.1 release (credit) | https://github.com/CardanoPress/cardanopress/releases/tag/v1.36.1 | Shot 2 (optional) |

  Open them all at once in Brave (PowerShell):
  `$b="C:\Program Files\BraveSoftware\Brave-Browser\Application\brave.exe"; & $b --new-window https://www.valt.digital/ https://www.valt.digital/song/london/ https://www.valt.digital/how-to-collect/ https://www.valt.digital/artist/hazzy-jo/ https://www.valt.digital/artist/mie/ https://github.com/Awen-online/valt https://github.com/Awen-online/valt/blob/main/docs/M5_Closeout/M5_Final_Report.pdf https://github.com/Awen-online/valt/blob/main/docs/M4_LaunchRollout/M4_Rollout_Bug_Log.pdf https://github.com/CardanoPress/cardanopress/releases/tag/v1.36.1`
- **Never complete a real payment on camera.** Show the edition picker and stop before signing.
- Intro card (first 3 s): *Valt · Project Catalyst Fund 11 · Project 1100019 · Awen LLC*.

## Script

### Part 1: the challenge and the funding (0:00 to 0:35)
**Shot 1.** Intro card, then tab 1, the homepage. Scroll once, slowly.
> Streaming made music easy to hear and hard to support. Valt gives independent artists a direct line to their superfans: collect a limited edition of a song, and it unlocks that artist's private Valt, owned on Cardano.
> I'm Ian McCullough from Awen. This is Valt, the Afrocharts Web3 Artist Portal, project 1100019, funded in Project Catalyst Fund 11 under Cardano Use Cases: Concept.

### Part 2: progress, KPIs, learnings, gaps (0:35 to 1:40)
**Shot 2.** Tab 7, Final Report section 3 (the numbers grid). Hold.
> All five milestones are delivered with published evidence. The portal launched publicly on the 7th of August as a testnet preview on Cardano: three artists, twelve songs, three holder-only videos.
> Since launch: 78 unique visitors. Six wallet-connected accounts in total, feedback from five people with a Net Promoter Score of plus 60, and one artist application. Our launch posts drew about 1,400 in reach and impressions, and the artist's own post reached 875 on its own.
> The gaps, honestly: traffic was light, and no outside collector has bought an edition yet. Every token on the policy is held by our own wallets as tests.
> We also found a critical wallet-login flaw in CardanoPress, a plugin many Cardano sites use. We reported it privately, and it was fixed upstream with credit, so every site using it is safer.
> The main learning: artists are the distribution. Their audiences reached more people than ours.

On screen: *Critical CardanoPress flaw found, fixed upstream v1.36.1, credited*

On screen: *78 visitors · 6 wallet accounts · NPS +60 (n = 5) · 1,432 reach · 0 third-party purchases*

On screen (quote card): *"The most interesting part to me is the potential to make digital music feel valuable and ownable again without asking fans to become crypto users first." Mie, artist on Valt*

### Part 3: the demonstration (1:40 to 3:40)
**Shot 3a.** Tab 1, homepage. Press play on a song; the player stays at the bottom as you move.
> Every song streams in full for free. The player follows you around the site.

**Shot 3b.** Tab 2, London by Hazzy Jo. Scroll to Collect, press plus to 2 editions. **Stop there.**
> This is London by Hazzy Jo. You choose one to five editions and sign a single transaction in your wallet: payment and minting happen together. On testnet the counter says plainly that the mints so far include our own test collects.

On screen: *Collect 1 to 5 editions in one transaction · testnet preview*

**Shot 3c.** Tab 4, Hazzy Jo's Valt (locked view).
> Owning any of the artist's songs unlocks their Valt. The server checks the wallet on every request before it shows holder-only content, such as the official London music video.

**Shot 3d.** Tab 3, How to collect. Scroll the steps.
> New users get a step-by-step guide, and collecting also works inside a phone wallet's browser.

**Shot 3e.** Tab 6, GitHub repo, then the M4 Rollout Bug Log PDF.
> Everything is open source, with 19 automated tests on every push. During rollout we fixed 38 issues, each documented and mapped to the public code. We moved checkout from NMKR Pay to our own in-page Anvil checkout: one wallet signature pays and mints, with no redirect, and it works on a phone.

On screen: *Open source · 19 tests · 38 fixes documented*

### Part 4: commercialisation and what comes next (3:40 to 4:30)
**Shot 4.** Tab 5, Mie's page, then back to the homepage. End card fades in for the last 3 s.
> Valt continues past the grant as an Awen product. Through our sync.land platform, 82 artist profiles are now covered by signed terms that authorise Valt releases: that's our pipeline.
> Next is mainnet, starting with London, with direct payouts to each artist and a platform share of each sale; mobile wallet support; and an audited on-chain contract for edition limits, which we may bring back to Catalyst as a future proposal.
> Valt is a concept, and it's open source: the code is MIT-licensed on GitHub for the community to use, fork and build on.
> Thank you to Cullah, Mie and Hazzy Jo, and to the Cardano community. The reports and the code are linked in the submission.

On screen: *82 artist profiles under Valt-ready terms (sync.land)*
End card: *valt.digital · github.com/Awen-online/valt · Project Catalyst Fund 11, project 1100019*

## Shot list at a glance
| Shot | Part | Screen | Target |
|---|---|---|---|
| 1 | 1 | Intro card, homepage | 35 s |
| 2 | 2 | Final Report, results grid | 65 s |
| 3a | 3 | Homepage, play a song | 15 s |
| 3b | 3 | /song/london/, picker to 2, stop | 30 s |
| 3c | 3 | /artist/hazzy-jo/ (locked Valt) | 20 s |
| 3d | 3 | /how-to-collect/ | 15 s |
| 3e | 3 | GitHub repo, bug log | 40 s |
| 4 | 4 | Mie, homepage, end card | 50 s |
| | | **Total** | **4 min 30 s** |

## After recording
- Export 1080p (H.264). Upload to YouTube as **Public**. Title: "Valt: Project Catalyst Fund 11 close-out (project 1100019)".
- Optional polish: your own .srt captions; chapters in the description starting at 0:00 (0:00 Challenge, 0:35 Progress and KPIs, 1:40 Demo, 3:40 What's next).
- Check it is public: open it signed out in a private window.
- Send the URL and runtime. They go into the PCR header, the Final Report (section 9) and the M5 letter.
