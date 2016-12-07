<?php

namespace Svg2Png;

use CloudConvert\Api;

/**
 * Class Svg2Png
 *
 * Handles conversion of SVG to PNG images.
 *
 * @package Svg2Png
 */
class Svg2Png {
	/**
	 * Svg2Png constructor.
	 */
	public function __construct() {
		/**
		 * Convert uploaded SVG images to PNG.
		 *
		 * @since 1.0.0
		 */
		add_filter( 'wp_handle_upload', function( $file ) {
			if ( 'image/svg+xml' === $file['type'] ) {
				$filepath = $file['file'];
				self::create_png_from_svg( $filepath );
			}

			return $file;
		} );

		/**
		 * Delete PNG image if an SVG is deleted.
		 *
		 * @since 1.0.0
		 */
		add_action( 'delete_attachment', function( $post_id ) {
			// Bailout if it is no SVG image
			if ( 'image/svg+xml' !== get_post_mime_type( $post_id ) ) {
				return;
			}

			// Sort out path to PNG file
			$filepath = get_attached_file( $post_id );
			$png_path = substr( $filepath, 0, -3 ) . 'png';

			if ( file_exists( $png_path ) ) {
				unlink( $png_path );
			}
		} );
	}

	/**
	 * Converts a PNG to SVG through the CloudConvert API
	 *
	 * @since 1.0.0
	 *
	 * @param $filepath
	 * @param bool $return
	 *
	 * @return bool|mixed
	 */
	public static function create_png_from_svg( $filepath, $return = false ) {
		$options = get_option( 'svg2png-settings-general' );

		// Bailout if we have no API Key
		if ( empty( $options['api-key'] ) ) {
			return false;
		}

		$api = new Api( $options['api-key'] );

		$output_path = substr( $filepath, 0, -3 ) . 'png';

		// Bail out if file already exists.
		if ( file_exists( $output_path ) ) {
			return false;
		}

		$message = '';
		$converteroptions = [];

		if ( ! empty( $options['resize'] ) ) {
			$converteroptions['resize'] = $options['resize'];

		// Use density only if resize is empty
		} elseif ( ! empty( $options['density'] ) ) {
			$converteroptions['density'] = $options['density'];
		}

		// Convert file through API
		try {
			$api->convert([
		        'inputformat' => 'svg',
		        'outputformat' => 'png',
		        'input' => 'upload',
				'converteroptions' => $converteroptions,
				'timeout' => 0,
		        'file' => fopen( $filepath, 'r' ),
		    ])
		    ->wait()
		    ->download( $output_path );

			if ( $return ) {
				$message = __( 'Created PNG fallback for', 'svg2png' ) . ': ' . $filepath;
			}
		} catch ( \CloudConvert\Exceptions\ApiBadRequestException $e ) {
		    $message = 'Something with your request is wrong: ' . $e->getMessage();
		} catch ( \CloudConvert\Exceptions\ApiConversionFailedException $e ) {
		    $message = 'Conversion failed, maybe because of a broken input file: ' . $e->getMessage();
		} catch ( \CloudConvert\Exceptions\ApiTemporaryUnavailableException $e ) {
		    $message = 'API temporary unavailable: ' . $e->getMessage() . "\n" .
		               'We should retry the conversion in' . $e->retryAfter . ' seconds';
		} catch ( \Exception $e ) {
		    // Network problems, etc..
		    $message = 'Something else went wrong: ' . $e->getMessage() . "\n";
		}

		if ( ! empty( $message ) ) {
			return self::log( $message );
		}

		return false;
	}

	/**
	 * Returns or logs a message
	 *
	 * @since 1.0.0
	 *
	 * @param string    $message
	 * @param bool      $return     Whether to return the message
	 *
	 * @return mixed
	 */
	public static function log( $message, $return = false ) {
		if ( ! $return ) {
			error_log( $message );
		}

		return $message;
	}
}
