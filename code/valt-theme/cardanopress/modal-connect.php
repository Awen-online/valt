<?php
/**
 * Wallet connect modal — Valt styled.
 */
?>

<div
	class="valt-modal-overlay"
	x-init="$el.style.display = 'none'"
	x-show="showModal"
	x-transition:enter="valt-modal-enter"
	x-transition:leave="valt-modal-leave"
	style="display:none;"
>
	<div
		class="valt-modal"
		x-show="showModal"
		x-transition:enter="valt-modal-box-enter"
		x-transition:enter-start="valt-modal-box-start"
		x-transition:enter-end="valt-modal-box-end"
		x-transition:leave="valt-modal-box-leave"
		x-transition:leave-start="valt-modal-box-end"
		x-transition:leave-end="valt-modal-box-start"
		x-on:click.away="showModal = false"
	>
		<?php cardanoPress()->template('part/modal-header'); ?>
		<?php cardanoPress()->template('part/modal-content'); ?>
	</div>
</div>
