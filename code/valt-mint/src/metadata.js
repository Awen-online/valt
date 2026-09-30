// CIP-25 (label 721) metadata for one Valt song edition, mirroring valt_build_cip25_metadata()
// in code/valt-platform/includes/nmkr.php, plus CIP-25 size rules:
//   - every metadata string must be <= 64 bytes (UTF-8); longer free-text values are split into arrays
//   - `name` and `artist` must stay single strings (wallets show `name`; Valt gating reads `artist`
//     as a string and compares it to the artist post title), so they are hard errors if too long
//   - on-chain asset names are <= 32 bytes
export const MAX_STR = 64;
export const MAX_ASSET_NAME = 32;
export const DEFAULT_PREFIX = 'valt';

const bytes = (s) => Buffer.byteLength(String(s), 'utf8');

const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', hellip: '…', ndash: '–', mdash: '—', rsquo: '’', lsquo: '‘', rdquo: '”', ldquo: '“' };
/** Decode HTML entities the way WordPress titles store them (&#8217; &#x2019; &amp; ...). */
export function decodeEntities(s) {
  return String(s ?? '').replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (m, e) => {
    if (e[0] === '#') {
      const cp = e[1] === 'x' || e[1] === 'X' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10);
      return Number.isFinite(cp) ? String.fromCodePoint(cp) : m;
    }
    return ENTITIES[e.toLowerCase()] ?? m;
  });
}

/** Split a string into <=64-byte chunks without breaking UTF-8 characters. */
export function chunk64(s) {
  const out = [];
  let cur = '';
  for (const ch of String(s)) {
    if (bytes(cur + ch) > MAX_STR) { out.push(cur); cur = ch; } else cur += ch;
  }
  if (cur || out.length === 0) out.push(cur);
  return out;
}
const fit = (s) => (bytes(s) <= MAX_STR ? s : chunk64(s));

/** "4:14" -> "PT4M14S"; ISO-8601 and other values pass through. */
export function isoDuration(d) {
  if (d == null || d === '') return undefined;
  const t = String(d).trim();
  let m = t.match(/^(\d+):(\d{1,2})$/);
  if (m) return `PT${Number(m[1])}M${Number(m[2])}S`;
  m = t.match(/^(\d+):(\d{1,2}):(\d{1,2})$/);
  if (m) return `PT${Number(m[1])}H${Number(m[2])}M${Number(m[3])}S`;
  if (/^\d+$/.test(t)) { const s = Number(t); return `PT${Math.floor(s / 60)}M${s % 60}S`; }
  return t;
}

/** Same truncation rule as the PHP: first sentence if it fits in 64 chars, else hard cut. */
export function shortDescription(desc, fallback) {
  let d = decodeEntities(String(desc ?? '').replace(/<[^>]*>/g, '')).replace(/\s+/g, ' ').trim() || fallback;
  if (bytes(d) > MAX_STR) {
    const m = d.match(/^(.{1,64}?[.!?])(\s|$)/u);
    d = m && bytes(m[1]) <= MAX_STR ? m[1] : chunk64(d)[0].trimEnd();
  }
  return d;
}

export function editionSuffix(n, suffix = 'e') {
  const i = Number(n);
  if (!Number.isInteger(i) || i < 1 || i > 999) throw new Error(`Edition must be an integer 1..999 (got ${n}).`);
  return suffix + String(i).padStart(2, '0');
}

/** On-chain asset name for an edition: prefix + asset_base + eNN (e.g. valtlondon300e18). */
export function assetNameFor(song, edition, { prefix = DEFAULT_PREFIX, suffix = 'e' } = {}) {
  if (!song.asset_base || !/^[a-z0-9]+$/.test(song.asset_base)) throw new Error('song.asset_base missing or not [a-z0-9] (export it with wp valt mint-json).');
  const name = `${prefix}${song.asset_base}${editionSuffix(edition, suffix)}`;
  if (bytes(name) > MAX_ASSET_NAME) throw new Error(`Asset name "${name}" is ${bytes(name)} bytes; the limit is ${MAX_ASSET_NAME}.`);
  return name;
}

const namesList = (v) => (Array.isArray(v) ? v : String(v ?? '').split(','))
  .map((x) => (typeof x === 'object' && x ? x : decodeEntities(String(x)).trim()))
  .filter((x) => (typeof x === 'object' ? x.name : x));

/** Build the per-asset metadata map (the value under 721 -> policy -> asset). */
export function buildAssetMetadata(song) {
  const title = decodeEntities(song.title).trim();
  const md = {
    name: title,
    image: fit(`ipfs://${song.image_cid}`),
    mediaType: song.image_mime || 'image/jpeg',
    description: fit(shortDescription(song.description, title)),
    artist: decodeEntities(song.artist).trim(),
    platform: 'Valt',
    website: fit(song.website || 'https://valt.digital'),
    music_metadata_version: 3,
  };
  if (song.album) md.album = fit(decodeEntities(song.album).trim());
  if (song.genre) md.genre = fit(Array.isArray(song.genre) ? song.genre.join(', ') : String(song.genre));
  const dur = isoDuration(song.duration);
  if (dur) md.duration = fit(dur);
  if (song.track) md.track = Number(song.track);
  const authors = namesList(song.authors ?? song.songwriter);
  if (authors.length) md.authors = authors.map((a) => (typeof a === 'object' ? { ...a, name: fit(a.name) } : { name: fit(a) }));
  const contrib = song.contributing_artists
    ? song.contributing_artists
    : (song.producer ? [{ name: decodeEntities(song.producer).trim(), role: ['Producer'] }] : []);
  if (contrib.length) md.contributing_artists = contrib.map((c) => ({ ...c, name: fit(c.name), role: (c.role || []).map(fit) }));
  if (song.copyright) md.copyright = typeof song.copyright === 'object' ? song.copyright : { master: fit(decodeEntities(song.copyright)) };
  if (song.audio_cid) {
    md.files = [{ name: fit(`${title}.mp3`), mediaType: song.audio_mime || 'audio/mpeg', src: fit(`ipfs://${song.audio_cid}`) }];
  }
  return md;
}

/** Full label-721 payload: { [policyId]: { [assetName]: md }, version? } */
export function buildCip25(song, edition, policyId, opts = {}) {
  const assetName = assetNameFor(song, edition, opts);
  const md = buildAssetMetadata(song);
  return { assetName, label: 721, metadata: { [policyId]: { [assetName]: md } } };
}

/** Validate a metadata value tree. Returns {errors, warnings}. */
export function validateCip25(payload, song) {
  const errors = [];
  const warnings = [];
  const walk = (v, p) => {
    if (typeof v === 'string') {
      if (bytes(v) > MAX_STR) errors.push(`${p}: string is ${bytes(v)} bytes (> ${MAX_STR}).`);
    } else if (Array.isArray(v)) v.forEach((x, i) => walk(x, `${p}[${i}]`));
    else if (v && typeof v === 'object') {
      for (const [k, x] of Object.entries(v)) {
        if (bytes(k) > MAX_STR) errors.push(`${p}: key "${k}" is ${bytes(k)} bytes (> ${MAX_STR}).`);
        walk(x, `${p}.${k}`);
      }
    } else if (typeof v === 'number') {
      if (!Number.isInteger(v)) errors.push(`${p}: non-integer number ${v} (metadata ints only).`);
    } else if (v !== undefined) errors.push(`${p}: unsupported value type ${typeof v}.`);
  };
  walk(payload.metadata, '721');

  const [policyId] = Object.keys(payload.metadata);
  if (!/^[0-9a-f]{56}$/.test(policyId)) errors.push(`policy id "${policyId}" is not 56 hex chars.`);
  const md = payload.metadata[policyId][payload.assetName];
  if (!md) { errors.push('asset entry missing'); return { errors, warnings }; }
  if (typeof md.name !== 'string' || !md.name) errors.push('name must be a non-empty single string (<= 64 bytes).');
  if (typeof md.artist !== 'string' || !md.artist) errors.push('artist must be a non-empty single string: Valt gating matches it against the artist post title.');
  if (/&(#\d+|#x[0-9a-f]+|[a-z]+);/i.test(JSON.stringify(md))) errors.push('HTML entities remain in metadata (decode them).');
  const img = Array.isArray(md.image) ? md.image.join('') : md.image;
  if (!/^ipfs:\/\/(Qm[1-9A-HJ-NP-Za-km-z]{44}|b[a-z2-7]{20,})$/.test(img)) errors.push(`image "${img}" is not ipfs://<CID> (placeholder images are not allowed).`);
  if (!/^image\//.test(md.mediaType)) errors.push(`mediaType "${md.mediaType}" is not an image/* type.`);
  if (md.duration && !/^P(T(\d+H)?(\d+M)?(\d+S)?)$/.test(Array.isArray(md.duration) ? md.duration.join('') : md.duration)) warnings.push(`duration "${md.duration}" is not ISO-8601 (PT#M#S).`);
  if (song && song.artist && md.artist !== decodeEntities(song.artist).trim()) errors.push('artist differs from the exported artist title.');
  if (!md.album) warnings.push('no album');
  if (!md.genre) warnings.push('no genre');
  if (!md.duration) warnings.push('no duration');
  return { errors, warnings };
}

export function validateSong(song) {
  const errors = [];
  for (const f of ['title', 'artist', 'image_cid', 'asset_base']) if (!song[f]) errors.push(`song.${f} is required`);
  if (song.image_cid && /PLACEHOLDER|PENDING/i.test(song.image_cid)) errors.push('song.image_cid is a placeholder; set the real IPFS CID (valt_nft_ipfs_hash).');
  return errors;
}
