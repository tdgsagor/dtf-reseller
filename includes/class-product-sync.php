<?php
namespace DtfReseller;

use DtfReseller\Admin\CommonFunctions;

class ProductSync
{
    public function __construct()
    {
        add_action('save_post_product', array($this, 'sync_product'), 10, 3);
    }

    public function sync_product($post_id, $post, $update)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;

        if (!is_main_site())
            return;

        // Ensure we're only syncing WooCommerce products
        if ($post->post_type !== 'product')
            return;

        // Only sync if product is published
        if ($post->post_status !== 'publish')
            return;

        // Get all subsites except the main site
        $sites = get_sites(['number' => 0]);
        $site_ids = [];

        foreach ($sites as $site) {
            if ((int) $site->blog_id !== get_main_site_id()) {
                $site_ids[] = (int) $site->blog_id;
            }
        }

        // Call the static function with a single product ID and the list of subsites
        CommonFunctions::sync_selected_products([$post_id], $site_ids);
    }
}