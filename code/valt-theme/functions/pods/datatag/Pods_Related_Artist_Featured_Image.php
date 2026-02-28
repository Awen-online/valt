<?php

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;

// Define the custom dynamic tag class outside the hook, as per Elementor documentation
class Pods_Related_Artist_Featured_Image extends Data_Tag {
    /**
     * Get the unique name of the dynamic tag.
     *
     * @return string
     */
    public function get_name() {
        return 'pods-related-artist-featured-image';
    }

    /**
     * Get the display title of the dynamic tag.
     *
     * @return string
     */
    public function get_title() {
        return __('Pods Related Artist Featured Image', 'text-domain');
    }

    /**
     * Define the group under which the tag appears in the Elementor editor.
     *
     * @return string
     */
    public function get_group() {
        return 'pods'; // Appears under the "Pods" group in Dynamic Tags
    }

    /**
     * Define the categories this tag belongs to (e.g., URL, Image).
     *
     * @return array
     */
    public function get_categories() {
        return [
            \Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
            \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY
        ];
    }

    /**
     * Register controls for the dynamic tag in the Elementor editor.
     *
     * Adds settings for Pod name, relationship field, and image size.
     */
    protected function register_controls() {
        $this->add_control(
            'pod_name',
            [
                'label'   => __('Pod Name', 'text-domain'),
                'type'    => Controls_Manager::SELECT,
                'options' => $this->get_pods_list(),
                'default' => 'song',
            ]
        );

        $this->add_control(
            'relationship_field',
            [
                'label'       => __('Relationship Field', 'text-domain'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'artist',
                'description' => __('Enter the name of the relationship field (e.g., artist).', 'text-domain'),
            ]
        );

        $this->add_control(
            'image_size',
            [
                'label'   => __('Image Size', 'text-domain'),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'thumbnail' => __('Thumbnail', 'text-domain'),
                    'medium'    => __('Medium', 'text-domain'),
                    'large'     => __('Large', 'text-domain'),
                    'full'      => __('Full', 'text-domain'),
                ],
                'default' => 'thumbnail',
            ]
        );
    }

    /**
     * Retrieve the value of the dynamic tag (the featured image URL).
     *
     * @param array $options Optional options array.
     * @return string The featured image URL or an empty string if not found.
     */
    public function get_value(array $options = []) {
        $pod_name = $this->get_settings('pod_name');
        $relationship_field = $this->get_settings('relationship_field');
        $image_size = $this->get_settings('image_size');

        // Validate required settings
        if (!$pod_name || !$relationship_field) {
            error_log('Pods Related Artist Featured Image: Missing pod_name or relationship_field');
            return '';
        }

        // Get the current post ID (e.g., song)
        $post_id = get_the_ID();
        error_log("Pods Related Artist Featured Image: Current Post ID: $post_id");

        // Fetch the Pod
        $pod = pods($pod_name, $post_id);
        if (!$pod->exists()) {
            error_log("Pods Related Artist Featured Image: Pod '$pod_name' does not exist for Post ID: $post_id");
            return '';
        }

        // Get the related artist ID from the relationship field
        $artist_id = $pod->field($relationship_field . '.ID');
        if (!$artist_id) {
            error_log("Pods Related Artist Featured Image: No artist ID found for relationship field '$relationship_field'");
            return '';
        }
        error_log("Pods Related Artist Featured Image: Artist ID: $artist_id");

        // Get the featured image URL for the artist
        $thumbnail_url = get_the_post_thumbnail_url($artist_id, $image_size);
        if (!$thumbnail_url) {
            error_log("Pods Related Artist Featured Image: No thumbnail URL found for Artist ID: $artist_id with size: $image_size");
            return '';
        }
        error_log("Pods Related Artist Featured Image: Thumbnail URL: $thumbnail_url");

        return $thumbnail_url;
    }

    /**
     * Get a list of available Pods for the Pod Name dropdown.
     *
     * @return array An array of Pod names and labels.
     */
    private function get_pods_list() {
        $pods = pods_api()->load_pods(['fields' => false]);
        $options = [];
        foreach ($pods as $pod) {
            $options[$pod['name']] = $pod['label'];
        }
        error_log('Pods Available: ' . json_encode($options, JSON_PRETTY_PRINT));
        return $options;
    }
}

// Register the custom dynamic tag using Elementor’s recommended approach
function register_new_dynamic_tags($dynamic_tags_manager) {
    $dynamic_tags_manager->register(new Pods_Related_Artist_Featured_Image());
    error_log('Registered Pods Related Artist Featured Image Tag on elementor/dynamic_tags/register');
}
add_action('elementor/dynamic_tags/register', 'register_new_dynamic_tags');

// Debug on elementor/editor/after_enqueue_scripts to check in the editor context
add_action('elementor/editor/after_enqueue_scripts', function () {
    if (class_exists('Elementor\Plugin')) {
        $dynamic_tags = Elementor\Plugin::$instance->dynamic_tags;
        $tags = $dynamic_tags->get_tags();
        $all_tags = [];
        $pods_tags = [];
        $unique_groups = [];
        foreach ($tags as $tag_name => $tag) {
            if (is_object($tag) && method_exists($tag, 'get_group')) {
                $group = $tag->get_group();
                $title = $tag->get_title();
                $categories = $tag->get_categories();
                $all_tags[$tag_name] = [
                    'title' => $title,
                    'group' => $group,
                    'categories' => $categories
                ];
                if (is_array($group)) {
                    $unique_groups = array_merge($unique_groups, $group);
                } else {
                    $unique_groups[] = $group;
                }
                if ((is_array($group) && in_array('pods', $group)) || $group === 'pods') {
                    $pods_tags[$tag_name] = $title;
                }
            }
        }
        $unique_groups = array_unique($unique_groups);
        error_log('All Registered Tags on elementor/editor/after_enqueue_scripts: ' . json_encode($all_tags, JSON_PRETTY_PRINT));
        error_log('Pods Group Tags on elementor/editor/after_enqueue_scripts: ' . json_encode($pods_tags, JSON_PRETTY_PRINT));
        error_log('Unique Groups on elementor/editor/after_enqueue_scripts: ' . json_encode($unique_groups, JSON_PRETTY_PRINT));
    }
}, 999);

// Add JavaScript debugging to inspect the Dynamic Tags dropdown in the editor
add_action('elementor/editor/after_enqueue_scripts', function () {
    ?>
    <script>
    jQuery(document).ready(function ($) {
        elementor.hooks.addFilter('panel/dynamic-tags/source', function (sources) {
            console.log('Elementor Dynamic Tags Sources:', sources);
            // Log all groups and their tags
            Object.keys(sources).forEach(function (group) {
                console.log('Group:', group, 'Tags:', sources[group]);
            });
            // Log specifically the Pods group
            if (sources['pods']) {
                console.log('Pods Group Tags:', sources['pods']);
            }
            return sources;
        });
    });
    </script>
    <?php
});