<?php
/**
 * Wallet connect modal — Valt styled.
 * Resets connecting state when modal closes.
 */
?>

<div
	class="valt-modal-overlay"
	x-init="$el.style.display = 'none'"
	x-show="showModal"
	x-transition:enter="valt-modal-enter"
	x-transition:leave="valt-modal-leave"
	x-on:show-modal.window="$el.querySelectorAll('.valt-wallet-btn--connecting').forEach(el => el.classList.remove('valt-wallet-btn--connecting')); $el.querySelector('.valt-wallet-list')?.classList.remove('is-connecting')"
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

<script>
// Reset connecting state when modal opens or connection completes/fails.
document.addEventListener('alpine:initialized', function() {
	// Watch for showModal changes to reset state.
	var overlay = document.querySelector('.valt-modal-overlay');
	if (!overlay) return;
	var observer = new MutationObserver(function() {
		var isHidden = overlay.style.display === 'none' || !overlay.offsetParent;
		if (isHidden) {
			overlay.querySelectorAll('.valt-wallet-btn--connecting').forEach(function(el) {
				el.classList.remove('valt-wallet-btn--connecting');
			});
			var list = overlay.querySelector('.valt-wallet-list');
			if (list) list.classList.remove('is-connecting');
		}
	});
	observer.observe(overlay, { attributes: true, attributeFilter: ['style', 'class'] });
});
</script>
