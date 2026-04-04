<?php
defined( 'ABSPATH' ) || exit;

/**
 * Artist discovery and trending for Valt Platform v2.
 */

/**
 * Discover/search artists with filters and sorting.
 *
 * @param array $args search, genre, country, sort, page, per_page.
 * @return array [ 'artists' => [...], 'total' => int, 'pages' => int ]
 */
function valt_discover_artists( array $args = [] ): array {
	$defaults = [
		'search'   => '',
		'genre'    => '',
		'country'  => '',
		'sort'     => 'trending',
		'page'     => 1,
		'per_page' => 12,
	];
	$args = wp_parse_args( $args, $defaults );

	$query_args = [
		'post_type'      => 'artist',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $args['per_page'],
		'paged'          => (int) $args['page'],
		'meta_query'     => [],
	];

	if ( $args['search'] ) {
		$query_args['s'] = $args['search'];
	}

	if ( $args['genre'] ) {
		$query_args['meta_query'][] = [
			'key'     => 'genre',
			'value'   => $args['genre'],
			'compare' => '=',
		];
	}

	if ( $args['country'] ) {
		$query_args['meta_query'][] = [
			'key'     => 'country',
			'value'   => $args['country'],
			'compare' => '=',
		];
	}

	switch ( $args['sort'] ) {
		case 'newest':
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
			break;
		case 'alphabetical':
			$query_args['orderby'] = 'title';
			$query_args['order']   = 'ASC';
			break;
		case 'fans':
			$query_args['meta_key'] = 'valt_fan_count';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'DESC';
			break;
		case 'trending':
		default:
			// For trending, fetch all and sort by computed score.
			$query_args['posts_per_page'] = -1;
			break;
	}

	$query   = new WP_Query( $query_args );
	$artists = [];

	foreach ( $query->posts as $post ) {
		$artist = valt_format_artist_card( $post );
		if ( $args['sort'] === 'trending' ) {
			$artist['trending_score'] = valt_compute_trending_score( $post->ID );
		}
		$artists[] = $artist;
	}

	// Sort by trending if needed, then paginate.
	if ( $args['sort'] === 'trending' ) {
		usort( $artists, function ( $a, $b ) {
			return $b['trending_score'] <=> $a['trending_score'];
		} );
		$total    = count( $artists );
		$offset   = ( (int) $args['page'] - 1 ) * (int) $args['per_page'];
		$artists  = array_slice( $artists, $offset, (int) $args['per_page'] );
		$pages    = ceil( $total / (int) $args['per_page'] );
	} else {
		$total = $query->found_posts;
		$pages = $query->max_num_pages;
	}

	return [
		'artists' => $artists,
		'total'   => $total,
		'pages'   => $pages,
	];
}

/**
 * Get trending artists by computed score.
 */
function valt_get_trending_artists( int $limit = 10 ): array {
	$result = valt_discover_artists( [
		'sort'     => 'trending',
		'per_page' => $limit,
	] );
	return $result['artists'];
}

/**
 * Compute a trending score for an artist.
 * Score = (purchases_30d * 10) + (views_30d * 1) + (new_fans_30d * 5)
 */
function valt_compute_trending_score( int $artist_id ): int {
	global $wpdb;
	$table    = $wpdb->prefix . 'valt_points_ledger';
	$since    = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

	$purchases = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE artist_id = %d AND action = 'nft_purchase' AND created_at >= %s",
		$artist_id, $since
	) );

	$views = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE artist_id = %d AND action = 'content_view' AND created_at >= %s",
		$artist_id, $since
	) );

	$fans = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE artist_id = %d AND action = 'nft_purchase' AND created_at >= %s",
		$artist_id, $since
	) );

	return ( $purchases * 10 ) + ( $views * 1 ) + ( $fans * 5 );
}

/**
 * Format an artist post into a card data array.
 */
function valt_format_artist_card( WP_Post $post ): array {
	$thumb_id = get_post_thumbnail_id( $post->ID );
	return [
		'id'            => $post->ID,
		'name'          => $post->post_title,
		'genre'         => get_post_meta( $post->ID, 'genre', true ) ?: '',
		'country'       => get_post_meta( $post->ID, 'country', true ) ?: '',
		'bio'           => wp_trim_words( get_post_meta( $post->ID, 'bio', true ) ?: '', 20 ),
		'thumbnail_url' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '',
		'fan_count'     => (int) get_post_meta( $post->ID, 'valt_fan_count', true ),
		'featured'      => (bool) get_post_meta( $post->ID, 'valt_featured', true ),
		'url'           => get_permalink( $post->ID ),
		'social'        => [
			'x'         => get_post_meta( $post->ID, 'valt_social_x', true ),
			'instagram' => get_post_meta( $post->ID, 'valt_social_instagram', true ),
			'spotify'   => get_post_meta( $post->ID, 'valt_social_spotify', true ),
		],
	];
}

/**
 * Get all distinct genres from artist CPTs.
 */
function valt_get_genres(): array {
	global $wpdb;
	return $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
		 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = 'genre' AND p.post_type = 'artist' AND p.post_status = 'publish'
		 AND pm.meta_value != ''
		 ORDER BY pm.meta_value"
	);
}

/**
 * Get all distinct countries from artist CPTs.
 */
function valt_get_countries(): array {
	global $wpdb;
	return $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
		 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = 'country' AND p.post_type = 'artist' AND p.post_status = 'publish'
		 AND pm.meta_value != ''
		 ORDER BY pm.meta_value"
	);
}
