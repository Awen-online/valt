<?php
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Meta box on Song edit screen: release status dropdown + mint count field
// ---------------------------------------------------------------------------

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'valt_song_meta',
		'Valt Release Info',
		'valt_song_meta_box_cb',
		'song',
		'side',
		'default'
	);
} );

function valt_song_meta_box_cb( WP_Post $post ): void {
	wp_nonce_field( 'valt_song_meta_save', 'valt_song_meta_nonce' );

	$status     = (int) get_post_meta( $post->ID, 'valt_release_status', true ) ?: 1;
	$mint_count = (int) get_post_meta( $post->ID, 'valt_mint_count', true );
	?>
	<p>
		<label for="valt_release_status"><strong>Release Status</strong></label><br>
		<select name="valt_release_status" id="valt_release_status" style="width:100%;">
			<option value="1" <?php selected( $status, 1 ); ?>>1 &mdash; Uploaded</option>
			<option value="2" <?php selected( $status, 2 ); ?>>2 &mdash; In NFT Collection</option>
			<option value="3" <?php selected( $status, 3 ); ?>>3 &mdash; Minted</option>
		</select>
	</p>
	<p>
		<label for="valt_mint_count"><strong>Mint Count</strong></label><br>
		<input type="number" name="valt_mint_count" id="valt_mint_count"
		       value="<?php echo esc_attr( (string) $mint_count ); ?>"
		       min="0" style="width:100%;">
		<em style="font-size:11px;">Number of copies minted (admin-only).</em>
	</p>
	<?php
}

add_action( 'save_post_song', function ( int $post_id ): void {
	if (
		! isset( $_POST['valt_song_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['valt_song_meta_nonce'], 'valt_song_meta_save' )
	) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['valt_release_status'] ) ) {
		$status = (int) $_POST['valt_release_status'];
		if ( in_array( $status, [ 1, 2, 3 ], true ) ) {
			update_post_meta( $post_id, 'valt_release_status', $status );
		}
	}

	if ( isset( $_POST['valt_mint_count'] ) ) {
		update_post_meta( $post_id, 'valt_mint_count', absint( $_POST['valt_mint_count'] ) );
	}
} );

// ---------------------------------------------------------------------------
// Custom column on Artist post list: shows valt_policy_id
// ---------------------------------------------------------------------------

add_filter( 'manage_artist_posts_columns', function ( array $cols ): array {
	$cols['valt_policy_id'] = 'Policy ID';
	return $cols;
} );

add_action( 'manage_artist_posts_custom_column', function ( string $col, int $post_id ): void {
	if ( $col !== 'valt_policy_id' ) {
		return;
	}
	$pid = get_post_meta( $post_id, 'valt_policy_id', true );
	echo $pid
		? '<code style="font-size:11px;word-break:break-all;">' . esc_html( $pid ) . '</code>'
		: '<span style="color:#aaa;">&mdash;</span>';
}, 10, 2 );
