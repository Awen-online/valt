<?php
/**
 * Modal trigger button — Valt styled with wallet icon.
 */
if (empty($text)) {
	$text = 'Connect Wallet';
}
?>

<button type="button" class="valt-btn valt-btn--primary valt-connect-trigger" x-on:click="showModal = true">
	<?php echo valt_svg_wallet( 18 ); ?>
	<?php echo esc_html($text); ?>
</button>
