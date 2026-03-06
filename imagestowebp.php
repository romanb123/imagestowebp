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

	// Serve WebP to browsers that support it (works on any server — Apache, Nginx, etc.)
	if ( itwp_browser_supports_webp() ) {
		add_filter( 'wp_get_attachment_image_src', 'itwp_filter_attachment_image_src', 10, 1 );
		add_filter( 'the_content',                 'itwp_filter_content_images' );
		add_filter( 'wp_calculate_image_srcset',   'itwp_filter_srcset', 10, 1 );
	}
}

/**
 * Check if the current browser accepts WebP.
 */
function itwp_browser_supports_webp() {
	return isset( $_SERVER['HTTP_ACCEPT'] )
		&& strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false;
}

/**
 * Replace a single image URL with its WebP equivalent if it exists.
 */
function itwp_maybe_webp_url( $url ) {
	if ( empty( $url ) || strpos( $url, 'wp-content/uploads' ) === false ) {
		return $url;
	}

	$upload_dir  = wp_upload_dir();
	$base_url    = $upload_dir['baseurl'];
	$base_dir    = $upload_dir['basedir'];
	$relative    = str_replace( $base_url, '', $url );
	$ext         = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );

	if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif' ], true ) ) {
		return $url;
	}

	$webp_rel  = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/i', '.webp', $relative );
	$webp_file = $base_dir . $webp_rel;

	if ( file_exists( $webp_file ) ) {
		return $base_url . $webp_rel;
	}

	return $url;
}

/**
 * Filter wp_get_attachment_image_src() results.
 */
function itwp_filter_attachment_image_src( $image ) {
	if ( ! empty( $image[0] ) ) {
		$image[0] = itwp_maybe_webp_url( $image[0] );
	}
	return $image;
}

/**
 * Replace image URLs inside post content.
 */
function itwp_filter_content_images( $content ) {
	return preg_replace_callback(
		'/(<img[^>]+src=["\'])([^"\']+\.(jpe?g|png|gif))(["\'])/i',
		function ( $matches ) {
			return $matches[1] . itwp_maybe_webp_url( $matches[2] ) . $matches[4];
		},
		$content
	);
}

/**
 * Replace URLs in srcset attributes.
 */
function itwp_filter_srcset( $sources ) {
	if ( ! is_array( $sources ) ) return $sources;
	foreach ( $sources as &$source ) {
		if ( ! empty( $source['url'] ) ) {
			$source['url'] = itwp_maybe_webp_url( $source['url'] );
		}
	}
	return $sources;
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
