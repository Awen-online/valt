<?php
defined( 'ABSPATH' ) || exit;

/**
 * Register post meta for Artist and Song CPTs with REST API visibility.
 */
add_action( 'init', function () {

	register_post_meta( 'artist', 'valt_policy_id', [
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	] );

	register_post_meta( 'song', 'valt_release_status', [
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'integer',
		'default'           => 1,
		'sanitize_callback' => 'absint',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	] );

	register_post_meta( 'song', 'valt_mint_count', [
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'integer',
		'default'           => 0,
		'sanitize_callback' => 'absint',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	] );
} );
