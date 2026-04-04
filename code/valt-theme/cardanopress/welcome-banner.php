<?php
/**
 * Welcome banner — Valt styled.
 */
$userProfile = cardanoPress()->userProfile();
$username = $userProfile->getData('user_login');
?>

<div class="valt-welcome">
	<div class="valt-welcome__greeting">
		<?php echo valt_svg_user( 24 ); ?>
		<h2>Welcome, <em><?php echo esc_html($username); ?></em></h2>
	</div>
	<a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="valt-btn valt-btn--small valt-btn--secondary">Disconnect</a>
</div>
