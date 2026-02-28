<?php

// Shortcode to generate a responsive <img> tag for the related artist's featured image
add_shortcode('pods_artist_featured_image', function ($atts) {
    // Define default attributes and merge with user-provided attributes
    $atts = shortcode_atts([
        'pod'   => 'song',        // Default Pod name
        'field' => 'artist',      // Default relationship field name
        'size'  => 'thumbnail',   // Default image size
        'class' => '',            // Optional CSS classes for the <img> tag
    ], $atts, 'pods_artist_featured_image');

    // Get the current post ID (e.g., song)
    $post_id = get_the_ID();
    if (!$post_id) {
        error_log('Pods Artist Featured Image Shortcode: No post ID found');
        return '';
    }

    // Fetch the Pod
    $pod = pods($atts['pod'], $post_id);
    if (!$pod->exists()) {
        error_log("Pods Artist Featured Image Shortcode: Pod '{$atts['pod']}' does not exist for Post ID: $post_id");
        return '';
    }

    // Get the related artist ID from the relationship field
    $artist_id = $pod->field($atts['field'] . '.ID');
    if (!$artist_id) {
        error_log("Pods Artist Featured Image Shortcode: No artist ID found for relationship field '{$atts['field']}'");
        return '';
    }

    // Get the featured image URL for the artist
    $thumbnail_url = get_the_post_thumbnail_url($artist_id, $atts['size']);
    if (!$thumbnail_url) {
        error_log("Pods Artist Featured Image Shortcode: No thumbnail URL found for Artist ID: $artist_id with size: {$atts['size']}");
        return '';
    }

    // Get the alt text (use the artist's post title)
    $alt_text = get_the_title($artist_id);
    $alt_text = esc_attr($alt_text); // Escape for HTML attribute

    // Combine the default responsive class with any user-provided classes
    $default_class = 'responsive-artist-image';
    $classes = esc_attr(trim($default_class . ' ' . $atts['class']));

    // Generate the <img> tag with inline styles for responsiveness
    $img_html = sprintf(
        '<img src="%s" alt="%s" class="%s" style="max-width: 100%%; height: auto; width: 100%%; object-fit: contain;">',
        esc_url($thumbnail_url),
        $alt_text,
        $classes
    );

    return $img_html;
});