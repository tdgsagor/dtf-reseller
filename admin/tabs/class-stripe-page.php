<?php
namespace DtfReseller\Admin\Tabs;

class StripePage
{

    public function render()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            update_option('smc_stripe_secret_key', sanitize_text_field($_POST['smc_stripe_secret_key']));
            update_option('smc_stripe_publishable_key', sanitize_text_field($_POST['smc_stripe_publishable_key']));
            // update_option('smc_platform_fee', smc_sanitize_platform_fee($_POST['smc_platform_fee']));
            update_option('smc_stripe_webhook_secret', sanitize_text_field($_POST['smc_stripe_webhook_secret']));

            // Display a success message
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1 class="dtfreseller-tab-title">Stripe Connect Settings</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th><label for="smc_stripe_secret_key">Stripe Secret Key</label></th>
                        <td>
                            <div class="toggle-password-container">
                                <input type="password" id="smc_stripe_secret_key" name="smc_stripe_secret_key"
                                    value="<?php echo esc_attr(get_option('smc_stripe_secret_key')); ?>" class="regular-text">
                                <button type="button" class="toggle-password dashicons dashicons-visibility"
                                    data-target="smc_stripe_secret_key"></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="smc_stripe_publishable_key">Stripe Publishable Key</label></th>
                        <td>
                            <div class="toggle-password-container">
                                <input type="password" id="smc_stripe_publishable_key" name="smc_stripe_publishable_key"
                                    value="<?php echo esc_attr(get_option('smc_stripe_publishable_key')); ?>"
                                    class="regular-text">
                                <button type="button" class="toggle-password dashicons dashicons-visibility"
                                    data-target="smc_stripe_publishable_key"></button>
                            </div>
                        </td>
                    </tr>
                    <!-- <tr>
                        <th><label for="smc_stripe_webhook_secret">Webhook Secret</label></th>
                        <td><input type="text" name="smc_stripe_webhook_secret"
                                value="<?php echo esc_attr(get_option('smc_stripe_webhook_secret')); ?>" class="regular-text">
                        </td>
                    </tr> -->
                </table>

                <input type="submit" value="Save Changes" class="button button-primary">
            </form>
        </div>
        <?php
    }

    function smc_sanitize_platform_fee($input)
    {
        $input = floatval($input);
        if ($input < 0 || $input > 100) {
            add_settings_error('smc_platform_fee', 'invalid_platform_fee', 'Platform fee must be between 0 and 100.');
            return get_option('smc_platform_fee'); // Keep previous valid value
        }
        return $input;
    }
}
