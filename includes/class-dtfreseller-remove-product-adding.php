<?php
namespace DtfReseller;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class DtfReseller_Remove_Product_Adding {

    public function __construct() {
        // Only run on sub-sites
        if (!is_main_site()) {
            add_action('admin_init', [$this, 'init_admin_restrictions']);
            add_filter('user_has_cap', [$this, 'disable_product_caps'], 10, 4);
        }
    }

    public function init_admin_restrictions() {
        add_filter('post_row_actions', [$this, 'remove_quick_edit_add_new'], 10, 2);
        add_action('admin_menu', [$this, 'remove_add_product_menu'], 99);
        add_action('admin_head', [$this, 'hide_add_product_buttons']);
        add_action('load-post-new.php', [$this, 'block_product_creation_page']);
        add_action('load-edit.php', [$this, 'block_product_import']);
    }

    public function remove_quick_edit_add_new($actions, $post) {
        if ($post->post_type === 'product') {
            unset($actions['inline hide-if-no-js']);
            unset($actions['edit']);
        }
        return $actions;
    }

    public function remove_add_product_menu() {
        remove_submenu_page('edit.php?post_type=product', 'post-new.php?post_type=product');
    }

    public function hide_add_product_buttons() {
        // echo '<style>
        //     .page-title-action, .wrap .bulkactions, .wp-heading-inline + a { display: none !important; }
        //     a[href*="import"], a[href*="export"] { display: none !important; }
        // </style>';
        echo '<style>
            .page-title-action, .wp-heading-inline + a { display: none !important; }
            a[href*="import"], a[href*="export"] { display: none !important; }
        </style>';
    }

    public function block_product_creation_page() {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'product') {
            wp_die(__('Product creation is disabled on sub-sites.', 'dtf-reseller'));
        }
    }

    public function block_product_import() {
        if (isset($_GET['import']) && $_GET['import'] === 'woocommerce') {
            wp_die(__('Importing products is disabled on sub-sites.', 'dtf-reseller'));
        }
    }

    public function disable_product_caps($allcaps, $caps, $args, $user) {
        // $blocked_caps = [
        //     'edit_products', 'edit_product', 'edit_others_products',
        //     'publish_products', 'delete_products', 'import'
        // ];
        $blocked_caps = [
            'edit_products', 'edit_product', 'edit_others_products',
            'publish_products', 'import'
        ];
        foreach ($blocked_caps as $cap) {
            $allcaps[$cap] = false;
        }
        return $allcaps;
    }
}
