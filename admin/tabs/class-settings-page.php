<?php
namespace DtfReseller\Admin\Tabs;

use DtfReseller\Admin\CommonFunctions;

class SettingsPage {
    public function __construct()
    {
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    public function render() {
        ?>
        <form method="post">
            <?php wp_nonce_field('dtfreseller_settings', 'dtfreseller_settings_nonce');; ?>
            <h2 class="dtfreseller-tab-title">Global Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Product Sync</th>
                    <td>
                        <input type="checkbox" name="enable_product_sync" value="1" 
                            <?php checked(get_site_option('dtfreseller_enable_products'), 1); ?>>
                        <p class="description">Sync products from main site to subsites</p>
                    </td>
                </tr>
                <tr>
                        <th><label for="reseller_product_comission">Reseller Product Permission (%)</label></th>
                        <td>
                            <div>
                                <input type="number" id="reseller_product_comission" name="reseller_product_comission"
                                    value="<?php echo get_site_option('reseller_product_comission'); ?>" class="regular-text">
                                    <p class="description">This will be applied to the reseller's own product.</p>
                            </div>
                        </td>
                    </tr>
            </table>
            <?php submit_button('Save Global Settings', 'primary', 'dtfreseller_settings_submit');; ?>
        </form>
        <?php
    }
    public function handle_form_submissions()
    {
        // Handle global settings
        if (
            is_network_admin() &&
            isset($_GET['page']) && $_GET['page'] === 'dtfreseller' &&
            (!isset($_GET['tab']) || $_GET['tab'] === 'settings') &&
            isset($_POST['dtfreseller_settings_submit']) &&
            check_admin_referer('dtfreseller_settings', 'dtfreseller_settings_nonce')
        ) {
            update_site_option('dtfreseller_enable_products', isset($_POST['enable_product_sync']) ? 1 : 0);
            update_site_option('dtfreseller_enable_orders', isset($_POST['enable_order_sync']) ? 1 : 0);
            $margin = isset($_POST['reseller_product_comission']) ? sanitize_text_field($_POST['reseller_product_comission']) : 0;
            update_site_option('reseller_product_comission', $margin);

            CommonFunctions::add_notice('updated', 'Settings saved successfully.');
        }
    }
}