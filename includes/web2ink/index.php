<?php
require_once DTFRESELLER_SYNC_PATH . 'includes/web2ink/class-web2ink-pricing-override.php';
require_once DTFRESELLER_SYNC_PATH . 'includes/web2ink/class-web2ink-import-override.php';
require_once DTFRESELLER_SYNC_PATH . 'includes/web2ink/db-setup/db-setup-index.php';

$reseller_margin = get_option('dtfr_default_product_margin', 0);
function price_with_margin($prev_price)
{
    global $reseller_margin;
    $new_price = $prev_price + ($prev_price * ($reseller_margin / 100));
    return $prev_price;
}