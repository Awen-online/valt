<p align="center">
  <img src="assets/valt-banner.png" alt="Valt - Token-gated music platform on Cardano" width="100%">
</p>

# Afrocharts | Web3 Artist Portal (Awen)
## aka Valt
Visit our domain: https://valt.digital

## Documentation

Here you can find links to all necessary documentation for each of the milestones throughout the progress of this project.
### Milestone 1 - Initialization
>**Documents**
>
>[Setup Report](M1_Initialization/Setup_Report_Web3_Artist_Portal_Afrocharts_x_Awen.pdf) - information about initial design, including Afrocharts.com platform analysis setup
>
>[Afrocharts Partner API Doc](M1_Initialization/AfroCharts_Partners_API_Documentation_V1.pdf) - documentation to integrate with Afrocharts.com platform via permissioned API
>
>[Project Status Report](M1_Initialization/Awen_x_AfroCharts_Project_Status_Report.pdf) - Current project status
>
>[Project Timeline](M1_Initialization/Awen_x_AfroCharts_Project_Timeline.pdf) - project roadmap, timeline, and responsibilities
>
>[Notion Kanban Board](https://awen-online.notion.site/3519bb544b96476ea2baef08991e7644?v=575936b18fe94d4dba30037ca464be9f&pvs=32) - board of all tasks, stories, and epics for this project


### Milestone 2 - Development
>**Documents**
>
>[Proof of Achievement](M2_Development/Valt_M2_Proof_of_Achievement.pdf) - consolidated M2 submission: every Output, Acceptance Criterion, and Evidence Requirement mapped to verifiable proof, with the evidence screenshots embedded
>
>[Development Report](M2_Development/M2_Development_Report.pdf) - the web3 portal as built: architecture, on-chain NFT proof, the minting flow, user flow & token-gating
>
>[Launch Partner Roster](M2_Development/M2_Launch_Partner_Roster.pdf) - the artist partners chosen and ready for the initial launch (Acceptance Criterion: 5-10 artists)
>
>[Project Status Report (M2)](M2_Development/Awen%20x%20AfroCharts%20Project%20Status%20Report%20M2.pdf) - current project status
>
>[Project Timeline (M2)](M2_Development/Awen%20x%20AfroCharts%20Project%20Timeline%20M2.pdf) - updated roadmap, timeline, and responsibilities
>
>[Evidence Screenshots](M2_Development/screenshots) - the E1-E12 evidence set (NMKR Studio + inventory, Cardanoscan, live portal, wallet connection, the full minting sequence, The Valt unlocked, NFT Monitor backend, the Valt terms clauses and signatures) plus the S1-S5 supporting screens
>
>**Verify**
>
>[Live portal](https://www.valt.digital) - the web3 artist portal, running on the Cardano pre-production testnet
>
>[On-chain proof (Cardanoscan preprod)](https://preprod.cardanoscan.io/tokenPolicy/bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a) - the 8 song NFTs minted under policy `bf5a88ac…`
>
>[Source code](../code) - the curated open-source platform (plugin + theme)

### Milestone 3 - Implementation & Prelaunch
>**Documents**
>
>[Proof of Achievement](M3_Implementation/M3_Proof_of_Achievement.pdf) - the milestone summary: outputs, acceptance criteria, evidence, and how a reviewer can verify every claim independently
>
>[Test & Bug-Fix Report](M3_Implementation/M3_Test_and_Bugfix_Report.pdf) - the test suite and its results, the six defects found and resolved, repository reconciliation, known limitations
>
>[Security Audit Report](M3_Implementation/M3_Security_Audit_Report.pdf) - an internal security review (not an independent third-party audit): automated scanning (semgrep) plus an autonomous-style assessment, the manual and on-chain security reviews, findings with dispositions, and mainnet prerequisites
>
>[User Feedback & Roadmap](M3_Implementation/M3_User_Feedback_and_Roadmap.pdf) - feedback channels, the in-product survey, the analysis (n=5: artists and collectors), improvements made, and the updated roadmap through M4/M5
>
>[Project Status Report (M3)](M3_Implementation/M3_Project_Status.pdf) - current project status
>
>[Project Timeline (M3)](M3_Implementation/M3_Project_Timeline.pdf) - updated roadmap, timeline, and responsibilities
>
>**Verify**
>
>[Test suite](../tests) - 15 tests / 25 assertions at M3, now 19 tests / 41 assertions after the M4 checkout tests: `composer install && vendor/bin/phpunit`
>
>[CI workflow](../.github/workflows/ci.yml) - lint + suite on PHP 8.1-8.3 (8.0 dropped at M4: PHPUnit 10 needs 8.1) plus a semgrep security scan, on every push
>
>[Load check](../tests/load-plugin.php) - `php tests/load-plugin.php code/valt-platform` boots the published build against a stubbed WordPress API and reports `VERDICT=LOADED`

### Milestone 4 - Launch and Rollout
>**Documents**
>
>[Proof of Achievement](M4_LaunchRollout/M4_Proof_of_Achievement.pdf) - submitted 30 Sep 2026; the milestone summary: the public launch, the launch announcements, the initial adoption phase, and how a reviewer can verify each acceptance criterion
>
>[Rollout Bug Log](M4_LaunchRollout/M4_Rollout_Bug_Log.pdf) - every defect found on the live site from 7 August to 30 September 2026 with its root cause, resolution and commit or deploy record (38 resolved, each mapped to the public code), observations that needed no code change, the disclosed multi-wallet checkout test, and the items still open
>
>[Project Status Report (M4)](M4_LaunchRollout/M4_Project_Status.pdf) - current project status
>
>[Project Timeline (M4)](M4_LaunchRollout/M4_Project_Timeline.pdf) - updated roadmap, timeline, and responsibilities
>
>**Verify**
>
>[Live portal](https://www.valt.digital) - the public web3 portal on the Cardano pre-production testnet
>
>[Featured release: London by Hazzy Jo](https://www.valt.digital/song/london/) - the in-page collect flow (1 to 5 editions per checkout) and the artist's token-gated Valt
>
>[How to collect](https://www.valt.digital/how-to-collect/) - the step-by-step guide for new users on testnet
>
>[On-chain policy (Cardanoscan preprod)](https://preprod.cardanoscan.io/tokenPolicy/bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a) - the song NFTs minted under policy `bf5a88ac…`
>
>On-chain test purchases through the public checkout (30 Sep 2026): [Freakshow a01-a02](https://preprod.cardanoscan.io/transaction/1d28cc8412209b1e661449223c34cbee0d87efe0c453aaf60fc7220b2be03ea1), [London a01-a05](https://preprod.cardanoscan.io/transaction/466e4369b6db390ca70d2e7b1ee859273d1988e71d85b73411119ffeb9c8ea5c), [London #15 from a mobile wallet](https://preprod.cardanoscan.io/transaction/47c9af06cebc70ee2561113f939781861902ef450f74bd6ac20d92437afe9dd3) - payment and mint in one transaction
>
>Launch announcements (7 Aug 2026): [X](https://x.com/awen_online/status/2085778010221781268), [Instagram](https://www.instagram.com/p/Dbv0KD1oGKf/), [LinkedIn](https://www.linkedin.com/feed/update/urn:li:share:7491543690490929153/), [Facebook](https://www.facebook.com/1520214066786625) and the [launch article](https://awen.online/news/valt-is-live/) (published 5 Aug)
>
>Artist announcement (Cullah, 8 Aug 2026): [X](https://x.com/CullahMusic/status/2086145493663428971), [Instagram](https://www.instagram.com/p/DbycCdGIB05/), [LinkedIn](https://www.linkedin.com/feed/update/urn:li:share:7491911173290774530/)
>
>Follow-up, one month on (21 Sep 2026): [X](https://x.com/awen_online/status/2101953731768049855), [Instagram](https://www.instagram.com/p/Ddiv0V5EkIG/), [article](https://awen.online/news/valt-one-month-on/)
>
>Featured release promo, London by Hazzy Jo (30 Sep 2026): [X](https://x.com/awen_online/status/2105201059018760418), [Instagram](https://www.instagram.com/reel/Dd50c76AIVA/), [LinkedIn](https://www.linkedin.com/feed/update/urn:li:ugcPost:7510966925615824898/)
>
>[CI](../.github/workflows/ci.yml) - lint + PHPUnit (19 tests, 41 assertions) on PHP 8.1-8.3 plus a semgrep scan, on every push

### Milestone 5 - Closeout & Evaluation
>**Documents**
>
>[Final Report](M5_Closeout/M5_Final_Report.pdf) - final design, results and evaluation: the architecture, the metrics dashboard, user feedback and a named artist testimonial, the per-artist evaluation of recognition, opportunities and compensation, the ecosystem contribution, and the next phase
>
>[Project Close-out Report](M5_Closeout/M5_Project_Closeout_Report.pdf) - the Catalyst close-out summary: challenge and project KPIs, key achievements, learnings and next steps
>
>[Close-out video](https://awen.online/valt-closeout) - the close-out video (the final cut is being finalised)
>
>[Close-out video script](M5_Closeout/M5_Closeout_Video_Script.md) - the script and shot list for the close-out video
>
>[Screens](M5_Closeout/screens) - the live portal as delivered (homepage, featured release, how to collect, a token-gated Valt)
>
>**Verify**
>
>[Live portal](https://www.valt.digital) - the Web3 artist portal on the Cardano pre-production testnet
>
>[Open-source code](../code) - the curated platform, with tests and CI on every push
>
>[CardanoPress v1.36.1](https://github.com/CardanoPress/cardanopress/releases/tag/v1.36.1) - the critical wallet-authentication fix we reported upstream, credited in the release notes
