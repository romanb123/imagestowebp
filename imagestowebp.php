<?php
/**
 * Plugin Name: Images to WebP
 * Plugin URI:  https://github.com/romanb123/imagestowebp
 * Description: Convert all website images (JPEG, PNG, GIF) to WebP format for better performance.
 * Version:     1.0.0
 * Author:      romanb123
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ITWP_VERSION',    '1.0.0' );
define( 'ITWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ITWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ITWP_PLUGIN_DIR . 'includes/class-webp-converter.php';
require_once ITWP_PLUGIN_DIR . 'admin/class-admin-page.php';

add_action( 'plugins_loaded', 'itwp_init' );
function itwp_init() {
	new ITWP_Admin_Page();
	add_filter( 'wp_handle_upload', 'itwp_auto_convert_on_upload' );
}

/**
 * Auto-convert images to WebP on upload.
 */
function itwp_auto_convert_on_upload( $upload ) {
	$convertible = [ 'image/jpeg', 'image/png', 'image/gif' ];
	if ( in_array( $upload['type'], $convertible, true ) ) {
		$converter = new ITWP_WebP_Converter();
		$converter->convert( $upload['file'] );
	}
	return $upload;
}

register_activation_hook( __FILE__, 'itwp_activate' );
function itwp_activate() {
	itwp_add_htaccess_rules();
}

register_deactivation_hook( __FILE__, 'itwp_deactivate' );
function itwp_deactivate() {
	itwp_remove_htaccess_rules();
}

/**
 * Add WebP rewrite rules to .htaccess so Apache serves .webp files
 * automatically to browsers that support it.
 */
function itwp_add_htaccess_rules() {
	$htaccess = get_home_path() . '.htaccess';
	if ( ! file_exists( $htaccess ) ) return;

	$rules = PHP_EOL
		. '# BEGIN Images to WebP' . PHP_EOL
		. '<IfModule mod_rewrite.c>' . PHP_EOL
		. 'RewriteEngine On' . PHP_EOL
		. 'RewriteCond %{HTTP_ACCEPT} image/webp' . PHP_EOL
		. 'RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png|gif)$' . PHP_EOL
		. 'RewriteCond %1.webp -f' . PHP_EOL
		. 'RewriteRule ^ %1.webp [L,T=image/webp]' . PHP_EOL
		. '</IfModule>' . PHP_EOL
		. '# END Images to WebP' . PHP_EOL;

	$contents = file_get_contents( $htaccess );
	if ( strpos( $contents, '# BEGIN Images to WebP' ) === false ) {
		file_put_contents( $htaccess, $rules . $contents );
	}
}

function itwp_remove_htaccess_rules() {
	$htaccess = get_home_path() . '.htaccess';
	if ( ! file_exists( $htaccess ) ) return;

	$contents = file_get_contents( $htaccess );
	$contents = preg_replace(
		'/\n?# BEGIN Images to WebP.*?# END Images to WebP\n?/s',
		'',
		$contents
	);
	file_put_contents( $htaccess, $contents );
}
