<?php
/**
 * Modal content — wallet list with connecting state.
 */
?>

<div class="valt-modal__body">
	<p class="valt-modal__hint">Choose your Cardano wallet. Make sure the browser extension is installed.</p>
	<div class="valt-wallet-list">
		<template x-for="(type, index) in supportedWallets" :key="index">
			<?php cardanoPress()->template('part/connect-wallet'); ?>
		</template>
	</div>
	<p class="valt-modal__help">
		Don't have a wallet? We recommend <a href="https://eternl.io" target="_blank" rel="noopener">Eternl</a> or <a href="https://www.lace.io" target="_blank" rel="noopener">Lace</a>.
	</p>
</div>
