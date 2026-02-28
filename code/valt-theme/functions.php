<?php

function disable_wp_backend_for_subscribers() {
    if (is_admin() && current_user_can('subscriber') && !defined('DOING_AJAX')) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('init', 'disable_wp_backend_for_subscribers');

function remove_admin_bar_for_subscribers() {
    if (current_user_can('subscriber')) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'remove_admin_bar_for_subscribers');


//
//ADD CSS
//
add_action('wp_enqueue_scripts', function() {
    
    $style_version = '1.00'; // Change this to your desired version number
    
    //
    //ADD CSS
    //
    wp_enqueue_style('custom-style', get_stylesheet_directory_uri() . '/assets/css/main.css', array(), $style_version);
    wp_enqueue_style('cardano-press-style', get_stylesheet_directory_uri() . '/assets/css/cardanopress_styles.css', array(), $style_version);
    // wp_enqueue_style('my-account-style', get_stylesheet_directory_uri() . '/assets/css/my-account.css', array(), $style_version);
//    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css', array(), '6.4.2');

    
    //
    //ADD JS
    //
    // wp_enqueue_script('custom-script', get_stylesheet_directory_uri() . '/assets/js/main.js', array('jquery'), $style_version);
    // wp_enqueue_script('create-event-script', get_stylesheet_directory_uri() . '/assets/js/create-event.js', array('jquery'), $style_version);

     // Enqueue Three.js core
    //  wp_enqueue_script(
    //     'three-js',
    //     'https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.module.js', // Use the module version
    //     array(),
    //     null,
    //     true
    // );
    
    // // Enqueue OrbitControls (module version)
    // wp_enqueue_script(
    //     'three-orbit-controls',
    //     'https://cdn.jsdelivr.net/npm/three@0.158.0/examples/jsm/controls/OrbitControls.js',
    //     array('three-js'),
    //     null,
    //     true
    // );
    
    // // Enqueue regal-particles.js (your new script)
    // wp_enqueue_script(
    //     'regal-particles',
    //     get_stylesheet_directory_uri() . '/assets/js/regal-particles.js',
    //     array('three-js', 'three-orbit-controls'),
    //     null, // You can define $style_version here if needed
    //     true
    // );
    
    // // Add type="module" to both three-orbit-controls and regal-particles script tags
    // add_filter('script_loader_tag', 'add_module_to_threejs_script', 10, 3);
    // function add_module_to_threejs_script($tag, $handle, $src) {
    //     if (in_array($handle, ['three-orbit-controls', 'regal-particles'])) {
    //         $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    //     }
    //     return $tag;
    // }
    


});

require get_stylesheet_directory().'/functions/elementor.php';
require get_stylesheet_directory().'/functions/pods.php';
require get_stylesheet_directory().'/functions/shortcodes/pods_artist_featured_image.php';