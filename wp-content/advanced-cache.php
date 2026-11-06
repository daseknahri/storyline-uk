<?php
/**
 * UK Page Cache — early-serve drop-in (wp-content/advanced-cache.php).
 *
 * WordPress includes this file in wp-settings.php when WP_CACHE is on, BEFORE the database,
 * plugins and mu-plugins load. It serves the exact cache files written by the UK Page Cache
 * mu-plugin (same on-disk format: 3-byte status prefix + HTML), so a warm page costs a file
 * read instead of a full WordPress bootstrap. It NEVER stores: storing, purging and every
 * other rule stay with the mu-plugin, which also remains the fallback server whenever this
 * drop-in declines (stale file, bypassed request, missing constant).
 *
 * The bypass rules and the cache-key recipe below intentionally mirror ukpc_bypass() and
 * ukpc_file() in mu-plugins/uk-page-cache.php — including core's wp_is_mobile() test, which
 * the mu-plugin uses for the device variant. Keep the two in sync when either changes.
 */

if ( ! defined( 'ABSPATH' ) ) { return; }

/* Only ever serve a plain front-end GET; everything else falls through to WordPress. */
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { return; }
if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) { return; }
if ( ! isset( $_SERVER['REQUEST_URI'] ) ) { return; }

$ukac_uri = (string) $_SERVER['REQUEST_URI'];
if ( false !== strpos( $ukac_uri, '/wp-' ) || false !== strpos( $ukac_uri, 'preview' ) || false !== strpos( $ukac_uri, 'feed' ) ) { return; }
if ( isset( $_SERVER['QUERY_STRING'] ) && '' !== (string) $_SERVER['QUERY_STRING'] ) { return; }
foreach ( array_keys( $_COOKIE ) as $ukac_c ) {
	if ( preg_match( '/^(wordpress_logged_in|wp-postpass|comment_author)/', (string) $ukac_c ) ) { return; }
}
if ( isset( $_COOKIE['wordpress_test_cookie'] ) ) { return; }

/* Device variant — core wp_is_mobile()'s test, verbatim (the function itself is not loaded yet). */
$ukac_ua       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
$ukac_mobile   = '' !== $ukac_ua && ( false !== strpos( $ukac_ua, 'Mobile' ) || false !== strpos( $ukac_ua, 'Android' )
	|| false !== strpos( $ukac_ua, 'Silk/' ) || false !== strpos( $ukac_ua, 'Kindle' )
	|| false !== strpos( $ukac_ua, 'BlackBerry' ) || false !== strpos( $ukac_ua, 'Opera Mini' )
	|| false !== strpos( $ukac_ua, 'Opera Mobi' ) );
$ukac_host     = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
$ukac_dir      = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
$ukac_file     = $ukac_dir . '/cache/uk-page/' . md5( $ukac_host . $ukac_uri . ( $ukac_mobile ? '|m' : '|d' ) ) . '.html';
$ukac_ttl      = 1800; /* UKPC_TTL in the mu-plugin: 30 minutes. */

if ( is_readable( $ukac_file ) && ( time() - filemtime( $ukac_file ) ) < $ukac_ttl ) {
	$ukac_head = @file_get_contents( $ukac_file, false, null, 0, 3 );
	if ( '200' === $ukac_head ) {
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Vary: User-Agent' );   /* cache is keyed per device class; proxies must not mix them */
		header( 'X-UK-Cache: hit' );
		$ukac_fp = fopen( $ukac_file, 'rb' );
		if ( $ukac_fp ) {
			fseek( $ukac_fp, 3 );       /* skip the 3-byte status prefix */
			fpassthru( $ukac_fp );
			fclose( $ukac_fp );
		}
		exit;
	}
}
