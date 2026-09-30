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

    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    // Version by file mtime so browsers cache CSS/JS until the file actually changes
    // (the old time()-based version defeated caching on every page view).
    $ver = function ( $rel ) use ( $dir ) {
        $f = $dir . $rel;
        return file_exists( $f ) ? (string) filemtime( $f ) : '1';
    };

    // Ruda, the brand typeface. Enqueued as a real stylesheet: the old @import sat after
    // :root in main.css, which browsers ignore, so the site silently fell back to system fonts.
    wp_enqueue_style( 'valt-ruda', 'https://fonts.googleapis.com/css2?family=Ruda:wght@400;500;600;700;800;900&display=swap', array(), null );

    // Load Valt's styles AFTER the Hello Elementor parent CSS. Its reset.css sets
    // body{background:#fff;color:#333;font-family:system} and pink #c36 buttons, and it used to
    // load later and win. Only depend on handles that are actually registered.
    $deps = array_values( array_filter(
        array( 'hello-elementor', 'hello-elementor-theme-style', 'hello-elementor-header-footer', 'valt-ruda' ),
        function ( $h ) { return wp_style_is( $h, 'registered' ); }
    ) );

    //
    //ADD CSS
    //
    wp_enqueue_style('custom-style', $uri . '/assets/css/main.css', $deps, $ver('/assets/css/main.css'));
    wp_enqueue_style('cardano-press-style', $uri . '/assets/css/cardanopress_styles.css', array('custom-style'), $ver('/assets/css/cardanopress_styles.css'));

    // Valt player: sticky bottom bar that plays songs from any [data-valt-track] on the page.
    wp_enqueue_script('valt-player', $uri . '/assets/js/player.js', array(), $ver('/assets/js/player.js'), true);
    // wp_enqueue_style('my-account-style', get_stylesheet_directory_uri() . '/assets/css/my-account.css', array(), $style_version);

    // Pass current post ID to music player on CPT single pages so it can
    // fetch the contextual playlist from the REST endpoint.
    if ( is_singular( [ 'song', 'album', 'artist' ] ) ) {
        wp_localize_script( 'music-player', 'fmlPlayer', [
            'contextId' => get_the_ID(),
            'restUrl'   => rest_url( 'fml-music-player/v1/a/' ),
        ] );
    }
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



}, 20); // After Hello Elementor (priority 10) registers its styles, so the deps above resolve.

// SVG favicon + font preconnect + browser chrome colour.
add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<link rel="icon" href="' . get_stylesheet_directory_uri() . '/assets/img/favicon.svg" type="image/svg+xml">' . "\n";
	echo '<meta name="theme-color" content="#1B1A2B">' . "\n";
}, 1 );

// Google Analytics 4 (gtag.js) — property G-4Q5EDNY0F7. Front-end only.
add_action( 'wp_head', function () {
	$gid = 'G-4Q5EDNY0F7';
	?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $gid ); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo esc_js( $gid ); ?>');
</script>
	<?php
}, 2 );

require get_stylesheet_directory().'/functions/elementor.php';
require get_stylesheet_directory().'/functions/pods.php';
require get_stylesheet_directory().'/functions/shortcodes/pods_artist_featured_image.php';
require get_stylesheet_directory().'/functions/svg-icons.php';
require get_stylesheet_directory().'/functions/site-chrome.php';
require get_stylesheet_directory().'/functions/survey/loader.php';
require get_stylesheet_directory().'/functions/intake/loader.php';
