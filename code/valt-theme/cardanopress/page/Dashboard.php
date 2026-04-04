<?php
/**
 * Wallet Dashboard — restyled for Valt design system.
 */
get_header();
$userProfile = cardanoPress()->userProfile();
?>

<div class="valt-site">
	<?php valt_render_nav(); ?>

	<main class="valt-main">
		<div class="valt-container">

			<template x-if="!isConnected">
				<div class="valt-wallet-prompt">
					<div class="valt-wallet-prompt__icon">
						<?php echo valt_svg_wallet( 64 ); ?>
					</div>
					<h1>Connect Your Wallet</h1>
					<p>Link your Cardano wallet to access your collection, manage your profile, and collect music NFTs.</p>
					<?php cardanoPress()->template('part/modal-trigger', ['text' => 'Connect Wallet']); ?>
				</div>
			</template>

			<template x-if="isConnected">
				<div>
					<?php cardanoPress()->template('welcome-banner'); ?>

					<div class="valt-dashboard-grid">
						<div class="valt-card">
							<h3><?php echo valt_svg_wallet( 20 ); ?> Wallet</h3>
							<?php cardanoPress()->template('part/profile-connection'); ?>
						</div>
						<div class="valt-card">
							<h3><?php echo valt_svg_collection( 20 ); ?> Quick Links</h3>
							<div class="valt-dashboard-links">
								<a href="<?php echo home_url( '/collection/' ); ?>" class="valt-btn valt-btn--secondary">My Collection</a>
								<a href="<?php echo home_url( '/discover/' ); ?>" class="valt-btn valt-btn--secondary">Discover Artists</a>
							</div>
						</div>
					</div>

					<?php cardanoPress()->template('part/profile-adahandles'); ?>

					<?php the_content(); ?>
				</div>
			</template>

		</div>
	</main>

	<?php valt_render_footer(); ?>
</div>

<?php get_footer(); ?>
