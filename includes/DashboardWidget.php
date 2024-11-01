<?php
declare( strict_types=1 );
namespace WebFacing\cPanel\Email;
use WebFacing\cPanel\UAPI;

/**
 * Exit if accessed directly
 */
\class_exists( __NAMESPACE__ . '\Main' ) || exit;

abstract class DashBoardWidget extends Main {

	public    static string  $class;

	protected static string  $id;

	protected static bool    $interference;

	public    static function admin(): void {

		$class = \explode( '\\', __CLASS__ );
		self::$class = \strtolower( \end( $class ) );

		self::$id = self::$pf . self::$class;

		\add_action( 'plugins_loaded', static function(): void {
			self::$interference =  ! UAPI::has_features( [ 'serverstatus' ] ) || \class_exists( 'WebFacing\cPanel\Charts' );
		} );

		\add_action( 'wp_dashboard_setup', static function(): void {
			$cap = \apply_filters( self::pf . 'widget_capability', 'manage_options' );

			if ( self::$is_cpanel && \current_user_can( $cap ) ) {

				\wp_add_dashboard_widget(
					self::$pf . 'widget',
					\esc_html( _x( 'cPanel® Resource Usage', 'Dashboard Widget Title' ) ),

					static function(): void {
						echo
							'<div id="',
							self::$id,
							'">',
							self::$interference ?
								_x(
									'No gauges can be displayed. Your cPanel® does not have the `serverstatus` feature (old version), or interference with the deprecated &raquo;WebFacing™ - Storage, resource usage and errors in cPanel®&raquo; plugin.',
									'Replacement Error',
								) :
								'',
							'</div>'
						;
					}
				);
			}
		} );

		\add_action( 'admin_enqueue_scripts', static function( string $hook ): void {

			if ( self::$is_cpanel && $hook === 'index.php' && ! self::$interference ) {
				$google = self::$pf . 'google-' . self::$class;
				$handle = self::$pf . self::$class;
				$pl_dir = \plugin_dir_url( PLUGIN_FILE ) . 'assets/';
				$beat   = \defined( 'AUTOSAVE_INTERVAL' ) ? ( \intval( \AUTOSAVE_INTERVAL ) ?: 60 ) : ( self::$has_http && ! self::$use_exec ? 30 : 10 );
				$beat   = self::$is_debug ? \intval( $beat / 5 ) : $beat;

				\wp_register_script( $google, 'https://www.gstatic.com/charts/loader.js' );
				\wp_register_script( $handle, $pl_dir . self::$class . '.js', [ $google ], self::$plugin->Version );
				\wp_localize_script( $handle, 'wFcPanelSettings', [
					'chartID'  => self::$id,
					'adminURI' => \admin_url(),
					'dataURI'  => $pl_dir . 'index.php',
					'secret'   => self::cpanel_secret(),
					'token'    => self::$has_http && ! self::$use_exec ? UAPI::$api_token : '',
					'host'     => self::$host_name,
					'abs'      => \trailingslashit( $_SERVER['DOCUMENT_ROOT'] ) === \ABSPATH ? null : \urlencode( \ABSPATH ),
					'interval' => (int) \apply_filters( self::pf . 'gauges_interval', $beat ),
					'labels'   => [
						'mem' => _x( 'Memory',   'Gauge Label' ),
//						'swa' => _x( 'Swap',     'Gauge Label' ),
						'con' => _x( 'Database', 'Gauge Label' ),
						'loa' => _x( 'Load',     'Gauge Label' ),
					],
				] );
				\wp_enqueue_script( $handle );
			}
		} );
	}

	protected static function cpanel_secret(): int {
		$time = \strtotime( 'first day of last month 00:00:00' );
		return ( ( UAPI::user_updated() ?: $time ) - ( UAPI::user_created() ?: $time ) ) + $time + ( UAPI::user_uid() ?: \idate( 'Y' ) ) + ( UAPI::user_gid() ?: \idate( 'Y' ) );
	}
}
