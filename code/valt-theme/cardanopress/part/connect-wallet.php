<?php
/**
 * Individual wallet button — shows availability + connecting state.
 *
 * Detection: checks window.cardano[name] with fallback for late-injecting
 * wallets like Eternl. All buttons disable when any wallet is connecting.
 */
?>

<?php if (isset($type)) : ?>
	<template x-data="{type:'<?php echo esc_attr($type); ?>'}" x-if="supportedWallets.includes(type)">
<?php endif; ?>

<button
	class="valt-wallet-btn"
	x-on:click.prevent="$el.closest('.valt-wallet-list').classList.add('is-connecting'); $el.classList.add('valt-wallet-btn--connecting'); walletConnect(type)"
	x-bind:disabled="isDisabled(type)"
>
	<span class="valt-wallet-btn__name" x-text="type === 'typhoncip30' ? 'Typhon' : type.charAt(0).toUpperCase() + type.slice(1)"></span>

	<span class="valt-wallet-btn__spinner"></span>

	<template x-if="!!fromVespr(type)">
		<span class="valt-wallet-btn__vespr">via VESPR</span>
	</template>
</button>

<?php if (isset($type)) : ?>
	</template>
<?php endif; ?>
