<?php
namespace DtfReseller;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class DtfReseller_Remove_Product_Adding
{
    private $_product_add_permission = 0;
    public function __construct()
    {
        // Only run on sub-sites
        if (!is_main_site()) {
            add_action('admin_init', [$this, 'init_admin_restrictions']);
            add_filter('user_has_cap', [$this, 'disable_product_caps'], 10, 4);
        }

        add_action('init', [$this, 'check_subscription_status']);
    }

    public function check_subscription_status()
    {
        $blog_id = get_current_blog_id();
        $admins = get_users([
            'blog_id' => $blog_id,
            'role' => 'administrator',
            'number' => 1
        ]);
        $admin_user = !empty($admins) ? $admins[0] : null;
        if ($admin_user) {
            switch_to_blog(get_main_site_id());
            if (function_exists('wcs_user_has_subscription')) {
                $subscriptions = wcs_get_users_subscriptions($admin_user->ID);
                foreach ($subscriptions as $subscription_id => $subscription) {
                    if ($subscription->get_status() === 'active') {
                        foreach ($subscription->get_items() as $item_id => $item) {
                            $product_id = $item->get_product_id();
                            $has_permission = get_post_meta($product_id, '_product_add_permission', true);
                            if ($has_permission == 1) {
                                $this->_product_add_permission = 1;
                                break;
                            }
                        }
                    }
                }
            }
            restore_current_blog();
        }
    }

    public function init_admin_restrictions()
    {
        if (!$this->_product_add_permission) {
            add_filter('post_row_actions', [$this, 'remove_quick_edit_add_new'], 10, 2);
            add_action('admin_menu', [$this, 'remove_add_product_menu'], 99);
            add_action('admin_head', [$this, 'hide_add_product_buttons']);
            add_action('load-post-new.php', [$this, 'block_product_creation_page']);
            add_action('load-edit.php', [$this, 'block_product_import']);
        }
    }

    public function remove_quick_edit_add_new($actions, $post)
    {
        if ($post->post_type === 'product') {
            unset($actions['inline hide-if-no-js']);
            unset($actions['edit']);
        }
        return $actions;
    }

    public function remove_add_product_menu()
    {
        remove_submenu_page('edit.php?post_type=product', 'post-new.php?post_type=product');
    }

    public function hide_add_product_buttons()
    {
        echo '<style>
            .page-title-action, .wp-heading-inline + a { display: none !important; }
            a[href*="import"], a[href*="export"] { display: none !important; }
        </style>';
    }

    public function block_product_creation_page()
    {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'product' && !$this->_product_add_permission) {
            wp_die(__('Product creation is disabled on sub-sites.', 'dtf-reseller'));
        }
    }

    public function block_product_import()
    {
        if (isset($_GET['import']) && $_GET['import'] === 'woocommerce' && !$this->_product_add_permission) {
            wp_die(__('Importing products is disabled on sub-sites.', 'dtf-reseller'));
        }
    }

    public function disable_product_caps($allcaps, $caps, $args, $user)
    {
        // $blocked_caps = [
        //     'edit_products', 'edit_product', 'edit_others_products',
        //     'publish_products', 'delete_products', 'import'
        // ];

        $blocked_caps = [];
        if (!$this->_product_add_permission) {
            $blocked_caps = [
                'publish_products',
                'import'
            ];
        }

        foreach ($blocked_caps as $cap) {
            $allcaps[$cap] = false;
        }
        return $allcaps;
    }
}
