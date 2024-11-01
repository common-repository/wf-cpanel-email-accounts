<?php
declare( strict_types=1 );
namespace WebFacing\cPanel;
global $wpdb;
$wpabs  = \urldecode( \filter_input( \INPUT_GET, 'abs',   \FILTER_SANITIZE_SPECIAL_CHARS ) ?: $_SERVER['DOCUMENT_ROOT'] . '/' );

if ( $wpabs ) {
	\date_default_timezone_set( 'UTC' );
	\define( 'ABSPATH', $wpabs );
	\define( 'SHORTINIT', true );
	\define( 'WP_USE_THEMES', false );
	require_once \ABSPATH . '/wp-load.php';
	$result = $wpdb->get_row(
		$wpdb->prepare( 'SHOW VARIABLES LIKE %s', 'max_connections'),
		\ARRAY_A
	);
	$mc = $result['Value'] ?? 1;
	$result = $wpdb->get_row(
		$wpdb->prepare( 'SHOW STATUS LIKE %s', 'Threads_connected'),
		\ARRAY_A
	);
	$tc = $result['Value'] ?? 0;
	$di = \log( \floatval( $mc ) ** ( 1. / 99. ) );
	$co = \min( \intval( \round( \log( \floatval( $tc ) ) / $di ) ) + 1, 100 );

	\header( 'Content-Type: application/json' );
	\header( 'Expires: Sat, 1 Jun 2024 12:34:56 GMT' );
	\header( 'Cache-Control: no-cache, must-revalidate, max-age=1' );
	\header( 'X-Threads_connected: ' . \json_encode( [ 'co' => $co, 'mc' => $mc, 'tc' => $tc ] ) );

	$secret   = \intval( \filter_input( \INPUT_GET, 'secret', \FILTER_SANITIZE_NUMBER_INT ) );
	$token    =          \filter_input( \INPUT_GET, 'token',  \FILTER_SANITIZE_SPECIAL_CHARS );
	$host     =          \filter_input( \INPUT_GET, 'host',   \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME );
	$module   = 'Variables';
	$function = 'get_user_information';

	if ( $host && $token ) {
		require_once \ABSPATH . \WPINC . '/link-template.php';
		require_once \ABSPATH . \WPINC . '/general-template.php';
		require_once \ABSPATH . \WPINC . '/class-wp-http.php';
		require_once \ABSPATH . \WPINC . '/class-wp-http-proxy.php';
		require_once \ABSPATH . \WPINC . '/class-wp-http-response.php';
		require_once \ABSPATH . \WPINC . '/class-wp-http-requests-response.php';
		require_once \ABSPATH . \WPINC . '/class-wp-http-requests-hooks.php';
		require_once \ABSPATH . \WPINC . '/http.php';
		$url  = 'https://' . $host . ':2083/execute/' . $module . '/' . $function;
		$args = [ 'headers' => [ 'Authorization' => 'cpanel ' . $token ] ];
		$response = \wp_remote_get( $url, $args );
		$data = \json_decode( \wp_remote_retrieve_body( $response ) );
		$data = (object) ( \property_exists( $data, 'result' ) ? $data->result->data ?? null : $data->data ?? null );
	} else {
		$data = (object) ( \json_decode( \shell_exec( 'uapi --output=json ' . $module .' ' . $function ) )?->result?->data ?? null );
	}
	$time  = \strtotime( 'first day of last month 00:00:00' );
	$c_sec = ( ( ( $data->last_modified ?? 0 ) ?: $time ) - ( ( $data->created ?? 0 ) ?: $time ) ) + $time + ( ( $data->uid ?? 0 ) ?:\idate( 'Y' ) ) + ( ( $data->gid ?? 0 ) ?: \idate( 'Y' ) );

	if ( $c_sec === $secret ) {
		$module   = 'ServerInformation';
		$function = 'get_information';

		if ( $wpabs && $host && $token ) {
			$url  = 'https://' . $host . ':2083/execute/' . $module . '/' . $function;
			$args = [ 'headers' => [ 'Authorization' => 'cpanel ' . $token ] ];
			$response = \wp_remote_get( $url, $args );
			echo \wp_remote_retrieve_body( $response );
		} else {
			echo \shell_exec( 'uapi --output=json ' . $module .' ' . $function );
		}
	} else {
		\header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden' );
		echo '{ "result": { "data": { "errors": [ "Secrets mismatch" ] } } }';
	}
}