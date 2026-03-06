<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITWP_Admin_Page {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_itwp_convert_batch', [ $this, 'ajax_convert_batch' ] );
		add_action( 'wp_ajax_itwp_get_stats',     [ $this, 'ajax_get_stats' ] );
	}

	public function add_menu() {
		add_media_page(
			__( 'Images to WebP', 'imagestowebp' ),
			__( 'Images to WebP', 'imagestowebp' ),
			'manage_options',
			'images-to-webp',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'media_page_images-to-webp' ) return;

		wp_enqueue_style(
			'itwp-admin',
			ITWP_PLUGIN_URL . 'assets/css/admin.css',
			[],
			ITWP_VERSION
		);

		wp_enqueue_script(
			'itwp-admin',
			ITWP_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			ITWP_VERSION,
			true
		);

		wp_localize_script( 'itwp-admin', 'itwp', [
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'itwp_nonce' ),
			'strings'   => [
				'converting'  => __( 'Converting…', 'imagestowebp' ),
				'done'        => __( 'All done!', 'imagestowebp' ),
				'convert_all' => __( 'Convert All Images', 'imagestowebp' ),
				'error'       => __( 'An error occurred. Please try again.', 'imagestowebp' ),
			],
		] );
	}

	public function render_page() {
		$converter = new ITWP_WebP_Converter();
		$stats     = $converter->get_stats();
		$gd_webp   = function_exists( 'imagewebp' );
		require_once ITWP_PLUGIN_DIR . 'admin/admin-page.php';
	}

	public function ajax_convert_batch() {
		check_ajax_referer( 'itwp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$offset    = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$converter = new ITWP_WebP_Converter();
		$result    = $converter->convert_batch( $offset );

		wp_send_json_success( $result );
	}

	public function ajax_get_stats() {
		check_ajax_referer( 'itwp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$converter = new ITWP_WebP_Converter();
		wp_send_json_success( $converter->get_stats() );
	}
}
