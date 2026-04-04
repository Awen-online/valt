<?php
/**
 * Single NFT collection item — Valt styled card.
 */
if (empty($asset)) {
	return;
}
?>

<div class="valt-nft-card">
	<div class="valt-nft-card__image">
		<?php cardanoPress()->template('part/asset-image', compact('asset')); ?>
	</div>
	<div class="valt-nft-card__body">
		<h3 class="valt-nft-card__name"><?php cardanoPress()->template('part/asset-name', compact('asset')); ?></h3>
		<span class="valt-nft-card__qty">Qty: <?php echo esc_html($asset['quantity']); ?></span>

		<?php if (! empty($asset['onchain_metadata'])) : ?>
			<div class="valt-nft-card__meta">
				<?php foreach ($asset['onchain_metadata'] as $key => $value) : ?>
					<?php if (! in_array($key, ['name', 'image', 'arweaveId', 'files', 'music_metadata_version'])) : ?>
						<div class="valt-nft-card__field">
							<span class="valt-nft-card__label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></span>
							<span class="valt-nft-card__value">
								<?php echo is_array($value) ? esc_html(implode(', ', $value)) : esc_html($value); ?>
							</span>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
