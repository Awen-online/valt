<?php
/**
 * Individual wallet button — shows availability, Valt styled.
 * Uses window.cardano[type] detection to show installed vs not-installed state.
 */
?>

<?php if (isset($type)) : ?>
	<template x-data="{type:'<?php echo esc_attr($type); ?>'}" x-if="supportedWallets.includes(type)">
<?php endif; ?>

<button
	class="valt-wallet-btn"
	x-on:click.prevent="walletConnect(type)"
	x-bind:disabled="isDisabled(type)"
	x-bind:class="{
		'valt-wallet-btn--available': window.cardano && window.cardano[type === 'typhoncip30' ? 'typhon' : type],
		'valt-wallet-btn--unavailable': !(window.cardano && window.cardano[type === 'typhoncip30' ? 'typhon' : type])
	}"
>
	<span class="valt-wallet-btn__name" x-text="type === 'typhoncip30' ? 'Typhon' : type.charAt(0).toUpperCase() + type.slice(1)"></span>
	<template x-if="window.cardano && window.cardano[type === 'typhoncip30' ? 'typhon' : type]">
		<span class="valt-wallet-btn__status valt-wallet-btn__status--installed">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
			Installed
		</span>
	</template>
	<template x-if="!(window.cardano && window.cardano[type === 'typhoncip30' ? 'typhon' : type])">
		<span class="valt-wallet-btn__status valt-wallet-btn__status--missing">Not detected</span>
	</template>
	<template x-if="!!fromVespr(type)">
		<span class="valt-wallet-btn__vespr">via VESPR</span>
	</template>
</button>

<?php if (isset($type)) : ?>
	</template>
<?php endif; ?>
