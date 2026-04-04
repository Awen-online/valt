<?php
/**
 * Modal header — Valt styled.
 */
?>

<div class="valt-modal__header">
	<div class="valt-modal__title">
		<?php echo valt_svg_wallet( 24 ); ?>
		<h2>
			<span x-text="isConnected ? 'Reconnect' : 'Connect'">Connect</span> Wallet
		</h2>
	</div>
	<button type="button" class="valt-modal__close" x-on:click="showModal = false" aria-label="Close">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M18 6L6 18M6 6l12 12"/>
		</svg>
	</button>
</div>
