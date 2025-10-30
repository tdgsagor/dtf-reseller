<?php
namespace DtfReseller\Admin;

class CommonFunctions
{
    public function __construct()
    {
        add_action('network_admin_notices', array($this, 'show_admin_notices'));
    }

    private static $admin_notices = array();

    public static function add_notice($type, $message)
    {
        self::$admin_notices[] = array(
            'type' => $type,
            'message' => $message
        );
    }

    public function show_admin_notices()
    {
        foreach (self::$admin_notices as $notice) {
            printf(
                '<div class="%s"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }
    public static function log_error($message, $context = array())
    {
        $log_file = plugin_dir_path(__FILE__) . 'log.txt';
        $timestamp = current_time('mysql');
        $formatted_message = sprintf(
            "[%s] %s %s\n",
            $timestamp,
            $message,
            !empty($context) ? ' Context: ' . json_encode($context) : ''
        );

        error_log($formatted_message, 3, $log_file);
    }
    public static function get_product_id_by_original_id($original_id)
    {
        $args = array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_original_product_id',
                    'value' => $original_id,
                    'compare' => '='
                )
            )
        );

        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            return $query->posts[0]->ID;
        }

        return false;
    }
    public static function copy_attachment_with_data($attachment, $source_file_path, $parent_post_id)
    {
        if (!$attachment || !file_exists($source_file_path)) {
            CommonFunctions::log_error('Invalid attachment data', array(
                'attachment' => $attachment,
                'file_path' => $source_file_path
            ));
            return false;
        }

        $upload_dir = wp_upload_dir();
        $file_name = basename($source_file_path);
        $new_file = $upload_dir['path'] . '/' . wp_unique_filename($upload_dir['path'], $file_name);

        // Ensure upload directory exists
        wp_mkdir_p($upload_dir['path']);

        if (copy($source_file_path, $new_file)) {
            $attachment_data = array(
                'post_mime_type' => $attachment->post_mime_type,
                'post_title' => sanitize_file_name($attachment->post_title),
                'post_content' => $attachment->post_content,
                'post_status' => 'inherit',
                'guid' => $upload_dir['url'] . '/' . basename($new_file)
            );

            $new_attachment_id = wp_insert_attachment($attachment_data, $new_file, $parent_post_id);

            if (!is_wp_error($new_attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($new_attachment_id, $new_file);
                wp_update_attachment_metadata($new_attachment_id, $attach_data);

                set_post_thumbnail($parent_post_id, $new_attachment_id);

                CommonFunctions::log_error('Successfully copied attachment', array(
                    'new_attachment_id' => $new_attachment_id,
                    'parent_post_id' => $parent_post_id
                ));

                return $new_attachment_id;
            } else {
                CommonFunctions::log_error('Failed to insert attachment', array(
                    'error' => $new_attachment_id->get_error_message()
                ));
            }
        } else {
            CommonFunctions::log_error('Failed to copy file', array(
                'source' => $source_file_path,
                'destination' => $new_file
            ));
        }

        return false;
    }
    public static function sync_selected_products($product_ids, $site_ids)
    {
        $result = [
            'success' => false,
            'count' => 0,
            'sites' => [],
            'message' => ''
        ];

        try {
            switch_to_blog(get_main_site_id());

            $products = [];
            $terms_meta_map = [];

            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    // $terms = wp_get_object_terms($product_id, ['product_cat', 'product_tag', 'pa_color', 'pa_size']);
                    $taxonomies = get_object_taxonomies('product');
                    $terms = wp_get_object_terms($product_id, $taxonomies);

                    // Store all term meta keyed by slug+taxonomy
                    foreach ($terms as $term) {
                        $key = $term->slug . '|' . $term->taxonomy;

                        if (!isset($terms_meta_map[$key])) {
                            $taxonomy_info = get_term_by('id', $term->term_id, $term->taxonomy);
                            $terms_meta_map[$key] = [
                                'term_id' => $term->term_id, // main site ID
                                'slug' => $term->slug,
                                'name' => $term->name,
                                'taxonomy' => $term->taxonomy,
                                'description' => $taxonomy_info ? $taxonomy_info->description : '',
                                'meta' => get_term_meta($term->term_id),
                            ];
                        }
                    }

                    $gallery_ids_str = get_post_meta($product_id, '_product_image_gallery', true);
                    $gallery_ids = !empty($gallery_ids_str) ? explode(',', $gallery_ids_str) : [];

                    $products[] = [
                        'product_id' => $product_id,
                        'product' => $product,
                        'post' => get_post($product_id),
                        'meta' => get_post_meta($product_id),
                        'terms' => $terms,
                        'thumbnail_id' => get_post_thumbnail_id($product_id),
                        'gallery_ids' => $gallery_ids,
                        'variations' => $product->is_type('variable') ? $product->get_children() : []
                    ];
                }
            }

            // file_put_contents(__DIR__ . '/log.txt', 'products: ' . print_r($products, true) . PHP_EOL, FILE_APPEND);

            restore_current_blog();

            if (empty($products)) {
                throw new \Exception('No valid products found.');
            }

            foreach ($site_ids as $site_id) {
                /* Syncing Attributes Started */
                switch_to_blog(get_main_site_id());

                // Get all product attributes
                global $wpdb;
                $attributes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies");

                // Get all taxonomies (attribute terms)
                $taxonomies = [];
                foreach ($attributes as $attribute) {
                    $taxonomy = 'pa_' . $attribute->attribute_name;
                    $taxonomies[$taxonomy] = get_terms([
                        'taxonomy' => $taxonomy,
                        'hide_empty' => false,
                    ]);
                }

                restore_current_blog();

                // Switch to subsite
                switch_to_blog($site_id);

                // Sync attribute taxonomies
                foreach ($attributes as $attribute) {
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
                        $attribute->attribute_name
                    ));

                    if (!$exists) {
                        $wpdb->insert(
                            "{$wpdb->prefix}woocommerce_attribute_taxonomies",
                            [
                                'attribute_name' => $attribute->attribute_name,
                                'attribute_label' => $attribute->attribute_label,
                                'attribute_type' => $attribute->attribute_type,
                                'attribute_orderby' => $attribute->attribute_orderby,
                                'attribute_public' => $attribute->attribute_public,
                            ]
                        );
                    }
                }

                // Sync terms
                foreach ($taxonomies as $taxonomy => $terms) {
                    if (!taxonomy_exists($taxonomy)) {
                        register_taxonomy($taxonomy, 'product');
                    }

                    foreach ($terms as $term) {
                        if (!term_exists($term->name, $taxonomy)) {
                            wp_insert_term($term->name, $taxonomy, [
                                'slug' => $term->slug,
                                'description' => $term->description,
                                'parent' => $term->parent,
                            ]);
                        }
                    }
                }
                do_action('woocommerce_attribute_taxonomy_updated');
                delete_transient('wc_attribute_taxonomies');
                do_action('woocommerce_attribute_taxonomy_updated');

                restore_current_blog();
                /* Syncing Attributes Ended */


                switch_to_blog($site_id);

                foreach ($products as $p) {
                    try {
                        $source_product = $p['product'];
                        $source_post = $p['post'];
                        $source_meta = $p['meta'];

                        $new_post = [
                            'post_title' => $source_post->post_title,
                            'post_content' => $source_post->post_content,
                            'post_excerpt' => $source_post->post_excerpt,
                            'post_status' => 'publish',
                            'post_type' => 'product',
                            'post_author' => get_current_user_id(),
                        ];

                        $new_product_id = wp_insert_post($new_post);
                        if (is_wp_error($new_product_id) || !$new_product_id) {
                            continue;
                        }

                        foreach ($source_meta as $key => $values) {
                            if ($key === '_thumbnail_id')
                                continue;

                            foreach ($values as $value) {
                                update_post_meta($new_product_id, $key, maybe_unserialize($value));
                            }
                        }

                        update_post_meta($new_product_id, '_original_product_id', $p['product_id']);

                        if (!$source_product || !is_a($source_product, 'WC_Product')) {
                            return;
                        }

                        $product_price = floatval($source_product->get_price() ?? 0);
                        $regular_price = floatval($source_product->get_regular_price() ?? 0);
                        $sale_price = floatval($source_product->get_sale_price() ?? 0);

                        update_post_meta($new_product_id, '_original_product_price', $product_price);
                        update_post_meta($new_product_id, '_original_regular_price', $regular_price);
                        update_post_meta($new_product_id, '_original_sale_price', $sale_price);

                        $margin = floatval(get_option('dtfr_default_product_margin') ?? 0);
                        update_post_meta($new_product_id, '_ms_price_margin', $margin);

                        // if ($margin > 0) {
                        //     $with_margin_product_price = $product_price * (1 + $margin / 100);
                        //     $with_margin_regular_price = $regular_price * (1 + $margin / 100);
                        //     $with_margin_sale_price = $sale_price * (1 + $margin / 100);

                        //     $new_product = wc_get_product($new_product_id);

                        //     if ($new_product) {
                        //         $new_product->set_price($with_margin_product_price);
                        //         $new_product->set_regular_price($with_margin_regular_price);

                        //         if ($sale_price > 0) {
                        //             $new_product->set_sale_price($with_margin_sale_price);
                        //         } else {
                        //             $new_product->set_sale_price('');
                        //         }

                        //         $new_product->save();
                        //     }
                        // }

                        // Sync terms and term meta
                        foreach ($p['terms'] as $term) {
                            $term_key = $term->slug . '|' . $term->taxonomy;
                            if (!isset($terms_meta_map[$term_key]))
                                continue;

                            $t = $terms_meta_map[$term_key];

                            $term_exists = term_exists($t['slug'], $t['taxonomy']);
                            if (!$term_exists) {
                                $term_exists = wp_insert_term($t['name'], $t['taxonomy'], [
                                    'slug' => $t['slug'],
                                    'description' => $t['description'],
                                ]);
                            }

                            if (!is_wp_error($term_exists) && isset($term_exists['term_id'])) {
                                $new_term_id = (int) $term_exists['term_id'];

                                wp_update_term($new_term_id, $t['taxonomy'], [
                                    'description' => $t['description'],
                                ]);

                                wp_set_object_terms($new_product_id, [$new_term_id], $t['taxonomy'], true);

                                // Now copy term meta
                                $existing = get_term_meta($new_term_id);
                                foreach ($t['meta'] as $meta_key => $meta_values) {
                                    foreach ($meta_values as $meta_value) {
                                        if (!isset($existing[$meta_key]) || !in_array($meta_value, $existing[$meta_key], true)) {
                                            update_term_meta($new_term_id, $meta_key, maybe_unserialize($meta_value));
                                        }
                                    }
                                }
                            }
                        }

                        // Copy featured image
                        if ($p['thumbnail_id']) {
                            switch_to_blog(get_main_site_id());
                            $file_path = get_attached_file($p['thumbnail_id']);
                            $attachment = get_post($p['thumbnail_id']);
                            switch_to_blog($site_id);

                            if ($file_path && $attachment) {
                                $new_thumb_id = self::copy_attachment_with_data($attachment, $file_path, $new_product_id);
                                if ($new_thumb_id) {
                                    update_post_meta($new_product_id, '_thumbnail_id', $new_thumb_id);
                                }
                            }
                        }

                        if (!empty($p['gallery_ids'])) { // assume $p['gallery_ids'] is an array of attachment IDs
                            $new_gallery_ids = [];

                            foreach ($p['gallery_ids'] as $gallery_id) {
                                if ($gallery_id) {
                                    switch_to_blog(get_main_site_id());
                                    $file_path = get_attached_file($gallery_id);
                                    $attachment = get_post($gallery_id);
                                    switch_to_blog($site_id);

                                    if ($file_path && $attachment) {
                                        // Copy the attachment to the new site
                                        $new_gallery_id = self::copy_attachment_with_data($attachment, $file_path, $new_product_id);
                                        if ($new_gallery_id) {
                                            $new_gallery_ids[] = $new_gallery_id;
                                        }
                                    }
                                }
                            }

                            if (!empty($new_gallery_ids)) {
                                // Save as comma-separated string in _product_image_gallery
                                update_post_meta($new_product_id, '_product_image_gallery', implode(',', $new_gallery_ids));
                            }
                        }

                        // Product type
                        $product_type = $source_product->get_type();
                        wp_set_object_terms($new_product_id, $product_type, 'product_type');

                        // Attributes
                        $attributes = maybe_unserialize(get_post_meta($p['product_id'], '_product_attributes', true));
                        if (!empty($attributes)) {
                            update_post_meta($new_product_id, '_product_attributes', $attributes);
                        }

                        // Variations
                        if ($product_type === 'variable' && !empty($p['variations'])) {
                            foreach ($p['variations'] as $variation_id) {
                                switch_to_blog(get_main_site_id());
                                $variation_post = get_post($variation_id);
                                $variation_meta = get_post_meta($variation_id);
                                switch_to_blog($site_id);

                                $new_variation_post = [
                                    'post_title' => $variation_post->post_title,
                                    'post_name' => 'product-' . $new_product_id . '-variation',
                                    'post_status' => 'publish',
                                    'post_type' => 'product_variation',
                                    'post_parent' => $new_product_id,
                                    'menu_order' => $variation_post->menu_order
                                ];

                                $new_variation_id = wp_insert_post($new_variation_post);
                                if (is_wp_error($new_variation_id) || !$new_variation_id) {
                                    continue;
                                }

                                foreach ($variation_meta as $meta_key => $meta_values) {
                                    foreach ($meta_values as $meta_value) {
                                        if ($meta_key === '_thumbnail_id') {
                                            $original_thumb_id = maybe_unserialize($meta_value);

                                            if ($original_thumb_id) {
                                                switch_to_blog(get_main_site_id());
                                                $file_path = get_attached_file($original_thumb_id);
                                                $attachment = get_post($original_thumb_id);
                                                switch_to_blog($site_id);

                                                if ($file_path && $attachment) {
                                                    $new_thumb_id = self::copy_attachment_with_data($attachment, $file_path, $new_variation_id);
                                                    if ($new_thumb_id) {
                                                        // update_post_meta($new_variation_id, '_thumbnail_id', $new_thumb_id);
                                                    }
                                                }
                                            }
                                        } else {
                                            update_post_meta($new_variation_id, $meta_key, maybe_unserialize($meta_value));
                                        }
                                    }
                                }


                                update_post_meta($new_variation_id, '_original_variation_id', $variation_id);

                                switch_to_blog(get_main_site_id());
                                $product_price = floatval(get_post_meta($variation_id, '_price', true) ?? 0);
                                $regular_price = floatval(get_post_meta($variation_id, '_regular_price', true) ?? 0);
                                $sale_price = floatval(get_post_meta($variation_id, '_sale_price', true) ?? 0);
                                switch_to_blog($site_id);

                                // Store original prices
                                update_post_meta($new_variation_id, '_original_product_price', $product_price);
                                update_post_meta($new_variation_id, '_original_regular_price', $regular_price);
                                update_post_meta($new_variation_id, '_original_sale_price', $sale_price);

                                // Apply margin if any
                                $margin = floatval(get_option('dtfr_default_product_margin') ?? 0);
                                update_post_meta($new_variation_id, '_ms_price_margin', $margin);

                                // if ($margin > 0) {
                                //     $with_margin_product_price = $product_price * (1 + $margin / 100);
                                //     $with_margin_regular_price = $regular_price * (1 + $margin / 100);
                                //     $with_margin_sale_price = $sale_price * (1 + $margin / 100);

                                //     if (!empty($with_margin_product_price) && $with_margin_product_price > 0) {
                                //         update_post_meta($new_variation_id, '_price', $with_margin_product_price);
                                //     }

                                //     if (!empty($with_margin_regular_price) && $with_margin_regular_price > 0) {
                                //         update_post_meta($new_variation_id, '_regular_price', $with_margin_regular_price);
                                //     }

                                //     if (!empty($with_margin_sale_price) && $with_margin_sale_price > 0) {
                                //         update_post_meta($new_variation_id, '_sale_price', $with_margin_sale_price);
                                //     }
                                // }
                            }
                        }

                        // Final cleanup
                        wc_delete_product_transients($new_product_id);
                        $new_product = wc_get_product($new_product_id);
                        do_action('woocommerce_update_product', $new_product_id, $new_product);

                        $result['count']++;
                    } catch (\Exception $e) {
                        self::log_error('Product sync error on subsite ' . $site_id, [
                            'product_id' => $p['product_id'],
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                $result['sites'][] = $site_id;
                restore_current_blog();
            }

            $result['success'] = true;
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            self::log_error('Global sync error', [
                'message' => $e->getMessage(),
                'product_ids' => $product_ids,
                'site_ids' => $site_ids
            ]);
            restore_current_blog();
        }

        return $result;
    }
}