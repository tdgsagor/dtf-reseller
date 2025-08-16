<?php
namespace DtfReseller\Admin\Tabs;

use DtfReseller\Admin\Tabs\Orders\OrderEdit;
use DtfReseller\Admin\Tabs\Orders\OrdersList;

class OrdersPage
{
    public function __construct()
    {
        require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/orders/class-orders-list.php';
        require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/orders/class-order-edit.php';
    }
    public function render()
    {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'show';
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
        $site_id = isset($_GET['site_id']) ? intval($_GET['site_id']) : null;

        if ($action === 'edit' && $order_id) {
            $order_edit = new OrderEdit();
            $order_edit->render($order_id, $site_id);
        } else {
            $orders_list = new OrdersList();
            $orders_list->render();
        }
    }
}