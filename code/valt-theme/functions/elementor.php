<?
function filter_songs_by_artist( $query ) {
    if ( isset( $query->query['post_type'] ) ) {
        // Dynamically get the current artist ID from the context (e.g., if you are on an artist archive page)
        if ( is_singular( 'artist' ) ) {
            $artist_id = get_the_ID(); // Get the current artist ID
        }

        // Query albums related to the specific artist
        if ( isset( $artist_id ) ) {
            $meta_query = array(
                array(
                    'key'     => 'artist', // Pods field for artist relationship
                    'value'   => $artist_id, // Artist ID dynamically set
                    'compare' => '='
                )
            );
            
            $query->set( 'meta_query', $meta_query );
        }
    }
}
add_action( 'elementor/query/songs_filter', 'filter_songs_by_artist' );
