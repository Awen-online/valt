<?php
/**
 * Modal content — wallet list with availability detection.
 */
?>

<div class="valt-modal__body">
	<p class="valt-modal__hint">Select a wallet to connect. Installed wallets are highlighted.</p>
	<div class="valt-wallet-list">
		<template x-for="(type, index) in supportedWallets" :key="index">
			<?php cardanoPress()->template('part/connect-wallet'); ?>
		</template>
	</div>
</div>
