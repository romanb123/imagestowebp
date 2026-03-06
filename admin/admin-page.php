<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap itwp-wrap">
	<h1><?php esc_html_e( 'Images to WebP', 'imagestowebp' ); ?></h1>
	<p class="itwp-subtitle"><?php esc_html_e( 'Convert your media library images to WebP for faster page loads.', 'imagestowebp' ); ?></p>

	<?php if ( ! $gd_webp ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Your server does not support WebP via the GD library. Please contact your hosting provider to enable it.', 'imagestowebp' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Stats -->
	<div class="itwp-stats">
		<div class="itwp-stat-card">
			<span class="itwp-stat-number" id="itwp-total"><?php echo esc_html( $stats['total'] ); ?></span>
			<span class="itwp-stat-label"><?php esc_html_e( 'Total Images', 'imagestowebp' ); ?></span>
		</div>
		<div class="itwp-stat-card itwp-stat-success">
			<span class="itwp-stat-number" id="itwp-converted"><?php echo esc_html( $stats['converted'] ); ?></span>
			<span class="itwp-stat-label"><?php esc_html_e( 'Converted', 'imagestowebp' ); ?></span>
		</div>
		<div class="itwp-stat-card itwp-stat-pending">
			<span class="itwp-stat-number" id="itwp-pending"><?php echo esc_html( $stats['pending'] ); ?></span>
			<span class="itwp-stat-label"><?php esc_html_e( 'Pending', 'imagestowebp' ); ?></span>
		</div>
	</div>

	<!-- Progress -->
	<div class="itwp-progress-wrap" id="itwp-progress-wrap" style="display:none;">
		<div class="itwp-progress-bar-bg">
			<div class="itwp-progress-bar" id="itwp-progress-bar" style="width:0%"></div>
		</div>
		<p class="itwp-progress-text" id="itwp-progress-text"></p>
	</div>

	<!-- Actions -->
	<div class="itwp-actions">
		<?php if ( $gd_webp ) : ?>
			<button id="itwp-convert-btn" class="button button-primary button-large" <?php echo $stats['pending'] === 0 ? 'disabled' : ''; ?>>
				<?php echo $stats['pending'] === 0
					? esc_html__( 'All Images Already Converted', 'imagestowebp' )
					: esc_html__( 'Convert All Images', 'imagestowebp' );
				?>
			</button>
			<button id="itwp-reconvert-btn" class="button button-secondary button-large">
				<?php esc_html_e( 'Re-convert All', 'imagestowebp' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<!-- Log -->
	<div class="itwp-log-wrap" id="itwp-log-wrap" style="display:none;">
		<h3><?php esc_html_e( 'Conversion Log', 'imagestowebp' ); ?></h3>
		<ul class="itwp-log" id="itwp-log"></ul>
	</div>

	<!-- Info -->
	<div class="itwp-info-box">
		<h3><?php esc_html_e( 'How it works', 'imagestowebp' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'WebP files are saved alongside the originals (e.g. image.jpg → image.webp).', 'imagestowebp' ); ?></li>
			<li><?php esc_html_e( 'Apache servers: .htaccess rules are added on activation to serve WebP automatically to supported browsers.', 'imagestowebp' ); ?></li>
			<li><?php esc_html_e( 'New uploads are converted automatically.', 'imagestowebp' ); ?></li>
			<li><?php esc_html_e( 'Thumbnails for each image size are also converted.', 'imagestowebp' ); ?></li>
		</ul>
	</div>
</div>
