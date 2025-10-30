<?php
namespace DtfReseller\Admin\Tabs\Orders;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OrdersList extends \WP_List_Table
{
    private $sites;

    public function __construct()
    {
        parent::__construct([
            'singular' => __('Order', 'textdomain'),
            'plural' => __('Orders', 'textdomain'),
            'ajax' => false
        ]);

        $this->sites = array_filter(
            get_sites(['number' => 0]),
            function ($site) {
                return (int) $site->blog_id !== (int) get_main_site_id();
            }
        );
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $orders_data = [];
        $selected_site = isset($_GET['site_id']) ? intval($_GET['site_id']) : null;

        foreach ($this->sites as $site) {
            if ($selected_site && $site->blog_id != $selected_site) {
                continue;
            }
            switch_to_blog($site->blog_id);
            $orders = wc_get_orders(['limit' => -1]);
            foreach ($orders as $order) {
                $order_link = esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit'));
                $status = wc_get_order_status_name($order->get_status());
                $status_class = 'status-' . sanitize_html_class($order->get_status());

                $orders_data[] = [
                    'order_id' => '<a href="' . $order_link . '" target="_blank">#' . $order->get_id() . ' ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '</a>',
                    'site_name' => get_bloginfo('name'),
                    'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                    'total' => $order->get_formatted_order_total(),
                    'status' => '<mark class="order-status ' . $status_class . '"><span>' . esc_html($status) . '</span></mark>',
                ];
            }
            restore_current_blog();
        }

        usort($orders_data, function ($a, $b) {
            $orderby = $_REQUEST['orderby'] ?? 'order_id';
            $order = $_REQUEST['order'] ?? 'asc';
            if ($orderby === 'total') {
                $result = floatval($a[$orderby]) <=> floatval($b[$orderby]);
            } else {
                $result = strcmp($a[$orderby], $b[$orderby]);
            }
            return ($order === 'asc') ? $result : -$result;
        });

        $this->items = $orders_data;
    }

    public function get_columns()
    {
        return [
            'order_id' => __('Order', 'textdomain'),
            'site_name' => __('Site Name', 'textdomain'),
            'date' => __('Date', 'textdomain'),
            'total' => __('Total', 'textdomain'),
            'status' => __('Status', 'textdomain'),
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'order_id' => ['order_id', true],
            'total' => ['total', false],
        ];
    }

    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    public function render()
    {
        echo '<div class="wrap"><h1 class="dtfreseller-tab-title">Orders</h1>';
        // $this->render_site_filter();
        $this->prepare_items();
        $this->display();
        echo '</div>';
    }

    protected function extra_tablenav($which)
    {
        if ($which == 'top') {
            echo '<div class="alignleft actions">';
            $this->render_site_filter();
            echo '</div>';
        }
    }

    private function render_site_filter()
    {
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page']) . '" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr($_REQUEST['tab']) . '" />';
        echo '<select name="site_id">';
        echo '<option value="">' . __('All Sites', 'textdomain') . '</option>';
        foreach ($this->sites as $site) {
            $selected = (isset($_REQUEST['site_id']) && $_REQUEST['site_id'] == $site->blog_id) ? 'selected' : '';
            echo '<option value="' . esc_attr($site->blog_id) . '" ' . $selected . '>' . esc_html(get_blog_details($site->blog_id)->blogname) . '</option>';
        }
        echo '</select>';
        submit_button(__('Filter'), 'secondary', '', false);
        echo '</form>';
    }
}