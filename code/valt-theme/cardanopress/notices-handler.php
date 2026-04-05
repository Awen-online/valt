<?php
/**
 * Toast notifications — centered, above nav, Valt styled.
 */
?>

<div class="valt-toast-container" x-data>
	<template x-for="notice of $store.toastNotification.list" :key="notice.id">
		<div
			class="valt-toast"
			x-show="$store.toastNotification.visible.includes(notice)"
			x-transition:enter="valt-toast-enter"
			x-transition:enter-start="valt-toast-start"
			x-transition:enter-end="valt-toast-end"
			x-transition:leave="valt-toast-leave"
			x-transition:leave-start="valt-toast-end"
			x-transition:leave-end="valt-toast-start"
			x-bind:class="{
				'valt-toast--success': notice.type === 'success',
				'valt-toast--info':    notice.type === 'info',
				'valt-toast--warning': notice.type === 'warning',
				'valt-toast--error':   notice.type === 'error',
			}"
		>
			<span class="valt-toast__text" x-text="notice.text"></span>
			<div class="valt-toast__actions">
				<button class="valt-toast__refresh" x-on:click="window.location.reload()">Refresh</button>
				<button class="valt-toast__close" x-on:click="$store.toastNotification.remove(notice.id)">&times;</button>
			</div>
		</div>
	</template>
</div>
