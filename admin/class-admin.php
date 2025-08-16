<?php
namespace DtfReseller;

use DtfReseller\Admin\CommonFunctions;
use DtfReseller\Admin\ResellerTabs\ResellerGeneralPage;
use DtfReseller\Admin\ResellerTabs\ResellerStatsPage;
use DtfReseller\Admin\ResellerTabs\ResellerStripePage;
use DtfReseller\Admin\Tabs\ManualSyncPage;
use DtfReseller\Admin\Tabs\OrdersPage;
use DtfReseller\Admin\Tabs\SettingsPage;
use DtfReseller\Admin\Tabs\StatsPage;
use DtfReseller\Admin\Tabs\StripePage;

class Admin
{
    private $settings_page;
    private $manual_sync_page;
    private $orders_page;
    private $stats_page;
    private $stripe_page;
    private $reseller_stats_page;
    private $reseller_stripe_page;
    private $reseller_general_page;
    private $admin_notices = array();

    public function __construct()
    {
        new CommonFunctions();
        if (is_multisite() && is_main_site() && is_network_admin()) {
            require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/class-settings-page.php';
            require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/class-manual-sync-page.php';
            require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/class-orders-page.php';
            require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/class-stats-page.php';
            require_once DTFRESELLER_SYNC_PATH . 'admin/tabs/class-stripe-page.php';

            $this->settings_page = new SettingsPage();
            $this->manual_sync_page = new ManualSyncPage();
            $this->orders_page = new OrdersPage();
            $this->stats_page = new StatsPage();
            $this->stripe_page = new StripePage();
        } else {
            require_once DTFRESELLER_SYNC_PATH . 'admin/reseller-tabs/class-stats-page.php';
            require_once DTFRESELLER_SYNC_PATH . 'admin/reseller-tabs/class-stripe-page.php';
            require_once DTFRESELLER_SYNC_PATH . 'admin/reseller-tabs/class-general-page.php';
            $this->reseller_stats_page = new ResellerStatsPage();
            $this->reseller_stripe_page = new ResellerStripePage();
            $this->reseller_general_page = new ResellerGeneralPage();
        }


        add_action('network_admin_menu', array($this, 'add_network_menu'));
        add_action('admin_menu', array($this, 'add_subsite_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('network_admin_notices', array($this, 'show_admin_notices'));
    }

    private function add_notice($type, $message)
    {
        $this->admin_notices[] = array(
            'type' => $type,
            'message' => $message
        );
    }

    public function show_admin_notices()
    {
        foreach ($this->admin_notices as $notice) {
            printf(
                '<div class="%s"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    public function enqueue_admin_scripts($hook)
    {

        wp_enqueue_style(
            'dtf-reseller-css',
            DTFRESELLER_SYNC_URL . 'assets/css/dtf-reseller.css',
            array(),
            null
        );
        wp_enqueue_script(
            'dtf-reseller-admin-js',
            DTFRESELLER_SYNC_URL . 'assets/js/dtf-reseller-admin.js',
            array('jquery'),
            null,
            true
        );

        // file_put_contents(__DIR__ . '/log.txt', $hook . PHP_EOL, FILE_APPEND);

        if ('toplevel_page_dtfreseller' !== $hook) {
            return;
        }

        // Enqueue Select2 CSS and JS
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));

        // Enqueue custom CSS and JS
        wp_enqueue_style('dtfreseller-sync-custom-css', plugins_url('assets/css/custom-style.css', DTFRESELLER_SYNC_PATH . 'admin/class-admin.php'));
        wp_enqueue_script('dtfreseller-sync-custom-js', plugins_url('assets/js/custom-script.js', DTFRESELLER_SYNC_PATH . 'admin/class-admin.php'), array('jquery'), null, true);
    }

    public function add_network_menu()
    {
        add_menu_page(
            'DTF Reseller Settings',              // Page title
            'DTF Reseller',                       // Menu title
            'manage_network_options',             // Capability
            'dtfreseller',                        // Menu slug
            array($this, 'render_settings_page'), // Callback function
            '',
            80                                    // Position
        );
    }

    public function render_settings_page()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        ?>
        <div class="wrap dtf-reseller-wrapper">
            <h1 class="dtfreseller-page-title">DTF Reseller Settings</h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'settings')); ?>"
                    class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    General Settings
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'manual-sync')); ?>"
                    class="nav-tab <?php echo $current_tab === 'manual-sync' ? 'nav-tab-active' : ''; ?>">
                    Manual Product Sync
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'orders')); ?>"
                    class="nav-tab <?php echo $current_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
                    Orders
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'stats')); ?>"
                    class="nav-tab <?php echo $current_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                    Statistics
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'stripe')); ?>"
                    class="nav-tab <?php echo $current_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">
                    Stripe Settings
                </a>
            </nav>

            <?php if ($current_tab === 'settings'): ?>
                <?php $this->settings_page->render(); ?>
            <?php elseif ($current_tab === 'manual-sync'): ?>
                <?php $this->manual_sync_page->render(); ?>
            <?php elseif ($current_tab === 'stats'): ?>
                <?php $this->stats_page->render(); ?>
            <?php elseif ($current_tab === 'stripe'): ?>
                <?php $this->stripe_page->render(); ?>
            <?php else: ?>
                <?php $this->orders_page->render(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_subsite_menu()
    {
        if (is_multisite() && get_current_blog_id() == 1) {
            return;
        }

        add_menu_page(
            'DTF Reseller',
            'DTF Reseller',
            'manage_options',
            'dtfreseller-resellers',
            array($this, 'subsite_menu_callback'),
            '',
            71
        );
    }

    public function subsite_menu_callback()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'stats';
        ?>
        <div class="wrap dtf-reseller-wrapper">
            <h1 class="dtfreseller-page-title">DTF Reseller Settings</h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'general')); ?>"
                    class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    General
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'stats')); ?>"
                    class="nav-tab <?php echo $current_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                    Statistics
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'stripe')); ?>"
                    class="nav-tab <?php echo $current_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">
                    Stripe Settings
                </a>
            </nav>

            <?php if ($current_tab === 'stats'): ?>
                <?php $this->reseller_stats_page->render(); ?>
            <?php elseif ($current_tab === 'stripe'): ?>
                <?php $this->reseller_stripe_page->render(); ?>
            <?php elseif ($current_tab === 'general'): ?>
                <?php $this->reseller_general_page->render(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

}