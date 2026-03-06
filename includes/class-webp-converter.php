<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITWP_WebP_Converter {

	private $quality = 85;

	/**
	 * Convert a file to WebP and return the new path.
	 * Does NOT delete the original — caller is responsible.
	 */
	private function convert_file( $file_path ) {
		if ( ! file_exists( $file_path ) ) return false;
		if ( ! function_exists( 'imagewebp' ) ) return false;

		$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$webp_path = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/', '.webp', $file_path );

		$image = $this->create_image( $file_path, $ext );
		if ( ! $image ) return false;

		$result = imagewebp( $image, $webp_path, $this->quality );
		imagedestroy( $image );

		return $result ? $webp_path : false;
	}

	private function create_image( $file_path, $ext ) {
		switch ( $ext ) {
			case 'jpg':
			case 'jpeg':
				return imagecreatefromjpeg( $file_path );
			case 'png':
				$img = imagecreatefrompng( $file_path );
				if ( $img ) {
					imagepalettetotruecolor( $img );
					imagealphablending( $img, true );
					imagesavealpha( $img, true );
				}
				return $img;
			case 'gif':
				return imagecreatefromgif( $file_path );
		}
		return false;
	}

	/**
	 * Replace a single attachment with its WebP version.
	 * Updates WordPress DB records and deletes originals.
	 *
	 * @param int $attachment_id
	 * @return true|WP_Error
	 */
	public function replace_with_webp( $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! $file ) {
			return new WP_Error( 'no_path', 'No file path registered.' );
		}
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'not_found', 'File not found on disk — ' . basename( $file ) );
		}

		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( $ext === 'webp' ) {
			return new WP_Error( 'already_webp', 'Already WebP.' );
		}

		// --- Convert main file ---
		$webp_file = $this->convert_file( $file );
		if ( ! $webp_file ) {
			return new WP_Error( 'conversion_failed', 'GD conversion failed — ' . basename( $file ) );
		}

		// --- Convert thumbnails and collect original thumb paths ---
		$metadata       = wp_get_attachment_metadata( $attachment_id );
		$original_thumbs = [];

		if ( ! empty( $metadata['sizes'] ) ) {
			$dir = dirname( $file );
			foreach ( $metadata['sizes'] as $size_name => &$size_data ) {
				$thumb_orig = $dir . '/' . $size_data['file'];
				$thumb_ext  = strtolower( pathinfo( $thumb_orig, PATHINFO_EXTENSION ) );

				if ( $thumb_ext === 'webp' ) continue;

				if ( file_exists( $thumb_orig ) ) {
					$original_thumbs[] = $thumb_orig;
					$webp_thumb = $this->convert_file( $thumb_orig );
					if ( $webp_thumb ) {
						$size_data['file']      = basename( $webp_thumb );
						$size_data['mime-type'] = 'image/webp';
					}
				}
			}
			unset( $size_data );
		}

		// --- Update DB ---
		$old_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$new_relative = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/i', '.webp', $old_relative );

		update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );

		if ( ! empty( $metadata ) ) {
			$metadata['file'] = $new_relative;
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$old_guid = get_the_guid( $attachment_id );
		$new_guid = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/i', '.webp', $old_guid );

		wp_update_post( [
			'ID'             => $attachment_id,
			'post_mime_type' => 'image/webp',
			'guid'           => $new_guid,
		] );

		// --- Delete originals ---
		@unlink( $file );
		foreach ( $original_thumbs as $thumb ) {
			@unlink( $thumb );
		}

		return true;
	}

	/**
	 * Get all attachments still in original format (jpeg/png/gif).
	 */
	public function get_all_images() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT ID, guid, post_mime_type
			 FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_mime_type IN ('image/jpeg','image/png','image/gif')
			 AND post_status = 'inherit'
			 ORDER BY ID DESC"
		);
	}

	/**
	 * Return conversion stats.
	 * Converted = attachments already registered as image/webp.
	 * Pending   = attachments still in original format.
	 */
	public function get_stats() {
		global $wpdb;

		$pending = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_mime_type IN ('image/jpeg','image/png','image/gif')
			 AND post_status = 'inherit'"
		);

		$converted = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_mime_type = 'image/webp'
			 AND post_status = 'inherit'"
		);

		return [
			'total'     => $pending + $converted,
			'converted' => $converted,
			'pending'   => $pending,
		];
	}

	/**
	 * Replace a batch of attachments with their WebP versions.
	 */
	public function convert_batch( $offset, $batch_size = 5 ) {
		$images    = $this->get_all_images();
		$batch     = array_slice( $images, $offset, $batch_size );
		$processed = 0;
		$failed    = 0;
		$skipped   = 0;
		$errors    = [];

		foreach ( $batch as $image ) {
			$result = $this->replace_with_webp( $image->ID );

			if ( is_wp_error( $result ) ) {
				$code = $result->get_error_code();
				if ( $code === 'already_webp' ) {
					$skipped++;
				} else {
					$failed++;
					$errors[] = 'ID ' . $image->ID . ': ' . $result->get_error_message();
				}
			} else {
				$processed++;
			}
		}

		// Re-fetch remaining count after replacements
		$remaining = count( $this->get_all_images() );

		return [
			'processed'   => $processed,
			'failed'      => $failed,
			'skipped'     => $skipped,
			'errors'      => $errors,
			'total'       => count( $images ),
			'next_offset' => $offset + $batch_size,
			'done'        => $remaining === 0 || ( $offset + $batch_size ) >= count( $images ),
		];
	}
}
