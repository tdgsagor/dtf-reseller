<?php
// require_once plugin_dir_path(__FILE__) . '../vendor/stripe/init.php'; // If using Composer


if (!class_exists('Stripe\Stripe')) {
    // Include Stripe SDK only if it's not loaded already
    if (file_exists(plugin_dir_path(__FILE__) . '../vendor/stripe/init.php')) {
        require_once plugin_dir_path(__FILE__) . '../vendor/stripe/init.php'; // If using Composer
    } else {
        // Optional: handle case where vendor/autoload.php doesn't exist (show warning or fallback)
        error_log('Stripe SDK is not available. Please install Stripe via Composer or your theme/plugin.');
    }
}
