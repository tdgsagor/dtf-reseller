<?php
namespace DtfReseller;

if (!defined('ABSPATH'))
    exit;

class DtfReseller_Updater
{
    private $file;
    private $plugin;
    private $basename;
    private $username;
    private $repo;
    private $token;
    private $api_response;

    public function __construct($file, $username, $repo, $token = '')
    {
        $this->file = $file;
        $this->username = $username;
        $this->repo = $repo;
        $this->token = $token;

        $this->basename = plugin_basename($this->file);
        $this->plugin = get_plugin_data($this->file);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);

        delete_site_transient('update_plugins');
        wp_update_plugins();
    }

    private function get_repo_info()
    {
        if (is_null($this->api_response)) {
            $url = "https://api.bitbucket.org/2.0/repositories/TDG-D2C/dtf-reseller/downloads";

            // Basic Auth with App Password
            $username = 'sagor_dev';
            $app_password = 'ATBB7VW4Xmg9JTWve8dEgm3Apccd4A4BA6F7';
            $credentials = base64_encode($username . ':' . $app_password);

            $args = [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials
                ]
            ];

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                file_put_contents(__DIR__ . '/log.txt', 'WP_Error: ' . $response->get_error_message() . PHP_EOL, FILE_APPEND);
                return;
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                file_put_contents(__DIR__ . '/log.txt', "HTTP code: $code" . PHP_EOL, FILE_APPEND);
                return;
            }

            $data = json_decode(wp_remote_retrieve_body($response));
            if (json_last_error() !== JSON_ERROR_NONE) {
                file_put_contents(__DIR__ . '/log.txt', 'JSON decode error: ' . json_last_error_msg() . PHP_EOL, FILE_APPEND);
                return;
            }

            file_put_contents(__DIR__ . '/log.txt', '$data: ' . print_r($data, true) . PHP_EOL, FILE_APPEND);

            if (!empty($data->values)) {
                // Sort by created_on descending to get latest file
                usort($data->values, function ($a, $b) {
                    return strtotime($b->created_on) - strtotime($a->created_on);
                });
                $this->api_response = $data->values[0]; // latest file
            }
        }
    }


    public function check_update($transient)
    {
        if (empty($transient->checked))
            return $transient;

        $this->get_repo_info();
        if ($this->api_response) {
            $remote_version = preg_replace('/[^0-9\.]/', '', $this->api_response->name); // Extract version
            if (version_compare($this->plugin['Version'], $remote_version, '<')) {
                $obj = new \stdClass();
                $obj->slug = $this->basename;
                $obj->new_version = $remote_version;
                $obj->url = '';
                $obj->package = $this->api_response->links->self->href;

                $transient->response[$this->basename] = $obj;
            }
        }

        return $transient;
    }

    public function plugins_api($false, $action, $response)
    {
        if (empty($response->slug) || $response->slug !== $this->basename)
            return $false;
        return $response;
    }

    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->basename);
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;
        return $result;
    }
}
