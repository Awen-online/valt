<?php
/**
 * Asset sync button — Valt styled.
 */
if (empty($text)) {
    $text = 'Sync Assets';
}
?>

<button class="valt-btn valt-btn--secondary valt-btn--small valt-sync-btn" x-on:click.prevent="handleSync()" x-bind:disabled="isDisabled()">
	<svg class="valt-icon valt-sync-btn__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
		<path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
		<path d="M16 16h5v5"/>
	</svg>
	<?php echo esc_html($text); ?>
</button>
