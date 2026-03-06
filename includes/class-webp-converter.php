<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ITWP_WebP_Converter {

	private $quality = 85;

	/**
	 * Convert a single image file to WebP.
	 *
	 * @param string $file_path Absolute path to the source image.
	 * @return string|false Path to the .webp file, or false on failure.
	 */
	public function convert( $file_path ) {
		if ( ! file_exists( $file_path ) ) return false;
		if ( ! function_exists( 'imagewebp' ) ) return false;

		$ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$webp_path = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/', '.webp', $file_path );

		if ( file_exists( $webp_path ) ) return $webp_path;

		$image = $this->create_image( $file_path, $ext );
		if ( ! $image ) return false;

		$result = imagewebp( $image, $webp_path, $this->quality );
		imagedestroy( $image );

		return $result ? $webp_path : false;
	}

	/**
	 * Create a GD image resource from a file.
	 */
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
	 * Check whether a WebP version already exists for a file.
	 */
	public function has_webp( $file_path ) {
		$ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$webp_path = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/', '.webp', $file_path );
		return file_exists( $webp_path );
	}

	/**
	 * Get all convertible image attachments from the media library.
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
	 */
	public function get_stats() {
		$images    = $this->get_all_images();
		$total     = count( $images );
		$converted = 0;

		foreach ( $images as $image ) {
			$file = get_attached_file( $image->ID );
			if ( $file && $this->has_webp( $file ) ) {
				$converted++;
			}
		}

		return [
			'total'     => $total,
			'converted' => $converted,
			'pending'   => $total - $converted,
		];
	}

	/**
	 * Convert a batch of images (with their thumbnails).
	 *
	 * @param int $offset
	 * @param int $batch_size
	 * @return array
	 */
	public function convert_batch( $offset, $batch_size = 5 ) {
		$images    = $this->get_all_images();
		$batch     = array_slice( $images, $offset, $batch_size );
		$processed = 0;
		$failed    = 0;
		$skipped   = 0;
		$errors    = [];

		foreach ( $batch as $image ) {
			$file = get_attached_file( $image->ID );

			if ( ! $file ) {
				$failed++;
				$errors[] = 'ID ' . $image->ID . ': no file path registered.';
				continue;
			}

			if ( ! file_exists( $file ) ) {
				$failed++;
				$errors[] = 'ID ' . $image->ID . ': file not found on disk — ' . basename( $file );
				continue;
			}

			// Skip if already converted
			if ( $this->has_webp( $file ) ) {
				$skipped++;
				continue;
			}

			$result = $this->convert( $file );

			// Also convert all registered thumbnail sizes
			$metadata = wp_get_attachment_metadata( $image->ID );
			if ( ! empty( $metadata['sizes'] ) ) {
				$dir = dirname( $file );
				foreach ( $metadata['sizes'] as $size ) {
					$thumb = $dir . '/' . $size['file'];
					if ( file_exists( $thumb ) && ! $this->has_webp( $thumb ) ) {
						$this->convert( $thumb );
					}
				}
			}

			if ( $result ) {
				$processed++;
			} else {
				$failed++;
				$errors[] = 'ID ' . $image->ID . ': GD conversion failed — ' . basename( $file );
			}
		}

		return [
			'processed'   => $processed,
			'failed'      => $failed,
			'skipped'     => $skipped,
			'errors'      => $errors,
			'total'       => count( $images ),
			'next_offset' => $offset + $batch_size,
			'done'        => ( $offset + $batch_size ) >= count( $images ),
		];
	}
}
