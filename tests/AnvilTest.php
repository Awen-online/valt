<?php
/**
 * Anvil checkout: the pieces that decide what the policy key signs.
 *
 * The policy key must only co-sign a transaction that mints exactly the reserved editions to the
 * buyer and pays the payout address, and adding witnesses must never change the transaction id.
 */

declare( strict_types=1 );

namespace Valt\Tests;

use PHPUnit\Framework\TestCase;

final class AnvilTest extends TestCase {

	private const POLICY   = 'bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a';
	private const KEY_HASH = '7486c7912d8b3b58d485fdd61b5e0645949ad1a393366df4fe35b4f5';
	private const ADDR     = 'addr_test1vpwp925m6kflmaflqur6dgq0q2zql6depfyxqmnw02c63qg60yueh';

	public static function setUpBeforeClass(): void {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			self::markTestSkipped( 'sodium extension not loaded' );
		}
		require_once dirname( __DIR__ ) . '/tests/wp-stubs.php';
		require_once dirname( __DIR__ ) . '/code/valt-platform/includes/anvil.php';
	}

	private static function bytes( string $bin ): string {
		return \valt_cbor_encode_head( 2, strlen( $bin ) ) . $bin;
	}

	private static function uint( int $n ): string {
		return \valt_cbor_encode_head( 0, $n );
	}

	/** tx = [ body, {}, true, null ] minting $mintQty of one asset, paying $pay to $payout, delivering to $buyer. */
	private static function tx( string $payout, string $buyer, string $asset, int $mintQty, int $pay ): string {
		$policy = hex2bin( self::POLICY );
		$ma     = "\xa1" . self::bytes( $policy ) . "\xa1" . self::bytes( $asset ) . self::uint( 1 );
		$outs   = "\x82"
			. "\x82" . self::bytes( $payout ) . self::uint( $pay )
			. "\x82" . self::bytes( $buyer ) . "\x82" . self::uint( 2000000 ) . $ma;
		$mint   = "\xa1" . self::bytes( $policy ) . "\xa1" . self::bytes( $asset ) . self::uint( $mintQty );
		$body   = "\xa4" . self::uint( 0 ) . "\x80" . self::uint( 1 ) . $outs . self::uint( 2 ) . self::uint( 180000 ) . self::uint( 9 ) . $mint;
		return "\x84" . $body . "\xa0\xf5\xf6";
	}

	public function test_policy_key_hash_derives_the_nmkr_policy_id(): void {
		$this->assertSame( self::POLICY, \valt_anvil_script_hash( hex2bin( self::KEY_HASH ) ) );
	}

	public function test_bech32_round_trip_and_network_check(): void {
		$dec = \valt_bech32_decode( self::ADDR );
		$this->assertSame( 'addr_test', $dec[0] );
		$this->assertSame( self::ADDR, \valt_bech32_encode( 'addr_test', $dec[1] ) );
		$this->assertNull( \valt_bech32_decode( substr( self::ADDR, 0, -1 ) . 'q' ) );

		$fromHex = \valt_anvil_address( bin2hex( $dec[1] ) );
		$this->assertSame( self::ADDR, $fromHex[0] );
		$mainnet = chr( ( ord( $dec[1][0] ) & 0xf0 ) | 1 ) . substr( $dec[1], 1 );
		$this->assertInstanceOf( \WP_Error::class, \valt_anvil_address( bin2hex( $mainnet ) ) );
	}

	public function test_verify_body_accepts_exact_mint_and_rejects_anything_else(): void {
		$payout = \valt_bech32_decode( self::ADDR )[1];
		$buyer  = "\x60" . str_repeat( "\x11", 28 );
		$asset  = 'valtfreakshow262a01';
		$want   = [ bin2hex( $asset ) => 1 ];

		$ok = \valt_anvil_verify_body( self::tx( $payout, $buyer, $asset, 1, 20000000 ), self::POLICY, $want, bin2hex( $payout ), 20000000, bin2hex( $buyer ) );
		$this->assertTrue( $ok );

		$extra = \valt_anvil_verify_body( self::tx( $payout, $buyer, $asset, 2, 20000000 ), self::POLICY, $want, bin2hex( $payout ), 20000000, bin2hex( $buyer ) );
		$this->assertInstanceOf( \WP_Error::class, $extra, 'minting 2 when 1 was reserved must be refused' );

		$underpaid = \valt_anvil_verify_body( self::tx( $payout, $buyer, $asset, 1, 5000000 ), self::POLICY, $want, bin2hex( $payout ), 20000000, bin2hex( $buyer ) );
		$this->assertInstanceOf( \WP_Error::class, $underpaid );

		$wrongDest = \valt_anvil_verify_body( self::tx( $payout, $buyer, $asset, 1, 20000000 ), self::POLICY, $want, bin2hex( $payout ), 20000000, bin2hex( $payout ) );
		$this->assertInstanceOf( \WP_Error::class, $wrongDest );
	}

	public function test_adding_witnesses_keeps_the_tx_id_and_signatures_verify(): void {
		$payout = \valt_bech32_decode( self::ADDR )[1];
		$tx     = self::tx( $payout, "\x60" . str_repeat( "\x22", 28 ), 'valtlondon300a01', 1, 20000000 );
		$hash   = \valt_anvil_tx_hash( $tx );

		$policy = sodium_crypto_sign_seed_keypair( random_bytes( 32 ) );
		$wallet = sodium_crypto_sign_seed_keypair( random_bytes( 32 ) );
		$pw     = \valt_anvil_vkey_witness( $hash, sodium_crypto_sign_secretkey( $policy ), sodium_crypto_sign_publickey( $policy ) );
		$ww     = \valt_anvil_vkey_witness( $hash, sodium_crypto_sign_secretkey( $wallet ), sodium_crypto_sign_publickey( $wallet ) );

		// What a CIP-30 wallet returns from signTx(tx, true): a witness set { 0: [ [vkey, sig] ] }.
		$walletSet = "\xa1\x00\x81" . $ww;
		$items     = \valt_anvil_wallet_witnesses( bin2hex( $walletSet ), $hash );
		$this->assertIsArray( $items );

		$signed = \valt_anvil_add_witnesses( $tx, array_merge( $items, [ $pw ] ) );
		$this->assertSame( $hash, \valt_anvil_tx_hash( $signed ) );
		$ws = \valt_cbor_raw_map( \valt_cbor_raw_items( $signed )[1] );
		$this->assertCount( 2, \valt_cbor_vkey_items( $ws[0] ) );
		foreach ( \valt_cbor_vkey_items( $ws[0] ) as $w ) {
			$this->assertTrue( sodium_crypto_sign_verify_detached( substr( $w, 37, 64 ), hex2bin( $hash ), substr( $w, 3, 32 ) ) );
		}

		// A witness over some other transaction is refused before anything is co-signed.
		$other = \valt_anvil_vkey_witness( str_repeat( 'ab', 32 ), sodium_crypto_sign_secretkey( $wallet ), sodium_crypto_sign_publickey( $wallet ) );
		$this->assertInstanceOf( \WP_Error::class, \valt_anvil_wallet_witnesses( bin2hex( "\xa1\x00\x81" . $other ), $hash ) );
	}
}
