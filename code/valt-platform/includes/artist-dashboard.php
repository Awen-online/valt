<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render the full artist dashboard HTML for the given Artist WP_Post.
 *
 * Called by the [valt_artist_dashboard] shortcode.
 */
function valt_render_artist_dashboard( WP_Post $artist ): string {

	$artist_id = $artist->ID;

	// Current field values
	$name      = get_the_title( $artist_id );
	$bio       = get_post_meta( $artist_id, 'bio', true );
	$genre     = get_post_meta( $artist_id, 'genre', true );
	$country   = get_post_meta( $artist_id, 'country', true );
	$policy_id = get_post_meta( $artist_id, 'valt_policy_id', true );
	$thumb_id  = get_post_thumbnail_id( $artist_id );
	$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';

	// Albums linked to this artist
	$albums = get_posts( [
		'post_type'      => 'album',
		'meta_query'     => [ [ 'key' => 'artist', 'value' => $artist_id ] ],
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );

	// Releases by this artist (post_author set on creation)
	$songs = get_posts( [
		'post_type'      => 'song',
		'author'         => get_current_user_id(),
		'posts_per_page' => -1,
		'post_status'    => [ 'publish', 'draft' ],
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );

	ob_start();
	?>
	<div class="valt-dashboard" data-artist-id="<?php echo esc_attr( $artist_id ); ?>">

		<!-- Tabs ---------------------------------------------------------- -->
		<div class="valt-tabs" role="tablist">
			<button class="valt-tab-btn valt-tab-btn--active"
			        data-tab="profile" role="tab" aria-selected="true">
				Profile
			</button>
			<button class="valt-tab-btn"
			        data-tab="releases" role="tab" aria-selected="false">
				Releases
			</button>
		</div>

		<!-- Profile Tab --------------------------------------------------- -->
		<div class="valt-tab-panel valt-tab-panel--active" id="valt-tab-profile" role="tabpanel">

			<div id="valt-profile-message" class="valt-dashboard__notice" style="display:none;"></div>

			<form id="valt-profile-form" class="valt-form">
				<input type="hidden" name="artist_id" value="<?php echo esc_attr( $artist_id ); ?>">

				<!-- Photo -->
				<div class="valt-form__group valt-form__group--photo">
					<div class="valt-photo-preview">
						<?php if ( $thumb_url ) : ?>
							<img src="<?php echo esc_url( $thumb_url ); ?>"
							     id="valt-photo-preview"
							     class="valt-photo-preview__img"
							     alt="Profile Photo">
						<?php else : ?>
							<div id="valt-photo-preview" class="valt-photo-preview__placeholder">
								No photo
							</div>
						<?php endif; ?>
					</div>
					<input type="hidden" id="valt-photo-id" name="photo_id"
					       value="<?php echo esc_attr( (string) $thumb_id ); ?>">
					<button type="button" id="valt-upload-photo" class="valt-btn valt-btn--secondary">
						Upload Photo
					</button>
					<button type="button" id="valt-remove-photo" class="valt-btn valt-btn--danger"
					        <?php echo $thumb_id ? '' : 'style="display:none;"'; ?>>
						Remove Photo
					</button>
				</div>

				<!-- Name -->
				<div class="valt-form__group">
					<label class="valt-form__label" for="valt-name">Artist Name</label>
					<input type="text" id="valt-name" name="name"
					       class="valt-form__input"
					       value="<?php echo esc_attr( $name ); ?>"
					       required>
				</div>

				<!-- Bio -->
				<div class="valt-form__group">
					<label class="valt-form__label" for="valt-bio">Bio</label>
					<textarea id="valt-bio" name="bio" class="valt-form__textarea"
					          rows="5"><?php echo esc_textarea( $bio ); ?></textarea>
				</div>

				<!-- Genre + Country -->
				<div class="valt-form__row">
					<div class="valt-form__group">
						<label class="valt-form__label" for="valt-genre">Genre</label>
						<input type="text" id="valt-genre" name="genre"
						       class="valt-form__input"
						       value="<?php echo esc_attr( $genre ); ?>">
					</div>
					<div class="valt-form__group">
						<label class="valt-form__label" for="valt-country">Country</label>
						<input type="text" id="valt-country" name="country"
						       class="valt-form__input"
						       value="<?php echo esc_attr( $country ); ?>">
					</div>
				</div>

				<!-- Policy ID -->
				<div class="valt-form__group">
					<label class="valt-form__label" for="valt-policy-id">NFT Policy ID</label>
					<input type="text" id="valt-policy-id" name="valt_policy_id"
					       class="valt-form__input valt-form__input--mono"
					       value="<?php echo esc_attr( $policy_id ); ?>"
					       placeholder="e.g. a0028f350aaabe0545fdcb56b039bfb08e4bb4d8c4d7c3c7d481809">
					<p class="valt-form__help">
						The Cardano NFT policy ID that gates your Valt fan-club zone.
					</p>
				</div>

				<div class="valt-form__actions">
					<button type="submit" class="valt-btn valt-btn--primary">Save Profile</button>
				</div>
			</form>
		</div><!-- /profile tab -->

		<!-- Releases Tab -------------------------------------------------- -->
		<div class="valt-tab-panel" id="valt-tab-releases" role="tabpanel">

			<!-- Add Release Form -->
			<div class="valt-dashboard__section">
				<h3 class="valt-dashboard__section-title">Add Release</h3>

				<div id="valt-release-message" class="valt-dashboard__notice" style="display:none;"></div>

				<form id="valt-release-form" class="valt-form">
					<input type="hidden" name="artist_id" value="<?php echo esc_attr( $artist_id ); ?>">

					<!-- Title -->
					<div class="valt-form__group">
						<label class="valt-form__label" for="valt-release-title">Track Title</label>
						<input type="text" id="valt-release-title" name="title"
						       class="valt-form__input" required placeholder="Song title">
					</div>

					<!-- Audio file -->
					<div class="valt-form__group">
						<label class="valt-form__label">Audio File</label>
						<div class="valt-audio-upload">
							<input type="hidden" id="valt-audio-id" name="audio_id" value="">
							<span id="valt-audio-filename" class="valt-audio-upload__name">
								No file selected
							</span>
							<button type="button" id="valt-upload-audio" class="valt-btn valt-btn--secondary">
								Select Audio File
							</button>
						</div>
					</div>

					<!-- Album + Duration + Track # -->
					<div class="valt-form__row">
						<div class="valt-form__group">
							<label class="valt-form__label" for="valt-release-album">Album</label>
							<select id="valt-release-album" name="album_id" class="valt-form__select">
								<option value="">&#8212; No album &#8212;</option>
								<?php foreach ( $albums as $album ) : ?>
									<option value="<?php echo esc_attr( $album->ID ); ?>">
										<?php echo esc_html( get_the_title( $album->ID ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="valt-form__group">
							<label class="valt-form__label" for="valt-release-duration">Duration</label>
							<input type="text" id="valt-release-duration" name="duration"
							       class="valt-form__input valt-form__input--sm"
							       placeholder="3:42">
						</div>
						<div class="valt-form__group">
							<label class="valt-form__label" for="valt-release-track">Track #</label>
							<input type="number" id="valt-release-track" name="track_number"
							       class="valt-form__input valt-form__input--sm"
							       min="1" placeholder="1">
						</div>
					</div>

					<div class="valt-form__actions">
						<button type="submit" class="valt-btn valt-btn--primary">Add Release</button>
					</div>
				</form>
			</div>

			<!-- Releases Table -->
			<div class="valt-dashboard__section" id="valt-releases-table-wrap">
				<h3 class="valt-dashboard__section-title">Your Releases</h3>

				<?php if ( empty( $songs ) ) : ?>
					<p class="valt-notice">No releases yet. Add your first track above.</p>
				<?php else : ?>
					<table class="valt-table">
						<thead>
							<tr>
								<th>Title</th>
								<th>Album</th>
								<th>Duration</th>
								<th>Status</th>
								<th>Minted</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $songs as $song ) : ?>
								<?php
								$song_album_id  = (int) get_post_meta( $song->ID, 'album', true );
								$song_album     = $song_album_id ? get_the_title( $song_album_id ) : '&mdash;';
								$song_duration  = get_post_meta( $song->ID, 'duration', true ) ?: '&mdash;';
								$song_status    = (int) get_post_meta( $song->ID, 'valt_release_status', true ) ?: 1;
								$song_mint      = (int) get_post_meta( $song->ID, 'valt_mint_count', true );

								$status_labels  = [
									1 => 'Uploaded',
									2 => 'In NFT Collection',
									3 => 'Minted',
								];
								$status_classes = [
									1 => 'valt-badge--grey',
									2 => 'valt-badge--amber',
									3 => 'valt-badge--gold',
								];
								$status_label  = $status_labels[ $song_status ] ?? 'Uploaded';
								$status_class  = $status_classes[ $song_status ] ?? 'valt-badge--grey';
								?>
								<tr>
									<td><?php echo esc_html( get_the_title( $song->ID ) ); ?></td>
									<td><?php echo $song_album; ?></td>
									<td><?php echo $song_duration; ?></td>
									<td>
										<span class="valt-badge <?php echo esc_attr( $status_class ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</span>
									</td>
									<td><?php echo $song_status === 3 ? esc_html( (string) $song_mint ) : '&mdash;'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		</div><!-- /releases tab -->

	</div><!-- /valt-dashboard -->
	<?php
	return ob_get_clean();
}

// ---------------------------------------------------------------------------
// AJAX: Save artist profile
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_valt_save_artist_profile', function () {

	check_ajax_referer( 'valt_platform', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( 'Not logged in.' );
	}

	$artist = valt_get_current_artist();
	if ( ! $artist ) {
		wp_send_json_error( 'No artist profile linked to your account.' );
	}

	// Confirm submitted artist_id matches the linked artist
	$submitted_id = (int) ( $_POST['artist_id'] ?? 0 );
	if ( $submitted_id !== $artist->ID ) {
		wp_send_json_error( 'Artist ID mismatch.' );
	}

	$artist_id = $artist->ID;

	// Update post title
	$name = sanitize_text_field( $_POST['name'] ?? '' );
	if ( $name ) {
		wp_update_post( [
			'ID'         => $artist_id,
			'post_title' => $name,
			'post_name'  => sanitize_title( $name ),
		] );
	}

	// Update meta
	update_post_meta( $artist_id, 'bio',            wp_kses_post( $_POST['bio'] ?? '' ) );
	update_post_meta( $artist_id, 'genre',          sanitize_text_field( $_POST['genre'] ?? '' ) );
	update_post_meta( $artist_id, 'country',        sanitize_text_field( $_POST['country'] ?? '' ) );
	update_post_meta( $artist_id, 'valt_policy_id', sanitize_text_field( $_POST['valt_policy_id'] ?? '' ) );

	// Profile thumbnail
	$photo_id = (int) ( $_POST['photo_id'] ?? 0 );
	if ( $photo_id ) {
		set_post_thumbnail( $artist_id, $photo_id );
	} else {
		delete_post_thumbnail( $artist_id );
	}

	wp_send_json_success( 'Profile updated successfully.' );
} );

// ---------------------------------------------------------------------------
// AJAX: Add a new release (Song CPT)
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_valt_add_release', function () {

	check_ajax_referer( 'valt_platform', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( 'Not logged in.' );
	}

	$artist = valt_get_current_artist();
	if ( ! $artist ) {
		wp_send_json_error( 'No artist profile linked to your account.' );
	}

	$submitted_id = (int) ( $_POST['artist_id'] ?? 0 );
	if ( $submitted_id !== $artist->ID ) {
		wp_send_json_error( 'Artist ID mismatch.' );
	}

	$title = sanitize_text_field( $_POST['title'] ?? '' );
	if ( ! $title ) {
		wp_send_json_error( 'A track title is required.' );
	}

	// Create the Song CPT — post_author set so releases table query finds it
	$song_id = wp_insert_post( [
		'post_type'   => 'song',
		'post_title'  => $title,
		'post_status' => 'publish',
		'post_author' => get_current_user_id(),
	], true );

	if ( is_wp_error( $song_id ) ) {
		wp_send_json_error( $song_id->get_error_message() );
	}

	$audio_id  = (int) ( $_POST['audio_id'] ?? 0 );
	$album_id  = (int) ( $_POST['album_id'] ?? 0 );
	$duration  = sanitize_text_field( $_POST['duration'] ?? '' );
	$track_num = (int) ( $_POST['track_number'] ?? 0 );

	if ( $audio_id ) {
		update_post_meta( $song_id, 'audio_file', $audio_id );
	}
	if ( $album_id ) {
		update_post_meta( $song_id, 'album', $album_id );
	}
	if ( $duration ) {
		update_post_meta( $song_id, 'duration', $duration );
	}
	if ( $track_num ) {
		update_post_meta( $song_id, 'track_number', $track_num );
	}

	update_post_meta( $song_id, 'artist',              $artist->ID );
	update_post_meta( $song_id, 'valt_release_status', 1 );
	update_post_meta( $song_id, 'valt_mint_count',     0 );

	wp_send_json_success( [
		'message'  => 'Release added successfully.',
		'song_id'  => $song_id,
		'title'    => $title,
		'album'    => $album_id ? get_the_title( $album_id ) : '&mdash;',
		'duration' => $duration ?: '&mdash;',
	] );
} );
