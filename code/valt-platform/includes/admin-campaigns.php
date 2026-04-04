<?php
defined( 'ABSPATH' ) || exit;

/**
 * Campaign management admin page for Valt Platform v2.
 */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'valt-platform-docs',
		'Campaigns',
		'Campaigns',
		'manage_options',
		'valt-campaigns',
		'valt_render_campaigns_admin_page'
	);
}, 22 );

function valt_render_campaigns_admin_page(): void {
	$campaigns = valt_get_active_campaigns( 50 );

	// Also get inactive campaigns.
	$all_albums = get_posts( [
		'post_type'      => 'album',
		'posts_per_page' => 50,
		'meta_query'     => [ [
			'key'   => 'valt_campaign_goal',
			'value' => '0',
			'compare' => '>',
		] ],
	] );
	?>
	<div class="wrap">
		<h1>Album Campaigns</h1>

		<?php if ( empty( $all_albums ) && empty( $campaigns ) ) : ?>
			<p>No campaigns created yet. Artists can create campaigns from their dashboard.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr><th>Album</th><th>Artist</th><th>Goal</th><th>Pledged</th><th>%</th><th>Backers</th><th>Deadline</th><th>Status</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $all_albums as $album ) :
					$progress = valt_get_campaign_progress( $album->ID );
					if ( ! $progress ) continue;
					$active = $progress['is_active'] && (int) get_post_meta( $album->ID, 'valt_campaign_active', true );
				?>
					<tr>
						<td><a href="<?php echo get_edit_post_link( $album->ID ); ?>"><?php echo esc_html( $progress['album_title'] ); ?></a></td>
						<td><?php echo esc_html( $progress['artist_name'] ); ?></td>
						<td><?php echo number_format( $progress['goal'] ); ?></td>
						<td><?php echo number_format( $progress['total_pledged'] ); ?></td>
						<td><?php echo (int) $progress['percent']; ?>%</td>
						<td><?php echo (int) $progress['backer_count']; ?></td>
						<td><?php echo $progress['deadline'] ? esc_html( date( 'M j, Y', strtotime( $progress['deadline'] ) ) ) : '—'; ?></td>
						<td><?php echo $active ? '<span style="color:green;">Active</span>' : '<span style="color:gray;">Ended</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
