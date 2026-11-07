<?php
/**
 * Plugin Name: Local: Media from Live
 * Plugin URI:  https://github.com/benwakeford/local-media-from-live
 * Description: LOCAL ONLY. Any upload missing from this local copy is loaded from the live site instead, so the uploads folder doesn't need to be on this machine. Configure it in wp-config.php (see README). Inert on any environment that isn't 'local'.
 * Version:     2.0.1
 * Author:      Ben Wakeford
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 *
 * Settings, all defined in each site's wp-config.php:
 *
 *   LMFL_LIVE_URL          Required. The live site, e.g. 'https://www.example.com'.
 *                          Include www. if live uses it. Without it the plugin does nothing.
 *   LMFL_LIVE_UPLOADS_URL  Optional. Only if live's uploads aren't at /wp-content/uploads.
 *   LMFL_TIMTHUMB          Optional, default true. Rewrites TimThumb (thumb.php?src=) links
 *                          to the live image when the original isn't on this machine.
 */

defined( 'ABSPATH' ) || exit;

// Safety net: never run anywhere but Local (wp-config sets WP_ENVIRONMENT_TYPE = 'local').
if ( wp_get_environment_type() !== 'local' ) {
	return;
}

// Not configured for this site: say so in wp-admin rather than failing silently.
if ( ! defined( 'LMFL_LIVE_URL' ) || ! is_string( LMFL_LIVE_URL ) || LMFL_LIVE_URL === '' ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-warning"><p><strong>Local: Media from Live</strong> is installed but not set up. Add <code>define( \'LMFL_LIVE_URL\', \'https://www.example.com\' );</code> to wp-config.php.</p></div>';
	} );
	return;
}

/**
 * Live uploads URL, no trailing slash.
 */
function lmfl_live_uploads_url() {
	if ( defined( 'LMFL_LIVE_UPLOADS_URL' ) && is_string( LMFL_LIVE_UPLOADS_URL ) && LMFL_LIVE_UPLOADS_URL !== '' ) {
		return rtrim( LMFL_LIVE_UPLOADS_URL, '/' );
	}
	return rtrim( LMFL_LIVE_URL, '/' ) . '/wp-content/uploads';
}

/**
 * Swap a local uploads URL for the live one if the file isn't on disk here.
 */
function lmfl_swap( $url ) {
	if ( ! is_string( $url ) || $url === '' ) {
		return $url;
	}
	$uploads = wp_get_upload_dir();

	// Match http/https variants of the local uploads URL.
	$base_noscheme = preg_replace( '#^https?:#', '', $uploads['baseurl'] );
	$url_noscheme  = preg_replace( '#^https?:#', '', $url );
	if ( strpos( $url_noscheme, $base_noscheme ) !== 0 ) {
		return $url;
	}

	$relative = substr( $url_noscheme, strlen( $base_noscheme ) );
	if ( file_exists( $uploads['basedir'] . strtok( $relative, '?#' ) ) ) {
		return $url;
	}

	return lmfl_live_uploads_url() . $relative;
}

// Attachment URLs (media library, featured images, ACF image fields, srcset).
add_filter( 'wp_get_attachment_url', 'lmfl_swap', 99 );
add_filter( 'wp_get_attachment_image_src', function ( $image ) {
	if ( is_array( $image ) && ! empty( $image[0] ) ) {
		$image[0] = lmfl_swap( $image[0] );
	}
	return $image;
}, 99 );
add_filter( 'wp_calculate_image_srcset', function ( $sources ) {
	if ( is_array( $sources ) ) {
		foreach ( $sources as $w => $source ) {
			$sources[ $w ]['url'] = lmfl_swap( $source['url'] );
		}
	}
	return $sources;
}, 99 );

/**
 * Rewrite hardcoded upload URLs (and TimThumb links) in a block of front-end HTML.
 */
function lmfl_filter_html( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	// Old theme resizers (TimThumb thumb.php) can't fetch from another site, so when the
	// image they're asked for isn't on this machine, show the live image directly instead.
	if ( ! defined( 'LMFL_TIMTHUMB' ) || LMFL_TIMTHUMB ) {
		$live = lmfl_live_uploads_url();
		$html = preg_replace_callback(
			'#(?:https?:)?//[^"\'\s<>]+/thumb\.php\?src=([^&"\'\s<>]+)[^"\'\s<>]*#',
			function ( $m ) use ( $live ) {
				$src = html_entity_decode( rawurldecode( $m[1] ) );
				// Some themes pass a site-relative path (e.g. "wp-content/uploads/..."), not a URL.
				if ( ! preg_match( '#^(?:https?:)?//#i', $src ) ) {
					$src = home_url( '/' . ltrim( $src, '/' ) );
				}
				$src = lmfl_swap( $src );
				return strpos( $src, $live ) === 0 ? $src : $m[0];
			},
			$html
		);
	}

	$base = preg_quote( preg_replace( '#^https?:#', '', wp_get_upload_dir()['baseurl'] ), '#' );
	return preg_replace_callback(
		'#(https?:)?' . $base . '/[^"\'\s\)<>,]+#',
		function ( $m ) {
			return lmfl_swap( $m[0] );
		},
		$html
	);
}

// Hardcoded upload URLs anywhere in front-end HTML (post content, theme templates, inline CSS).
add_action( 'template_redirect', function () {
	ob_start( 'lmfl_filter_html' );
}, 0 );
