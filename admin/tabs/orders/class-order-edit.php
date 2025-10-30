<?php
namespace DtfReseller\Admin\Tabs\Orders;

class OrderEdit
{
    public function __construct()
    {
        $this->dtfreseller_order_update();
    }
    public function render($order_id, $site_id)
    {
        switch_to_blog($site_id);
        $order = wc_get_order($order_id);
        restore_current_blog();

        if (!$order) {
            echo '<div class="notice notice-error"><p>Order not found.</p></div>';
            restore_current_blog();
            return;
        }
        ?>
        <div class="wrap">
            <h1>Edit Order #<?php echo esc_html($order->get_id()); ?></h1>
            <form method="post">
                <?php wp_nonce_field('dtfreseller_order_update', 'dtfreseller_order_update_nonce'); ?>
                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                <input type="hidden" name="site_id" value="<?php echo esc_attr($site_id); ?>">

                <h2>Order Details</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="customer_name">Customer Name</label></th>
                        <td><input name="customer_name" type="text" id="customer_name"
                                value="<?php echo esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>"
                                class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="order_total">Order Total</label></th>
                        <td><input name="order_total" type="text" id="order_total"
                                value="<?php echo esc_attr($order->get_total()); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="order_items">Order Items</label></th>
                        <td>
                            <ul>
                                <?php foreach ($order->get_items() as $item_id => $item): ?>
                                    <li><?php echo esc_html($item->get_name()); ?> - <?php echo esc_html($item->get_quantity()); ?>
                                        x <?php echo esc_html($item->get_total()); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                </table>
                <h2>Shipping Details</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="shipping_address">Shipping Address</label></th>
                        <td><textarea name="shipping_address" id="shipping_address"
                                class="large-text"><?php echo esc_textarea($order->get_formatted_shipping_address()); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Update', 'primary', 'update_order'); ?>
            </form>
        </div>
        <?php
    }

    private function dtfreseller_order_update()
    {
        // if (isset($_POST['update_order']) && check_admin_referer('dtfreseller_order_update', 'dtfreseller_order_update_nonce')) {


        if (
            is_network_admin() &&
            isset($_GET['page']) &&
            $_GET['page'] === 'dtfreseller' &&
            (!isset($_GET['tab']) || $_GET['tab'] === 'orders') &&
            isset($_POST['update_order']) &&
            check_admin_referer('dtfreseller_order_update', 'dtfreseller_order_update_nonce')
        ) {

            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $site_id = isset($_POST['site_id']) ? absint($_POST['site_id']) : 0;

            if (!$order_id || !$site_id) {
                wp_redirect(add_query_arg('message', 'missing_data', admin_url('admin.php?page=your_page_slug')));
                exit;
            }

            switch_to_blog($site_id);
            $order = wc_get_order($order_id);

            if (!$order) {
                restore_current_blog();
                wp_redirect(add_query_arg('message', 'order_not_found', admin_url('admin.php?page=your_page_slug')));
                exit;
            }

            // Update customer name
            if (isset($_POST['customer_name'])) {
                $names = explode(' ', sanitize_text_field($_POST['customer_name']), 2);
                $order->set_billing_first_name($names[0]);
                $order->set_billing_last_name(isset($names[1]) ? $names[1] : '');
            }

            // Update order total
            if (isset($_POST['order_total'])) {
                $order->set_total(floatval($_POST['order_total']));
            }

            $order->save();
            // restore_current_blog();
            // wp_redirect(add_query_arg('message', 'order_updated', admin_url('admin.php?page=your_page_slug')));
            // exit;
        }
    }
}