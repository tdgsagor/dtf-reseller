<?php
namespace DtfReseller\Admin\ResellerTabs;

class ResellerStatsPage
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets()
    {
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('dtfreseller-stats', plugin_dir_url(__FILE__) . 'assets/js/stats.js', ['chartjs'], '1.0', true);

        wp_enqueue_style(
            'dtfreseller-stats',
            plugin_dir_url(__DIR__) . 'tabs/assets/css/stats.css',
            [],
            '1.0'
        );
    }

    public function render()
    {
        $stats = [
            'total_sales' => 0,
            'total_orders' => 0,
            'total_profit' => 0,
            'products_in_use' => [],
            'product_revenue' => [],
            'product_profit' => [],
            'revenue_by_date' => [],
            'orders_by_date' => [],
            'markup_per_product' => [],
            'markup_distribution' => [],
            'cold_products' => []
        ];

        // Query all completed orders (adjust date range if needed)
        $args = [
            'limit' => -1,
            'status' => ['wc-completed', 'wc-processing'],
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ];

        $orders = wc_get_orders($args);

        foreach ($orders as $order) {
            $order_date = $order->get_date_created()->date('Y-m-d');
            $order_total = (float) $order->get_total();
            $stats['total_sales'] += $order_total;
            $stats['total_orders']++;

            if (!isset($stats['revenue_by_date'][$order_date])) {
                $stats['revenue_by_date'][$order_date] = 0;
                $stats['orders_by_date'][$order_date] = 0;
            }
            $stats['revenue_by_date'][$order_date] += $order_total;
            $stats['orders_by_date'][$order_date]++;

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product)
                    continue;

                $product_id = $product->get_id();

                $sale_price = get_post_meta($product_id, '_sale_price', true);
                $original_price = get_post_meta($product_id, '_original_product_price', true);

                $sale_price = (float) $sale_price;
                $original_price = (float) $original_price;

                $product_name = $product->get_name();
                $qty = $item->get_quantity();
                // $line_total = $item->get_total(); // revenue
                $line_total = $sale_price - $original_price; // revenue
                $cost = $product->get_meta('_cost'); // stored by most cost-of-goods plugins

                $stats['products_in_use'][$product_id] = $product_name;

                // Revenue
                if (!isset($stats['product_revenue'][$product_id])) {
                    $stats['product_revenue'][$product_id] = 0;
                }
                $stats['product_revenue'][$product_id] += $line_total;

                // Cost
                $total_cost = is_numeric($cost) ? $cost * $qty : 0;

                // Profit
                $profit = $line_total - $total_cost;
                $stats['total_profit'] += $profit;

                if (!isset($stats['product_profit'][$product_id])) {
                    $stats['product_profit'][$product_id] = 0;
                }
                $stats['product_profit'][$product_id] += $profit;

                // Markup per product
                if (!isset($stats['markup_per_product'][$product_id])) {
                    $stats['markup_per_product'][$product_id] = [];
                }
                $stats['markup_per_product'][$product_id][] = $profit;
            }
        }
        arsort($stats['product_revenue']);
        $most_popular_product_id = array_key_first($stats['product_revenue']);
        // file_put_contents(__DIR__ . '/log.txt', $most_popular_product_id . PHP_EOL, FILE_APPEND);
        // switch_to_blog(get_main_site_id());
        $popular_product = wc_get_product($most_popular_product_id);
        $popular_name = $popular_product ? $popular_product->get_name() : 'N/A';
        // restore_current_blog();

        wp_localize_script('dtfreseller-stats', 'DtfResellerStatsData', $stats);

        echo '<div class="wrap">';
        echo '<h1 class="dtfreseller-tab-title">Reseller Dashboard</h1>';

        echo '<div class="stats-cards">';
        echo '<div class="stat-card"><h3>Total Sales</h3><p>' . wc_price($stats['total_sales']) . '</p></div>';
        echo '<div class="stat-card"><h3>Total Orders</h3><p>' . $stats['total_orders'] . '</p></div>';
        echo '<div class="stat-card"><h3>Total Profit</h3><p>' . wc_price($stats['total_profit']) . '</p></div>';
        echo '<div class="stat-card"><h3>Top Product</h3><p>' . esc_html($popular_name) . '</p></div>';
        echo '</div>';

        echo '<h2 class="dtfreseller-tab-subtitle">Summary Table</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Stat</th><th>Value</th></tr></thead><tbody>';
        echo '<tr><td>Total Sales Revenue</td><td>' . wc_price($stats['total_sales']) . '</td></tr>';
        echo '<tr><td>Total Orders</td><td>' . $stats['total_orders'] . '</td></tr>';
        echo '<tr><td>Total Profit (Your Margin)</td><td>' . wc_price($stats['total_profit']) . '</td></tr>';
        echo '<tr><td>Number of Products Sold</td><td>' . count($stats['products_in_use']) . '</td></tr>';
        echo '<tr><td>Most Popular Product</td><td>' . esc_html($popular_name) . '</td></tr>';
        echo '</tbody></table>';

        echo '<h2 class="dtfreseller-tab-subtitle">Charts</h2>';
        echo '<div class="charts-container">';
        echo "<div class='chart-box'><h3>Revenue Over Time</h3><div class='canvas-container'><canvas id='revenue_by_date'></canvas></div></div>";
        echo "<div class='chart-box'><h3>Top Products</h3><div class='canvas-container'><canvas id='product_revenue'></canvas></div></div>";
        echo '</div>';

        echo '</div>';
    }
}
