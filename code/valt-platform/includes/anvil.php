<?php
defined( 'ABSPATH' ) || exit;

/**
 * Self-hosted Collect via the Anvil API (preprod), a fallback to NMKR Pay.
 *
 * Flow: the fan's wallet (CIP-30) sends its address + UTXOs → POST /valt/v1/anvil/build reserves
 * editions in our own ledger and asks Anvil for ONE transaction that pays the song price to our
 * payout address and mints the editions (CIP-25) straight to the fan → the wallet signs its part →
 * POST /valt/v1/anvil/submit adds the policy key's witness and submits through Anvil.
 *
 * Safety:
 * - Opt-in per song: meta valt_checkout = 'anvil' (default is NMKR). Preprod only.
 * - Own edition series "<asset>aNN" so names never collide with NMKR's "<asset>eNN".
 * - Hard cap per song: meta valt_anvil_cap, never above valt_nft_max_supply.
 * - The policy key signs only a transaction WE built and stored, and only after decoding its body
 *   and checking it mints exactly the reserved editions to the buyer and pays the payout address.
 * - The key file lives outside the web root (VALT_ANVIL_POLICY_SKEY_PATH or
 *   ~/valt-private-keys/policy-preprod.skey) and is checked against the configured policy id.
 */

if ( ! defined( 'VALT_ANVIL_LOCK_TTL' ) ) {
	define( 'VALT_ANVIL_LOCK_TTL', 15 * MINUTE_IN_SECONDS ); // how long an unsigned build holds its editions
}

// ─── Config ──────────────────────────────────────────────────────────

function valt_anvil_config(): array {
	$nmkr = function_exists( 'valt_nmkr_config' ) ? valt_nmkr_config() : [ 'mode' => 'preprod', 'policy_id' => '' ];
	$mode = $nmkr['mode'] === 'mainnet' ? 'mainnet' : 'preprod';
	$key  = $mode === 'mainnet'
		? ( defined( 'VALT_ANVIL_MAINNET_API_KEY' ) ? (string) VALT_ANVIL_MAINNET_API_KEY : '' )
		: ( defined( 'VALT_ANVIL_PREPROD_API_KEY' ) ? (string) VALT_ANVIL_PREPROD_API_KEY : '' );
	// PHP-FPM usually runs without $HOME (CLI has it), so fall back to the account's home dir.
	$home = (string) ( getenv( 'HOME' ) ?: ( function_exists( 'posix_getpwuid' ) ? ( posix_getpwuid( posix_geteuid() )['dir'] ?? '' ) : '' ) );
	return [
		'mode'      => $mode,
		'base'      => $mode === 'mainnet' ? 'https://prod.api.ada-anvil.app/v2/services' : 'https://preprod.api.ada-anvil.app/v2/services',
		'api_key'   => $key,
		'policy_id' => strtolower( (string) $nmkr['policy_id'] ),
		'skey_path' => defined( 'VALT_ANVIL_POLICY_SKEY_PATH' ) ? (string) VALT_ANVIL_POLICY_SKEY_PATH : ( $home ? $home . '/valt-private-keys/policy-preprod.skey' : '' ),
		'payout'    => defined( 'VALT_ANVIL_PAYOUT_ADDRESS' ) ? (string) VALT_ANVIL_PAYOUT_ADDRESS : '',
		'explorer'  => $mode === 'mainnet' ? 'https://cardanoscan.io/transaction/' : 'https://preprod.cardanoscan.io/transaction/',
	];
}

/** Why Anvil checkout can't run right now, or true. */
function valt_anvil_ready() {
	$c = valt_anvil_config();
	if ( $c['mode'] !== 'preprod' ) return new WP_Error( 'valt_anvil_mainnet', 'Anvil checkout is enabled on preprod only.' );
	if ( $c['api_key'] === '' ) return new WP_Error( 'valt_anvil_key', 'Anvil API key is not configured.' );
	if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) return new WP_Error( 'valt_anvil_sodium', 'PHP sodium is not available.' );
	if ( $c['payout'] === '' || ! valt_bech32_decode( $c['payout'] ) ) return new WP_Error( 'valt_anvil_payout', 'Payout address is not configured.' );
	if ( $c['skey_path'] === '' || ! is_readable( $c['skey_path'] ) ) return new WP_Error( 'valt_anvil_policy', 'Policy key file is not available.' );
	return true;
}

/** Is this song switched to Anvil checkout? (Does not check readiness.) */
function valt_song_uses_anvil( int $song_id ): bool {
	return get_post_meta( $song_id, 'valt_checkout', true ) === 'anvil';
}

// ─── Policy key ──────────────────────────────────────────────────────

/** Native script CBOR for ScriptAll[ sig(keyHash) ] — the NMKR-style single-signer policy. */
function valt_anvil_script_cbor( string $key_hash_bin ): string {
	return "\x82\x01\x81\x82\x00\x58\x1c" . $key_hash_bin;
}

/** Policy id (script hash) = blake2b-224( 0x00 ‖ script CBOR ). */
function valt_anvil_script_hash( string $key_hash_bin ): string {
	return bin2hex( sodium_crypto_generichash( "\x00" . valt_anvil_script_cbor( $key_hash_bin ), '', 28 ) );
}

/**
 * Load the policy signing key and prove it controls the configured policy.
 *
 * @return array{sk:string,pk:string,key_hash:string}|WP_Error
 */
function valt_anvil_policy_key() {
	$c   = valt_anvil_config();
	$raw = ( $c['skey_path'] !== '' && is_readable( $c['skey_path'] ) ) ? file_get_contents( $c['skey_path'] ) : false;
	$j   = $raw ? json_decode( $raw, true ) : null;
	$hex = is_array( $j ) ? (string) ( $j['cborHex'] ?? '' ) : '';
	if ( strlen( $hex ) !== 68 || strpos( $hex, '5820' ) !== 0 || ! ctype_xdigit( $hex ) ) {
		return new WP_Error( 'valt_anvil_policy', 'Policy key file is missing or not a Shelley ed25519 signing key.' );
	}
	$seed = hex2bin( substr( $hex, 4 ) );
	$kp   = sodium_crypto_sign_seed_keypair( $seed );
	sodium_memzero( $seed );
	$pk = sodium_crypto_sign_publickey( $kp );
	$kh = sodium_crypto_generichash( $pk, '', 28 );
	if ( valt_anvil_script_hash( $kh ) !== $c['policy_id'] ) {
		return new WP_Error( 'valt_anvil_policy', 'Policy key does not match the configured policy id.' );
	}
	return [ 'sk' => sodium_crypto_sign_secretkey( $kp ), 'pk' => $pk, 'key_hash' => bin2hex( $kh ) ];
}

// ─── Minimal CBOR (enough to check and co-sign a transaction) ───────

/** Read one CBOR head. Returns [major, argument (null = indefinite), additional info]. */
function valt_cbor_head( string $s, int &$o ): array {
	if ( $o >= strlen( $s ) ) throw new RuntimeException( 'cbor: unexpected end' );
	$b  = ord( $s[ $o++ ] );
	$mj = $b >> 5;
	$ai = $b & 31;
	if ( $ai < 24 ) {
		$v = $ai;
	} elseif ( $ai === 24 ) {
		$v = ord( $s[ $o ] ); $o += 1;
	} elseif ( $ai === 25 ) {
		$v = unpack( 'n', substr( $s, $o, 2 ) )[1]; $o += 2;
	} elseif ( $ai === 26 ) {
		$v = unpack( 'N', substr( $s, $o, 4 ) )[1]; $o += 4;
	} elseif ( $ai === 27 ) {
		$v = unpack( 'J', substr( $s, $o, 8 ) )[1]; $o += 8;
	} elseif ( $ai === 31 && $mj >= 2 && $mj <= 5 ) {
		$v = null;
	} else {
		throw new RuntimeException( 'cbor: unsupported head' );
	}
	if ( $o > strlen( $s ) ) throw new RuntimeException( 'cbor: unexpected end' );
	return [ $mj, $v, $ai ];
}

/**
 * Decode one CBOR item. Byte strings → ['b' => hex], maps → ['m' => [[k, v], ...]],
 * tags → ['t' => n, 'v' => item], arrays → lists, text → string, simple values → bool/null.
 */
function valt_cbor_decode( string $s, int &$o ) {
	[ $mj, $v, $ai ] = valt_cbor_head( $s, $o );
	switch ( $mj ) {
		case 0:
			return $v;
		case 1:
			return -1 - $v;
		case 2:
		case 3:
			if ( $v === null ) {
				$out = '';
				while ( ( $s[ $o ] ?? "\xff" ) !== "\xff" ) {
					$chunk = valt_cbor_decode( $s, $o );
					$out  .= is_array( $chunk ) ? hex2bin( $chunk['b'] ) : $chunk;
				}
				$o++;
			} else {
				if ( $o + $v > strlen( $s ) ) throw new RuntimeException( 'cbor: unexpected end' );
				$out = substr( $s, $o, $v );
				$o  += $v;
			}
			return $mj === 2 ? [ 'b' => bin2hex( $out ) ] : $out;
		case 4:
		case 5:
			$items = [];
			for ( $i = 0; $v === null ? ( ( $s[ $o ] ?? "\xff" ) !== "\xff" ) : $i < $v; $i++ ) {
				$items[] = $mj === 4 ? valt_cbor_decode( $s, $o ) : [ valt_cbor_decode( $s, $o ), valt_cbor_decode( $s, $o ) ];
			}
			if ( $v === null ) $o++;
			return $mj === 4 ? $items : [ 'm' => $items ];
		case 6:
			return [ 't' => $v, 'v' => valt_cbor_decode( $s, $o ) ];
		default: // 7: simple values and floats (argument already consumed)
			return $ai === 20 ? false : ( $ai === 21 ? true : null );
	}
}

/** Offset just past the item starting at $o. */
function valt_cbor_skip( string $s, int $o ): int {
	valt_cbor_decode( $s, $o );
	return $o;
}

/** Encode a CBOR head for major type $mj with argument $n. */
function valt_cbor_encode_head( int $mj, int $n ): string {
	$m = $mj << 5;
	if ( $n < 24 ) return chr( $m | $n );
	if ( $n < 0x100 ) return chr( $m | 24 ) . chr( $n );
	if ( $n < 0x10000 ) return chr( $m | 25 ) . pack( 'n', $n );
	if ( $n < 0x100000000 ) return chr( $m | 26 ) . pack( 'N', $n );
	return chr( $m | 27 ) . pack( 'J', $n );
}

/** Value for a key in a decoded map (int key, or hex string for a byte-string key). */
function valt_cbor_get( $map, $key ) {
	if ( ! is_array( $map ) || ! isset( $map['m'] ) ) return null;
	foreach ( $map['m'] as [ $k, $v ] ) {
		if ( $k === $key || ( is_array( $k ) && isset( $k['b'] ) && $k['b'] === $key ) ) return $v;
	}
	return null;
}

/** Split a CBOR array into the raw bytes of each element. */
function valt_cbor_raw_items( string $s ): array {
	$o = 0;
	[ $mj, $n ] = valt_cbor_head( $s, $o );
	if ( $mj !== 4 || $n === null ) throw new RuntimeException( 'cbor: expected a definite array' );
	$out = [];
	for ( $i = 0; $i < $n; $i++ ) {
		$st    = $o;
		$o     = valt_cbor_skip( $s, $o );
		$out[] = substr( $s, $st, $o - $st );
	}
	if ( $o !== strlen( $s ) ) throw new RuntimeException( 'cbor: trailing bytes' );
	return $out;
}

/** Split a CBOR map with unsigned-int keys into [ key => raw value bytes ]. */
function valt_cbor_raw_map( string $s ): array {
	$o = 0;
	[ $mj, $n ] = valt_cbor_head( $s, $o );
	if ( $mj !== 5 || $n === null ) throw new RuntimeException( 'cbor: expected a definite map' );
	$out = [];
	for ( $i = 0; $i < $n; $i++ ) {
		$k = valt_cbor_decode( $s, $o );
		if ( ! is_int( $k ) ) throw new RuntimeException( 'cbor: unexpected map key' );
		$st        = $o;
		$o         = valt_cbor_skip( $s, $o );
		$out[ $k ] = substr( $s, $st, $o - $st );
	}
	if ( $o !== strlen( $s ) ) throw new RuntimeException( 'cbor: trailing bytes' );
	return $out;
}

/** Raw vkey witnesses ([vkey, sig] items) from a witness-set key 0 value (array or tag-258 set). */
function valt_cbor_vkey_items( string $raw ): array {
	if ( $raw === '' ) return [];
	$o = 0;
	[ $mj, $n ] = valt_cbor_head( $raw, $o );
	if ( $mj === 6 ) { // tag 258 (set): the array follows
		[ $mj, $n ] = valt_cbor_head( $raw, $o );
	}
	if ( $mj !== 4 || $n === null ) throw new RuntimeException( 'cbor: bad vkey witness list' );
	$out = [];
	for ( $i = 0; $i < $n; $i++ ) {
		$st    = $o;
		$o     = valt_cbor_skip( $raw, $o );
		$out[] = substr( $raw, $st, $o - $st );
	}
	return $out;
}

// ─── Transactions ────────────────────────────────────────────────────

/** Transaction id (blake2b-256 of the body) from a full transaction's bytes. */
function valt_anvil_tx_hash( string $tx_bin ): string {
	$parts = valt_cbor_raw_items( $tx_bin );
	return bin2hex( sodium_crypto_generichash( $parts[0], '', 32 ) );
}

/** A vkey witness [vkey, signature] over the transaction id, as raw CBOR. */
function valt_anvil_vkey_witness( string $tx_hash_hex, string $sk, string $pk ): string {
	$sig = sodium_crypto_sign_detached( hex2bin( $tx_hash_hex ), $sk );
	return "\x82\x58\x20" . $pk . "\x58\x40" . $sig;
}

/**
 * Add vkey witnesses to a transaction, keeping body and auxiliary data byte-for-byte.
 *
 * @param string   $tx_bin    Full transaction (as built).
 * @param string[] $witnesses Raw [vkey, sig] CBOR items.
 */
function valt_anvil_add_witnesses( string $tx_bin, array $witnesses ): string {
	$parts = valt_cbor_raw_items( $tx_bin );
	$ws    = valt_cbor_raw_map( $parts[1] );
	$have  = valt_cbor_vkey_items( $ws[0] ?? '' );
	$seen  = [];
	$all   = [];
	foreach ( array_merge( $have, $witnesses ) as $w ) {
		$vk = substr( $w, 3, 32 ); // after 82 58 20
		if ( isset( $seen[ $vk ] ) ) continue;
		$seen[ $vk ] = true;
		$all[]       = $w;
	}
	$ws[0] = valt_cbor_encode_head( 4, count( $all ) ) . implode( '', $all );
	ksort( $ws );
	$ws_bin = valt_cbor_encode_head( 5, count( $ws ) );
	foreach ( $ws as $k => $raw ) {
		$ws_bin .= valt_cbor_encode_head( 0, $k ) . $raw;
	}
	$parts[1] = $ws_bin;
	return valt_cbor_encode_head( 4, count( $parts ) ) . implode( '', $parts );
}

/**
 * Raw [vkey, sig] items from a wallet's signTx(tx, true) witness set, each checked against the tx id.
 *
 * @return string[]|WP_Error
 */
function valt_anvil_wallet_witnesses( string $witness_hex, string $tx_hash_hex ) {
	if ( $witness_hex === '' || ! ctype_xdigit( $witness_hex ) || strlen( $witness_hex ) > 20000 ) {
		return new WP_Error( 'valt_anvil_witness', 'The wallet signature was not readable.' );
	}
	try {
		$ws    = valt_cbor_raw_map( hex2bin( $witness_hex ) );
		$items = valt_cbor_vkey_items( $ws[0] ?? '' );
	} catch ( RuntimeException $e ) {
		return new WP_Error( 'valt_anvil_witness', 'The wallet signature was not readable.' );
	}
	if ( ! $items ) return new WP_Error( 'valt_anvil_witness', 'The wallet did not sign the transaction.' );
	foreach ( $items as $w ) {
		$o = 0;
		$d = valt_cbor_decode( $w, $o );
		$vk  = is_array( $d ) && isset( $d[0]['b'] ) ? hex2bin( $d[0]['b'] ) : '';
		$sig = is_array( $d ) && isset( $d[1]['b'] ) ? hex2bin( $d[1]['b'] ) : '';
		if ( strlen( $vk ) !== 32 || strlen( $sig ) !== 64 || ! sodium_crypto_sign_verify_detached( $sig, hex2bin( $tx_hash_hex ), $vk ) ) {
			return new WP_Error( 'valt_anvil_witness', 'The wallet signature does not match this transaction.' );
		}
	}
	return $items;
}

/**
 * Check a built transaction body does exactly what we asked: mints (or burns) exactly $assets on our
 * policy, and — for a sale — pays at least $lovelace to $payout and delivers every asset to $buyer.
 *
 * @param array  $assets  [ asset_name_hex => quantity ]
 * @return bool|WP_Error
 */
function valt_anvil_verify_body( string $tx_bin, string $policy_id, array $assets, string $payout_hex = '', int $lovelace = 0, string $buyer_hex = '' ) {
	try {
		$parts = valt_cbor_raw_items( $tx_bin );
		$o     = 0;
		$body  = valt_cbor_decode( $parts[0], $o );
	} catch ( RuntimeException $e ) {
		return new WP_Error( 'valt_anvil_verify', 'Built transaction could not be decoded.' );
	}
	$mint = valt_cbor_get( $body, 9 );
	if ( ! is_array( $mint ) || count( $mint['m'] ?? [] ) !== 1 ) {
		return new WP_Error( 'valt_anvil_verify', 'Built transaction mints on an unexpected policy.' );
	}
	[ $pk, $inner ] = $mint['m'][0];
	if ( ( $pk['b'] ?? '' ) !== $policy_id ) {
		return new WP_Error( 'valt_anvil_verify', 'Built transaction mints on an unexpected policy.' );
	}
	$got = [];
	foreach ( $inner['m'] ?? [] as [ $an, $q ] ) {
		$got[ $an['b'] ?? '?' ] = $q;
	}
	ksort( $got );
	ksort( $assets );
	if ( $got !== $assets ) {
		return new WP_Error( 'valt_anvil_verify', 'Built transaction mints different tokens than reserved.' );
	}
	if ( $payout_hex === '' ) return true; // burn: mint check is enough

	$paid      = 0;
	$delivered = [];
	foreach ( (array) valt_cbor_get( $body, 1 ) as $out ) {
		if ( isset( $out['t'] ) ) $out = $out['v'];
		$addr = isset( $out['m'] ) ? ( valt_cbor_get( $out, 0 )['b'] ?? '' ) : ( $out[0]['b'] ?? '' );
		$val  = isset( $out['m'] ) ? valt_cbor_get( $out, 1 ) : ( $out[1] ?? 0 );
		$coin = is_int( $val ) ? $val : (int) ( $val[0] ?? 0 );
		if ( $addr === $payout_hex ) $paid += $coin;
		if ( $addr === $buyer_hex && is_array( $val ) && isset( $val[1] ) ) {
			$ma = valt_cbor_get( $val[1], $policy_id );
			foreach ( $ma['m'] ?? [] as [ $an, $q ] ) {
				$delivered[ $an['b'] ?? '?' ] = ( $delivered[ $an['b'] ?? '?' ] ?? 0 ) + $q;
			}
		}
	}
	if ( $paid < $lovelace ) {
		return new WP_Error( 'valt_anvil_verify', 'Built transaction does not pay the song price.' );
	}
	foreach ( $assets as $an => $q ) {
		if ( ( $delivered[ $an ] ?? 0 ) < $q ) {
			return new WP_Error( 'valt_anvil_verify', 'Built transaction does not deliver the editions to the buyer.' );
		}
	}
	return true;
}

// ─── Addresses (bech32) ──────────────────────────────────────────────

function valt_bech32_polymod( array $values ): int {
	$gen = [ 0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3 ];
	$chk = 1;
	foreach ( $values as $v ) {
		$top = $chk >> 25;
		$chk = ( ( $chk & 0x1ffffff ) << 5 ) ^ $v;
		for ( $i = 0; $i < 5; $i++ ) {
			if ( ( $top >> $i ) & 1 ) $chk ^= $gen[ $i ];
		}
	}
	return $chk;
}

function valt_bech32_hrp_expand( string $hrp ): array {
	$hi = [];
	$lo = [];
	foreach ( str_split( $hrp ) as $ch ) {
		$hi[] = ord( $ch ) >> 5;
		$lo[] = ord( $ch ) & 31;
	}
	return array_merge( $hi, [ 0 ], $lo );
}

function valt_bech32_regroup( array $data, int $from, int $to, bool $pad ): ?array {
	$acc = 0; $bits = 0; $out = []; $max = ( 1 << $to ) - 1;
	foreach ( $data as $v ) {
		$acc   = ( $acc << $from ) | $v;
		$bits += $from;
		while ( $bits >= $to ) {
			$bits -= $to;
			$out[] = ( $acc >> $bits ) & $max;
		}
		$acc &= ( 1 << $bits ) - 1;
	}
	if ( $pad && $bits > 0 ) {
		$out[] = ( $acc << ( $to - $bits ) ) & $max;
	} elseif ( ! $pad && ( $bits >= $from || ( ( $acc << ( $to - $bits ) ) & $max ) ) ) {
		return null;
	}
	return $out;
}

function valt_bech32_encode( string $hrp, string $bin ): string {
	$charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
	$data    = valt_bech32_regroup( array_values( unpack( 'C*', $bin ) ?: [] ), 8, 5, true );
	$pm      = valt_bech32_polymod( array_merge( valt_bech32_hrp_expand( $hrp ), $data, [ 0, 0, 0, 0, 0, 0 ] ) ) ^ 1;
	$out     = $hrp . '1';
	foreach ( $data as $d ) $out .= $charset[ $d ];
	for ( $i = 0; $i < 6; $i++ ) $out .= $charset[ ( $pm >> ( 5 * ( 5 - $i ) ) ) & 31 ];
	return $out;
}

/** @return array{0:string,1:string}|null [hrp, bytes] */
function valt_bech32_decode( string $str ): ?array {
	$str = strtolower( trim( $str ) );
	$pos = strrpos( $str, '1' );
	if ( $pos === false || $pos < 1 || strlen( $str ) - $pos < 7 ) return null;
	$hrp  = substr( $str, 0, $pos );
	$data = [];
	for ( $i = $pos + 1; $i < strlen( $str ); $i++ ) {
		$v = strpos( 'qpzry9x8gf2tvdw0s3jn54khce6mua7l', $str[ $i ] );
		if ( $v === false ) return null;
		$data[] = $v;
	}
	if ( valt_bech32_polymod( array_merge( valt_bech32_hrp_expand( $hrp ), $data ) ) !== 1 ) return null;
	$bytes = valt_bech32_regroup( array_slice( $data, 0, -6 ), 5, 8, false );
	if ( $bytes === null ) return null;
	return [ $hrp, pack( 'C*', ...$bytes ) ];
}

/**
 * Normalise a wallet address (CIP-30 hex bytes or bech32) to [bech32, hex] for this network.
 *
 * @return array{0:string,1:string}|WP_Error
 */
function valt_anvil_address( string $addr ) {
	$addr = trim( $addr );
	$want = valt_anvil_config()['mode'] === 'mainnet' ? 1 : 0;
	if ( ctype_xdigit( $addr ) && strlen( $addr ) >= 58 && strlen( $addr ) <= 228 ) {
		$bin = hex2bin( $addr );
	} else {
		$dec = valt_bech32_decode( $addr );
		$bin = $dec && in_array( $dec[0], [ 'addr', 'addr_test' ], true ) ? $dec[1] : '';
	}
	if ( $bin === '' || $bin === false ) return new WP_Error( 'valt_anvil_address', 'Wallet address not recognised.', [ 'status' => 400 ] );
	$type = ord( $bin[0] ) >> 4;
	if ( $type > 7 || ( ord( $bin[0] ) & 15 ) !== $want ) {
		return new WP_Error( 'valt_anvil_address', $want ? 'Switch your wallet to Cardano mainnet.' : 'Switch your wallet to the Preprod testnet.', [ 'status' => 400 ] );
	}
	return [ valt_bech32_encode( $want ? 'addr' : 'addr_test', $bin ), bin2hex( $bin ) ];
}

// ─── Anvil API ───────────────────────────────────────────────────────

function valt_anvil_request( string $method, string $path, ?array $body = null ) {
	$c    = valt_anvil_config();
	$args = [
		'method'  => $method,
		'timeout' => 45,
		'headers' => [ 'X-Api-Key' => $c['api_key'], 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
	];
	if ( $body !== null ) $args['body'] = wp_json_encode( $body );
	$res = wp_remote_request( $c['base'] . $path, $args );
	if ( is_wp_error( $res ) ) return $res;
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( $code < 200 || $code >= 300 ) {
		$msg = is_array( $data ) ? (string) ( $data['message'] ?? $data['error'] ?? '' ) : '';
		return new WP_Error( 'valt_anvil_http', 'Anvil ' . $code . ( $msg !== '' ? ': ' . $msg : '' ), [ 'status' => 502, 'anvil' => $data ] );
	}
	return is_array( $data ) ? $data : [];
}

// ─── Edition ledger ──────────────────────────────────────────────────

/** Anvil series cap for a song: its valt_anvil_cap, never above the song's max supply, never above 99. */
function valt_anvil_cap( int $song_id ): int {
	$cap = (int) get_post_meta( $song_id, 'valt_anvil_cap', true );
	$max = (int) get_post_meta( $song_id, 'valt_nft_max_supply', true );
	return max( 0, min( $cap, $max > 0 ? $max : 0, 99 ) );
}

/** On-chain asset name for Anvil edition $n of a song, e.g. valtfreakshow262a01. */
function valt_anvil_asset_name( int $song_id, int $n ): string {
	return 'valt' . valt_generate_asset_name( get_the_title( $song_id ), $song_id ) . 'a' . sprintf( '%02d', $n );
}

function valt_anvil_ledger_get(): array {
	// Always read from the database: get_option() caches per request, and a stale copy written
	// back (e.g. releasing a failed build) would undo another worker's "sent" entry.
	wp_cache_delete( 'valt_anvil_ledger', 'options' );
	$l = get_option( 'valt_anvil_ledger', [] );
	return is_array( $l ) ? $l : [];
}

/**
 * Edition numbers of a song's Anvil series that already exist on-chain (Koios, cached 20s).
 * A second line of defence so an edition is never minted twice, whatever the ledger says.
 *
 * @return int[]|null Null when the chain can't be read.
 */
function valt_anvil_onchain_editions( int $song_id ): ?array {
	$ck = 'valt_anvil_oc_' . $song_id;
	$c  = get_transient( $ck );
	if ( is_array( $c ) ) return $c;
	$cfg  = valt_anvil_config();
	$base = $cfg['mode'] === 'mainnet' ? 'https://api.koios.rest/api/v1' : 'https://preprod.koios.rest/api/v1';
	$r    = wp_remote_get( $base . '/policy_asset_list?_asset_policy=' . rawurlencode( $cfg['policy_id'] ), [ 'timeout' => 10 ] );
	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) return null;
	$rows   = json_decode( wp_remote_retrieve_body( $r ), true );
	$prefix = substr( valt_anvil_asset_name( $song_id, 1 ), 0, -2 ); // "...a"
	$out    = [];
	foreach ( (array) $rows as $row ) {
		$name = (string) hex2bin( (string) ( $row['asset_name'] ?? '' ) );
		if ( strpos( $name, $prefix ) === 0 && ctype_digit( substr( $name, strlen( $prefix ) ) ) && (int) ( $row['total_supply'] ?? 1 ) > 0 ) {
			$out[] = (int) substr( $name, strlen( $prefix ) );
		}
	}
	set_transient( $ck, $out, 20 );
	return $out;
}

function valt_anvil_ledger_put( array $l ): void {
	update_option( 'valt_anvil_ledger', $l, false );
}

/** Serialise ledger writes across PHP workers. */
function valt_anvil_mutex( bool $acquire ): bool {
	global $wpdb;
	if ( $acquire ) return (int) $wpdb->get_var( "SELECT GET_LOCK('valt_anvil_ledger', 10)" ) === 1;
	$wpdb->query( "SELECT RELEASE_LOCK('valt_anvil_ledger')" );
	return true;
}

/** Drop expired unsigned reservations for a song (in place). */
function valt_anvil_prune( array &$l, int $song_id ): void {
	foreach ( ( $l[ $song_id ] ?? [] ) as $n => $e ) {
		if ( ( $e['s'] ?? '' ) === 'lock' && (int) ( $e['x'] ?? 0 ) < time() ) unset( $l[ $song_id ][ $n ] );
	}
}

/** Editions minted through Anvil (submitted on-chain) for a song. */
function valt_anvil_sold( int $song_id ): int {
	$n = 0;
	foreach ( ( valt_anvil_ledger_get()[ $song_id ] ?? [] ) as $e ) {
		if ( ( $e['s'] ?? '' ) === 'sent' ) $n++;
	}
	return $n;
}

/** Editions still collectible through Anvil, or null when the song isn't on Anvil checkout. */
function valt_anvil_available( int $song_id ): ?int {
	if ( ! valt_song_uses_anvil( $song_id ) ) return null;
	$l = valt_anvil_ledger_get();
	valt_anvil_prune( $l, $song_id );
	return max( 0, valt_anvil_cap( $song_id ) - count( $l[ $song_id ] ?? [] ) );
}

/** Release a build's reservations (e.g. the build or submit failed). */
function valt_anvil_release( int $song_id, string $build_id ): void {
	if ( ! valt_anvil_mutex( true ) ) return;
	$l = valt_anvil_ledger_get();
	foreach ( ( $l[ $song_id ] ?? [] ) as $n => $e ) {
		if ( ( $e['s'] ?? '' ) === 'lock' && ( $e['b'] ?? '' ) === $build_id ) unset( $l[ $song_id ][ $n ] );
	}
	valt_anvil_ledger_put( $l );
	valt_anvil_mutex( false );
}

// ─── Build + verify (shared by REST and WP-CLI dry run) ─────────────

/** CIP-25 metadata for one Anvil edition (same fields as the NMKR editions). */
function valt_anvil_edition_metadata( int $song_id ): array {
	$cid = (string) get_post_meta( $song_id, 'valt_nft_ipfs_hash', true );
	$img = (int) get_post_meta( $song_id, 'valt_nft_image_id', true ) ?: (int) get_post_thumbnail_id( $song_id );
	$mime = $img ? (string) get_post_mime_type( $img ) : '';
	$cip  = valt_build_cip25_metadata( $song_id, $cid, '', $mime ?: 'image/jpeg' );
	$byp  = reset( $cip['721'] );
	return is_array( $byp ) ? (array) reset( $byp ) : [];
}

/**
 * Ask Anvil for the collect transaction and verify it.
 *
 * @param int[] $editions Edition numbers.
 * @return array{tx:string,hash:string,lovelace:int}|WP_Error
 */
function valt_anvil_build_collect( int $song_id, array $editions, string $buyer_bech, string $buyer_hex, array $utxos, string $payout_bech ) {
	$c   = valt_anvil_config();
	$key = valt_anvil_policy_key();
	if ( is_wp_error( $key ) ) return $key;
	if ( ! get_post_meta( $song_id, 'valt_nft_ipfs_hash', true ) ) {
		return new WP_Error( 'valt_anvil_cid', 'This song has no cover on IPFS yet.', [ 'status' => 500 ] );
	}
	$price    = (float) get_post_meta( $song_id, 'valt_nft_price_ada', true );
	$lovelace = (int) round( $price * 1000000 ) * count( $editions );
	if ( $lovelace < 1000000 ) return new WP_Error( 'valt_anvil_price', 'This song has no price set.', [ 'status' => 500 ] );
	$payout = valt_bech32_decode( $payout_bech );
	$meta   = valt_anvil_edition_metadata( $song_id );

	$mint = [];
	$want = [];
	foreach ( $editions as $n ) {
		$name = valt_anvil_asset_name( $song_id, (int) $n );
		if ( strlen( $name ) > 32 ) return new WP_Error( 'valt_anvil_name', 'Asset name too long.', [ 'status' => 500 ] );
		$mint[] = [
			'version'     => 'cip25',
			'assetName'   => [ 'name' => $name, 'format' => 'utf8' ],
			// Per-edition display name ("London #3") so collectors can tell editions apart.
			'metadata'    => array_merge( $meta, [ 'name' => trim( (string) ( $meta['name'] ?? get_the_title( $song_id ) ) ) . ' #' . (int) $n ] ),
			'policyId'    => $c['policy_id'],
			'quantity'    => 1,
			'destAddress' => $buyer_bech,
		];
		$want[ bin2hex( $name ) ] = 1;
	}
	$req = [
		'changeAddress'    => $buyer_bech,
		'message'          => 'Valt collect: ' . html_entity_decode( get_the_title( $song_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		'outputs'          => [ [ 'address' => $payout_bech, 'lovelace' => $lovelace ] ],
		'mint'             => $mint,
		'preloadedScripts' => [ [
			'type'   => 'simple',
			'script' => [ 'type' => 'all', 'scripts' => [ [ 'type' => 'sig', 'keyHash' => $key['key_hash'] ] ] ],
			'hash'   => $c['policy_id'],
		] ],
		'requiredSigners'  => [ $key['key_hash'] ],
	];
	if ( $utxos ) $req['utxos'] = array_values( $utxos );
	$res = valt_anvil_request( 'POST', '/transactions/build', $req );
	if ( is_wp_error( $res ) ) return $res;

	$tx_hex = (string) ( $res['complete'] ?? '' );
	if ( $tx_hex === '' || ! ctype_xdigit( $tx_hex ) ) return new WP_Error( 'valt_anvil_build', 'Anvil returned no transaction.', [ 'status' => 502 ] );
	$tx_bin = hex2bin( $tx_hex );
	try {
		$hash = valt_anvil_tx_hash( $tx_bin );
	} catch ( RuntimeException $e ) {
		return new WP_Error( 'valt_anvil_build', 'Anvil returned an unreadable transaction.', [ 'status' => 502 ] );
	}
	if ( isset( $res['hash'] ) && strtolower( (string) $res['hash'] ) !== $hash ) {
		return new WP_Error( 'valt_anvil_build', 'Transaction id mismatch.', [ 'status' => 502 ] );
	}
	$ok = valt_anvil_verify_body( $tx_bin, $c['policy_id'], $want, bin2hex( $payout[1] ), $lovelace, $buyer_hex );
	if ( is_wp_error( $ok ) ) return $ok;
	return [ 'tx' => $tx_hex, 'hash' => $hash, 'lovelace' => $lovelace ];
}

// ─── REST ────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {
	register_rest_route( 'valt/v1', '/anvil/build', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // public checkout; guarded by collect token, rate limit, per-song opt-in
		'callback'            => 'valt_anvil_rest_build',
	] );
	register_rest_route( 'valt/v1', '/anvil/cancel', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // only frees editions held by an unsigned build the caller holds the id of
		'callback'            => 'valt_anvil_rest_cancel',
	] );
	register_rest_route( 'valt/v1', '/anvil/status', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true', // read-only: is a tx on-chain yet
		'callback'            => 'valt_anvil_rest_status',
	] );
	register_rest_route( 'valt/v1', '/anvil/submit', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // only co-signs a transaction this site built and stored
		'callback'            => 'valt_anvil_rest_submit',
	] );
} );

function valt_anvil_rest_build( WP_REST_Request $req ) {
	if ( ! function_exists( 'valt_collect_token_ok' ) || ! valt_collect_token_ok( (string) $req['nonce'] ) ) {
		return new WP_Error( 'valt_bad_nonce', 'Please refresh the page and try again.', [ 'status' => 403 ] );
	}
	$sid = (int) $req['song_id'];
	$qty = (int) $req['qty'];
	if ( get_post_type( $sid ) !== 'song' || get_post_status( $sid ) !== 'publish' || ! valt_song_uses_anvil( $sid ) ) {
		return new WP_Error( 'valt_bad_song', 'This song is not available to collect here.', [ 'status' => 404 ] );
	}
	$ready = valt_anvil_ready();
	if ( is_wp_error( $ready ) ) {
		valt_log_event( 'anvil_error', 'Anvil checkout not ready: ' . $ready->get_error_message() );
		return new WP_Error( 'valt_anvil_unavailable', 'Collecting is paused for a moment. Please try again later.', [ 'status' => 503 ] );
	}
	$max_qty = defined( 'VALT_COLLECT_MAX_QTY' ) ? VALT_COLLECT_MAX_QTY : 5;
	if ( $qty < 1 || $qty > $max_qty ) {
		return new WP_Error( 'valt_bad_qty', 'Choose between 1 and ' . $max_qty . ' editions.', [ 'status' => 400 ] );
	}
	$addr = valt_anvil_address( (string) $req['address'] );
	if ( is_wp_error( $addr ) ) return $addr;
	$utxos = $req['utxos'];
	$utxos = is_array( $utxos ) ? array_values( array_filter( $utxos, function ( $u ) {
		return is_string( $u ) && $u !== '' && strlen( $u ) < 20000 && ctype_xdigit( $u );
	} ) ) : [];
	if ( count( $utxos ) > 300 ) $utxos = array_slice( $utxos, 0, 300 );

	// Rate limit per IP (each build holds editions for VALT_ANVIL_LOCK_TTL).
	$ip   = function_exists( 'valt_collect_client_ip' ) ? valt_collect_client_ip() : '0.0.0.0';
	$rk   = 'valt_anvil_rl_' . md5( $ip );
	$hits = (int) get_transient( $rk );
	if ( $hits >= 8 ) return new WP_Error( 'valt_rate_limited', 'Too many attempts. Please wait a few minutes.', [ 'status' => 429 ] );
	set_transient( $rk, $hits + 1, 10 * MINUTE_IN_SECONDS );

	// Editions already on-chain are never offered again, even if the ledger lost track of them.
	$onchain = valt_anvil_onchain_editions( $sid );
	if ( $onchain === null ) {
		return new WP_Error( 'valt_anvil_unavailable', 'Collecting is paused for a moment. Please try again later.', [ 'status' => 503 ] );
	}

	// Reserve editions.
	if ( ! valt_anvil_mutex( true ) ) return new WP_Error( 'valt_busy', 'Busy, please try again.', [ 'status' => 503 ] );
	$l = valt_anvil_ledger_get();
	valt_anvil_prune( $l, $sid );
	$cap  = valt_anvil_cap( $sid );
	$free = [];
	for ( $n = 1; $n <= $cap && count( $free ) < $qty; $n++ ) {
		if ( ! isset( $l[ $sid ][ $n ] ) && ! in_array( $n, $onchain, true ) ) $free[] = $n;
	}
	if ( count( $free ) < $qty ) {
		valt_anvil_ledger_put( $l );
		valt_anvil_mutex( false );
		$left = $cap - count( $l[ $sid ] ?? [] );
		return new WP_Error( 'valt_not_enough', $left > 0 ? sprintf( 'Only %d edition%s left.', $left, $left === 1 ? '' : 's' ) : 'Sold out.', [ 'status' => 409, 'available' => max( 0, $left ) ] );
	}
	$build_id = bin2hex( random_bytes( 12 ) );
	foreach ( $free as $n ) {
		$l[ $sid ][ $n ] = [ 's' => 'lock', 'b' => $build_id, 'x' => time() + VALT_ANVIL_LOCK_TTL ];
	}
	valt_anvil_ledger_put( $l );
	valt_anvil_mutex( false );

	$built = valt_anvil_build_collect( $sid, $free, $addr[0], $addr[1], $utxos, valt_anvil_config()['payout'] );
	if ( is_wp_error( $built ) ) {
		valt_anvil_release( $sid, $build_id );
		valt_log_event( 'anvil_error', 'Anvil build failed for song ' . $sid, [ 'error' => $built->get_error_message() ] );
		$m = $built->get_error_message();
		// Anvil's coin-selection errors mean the wallet can't cover price + fees.
		$friendly = stripos( $m, 'insufficient' ) !== false || stripos( $m, 'balance' ) !== false || stripos( $m, 'utxo' ) !== false
			? 'Your wallet doesn\'t have enough test ADA for this (the price plus about 2.5 ADA per edition for fees and the token deposit).'
			: 'The transaction could not be prepared. Please try again.';
		return new WP_Error( 'valt_anvil_build', $friendly, [ 'status' => 502 ] );
	}
	set_transient( 'valt_anvil_b_' . $build_id, [
		'song'     => $sid,
		'editions' => $free,
		'tx'       => $built['tx'],
		'hash'     => $built['hash'],
		'buyer'    => $addr[0],
	], VALT_ANVIL_LOCK_TTL );

	return rest_ensure_response( [ 'build_id' => $build_id, 'tx' => $built['tx'], 'hash' => $built['hash'], 'editions' => count( $free ) ] );
}

/**
 * Has a collect transaction reached the chain? Polled by the checkout's pending panel so
 * "Open the Valt" only appears once the edition is really in the wallet. Cached briefly.
 */
function valt_anvil_rest_status( WP_REST_Request $req ) {
	$tx = strtolower( (string) $req['tx'] );
	if ( ! preg_match( '/^[0-9a-f]{64}$/', $tx ) ) return new WP_Error( 'valt_bad_tx', 'Unknown transaction.', [ 'status' => 400 ] );
	$ck = 'valt_anvil_st_' . substr( $tx, 0, 40 );
	$n  = get_transient( $ck );
	if ( $n === false ) {
		$base = valt_anvil_config()['mode'] === 'mainnet' ? 'https://api.koios.rest/api/v1' : 'https://preprod.koios.rest/api/v1';
		$r    = wp_remote_post( $base . '/tx_status', [
			'timeout' => 8,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [ '_tx_hashes' => [ $tx ] ] ),
		] );
		$d = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
		$n = (int) ( $d[0]['num_confirmations'] ?? 0 );
		set_transient( $ck, $n, $n > 0 ? HOUR_IN_SECONDS : 5 );
	}
	return rest_ensure_response( [ 'confirmed' => (int) $n > 0, 'confirmations' => (int) $n ] );
}

/** The wallet declined to sign: drop the build and free its editions straight away. */
function valt_anvil_rest_cancel( WP_REST_Request $req ) {
	if ( ! function_exists( 'valt_collect_token_ok' ) || ! valt_collect_token_ok( (string) $req['nonce'] ) ) {
		return new WP_Error( 'valt_bad_nonce', 'Please refresh the page and try again.', [ 'status' => 403 ] );
	}
	$build_id = (string) $req['build_id'];
	if ( ! preg_match( '/^[0-9a-f]{24}$/', $build_id ) ) return rest_ensure_response( [ 'ok' => true ] );
	if ( ! valt_anvil_mutex( true ) ) return new WP_Error( 'valt_busy', 'Busy.', [ 'status' => 503 ] );
	$b = get_transient( 'valt_anvil_b_' . $build_id );
	if ( is_array( $b ) ) delete_transient( 'valt_anvil_b_' . $build_id );
	valt_anvil_mutex( false );
	if ( is_array( $b ) ) valt_anvil_release( (int) $b['song'], $build_id );
	return rest_ensure_response( [ 'ok' => true ] );
}

function valt_anvil_rest_submit( WP_REST_Request $req ) {
	if ( ! function_exists( 'valt_collect_token_ok' ) || ! valt_collect_token_ok( (string) $req['nonce'] ) ) {
		return new WP_Error( 'valt_bad_nonce', 'Please refresh the page and try again.', [ 'status' => 403 ] );
	}
	$build_id = (string) $req['build_id'];
	if ( ! preg_match( '/^[0-9a-f]{24}$/', $build_id ) ) return new WP_Error( 'valt_anvil_build', 'Unknown checkout.', [ 'status' => 400 ] );

	if ( ! valt_anvil_mutex( true ) ) return new WP_Error( 'valt_busy', 'Busy, please try again.', [ 'status' => 503 ] );
	$b = get_transient( 'valt_anvil_b_' . $build_id );
	if ( ! is_array( $b ) ) {
		valt_anvil_mutex( false );
		return new WP_Error( 'valt_anvil_expired', 'This checkout expired. Please start again.', [ 'status' => 410 ] );
	}
	delete_transient( 'valt_anvil_b_' . $build_id ); // one submit per build
	valt_anvil_mutex( false );

	$sid    = (int) $b['song'];
	$wallet = valt_anvil_wallet_witnesses( strtolower( (string) $req['witness'] ), $b['hash'] );
	if ( is_wp_error( $wallet ) ) {
		valt_anvil_release( $sid, $build_id );
		return $wallet;
	}
	$key = valt_anvil_policy_key();
	if ( is_wp_error( $key ) ) {
		valt_anvil_release( $sid, $build_id );
		valt_log_event( 'anvil_error', 'Policy key unavailable at submit', [ 'error' => $key->get_error_message() ] );
		return new WP_Error( 'valt_anvil_unavailable', 'Collecting is paused for a moment. Please try again later.', [ 'status' => 503 ] );
	}
	$policy_w = valt_anvil_vkey_witness( $b['hash'], $key['sk'], $key['pk'] );
	sodium_memzero( $key['sk'] );
	try {
		$signed = valt_anvil_add_witnesses( hex2bin( $b['tx'] ), array_merge( $wallet, [ $policy_w ] ) );
	} catch ( RuntimeException $e ) {
		valt_anvil_release( $sid, $build_id );
		return new WP_Error( 'valt_anvil_submit', 'The transaction could not be signed.', [ 'status' => 500 ] );
	}

	$res = valt_anvil_request( 'POST', '/transactions/submit', [ 'transaction' => bin2hex( $signed ) ] );
	if ( is_wp_error( $res ) ) {
		valt_anvil_release( $sid, $build_id );
		valt_log_event( 'anvil_error', 'Anvil submit failed for song ' . $sid, [ 'error' => $res->get_error_message() ] );
		return new WP_Error( 'valt_anvil_submit', 'The network did not accept the transaction. Nothing was charged; please try again.', [ 'status' => 502 ] );
	}
	$tx_hash = strtolower( (string) ( $res['txHash'] ?? $b['hash'] ) );

	valt_anvil_mutex( true );
	$l = valt_anvil_ledger_get();
	foreach ( $b['editions'] as $n ) {
		$l[ $sid ][ (int) $n ] = [ 's' => 'sent', 'tx' => $tx_hash, 'to' => $b['buyer'], 'at' => time() ];
	}
	valt_anvil_ledger_put( $l );
	valt_anvil_mutex( false );
	update_post_meta( $sid, 'valt_anvil_last_tx', $tx_hash );
	valt_log_event( 'anvil_mint', 'Anvil collect submitted for song ' . $sid, [ 'tx' => $tx_hash, 'editions' => $b['editions'] ] );

	$aid = function_exists( 'valt_resolve_artist_id' ) ? valt_resolve_artist_id( $sid ) : 0;
	return rest_ensure_response( [
		'tx_hash'  => $tx_hash,
		'explorer' => valt_anvil_config()['explorer'] . $tx_hash,
		'valt_url' => $aid ? add_query_arg( 'collected', '1', get_permalink( $aid ) ) : '',
		'editions' => count( $b['editions'] ),
	] );
}

// ─── WP-CLI: enable/disable, dry run, burn ──────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Anvil checkout admin.
	 */
	class Valt_Anvil_CLI {

		/**
		 * Show Anvil readiness and every song's ledger.
		 *
		 * @subcommand status
		 */
		public function status( $args, $assoc ) {
			$r = valt_anvil_ready();
			WP_CLI::log( 'ready: ' . ( is_wp_error( $r ) ? 'NO (' . $r->get_error_message() . ')' : 'yes' ) );
			$k = valt_anvil_policy_key();
			WP_CLI::log( 'policy key: ' . ( is_wp_error( $k ) ? 'NO (' . $k->get_error_message() . ')' : 'ok, matches ' . valt_anvil_config()['policy_id'] ) );
			foreach ( get_posts( [ 'post_type' => 'song', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ] ) as $sid ) {
				$cap = valt_anvil_cap( $sid );
				if ( ! valt_song_uses_anvil( $sid ) && ! $cap ) continue;
				WP_CLI::log( sprintf( '%d %s: checkout=%s cap=%d sold=%d available=%s', $sid, get_the_title( $sid ), get_post_meta( $sid, 'valt_checkout', true ) ?: 'nmkr', $cap, valt_anvil_sold( $sid ), var_export( valt_anvil_available( $sid ), true ) ) );
			}
		}

		/**
		 * Switch a song to Anvil checkout.
		 *
		 * <song_id>
		 * --cap=<n>
		 * [--accept-overlap] : allow cap + NMKR editions to exceed max supply (preprod only).
		 * [--preview] : local sites only; switch the UI over without an Anvil key or payout
		 *   address, for testing the page. Collecting then fails with a clear "not configured" error.
		 *
		 * @subcommand enable
		 */
		public function enable( $args, $assoc ) {
			$sid = (int) $args[0];
			$cap = (int) ( $assoc['cap'] ?? 0 );
			if ( get_post_type( $sid ) !== 'song' ) WP_CLI::error( 'Not a song.' );
			if ( valt_anvil_config()['mode'] !== 'preprod' ) WP_CLI::error( 'Anvil checkout is preprod only.' );
			$max = (int) get_post_meta( $sid, 'valt_nft_max_supply', true );
			if ( $cap < 1 || $cap > $max || $cap > 99 ) WP_CLI::error( "cap must be 1..min(max supply {$max}, 99)." );
			$nmkr = self::nmkr_editions( $sid );
			WP_CLI::log( "NMKR editions of this song: {$nmkr}; max supply {$max}; Anvil cap {$cap}." );
			if ( $nmkr + $cap > $max && empty( $assoc['accept-overlap'] ) ) {
				WP_CLI::error( 'NMKR editions + Anvil cap exceed max supply. Re-run with --accept-overlap only if that is agreed.' );
			}
			$r = valt_anvil_ready();
			if ( is_wp_error( $r ) ) {
				if ( empty( $assoc['preview'] ) || wp_get_environment_type() !== 'local' ) {
					WP_CLI::error( 'Not ready: ' . $r->get_error_message() . ( wp_get_environment_type() === 'local' ? ' (Add --preview to test the page without it.)' : '' ) );
				}
				WP_CLI::warning( 'Preview only: ' . $r->get_error_message() . ' Collecting will fail until it is configured.' );
			}
			update_post_meta( $sid, 'valt_anvil_cap', $cap );
			update_post_meta( $sid, 'valt_checkout', 'anvil' );
			delete_transient( 'valt_song_inventory' );
			WP_CLI::success( get_the_title( $sid ) . ' now collects through Anvil (' . valt_anvil_asset_name( $sid, 1 ) . ' .. a' . sprintf( '%02d', $cap ) . ').' );
		}

		/**
		 * Mark every edition of a song that exists on-chain as sent in the ledger (repairs a
		 * ledger that lost a "sent" entry). Never frees anything.
		 *
		 * <song_id>
		 *
		 * @subcommand reconcile
		 */
		public function reconcile( $args, $assoc ) {
			$sid = (int) $args[0];
			delete_transient( 'valt_anvil_oc_' . $sid );
			$oc = valt_anvil_onchain_editions( $sid );
			if ( $oc === null ) WP_CLI::error( 'Could not read the chain.' );
			if ( ! valt_anvil_mutex( true ) ) WP_CLI::error( 'Ledger busy, try again.' );
			$l     = valt_anvil_ledger_get();
			$fixed = [];
			foreach ( $oc as $n ) {
				if ( ( $l[ $sid ][ $n ]['s'] ?? '' ) !== 'sent' ) {
					$l[ $sid ][ $n ] = [ 's' => 'sent', 'tx' => '', 'to' => '', 'at' => time(), 'note' => 'reconciled from chain' ];
					$fixed[]         = $n;
				}
			}
			if ( $fixed ) valt_anvil_ledger_put( $l );
			valt_anvil_mutex( false );
			WP_CLI::success( count( $oc ) . ' editions on-chain; marked sent: ' . ( $fixed ? implode( ', ', array_map( function ( $n ) { return 'a' . sprintf( '%02d', $n ); }, $fixed ) ) : 'none needed' ) . '.' );
		}

		/**
		 * Switch a song back to NMKR checkout (the Anvil ledger is kept).
		 *
		 * <song_id>
		 *
		 * @subcommand disable
		 */
		public function disable( $args, $assoc ) {
			delete_post_meta( (int) $args[0], 'valt_checkout' );
			WP_CLI::success( 'Back on NMKR checkout.' );
		}

		/**
		 * Build (never submit) a collect transaction and co-sign it locally to prove the policy witness.
		 *
		 * <song_id>
		 * --buyer=<address>
		 * [--payout=<address>]
		 * [--qty=<n>]
		 *
		 * @subcommand dryrun
		 */
		public function dryrun( $args, $assoc ) {
			$sid   = (int) $args[0];
			$qty   = max( 1, (int) ( $assoc['qty'] ?? 1 ) );
			$c     = valt_anvil_config();
			$buyer = valt_anvil_address( (string) ( $assoc['buyer'] ?? '' ) );
			if ( is_wp_error( $buyer ) ) WP_CLI::error( $buyer->get_error_message() );
			$payout = (string) ( $assoc['payout'] ?? $c['payout'] );
			if ( ! valt_bech32_decode( $payout ) ) WP_CLI::error( 'Need --payout (no VALT_ANVIL_PAYOUT_ADDRESS configured).' );
			// Use edition numbers past the cap so the dry run can never look like a real reservation.
			$eds   = range( 90, 89 + $qty );
			$built = valt_anvil_build_collect( $sid, $eds, $buyer[0], $buyer[1], [], $payout );
			if ( is_wp_error( $built ) ) WP_CLI::error( $built->get_error_message() . ' ' . wp_json_encode( $built->get_error_data()['anvil'] ?? '' ) );
			WP_CLI::log( 'built + verified: tx ' . $built['hash'] . ', ' . ( strlen( $built['tx'] ) / 2 ) . ' bytes, pays ' . ( $built['lovelace'] / 1000000 ) . ' ADA' );
			$key    = valt_anvil_policy_key();
			if ( is_wp_error( $key ) ) WP_CLI::error( $key->get_error_message() );
			$signed = valt_anvil_add_witnesses( hex2bin( $built['tx'] ), [ valt_anvil_vkey_witness( $built['hash'], $key['sk'], $key['pk'] ) ] );
			sodium_memzero( $key['sk'] );
			$same   = valt_anvil_tx_hash( $signed ) === $built['hash'];
			$ws     = valt_cbor_raw_map( valt_cbor_raw_items( $signed )[1] );
			$ok     = false;
			foreach ( valt_cbor_vkey_items( $ws[0] ?? '' ) as $w ) {
				$ok = $ok || sodium_crypto_sign_verify_detached( substr( $w, 37, 64 ), hex2bin( $built['hash'] ), substr( $w, 3, 32 ) );
			}
			WP_CLI::log( 'policy witness attached: ' . ( $ok ? 'yes, signature verifies' : 'NO' ) . '; tx id unchanged by signing: ' . ( $same ? 'yes' : 'NO' ) . '; witness-set keys: ' . implode( ',', array_keys( $ws ) ) );
			WP_CLI::success( 'Dry run only. Nothing was submitted and the ledger was not touched.' );
		}

		/**
		 * Build a burn of specific tokens held at an address (Ian signs with the holding wallet).
		 * Writes a JSON file for `burn-submit`.
		 *
		 * --assets=<names> : comma list of on-chain asset names, e.g. valtlondon300e01,valtlondon300e02
		 * --from=<address>  : address holding them (change returns here)
		 * --out=<file>
		 *
		 * @subcommand burn-build
		 */
		public function burn_build( $args, $assoc ) {
			$c = valt_anvil_config();
			if ( $c['mode'] !== 'preprod' ) WP_CLI::error( 'Preprod only.' );
			$from = valt_anvil_address( (string) ( $assoc['from'] ?? '' ) );
			if ( is_wp_error( $from ) ) WP_CLI::error( $from->get_error_message() );
			$key = valt_anvil_policy_key();
			if ( is_wp_error( $key ) ) WP_CLI::error( $key->get_error_message() );
			$names = array_filter( array_map( 'trim', explode( ',', (string) ( $assoc['assets'] ?? '' ) ) ) );
			if ( ! $names ) WP_CLI::error( 'No --assets.' );
			$mint = [];
			$want = [];
			foreach ( $names as $nm ) {
				if ( ! preg_match( '/^[a-z0-9]{1,32}$/', $nm ) ) WP_CLI::error( "Bad asset name {$nm}" );
				$mint[] = [ 'version' => 'cip25', 'assetName' => [ 'name' => $nm, 'format' => 'utf8' ], 'policyId' => $c['policy_id'], 'quantity' => -1 ];
				$want[ bin2hex( $nm ) ] = -1;
			}
			$res = valt_anvil_request( 'POST', '/transactions/build', [
				'changeAddress'    => $from[0],
				'mint'             => $mint,
				'preloadedScripts' => [ [ 'type' => 'simple', 'script' => [ 'type' => 'all', 'scripts' => [ [ 'type' => 'sig', 'keyHash' => $key['key_hash'] ] ] ], 'hash' => $c['policy_id'] ] ],
				'requiredSigners'  => [ $key['key_hash'] ],
			] );
			sodium_memzero( $key['sk'] );
			if ( is_wp_error( $res ) ) WP_CLI::error( $res->get_error_message() );
			$tx   = hex2bin( (string) $res['complete'] );
			$ok   = valt_anvil_verify_body( $tx, $c['policy_id'], $want );
			if ( is_wp_error( $ok ) ) WP_CLI::error( $ok->get_error_message() );
			$out  = (string) ( $assoc['out'] ?? 'valt-burn.json' );
			file_put_contents( $out, wp_json_encode( [ 'tx' => $res['complete'], 'hash' => valt_anvil_tx_hash( $tx ), 'assets' => $names ], JSON_PRETTY_PRINT ) );
			WP_CLI::success( "Burn built for " . count( $names ) . " token(s): {$out}. Sign the 'tx' in the holding wallet (signTx(tx, true)), then run burn-submit." );
		}

		/**
		 * Add the policy witness to a burn built by burn-build plus the wallet's witness set, and submit.
		 *
		 * --file=<file>
		 * --witness=<hex> : witness set returned by the wallet's signTx(tx, true)
		 *
		 * @subcommand burn-submit
		 */
		public function burn_submit( $args, $assoc ) {
			$b = json_decode( (string) @file_get_contents( (string) ( $assoc['file'] ?? '' ) ), true );
			if ( ! is_array( $b ) || empty( $b['tx'] ) ) WP_CLI::error( 'Bad --file.' );
			$wallet = valt_anvil_wallet_witnesses( strtolower( (string) ( $assoc['witness'] ?? '' ) ), $b['hash'] );
			if ( is_wp_error( $wallet ) ) WP_CLI::error( $wallet->get_error_message() );
			$key = valt_anvil_policy_key();
			if ( is_wp_error( $key ) ) WP_CLI::error( $key->get_error_message() );
			$signed = valt_anvil_add_witnesses( hex2bin( $b['tx'] ), array_merge( $wallet, [ valt_anvil_vkey_witness( $b['hash'], $key['sk'], $key['pk'] ) ] ) );
			sodium_memzero( $key['sk'] );
			$res = valt_anvil_request( 'POST', '/transactions/submit', [ 'transaction' => bin2hex( $signed ) ] );
			if ( is_wp_error( $res ) ) WP_CLI::error( $res->get_error_message() );
			WP_CLI::success( 'Burn submitted: ' . ( $res['txHash'] ?? $b['hash'] ) );
		}

		private static function nmkr_editions( int $sid ): int {
			$c    = valt_nmkr_config();
			$base = strtolower( valt_generate_asset_name( get_the_title( $sid ), $sid ) );
			$n    = 0;
			for ( $p = 1; $p <= 40; $p++ ) {
				$b = valt_nmkr_request( 'GET', "GetNfts/{$c['project_uid']}/all/50/{$p}" );
				if ( is_wp_error( $b ) || ! is_array( $b ) ) break;
				foreach ( $b as $x ) {
					if ( strpos( strtolower( (string) ( $x['name'] ?? '' ) ), $base ) === 0 ) $n++;
				}
				if ( count( $b ) < 50 ) break;
			}
			return $n;
		}
	}
	WP_CLI::add_command( 'valt anvil', 'Valt_Anvil_CLI' );
}
