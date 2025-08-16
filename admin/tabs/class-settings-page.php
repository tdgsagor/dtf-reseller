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
            <?php wp_nonce_field('dtfreseller_settings'); ?>
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
                <!-- <tr>
                        <th><label for="dtfr_default_product_margin">Default Product Margin (%)</label></th>
                        <td>
                            <div>
                                <input type="number" id="dtfr_default_product_margin" name="dtfr_default_product_margin"
                                    value="<?php echo get_site_option('dtfr_default_product_margin'); ?>" class="regular-text">
                                    <p class="description">This will be applied</p>
                            </div>
                        </td>
                    </tr> -->
            </table>
            <?php submit_button('Save Global Settings'); ?>
        </form>
        <?php
    }
    public function handle_form_submissions()
    {
        // Handle global settings
        if (isset($_POST['submit']) && check_admin_referer('dtfreseller_settings')) {
            update_site_option('dtfreseller_enable_products', isset($_POST['enable_product_sync']) ? 1 : 0);
            update_site_option('dtfreseller_enable_orders', isset($_POST['enable_order_sync']) ? 1 : 0);
            $margin = isset($_POST['dtfr_default_product_margin']) ? sanitize_text_field($_POST['dtfr_default_product_margin']) : 0;
            update_site_option('dtfr_default_product_margin', $margin);

            CommonFunctions::add_notice('updated', 'Settings saved successfully.');
        }
    }
}