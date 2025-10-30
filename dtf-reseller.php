<?php

use DtfReseller\DtfReseller_Price_Margin_Field;
use DtfReseller\DtfReseller_Remove_Product_Adding;
use DtfReseller\DtfReseller_Subscription_Meta;
/**
 * Plugin Name: DTF Reseller
 * Description: Sync products from main site to subsites and orders from subsites to main site
 * Version: 1.0.7
 * Author: TheDevGarden
 * Network: true
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DTFRESELLER_SYNC_PATH', plugin_dir_path(__FILE__));
define('DTFRESELLER_SYNC_URL', plugin_dir_url(__FILE__));

require_once DTFRESELLER_SYNC_PATH . 'includes/class-dtfreseller-sync.php';
require_once DTFRESELLER_SYNC_PATH . 'includes/class-dtfreseller-remove-product-adding.php';
require_once DTFRESELLER_SYNC_PATH . 'includes/class-dtfreseller-price-margin-field.php';
require_once DTFRESELLER_SYNC_PATH . 'includes/class-dtfreseller-subscription-meta.php';
require_once DTFRESELLER_SYNC_PATH . 'includes/class-dtfreseller-updater.php';
require_once DTFRESELLER_SYNC_PATH . 'includes/web2ink/index.php';
/* For Common Function */
require_once DTFRESELLER_SYNC_PATH . 'admin/common-functions.php';

// Load private credentials if present (not committed; see .gitignore)
if (file_exists(DTFRESELLER_SYNC_PATH . 'credentials.php')) {
    require_once DTFRESELLER_SYNC_PATH . 'credentials.php';
}

add_action('wp_enqueue_scripts', 'my_custom_checkout_script');
function my_custom_checkout_script()
{
    if (is_checkout()) {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/');
        wp_enqueue_script('custom-checkout', DTFRESELLER_SYNC_URL . 'assets/js/custom-checkout.js', ['jquery'], null, true);
        wp_localize_script('custom-checkout', 'my_custom_checkout_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'stripe_key' => get_blog_option(1, 'smc_stripe_publishable_key'),
        ]);
    }
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'DtfReseller\\';
    $base_dir = DTFRESELLER_SYNC_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('\\', '-', $relative_class)) . '.php';
    error_log('Attempting to load: ' . $file);

    if (file_exists($file)) {
        require $file;
    } else {
        error_log('File not found: ' . $file);
    }
});

function dtfreseller_sync_init()
{
    if (!is_multisite()) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>DTF Reseller requires WordPress Multisite to be enabled.</p></div>';
        });
        return;
    }

    new DtfReseller\DtfReseller();
    new DtfReseller_Remove_Product_Adding();
    new DtfReseller_Price_Margin_Field();
    new DtfReseller_Subscription_Meta();
}

add_action('plugins_loaded', 'dtfreseller_sync_init');

if (is_admin()) {
    if (!defined('GH_REQUEST_URI')) {
        define('GH_REQUEST_URI', 'https://api.github.com/repos/%s/%s/releases');
    }

    // Only initialize updater when required credentials are available
    if (
        defined('GHPU_USERNAME') &&
        defined('GHPU_REPOSITORY') &&
        defined('GHPU_AUTH_TOKEN') && GHPU_AUTH_TOKEN
    ) {
        $updater = new GhPluginUpdater(__FILE__);
        $updater->init();
    }
}

add_action('template_redirect', function () {
    if (is_main_site()) {
        return; // allow main site
    }

    $status = get_blog_option(get_current_blog_id(), 'dtfreseller_status', 'Active');

    if ($status === 'Inactive') {
        wp_die(__('This site is inactive.', 'dtfreseller'), '', ['response' => 403]);
    }
});