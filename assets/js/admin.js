/* global itwp, jQuery */
( function ( $ ) {
	'use strict';

	var total       = parseInt( $( '#itwp-total' ).text(), 10 ) || 0;
	var batchSize   = 5;
	var processed   = 0;
	var failed      = 0;
	var skipped     = 0;
	var isRunning   = false;
	var forceReconv = false;

	function startConversion( force ) {
		if ( isRunning ) return;

		forceReconv = !! force;
		isRunning   = true;
		processed   = 0;
		failed      = 0;
		skipped     = 0;

		$( '#itwp-convert-btn, #itwp-reconvert-btn' ).prop( 'disabled', true );
		$( '#itwp-progress-wrap' ).show();
		$( '#itwp-log-wrap' ).show();
		$( '#itwp-log' ).empty();
		setProgress( 0, itwp.strings.converting );

		// Re-fetch fresh total before starting
		$.post( itwp.ajax_url, {
			action: 'itwp_get_stats',
			nonce:  itwp.nonce,
		}, function ( res ) {
			if ( res.success ) {
				total = res.data.total;
				$( '#itwp-total' ).text( total );
			}
			processBatch( 0 );
		} );
	}

	function processBatch( offset ) {
		$.post( itwp.ajax_url, {
			action:    'itwp_convert_batch',
			nonce:     itwp.nonce,
			offset:    offset,
			batch_size: batchSize,
		}, function ( res ) {
			if ( ! res.success ) {
				addLog( itwp.strings.error, 'error' );
				finishConversion();
				return;
			}

			var data = res.data;
			processed += data.processed;
			failed    += data.failed;
			skipped   += data.skipped;

			var done = offset + batchSize;
			var pct  = total > 0 ? Math.min( 100, Math.round( ( done / total ) * 100 ) ) : 100;

			setProgress( pct, itwp.strings.converting + ' ' + Math.min( done, total ) + ' / ' + total );

			if ( data.processed > 0 ) {
				addLog( data.processed + ' image(s) converted in this batch.', 'success' );
			}
			if ( data.skipped > 0 ) {
				addLog( data.skipped + ' image(s) already converted, skipped.', 'info' );
			}
			if ( data.failed > 0 ) {
				addLog( data.failed + ' image(s) failed in this batch.', 'error' );
			}

			if ( data.done ) {
				finishConversion();
			} else {
				processBatch( data.next_offset );
			}
		} ).fail( function () {
			addLog( itwp.strings.error, 'error' );
			finishConversion();
		} );
	}

	function finishConversion() {
		isRunning = false;
		setProgress( 100, itwp.strings.done );
		addLog(
			'Finished. Converted: ' + processed + ' | Skipped: ' + skipped + ' | Failed: ' + failed,
			'success'
		);

		// Refresh stats
		$.post( itwp.ajax_url, {
			action: 'itwp_get_stats',
			nonce:  itwp.nonce,
		}, function ( res ) {
			if ( res.success ) {
				$( '#itwp-total' ).text( res.data.total );
				$( '#itwp-converted' ).text( res.data.converted );
				$( '#itwp-pending' ).text( res.data.pending );
			}
		} );

		$( '#itwp-convert-btn' ).prop( 'disabled', true ).text( itwp.strings.done );
		$( '#itwp-reconvert-btn' ).prop( 'disabled', false );
	}

	function setProgress( pct, text ) {
		$( '#itwp-progress-bar' ).css( 'width', pct + '%' );
		$( '#itwp-progress-text' ).text( text );
	}

	function addLog( message, type ) {
		var cls = type === 'error' ? 'itwp-log-error'
		        : type === 'info'  ? 'itwp-log-info'
		        :                    'itwp-log-success';
		$( '#itwp-log' ).append( '<li class="' + cls + '">' + message + '</li>' );
	}

	$( '#itwp-convert-btn' ).on( 'click', function () {
		startConversion( false );
	} );

	$( '#itwp-reconvert-btn' ).on( 'click', function () {
		startConversion( true );
	} );

} )( jQuery );
