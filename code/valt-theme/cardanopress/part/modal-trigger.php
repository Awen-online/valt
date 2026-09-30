<?php
/**
 * Modal trigger button — Valt styled with wallet icon.
 */
if (empty($text)) {
	$text = 'Connect Wallet';
}
// Optional style override, e.g. 'valt-btn--secondary' when a collect CTA is the primary action.
$variant = ! empty( $class ) ? $class : 'valt-btn--primary';
?>

<button type="button" class="valt-btn <?php echo esc_attr( $variant ); ?> valt-connect-trigger" x-on:click="showModal = true">
	<?php echo valt_svg_wallet( 18 ); ?>
	<?php echo esc_html($text); ?>
</button>
