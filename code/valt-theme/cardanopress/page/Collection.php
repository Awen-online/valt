<?php
/**
 * NFT Collection page — restyled for Valt design system.
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
?>

<div class="valt-site">
	<?php valt_render_nav(); ?>

	<main class="valt-main">
		<div class="valt-container">

			<?php cardanoPress()->template('welcome-banner'); ?>

			<div class="valt-section-header">
				<h1>Your Valt</h1>
				<p>Your personal collection of digital music collectables.</p>
			</div>

			<?php the_content(); ?>

			<template x-if="!isConnected">
				<div class="valt-wallet-prompt valt-wallet-prompt--compact">
					<p>Connect your wallet to view your collection.</p>
					<?php cardanoPress()->template('part/modal-trigger', ['text' => 'Connect Wallet']); ?>
				</div>
			</template>

			<template x-if="isConnected">
				<div>
					<div class="valt-collection-actions">
						<?php cardanoPress()->template('part/asset-sync'); ?>
					</div>
					<div class="valt-collection-grid">
						<?php cardanoPress()->template('collection-list'); ?>
					</div>
				</div>
			</template>

		</div>
	</main>

	<?php valt_render_footer(); ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
