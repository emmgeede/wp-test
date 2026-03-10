<?php
namespace MBViews;

use MetaBox\Updater\Option;

class Editor {
	private $location;

	public function __construct( $location ) {
		$this->location = $location;

		add_filter( 'rwmb_meta_boxes', [ $this, 'register_meta_boxes' ] );

		// High priority to make sure Meta Box's scripts are enqueued.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ], 99 );

		add_action( 'admin_footer', [ $this, 'print_js_templates' ] );

		$post_fields = [ 'menu_order', 'post_name' ];
		foreach ( $post_fields as $post_field ) {
			add_filter( "rwmb_{$post_field}_field_meta", [ $this, "get_{$post_field}" ] );
			add_filter( "rwmb_{$post_field}_value", '__return_empty_string' );
		}

		add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );

		add_action( 'add_meta_boxes_mb-views', [ $this, 'remove_meta_boxes' ] );

		add_action( 'wp_ajax_mbv_save_post', [ $this, 'ajax_save_post' ] );
	}

	public function register_meta_boxes( array $meta_boxes ): array {
		if ( ! $this->is_license_active() ) {
			$meta_boxes[] = [
				'title'      => '<span class="dashicons dashicons-warning"></span>' . __( 'License Warning', 'meta-box-builder' ),
				'id'         => 'mbv-license-warning',
				'post_types' => [ 'mb-views' ],
				'context'    => 'side',
				'priority'   => 'high',
				'style'      => 'seamless',
				'fields'     => [
					[
						'type'     => 'custom_html',
						'callback' => [ $this, 'output_license_warning' ],
					],
				],
			];
		} else {
			$meta_boxes[] = [
				'title'      => __( 'Publish', 'mb-views' ),
				'id'         => 'mbv-save',
				'context'    => 'side',
				'priority'   => 'high',
				'post_types' => [ 'mb-views' ],
				'fields'     => [
					[
						'type'     => 'custom_html',
						'callback' => [ $this, 'render_save_box' ],
					],
				],
			];
		}

		$meta_boxes[] = [
			'title'      => __( 'Template Editor', 'mb-views' ),
			'id'         => 'mbv-template-editor',
			'class'      => 'mbv-editors',
			'post_types' => [ 'mb-views' ],
			'style'      => 'seamless',
			'fields'     => [
				[
					'type'     => 'custom_html',
					'callback' => [ $this, 'render_editors' ],
					'before'   => $this->render( 'toolbar' ),
					// Translators: %s - Twig link.
					'desc'     => wp_kses_post( sprintf( __( 'Supports any HTML/CSS/JS and shortcodes. Use %s to write conditions, loops and more. To run a PHP function, use <code>mb.function_name(param)</code>.', 'mb-views' ), '<a href="https://twig.symfony.com/doc/3.x/templates.html" target="_blank">Twig</a>' ) ),
				],
			],
		];

		$meta_boxes[] = $this->location->get_meta_box();

		$view_id = rwmb_request()->filter_get( 'post', FILTER_SANITIZE_NUMBER_INT );
		if ( ! is_admin() || ! $view_id ) {
			return $meta_boxes;
		}

		$post = get_post( $view_id );
		if ( ! $post ) {
			return $meta_boxes;
		}

		$shortcode    = '[mbv name="' . $post->post_name . '"]';
		$meta_boxes[] = [
			'title'      => __( 'Shortcode', 'mb-views' ),
			'id'         => 'mbv-shortcode',
			'context'    => 'side',
			'priority'   => 'low',
			'post_types' => [ 'mb-views' ],
			'fields'     => [
				[
					'type' => 'custom_html',
					'std'  => '<input type="text" class="regular-text" readonly value="' . esc_attr( $shortcode ) . '" onclick="this.select()">',
				],
			],
		];

		return $meta_boxes;
	}
	public function output_license_warning(): string {
		$settings_page = $this->get_updater()->is_network_activated() ? network_admin_url( 'settings.php?page=meta-box-updater' ) : admin_url( 'admin.php?page=meta-box-updater' );

		$status   = $this->get_updater()->get_license_status();
		$messages = [
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'no_key'   => __( 'You have not entered a Meta Box license key. Please <a href="%1$s">enter your license key</a> or <a href="%2$s" target="_blank">purchase a license</a> to enable premium features.', 'meta-box' ),
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'invalid'  => __( 'Your license key for Meta Box is <b>invalid</b>. Please <a href="%1$s">update your license key</a> to continue.', 'mb-views' ),
			// Translators: %1$s - URL to the settings page, %2$s - URL to the pricing page.
			'error'    => __( 'Your license key for Meta Box is <b>invalid</b>. Please <a href="%1$s">update your license key</a> to continue.', 'mb-views' ),
			// Translators: %3$s - URL to the My Account page.
			'expired'  => __( 'Your license key for Meta Box is <b>expired</b>. Please <a href="%3$s" target="_blank">renew your license</a> to continue.', 'mb-views' ),
			// Translators: %2$s - URL to the pricing page.
			'refunded' => __( 'Your Meta Box license has been <b>refunded</b>. To continue using Meta Box premium features and receive updates, please <a href="%2$s" target="_blank">purchase a new license</a>. Otherwise, premium features will be disabled.', 'meta-box' ),
		];

		return '<h2><span class="dashicons dashicons-warning"></span>' . __( 'License Warning', 'meta-box-builder' ) . '</h2>' . wp_kses_post( sprintf( $messages[ $status ], $settings_page, 'https://metabox.io/pricing/', 'https://metabox.io/my-account/' ) );
	}

	public function enqueue(): void {
		if ( ! $this->is_screen() ) {
			return;
		}
		wp_enqueue_code_editor( [
			'type' => 'application/x-httpd-php',
		] );
		wp_enqueue_style( 'mb-views', MBV_URL . 'assets/views.css', [ 'code-editor' ], MBV_VER );
		wp_enqueue_script( 'mb-views', MBV_URL . 'assets/views.js', [ 'jquery', 'code-editor', 'underscore', 'wp-element', 'wp-components', 'wp-i18n' ], MBV_VER, true );

		$localized_data = array_merge( $this->location->get_localized_data(), [
			'nonce'        => wp_create_nonce( 'views' ),
			'cloneable'    => __( 'Cloneable field', 'mb-views' ),

			// Relationships.
			'relationship' => __( 'Relationship', 'mb-views' ),
			'postType'     => __( 'post type', 'mb-views' ),
			'taxonomy'     => __( 'taxonomy', 'mb-views' ),
			'user'         => __( 'user', 'mb-views' ),
		] );
		wp_localize_script( 'mb-views', 'MBViews', $localized_data );

		wp_enqueue_script( 'mb-views-save', MBV_URL . 'assets/save.js', [], filemtime( MBV_DIR . '/assets/save.js' ), true );
		wp_localize_script( 'mb-views-save', 'MBViewsSave', [
			'adminUrl' => admin_url(),
		] );
	}

	public function render_editors(): string {
		return $this->render( 'editors' );
	}

	public function render_save_box(): string {
		return $this->render( 'save-box' );
	}

	public function print_js_templates(): void {
		if ( ! $this->is_screen() ) {
			return;
		}
		require MBV_DIR . '/views/inserter.php';

		foreach ( glob( __DIR__ . '/Fields/*/template.php' ) as $template ) {
			require $template;
		}
	}

	public function get_menu_order(): int {
		return (int) get_post_field( 'menu_order' );
	}

	public function get_post_name(): string {
		return (string) get_post_field( 'post_name' );
	}

	public function add_body_class( string $class ): string {
		if ( $this->is_screen() ) {
			$class .= ' mbv-dark';
		}
		return $class;
	}

	public function remove_meta_boxes(): void {
		remove_meta_box( 'slugdiv', null, 'normal' );
		remove_meta_box( 'submitdiv', null, 'side' );
	}

	private function render( string $name ): string {
		ob_start();
		require MBV_DIR . "/views/$name.php";
		return (string) ob_get_clean();
	}

	/**
	 * Get image sizes, used in fields' settings popups.
	 */
	private function get_image_sizes(): array {
		global $_wp_additional_image_sizes;
		$sizes = [];
		foreach ( get_intermediate_image_sizes() as $size ) {
			$sizes[ $size ] = [ 0, 0 ];

			if ( in_array( $size, [ 'thumbnail', 'medium', 'medium_large', 'large' ], true ) ) {
				$sizes[ $size ][0] = get_option( $size . '_size_w' );
				$sizes[ $size ][1] = get_option( $size . '_size_h' );
			} elseif ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				$sizes[ $size ] = [
					$_wp_additional_image_sizes[ $size ]['width'],
					$_wp_additional_image_sizes[ $size ]['height'],
				];
			}
		}
		return $sizes;
	}

	private function is_screen(): bool {
		return 'mb-views' === get_current_screen()->id;
	}

	protected function is_license_active(): bool {
		return $this->get_updater()->get_license_status() === 'active';
	}

	private function get_updater(): Option {
		static $updater;

		if ( ! $updater ) {
			$updater = new Option();
		}

		return $updater;
	}

	public function ajax_save_post(): void {
		check_ajax_referer( 'views', 'nonce' );

		$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid view.', 'mb-views' ), 400 );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'mb-views' ) {
			wp_send_json_error( __( 'Invalid view.', 'mb-views' ), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'You do not have permission to edit this view.', 'mb-views' ), 403 );
		}

		if ( isset( $_POST['original_post_status'] ) && ! in_array( $_POST['original_post_status'], [ 'publish', 'draft' ], true ) ) {
			$_POST['post_status'] = 'publish';
		}

		$result = edit_post();

		if ( ! $result ) {
			wp_send_json_error( __( 'Failed to save view.', 'mb-views' ), 500 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Failed to save view.', 'mb-views' ), 500 );
		}

		wp_send_json_success( [
			'message' => __( 'Changes saved.', 'mb-views' ),
			'slug'    => $post->post_name,
		] );
	}
}
