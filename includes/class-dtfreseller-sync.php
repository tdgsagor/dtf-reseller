<?php
namespace DtfReseller;

class DtfReseller {
    private $product_sync;
    private $order_sync;
    private $admin;

    public function __construct() {
        $this->load_dependencies();
        $this->init();
    }

    private function load_dependencies() {
        require_once DTFRESELLER_SYNC_PATH . 'includes/class-product-sync.php';
        require_once DTFRESELLER_SYNC_PATH . 'includes/class-order-sync.php';
        require_once DTFRESELLER_SYNC_PATH . 'admin/class-admin.php';
    }

    private function init() {
        if (is_main_site() && get_site_option('dtfreseller_enable_products')) {
            $this->product_sync = new ProductSync();
        }
        // $this->order_sync = new OrderSync();
        $this->admin = new Admin();
    }
}