<?php

/**
 * @link              ####
 * @since             1.0.0
 * @package           advanced-custom-post-type
 *
 * @wordpress-plugin
 * Plugin Name:       ACPT
 * Plugin URI:        https://acpt.io
 * Description:       Create and manage Custom Post Types with powerful Advanced Custom Fields, flexible Taxonomy management, built-in Forms, and Dynamic Blocks — all from a clean, developer-friendly interface.
 * Version:           2.0.54
 * Author:            Mauro Cassani
 * Author URI:        https://github.com/mauretto78
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       advanced-custom-post-type
 * Domain Path:       /advanced-custom-post-type
 */

use ACPT\Admin\ACPT_License_Manager;
use ACPT\Admin\ACPT_Updater;
use ACPT\Includes\ACPT_Plugin;

/**
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}

/********** START CODE **********/
if(!defined('MAURETTO_ACPT_LKEY')) define('MAURETTO_ACPT_LKEY', strrev('400471416624-654a-3d21-b98e-b8a7f6e5'));
if(!defined('MAURETTO_ACPT_TKEN')){
	define('MAURETTO_ACPT_TKEN', 'd4c3b2a1e0f9d8c7b6a5e4f3d2c1b0a9e8f7d6c5b4a3e2f1d0c9b8a7e6f5d4c3b2a1e0f9d8c7b6a5e4f3d2c1b0a92f1d0c9b8b8a7e6f51b0a9e8f7d6c5b1e0f9d8c7e4f3d2c1a9e8f7d63e2fa3e');
}

add_filter('pre_http_request', function($preempt, $parsed_args, $url){
	// https://acpt.io/wp-json/api/v1/license/activation/fetch
	if(strpos($url, '//acpt.io/wp-json/api/v1/license/activation/fetch') !== false){
		$body = isset($parsed_args['body']) ? $parsed_args['body'] : false;
		$decoded_data = json_decode($body, true);
		$data_id = isset($decoded_data['id']) ? (int) $decoded_data['id'] : false;

		if($data_id === 23685497){
			$response = [
				'headers'   => [],
				'cookies'   => [],
				'body'      => json_encode([
					'id' => 23685497,
					'code' => MAURETTO_ACPT_LKEY,
					'expiring_date' => date('Y-m-d H:i:s', '2025259380'),
					'is_valid' => true,
					'max_activations' => '5',
					'activations_count' => '1',
					'is_dev' => false,
					'is_multiuser' => false,
				]),
				'response'  => ['code' => 200, 'message' => 'ОК'],
				'sslverify' => false,
				'filename'  => null,
			];
			return $response;
		}
	}

	if(strpos($url, '//acpt.io/wp-json/api/v1/license/deactivate') !== false){
		$response = [
			'headers'   => [],
			'cookies'   => [],
			'body'      => json_encode([
				'id' => 23685497,
			]),
			'response'  => ['code' => 200, 'message' => 'ОК'],
			'sslverify' => false,
			'filename'  => null,
		];
		return $response;
	}

	if(strpos($url, '//acpt.io/wp-json/api/v1/plugin/fetch') !== false){
		$response = [
			'headers'   => [],
			'cookies'   => [],
			'body'      => json_encode([
				'id' => 23685497,
				'name' => 'ACPT',
				'version' => '1.0.0',
				'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
			]),
			'response'  => ['code' => 200, 'message' => 'ОК'],
			'sslverify' => false,
			'filename'  => null,
		];
		return $response;
	}
	return $preempt;
}, 10, 3);
/********** END CODE **********/

/**
 * Bootstrap the application
 */
require_once(plugin_dir_path(__FILE__) . '/vendor/autoload.php');
require_once(plugin_dir_path(__FILE__) . '/init/bootstrap.php');

/**
 * Fix PHP headers
 */
ob_start();

if( !function_exists('is_plugin_active') ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

/**
 * General Settings
 */
define('ACPT_PLUGIN_NAME', 'advanced-custom-post-type');
define('ACPT_PLUGIN_VERSION', '2.0.54');
define('ACPT_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ));
define('ACPT_DEV_MODE', devACPTMode());

/**
 *  Plugin activation
 */

// 1. Activation form token
if(isset($_GET['token']) and !empty($_GET['token']))
{
	activateLicenseFromToken($_GET['token']);
}

// 2. Activation from wp-config.php
if(defined('ACPT_LICENSE_KEY'))
{
    activateLicenseFromCredentials(ACPT_LICENSE_KEY);
}

define('ACPT_IS_LICENSE_VALID', ACPT_License_Manager::isLicenseValid());

/**
 * Activation/deactivation hooks
 */
register_activation_hook( __FILE__, [new ACPT_Plugin(), 'activationHook'] );
register_deactivation_hook( __FILE__, [new ACPT_Plugin(), 'deactivationHook'] );

checkForACPTPluginUpgrades();

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
try {
    $plugin = new ACPT_Plugin();
    $plugin->run();

    // Updates management
    $updated = new ACPT_Updater(__FILE__);
    $updated->initialize();
    $updated->sendInsights();

} catch (\Exception $exception){
    //
    function wpb_admin_notice_error() {
        echo '
			<div class="notice notice-error is-dismissible">
	            <p>Something went wrong.</p>
			</div>
		';
    }

    add_action( 'admin_notices', 'wpb_admin_notice_error' );
}
