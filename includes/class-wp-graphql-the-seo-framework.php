<?php

namespace WPGraphQL\Extensions;

use WPGraphQL\Data\DataSource;

class TheSeoFramework
{
    /**
     * TaxQuery constructor.
     *
     * This hooks the plugin into the WPGraphQL Plugin
     *
     * @since 0.0.1
     */
    public function __construct()
    {

        $this->add_dependency_check();

        /**
         * Setup plugin constants
         * @since 0.0.1
         */
        $this->setup_constants();

        /**
         * Included required files
         * @since 0.0.1
         */
        $this->actions();
    }

    /**
     * Setup plugin constants.
     *
     * @return void
     * @since 0.0.1
     */
    private function setup_constants()
    {

        // Plugin version.
        if (! defined('WPGRAPHQL_THE_SEO_FRAMEWORK_VERSION')) {
            define('WPGRAPHQL_THE_SEO_FRAMEWORK_VERSION', '0.1.1');
        }

        // Plugin Folder Path.
        if (! defined('WPGRAPHQL_THE_SEO_FRAMEWORK_PLUGIN_DIR')) {
            define('WPGRAPHQL_THE_SEO_FRAMEWORK_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }

        // Plugin Folder URL.
        if (! defined('WPGRAPHQL_THE_SEO_FRAMEWORK_PLUGIN_URL')) {
            define('WPGRAPHQL_THE_SEO_FRAMEWORK_PLUGIN_URL', plugin_dir_url(__FILE__));
        }

        // Plugin Root File.
        if (! defined('WPGRAPHQL_THE_SEO_FRAMEWORK_PLUGIN_FILE')) {
            define('WPGRAPHQL_THE_SEO_FRAMEWORK_PLUGIN_FILE', __FILE__);
        }
    }

    /**
     * Check if WPGraphQL is installed and activated.
     */
    public function add_dependency_check()
    {
        add_action(
            'plugins_loaded',
            function () {
                if (! class_exists('\WPGraphQL')) {
                    add_action(
                        'admin_notices',
                        function () {
                            ?>
                            <div class="error notice">
                                <p>
                                    <?php
                                    esc_html__(
                                        'WPGraphQL must be installed and activated for "WPGraphQL CORS" to work',
                                        'wp-graphql-cors'
                                    );
                                    ?>
                                </p>
                            </div>
                            <?php
                        }
                    );
                }
            }
        );
    }

    /**
     * Add actions.
     */
    private function actions()
    {
        add_action('graphql_register_types', [$this, 'add_the_seo_framework_fields'], 99);
    }

    public function add_the_seo_framework_fields()
    {
        $post_types = \WPGraphQL::get_allowed_post_types();
        $taxonomies = \WPGraphQL::get_allowed_taxonomies();

        $meta_fields = [
            'title' => [
                'meta_key' => '_genesis_title',
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->get_title($args);
                },
                'type' => 'String',
                'description' => 'SEO title'
            ],
            'description' => [
                'meta_key' => '_genesis_description',
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->get_description($args);
                },
                'type' => 'String',
                'description' => 'SEO description'
            ],
            'canonicalUrl' => [
                'meta_key' => '_genesis_canonical_uri',
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->create_canonical_url(array_merge($args, [
                        'get_custom_field' => true
                    ]));
                },
                'type' => 'String',
                'description' => 'Canonical URL'
            ],
            'socialImage' => [
                'type' => 'MediaItem',
                'seo_cb' => function ($args, $context) {
                    // get_image_details returns an array, but we only need the most recent selected image
                    $images = the_seo_framework()->get_image_details($args, true);
                    if (empty($images)) {
                        return null;
                    }

                    return DataSource::resolve_post_object((int) the_seo_framework()->get_image_details($args,
                        true)[0]['id'], $context);
                },
            ],
            'openGraphTitle' => [
                'type' => 'String',
                'description' => 'Open Graph title',
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->get_open_graph_title($args);
                },
            ],
            'openGraphDescription' => [
                'type' => 'String',
                'description' => 'Open Graph description',
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->get_open_graph_description($args);
                },
            ],
            'openGraphType' => [
                'type' => 'String',
                'description' => "Open Graph type ('website', 'article', ...)",
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->get_og_type($args);
                }
            ],
            'twitterTitle' => [
                'type' => 'String',
                'description' => 'Twitter title',
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->get_twitter_title($args);
                },
            ],
            'twitterDescription' => [
                'type' => 'String',
                'description' => 'Twitter description',
                'seo_cb' => function ($args, $context) {
                    return the_seo_framework()->get_twitter_description($args);
                },
            ],
        ];

        $setting_fields = [
            'separator' => [
                'type' => 'String',
                'description' => 'Title separator setting for seo titles',
                'seo_cb' => function ($post_id, $context) {
                    return the_seo_framework()->get_separator();
                }
            ]
        ];

        register_graphql_object_type('SEO', [
            'fields' => $meta_fields,
            'eagerlyLoadType' => false,
        ]);

        register_graphql_object_type('SeoSettings', [
            'fields' => $setting_fields,
            'eagerlyLoadType' => false,
        ]);

        register_graphql_field('RootQuery', 'seoSettings', [
            'type' => 'SeoSettings',
            'description' => __('The SEO Framework settings', 'wp-graphql'),
            'resolve' => function ($root, $args, $context, $info) use ($setting_fields) {
                $seoSettings = array();

                foreach ($setting_fields as $key => $setting_field) {
                    $seoSettings[$key] = $setting_field['seo_cb'];
                }

                return ! empty($seoSettings) ? $seoSettings : null;
            }
        ]);

        if (! empty($post_types) && is_array($post_types)) {
            foreach ($post_types as $post_type) {
                $post_type_object = get_post_type_object($post_type);

                if (isset($post_type_object->graphql_single_name)) {
                    $single_name = $post_type_object->graphql_single_name;
                    register_graphql_field($single_name, 'seo', [
                        'type' => 'SEO',
                        'description' => __('The SEO Framework data of the '.$post_type_object->graphql_single_name,
                            'wp-graphql'),
                        'resolve' => function ($root, $args, $context, $info) use ($meta_fields, $single_name) {
                            $post_id = $root->ID;

                            $seo = array();

                            foreach ($meta_fields as $key => $meta_field) {
                                if (! empty($meta_field['seo_cb'])) {
                                    $seo[$key] = $meta_field['seo_cb']($post_id, $context);
                                }
                            }

                            return ! empty($seo) ? $seo : null;
                        }
                    ]);
                }
            }
        }

        if (! empty($taxonomies) && is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $tax_object = get_taxonomy($taxonomy);
                if (isset($tax_object->graphql_single_name)) {
                    $single_name = $tax_object->graphql_single_name;
                    register_graphql_field($single_name, 'seo', [
                        'type' => 'SEO',
                        'description' => __('The SEO Framework data of the '.$single_name, 'wp-graphql'),
                        'resolve' => function ($root, $args, $context, $info) use ($meta_fields, $taxonomy) {
                            $tsf_args = array(
                                'id' => $root->term_id,
                                'taxonomy' => $taxonomy
                            );

                            $seo = array();

                            foreach ($meta_fields as $key => $meta_field) {
                                if (! empty($meta_field['seo_cb'])) {
                                    $seo[$key] = $meta_field['seo_cb']($tsf_args, $context);
                                }
                            }

                            return ! empty($seo) ? $seo : null;
                        }
                    ]);
                }
            }
        }
    }
}
