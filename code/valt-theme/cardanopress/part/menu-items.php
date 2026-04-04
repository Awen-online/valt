<?php
/**
 * Menu dropdown items — Valt styled.
 */
$list = [
	'Dashboard'  => home_url('/dashboard/'),
	'Collection' => home_url('/collection/'),
	'Disconnect' => wp_logout_url(home_url()),
];
?>

<?php foreach ($list as $label => $link) : ?>
	<li><a href="<?php echo esc_url($link); ?>" class="valt-dropdown__link"><?php echo esc_html($label); ?></a></li>
<?php endforeach; ?>
