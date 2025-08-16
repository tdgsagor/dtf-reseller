<?php

namespace DtfReseller;

if (!defined('ABSPATH')) {
    exit;
}

class DtfReseller_Price_Margin_Field
{

    public function __construct()
    {
        if (!is_main_site()) {
            add_action('add_meta_boxes', [$this, 'add_price_margin_meta_box']);
            add_action('save_post_product', [$this, 'save_price_margin_meta'], 20);

            add_filter('wp_insert_post_data', [$this, 'prevent_product_post_data_changes'], 10, 2);
            add_action('init', [$this, 'remove_wc_product_save'], 9);
        }
    }
    public function remove_wc_product_save()
    {
        remove_action('save_post_product', ['WC_Meta_Box_Product_Data', 'save'], 10);
    }

    public function add_price_margin_meta_box()
    {
        add_meta_box(
            'price_margin_box',
            __('Price Margin (%)', 'dtf-reseller'),
            [$this, 'render_price_margin_meta_box'],
            'product',
            'side',
            'default'
        );
    }

    public function render_price_margin_meta_box($post)
    {
        $value = get_post_meta($post->ID, '_ms_price_margin', true);
        wp_nonce_field('save_price_margin_meta', 'price_margin_meta_nonce');
        echo '<label for="price_margin_field">' . __('Enter Price Margin (%)', 'dtf-reseller') . '</label>';
        echo '<input type="number" id="price_margin_field" name="price_margin_field" value="' . esc_attr($value) . '" step="0.01" min="0" max="100" style="width: 100%;" />';
        echo '<p class="description">This is the profit percentage added on top of the original product price.</p>';
    }

    public function save_price_margin_meta($post_id)
    {
        // Only run on subsites
        if (is_main_site())
            return;

        unset($_POST['_regular_price'], $_POST['_sale_price'], $_POST['_price']);

        // Stop autosaves, invalid nonces, or users who can't edit
        if (
            !isset($_POST['price_margin_meta_nonce']) ||
            !wp_verify_nonce($_POST['price_margin_meta_nonce'], 'save_price_margin_meta') ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        // Save ONLY our field
        if (isset($_POST['price_margin_field']) && $_POST['price_margin_field'] !== '') {
            $margin = floatval($_POST['price_margin_field']);
            update_post_meta($post_id, '_ms_price_margin', $margin);

            $_original_product_price = get_post_meta($post_id, '_original_product_price', true);
            $_original_regular_price = get_post_meta($post_id, '_original_regular_price', true);
            $_original_sale_price = get_post_meta($post_id, '_original_sale_price', true);

            if (!empty($margin)) {
                $product_price = $_original_product_price * (1 + $margin / 100);
                $regular_price = $_original_regular_price * (1 + $margin / 100);
                $sale_price = $_original_sale_price * (1 + $margin / 100);

                $product = wc_get_product($post_id);


                $product->set_price($product_price);
                $product->set_regular_price($regular_price);
                $product->set_sale_price($sale_price);
                $product->save();
            }
        } else {
            delete_post_meta($post_id, '_ms_price_margin');
        }

        // NOW: Stop other meta from saving
        // This blocks WooCommerce and everything else from saving further data
        remove_action('save_post_product', 'WC_Meta_Box_Product_Data::save', 10); // WC stuff
        remove_all_actions('save_post_product'); // optional: nuke everything else (extreme)
    }

    public function prevent_product_post_data_changes($data, $postarr)
    {
        if (!is_main_site() && isset($data['post_type']) && $data['post_type'] === 'product' && !empty($postarr['ID'])) {
            $original_post = get_post($postarr['ID']);

            if ($original_post && $original_post->post_type === 'product' && $data['post_status'] !== 'trash') {
                // Keep original post data
                $data['post_title'] = $original_post->post_title;
                $data['post_content'] = $original_post->post_content;
                $data['post_excerpt'] = $original_post->post_excerpt;
                $data['post_name'] = $original_post->post_name;
                $data['post_status'] = $original_post->post_status;
            }
        }

        return $data;
    }
}
