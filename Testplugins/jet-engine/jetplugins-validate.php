<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Crocoblock GPL License v1.0.0
 * allows you to activate license keys for all JetPlugins by crocoblock.com
 * Created by: gplgood.com, snackwp.com, gplplace.com
 */

add_filter('pre_http_request', function($preempt, $parsed_args, $url){
	// https://api.crocoblock.com
	if(strpos($url, '//api.crocoblock.com') !== false){
		$get_args = [];
		parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $get_args);

		$action = isset($get_args['action']) ? $get_args['action'] : false;
		$license = isset($get_args['license']) ? $get_args['license'] : false;

		$site_url = site_url( '/' );
		$url_parts = parse_url( $site_url );
		$url_path = isset($url_parts['path']) ? $url_parts['path'] : '';
		$client_site = isset($url_parts['host']) ? $url_parts['host'] . $url_path : '';
		$client_site = preg_replace( '#^https?://#', '', rtrim( $client_site ) );
		$client_site = str_replace( 'www.', '', $client_site );

		if($action === 'activate_license' && $license === CRCBLOCK_JETBUNDLE_LKEY){

			$license_data = [
				'license' => CRCBLOCK_JETBUNDLE_LKEY,
				'product_category' => 'lifetime',
				'type' => 'crocoblock',
				'expire' => 'lifetime',
				'product_name' => 'Lifetime',
				'site_url' => $client_site,
				'plugins' => [
					'jet-elements/jet-elements.php' => [
						'name' => 'JetElements',
						'slug' => 'jet-elements/jet-elements.php',
						'version' => '1.0',
					],
					'jet-tabs/jet-tabs.php' => [
						'name' => 'JetTabs',
						'slug' => 'jet-tabs/jet-tabs.php',
						'version' => '1.0',
					],
					'jet-reviews/jet-reviews.php' => [
						'name' => 'JetReviews',
						'slug' => 'jet-reviews/jet-reviews.php',
						'version' => '1.0',
					],
					'jet-menu/jet-menu.php' => [
						'name' => 'JetMenu',
						'slug' => 'jet-menu/jet-menu.php',
						'version' => '1.0',
					],
					'jet-blog/jet-blog.php' => [
						'name' => 'JetBlog',
						'slug' => 'jet-blog/jet-blog.php',
						'version' => '1.0',
					],
					'jet-blocks/jet-blocks.php' => [
						'name' => 'JetBlocks',
						'slug' => 'jet-blocks/jet-blocks.php',
						'version' => '1.0',
					],
					'jet-tricks/jet-tricks.php' => [
						'name' => 'JetTricks',
						'slug' => 'jet-tricks/jet-tricks.php',
						'version' => '1.0',
					],
					'jet-smart-filters/jet-smart-filters.php' => [
						'name' => 'JetSmartFilters',
						'slug' => 'jet-smart-filters/jet-smart-filters.php',
						'version' => '1.0',
					],
					'jet-popup/jet-popup.php' => [
						'name' => 'JetPopup',
						'slug' => 'jet-popup/jet-popup.php',
						'version' => '1.0',
					],
					'jet-search/jet-search.php' => [
						'name' => 'JetSearch',
						'slug' => 'jet-search/jet-search.php',
						'version' => '1.0',
					],
					'jet-woo-builder/jet-woo-builder.php' => [
						'name' => 'JetWooBuilder',
						'slug' => 'jet-woo-builder/jet-woo-builder.php',
						'version' => '1.0',
					],
					'jet-woo-product-gallery/jet-woo-product-gallery.php' => [
						'name' => 'JetProductGallery',
						'slug' => 'jet-woo-product-gallery/jet-woo-product-gallery.php',
						'version' => '1.0',
					],
					'jet-compare-wishlist/jet-cw.php' => [
						'name' => 'JetCompare&Wishlist',
						'slug' => 'jet-compare-wishlist/jet-cw.php',
						'version' => '1.0',
					],
					'jet-engine/jet-engine.php' => [
						'name' => 'JetEngine',
						'slug' => 'jet-engine/jet-engine.php',
						'version' => '1.0',
					],
					'jet-booking/jet-booking.php' => [
						'name' => 'JetBooking',
						'slug' => 'jet-booking/jet-booking.php',
						'version' => '1.0',
					],
					'jet-style-manager/jet-style-manager.php' => [
						'name' => 'JetStyleManager',
						'slug' => 'jet-style-manager/jet-style-manager.php',
						'version' => '1.0',
					],
					'jet-appointments-booking/jet-appointments-booking.php' => [
						'name' => 'JetAppointment',
						'slug' => 'jet-appointments-booking/jet-appointments-booking.php',
						'version' => '1.0',
					],
					'jet-theme-core/jet-theme-core.php' => [
						'name' => 'JetThemeCore',
						'slug' => 'jet-theme-core/jet-theme-core.php',
						'version' => '1.0',
					],
					'jet-product-tables/jet-product-tables.php' => [
						'name' => 'JetProductTables',
						'slug' => 'jet-product-tables/jet-product-tables.php',
						'version' => '1.0',
					],
				],
			];

			$response = [
				'headers'   => [],
				'cookies'   => [],
				'body'      => json_encode([
					'status' => 'success',
					'message' => 'License activated successfully!',
					'code'    => 'license_activated',
					'data' => $license_data,
				]),
				'response'  => ['code' => 200, 'message' => 'ОК'],
				'sslverify' => false,
				'filename'  => null,
			];
			return $response;
		}

		if($action === 'deactivate_license'){
			$response = [
				'headers'   => [],
				'cookies'   => [],
				'body'      => json_encode([
					'status' => 'success',
					'code'    => 'license_deleted',
					'message' => 'License deactivated successfully!',
					'data'    => [],
				]),
				'response'  => ['code' => 200, 'message' => 'ОК'],
				'sslverify' => false,
				'filename'  => null,
			];
			return $response;
		}

		// https://api.crocoblock.com?action=get_plugins_data
        if($action === 'get_plugins_data'){
			
			$plugins_data = [
				[
					'name' => 'JetElements',
					'slug' => 'jet-elements/jet-elements.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetelements.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetelements.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jet-elements/',
					'demo' => 'https://crocoblock.com/plugins/jetelements/',
					'desc' => 'Must-have design widgets',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetTabs',
					'slug' => 'jet-tabs/jet-tabs.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jettabs.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jettabs.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jet-tabs/',
					'demo' => 'https://crocoblock.com/plugins/jettabs/',
					'desc' => 'A smart way to organize content',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetReviews',
					'slug' => 'jet-reviews/jet-reviews.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetreviews.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetreviews.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetreviews/',
					'demo' => 'https://crocoblock.com/plugins/jetreviews/',
					'desc' => 'Add reviews, comments, and rates',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetMenu',
					'slug' => 'jet-menu/jet-menu.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetmenu.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetmenu.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jet-menu/',
					'demo' => 'https://crocoblock.com/plugins/jetmenu/',
					'desc' => 'Build a custom mega menu',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetBlog',
					'slug' => 'jet-blog/jet-blog.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetblog.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetblog.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jet-blog/',
					'demo' => 'https://crocoblock.com/plugins/jetblog/',
					'desc' => 'Create engaging blog pages',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetBlocks',
					'slug' => 'jet-blocks/jet-blocks.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetblocks.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetblocks.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetblocks/',
					'demo' => 'https://crocoblock.com/plugins/jetblocks/',
					'desc' => 'Enrich header & footer content',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetTricks',
					'slug' => 'jet-tricks/jet-tricks.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jettricks.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jettricks.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jettricks/',
					'demo' => 'https://crocoblock.com/plugins/jettricks/',
					'desc' => 'Add interactive visual effects',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetSmartFilters',
					'slug' => 'jet-smart-filters/jet-smart-filters.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetsmartfilters.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetsmartfilters.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetsmartfilters/',
					'demo' => 'https://crocoblock.com/plugins/jetsmartfilters/',
					'desc' => 'Advanced filters for any post type',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetPopup',
					'slug' => 'jet-popup/jet-popup.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetpopup.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetpopup.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jet-popup/',
					'demo' => 'https://crocoblock.com/plugins/jetpopup/',
					'desc' => 'Create popups that boost sales',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetSearch',
					'slug' => 'jet-search/jet-search.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetsearch.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetsearch.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jet-search/',
					'demo' => 'https://crocoblock.com/plugins/jetsearch/',
					'desc' => 'Try the fastest AJAX search',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetWooBuilder',
					'slug' => 'jet-woo-builder/jet-woo-builder.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetwoobuilder.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetwoobuilder.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetwoobuilder/',
					'demo' => 'https://crocoblock.com/plugins/jetwoobuilder/',
					'desc' => 'Create custom e-commerce pages',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetProductGallery',
					'slug' => 'jet-woo-product-gallery/jet-woo-product-gallery.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetproductgallery.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetproductgallery.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetproductgallery/',
					'demo' => 'https://crocoblock.com/plugins/jetproductgallery/',
					'desc' => 'Product gallery sliders and carousels ',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetCompare&Wishlist',
					'slug' => 'jet-compare-wishlist/jet-cw.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetwoocomparewishlist.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetcomparewishlist.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetcomparewishlist/',
					'demo' => 'https://crocoblock.com/plugins/jetcomparewishlist/',
					'desc' => 'Compare and wishlist functionality',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetEngine',
					'slug' => 'jet-engine/jet-engine.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetengine.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetengine.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jet-engine/',
					'demo' => 'https://crocoblock.com/plugins/jetengine/',
					'desc' => 'Top-notch plugin for dynamic content',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetBooking',
					'slug' => 'jet-booking/jet-booking.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetbooking.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetbooking.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/plugins/jetbooking/',
					'demo' => 'https://crocoblock.com/plugins/jetbooking/',
					'desc' => 'Complex booking functionality',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetStyleManager',
					'slug' => 'jet-style-manager/jet-style-manager.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetstylemanager.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetstylemanager.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetstylemanager/',
					'demo' => 'https://crocoblock.com/plugins/jetstylemanager/',
					'desc' => 'Manage Elementor page style settings',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetAppointment',
					'slug' => 'jet-appointments-booking/jet-appointments-booking.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jet-appointment.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jet-appointment.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/jetappointment/',
					'demo' => 'https://crocoblock.com/plugins/jetappointment/',
					'desc' => 'Create custom appointment forms',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetThemeCore',
					'slug' => 'jet-theme-core/jet-theme-core.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetthemecore.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetthemecore.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/article-category/theme-parts/',
					'demo' => 'https://crocoblock.com/plugins/jetthemecore/',
					'desc' => 'Most powerful plugin created to make building websites super easy',
					'versions' => ['1.0.0']
				],
				[
					'name' => 'JetProductTables',
					'slug' => 'jet-product-tables/jet-product-tables.php',
					'version' => '1.0.0',
					'thumb' => 'https://account.crocoblock.com/free-download/images/jetlogo/jetproducttables.svg',
					'thumb_alt' => 'https://account.crocoblock.com/free-download/images/jetlogo-alt/jetproducttables.svg',
					'docs' => 'https://crocoblock.com/knowledge-base/plugins/jetproducttables/',
					'demo' => 'https://crocoblock.com/plugins/jetproducttables/',
					'desc' => 'Choose between different layouts and filtering options.',
					'versions' => ['1.0.0']
				]
			];

			$response = [
				'headers'   => [],
				'cookies'   => [],
				'body'      => json_encode([
					'status' => 'success',
					'message' => 'Plugins Data Founded',
					'data' => $plugins_data,
				]),
				'response'  => ['code' => 200, 'message' => 'ОК'],
				'sslverify' => false,
				'filename'  => null,
			];
			return $response;
		}

		// https://api.crocoblock.com?action=get_plugin_update&license={LICENSEKEY}&plugin=jet-elements/jet-elements.php&site_url=demo3380.com%2F
		if($action === 'get_plugin_update'){
			return new WP_Error('snackwp_update_blocked', 'Plugins need to be installed manually.');
		}
		if($action === 'get_jetengine_module_update'){
			return new WP_Error('snackwp_update_blocked', 'External Modules must be installed manually!');
		}
	}

	// https://crocoblock.com/wp-content/uploads/jet-changelog/{SLUG}.json
	if(strpos($url, '//crocoblock.com/wp-content/uploads/jet-changelog/') !== false){
		$response = [
			'headers'   => [],
			'cookies'   => [],
			'body'      => json_encode([
				'current_version' => '1.0.0',
				'changelog' => '<p>The version and changelog displayed on this page may not be updated. Please read the changelog page.</p>',
			]),
			'response'  => ['code' => 200, 'message' => 'ОК'],
			'sslverify' => false,
			'filename'  => null,
		];
		return $response;
	}

	
	if(strpos($url, '//account.crocoblock.com') !== false){
		$get_args = [];
		parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $get_args);

		$edd_action = isset($get_args['edd_action']) ? $get_args['edd_action'] : false;
		$item_id = isset($get_args['item_id']) ? (int) $get_args['item_id'] : false;

		// AI
		$ai_api = isset($get_args['ai_api']) ? $get_args['ai_api'] : false;
		$license = isset($get_args['license']) ? $get_args['license'] : false;

		if($ai_api === 'website' && $license === CRCBLOCK_JETBUNDLE_LKEY){
			$response = [
				'headers'   => [],
				'cookies'   => [],
				'body'      => json_encode([
					'success' => false,
					'data'    => 'Currently, AI features are not supported for the GPL license. They will be available in the future!',
				]),
				'response'  => ['code' => 200, 'message' => 'ОК'],
				'sslverify' => false,
				'filename'  => null,
			];
			return $response;
		}

		$url_mappings = [
			// Static uploads paths JET ENGINE MODULES
			// Like https://account.crocoblock.com/wp-content/uploads/static/jet-engine-attachment-link-callback.json
			'/wp-content/uploads/static/jet-engine-attachment-link-callback.json' => 'jet-engine-attachment-link-callback',
			'/wp-content/uploads/static/jet-engine-custom-visibility-conditions.json' => 'jet-engine-custom-visibility-conditions',
			'/wp-content/uploads/static/jet-engine-dynamic-charts-module.json' => 'jet-engine-dynamic-charts-module',
			'/wp-content/uploads/static/jet-engine-dynamic-tables-module.json' => 'jet-engine-dynamic-tables-module',
			'/wp-content/uploads/static/jet-engine-items-number-filter.json' => 'jet-engine-items-number-filter',
			'/wp-content/uploads/static/jet-engine-layout-switcher.json' => 'jet-engine-layout-switcher',
			'/wp-content/uploads/static/jet-engine-post-expiration-period.json' => 'jet-engine-post-expiration-period',
			'/wp-content/uploads/static/jet-engine-trim-callback.json' => 'jet-engine-trim-callback',
			
			// REST API paths JET ENGINE MODULES
			'/wp-json/croco/v1/engine-modules/jet-engine-attachment-link-callback' => 'jet-engine-attachment-link-callback',
			'/wp-json/croco/v1/engine-modules/jet-engine-custom-visibility-conditions' => 'jet-engine-custom-visibility-conditions',
			'/wp-json/croco/v1/engine-modules/jet-engine-dynamic-charts-module' => 'jet-engine-dynamic-charts-module',
			'/wp-json/croco/v1/engine-modules/jet-engine-dynamic-tables-module' => 'jet-engine-dynamic-tables-module',
			'/wp-json/croco/v1/engine-modules/jet-engine-items-number-filter' => 'jet-engine-items-number-filter',
			'/wp-json/croco/v1/engine-modules/jet-engine-layout-switcher' => 'jet-engine-layout-switcher',
			'/wp-json/croco/v1/engine-modules/jet-engine-post-expiration-period' => 'jet-engine-post-expiration-period',
			'/wp-json/croco/v1/engine-modules/jet-engine-trim-callback' => 'jet-engine-trim-callback',
		];

		foreach ($url_mappings as $search_path => $module_slug) {
			if (strpos($url, $search_path) !== false) {
				$response = [
					'headers'   => [],
					'cookies'   => [],
					'body'      => json_encode([
						'slug' => $module_slug,
						'version' => '1.0.0',
						'package' => null,
					]),
					'response'  => ['code' => 200, 'message' => 'ОК'],
					'sslverify' => false,
					'filename'  => null,
				];
				return $response;
				break; // Keluar dari loop setelah menemukan kecocokan
			}
		}
	}
	return $preempt;
}, 10, 3);