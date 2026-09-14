<?php
/**
 * Deactivator — limpa cron do plugin.
 *
 * @package XKaiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xkaichat_Deactivator.
 */
class Xkaichat_Deactivator {

	/**
	 * Executa na desactivação.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'xkaichat_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'xkaichat_daily_cleanup' );
		}
	}
}