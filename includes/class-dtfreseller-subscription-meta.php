<?php

namespace DtfReseller;

if (!defined('ABSPATH')) {
    exit;
}

class DtfReseller_Subscription_Meta
{

    public function __construct()
    {
        if (is_main_site()) {
            // Add custom meta box
            add_action('add_meta_boxes', [$this, 'add_subscription_checkbox_meta_box']);
            add_action('save_post_product', [$this, 'save_subscription_checkbox_meta'], 20);
            add_action('before_delete_post', [$this, 'delete_subscription_checkbox_meta']);

            // Prevent unwanted changes
            add_filter('wp_insert_post_data', [$this, 'prevent_product_post_data_changes'], 10, 2);
            add_action('init', [$this, 'remove_wc_product_save'], 9);
        }
    }

    public function remove_wc_product_save()
    {
        remove_action('save_post_product', ['WC_Meta_Box_Product_Data', 'save'], 10);
    }

    /**
     * ----------------------
     * SUBSCRIPTION CHECKBOX META
     * ----------------------
     */
    public function add_subscription_checkbox_meta_box()
    {
        add_meta_box(
            'subscription_checkbox_box',
            __('Product Permissions', 'dtf-reseller'),
            [$this, 'render_subscription_checkbox_meta_box'],
            'product',
            'side',
            'default'
        );
    }

    public function render_subscription_checkbox_meta_box($post)
    {
        $product = wc_get_product($post->ID);

        // Only show for subscription products
        if (!$product || $product->get_type() != 'subscription') {
            echo '<p>' . __('This meta applies to Subscription products only.', 'dtf-reseller') . '</p>';
            return;
        }

        $checked = get_post_meta($post->ID, '_product_add_permission', true);
        wp_nonce_field('save_subscription_checkbox_meta', 'subscription_checkbox_meta_nonce');

        // echo '<p>'. $checked . '</p>';
        echo '<label for="_product_add_permission">';
        echo '<input type="checkbox" id="_product_add_permission" name="_product_add_permission" value="1" ' . checked($checked, 1, false) . ' />';
        echo ' ' . __('Product Add Permission', 'dtf-reseller') . '</label>';
    }

    public function save_subscription_checkbox_meta($post_id)
    {
        if (!is_main_site()) return;

        if (
            !isset($_POST['subscription_checkbox_meta_nonce']) ||
            !wp_verify_nonce($_POST['subscription_checkbox_meta_nonce'], 'save_subscription_checkbox_meta') ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product || $product->get_type() != 'subscription') {
            return; // only save if subscription product
        }

        if (isset($_POST['_product_add_permission']) && $_POST['_product_add_permission'] === '1') {
            update_post_meta($post_id, '_product_add_permission', 1);
        } else {
            update_post_meta($post_id, '_product_add_permission', 0);
        }
    }

    public function delete_subscription_checkbox_meta($post_id)
    {
        $post = get_post($post_id);

        if ($post && $post->post_type === 'product') {
            delete_post_meta($post_id, '_product_add_permission');
        }
    }

    /**
     * ----------------------
     * LOCK POST CONTENT
     * ----------------------
     */
    public function prevent_product_post_data_changes($data, $postarr)
    {
        if (is_main_site() && isset($data['post_type']) && $data['post_type'] === 'product' && !empty($postarr['ID'])) {
            $original_post = get_post($postarr['ID']);

            if ($original_post && $original_post->post_type === 'product' && $data['post_status'] !== 'trash') {
                $data['post_title']   = $original_post->post_title;
                $data['post_content'] = $original_post->post_content;
                $data['post_excerpt'] = $original_post->post_excerpt;
                $data['post_name']    = $original_post->post_name;
                $data['post_status']  = $original_post->post_status;
            }
        }
        return $data;
    }
}
