# VALT Points
### A Simple Framework for Verifiable Music Ownership

*v0.1 — Proof of Concept*

---

## The Problem Is Obvious

You know the feeling. You find a song that changes your week. You play it forty times. You tell three friends. You go to the show. You buy the vinyl.

And the artist gets $0.003.

That's not a rounding error. That's the actual per-stream rate on the world's largest music platform. An artist needs a quarter-million streams a month just to make minimum wage. The math is rigged against everyone except the top 1%.

Bandcamp understood this. For a decade, it was the last honest record store on the internet — artists kept 85% of every sale, fans paid real money for music they actually wanted, and on Bandcamp Fridays, the community moved $100 million in a single day. Then it got acquired. Then acquired again. Then half the team was laid off. The community that built it had no say, no vote, no mechanism to stop it.

The lesson isn't that the model failed. The model worked beautifully. The lesson is that when one company holds the keys, one boardroom decision can kill what millions of people built.

---

## What If Fans Could Own Their Fandom?

Not metaphorically. Actually own it.

When you buy a record at a shop, you own something real. It sits on your shelf. You can lend it, sell it, give it away. Nobody can revoke it. The record is proof that you were there, that you cared, that you listened.

Digital music lost all of that. Streaming turned ownership into access, and access into a number on someone else's spreadsheet.

VALT brings it back.

When you collect a song on VALT, you receive a token — a native Cardano asset — that proves you own it. Not a license agreement. Not a rental. An actual digital object, on a public blockchain, that belongs to you. If VALT disappeared tomorrow, your token still exists. Your ownership is verified by math, not by a company's terms of service.

This is the core idea. Everything else flows from it.

---

## VALT Points: How the Economy Works

VALT Points are the internal currency of the platform. They are not cryptocurrency. They have no market value. You cannot trade them on an exchange. They exist for one reason: to make fandom visible, measurable, and rewarding.

Think of Points like experience points in a game you already play — the game of finding great music and supporting the artists who make it.

### Earning Points

| Action | Points | Why |
|--------|--------|-----|
| Collect a song NFT | 100 | You put money where your ears are |
| Connect your wallet | 15 | You showed up |
| Visit daily | 5 | Consistency matters |
| Complete your profile | 25 | Identity is participation |
| View exclusive content | 1 | Attention is real |
| Share an artist | 10 | Discovery is a gift |
| Back an album campaign | 1:1 | You believed before it existed |

### Levels

Your total Points determine your level. Levels are visible on your profile, on leaderboards, and inside artist fan clubs.

| Level | Name | Threshold |
|-------|------|-----------|
| 1 | Listener | 0 |
| 2 | Fan | 50 |
| 3 | Superfan | 200 |
| 4 | Patron | 500 |
| 5 | Legend | 1,500 |

A Listener is someone who just arrived. A Legend is someone who has deeply invested — time, money, attention — across the platform. Both are welcome. The levels just make the investment visible.

### Badges

Badges mark specific achievements. They are non-transferable. They are permanent proof.

- **First NFT** — You collected your first song
- **Collector** — You own 5 or more
- **Super Collector** — You own 25 or more
- **Early Supporter** — You backed a campaign before it was half-funded
- **Web3 Ready** — You connected a Cardano wallet
- **Dedicated Fan** — 7-day login streak
- **Music Explorer** — You hold NFTs from 3 or more artists

Badges aren't vanity metrics. They're social proof. When an artist looks at their fan club and sees ten Early Supporters, that's meaningful signal. Those are the people who believed first.

---

## Artist Fan Clubs: Token-Gated Experiences

Every artist on VALT can have a Fan Club — an exclusive space unlocked only by holding that artist's NFT.

This is not a paywall. It's a velvet rope. The difference matters.

A paywall says: "Pay $9.99/month or you can't come in." It rents you access. When you stop paying, you're locked out. The relationship is transactional.

A token gate says: "If you own this, you belong here." It recognizes ownership. You collected the artist's work. That act of collecting is your permanent key. Nobody revokes it. Nobody can.

Inside a Fan Club, artists can share:
- Unreleased demos and acoustic versions
- Behind-the-scenes studio content
- Early access to new releases
- Direct messages to their community
- Exclusive merchandise drops
- Campaign announcements for upcoming albums

The artist controls what's inside. VALT provides the gate.

---

## Album Campaigns: Proto-Tokenomics

Here's where it gets interesting.

An artist working on a new album can launch a Campaign. They set a goal — say, 10,000 Points — and a deadline. Fans pledge their earned Points toward that goal.

This is not crowdfunding. No money changes hands. Points are not dollars.

But the signal is real.

When 200 fans pledge Points toward an album, the artist knows demand exists before recording a single note. The backers know they are invested — they spent Points they earned through genuine engagement. The leaderboard shows who believed earliest.

This is proto-tokenomics — the behavioral skeleton of what becomes real tokenomics when smart contracts mature. Every pledge, every backer, every campaign progress bar is training the pattern: fans fund art directly, artists create for a known audience, and the platform is just the pipe.

When Cardano smart contracts are ready for production-grade revenue sharing (they're close, but not yet simple enough for mass market), the upgrade path is clear: Points become tokens. Pledges become stakes. Campaign goals become funding rounds with on-chain royalty splits. The UX doesn't change. The economics just become real.

---

## Discovery and Leaderboards

Great music platforms don't just host music. They help you find it.

VALT's discovery is driven by community signal, not algorithms. The trending score for an artist is a weighted function:

```
trending = (recent_collectors * 10) + (recent_views * 1) + (new_fans * 5)
```

This means an artist who is being collected, viewed, and followed — by real people making real decisions — rises to the top. There is no "pay for placement." There is no "editorial playlist" controlled by the platform. The community curates by participating.

Leaderboards show top fans — globally and per artist. This isn't competition for its own sake. It's visibility. When you're in the top 10 fans of an artist you love, and that artist sees your name, that's a connection no algorithm can manufacture.

---

## The Technical Foundation

VALT is built on Cardano for specific, practical reasons:

**Native tokens.** On Cardano, minting a token doesn't require deploying a smart contract. The token is a first-class citizen of the blockchain, as real as ADA itself. This means lower cost, lower complexity, and lower risk. A song NFT on VALT costs roughly $0.20 to mint, compared to $5-50 on Ethereum depending on gas.

**CIP-25 metadata.** Every song NFT carries its metadata — title, artist, album, genre, cover art — directly in the minting transaction. This metadata is permanent and publicly verifiable. Anyone can look up any VALT NFT and see exactly what it is, who made it, and when.

**EUTXO determinism.** Before you submit a transaction on Cardano, you know exactly what it will cost and exactly what will happen. No failed transactions eating gas. No front-running. This matters for a consumer product — regular people should not need to understand gas optimization to buy a song.

**Stake pool alignment.** An artist could one day run a Cardano stake pool. Their fans could delegate ADA to it, earning staking rewards while supporting the artist's infrastructure. This creates a non-extractive economic relationship between artist and fan that doesn't exist anywhere in traditional music.

---

## What This Is Not

VALT Points are not a cryptocurrency. They cannot be traded, sold, or exchanged for money. They are an internal engagement metric — closer to Reddit karma than to Bitcoin.

VALT is not a financial product. Collecting a song NFT is buying music, not making an investment. The NFT may have secondary market value (just as a vinyl record might), but VALT makes no promises about future value.

VALT is not trying to replace streaming. Spotify is good at what it does — infinite access to everything for $10/month. VALT is something different: a place where the music you love is something you own, the artists you support know you exist, and the community that forms around great music has a structure that can't be sold out from under it.

---

## The Vision

Imagine this:

You open VALT. The trending page shows you three artists you've never heard of, surfaced entirely by community activity. You listen. One of them hits. You collect the song for $3. An NFT appears in your wallet. You've earned 100 Points. You're now a Fan.

You visit the artist's Fan Club. There's an unreleased acoustic version and a note about the upcoming album. You love it. The artist has launched a Campaign — 10,000 Points to greenlight production. You pledge 50 of yours. You're Early Supporter #7.

Three months later, the album drops. Your name is on the backer wall. You have a badge that proves you were there before anyone else. The artist has a funded album and 200 fans who are genuinely invested in its success.

No label took 85%. No algorithm decided who heard it. No corporation can acquire the platform and dismantle what the community built.

That's VALT.

---

*This document describes a proof-of-concept system. Proto-tokenomics features use engagement points with no monetary value. Future iterations will introduce Cardano smart contracts for on-chain revenue sharing, royalty splits, and governance tokens. The platform is open source at github.com/Awen-online/valt.*
