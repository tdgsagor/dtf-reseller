<?php
namespace DtfReseller;

class OrderSync {
    public function __construct() {
        add_action('woocommerce_new_order', array($this, 'sync_order'), 10, 1);
    }

    public function sync_order($order_id) {
        if (is_main_site()) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        switch_to_blog(get_main_site_id());
        
        $this->copy_order($order, get_current_blog_id());
        
        restore_current_blog();
    }

    private function copy_order($source_order, $source_site_id) {
        $order_data = array(
            'status' => $source_order->get_status(),
            'customer_id' => $source_order->get_customer_id(),
            'customer_note' => $source_order->get_customer_note(),
            'created_via' => 'dtfreseller'
        );

        $new_order = wc_create_order($order_data);

        if (!is_wp_error($new_order)) {
            // Copy order items
            foreach ($source_order->get_items() as $item) {
                $new_order->add_product(
                    wc_get_product($item->get_product_id()),
                    $item->get_quantity(),
                    array(
                        'subtotal' => $item->get_subtotal(),
                        'total' => $item->get_total()
                    )
                );
            }

            // Add meta to identify source
            $new_order->add_meta_data('_source_site_id', $source_site_id, true);
            $new_order->add_meta_data('_source_order_id', $source_order->get_id(), true);

            $new_order->calculate_totals();
            $new_order->save();
        }
    }
}