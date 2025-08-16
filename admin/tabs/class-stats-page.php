<?php
namespace DtfReseller\Admin\Tabs;

use WC_Order;

class StatsPage
{
    public function __construct()
    {
        require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/orders/class-orders-list.php';
        require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/orders/class-order-edit.php';

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets()
    {
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('dtfreseller-stats', plugin_dir_url(__FILE__) . 'assets/js/stats.js', ['chartjs'], '1.0', true);

        wp_enqueue_style(
            'dtfreseller-stats',
            plugin_dir_url(__FILE__) . 'assets/css/stats.css',
            [],
            '1.0'
        );
    }

    public function render()
    {
        $sites = get_sites(['site__not_in' => [get_main_site_id()]]);

        $stats = [
            'total_sales' => 0,
            'total_orders' => 0,
            'total_profit' => 0,
            'total_resellers' => count($sites),
            'active_resellers' => 0,
            'products_in_use' => [],
            'product_reseller_map' => [],
            'revenue_by_date' => [],
            'orders_by_date' => [],
            'product_revenue' => [],
            'product_profit' => [],
            'reseller_revenue' => [],
            'markup_per_product' => [],
            'markup_distribution' => [],
            'cold_products' => [],
            'reseller_activity' => [
                'active' => 0,
                'idle' => 0,
                'inactive' => 0
            ]
        ];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $orders = wc_get_orders([
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => -1,
            ]);

            $has_order = false;
            $latest_order_timestamp = 0;

            foreach ($orders as $order) {
                if (!$order instanceof WC_Order)
                    continue;

                $order_total = (float) $order->get_total();
                $order_date = $order->get_date_created();
                $timestamp = strtotime($order_date);
                $date_str = date('Y-m-d', $timestamp);

                $stats['total_sales'] += $order_total;
                $stats['total_orders']++;

                // Revenue over time
                $stats['revenue_by_date'][$date_str] = ($stats['revenue_by_date'][$date_str] ?? 0) + $order_total;
                $stats['orders_by_date'][$date_str] = ($stats['orders_by_date'][$date_str] ?? 0) + 1;

                if ($timestamp > $latest_order_timestamp) {
                    $latest_order_timestamp = $timestamp;
                }

                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $_original_product_id = get_post_meta($product_id, '_original_product_id', true);
                    $qty = $item->get_quantity();
                    $line_total = $item->get_total();
                    $reseller_price = $qty ? ($line_total / $qty) : 0;

                    // Get base price from main site
                    switch_to_blog(get_main_site_id());
                    $base_price = get_post_meta($_original_product_id, '_base_price', true);
                    $base_price = is_numeric($base_price) ? (float) $base_price : 0;
                    restore_current_blog();

                    $profit = ($reseller_price - $base_price) * $qty;
                    $stats['total_profit'] += $profit;

                    // Product usage and mapping
                    $stats['products_in_use'][$_original_product_id] = true;
                    $stats['product_reseller_map'][$_original_product_id] = ($stats['product_reseller_map'][$_original_product_id] ?? 0) + 1;

                    // Revenue and profit per product
                    $stats['product_revenue'][$_original_product_id] = ($stats['product_revenue'][$_original_product_id] ?? 0) + $line_total;
                    $stats['product_profit'][$_original_product_id] = ($stats['product_profit'][$_original_product_id] ?? 0) + $profit;

                    // Markup % for scatter
                    if ($base_price > 0) {
                        $markup_percent = (($reseller_price - $base_price) / $base_price) * 100;
                        $stats['markup_per_product'][] = [
                            'base' => $base_price,
                            'markup' => round($markup_percent, 2)
                        ];

                        // Bucket for histogram
                        $bucket = floor($markup_percent / 5) * 5;
                        $bucket_label = $bucket . '-' . ($bucket + 5) . '%';
                        $stats['markup_distribution'][$bucket_label] = ($stats['markup_distribution'][$bucket_label] ?? 0) + 1;
                    }

                    // Cold products
                    $last_sale = $stats['cold_products'][$_original_product_id] ?? 0;
                    if ($timestamp > $last_sale) {
                        $stats['cold_products'][$_original_product_id] = $timestamp;
                    }
                }

                // Reseller revenue
                $stats['reseller_revenue'][$site->blogname] = ($stats['reseller_revenue'][$site->blogname] ?? 0) + $order_total;

                $has_order = true;
            }

            if ($has_order) {
                $days_since = (time() - $latest_order_timestamp) / 86400;
                if ($days_since < 30) {
                    $stats['reseller_activity']['active']++;
                    $stats['active_resellers']++;
                } elseif ($days_since < 60) {
                    $stats['reseller_activity']['idle']++;
                } else {
                    $stats['reseller_activity']['inactive']++;
                }
            } else {
                $stats['reseller_activity']['inactive']++;
            }

            restore_current_blog();
        }

        // Most popular product
        arsort($stats['product_reseller_map']);
        $most_popular_product_id = array_key_first($stats['product_reseller_map']);
        switch_to_blog(get_main_site_id());
        $popular_product = wc_get_product($most_popular_product_id);
        $popular_name = $popular_product ? $popular_product->get_name() : 'N/A';
        restore_current_blog();

        // Prepare data for JS
        wp_localize_script('dtfreseller-stats', 'DtfResellerStatsData', [
            'revenue_by_date' => $stats['revenue_by_date'],
            'reseller_activity' => $stats['reseller_activity'],
        ]);

        // Render HTML
        echo '<div class="wrap"><h1 class="dtfreseller-tab-title">DTF Reseller Analytics Dashboard</h1>';

        // Stat Cards
        echo '<div class="stats-cards">';
        echo '<div class="stat-card"><h3>Total Sales</h3><p>' . wc_price($stats['total_sales']) . '</p></div>';
        echo '<div class="stat-card"><h3>Total Profit</h3><p>' . wc_price($stats['total_profit']) . '</p></div>';
        echo '<div class="stat-card"><h3>Active Resellers</h3><p>' . $stats['active_resellers'] . ' / ' . $stats['total_resellers'] . '</p></div>';
        echo '<div class="stat-card"><h3>Top Product</h3><p>' . esc_html($popular_name) . '</p></div>';
        echo '</div>';

        // Summary Table
        echo '<h2 class="dtfreseller-tab-subtitle">Summary</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        echo '<tr><td>Total Sales Revenue</td><td>' . wc_price($stats['total_sales']) . '</td></tr>';
        echo '<tr><td>Total Profit</td><td>' . wc_price($stats['total_profit']) . '</td></tr>';
        echo '<tr><td>Total Orders</td><td>' . $stats['total_orders'] . '</td></tr>';
        echo '<tr><td>Number of Resellers</td><td>' . $stats['total_resellers'] . '</td></tr>';
        echo '<tr><td>Active Resellers (30d)</td><td>' . $stats['active_resellers'] . '</td></tr>';
        echo '<tr><td>Products in Use</td><td>' . count($stats['products_in_use']) . '</td></tr>';
        echo '<tr><td>Most Popular Product</td><td>' . esc_html($popular_name) . '</td></tr>';
        echo '</tbody></table>';

        // Charts
        echo '<h2 class="dtfreseller-tab-subtitle">Key Charts</h2>';
        echo '<div class="charts-container">';
        echo "<div class='chart-box'><h3>Revenue Over Time</h3><div class='canvas-container'><canvas id='revenue_by_date'></canvas></div></div>";
        echo "<div class='chart-box'><h3>Reseller Activity</h3><div class='canvas-container'><canvas id='reseller_activity'></canvas></div></div>";
        echo '</div>';

        echo '</div>';
    }
}
